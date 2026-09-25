<?php
/**
 * Application intake: the one path every application travels.
 *
 * Applications arrive three ways, and all three end up here:
 *
 *   web         someone filled in the form on flxlocalmedia.com
 *   fldn-relay  someone filled in the form on fingerlakesdailynews.com and
 *               that site relayed it over
 *   manual      a person walked in, called, or emailed, and a staff member
 *               entered them by hand
 *
 * Keeping one function responsible for creating the record is what stops the
 * three routes drifting apart on validation, on required fields, or on the EEO
 * data. It matters most for the manual route: an applicant who phoned in counts
 * toward the FCC interviewee and source numbers exactly like one who used the
 * form, and a system that can only see web submissions produces a report that
 * undercounts in precisely the way the company's currently posted one does.
 *
 * NEW YORK SALARY HISTORY BAN
 *
 * There is deliberately no field anywhere here for an applicant's current or
 * previous pay. New York prohibits an employer from asking for or relying on
 * wage history as a condition of being considered. Asking would itself be the
 * violation, so the field does not exist. An OPTIONAL salary EXPECTATION is
 * fine and is captured instead.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * How many applications one IP may submit per hour.
 *
 * Generous for a person (someone legitimately applying to several of the nine
 * open reporter postings in one sitting is normal and must not be blocked) and
 * tight enough to make a scripted flood pointless.
 */
const FLXLM_ATS_RATE_LIMIT = 8;

/**
 * Check, and consume, this submitter's rate limit.
 *
 * Checked BEFORE the uploaded file is looked at, so rejecting a scripted flood
 * of large uploads costs the server almost nothing.
 *
 * A rejected attempt does NOT extend its own window: the counter is only
 * incremented on an attempt that was within the limit. Otherwise hammering the
 * endpoint after being blocked would hold the block open forever, and a
 * genuine applicant caught behind a shared office IP would never get back in.
 *
 * The transient key is a hash of the address, never the address itself, so the
 * options table never becomes a list of applicants' IPs.
 *
 * @return true|WP_Error
 */
function flxlm_ats_check_rate_limit() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( '' === $ip ) {
		return true;
	}

	$key   = 'flxlm_ats_rl_' . hash( 'sha256', $ip );
	$count = (int) get_transient( $key );

	if ( $count >= FLXLM_ATS_RATE_LIMIT ) {
		return new WP_Error(
			'flxlm_ats_rate_limited',
			'That is a lot of applications at once. Please wait a little while and try again.'
		);
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	return true;
}

/**
 * Normalise and validate the applicant-supplied fields.
 *
 * @param array $raw Unsanitised input.
 * @return array|WP_Error
 */
function flxlm_ats_validate_fields( $raw ) {
	$fields = array(
		'first_name'         => sanitize_text_field( $raw['first_name'] ?? '' ),
		'last_name'          => sanitize_text_field( $raw['last_name'] ?? '' ),
		'email'              => sanitize_email( $raw['email'] ?? '' ),
		'phone'              => sanitize_text_field( $raw['phone'] ?? '' ),
		'source'             => sanitize_key( $raw['source'] ?? '' ),
		'message'            => sanitize_textarea_field( $raw['message'] ?? '' ),
		'links'              => sanitize_textarea_field( $raw['links'] ?? '' ),
		'salary_expectation' => sanitize_text_field( $raw['salary_expectation'] ?? '' ),
	);

	if ( '' === $fields['first_name'] || '' === $fields['last_name'] ) {
		return new WP_Error( 'flxlm_ats_name_required', 'Please tell us your name.' );
	}

	if ( ! is_email( $fields['email'] ) ) {
		return new WP_Error( 'flxlm_ats_email_required', 'Please give us an email address we can reach you at.' );
	}

	// Required, because the EEO report cannot be produced without it. The form
	// offers a "Somewhere else" option so nobody is ever stuck.
	if ( ! flxlm_ats_is_source( $fields['source'] ) ) {
		return new WP_Error( 'flxlm_ats_source_required', 'Please let us know how you heard about this job.' );
	}

	return $fields;
}

/**
 * Create an application record.
 *
 * @param array $args {
 *     @type array  $fields       Validated applicant fields.
 *     @type int    $job_id       Local flxlm_job ID, 0 when not on this site.
 *     @type string $job_title    Job title as it was when they applied.
 *     @type array  $resume       Result of a storage function, or null.
 *     @type string $entered_by   'web' | 'fldn-relay' | 'manual'.
 *     @type string $source_site  'flxlocalmedia' | 'fldn'.
 *     @type string $submitted_at UTC timestamp; defaults to now.
 * }
 * @return int|WP_Error Application post ID.
 */
function flxlm_ats_create_application( $args ) {
	// Every optional key gets a default here. Callers legitimately omit the ones
	// that do not apply to them (the web form has no submitted_at to pass; a
	// manual entry has no resume), and a missing-key warning on the intake path
	// would fire on every single application.
	$args = array_merge(
		array(
			'job_id'       => 0,
			'job_title'    => '',
			'resume'       => null,
			'entered_by'   => 'web',
			'source_site'  => 'flxlocalmedia',
			'submitted_at' => '',
		),
		(array) $args
	);

	$fields = $args['fields'];
	$title  = trim( $fields['first_name'] . ' ' . $fields['last_name'] );
	$job    = $args['job_title'] ? $args['job_title'] : 'General application';

	$application_id = wp_insert_post(
		array(
			'post_type'   => 'flxlm_application',
			'post_status' => flxlm_ats_initial_stage(),
			// The applicant's name alone. The job has its own column on the list
			// screen and its own line on the application, so repeating it in every
			// title just pushed the name, which is the thing anyone is actually
			// scanning for, off the edge of the column.
			'post_title'  => $title,
			// Content is left empty on purpose; everything lives in meta so the
			// EEO export can select columns without parsing prose.
			'post_content' => '',
		),
		true
	);

	if ( is_wp_error( $application_id ) ) {
		return $application_id;
	}

	$meta = array(
		'_flxlm_first_name'         => $fields['first_name'],
		'_flxlm_last_name'          => $fields['last_name'],
		'_flxlm_email'              => $fields['email'],
		'_flxlm_phone'              => $fields['phone'],
		'_flxlm_source'             => $fields['source'],
		'_flxlm_message'            => $fields['message'],
		'_flxlm_links'              => $fields['links'],
		'_flxlm_salary_expectation' => $fields['salary_expectation'],
		'_flxlm_job_id'             => (int) $args['job_id'],
		'_flxlm_job_title'          => $job,
		'_flxlm_entered_by'         => $args['entered_by'],
		'_flxlm_source_site'        => $args['source_site'],
		'_flxlm_submitted_at'       => $args['submitted_at'] ? $args['submitted_at'] : gmdate( 'Y-m-d H:i:s' ),
	);

	if ( ! empty( $args['resume'] ) && is_array( $args['resume'] ) ) {
		$meta['_flxlm_resume_file'] = $args['resume']['stored_name'];
		$meta['_flxlm_resume_name'] = $args['resume']['original_name'];

		// The resume row is written while the file is being validated, which is
		// before this post exists, so it starts life unattached. Point it at the
		// application now that there is one to point at.
		flxlm_ats_link_resume( (int) $args['resume']['stored_name'], $application_id );
	}

	foreach ( $meta as $key => $value ) {
		update_post_meta( $application_id, $key, $value );
	}

	/**
	 * Fires once an application record exists.
	 *
	 * @param int   $application_id Application ID.
	 * @param array $args           Intake arguments.
	 */
	do_action( 'flxlm_ats_application_received', $application_id, $args );

	return $application_id;
}

/**
 * Resolve a job posting to its ID and historical title.
 *
 * Accepts either a local post ID or, for relayed applications, a title string
 * from the other site. The title is always recorded even when the ID resolves,
 * because that is what the EEO report reads years later.
 *
 * @param mixed  $job_ref   Post ID or title.
 * @param string $fallback  Title to use when nothing resolves.
 * @return array{id:int,title:string}
 */
function flxlm_ats_resolve_job( $job_ref, $fallback = '' ) {
	$id    = 0;
	$title = (string) $fallback;

	if ( is_numeric( $job_ref ) ) {
		$post = get_post( (int) $job_ref );
		if ( $post && 'flxlm_job' === $post->post_type ) {
			$id    = $post->ID;
			$title = $post->post_title;
		}
	} elseif ( is_string( $job_ref ) && '' !== trim( $job_ref ) ) {
		$title = sanitize_text_field( $job_ref );

		// Best-effort match against a local posting so the admin can link
		// through while the posting still exists. Deliberately not
		// get_page_by_title(): that is deprecated as of WP 6.2 and this site
		// runs 7.1, so calling it would raise a notice today and break outright
		// on the next major.
		$match = get_posts(
			array(
				'post_type'        => 'flxlm_job',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'title'            => $title,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		if ( $match ) {
			$id = (int) $match[0];
		}
	}

	return array(
		'id'    => $id,
		'title' => $title ? $title : 'General application',
	);
}
