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
 * Namespace `flxlm-ats-hub/v1`; routes `/stages`, `/vacancies`,
 * `/vacancies/{id}/applicants`, `/applications/{id}`,
 * `/applications/{id}/resume`, `/applications/{id}/stage`,
 * `/applications/{id}/interviewers`, `/applications/{id}/comments`,
 * `/applications/{id}/feedback`, `/applications/{id}/job`; request headers
 * `X-FLX-Hub-Email`, `X-FLX-Hub-Timestamp`, `X-FLX-Hub-Signature`. Chosen to
 * match the hub-side Worker implementation already in progress on
 * `shswanson/fldn` (fldn#1912) rather than a second, competing naming, so
 * whichever build lands first the other can wire into it without a rename.
 *
 * The 2026-09-28 v1 hiring-flow contract extends `/vacancies` (adds
 * `hiring_manager` per posting and an "Unassigned" id-0 pseudo-vacancy) and
 * `/applications/{id}` (adds `stage_info`, `exit_checks`, `interviewers`,
 * `notes`, `close_reason`, `start_date`, `phone_screened_at`,
 * `interviewed_at`, `hiring_manager`, `source_info`, `flags` — see
 * flxlm_ats_hub_applicant_payload(), the one builder every mutating route
 * below also returns). `/stages`, `/applications/{id}/interviewers`,
 * `/applications/{id}/comments`, `/applications/{id}/feedback` and
 * `/applications/{id}/job` are new. `POST /applications/{id}/stage` now
 * accepts `note`/`close_reason`/`start_date` and answers 422
 * `{error, errors:[...]}` when a required field is missing, instead of the
 * flat `{ok, stage, stage_label}` it used to.
 *
 * The 2026-09-28 v1.1 pass (contact editing, the source control, the
 * interviewer picklist) adds four more routes: `GET /sources`, `GET /people`,
 * `POST /applications/{id}/contact` and `POST /applications/{id}/source`.
 * `POST /applications/{id}/interviewers` gains an `external` body field on
 * `action: "add"`. See this plugin's structured report for the exact bodies
 * and response shapes.
 *
 * The 2026-10-14 hub usability pass (plugin 1.4.0) adds `GET /me`,
 * `POST /applications/{id}/fit` and `POST /applications/{id}/consider-for`, and
 * extends `/vacancies`, `/vacancies/{id}/applicants` and `/applications/{id}`
 * with the data the hub's organised job list, coloured stages and "right
 * person? right job?" card need. Every change is ADDITIVE: no existing field
 * was renamed or removed, so the previous hub keeps working until the new one
 * deploys. Object forms of existing flat fields arrive as `stage_info` and
 * `source_info`, the same convention the 2026-09-28 pass set.
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
		'/stages',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_stages',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
		)
	);

	register_rest_route(
		$ns,
		'/me',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_me',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
		)
	);

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
		'/sources',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_sources',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
		)
	);

	register_rest_route(
		$ns,
		'/people',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_people',
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
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_applicant',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/resume',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxlm_ats_hub_get_resume',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/stage',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_stage',
			'permission_callback' => 'flxlm_ats_hub_permission_manage',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/interviewers',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_interviewers',
			'permission_callback' => 'flxlm_ats_hub_permission_manage',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/contact',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_contact',
			'permission_callback' => 'flxlm_ats_hub_permission_manage',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/source',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_source',
			'permission_callback' => 'flxlm_ats_hub_permission_manage',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/comments',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_comment',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/feedback',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_feedback',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/fit',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_fit',
			'permission_callback' => 'flxlm_ats_hub_permission_view',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/consider-for',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_consider_for',
			'permission_callback' => 'flxlm_ats_hub_permission_manage',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
		)
	);

	register_rest_route(
		$ns,
		'/applications/(?P<id>\d+)/job',
		array(
			'methods'             => 'POST',
			'callback'            => 'flxlm_ats_hub_post_job',
			'permission_callback' => 'flxlm_ats_hub_permission_manage',
			'args'                => array( 'id' => array( 'validate_callback' => function ( $value ) { return is_numeric( $value ); } ) ),
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
	// Every route on this bridge answers with applicant PII (or, on the
	// resume route, the file itself). WordPress only injects its own
	// nocache-header logic for is_user_logged_in() requests, which these
	// never are (they authenticate via a signed header, not a session
	// cookie) — so without this, the JSON routes rely entirely on the
	// Worker's own cf:{cacheTtl:0} to stay uncached. Set it here, once, so
	// every response this bridge ever sends is explicitly no-store regardless
	// of what calls it.
	nocache_headers();

	if ( ! flxlm_ats_hub_bridge_enabled() ) {
		return new WP_Error( 'flxlm_ats_hub_disabled', 'The hub bridge is not enabled on this site.', array( 'status' => 503 ) );
	}

	$timestamp = (int) $request->get_header( 'x-flx-hub-timestamp' );
	$signature = (string) $request->get_header( 'x-flx-hub-signature' );
	$email     = (string) $request->get_header( 'x-flx-hub-email' );

	if ( ! $timestamp || '' === $signature || '' === $email ) {
		flxlm_ats_hub_audit_denied( $email, 0, 'unsigned', $request );
		return new WP_Error( 'flxlm_ats_hub_unsigned', 'Missing signed request headers.', array( 'status' => 401 ) );
	}

	if ( abs( time() - $timestamp ) > FLXLM_ATS_HUB_BRIDGE_WINDOW ) {
		flxlm_ats_hub_audit_denied( $email, 0, 'stale_timestamp', $request );
		return new WP_Error( 'flxlm_ats_hub_stale', 'Signature timestamp is outside the accepted window.', array( 'status' => 401 ) );
	}

	if ( ! is_email( $email ) ) {
		flxlm_ats_hub_audit_denied( $email, 0, 'bad_email', $request );
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
		flxlm_ats_hub_audit_denied( $email, 0, 'bad_signature', $request );
		return new WP_Error( 'flxlm_ats_hub_bad_signature', 'Signature does not match.', array( 'status' => 401 ) );
	}

	// Belt and braces beyond the 60-second window: a signature can be used
	// exactly once. A transient is enough (not a durable table) because the
	// only thing it needs to survive is the window itself — a captured,
	// signed Worker->WordPress request must not be replayable a second later,
	// not just "not tomorrow".
	$nonce_key = 'flxlm_hub_sig_' . md5( $signature );
	if ( false !== get_transient( $nonce_key ) ) {
		flxlm_ats_hub_audit_denied( $email, 0, 'replayed_signature', $request );
		return new WP_Error( 'flxlm_ats_hub_replay', 'That signed request has already been used.', array( 'status' => 401 ) );
	}
	set_transient( $nonce_key, 1, FLXLM_ATS_HUB_BRIDGE_WINDOW + 5 );

	// (2) Independent per-person check. An email the JWT vouches for is not
	// automatically a WordPress user, and being a WordPress user is not
	// automatically someone with ATS rights — both must hold.
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		flxlm_ats_hub_audit_denied( $email, 0, 'unknown_user', $request );
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
		flxlm_ats_hub_audit_denied( $user->user_email, $user->ID, 'missing_cap:flxlm_view_applications', $request );
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
		flxlm_ats_hub_audit_denied( $user->user_email, $user->ID, 'missing_cap:flxlm_manage_applications', $request );
		return new WP_Error( 'flxlm_ats_hub_forbidden', 'This account cannot move applications.', array( 'status' => 403 ) );
	}
	$request->set_param( '_flxlm_hub_user', $user );
	return true;
}

/**
 * GET /stages — the ladder definition, so every hub screen renders the same
 * owner and exit-test wording this plugin does (wp-admin, the confirm-page
 * email links, the EEO report all read the same flxlm_ats_stages() this
 * builds from — one source of truth, four renderings).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function flxlm_ats_hub_get_stages( $request ) {
	$out = array();
	foreach ( flxlm_ats_stages() as $key => $stage ) {
		$out[] = array(
			'key'      => $key,
			'label'    => $stage['label'],
			'owner'    => $stage['owner'] ?? '',
			'exit_test' => $stage['exit_test'] ?? '',
			'terminal' => ! empty( $stage['terminal'] ),
			'retired'  => ! empty( $stage['retired'] ),
			'requires' => flxlm_ats_stage_required_fields( $key ),
		);
	}

	flxlm_ats_hub_audit( $request, 'list_stages', 0 );

	return new WP_REST_Response( $out, 200 );
}

/**
 * GET /me — who the signed-in hub user is, as far as hiring goes.
 *
 * The hub opens on "My jobs" when this user manages at least one posting, and
 * hides the move controls from someone who can only view.
 *
 * `manages_job_ids` is every job the person is the hiring manager of that the
 * job list would show: open postings, plus closed or draft postings that still
 * have active candidates. A manager of only a long-closed posting does not get
 * an empty "My jobs" view by default.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function flxlm_ats_hub_get_me( $request ) {
	$user = $request->get_param( '_flxlm_hub_user' );

	$manages = array();
	if ( $user instanceof WP_User ) {
		$jobs = get_posts(
			array(
				'post_type'        => 'flxlm_job',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'   => -1,
				'meta_key'         => 'job_hiring_manager',
				'meta_value'       => (string) $user->ID,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		foreach ( $jobs as $job_id ) {
			if ( 'publish' === get_post_status( $job_id ) || flxlm_ats_hub_job_has_active_applicants( $job_id ) ) {
				$manages[] = (int) $job_id;
			}
		}
		sort( $manages );
	}

	$is_business_manager = false;
	if ( $user instanceof WP_User ) {
		$business            = array_map( 'strtolower', flxlm_ats_business_manager_emails() );
		$is_business_manager = in_array( strtolower( $user->user_email ), $business, true );
	}

	flxlm_ats_hub_audit( $request, 'me', 0 );

	return new WP_REST_Response(
		array(
			'email'               => $user instanceof WP_User ? $user->user_email : '',
			'name'                => $user instanceof WP_User ? $user->display_name : '',
			'user_id'             => $user instanceof WP_User ? (int) $user->ID : 0,
			'can_manage'          => $user instanceof WP_User ? user_can( $user, 'flxlm_manage_applications' ) : false,
			'is_business_manager' => $is_business_manager,
			'manages_job_ids'     => $manages,
		),
		200
	);
}

/**
 * Whether a job has any candidate who is not Hired or Not hired.
 *
 * @param int $job_id Job posting ID.
 * @return bool
 */
function flxlm_ats_hub_job_has_active_applicants( $job_id ) {
	$active = array();
	foreach ( flxlm_ats_stages() as $key => $stage ) {
		if ( empty( $stage['terminal'] ) ) {
			$active[] = $key;
		}
	}

	$found = get_posts(
		array(
			'post_type'        => 'flxlm_application',
			'post_status'      => $active,
			'posts_per_page'   => 1,
			'meta_key'         => '_flxlm_job_id',
			'meta_value'       => (string) (int) $job_id,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	return (bool) $found;
}

/**
 * One row of GET /vacancies: a job (or the id-0 "Unassigned" pseudo-vacancy)
 * with its counts and what needs attention.
 *
 * @param array|null $job         Job fields from flxlm_ats_job_summary(), or null for Unassigned.
 * @param int[]      $app_ids     Every application on it, any stage.
 * @param array      $ctx         From flxlm_ats_hub_context().
 * @param array      $stages      flxlm_ats_stages().
 * @return array
 */
function flxlm_ats_hub_vacancy_row( $job, $app_ids, $ctx, $stages ) {
	$counts    = array_fill_keys( array_keys( $stages ), 0 );
	$new_count = 0;
	$active    = 0;
	$last      = '';
	$attention = array( 'stuck' => 0, 'source_unknown' => 0, 'feedback_missing' => 0, 'unreviewed_fit' => 0 );

	foreach ( $app_ids as $application_id ) {
		$stage = (string) get_post_status( $application_id );
		if ( isset( $counts[ $stage ] ) ) {
			++$counts[ $stage ];
		}
		if ( 'flxlm_new' === $stage ) {
			++$new_count;
		}

		$applied = (string) get_post_meta( $application_id, '_flxlm_submitted_at', true );
		if ( $applied > $last ) {
			$last = $applied;
		}

		if ( flxlm_ats_is_terminal_stage( $stage ) ) {
			continue;
		}
		++$active;

		$signals = flxlm_ats_hub_signals( $application_id, $ctx );
		foreach ( $signals['attention'] as $reason ) {
			if ( isset( $attention[ $reason ] ) ) {
				++$attention[ $reason ];
			}
		}
	}

	$by_stage = array();
	foreach ( $stages as $key => $stage ) {
		$by_stage[] = array( 'stage' => $key, 'label' => $stage['label'], 'count' => $counts[ $key ] );
	}

	$job_id = $job ? (int) $job['id'] : 0;

	return array(
		'id'             => $job_id,
		'title'          => $job ? $job['title'] : 'Unassigned',
		'permalink'      => $job ? $job['permalink'] : '',
		'location'       => $job_id ? (string) get_post_meta( $job_id, 'job_location', true ) : '',
		'type'           => $job_id ? (string) get_post_meta( $job_id, 'job_type', true ) : '',
		'job_type'       => $job_id ? (string) get_post_meta( $job_id, 'job_type', true ) : '',
		'status'         => $job ? $job['status'] : 'open',
		'hiring_manager' => flxlm_ats_hiring_manager_summary( $job_id ),
		'total'          => count( $app_ids ),
		'by_stage'       => $by_stage,
		// Same counts as by_stage, shaped as {stage: count} — this is the
		// field name and shape the hub front end's own wire contract
		// actually reads (docs/gws-migration/worker/hiring.html).
		'stage_counts'   => $counts,
		'new_count'      => $new_count,
		'active_count'   => $active,
		'last_applied_at' => flxlm_ats_iso( $last ),
		'attention'      => $attention,
	);
}

/**
 * GET /vacancies — open postings, plus closed or draft postings that still
 * have candidates in play, each with counts by stage and attention counts.
 *
 * Attention counts are of ACTIVE candidates (not Hired, not Not hired):
 *   stuck             in New, Phone screen, Decision or Offer for more than 5
 *                     business days (flxlm_ats_is_stuck(), the weekly digest's rule)
 *   source_unknown    recruitment source missing or "not known yet"
 *   feedback_missing  an active interviewer with no feedback 2+ business days
 *                     after being assigned
 *   unreviewed_fit    in New with no "right person? right job?" check yet
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function flxlm_ats_hub_get_vacancies( $request ) {
	$stages = flxlm_ats_stages();
	$all    = flxlm_ats_all_application_ids();
	$ctx    = flxlm_ats_hub_context( $all );

	$by_job = array(); // job id => application ids.
	foreach ( $all as $application_id ) {
		$by_job[ (int) get_post_meta( $application_id, '_flxlm_job_id', true ) ][] = $application_id;
	}

	$published = get_posts(
		array(
			'post_type'      => 'flxlm_job',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	$jobs = $published;

	// Closed, draft or trashed postings stay in the list while anyone is still in play on them.
	$published_ids = wp_list_pluck( $published, 'ID' );
	$extra_ids     = array();
	foreach ( $by_job as $job_id => $ids ) {
		if ( ! $job_id || in_array( $job_id, $published_ids, true ) ) {
			continue;
		}
		foreach ( $ids as $application_id ) {
			if ( ! flxlm_ats_is_terminal_stage( (string) get_post_status( $application_id ) ) ) {
				$extra_ids[] = $job_id;
				break;
			}
		}
	}
	if ( $extra_ids ) {
		$jobs = array_merge(
			$jobs,
			get_posts(
				array(
					'post_type'      => 'flxlm_job',
					'post__in'       => $extra_ids,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			)
		);
	}

	$out = array();
	foreach ( $jobs as $job ) {
		$out[] = flxlm_ats_hub_vacancy_row( flxlm_ats_job_summary( $job->ID ), $by_job[ $job->ID ] ?? array(), $ctx, $stages );
	}

	// The "Unassigned" pseudo-vacancy (id 0): applications with no job at all,
	// most often an email-intake arrival the intake script could not match to
	// a posting. Only sent when at least one exists, so a site with nothing
	// unassigned never has to render an always-empty tile.
	if ( ! empty( $by_job[0] ) ) {
		$out[] = flxlm_ats_hub_vacancy_row( null, $by_job[0], $ctx, $stages );
	}

	// Sent once at the top level, not per-vacancy — the labels for
	// stage_counts's keys. Same wire contract as above.
	$stage_labels = array();
	foreach ( $stages as $key => $stage ) {
		$stage_labels[ $key ] = $stage['label'];
	}

	flxlm_ats_hub_audit( $request, 'list_vacancies', 0 );

	return new WP_REST_Response(
		array(
			'vacancies' => $out,
			'stages'    => $stage_labels,
		),
		200
	);
}

/**
 * GET /sources — the recruitment-source vocabulary a picker should actually
 * offer. Excludes 'unknown' on purpose (flxlm_ats_hub_selectable_sources(),
 * inc/contact.php): the one place this list is used is fixing an applicant
 * whose source the EEO report cannot trust yet, and "Not known yet" is not a
 * fix for that.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function flxlm_ats_hub_get_sources( $request ) {
	$out = array();
	foreach ( flxlm_ats_hub_selectable_sources() as $key => $label ) {
		$out[] = array( 'key' => $key, 'label' => $label );
	}

	flxlm_ats_hub_audit( $request, 'list_sources', 0 );

	return new WP_REST_Response( $out, 200 );
}

/**
 * GET /people — the interviewer picklist: the staff directory, WordPress
 * users who can view applications, and anyone previously assigned as an
 * interviewer (flxlm_ats_hub_people(), inc/interviewers.php).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function flxlm_ats_hub_get_people( $request ) {
	flxlm_ats_hub_audit( $request, 'list_people', 0 );

	return new WP_REST_Response( flxlm_ats_hub_people(), 200 );
}

/**
 * GET /vacancies/{id}/applicants — every applicant against one vacancy.
 *
 * The `vacancy` header carries what the page needs to introduce the job:
 * {id, title, status, permalink, hiring_manager}.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_get_vacancy_applicants( $request ) {
	$job_id = (int) $request->get_param( 'id' );

	// id 0 is the "Unassigned" pseudo-vacancy (see flxlm_ats_hub_get_vacancies()),
	// not a real flxlm_job post, so it skips the post lookup entirely.
	if ( 0 === $job_id ) {
		$header = array(
			'id'             => 0,
			'title'          => 'Unassigned',
			'status'         => 'open',
			'permalink'      => '',
			'hiring_manager' => null,
		);
	} else {
		$job = get_post( $job_id );
		if ( ! $job || 'flxlm_job' !== $job->post_type ) {
			return new WP_Error( 'flxlm_ats_hub_no_vacancy', 'No such vacancy.', array( 'status' => 404 ) );
		}
		$summary = flxlm_ats_job_summary( $job_id );
		$header  = array(
			'id'             => $job_id,
			'title'          => $summary['title'],
			'status'         => $summary['status'],
			'permalink'      => $summary['permalink'],
			'hiring_manager' => flxlm_ats_hiring_manager_summary( $job_id ),
		);
	}

	$ids = array_map(
		'intval',
		get_posts(
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
		)
	);

	$ctx = flxlm_ats_hub_context( $ids );
	$out = array();
	foreach ( $ids as $application_id ) {
		$out[] = flxlm_ats_hub_applicant_summary( $application_id, $ctx );
	}

	flxlm_ats_hub_audit( $request, 'list_applicants', $job_id );

	return new WP_REST_Response(
		array(
			'vacancy'    => $header,
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
 * `stage` and `source` stay the flat strings the previous hub reads;
 * `stage_info` and `source_info` are the {key,label} objects.
 *
 * @param int        $application_id Application ID.
 * @param array|null $ctx            From flxlm_ats_hub_context(); built for this one when null.
 * @return array
 */
function flxlm_ats_hub_applicant_summary( $application_id, $ctx = null ) {
	$application_id = (int) $application_id;
	$signals        = flxlm_ats_hub_signals( $application_id, $ctx );
	$stage          = $signals['stage'];
	$submitted      = (string) get_post_meta( $application_id, '_flxlm_submitted_at', true );
	if ( '' === $submitted ) {
		$post      = get_post( $application_id );
		$submitted = $post ? get_gmt_from_date( $post->post_date ) : '';
	}

	return array(
		'id'                       => $application_id,
		'name'                     => flxlm_ats_applicant_name( $application_id ),
		'stage'                    => $stage,
		'stage_label'              => flxlm_ats_stage_label( $stage ),
		'stage_info'               => flxlm_ats_stage_info( $stage ),
		'stage_entered_at'         => $signals['stage_entered_at'],
		'days_in_stage'            => $signals['days_in_stage'],
		'business_days_in_stage'   => $signals['business_days_in_stage'],
		'target_days'              => $signals['target_days'],
		'over_target'              => $signals['over_target'],
		'interviewed'              => flxlm_ats_was_interviewed( $application_id ),
		'source'                   => flxlm_ats_source_label( $signals['source_key'] ),
		'source_info'              => array( 'key' => $signals['source_key'], 'label' => flxlm_ats_source_label( $signals['source_key'] ) ),
		'source_unknown'           => $signals['source_unknown'],
		'submitted_at'             => get_post_meta( $application_id, '_flxlm_submitted_at', true ),
		'applied_at'               => flxlm_ats_iso( $submitted ),
		'has_resume'               => $signals['has_resume'],
		'resume_type'              => $signals['resume_type'],
		'interviewers_total'       => $signals['interviewers_total'],
		'feedback_submitted'       => $signals['feedback_submitted'],
		'feedback_ratings'         => $signals['feedback_ratings'],
		'fit'                      => $signals['fit'],
		'other_applications_count' => $signals['other_applications_count'],
		'attention'                => $signals['attention'],
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

	$out = flxlm_ats_hub_applicant_payload( $application_id, $request->get_param( '_flxlm_hub_user' ) );
	if ( is_wp_error( $out ) ) {
		return $out;
	}

	flxlm_ats_hub_audit( $request, 'view_applicant', $application_id );

	return new WP_REST_Response( $out, 200 );
}

/**
 * The full applicant JSON, built once and reused by every route the v1
 * contract says returns "the full applicant JSON" after a mutation
 * (POST stage/interviewers/comments/feedback/job) as well as by the plain GET.
 * One builder means a field added here shows up everywhere at once, rather
 * than five hand-copied response arrays drifting apart from each other one
 * bug fix at a time.
 *
 * @param int          $application_id Application ID.
 * @param WP_User|null $hub_user       The authenticated hub user, for can_manage.
 * @return array|WP_Error
 */
function flxlm_ats_hub_applicant_payload( $application_id, $hub_user = null ) {
	$application = flxlm_ats_get_application( $application_id );
	if ( ! $application ) {
		return new WP_Error( 'flxlm_ats_hub_no_applicant', 'No such applicant.', array( 'status' => 404 ) );
	}

	$stages = array();
	foreach ( flxlm_ats_stages() as $key => $stage ) {
		$stages[] = array( 'stage' => $key, 'label' => $stage['label'] );
	}

	// Newest first, matching the wp-admin applicant screen's own history
	// display (inc/admin-detail.php) — same underlying meta, same convention.
	$raw_history = is_array( $application['stage_history'] ?? null ) ? $application['stage_history'] : array();
	$history     = array_map(
		function ( $entry ) {
			return array(
				'from_label' => flxlm_ats_stage_label( $entry['from'] ?? '' ),
				'to_label'   => flxlm_ats_stage_label( $entry['to'] ?? '' ),
				'at'         => $entry['at'] ?? '',
				'by'         => $entry['by'] ?? '',
			);
		},
		array_reverse( $raw_history )
	);

	$job_id  = (int) $application['job_id'];
	$manager = $job_id ? flxlm_ats_job_hiring_manager( $job_id ) : null;

	$raw_notes = flxlm_ats_get_notes( $application_id );

	// Feedback is append-only by design (inc/notes.php: "a correction is a
	// NEW note, the old one stays") and an interviewer's link deliberately
	// stays valid for re-submission ("the most recent note is what counts",
	// inc/interviewers.php). But nothing told a reader WHICH one that is, so
	// two contradictory ratings from the same interviewer sat side by side
	// with no way to tell which was authoritative (fldn review finding).
	// Notes come back oldest-first, so the last feedback id seen per author
	// is the current one; everything earlier from that same author is
	// superseded but kept, same as the record always intended.
	$latest_feedback_id_by_author = array();
	foreach ( $raw_notes as $note ) {
		if ( 'feedback' === $note['kind'] ) {
			$latest_feedback_id_by_author[ strtolower( $note['author_email'] ) ] = (int) $note['id'];
		}
	}

	$notes = array_map(
		function ( $note ) use ( $latest_feedback_id_by_author ) {
			$is_current = true;
			if ( 'feedback' === $note['kind'] ) {
				$author     = strtolower( $note['author_email'] );
				$is_current = ( (int) $note['id'] === ( $latest_feedback_id_by_author[ $author ] ?? null ) );
			}
			return array(
				'id'            => (int) $note['id'],
				'kind'          => $note['kind'],
				'author_name'   => $note['author_name'],
				'author_email'  => $note['author_email'],
				'stage'         => $note['stage'],
				'rating'        => $note['rating'],
				'body'          => $note['body'],
				'created_at'    => $note['created_at'],
				'is_current'    => $is_current,
				// A fit check's structured answers ({person, job, other_job_id,
				// other_job_title, note}); null for every other kind.
				'fit'           => 'fit' === $note['kind'] ? $note['meta'] : null,
			);
		},
		$raw_notes
	);

	$flags = array();
	if ( '' === $application['source'] || 'unknown' === $application['source'] ) {
		$flags[] = 'source_unknown';
	}
	if ( ! $job_id ) {
		$flags[] = 'no_job';
	}

	$fit         = flxlm_ats_fit_state( $raw_notes );
	$resume_id   = (int) $application['resume_file'];
	$resume_meta = $resume_id > 0 ? flxlm_ats_get_resume_meta( $resume_id ) : null;

	$out = array(
		'id'                => $application_id,
		'name'              => flxlm_ats_applicant_name( $application_id ),
		'email'             => $application['email'],
		'phone'             => $application['phone'],
		'job_title'         => wp_specialchars_decode( flxlm_ats_job_title( $application_id ), ENT_QUOTES ),
		// {id, title, status: open|closed, permalink}, or null when attached to no job.
		// The candidate page links back to /hiring/job/{job.id} from this.
		'job'               => flxlm_ats_job_summary( $job_id, $application['job_title'] ),
		// 'stage'/'stage_label' stay flat strings: this is the shape the hub
		// front end's existing wire contract (docs/gws-migration/worker/hiring.html
		// in shswanson/fldn) already reads. 'stage_info' carries the new
		// {key,label} object form the v1 contract also asks for, additively —
		// see this file's "WIRE CONTRACT" note and this plugin's structured
		// report for why this is additive rather than a breaking rename.
		'stage'             => $application['stage'],
		'stage_label'       => flxlm_ats_stage_label( $application['stage'] ),
		'stage_info'        => array( 'key' => $application['stage'], 'label' => flxlm_ats_stage_label( $application['stage'] ) ),
		'exit_checks'       => flxlm_ats_stage_exit_checks( $application_id ),
		'interviewed'       => flxlm_ats_was_interviewed( $application_id ),
		'interviewed_at'    => (string) get_post_meta( $application_id, '_flxlm_interviewed_at', true ),
		'phone_screened_at' => (string) get_post_meta( $application_id, '_flxlm_phone_screened_at', true ),
		'close_reason'      => (string) get_post_meta( $application_id, '_flxlm_close_reason', true ),
		'start_date'        => (string) get_post_meta( $application_id, '_flxlm_start_date', true ),
		'source'            => flxlm_ats_source_label( $application['source'] ),
		'source_info'       => array( 'key' => $application['source'], 'label' => flxlm_ats_source_label( $application['source'] ) ),
		'hiring_manager'    => $manager ? array( 'id' => $manager->ID, 'name' => $manager->display_name, 'email' => $manager->user_email ) : null,
		'interviewers'      => flxlm_ats_active_interviewers( $application_id ),
		'notes'             => $notes,
		// The AI resume summary record (inc/ai-summary.php), or null. Advisory and
		// staff-only; nothing in this bridge reads it for a sort, filter or stage move.
		'ai_summary'        => flxlm_ats_ai_summary_get( $application_id ),
		'flags'             => $flags,
		// Field names below match the hub front end's own wire contract
		// (docs/gws-migration/worker/hiring.html in shswanson/fldn) exactly —
		// this endpoint is the one documented to conform to it, not the
		// other way around (see this file's own "WIRE CONTRACT" note above).
		'submitted_at'  => $application['submitted_at'],
		'entered_by'    => $application['entered_by'],
		'message'       => $application['message'],
		'links'         => $application['links'],
		'salary_expectation' => $application['salary_expectation'],
		'has_resume'    => (bool) $application['resume_file'],
		'resume_name'   => $application['resume_name'],
		'resume_type'   => $resume_meta ? (string) $resume_meta['mime_type'] : '',
		// The same person's other applications (matched on lowercased email),
		// never this record. Newest first.
		'other_applications' => flxlm_ats_other_applications( $application_id ),
		// "Right person? Right job?": the latest check from each person, newest
		// first, and how many of them answered yes / maybe / no. Human judgments
		// only; the AI summary never fills or suggests these.
		'fit_checks'    => $fit['checks'],
		'fit_summary'   => $fit['summary'],
		'stage_history' => $history,
		'stages'        => $stages,
		'can_manage'    => $hub_user instanceof WP_User ? user_can( $hub_user, 'flxlm_manage_applications' ) : false,
	);

	return $out;
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

	// Same post-type guard flxlm_ats_hub_get_applicant() and
	// flxlm_ats_hub_get_vacancy_applicants() already apply before touching any
	// meta for a caller-supplied id. This route streams raw file bytes, so
	// skipping it here (as an earlier version of this file did) is an
	// IDOR-shaped gap: nothing today happens to carry `_flxlm_resume_file` on
	// any other post type, but the fix belongs on the id, not on today's data.
	if ( ! flxlm_ats_get_application( $application_id ) ) {
		return new WP_Error( 'flxlm_ats_hub_no_applicant', 'No such applicant.', array( 'status' => 404 ) );
	}

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
	// A resume is adversary-supplied by definition (anyone can apply for a
	// job); a well-formed PDF can still carry embedded JavaScript (a
	// long-documented PDF-viewer attack class), and this bytes stream ends up
	// rendered inline, same-origin, inside a reviewer's own authenticated hub
	// session. script-src 'none' means embedded PDF actions can't execute
	// regardless of which viewer renders it, independent of whatever the hub
	// iframe's own sandbox attribute does.
	header( "Content-Security-Policy: script-src 'none'" );
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
	$body           = is_array( $body ) ? $body : array();
	$stage          = sanitize_text_field( $body['stage'] ?? '' );

	if ( ! flxlm_ats_is_movable_stage( $stage ) ) {
		return new WP_Error( 'flxlm_ats_hub_bad_stage', 'Unknown stage: ' . $stage, array( 'status' => 400 ) );
	}

	$user  = $request->get_param( '_flxlm_hub_user' );
	$actor = 'hub:' . ( $user instanceof WP_User ? $user->user_email : 'unknown' );

	$args = array(
		'author_email' => $user instanceof WP_User ? $user->user_email : '',
		'author_name'  => $user instanceof WP_User ? $user->display_name : '',
	);
	if ( isset( $body['note'] ) ) {
		$args['note'] = sanitize_textarea_field( $body['note'] );
	}
	if ( isset( $body['close_reason'] ) ) {
		$args['close_reason'] = sanitize_key( $body['close_reason'] );
	}
	if ( isset( $body['start_date'] ) ) {
		$args['start_date'] = sanitize_text_field( $body['start_date'] );
	}

	$result = flxlm_ats_set_stage( $application_id, $stage, $actor, $args );

	if ( is_wp_error( $result ) ) {
		if ( 'flxlm_ats_missing_fields' === $result->get_error_code() ) {
			$data = $result->get_error_data();
			return new WP_REST_Response(
				array(
					'error'  => $result->get_error_message(),
					'errors' => $data['errors'] ?? array(),
				),
				422
			);
		}
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
	}

	flxlm_ats_hub_audit( $request, 'stage:' . $stage, $application_id );

	$payload = flxlm_ats_hub_applicant_payload( $application_id, $user );

	return new WP_REST_Response( $payload, 200 );
}

/**
 * POST /applications/{id}/interviewers — add or remove an interviewer.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_interviewers( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$body           = is_array( $body ) ? $body : array();
	$user           = $request->get_param( '_flxlm_hub_user' );
	$actor          = 'hub:' . ( $user instanceof WP_User ? $user->user_email : 'unknown' );

	$action = sanitize_key( $body['action'] ?? '' );
	$email  = sanitize_email( $body['email'] ?? '' );

	if ( ! in_array( $action, array( 'add', 'remove' ), true ) ) {
		return new WP_Error( 'flxlm_ats_hub_bad_action', "action must be 'add' or 'remove'.", array( 'status' => 400 ) );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'flxlm_ats_hub_bad_email', 'A valid email is required.', array( 'status' => 400 ) );
	}

	if ( 'add' === $action ) {
		$external = ! empty( $body['external'] );
		$result   = flxlm_ats_add_interviewer( $application_id, $email, sanitize_text_field( $body['name'] ?? '' ), $actor, $external );
	} else {
		$result = flxlm_ats_remove_interviewer( $application_id, $email, $actor, sanitize_textarea_field( $body['note'] ?? '' ) );
	}

	if ( is_wp_error( $result ) ) {
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
	}

	flxlm_ats_hub_audit( $request, 'interviewer_' . $action . ':' . $email, $application_id );

	return new WP_REST_Response( flxlm_ats_hub_applicant_payload( $application_id, $user ), 200 );
}

/**
 * POST /applications/{id}/contact — edit the applicant's contact details.
 * Accepts any subset of {first_name, last_name, email, phone}.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_contact( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$body           = is_array( $body ) ? $body : array();
	$user           = $request->get_param( '_flxlm_hub_user' );

	$fields = array();
	foreach ( array( 'first_name', 'last_name', 'email', 'phone' ) as $field ) {
		if ( array_key_exists( $field, $body ) ) {
			$fields[ $field ] = $body[ $field ];
		}
	}

	$result = flxlm_ats_update_contact(
		$application_id,
		$fields,
		$user instanceof WP_User ? $user->user_email : '',
		$user instanceof WP_User ? $user->display_name : ''
	);

	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		if ( 'flxlm_ats_invalid_contact' === $result->get_error_code() ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message(), 'errors' => $data['errors'] ?? array() ),
				422
			);
		}
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 404 ) );
	}

	flxlm_ats_hub_audit( $request, 'contact_edit', $application_id );

	return new WP_REST_Response( flxlm_ats_hub_applicant_payload( $application_id, $user ), 200 );
}

/**
 * POST /applications/{id}/source — change the recruitment source.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_source( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$body           = is_array( $body ) ? $body : array();
	$user           = $request->get_param( '_flxlm_hub_user' );

	$result = flxlm_ats_update_source(
		$application_id,
		$body['source'] ?? '',
		$user instanceof WP_User ? $user->user_email : '',
		$user instanceof WP_User ? $user->display_name : ''
	);

	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		if ( 'flxlm_ats_invalid_source' === $result->get_error_code() ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message(), 'errors' => $data['errors'] ?? array() ),
				422
			);
		}
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 404 ) );
	}

	flxlm_ats_hub_audit( $request, 'source', $application_id );

	return new WP_REST_Response( flxlm_ats_hub_applicant_payload( $application_id, $user ), 200 );
}

/**
 * POST /applications/{id}/comments — a team comment, authored by the hub user.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_comment( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$body           = is_array( $body ) ? $body : array();
	$user           = $request->get_param( '_flxlm_hub_user' );

	$text = isset( $body['body'] ) ? trim( (string) $body['body'] ) : '';
	if ( '' === $text ) {
		return new WP_Error( 'flxlm_ats_hub_empty_comment', 'A comment needs a body.', array( 'status' => 422 ) );
	}

	$result = flxlm_ats_add_comment(
		$application_id,
		$text,
		$user instanceof WP_User ? $user->user_email : '',
		$user instanceof WP_User ? $user->display_name : ''
	);

	if ( is_wp_error( $result ) ) {
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
	}

	flxlm_ats_hub_audit( $request, 'comment', $application_id );

	return new WP_REST_Response( flxlm_ats_hub_applicant_payload( $application_id, $user ), 200 );
}

/**
 * POST /applications/{id}/feedback — feedback left by a hub user directly
 * (distinct from the emailed signed-link route in inc/interviewers.php, which
 * requires the sender to be an active assigned interviewer; this route only
 * requires ordinary view access, since every hub user reaching it is already
 * staff who authenticated through Cloudflare Access).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_feedback( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$body           = is_array( $body ) ? $body : array();
	$user           = $request->get_param( '_flxlm_hub_user' );

	$rating = sanitize_key( $body['rating'] ?? '' );
	$text   = isset( $body['body'] ) ? trim( (string) $body['body'] ) : '';

	if ( ! in_array( $rating, flxlm_ats_note_ratings(), true ) ) {
		return new WP_Error( 'flxlm_ats_hub_bad_rating', 'rating must be one of: ' . implode( ', ', flxlm_ats_note_ratings() ), array( 'status' => 422 ) );
	}
	if ( '' === $text ) {
		return new WP_Error( 'flxlm_ats_hub_empty_feedback', 'Feedback needs a body.', array( 'status' => 422 ) );
	}

	$note_id = flxlm_ats_add_note(
		$application_id,
		'feedback',
		array(
			'body'         => $text,
			'rating'       => $rating,
			'author_email' => $user instanceof WP_User ? $user->user_email : '',
			'author_name'  => $user instanceof WP_User ? $user->display_name : '',
		)
	);

	if ( is_wp_error( $note_id ) ) {
		return new WP_Error( $note_id->get_error_code(), $note_id->get_error_message(), array( 'status' => 400 ) );
	}

	if ( $user instanceof WP_User ) {
		flxlm_ats_notify_feedback_received( $application_id, $user->user_email );
	}

	flxlm_ats_hub_audit( $request, 'feedback', $application_id );

	return new WP_REST_Response( flxlm_ats_hub_applicant_payload( $application_id, $user ), 200 );
}

/**
 * POST /applications/{id}/fit — record a "right person? right job?" check,
 * authored by the hub user. Append-only: a changed mind is a new check.
 *
 * Body: {person: 'yes'|'maybe'|'no', job: 'this_job'|'other_job'|'no_job',
 * other_job_id (required with other_job: an open posting other than this
 * application's own), note (optional, up to 1000 characters)}.
 *
 * Answers 422 {error, errors:[{field,message}]} for a bad answer, and the full
 * applicant JSON on success.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_fit( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$body           = is_array( $body ) ? $body : array();
	$user           = $request->get_param( '_flxlm_hub_user' );

	$result = flxlm_ats_add_fit_check(
		$application_id,
		$body,
		$user instanceof WP_User ? $user->user_email : '',
		$user instanceof WP_User ? $user->display_name : ''
	);

	if ( is_wp_error( $result ) ) {
		if ( 'flxlm_ats_invalid_fit' === $result->get_error_code() ) {
			$data = $result->get_error_data();
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message(), 'errors' => $data['errors'] ?? array() ),
				422
			);
		}
		$status = 'flxlm_ats_no_application' === $result->get_error_code() ? 404 : 400;
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
	}

	flxlm_ats_hub_audit( $request, 'fit', $application_id );

	return new WP_REST_Response( flxlm_ats_hub_applicant_payload( $application_id, $user ), 200 );
}

/**
 * POST /applications/{id}/consider-for — create a new application on another
 * open job for the same person (copying their details, sharing the stored
 * resume), so that job's hiring manager and the business manager are told.
 * No receipt goes to the applicant.
 *
 * Body: {job_id}. Answers 422 {error, errors:[{field,message}]} when the job
 * is not an open posting or the person already has an application on it, and
 * {application_id, job_id, job_title} on success.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_consider_for( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$body           = $request->get_json_params();
	$body           = is_array( $body ) ? $body : array();
	$user           = $request->get_param( '_flxlm_hub_user' );
	$job_id         = isset( $body['job_id'] ) && is_numeric( $body['job_id'] ) ? (int) $body['job_id'] : 0;

	$result = flxlm_ats_consider_for(
		$application_id,
		$job_id,
		$user instanceof WP_User ? $user->user_email : '',
		$user instanceof WP_User ? $user->display_name : ''
	);

	if ( is_wp_error( $result ) ) {
		if ( 'flxlm_ats_invalid_consider' === $result->get_error_code() ) {
			$data = $result->get_error_data();
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message(), 'errors' => $data['errors'] ?? array() ),
				422
			);
		}
		$status = 'flxlm_ats_no_application' === $result->get_error_code() ? 404 : 400;
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
	}

	flxlm_ats_hub_audit( $request, 'consider_for:' . $job_id, $application_id );

	return new WP_REST_Response(
		array(
			'application_id' => (int) $result,
			'job_id'         => $job_id,
			'job_title'      => wp_specialchars_decode( flxlm_ats_job_title( $result ), ENT_QUOTES ),
		),
		200
	);
}

/**
 * POST /applications/{id}/job — attach a job to an application that has none,
 * or reassign it. The primary use is an email-intake arrival the intake
 * script could not match to a posting (job_id 0, the hub's "Unassigned"
 * pseudo-vacancy).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flxlm_ats_hub_post_job( $request ) {
	$application_id = (int) $request->get_param( 'id' );
	$application    = flxlm_ats_get_application( $application_id );
	if ( ! $application ) {
		return new WP_Error( 'flxlm_ats_hub_no_applicant', 'No such applicant.', array( 'status' => 404 ) );
	}

	$body   = $request->get_json_params();
	$body   = is_array( $body ) ? $body : array();
	$job_id = isset( $body['job_id'] ) ? (int) $body['job_id'] : 0;

	$job = get_post( $job_id );
	if ( ! $job || 'flxlm_job' !== $job->post_type ) {
		return new WP_Error( 'flxlm_ats_hub_bad_job', 'No such job posting.', array( 'status' => 422 ) );
	}

	$title = wp_specialchars_decode( get_the_title( $job ), ENT_QUOTES );

	update_post_meta( $application_id, '_flxlm_job_id', $job_id );
	update_post_meta( $application_id, '_flxlm_job_title', $title );

	if ( function_exists( 'flxlm_ats_add_note' ) ) {
		flxlm_ats_add_note( $application_id, 'system', array( 'body' => 'Assigned to job: ' . $title ) );
	}

	flxlm_ats_hub_audit( $request, 'assign_job:' . $job_id, $application_id );

	$user = $request->get_param( '_flxlm_hub_user' );
	return new WP_REST_Response( flxlm_ats_hub_applicant_payload( $application_id, $user ), 200 );
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

/**
 * Record a denied access attempt — a bad signature, an expired timestamp, an
 * unknown email, or a missing capability. Before this, only successful
 * requests were ever logged, which meant an unauthorized probe against this
 * endpoint (ex-employee, wrong-capability account) left zero trace in the one
 * table meant to answer "who looked at what."
 *
 * @param string          $email  The claimed or resolved email (may be unverified).
 * @param int             $user_id WP user ID, or 0 if none resolved.
 * @param string          $reason Short machine-readable reason, e.g. 'missing_cap:flxlm_view_applications'.
 * @param WP_REST_Request $request Request, for the route.
 */
function flxlm_ats_hub_audit_denied( $email, $user_id, $reason, $request ) {
	global $wpdb;

	flxlm_ats_hub_maybe_install_audit_table();

	$wpdb->insert(
		flxlm_ats_hub_audit_table(),
		array(
			'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			'email'          => sanitize_email( (string) $email ),
			'action'         => 'denied:' . sanitize_text_field( $reason ) . ' on ' . sanitize_text_field( $request->get_route() ),
			'application_id' => 0,
		),
		array( '%s', '%s', '%s', '%d' )
	);
}
