<?php
/**
 * AI resume summary: a fixed-rubric, facts-only summary of each applicant's
 * resume, produced by a job on the office Air and stored here for hiring staff.
 * See scripts/careers/ats_ai_summary.py in shswanson/fldn for the producer;
 * this file is the receiving end, the validator, and the wp-admin card.
 * Approved by Scott on 2026-09-30 (github.com/shswanson/fldn/issues/2035),
 * deliberately without any applicant-facing disclosure: the summary is shown
 * only to FLX hiring staff.
 *
 * WHAT THE SUMMARY IS, AND WHAT IT MUST NEVER BE
 *
 * One fixed rubric (version "v1"), identical for every applicant: roles as
 * durations, years of office work, years of service-industry work (reported
 * separately, never excluded or penalised), other years, education level and
 * field, skills, and a short factual overview. It carries no score, rating,
 * ranking, fit judgment or recommendation, and it never feeds an exit test, a
 * sort, a filter or a stage move. Nothing in this plugin reads it for any
 * decision; it is displayed, and that is all.
 *
 * The rubric keeps out, by construction, the things an employer must not
 * collect or weigh at this stage: calendar years and dates (a graduation or
 * birth year is an age proxy, so durations only), pay history (NY Labor Law
 * 194-a), criminal history before an offer (NY Correction Law 23-A), and
 * anything about health, family, religion, politics, origin, race or gender.
 * The producer is told all of this, but a prompt is a request, not a control.
 * The controls are here: the validator below accepts only the exact shape of
 * the rubric, strips HTML, and refuses any text field containing a four-digit
 * year from 1940 to 2029, so a year that slips through a model's output is
 * rejected at the door rather than stored.
 *
 * AUTHENTICATION
 *
 * The same scheme as inc/email-intake.php: the same secret
 * (FLXLM_ATS_EMAIL_INTAKE_SECRET), the same headers (X-FLX-Intake-Timestamp,
 * X-FLX-Intake-Signature), the same 300 second window, but a different domain
 * prefix, so a signature minted for one purpose can never be replayed against
 * the other:
 *
 *   HMAC-SHA256( "ai-summary-v1." . timestamp . "." . raw_body, secret )
 *
 * lowercase hex. A GET signs an empty body. Missing secret is 503 (the feature
 * was never configured), not 401, for the reason given in email-intake.php.
 *
 * WHERE THE RESULT LIVES
 *
 *   * Post meta _flxlm_ai_summary: the CURRENT summary,
 *     {version, model, status, summary, created_at}, replaced on every post.
 *   * Post meta _flxlm_ai_summary_version: a DERIVED copy of the version, kept
 *     only so the queue can ask the database "which applications have no
 *     summary at this version" without unserialising every record.
 *   * The append-only notes table, kind 'ai_summary': one row per accepted
 *     summary, so the history of what the tool said and when survives a
 *     replacement. Only a status of 'ok' appends a note; the other statuses
 *     are bookkeeping that stops the queue retrying, not something a reader
 *     needs on the timeline.
 *   * The hub audit table: every accepted post and every resume handed to the
 *     job, like every other write and every resume read in this plugin.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** Post meta holding the current summary record. */
const FLXLM_ATS_AI_SUMMARY_META = '_flxlm_ai_summary';

/** Derived post meta: the version of the stored record, for the queue query. */
const FLXLM_ATS_AI_SUMMARY_VERSION_META = '_flxlm_ai_summary_version';

/** Most applications one queue call may return. */
const FLXLM_ATS_AI_SUMMARY_MAX_LIMIT = 10;

/** Queue default when no limit is given. */
const FLXLM_ATS_AI_SUMMARY_DEFAULT_LIMIT = 5;

/**
 * Ceiling on the base64 bytes one queue response carries. A resume may be up
 * to 8 MB (FLXLM_ATS_MAX_RESUME_BYTES), so ten of them would be a response
 * near 110 MB held in PHP memory. The queue stops adding items once this is
 * passed (it always returns at least one), and the job simply asks again.
 */
const FLXLM_ATS_AI_SUMMARY_MAX_RESPONSE_BYTES = 25165824;

/** Rubric limits. The producer script mirrors these exactly. */
const FLXLM_ATS_AI_SUMMARY_MAX_ROLES      = 12;
const FLXLM_ATS_AI_SUMMARY_MAX_SKILLS     = 12;
const FLXLM_ATS_AI_SUMMARY_MAX_OVERVIEW   = 600;
const FLXLM_ATS_AI_SUMMARY_MAX_TITLE      = 120;
const FLXLM_ATS_AI_SUMMARY_MAX_EMPLOYER   = 120;
const FLXLM_ATS_AI_SUMMARY_MAX_FIELD      = 100;
const FLXLM_ATS_AI_SUMMARY_MAX_SKILL      = 60;
const FLXLM_ATS_AI_SUMMARY_MAX_ROLE_YEARS = 60;
const FLXLM_ATS_AI_SUMMARY_MAX_TOTAL_YEARS = 70;

/**
 * Rubric versions this site accepts.
 *
 * @return string[]
 */
function flxlm_ats_ai_summary_versions() {
	return array( 'v1' );
}

/**
 * The statuses a post may carry.
 *
 * @return string[]
 */
function flxlm_ats_ai_summary_statuses() {
	return array( 'ok', 'no_resume', 'unreadable', 'failed' );
}

/**
 * The education levels the rubric allows, value => plain label.
 *
 * @return array<string,string>
 */
function flxlm_ats_ai_summary_education_levels() {
	return array(
		'none_listed' => 'None listed',
		'high_school' => 'High school',
		'some_college' => 'Some college',
		'associate'   => 'Associate degree',
		'bachelor'    => 'Bachelor degree',
		'master'      => 'Master degree',
		'doctorate'   => 'Doctorate',
		'other'       => 'Other',
	);
}

/**
 * Register the AI summary routes.
 */
function flxlm_ats_register_ai_summary_rest() {
	register_rest_route(
		'flxlm-ats/v1',
		'/ai-summary/queue',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_ai_summary_queue',
			'permission_callback' => '__return_true', // Authenticated by signature inside the callback: the caller is a script, not a user.
		)
	);

	register_rest_route(
		'flxlm-ats/v1',
		'/applications/(?P<id>\d+)/ai-summary',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_ai_summary_receive',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'flxlm_ats_register_ai_summary_rest' );

/**
 * Verify the signature on an AI summary request.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function flxlm_ats_verify_ai_summary_signature( $request ) {
	if ( ! flxlm_ats_email_intake_enabled() ) {
		return new WP_Error(
			'flxlm_ats_ai_summary_disabled',
			'AI summary is not configured on this site.',
			array( 'status' => 503 )
		);
	}

	$timestamp = (int) $request->get_header( 'x-flx-intake-timestamp' );
	$signature = strtolower( (string) $request->get_header( 'x-flx-intake-signature' ) );

	if ( ! $timestamp || '' === $signature ) {
		return new WP_Error( 'flxlm_ats_ai_summary_unsigned', 'Missing signature.', array( 'status' => 401 ) );
	}

	if ( abs( time() - $timestamp ) > FLXLM_ATS_EMAIL_INTAKE_WINDOW ) {
		return new WP_Error(
			'flxlm_ats_ai_summary_stale',
			'Signature timestamp is outside the accepted window.',
			array( 'status' => 401 )
		);
	}

	$body     = (string) $request->get_body();
	$expected = hash_hmac( 'sha256', 'ai-summary-v1.' . $timestamp . '.' . $body, (string) FLXLM_ATS_EMAIL_INTAKE_SECRET );

	if ( ! hash_equals( $expected, $signature ) ) {
		return new WP_Error( 'flxlm_ats_ai_summary_bad_signature', 'Signature does not match.', array( 'status' => 401 ) );
	}

	return true;
}

/**
 * A 422 response in the contract's shape: {error, errors:[{field,message}]}.
 *
 * @param array  $errors  List of {field, message}.
 * @param string $summary One-line summary for the top-level error.
 * @param int    $status  HTTP status.
 * @return WP_REST_Response
 */
function flxlm_ats_ai_summary_invalid( $errors, $summary = 'The request did not pass validation.', $status = 422 ) {
	$response = new WP_REST_Response(
		array(
			'error'  => $summary,
			'errors' => array_values( $errors ),
		),
		$status
	);
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

/**
 * GET /ai-summary/queue?version=v1&limit=5
 *
 * Applications, any stage, oldest first, that have no summary record at the
 * requested version. A record of ANY status counts as having one: 'failed' and
 * 'no_resume' exist precisely so the queue stops offering that application.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_ai_summary_queue( $request ) {
	$verified = flxlm_ats_verify_ai_summary_signature( $request );
	if ( is_wp_error( $verified ) ) {
		return $verified;
	}

	$errors  = array();
	$version = $request->get_param( 'version' );
	$version = null === $version ? 'v1' : $version;
	if ( ! is_string( $version ) || ! in_array( $version, flxlm_ats_ai_summary_versions(), true ) ) {
		$errors[] = array( 'field' => 'version', 'message' => 'Unknown version. Accepted: ' . implode( ', ', flxlm_ats_ai_summary_versions() ) . '.' );
	}

	$limit = $request->get_param( 'limit' );
	if ( null === $limit ) {
		$limit = FLXLM_ATS_AI_SUMMARY_DEFAULT_LIMIT;
	} elseif ( is_string( $limit ) && preg_match( '/^[0-9]{1,3}$/', $limit ) ) {
		$limit = (int) $limit;
	} else {
		$limit = 0;
	}
	if ( $limit < 1 || $limit > FLXLM_ATS_AI_SUMMARY_MAX_LIMIT ) {
		$errors[] = array( 'field' => 'limit', 'message' => 'limit must be a whole number from 1 to ' . FLXLM_ATS_AI_SUMMARY_MAX_LIMIT . '.' );
	}

	if ( $errors ) {
		return flxlm_ats_ai_summary_invalid( $errors );
	}

	$ids = get_posts(
		array(
			'post_type'        => 'flxlm_application',
			'post_status'      => array_keys( flxlm_ats_stages() ),
			'posts_per_page'   => $limit,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a few hundred applications at most; the query is the point of the derived meta key.
				'relation' => 'OR',
				array(
					'key'     => FLXLM_ATS_AI_SUMMARY_VERSION_META,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => FLXLM_ATS_AI_SUMMARY_VERSION_META,
					'value'   => $version,
					'compare' => '!=',
				),
			),
		)
	);

	$out   = array();
	$bytes = 0;
	foreach ( $ids as $application_id ) {
		$application_id = (int) $application_id;
		$resume         = flxlm_ats_ai_summary_queue_resume( $application_id );

		$size = $resume ? strlen( $resume['content_base64'] ) : 0;
		if ( $out && $bytes + $size > FLXLM_ATS_AI_SUMMARY_MAX_RESPONSE_BYTES ) {
			break; // The job asks again; this keeps one response bounded.
		}
		$bytes += $size;

		$out[] = array(
			'application_id' => $application_id,
			'job_title'      => wp_specialchars_decode( flxlm_ats_job_title( $application_id ), ENT_QUOTES ),
			'resume'         => $resume,
		);

		if ( $resume ) {
			flxlm_ats_ai_summary_audit( 'ai_summary_fetch:' . $version, $application_id );
		}
	}

	$response = new WP_REST_Response( $out, 200 );
	$response->header( 'Cache-Control', 'no-store, private' ); // Resume bytes: FCC EEO PII, never cached by anything in between.
	return $response;
}

/**
 * One application's resume as the queue hands it over, or null.
 *
 * @param int $application_id Application ID.
 * @return array|null {filename, mime, content_base64}
 */
function flxlm_ats_ai_summary_queue_resume( $application_id ) {
	$resume_id = (int) get_post_meta( $application_id, '_flxlm_resume_file', true );
	if ( $resume_id < 1 ) {
		return null;
	}

	$meta  = flxlm_ats_get_resume_meta( $resume_id );
	$bytes = $meta ? flxlm_ats_get_resume_bytes( $resume_id ) : null;
	if ( ! $meta || null === $bytes || '' === $bytes ) {
		return null;
	}

	$filename = (string) get_post_meta( $application_id, '_flxlm_resume_name', true );
	if ( '' === $filename ) {
		$filename = (string) $meta['file_name'];
	}

	return array(
		'filename'       => $filename,
		'mime'           => (string) $meta['mime_type'],
		'content_base64' => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport encoding of a stored file, not obfuscation.
	);
}

/**
 * POST /applications/{id}/ai-summary
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_ai_summary_receive( $request ) {
	$verified = flxlm_ats_verify_ai_summary_signature( $request );
	if ( is_wp_error( $verified ) ) {
		return $verified;
	}

	$application_id = (int) $request->get_param( 'id' );
	if ( ! flxlm_ats_get_application( $application_id ) ) {
		$response = new WP_REST_Response( array( 'error' => 'No such application.' ), 404 );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	$params = $request->get_json_params();
	if ( ! is_array( $params ) || flxlm_ats_ai_summary_is_list( $params ) ) {
		return flxlm_ats_ai_summary_invalid(
			array( array( 'field' => 'body', 'message' => 'Expected a JSON object.' ) ),
			'The request body must be a JSON object.',
			400
		);
	}

	$validated = flxlm_ats_ai_summary_validate_post( $params );
	if ( $validated['errors'] ) {
		return flxlm_ats_ai_summary_invalid( $validated['errors'] );
	}

	$record = array(
		'version'    => $validated['version'],
		'model'      => $validated['model'],
		'status'     => $validated['status'],
		'summary'    => $validated['summary'],
		'created_at' => gmdate( 'Y-m-d H:i:s' ),
	);

	// update_post_meta() runs wp_unslash() on what it is given, so anything
	// with a backslash in it would be silently altered without this.
	update_post_meta( $application_id, FLXLM_ATS_AI_SUMMARY_META, wp_slash( $record ) );
	update_post_meta( $application_id, FLXLM_ATS_AI_SUMMARY_VERSION_META, $record['version'] );

	$note_id = 0;
	if ( 'ok' === $record['status'] && function_exists( 'flxlm_ats_add_note' ) ) {
		$added = flxlm_ats_add_note(
			$application_id,
			'ai_summary',
			array(
				'body'        => flxlm_ats_ai_summary_note_text( $record ),
				'author_name' => $record['model'],
			)
		);
		$note_id = is_wp_error( $added ) ? 0 : (int) $added;
	}

	flxlm_ats_ai_summary_audit( 'ai_summary:' . $record['status'] . ':' . $record['version'], $application_id );

	$response = new WP_REST_Response(
		array(
			'status'         => 'stored',
			'application_id' => $application_id,
			'version'        => $record['version'],
			'summary_status' => $record['status'],
			'note_id'        => $note_id,
		),
		200
	);
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

// ---------------------------------------------------------------------------
// Validation. Strict on purpose: the exact rubric shape, nothing else.
// ---------------------------------------------------------------------------

/**
 * Whether an array is a non-empty JSON list (sequential integer keys) rather
 * than an object. An empty array is neither: callers treat it as an empty
 * object or an empty list as the field requires.
 *
 * @param array $value Decoded JSON.
 * @return bool
 */
function flxlm_ats_ai_summary_is_list( $value ) {
	return $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
}

/**
 * Whether a string contains a four-digit year from 1940 to 2029.
 *
 * Checks digits after folding full-width and other Unicode digits to ASCII,
 * so a year written in another digit script does not slip past.
 *
 * @param string $text Cleaned text.
 * @return bool
 */
function flxlm_ats_ai_summary_has_year( $text ) {
	$norm = (string) $text;

	if ( function_exists( 'mb_convert_kana' ) ) {
		$norm = mb_convert_kana( $norm, 'n', 'UTF-8' );
	}
	if ( class_exists( 'IntlChar' ) ) {
		$mapped = preg_replace_callback(
			'/\p{Nd}/u',
			function ( $m ) {
				$value = IntlChar::charDigitValue( $m[0] );
				return $value >= 0 ? (string) $value : $m[0];
			},
			$norm
		);
		if ( null !== $mapped ) {
			$norm = $mapped;
		}
	}

	return (bool) preg_match( '/(?<!\d)(?:19[4-9]\d|20[0-2]\d)(?!\d)/', $norm );
}

/**
 * Reduce free text to plain single-line text: no HTML (including entity
 * encoded HTML), no control or invisible characters, collapsed whitespace.
 *
 * @param string $text Raw text.
 * @return string|null Null when the text is not valid UTF-8.
 */
function flxlm_ats_ai_summary_clean_text( $text ) {
	$text = (string) $text;
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
		return null;
	}

	// Strip, decode entities, strip again: "&lt;b&gt;" must not become a tag
	// after the first pass, and "&#50;&#48;&#49;&#48;" must be seen as a year.
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = wp_strip_all_tags( $text );

	$text = preg_replace( '/[\x{00AD}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', $text );
	if ( null === $text ) {
		return null;
	}
	$text = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $text );
	$text = preg_replace( '/\s+/u', ' ', (string) $text );

	return null === $text ? null : trim( $text );
}

/**
 * Validate one text field. Appends to $errors and returns the cleaned string,
 * or null on failure.
 *
 * @param mixed  $value       Decoded JSON value.
 * @param string $path        Field path for error messages.
 * @param int    $max         Maximum length in characters.
 * @param bool   $allow_empty Whether "" is acceptable.
 * @param array  $errors      Error list, by reference.
 * @return string|null
 */
function flxlm_ats_ai_summary_check_text( $value, $path, $max, $allow_empty, &$errors ) {
	if ( ! is_string( $value ) ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must be a string.' );
		return null;
	}

	$clean = flxlm_ats_ai_summary_clean_text( $value );
	if ( null === $clean ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must be valid UTF-8 text.' );
		return null;
	}

	if ( '' === $clean && ! $allow_empty ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must not be empty.' );
		return null;
	}

	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $clean, 'UTF-8' ) : strlen( $clean );
	if ( $length > $max ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must be at most ' . $max . ' characters.' );
		return null;
	}

	if ( flxlm_ats_ai_summary_has_year( $clean ) ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must not contain a calendar year. The summary uses durations only.' );
		return null;
	}

	return $clean;
}

/**
 * Validate one number: an int or float, never a string or bool, within
 * [0, $max], with at most one decimal place.
 *
 * @param mixed  $value  Decoded JSON value.
 * @param string $path   Field path.
 * @param float  $max    Upper bound.
 * @param array  $errors Error list, by reference.
 * @return float|null
 */
function flxlm_ats_ai_summary_check_number( $value, $path, $max, &$errors ) {
	if ( ! ( is_int( $value ) || is_float( $value ) ) ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must be a number.' );
		return null;
	}

	$number = (float) $value;
	if ( ! is_finite( $number ) || $number < 0 || $number > $max ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must be between 0 and ' . $max . '.' );
		return null;
	}

	if ( abs( $number * 10 - round( $number * 10 ) ) > 0.000001 ) {
		$errors[] = array( 'field' => $path, 'message' => 'Must have at most one decimal place.' );
		return null;
	}

	return round( $number, 1 );
}

/**
 * Add an "unknown key" error for every key outside $allowed.
 *
 * @param array    $object  Decoded JSON object.
 * @param string[] $allowed Allowed keys.
 * @param string   $prefix  Path prefix ('' at the top level).
 * @param array    $errors  Error list, by reference.
 */
function flxlm_ats_ai_summary_check_keys( $object, $allowed, $prefix, &$errors ) {
	foreach ( array_keys( $object ) as $key ) {
		if ( ! in_array( (string) $key, $allowed, true ) ) {
			$errors[] = array( 'field' => $prefix . $key, 'message' => 'Unknown key.' );
		}
	}
}

/**
 * Validate the body of POST /applications/{id}/ai-summary.
 *
 * @param array $params Decoded JSON body.
 * @return array {errors, version, model, status, summary}
 */
function flxlm_ats_ai_summary_validate_post( $params ) {
	$errors = array();

	flxlm_ats_ai_summary_check_keys( $params, array( 'version', 'model', 'status', 'summary' ), '', $errors );

	$version = $params['version'] ?? null;
	if ( ! is_string( $version ) || ! in_array( $version, flxlm_ats_ai_summary_versions(), true ) ) {
		$errors[] = array( 'field' => 'version', 'message' => 'Must be one of: ' . implode( ', ', flxlm_ats_ai_summary_versions() ) . '.' );
		$version  = null;
	}

	// The model is metadata, not free text, so it is pattern checked rather
	// than year checked: real model ids can carry an 8 digit date suffix.
	$model = $params['model'] ?? null;
	if ( ! is_string( $model ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:\/\[\] +-]{0,63}$/', $model ) ) {
		$errors[] = array( 'field' => 'model', 'message' => 'Must be a short model name of letters, digits and . _ : / [ ] + - (at most 64 characters).' );
		$model    = null;
	}

	$status = $params['status'] ?? null;
	if ( ! is_string( $status ) || ! in_array( $status, flxlm_ats_ai_summary_statuses(), true ) ) {
		$errors[] = array( 'field' => 'status', 'message' => 'Must be one of: ' . implode( ', ', flxlm_ats_ai_summary_statuses() ) . '.' );
		$status   = null;
	}

	$summary = null;
	if ( 'ok' === $status ) {
		if ( ! array_key_exists( 'summary', $params ) || ! is_array( $params['summary'] ) || flxlm_ats_ai_summary_is_list( $params['summary'] ) ) {
			$errors[] = array( 'field' => 'summary', 'message' => 'Required as an object when status is ok.' );
		} else {
			$summary = flxlm_ats_ai_summary_validate_summary( $params['summary'], $errors );
		}
	} elseif ( null !== $status && isset( $params['summary'] ) ) {
		$errors[] = array( 'field' => 'summary', 'message' => 'Must be null unless status is ok.' );
	}

	return array(
		'errors'  => $errors,
		'version' => $version,
		'model'   => $model,
		'status'  => $status,
		'summary' => $summary,
	);
}

/**
 * Validate the summary object against the v1 rubric.
 *
 * Every key is required. A resume with no skills sends an empty list, not a
 * missing key, so a truncated model answer cannot pass for a complete one.
 *
 * @param array $summary Decoded summary object.
 * @param array $errors  Error list, by reference.
 * @return array|null Cleaned summary, or null when there are errors.
 */
function flxlm_ats_ai_summary_validate_summary( $summary, &$errors ) {
	$before = count( $errors );

	$keys = array( 'roles', 'years_office', 'years_service_industry', 'years_other', 'education', 'skills', 'overview' );
	flxlm_ats_ai_summary_check_keys( $summary, $keys, 'summary.', $errors );
	foreach ( $keys as $key ) {
		if ( ! array_key_exists( $key, $summary ) ) {
			$errors[] = array( 'field' => 'summary.' . $key, 'message' => 'Required.' );
		}
	}

	$clean = array();

	// roles: up to 12 of {title, employer, duration_years}.
	$clean['roles'] = array();
	if ( array_key_exists( 'roles', $summary ) ) {
		$roles = $summary['roles'];
		if ( ! is_array( $roles ) || ( $roles && ! flxlm_ats_ai_summary_is_list( $roles ) ) ) {
			$errors[] = array( 'field' => 'summary.roles', 'message' => 'Must be a list.' );
		} elseif ( count( $roles ) > FLXLM_ATS_AI_SUMMARY_MAX_ROLES ) {
			$errors[] = array( 'field' => 'summary.roles', 'message' => 'At most ' . FLXLM_ATS_AI_SUMMARY_MAX_ROLES . ' roles.' );
		} else {
			foreach ( $roles as $i => $role ) {
				$path = 'summary.roles[' . $i . ']';
				if ( ! is_array( $role ) || flxlm_ats_ai_summary_is_list( $role ) ) {
					$errors[] = array( 'field' => $path, 'message' => 'Must be an object with title, employer and duration_years.' );
					continue;
				}
				flxlm_ats_ai_summary_check_keys( $role, array( 'title', 'employer', 'duration_years' ), $path . '.', $errors );
				foreach ( array( 'title', 'employer', 'duration_years' ) as $k ) {
					if ( ! array_key_exists( $k, $role ) ) {
						$errors[] = array( 'field' => $path . '.' . $k, 'message' => 'Required.' );
					}
				}
				$title    = array_key_exists( 'title', $role ) ? flxlm_ats_ai_summary_check_text( $role['title'], $path . '.title', FLXLM_ATS_AI_SUMMARY_MAX_TITLE, false, $errors ) : null;
				$employer = array_key_exists( 'employer', $role ) ? flxlm_ats_ai_summary_check_text( $role['employer'], $path . '.employer', FLXLM_ATS_AI_SUMMARY_MAX_EMPLOYER, true, $errors ) : null;
				$years    = array_key_exists( 'duration_years', $role ) ? flxlm_ats_ai_summary_check_number( $role['duration_years'], $path . '.duration_years', FLXLM_ATS_AI_SUMMARY_MAX_ROLE_YEARS, $errors ) : null;
				if ( null !== $title && null !== $employer && null !== $years ) {
					$clean['roles'][] = array(
						'title'          => $title,
						'employer'       => $employer,
						'duration_years' => $years,
					);
				}
			}
		}
	}

	foreach ( array( 'years_office', 'years_service_industry', 'years_other' ) as $key ) {
		$clean[ $key ] = null;
		if ( array_key_exists( $key, $summary ) ) {
			$clean[ $key ] = flxlm_ats_ai_summary_check_number( $summary[ $key ], 'summary.' . $key, FLXLM_ATS_AI_SUMMARY_MAX_TOTAL_YEARS, $errors );
		}
	}

	// education: {level, field}.
	$clean['education'] = null;
	if ( array_key_exists( 'education', $summary ) ) {
		$education = $summary['education'];
		if ( ! is_array( $education ) || flxlm_ats_ai_summary_is_list( $education ) ) {
			$errors[] = array( 'field' => 'summary.education', 'message' => 'Must be an object with level and field.' );
		} else {
			flxlm_ats_ai_summary_check_keys( $education, array( 'level', 'field' ), 'summary.education.', $errors );
			$level = $education['level'] ?? null;
			if ( ! is_string( $level ) || ! array_key_exists( $level, flxlm_ats_ai_summary_education_levels() ) ) {
				$errors[] = array( 'field' => 'summary.education.level', 'message' => 'Must be one of: ' . implode( ', ', array_keys( flxlm_ats_ai_summary_education_levels() ) ) . '.' );
				$level    = null;
			}
			$field = null;
			if ( ! array_key_exists( 'field', $education ) ) {
				$errors[] = array( 'field' => 'summary.education.field', 'message' => 'Required. Use "" when there is none.' );
			} else {
				$field = flxlm_ats_ai_summary_check_text( $education['field'], 'summary.education.field', FLXLM_ATS_AI_SUMMARY_MAX_FIELD, true, $errors );
			}
			if ( null !== $level && null !== $field ) {
				$clean['education'] = array( 'level' => $level, 'field' => $field );
			}
		}
	}

	// skills: up to 12 short strings.
	$clean['skills'] = array();
	if ( array_key_exists( 'skills', $summary ) ) {
		$skills = $summary['skills'];
		if ( ! is_array( $skills ) || ( $skills && ! flxlm_ats_ai_summary_is_list( $skills ) ) ) {
			$errors[] = array( 'field' => 'summary.skills', 'message' => 'Must be a list of strings.' );
		} elseif ( count( $skills ) > FLXLM_ATS_AI_SUMMARY_MAX_SKILLS ) {
			$errors[] = array( 'field' => 'summary.skills', 'message' => 'At most ' . FLXLM_ATS_AI_SUMMARY_MAX_SKILLS . ' skills.' );
		} else {
			foreach ( $skills as $i => $skill ) {
				$text = flxlm_ats_ai_summary_check_text( $skill, 'summary.skills[' . $i . ']', FLXLM_ATS_AI_SUMMARY_MAX_SKILL, false, $errors );
				if ( null !== $text ) {
					$clean['skills'][] = $text;
				}
			}
		}
	}

	// overview: 2 or 3 plain factual sentences, at most 600 characters.
	$clean['overview'] = null;
	if ( array_key_exists( 'overview', $summary ) ) {
		$clean['overview'] = flxlm_ats_ai_summary_check_text( $summary['overview'], 'summary.overview', FLXLM_ATS_AI_SUMMARY_MAX_OVERVIEW, false, $errors );
	}

	if ( count( $errors ) > $before ) {
		return null;
	}

	// Stored in rubric order regardless of the order the producer sent.
	return array(
		'roles'                  => $clean['roles'],
		'years_office'           => $clean['years_office'],
		'years_service_industry' => $clean['years_service_industry'],
		'years_other'            => $clean['years_other'],
		'education'              => $clean['education'],
		'skills'                 => $clean['skills'],
		'overview'               => $clean['overview'],
	);
}

// ---------------------------------------------------------------------------
// Reading it back: the hub bridge, the notes timeline and the wp-admin card.
// ---------------------------------------------------------------------------

/**
 * The stored summary record for an application, or null.
 *
 * @param int $application_id Application ID.
 * @return array|null {version, model, status, summary, created_at}
 */
function flxlm_ats_ai_summary_get( $application_id ) {
	$record = get_post_meta( (int) $application_id, FLXLM_ATS_AI_SUMMARY_META, true );
	if ( ! is_array( $record ) || empty( $record['status'] ) ) {
		return null;
	}
	return $record;
}

/**
 * A number as plain text: "4", "3.5". Never a dash, never a trailing ".0".
 *
 * @param float|int $number Number.
 * @return string
 */
function flxlm_ats_ai_summary_fmt_years( $number ) {
	$number = round( (float) $number, 1 );
	return rtrim( rtrim( number_format( $number, 1, '.', '' ), '0' ), '.' );
}

/**
 * The plain text of a summary, used as the body of the 'ai_summary' note.
 *
 * @param array $record Stored record with status ok.
 * @return string
 */
function flxlm_ats_ai_summary_note_text( $record ) {
	$s      = $record['summary'];
	$levels = flxlm_ats_ai_summary_education_levels();

	$lines   = array();
	$lines[] = 'AI summary (' . $record['version'] . ', ' . $record['model'] . '): facts pulled from the resume, not a recommendation.';
	foreach ( $s['roles'] as $role ) {
		$lines[] = 'Role: ' . $role['title'] . ( '' !== $role['employer'] ? ', ' . $role['employer'] : '' ) . ', ' . flxlm_ats_ai_summary_fmt_years( $role['duration_years'] ) . ' years';
	}
	$lines[] = 'Office experience: ' . flxlm_ats_ai_summary_fmt_years( $s['years_office'] ) . ' years';
	$lines[] = 'Service industry experience: ' . flxlm_ats_ai_summary_fmt_years( $s['years_service_industry'] ) . ' years';
	$lines[] = 'Other experience: ' . flxlm_ats_ai_summary_fmt_years( $s['years_other'] ) . ' years';
	$lines[] = 'Education: ' . ( $levels[ $s['education']['level'] ] ?? $s['education']['level'] ) . ( '' !== $s['education']['field'] ? ', ' . $s['education']['field'] : '' );
	$lines[] = 'Skills: ' . ( $s['skills'] ? implode( ', ', $s['skills'] ) : 'none listed' );
	$lines[] = 'Overview: ' . $s['overview'];

	return implode( "\n", $lines );
}

/**
 * Record an AI summary action in the hub audit table.
 *
 * The audit table belongs to the hub bridge, which may be switched off on a
 * site that still runs this feature, so this makes sure the table exists
 * itself rather than going through flxlm_ats_hub_audit() (which reads a hub
 * user off the request and skips installing when the bridge is off). There is
 * no staff email on these rows: the actor is the signed job.
 *
 * @param string $action         e.g. 'ai_summary:ok:v1'.
 * @param int    $application_id Application ID.
 */
function flxlm_ats_ai_summary_audit( $action, $application_id ) {
	global $wpdb;

	if ( (int) get_option( 'flxlm_ats_hub_audit_db_version' ) !== FLXLM_ATS_HUB_AUDIT_DB_VERSION ) {
		flxlm_ats_hub_install_audit_table();
	}

	$wpdb->insert(
		flxlm_ats_hub_audit_table(),
		array(
			'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			'email'          => '',
			'action'         => substr( sanitize_text_field( $action ), 0, 64 ),
			'application_id' => (int) $application_id,
		),
		array( '%s', '%s', '%s', '%d' )
	);
}

/**
 * The wp-admin card. Visually distinct from a human note: a dashed violet
 * border on a tinted panel with an "AI" tag, where human notes are plain
 * list rows. Shown when a summary record exists, or when the feature is
 * configured and one is still on its way; on a site where it was never set
 * up, nothing renders.
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_ai_summary_card( $id ) {
	$record = flxlm_ats_ai_summary_get( $id );
	if ( ! $record && ! flxlm_ats_email_intake_enabled() ) {
		return;
	}

	echo '<div id="flxlm_ats_ai_summary" style="border:1.5px dashed #8b7cc8;border-radius:8px;background:#f6f4fc;padding:.85rem 1rem;margin:0 0 1rem;max-width:56rem">';
	echo '<div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.35rem">';
	echo '<span style="display:inline-block;background:#5b4bb0;color:#fff;border-radius:4px;padding:0 .45em;font-size:.75em;font-weight:700;letter-spacing:.03em">AI</span>';
	echo '<strong style="font-size:1.05em">AI summary: facts pulled from the resume, not a recommendation</strong>';
	echo '</div>';

	if ( ! $record ) {
		echo '<p style="margin:.3rem 0 0;color:#50575e">Not ready yet. It is made automatically a few minutes after an application arrives.</p>';
		echo '</div>';
		return;
	}

	echo '<p style="margin:0 0 .6rem;color:#50575e;font-size:.85em">Model '
		. esc_html( (string) ( $record['model'] ?? '' ) )
		. ' &middot; rubric ' . esc_html( (string) ( $record['version'] ?? '' ) )
		. ' &middot; made ' . esc_html( mysql2date( 'M j, Y g:ia', (string) ( $record['created_at'] ?? '' ) ) )
		. '</p>';

	if ( 'ok' !== $record['status'] || ! is_array( $record['summary'] ?? null ) ) {
		$why = array(
			'no_resume'  => 'There is no resume on file for this applicant, so there is nothing to summarise.',
			'unreadable' => 'The resume could not be read as text (for example a scanned image), so no summary was made.',
			'failed'     => 'The automatic summary did not work after several tries, so none was made.',
		);
		echo '<p style="margin:0">' . esc_html( $why[ $record['status'] ] ?? 'No summary is available.' ) . '</p>';
		echo '</div>';
		return;
	}

	$s      = $record['summary'];
	$levels = flxlm_ats_ai_summary_education_levels();

	echo '<table class="form-table" style="margin:0"><tbody>';
	printf(
		'<tr><th style="width:11rem;padding:.35rem 0">Office experience</th><td style="padding:.35rem 0">%s years</td></tr>',
		esc_html( flxlm_ats_ai_summary_fmt_years( $s['years_office'] ) )
	);
	printf(
		'<tr><th style="padding:.35rem 0">Service industry</th><td style="padding:.35rem 0">%s years <span style="color:#646970;font-size:.85em">(retail, restaurant, food service, front-line hospitality; counted separately)</span></td></tr>',
		esc_html( flxlm_ats_ai_summary_fmt_years( $s['years_service_industry'] ) )
	);
	printf(
		'<tr><th style="padding:.35rem 0">Other work</th><td style="padding:.35rem 0">%s years</td></tr>',
		esc_html( flxlm_ats_ai_summary_fmt_years( $s['years_other'] ) )
	);
	printf(
		'<tr><th style="padding:.35rem 0">Education</th><td style="padding:.35rem 0">%s%s</td></tr>',
		esc_html( $levels[ $s['education']['level'] ] ?? $s['education']['level'] ),
		'' !== $s['education']['field'] ? esc_html( ', ' . $s['education']['field'] ) : ''
	);
	echo '<tr><th style="padding:.35rem 0">Skills</th><td style="padding:.35rem 0">';
	if ( $s['skills'] ) {
		foreach ( $s['skills'] as $skill ) {
			echo '<span style="display:inline-block;background:#fff;border:1px solid #cfc8ea;border-radius:999px;padding:0 .6em;margin:0 .3rem .3rem 0;font-size:.85em">' . esc_html( $skill ) . '</span>';
		}
	} else {
		echo '<span style="color:#646970">None listed</span>';
	}
	echo '</td></tr>';
	echo '</tbody></table>';

	if ( $s['roles'] ) {
		echo '<table style="border-collapse:collapse;margin:.5rem 0;width:100%;font-size:.9em"><thead><tr style="text-align:left;color:#50575e"><th style="padding:.25rem .5rem .25rem 0">Role</th><th style="padding:.25rem .5rem">Employer</th><th style="padding:.25rem 0 .25rem .5rem;white-space:nowrap">Time in role</th></tr></thead><tbody>';
		foreach ( $s['roles'] as $role ) {
			printf(
				'<tr style="border-top:1px solid #e2def2"><td style="padding:.3rem .5rem .3rem 0">%s</td><td style="padding:.3rem .5rem">%s</td><td style="padding:.3rem 0 .3rem .5rem;white-space:nowrap">%s years</td></tr>',
				esc_html( $role['title'] ),
				esc_html( $role['employer'] ),
				esc_html( flxlm_ats_ai_summary_fmt_years( $role['duration_years'] ) )
			);
		}
		echo '</tbody></table>';
	}

	echo '<p style="margin:.5rem 0 0">' . esc_html( $s['overview'] ) . '</p>';
	echo '<p style="margin:.6rem 0 0;color:#646970;font-size:.8em">Only hiring staff see this. It is never used to sort, filter or move an applicant. Read the resume itself before deciding anything.</p>';
	echo '</div>';
}
