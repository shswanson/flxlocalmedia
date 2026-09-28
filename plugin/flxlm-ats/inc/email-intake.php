<?php
/**
 * Email intake: applications that arrived as email to jobs@flxlocalmedia.com
 * (a Google Group), read by a read-only script on the office Mac and POSTed
 * here. See scripts/careers/ats_email_intake.py in shswanson/fldn for the
 * reader; this file is only the receiving end.
 *
 * AUTHENTICATION IS ITS OWN SCHEME, NOT A REUSE OF THE FLDN RELAY'S
 *
 * inc/intake.php already has a signed-relay pattern (FLXLM_ATS_RELAY_SECRET),
 * but that secret is shared with fingerlakesdailynews.com, a second
 * WordPress install this plugin does not control. The email-intake job is a
 * script on a machine this company owns outright, so it gets its own secret
 * (FLXLM_ATS_EMAIL_INTAKE_SECRET) rather than widening what a compromise of
 * the FLDN relay secret could do. The signature SCHEME is intentionally the
 * same shape (HMAC-SHA256 over a timestamp and the body, verified with
 * hash_equals, inside a bounded window) because that shape is already proven
 * here in three places (inc/intake.php, inc/tokens.php, inc/hub-bridge.php)
 * and a fourth bespoke variant would only be a fourth thing to audit.
 *
 * WHY BOTH ROUTES ARE 503, NOT 401, WHEN THE SECRET IS MISSING
 *
 * A missing secret means this feature was never configured, not that a
 * caller failed to authenticate. 401 would tell a legitimate but
 * misconfigured intake script "keep retrying, your credentials are wrong";
 * 503 tells it "this endpoint is not available right now", which is the true
 * state and the one that should make an operator go check wp-config rather
 * than rotate a secret that was never set.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** Clock skew and transit delay tolerated on a signed email-intake request. */
const FLXLM_ATS_EMAIL_INTAKE_WINDOW = 300;

/** Ceiling on body_text, matching the contract's documented field size. */
const FLXLM_ATS_EMAIL_INTAKE_MAX_BODY = 20000;

/**
 * Whether email intake is configured at all.
 *
 * @return bool
 */
function flxlm_ats_email_intake_enabled() {
	return defined( 'FLXLM_ATS_EMAIL_INTAKE_SECRET' ) && '' !== (string) FLXLM_ATS_EMAIL_INTAKE_SECRET;
}

/**
 * Register the email-intake routes.
 */
function flxlm_ats_register_email_intake_rest() {
	register_rest_route(
		'flxlm-ats/v1',
		'/email-intake/jobs',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_email_intake_jobs',
			'permission_callback' => '__return_true', // Authenticated by signature inside the callback, not a WP capability: the caller is a script, not a user.
		)
	);

	register_rest_route(
		'flxlm-ats/v1',
		'/email-application',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_email_intake_receive',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'flxlm_ats_register_email_intake_rest' );

/**
 * Verify the signature on an email-intake request.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function flxlm_ats_verify_email_intake_signature( $request ) {
	if ( ! flxlm_ats_email_intake_enabled() ) {
		return new WP_Error(
			'flxlm_ats_email_intake_disabled',
			'Email intake is not configured on this site.',
			array( 'status' => 503 )
		);
	}

	$timestamp = (int) $request->get_header( 'x-flx-intake-timestamp' );
	$signature = strtolower( (string) $request->get_header( 'x-flx-intake-signature' ) );

	if ( ! $timestamp || '' === $signature ) {
		return new WP_Error( 'flxlm_ats_email_intake_unsigned', 'Missing signature.', array( 'status' => 401 ) );
	}

	if ( abs( time() - $timestamp ) > FLXLM_ATS_EMAIL_INTAKE_WINDOW ) {
		return new WP_Error(
			'flxlm_ats_email_intake_stale',
			'Signature timestamp is outside the accepted window.',
			array( 'status' => 401 )
		);
	}

	$body     = (string) $request->get_body();
	$payload  = 'email-intake-v1.' . $timestamp . '.' . $body;
	$expected = hash_hmac( 'sha256', $payload, (string) FLXLM_ATS_EMAIL_INTAKE_SECRET );

	if ( ! hash_equals( $expected, $signature ) ) {
		return new WP_Error( 'flxlm_ats_email_intake_bad_signature', 'Signature does not match.', array( 'status' => 401 ) );
	}

	return true;
}

/**
 * GET /email-intake/jobs — published postings, for the intake script's job
 * matching. Signed over an empty body (a GET carries none).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_email_intake_jobs( $request ) {
	$verified = flxlm_ats_verify_email_intake_signature( $request );
	if ( is_wp_error( $verified ) ) {
		return $verified;
	}

	$jobs = get_posts(
		array(
			'post_type'      => 'flxlm_job',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	$out = array();
	foreach ( $jobs as $job ) {
		$out[] = array(
			'id'     => $job->ID,
			'title'  => wp_specialchars_decode( get_the_title( $job ), ENT_QUOTES ),
			'slug'   => $job->post_name,
			'status' => $job->post_status,
		);
	}

	return new WP_REST_Response( $out, 200 );
}

/**
 * Find an application already imported from a given Gmail message, so a
 * re-run of the intake script (or a retry after an ambiguous response) never
 * creates a second record for the same email.
 *
 * @param string $message_id Gmail message id.
 * @return int 0 when not found.
 */
function flxlm_ats_find_by_intake_key( $message_id ) {
	$key   = 'gmail:' . $message_id;
	$found = get_posts(
		array(
			'post_type'        => 'flxlm_application',
			'post_status'      => array_keys( flxlm_ats_stages() ),
			'meta_key'         => '_flxlm_intake_key',
			'meta_value'       => $key,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	return $found ? (int) $found[0] : 0;
}

/**
 * Split a display name into a first and last name for the application record.
 *
 * A forwarded or Indeed-notification email rarely arrives with separate
 * first/last fields the way the web form collects them, only one display
 * name. First token is the first name, everything after it is the last name;
 * a single-token name (or none at all) leaves last name blank rather than
 * inventing one, since a guessed surname would be worse than an honest gap.
 *
 * @param string $name Full display name.
 * @return array{0:string,1:string}
 */
function flxlm_ats_split_name( $name ) {
	$name  = trim( sanitize_text_field( (string) $name ) );
	if ( '' === $name ) {
		return array( '', '' );
	}
	$parts = preg_split( '/\s+/', $name, 2 );
	return array( $parts[0], $parts[1] ?? '' );
}

/**
 * POST /email-application — create (or find the duplicate of) an application
 * imported from email.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_email_intake_receive( $request ) {
	$verified = flxlm_ats_verify_email_intake_signature( $request );
	if ( is_wp_error( $verified ) ) {
		return $verified;
	}

	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		return flxlm_ats_email_intake_error( 400, 'bad_body', 'Expected a JSON body.' );
	}

	$message_id = sanitize_text_field( $params['message_id'] ?? '' );
	if ( '' === $message_id ) {
		return flxlm_ats_email_intake_error( 400, 'missing_message_id', 'message_id is required.' );
	}

	$existing = flxlm_ats_find_by_intake_key( $message_id );
	if ( $existing ) {
		return new WP_REST_Response( array( 'status' => 'duplicate', 'application_id' => $existing ), 200 );
	}

	$from_email = sanitize_email( $params['from_email'] ?? '' );
	if ( ! is_email( $from_email ) ) {
		return flxlm_ats_email_intake_error( 400, 'invalid_email', 'from_email is required and must be a valid address.' );
	}

	$source = sanitize_key( $params['source'] ?? 'unknown' );
	if ( ! flxlm_ats_is_source( $source ) ) {
		return flxlm_ats_email_intake_error( 422, 'invalid_source', 'Unknown source: ' . $source );
	}

	$job_match = sanitize_key( $params['job_match'] ?? 'none' );
	if ( ! in_array( $job_match, array( 'exact', 'fuzzy', 'none' ), true ) ) {
		$job_match = 'none';
	}

	list( $first, $last ) = flxlm_ats_split_name( $params['from_name'] ?? '' );
	if ( '' === $first ) {
		// No usable name at all. Fall back to the local part of the address
		// rather than rejecting the application outright: an applicant who is
		// real and reachable by email should not be lost over a missing header.
		$first = sanitize_text_field( strstr( $from_email, '@', true ) );
		$first = $first ? $first : 'Applicant';
	}

	$body_text = (string) ( $params['body_text'] ?? '' );
	if ( strlen( $body_text ) > FLXLM_ATS_EMAIL_INTAKE_MAX_BODY ) {
		$body_text = substr( $body_text, 0, FLXLM_ATS_EMAIL_INTAKE_MAX_BODY );
	}

	$job_id = isset( $params['job_id'] ) && is_numeric( $params['job_id'] ) ? (int) $params['job_id'] : 0;
	$job    = flxlm_ats_resolve_job( $job_id ? $job_id : '', '' );

	$fields = array(
		'first_name'         => $first,
		'last_name'          => $last,
		'email'              => $from_email,
		'phone'              => '',
		'source'             => $source,
		'message'            => '', // The body goes into a system note instead — see below — so it sits on the timeline with the "Imported from email" context rather than looking like the applicant typed it into our form.
		'links'              => '',
		'salary_expectation' => '',
	);

	$resume       = null;
	$resume_note  = '';
	$resume_input = $params['resume'] ?? null;
	if ( is_array( $resume_input ) && ! empty( $resume_input['content_base64'] ) ) {
		$stored = flxlm_ats_store_relayed_resume(
			$resume_input['content_base64'],
			$resume_input['filename'] ?? 'resume'
		);
		if ( is_wp_error( $stored ) ) {
			// Per contract: a resume that fails validation does not block the
			// application. Recorded as a system note instead so a human can go
			// get the real file from the original email.
			$resume_note = 'The attached resume could not be stored: ' . $stored->get_error_message();
		} else {
			$resume = $stored;
		}
	}

	$submitted_at = sanitize_text_field( $params['received_at'] ?? '' );
	$submitted_at = $submitted_at ? gmdate( 'Y-m-d H:i:s', strtotime( $submitted_at ) ?: time() ) : '';

	$application_id = flxlm_ats_create_application(
		array(
			'fields'       => $fields,
			'job_id'       => $job['id'],
			'job_title'    => $job['title'],
			'resume'       => $resume,
			'entered_by'   => 'email-intake',
			'source_site'  => 'email',
			'submitted_at' => $submitted_at,
		)
	);

	if ( is_wp_error( $application_id ) ) {
		return flxlm_ats_email_intake_error( 500, 'store_failed', 'Could not store the application.' );
	}

	update_post_meta( $application_id, '_flxlm_intake_key', 'gmail:' . $message_id );
	update_post_meta( $application_id, '_flxlm_job_match', $job_match );
	$forwarded_by = sanitize_email( $params['forwarded_by'] ?? '' );
	if ( $forwarded_by ) {
		update_post_meta( $application_id, '_flxlm_forwarded_by', $forwarded_by );
	}

	if ( function_exists( 'flxlm_ats_add_note' ) ) {
		$subject = sanitize_text_field( $params['subject'] ?? '(no subject)' );
		$imported_body = 'Imported from email: ' . $subject;
		if ( $body_text ) {
			$imported_body .= "\n\n" . $body_text;
		}
		flxlm_ats_add_note( $application_id, 'system', array( 'body' => $imported_body ) );

		if ( $resume_note ) {
			flxlm_ats_add_note( $application_id, 'system', array( 'body' => $resume_note ) );
		}
	}

	// The normal new-application notification: hiring manager, else job_email,
	// else the business managers. No applicant receipt — inc/notify.php skips
	// it for entered_by === 'email-intake', same as a manual entry.
	do_action( 'flxlm_ats_application_received', $application_id, array( 'entered_by' => 'email-intake' ) );

	return new WP_REST_Response( array( 'status' => 'created', 'application_id' => $application_id ), 200 );
}

/**
 * Build a {error, code} response body at the given HTTP status.
 *
 * A WP_Error returned directly from a REST callback serialises as
 * {code, message, data}, not the {error, code} shape the v1 contract
 * specifies for this route ("400/422 {error, code} for malformed input"), so
 * this builds the response body explicitly instead.
 *
 * @param int    $status  HTTP status.
 * @param string $code    Machine-readable code.
 * @param string $message Human message.
 * @return WP_REST_Response
 */
function flxlm_ats_email_intake_error( $status, $code, $message ) {
	$response = new WP_REST_Response(
		array(
			'error' => $message,
			'code'  => $code,
		),
		$status
	);
	return $response;
}
