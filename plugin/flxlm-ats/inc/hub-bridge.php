<?php
/**
 * The hub bridge: a REST API for hub.flxlocalmedia.com to drive this plugin.
 *
 * fldn#1912, design option (a). The hub becomes a front end; this plugin
 * stays the system of record. No applicant data moves, nothing is deleted,
 * wp-admin keeps working exactly as it does today.
 *
 * TWO SEPARATE TRUST DECISIONS, BOTH ENFORCED ON EVERY REQUEST
 *
 *   1. "Is this really the Worker, and did it really validate Cloudflare
 *      Access?" — the HMAC signature (flxlm_ats_hub_verify_signature()).
 *      The signature covers the method, path, a hash of the body, a
 *      timestamp and the claimed staff email, keyed on a secret shared only
 *      with the Worker. A 60-second window bounds how long a captured
 *      request could be replayed.
 *   2. "Is THIS PERSON allowed to see applicant data?" — re-checked here,
 *      independently, every time (flxlm_ats_hub_require_capability()).
 *      Cloudflare Access answers "is this someone at the company", which is
 *      a much wider circle than "flxlm_view_applications". A shared service
 *      credential that stopped at (1) would let anyone with a hub login read
 *      resumes. Mirrors the exact failure this project already has on
 *      record for a Cloudflare Pages custom domain: Access gating the
 *      pretty URL while the underlying host stayed open.
 *
 * OFF BY DEFAULT. Nothing in this file runs unless wp-config.php defines:
 *
 *     define( 'FLXLM_ATS_HUB_BRIDGE_ENABLED', true );
 *     define( 'FLXLM_ATS_HUB_BRIDGE_SECRET', '...' );  // shared w/ the Worker
 *
 * That is deliberate: this ships to production behind the flag switched off,
 * because this repo has no staging environment (confirmed precedent on this
 * same issue — push-to-main is the deploy here) and the flag is the staging
 * gate instead.
 *
 * WIRE CONTRACT
 *
 * Namespace `flxlm-ats-hub/v1`; routes `/vacancies`,
 * `/vacancies/{id}/applicants`, `/applications/{id}`,
 * `/applications/{id}/resume`, `/applications/{id}/stage`; request headers
 * `X-FLX-Hub-Email`, `X-FLX-Hub-Timestamp`, `X-FLX-Hub-Signature`. Chosen to
 * match the hub-side Worker implementation already in progress on
 * `shswanson/fldn` (fldn#1912) rather than a second, competing naming, so
 * whichever build lands first the other can wire into it without a rename.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** How much clock skew and transit delay a signed hub request may have. */
const FLXLM_ATS_HUB_BRIDGE_WINDOW = 60;

/**
 * Whether the bridge is switched on at all.
 *
 * Both the enable flag and the secret must be set. A missing secret with the
 * flag left on would otherwise mean every route's permission check fails
 * closed anyway (no secret to verify against), but checking here means the
 * routes are not even registered, which is a smaller, easier-to-audit
 * surface than "registered but always 401".
 *
 * @return bool
 */
function flxlm_ats_hub_bridge_enabled() {
	return defined( 'FLXLM_ATS_HUB_BRIDGE_ENABLED' ) && FLXLM_ATS_HUB_BRIDGE_ENABLED
		&& defined( 'FLXLM_ATS_HUB_BRIDGE_SECRET' ) && '' !== (string) FLXLM_ATS_HUB_BRIDGE_SECRET;
}

/**
 * Register the bridge routes. Runs on rest_api_init like every other
 * namespace in this plugin, but every callback double-checks the flag: a
 * flag flipped off mid-request (e.g. an object-cached rest_api_init from a
 * page cache plugin) must never leave a route reachable.
 */
function flxlm_ats_hub_bridge_register_rest() {
	if ( ! flxlm_ats_hub_bridge_enabled() ) {
		return;
	}

	$ns = 'flxlm-ats-hub/v1';

	register_rest_route(
		$ns,
		'/vacancies',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_vacancies',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
		)
	);

	register_rest_route(
		$ns,
		'/vacancies/(?P<id>\d+)/applicants',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_vacancy_applicants',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_applicant',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/resume',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_resume',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/stage',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_stage',
			'permission_callback' => 'flxlm_ats_hub_permission_manage',
			'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
		)
	);
}
add_action( 'rest_api_init', 'flxlm_ats_hub_bridge_register_rest' );

/**
 * Verify the Worker's HMAC signature and resolve the request to a WP user.
 *
 * The signature covers method + path + a hash of the raw body + timestamp +
 * the claimed email, so tampering with any of those invalidates it. Reusing
 * one HMAC pattern across this plugin (see inc/rest-intake.php,
 * inc/tokens.php) rather than inventing a fourth scheme.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_User|WP_Error
 */
function flxlm_ats_hub_authenticate( $request ) {
	if ( ! flxlm_ats_hub_bridge_enabled() ) {
		return new WP_Error( 'flxlm_ats_hub_disabled', 'The hub bridge is not enabled on this site.', array( 'status' => 503 ) );
	}

	$timestamp = (int) $request->get_header( 'x-flx-hub-timestamp' );
	$signature = (string) $request->get_header( 'x-flx-hub-signature' );
	$email     = (string) $request->get_header( 'x-flx-hub-email' );

	if ( ! $timestamp || '' === $signature || '' === $email ) {
		return new WP_Error( 'flxlm_ats_hub_unsigned', 'Missing signed request headers.', array( 'status' => 401 ) );
	}

	if ( abs( time() - $timestamp ) > FLXLM_ATS_HUB_BRIDGE_WINDOW ) {
		return new WP_Error( 'flxlm_ats_hub_stale', 'Signature timestamp is outside the accepted window.', array( 'status' => 401 ) );
	}

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'flxlm_ats_hub_bad_email', 'Malformed email claim.', array( 'status' => 401 ) );
	}

	$body_hash = hash( 'sha256', (string) $request->get_body() );
	$route     = $request->get_route(); // e.g. /flxlm-ats-hub/v1/applications/42/resume — excludes query string, which is intentional: mode= is not signed input.
	$payload   = implode(
		"\n",
		array( strtoupper( $request->get_method() ), $route, $body_hash, (string) $timestamp, strtolower( $email ) )
	);
	$expected  = hash_hmac( 'sha256', $payload, (string) FLXLM_ATS_HUB_BRIDGE_SECRET );

	if ( ! hash_equals( $expected, $signature ) ) {
		return new WP_Error( 'flxlm_ats_hub_bad_signature', 'Signature does not match.', array( 'status' => 401 ) );
	}

	// (2) Independent per-person check. An email the JWT vouches for is not
	// automatically a WordPress user, and being a WordPress user is not
	// automatically someone with ATS rights — both must hold.
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		return new WP_Error( 'flxlm_ats_hub_unknown_user', 'No matching WordPress account.', array( 'status' => 403 ) );
	}

	return $user;
}

/**
 * Permission callback: caller must authenticate AND hold the read capability.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function flxlm_ats_hub_permission_view( $request ) {
	$user = flxlm_ats_hub_authenticate( $request );
	if ( is_wp_error( $user ) ) {
		return $user;
	}
	if ( ! user_can( $user, 'flxlm_view_applications' ) ) {
		return new WP_Error( 'flxlm_ats_hub_forbidden', 'This account cannot view applications.', array( 'status' => 403 ) );
	}
	$request->set_param( '_flxlm_hub_user', $user );
	return true;
}

/**
 * Permission callback: caller must authenticate AND hold the manage capability.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function flxlm_ats_hub_permission_manage( $request ) {
	$user = flxlm_ats_hub_authenticate( $request );
	if ( is_wp_error( $user ) ) {
		return $user;
	}
	if ( ! user_can( $user, 'flxlm_manage_applications' ) ) {
		return new WP_Error( 'flxlm_ats_hub_forbidden', 'This account cannot move applications.', array( 'status' => 403 ) );
	}
	$request->set_param( '_flxlm_hub_user', $user );
	return true;
}

/**
 * GET /vacancies — open postings with applicant counts by stage.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function flxlm_ats_hub_get_vacancies( $request ) {
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

	$stages = flxlm_ats_stages();
	$out    = array();

	foreach ( $jobs as $job ) {
		$counts = array_fill_keys( array_keys( $stages ), 0 );
		$total  = 0;

		$applications = get_posts(
			array(
				'post_type'        => 'flxlm_application',
				'post_status'      => array_keys( $stages ),
				'posts_per_page'   => -1,
				'meta_key'         => '_flxlm_job_id',
				'meta_value'       => (string) $job->ID,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		foreach ( $applications as $application_id ) {
			$stage = get_post_status( $application_id );
			if ( isset( $counts[ $stage ] ) ) {
				++$counts[ $stage ];
			}
			++$total;
		}

		$by_stage = array();
		foreach ( $stages as $key => $stage ) {
			$by_stage[] = array(
				'stage' => $key,
				'label' => $stage['label'],
				'count' => $counts[ $key ],
			);
		}

		$out[] = array(
			'id'          => $job->ID,
			'title'       => get_the_title( $job ),
			'permalink'   => get_permalink( $job ),
			'total'       => $total,
			'by_stage'    => $by_stage,
		);
	}

	flxlm_ats_hub_audit( $request, 'list_vacancies', 0 );

	return new WP_REST_Response( array( 'vacancies' => $out ), 200 );
}

/**
 * GET /vacancies/{id}/applicants — every applicant against one vacancy.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_get_vacancy_applicants( $request ) {
	$job_id = (int) $request->get_param( 'id' );
	$job    = get_post( $job_id );
	if ( ! $job || 'flxlm_job' !== $job->post_type ) {
		return new WP_Error( 'flxlm_ats_hub_no_vacancy', 'No such vacancy.', array( 'status' => 404 ) );
	}

	$ids = get_posts(
		array(
			'post_type'        => 'flxlm_application',
			'post_status'      => array_keys( flxlm_ats_stages() ),
			'posts_per_page'   => -1,
			'meta_key'         => '_flxlm_job_id',
			'meta_value'       => (string) $job_id,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	$out = array();
	foreach ( $ids as $application_id ) {
		$out[] = flxlm_ats_hub_applicant_summary( $application_id );
	}

	flxlm_ats_hub_audit( $request, 'list_applicants', $job_id );

	return new WP_REST_Response(
		array(
			'vacancy'    => array( 'id' => $job_id, 'title' => get_the_title( $job ) ),
			'applicants' => $out,
		),
		200
	);
}

/**
 * One applicant, summarised for a list row. No cover letter, no phone: a
 * list screen does not need them and every field returned is one more field
 * that travelled over the wire.
 *
 * @param int $application_id Application ID.
 * @return array
 */
function flxlm_ats_hub_applicant_summary( $application_id ) {
	$stage = get_post_status( $application_id );
	return array(
		'id'          => $application_id,
		'name'        => flxlm_ats_applicant_name( $application_id ),
		'stage'       => $stage,
		'stage_label' => flxlm_ats_stage_label( $stage ),
		'interviewed' => flxlm_ats_was_interviewed( $application_id ),
		'source'      => flxlm_ats_source_label( get_post_meta( $application_id, '_flxlm_source', true ) ),
		'submitted'   => get_post_meta( $application_id, '_flxlm_submitted_at', true ),
		'has_resume'  => (bool) get_post_meta( $application_id, '_flxlm_resume_file', true ),
	);
}

/**
 * GET /applications/{id} — full detail for the applicant screen.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_get_applicant( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$application    = flxlm_ats_get_application( $application_id );
	if ( ! $application ) {
		return new WP_Error( 'flxlm_ats_hub_no_applicant', 'No such applicant.', array( 'status' => 404 ) );
	}

	$stages = array();
	foreach ( flxlm_ats_stages() as $key => $stage ) {
		$stages[] = array( 'stage' => $key, 'label' => $stage['label'] );
	}

	$out = array(
		'id'          => $application_id,
		'name'        => flxlm_ats_applicant_name( $application_id ),
		'email'       => $application['email'],
		'phone'       => $application['phone'],
		'job_title'   => flxlm_ats_job_title( $application_id ),
		'stage'       => $application['stage'],
		'stage_label' => flxlm_ats_stage_label( $application['stage'] ),
		'interviewed' => flxlm_ats_was_interviewed( $application_id ),
		'source'      => flxlm_ats_source_label( $application['source'] ),
		'submitted'   => $application['submitted_at'],
		'entered_by'  => $application['entered_by'],
		'message'     => $application['message'],
		'links'       => $application['links'],
		'salary'      => $application['salary_expectation'],
		'has_resume'  => (bool) $application['resume_file'],
		'resume_name' => $application['resume_name'],
		'stages'      => $stages,
		'can_manage'  => user_can( $request->get_param( '_flxlm_hub_user' ), 'flxlm_manage_applications' ),
	);

	flxlm_ats_hub_audit( $request, 'view_applicant', $application_id );

	return new WP_REST_Response( $out, 200 );
}

/**
 * GET /applications/{id}/resume?mode=inline|download
 *
 * Reuses the exact byte source flxlm_ats_serve_resume() reads from
 * (resume-store.php), but chooses Content-Disposition from the requested
 * mode rather than only from mime type, because the hub requirement is
 * specifically "viewable in the window with a download button if wanted" —
 * a PDF must be able to go either way on the same file. Every response,
 * either mode, carries Cache-Control: no-store, private, matching the
 * WP-admin viewer shipped for this same issue: this is FCC EEO PII and must
 * never be cached by any intermediary, including the Worker in front of it.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_Error|void Exits on success by streaming bytes directly.
 */
function flxlm_ats_hub_get_resume( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$mode           = 'download' === $request->get_param( 'mode' ) ? 'download' : 'inline';

	$resume_id = (int) get_post_meta( $application_id, '_flxlm_resume_file', true );
	if ( $resume_id < 1 ) {
		return new WP_Error( 'flxlm_ats_hub_no_resume', 'No resume on file.', array( 'status' => 404 ) );
	}

	$meta = flxlm_ats_get_resume_meta( $resume_id );
	if ( ! $meta ) {
		return new WP_Error( 'flxlm_ats_hub_resume_missing', 'That resume is not available.', array( 'status' => 404 ) );
	}

	$bytes = flxlm_ats_get_resume_bytes( $resume_id );
	if ( null === $bytes || '' === $bytes ) {
		return new WP_Error( 'flxlm_ats_hub_resume_missing', 'That resume is not available.', array( 'status' => 404 ) );
	}

	$type      = $meta['mime_type'] ? $meta['mime_type'] : 'application/octet-stream';
	$original  = (string) get_post_meta( $application_id, '_flxlm_resume_name', true );
	$filename  = $original ? $original : ( 'resume.' . $meta['extension'] );
	// Only a PDF may ever render inline (see inc/storage.php's XSS reasoning);
	// every other type is force-download regardless of the requested mode.
	$can_inline = ( 'application/pdf' === $type );
	$disposition = ( 'inline' === $mode && $can_inline ) ? 'inline' : 'attachment';

	flxlm_ats_hub_audit( $request, 'download' === $mode ? 'download_resume' : 'view_resume', $application_id );

	nocache_headers();
	header( 'Cache-Control: no-store, private' );
	header( 'Content-Type: ' . $type );
	header( 'Content-Length: ' . strlen( $bytes ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'Content-Disposition: ' . $disposition . '; filename="' . sanitize_file_name( $filename ) . '"' );

	echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw file bytes.
	exit;
}

/**
 * POST /applications/{id}/stage — the only mutation this bridge exposes.
 *
 * Calls flxlm_ats_set_stage(), never reimplements it: that function owns the
 * one-way Interviewed stamp and the append-only stage_history log, and a
 * second "move a candidate" code path is exactly how those two records would
 * drift apart from each other.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_stage( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$stage          = is_array( $body ) ? sanitize_text_field( $body['stage'] ?? '' ) : '';

	if ( ! flxlm_ats_is_stage( $stage ) ) {
		return new WP_Error( 'flxlm_ats_hub_bad_stage', 'Unknown stage: ' . $stage, array( 'status' => 400 ) );
	}

	$user   = $request->get_param( '_flxlm_hub_user' );
	$actor  = 'hub:' . ( $user instanceof WP_User ? $user->user_email : 'unknown' );
	$result = flxlm_ats_set_stage( $application_id, $stage, $actor );

	if ( is_wp_error( $result ) ) {
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
	}

	flxlm_ats_hub_audit( $request, 'stage_change:' . $stage, $application_id );

	return new WP_REST_Response(
		array(
			'ok'          => true,
			'stage'       => $stage,
			'stage_label' => flxlm_ats_stage_label( $stage ),
		),
		200
	);
}

// ---------------------------------------------------------------------------
// Audit log — every view, download and stage change, with the staff email.
// ---------------------------------------------------------------------------

/**
 * Schema version, bumped when the audit table changes.
 */
const FLXLM_ATS_HUB_AUDIT_DB_VERSION = 1;

/**
 * The audit table name.
 *
 * @return string
 */
function flxlm_ats_hub_audit_table() {
	global $wpdb;
	return $wpdb->prefix . 'flxlm_ats_hub_audit';
}

/**
 * Create the audit table. Safe to call repeatedly.
 */
function flxlm_ats_hub_install_audit_table() {
	global $wpdb;

	$table   = flxlm_ats_hub_audit_table();
	$collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		email varchar(190) NOT NULL DEFAULT '',
		action varchar(64) NOT NULL DEFAULT '',
		application_id bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY application_id (application_id),
		KEY email (email)
	) {$collate};";

	dbDelta( $sql );

	update_option( 'flxlm_ats_hub_audit_db_version', FLXLM_ATS_HUB_AUDIT_DB_VERSION );
}

/**
 * Make sure the audit table exists before anything writes to it. Same
 * belt-and-braces pattern as the resume table: a plugin update deployed by
 * rsync can miss its activation hook.
 */
function flxlm_ats_hub_maybe_install_audit_table() {
	if ( ! flxlm_ats_hub_bridge_enabled() ) {
		return;
	}
	if ( (int) get_option( 'flxlm_ats_hub_audit_db_version' ) === FLXLM_ATS_HUB_AUDIT_DB_VERSION ) {
		return;
	}
	flxlm_ats_hub_install_audit_table();
}
add_action( 'admin_init', 'flxlm_ats_hub_maybe_install_audit_table' );
add_action( 'rest_api_init', 'flxlm_ats_hub_maybe_install_audit_table' );

/**
 * Record one audit line.
 *
 * @param WP_REST_Request $request        Request (for the authenticated email).
 * @param string           $action         What happened, e.g. 'view_resume'.
 * @param int              $application_id Application ID, or 0 for a list action.
 */
function flxlm_ats_hub_audit( $request, $action, $application_id ) {
	global $wpdb;

	flxlm_ats_hub_maybe_install_audit_table();

	$user  = $request->get_param( '_flxlm_hub_user' );
	$email = $user instanceof WP_User ? $user->user_email : (string) $request->get_header( 'x-flx-hub-email' );

	$wpdb->insert(
		flxlm_ats_hub_audit_table(),
		array(
			'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			'email'          => sanitize_email( $email ),
			'action'         => sanitize_text_field( $action ),
			'application_id' => (int) $application_id,
		),
		array( '%s', '%s', '%s', '%d' )
	);
}
