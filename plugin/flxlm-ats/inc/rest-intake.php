<?php
/**
 * The cross-site intake endpoint.
 *
 * fingerlakesdailynews.com carries the same job postings as this site and gets
 * most of the search traffic, but it is a different WordPress install on a
 * different host with a different database. The two share nothing. This
 * endpoint is how an application filled in over there becomes a record over
 * here, so there is one applicant pipeline rather than two.
 *
 * AUTHENTICATION
 *
 * A shared secret, used to sign the request rather than being sent as a
 * password. The signature covers the request body and a timestamp, and this end
 * rejects anything with a timestamp more than five minutes old. Signing rather
 * than sending means the secret never travels; including the timestamp means a
 * captured request cannot be replayed tomorrow to forge applications.
 *
 * The secret lives in wp-config.php on both sites, never in the repository:
 *
 *     define( 'FLXLM_ATS_RELAY_SECRET', '...' );
 *
 * WHAT THIS ENDPOINT PROMISES THE CALLER
 *
 * Exactly one thing: if it returns 200 with {"stored": true}, the application
 * is durably recorded here and the caller may stop retrying. Every other
 * outcome, including a timeout with no response at all, means "unknown, try
 * again" and the caller must keep the application queued. The endpoint is
 * idempotent on the caller's submission ID so that retrying after an ambiguous
 * failure cannot create a duplicate applicant.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** How much clock skew and transit delay to tolerate on a signed request. */
const FLXLM_ATS_RELAY_WINDOW = 300;

/**
 * Whether relay intake is configured at all.
 *
 * @return bool
 */
function flxlm_ats_relay_enabled() {
	return defined( 'FLXLM_ATS_RELAY_SECRET' ) && '' !== (string) FLXLM_ATS_RELAY_SECRET;
}

/**
 * Register the endpoint.
 */
function flxlm_ats_register_rest() {
	register_rest_route(
		'flxlm-ats/v1',
		'/application',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_rest_receive',
			// Authentication is the signature check inside the callback, not a
			// WordPress capability: the caller is a server, not a user.
			'permission_callback' => '__return_true',
		)
	);

	// A cheap liveness probe so the sending site can tell "endpoint is up" from
	// "endpoint is missing" without submitting anything.
	register_rest_route(
		'flxlm-ats/v1',
		'/ping',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				return new WP_REST_Response(
					array(
						'ok'      => true,
						'relay'   => flxlm_ats_relay_enabled(),
						'version' => FLXLM_ATS_VERSION,
					),
					200
				);
			},
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'flxlm_ats_register_rest' );

/**
 * Verify the signature on a relayed request.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function flxlm_ats_verify_relay( $request ) {
	if ( ! flxlm_ats_relay_enabled() ) {
		return new WP_Error(
			'flxlm_ats_relay_disabled',
			'Relay intake is not configured on this site.',
			array( 'status' => 503 )
		);
	}

	$timestamp = (int) $request->get_header( 'x-flxlm-timestamp' );
	$signature = (string) $request->get_header( 'x-flxlm-signature' );

	if ( ! $timestamp || '' === $signature ) {
		return new WP_Error( 'flxlm_ats_unsigned', 'Missing signature.', array( 'status' => 401 ) );
	}

	if ( abs( time() - $timestamp ) > FLXLM_ATS_RELAY_WINDOW ) {
		return new WP_Error(
			'flxlm_ats_stale',
			'Signature timestamp is outside the accepted window.',
			array( 'status' => 401 )
		);
	}

	$body     = $request->get_body();
	$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, (string) FLXLM_ATS_RELAY_SECRET );

	if ( ! hash_equals( $expected, $signature ) ) {
		return new WP_Error( 'flxlm_ats_bad_signature', 'Signature does not match.', array( 'status' => 401 ) );
	}

	return true;
}

/**
 * Receive a relayed application.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_rest_receive( $request ) {
	$verified = flxlm_ats_verify_relay( $request );
	if ( is_wp_error( $verified ) ) {
		return $verified;
	}

	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		return new WP_Error( 'flxlm_ats_bad_body', 'Expected a JSON body.', array( 'status' => 400 ) );
	}

	// Idempotency. The caller generates this once per application and reuses it
	// on every retry, so an ambiguous timeout followed by a retry produces one
	// applicant, not two.
	$submission_id = sanitize_text_field( $params['submission_id'] ?? '' );
	if ( '' === $submission_id ) {
		return new WP_Error( 'flxlm_ats_no_submission_id', 'submission_id is required.', array( 'status' => 400 ) );
	}

	$existing = flxlm_ats_find_by_submission_id( $submission_id );
	if ( $existing ) {
		return new WP_REST_Response(
			array(
				'stored'         => true,
				'duplicate'      => true,
				'application_id' => $existing,
			),
			200
		);
	}

	$fields = flxlm_ats_validate_fields( $params );
	if ( is_wp_error( $fields ) ) {
		// 422: the payload is understood and genuinely invalid. The caller must
		// NOT keep retrying this one; retrying will never make it valid.
		return new WP_Error(
			$fields->get_error_code(),
			$fields->get_error_message(),
			array( 'status' => 422 )
		);
	}

	$resume = null;
	if ( ! empty( $params['resume_base64'] ) ) {
		$resume = flxlm_ats_store_relayed_resume(
			$params['resume_base64'],
			$params['resume_name'] ?? 'resume.pdf'
		);
		if ( is_wp_error( $resume ) ) {
			return new WP_Error(
				$resume->get_error_code(),
				$resume->get_error_message(),
				array( 'status' => 422 )
			);
		}
	}

	$job = flxlm_ats_resolve_job(
		$params['job_title'] ?? '',
		$params['job_title'] ?? ''
	);

	$application_id = flxlm_ats_create_application(
		array(
			'fields'       => $fields,
			'job_id'       => $job['id'],
			'job_title'    => $job['title'],
			'resume'       => $resume,
			'entered_by'   => 'fldn-relay',
			'source_site'  => 'fldn',
			'submitted_at' => sanitize_text_field( $params['submitted_at'] ?? '' ),
		)
	);

	if ( is_wp_error( $application_id ) ) {
		// 500: something broke on this end. The caller SHOULD retry.
		return new WP_Error(
			'flxlm_ats_store_failed',
			'Could not store the application.',
			array( 'status' => 500 )
		);
	}

	update_post_meta( $application_id, '_flxlm_submission_id', $submission_id );

	return new WP_REST_Response(
		array(
			'stored'         => true,
			'duplicate'      => false,
			'application_id' => $application_id,
		),
		200
	);
}

/**
 * Look up an application by the sending site's submission ID.
 *
 * @param string $submission_id Idempotency key.
 * @return int 0 when not found.
 */
function flxlm_ats_find_by_submission_id( $submission_id ) {
	$found = get_posts(
		array(
			'post_type'        => 'flxlm_application',
			'post_status'      => array_keys( flxlm_ats_stages() ),
			'meta_key'         => '_flxlm_submission_id',
			'meta_value'       => (string) $submission_id,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return $found ? (int) $found[0] : 0;
}
