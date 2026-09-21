<?php
/**
 * The stage ladder.
 *
 * Stages are WordPress post statuses rather than a taxonomy or a meta field.
 * That choice buys the whole native admin experience for free: the "All (12) |
 * New (3) | Screening (2) | ..." links above the applications list, the status
 * filter, and the counts, all without writing a custom list screen. An
 * applicant is in exactly one stage, which is what a post status models and
 * what a taxonomy does not.
 *
 * THE LADDER IS SHORT ON PURPOSE. Six stages plus one terminal rejection is the
 * conventional small-company ladder. Every rung has to earn itself, because the
 * thing this replaces is a mailto: link that costs nothing to operate, and an
 * ATS nobody uses is worse than the mailto: it replaced.
 *
 * WHY "INTERVIEWED" IS ITS OWN RUNG
 *
 * 47 CFR 73.2080(c)(6)(iv) requires the annual EEO Public File Report to state
 * the total number of persons interviewed for each full-time vacancy, and the
 * number of interviewees referred by each recruitment source. The company's
 * currently posted report says "Total Number of Persons Interviewed During This
 * Period: 0" while the same document lists five vacancies filled and thirteen
 * interviewees by source. That self-contradiction is the single most common EEO
 * audit finding, and it exists because nothing in the old process recorded an
 * interview as a fact.
 *
 * A generic "reviewed" flag cannot answer the question. So Interviewed is a
 * distinct, timestamped state, and the timestamp is ONE-WAY: once someone has
 * been interviewed, that is a historical fact about the world, and moving them
 * onward to Offer, back to Manager Review, or out to Not Selected must never
 * erase it. See flxlm_ats_set_stage().
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * The ladder, in order.
 *
 * 'terminal' marks a stage nobody advances out of in the normal course.
 * 'counts_as_interviewed' marks the rung that stamps the EEO interview fact.
 *
 * @return array<string,array>
 */
function flxlm_ats_stages() {
	return array(
		'flxlm_new' => array(
			'label'       => 'New',
			'description' => 'Received, nobody has looked yet.',
		),
		'flxlm_screening' => array(
			'label'       => 'Screening',
			'description' => 'Someone is reading the application.',
		),
		'flxlm_manager' => array(
			'label'       => 'Manager Review',
			'description' => 'With the hiring manager for a decision on interviewing.',
		),
		'flxlm_interviewed' => array(
			'label'                 => 'Interviewed',
			'description'           => 'Has been interviewed. Recorded for the FCC EEO report.',
			'counts_as_interviewed' => true,
		),
		'flxlm_offer' => array(
			'label'       => 'Offer',
			'description' => 'An offer has been extended.',
		),
		'flxlm_hired' => array(
			'label'       => 'Hired',
			'description' => 'Accepted and hired. Fills the vacancy for EEO reporting.',
			'terminal'    => true,
		),
		'flxlm_rejected' => array(
			'label'       => 'Not Selected',
			'description' => 'Not moving forward. Reachable from any stage.',
			'terminal'    => true,
		),
	);
}

/**
 * The first stage every application enters.
 */
function flxlm_ats_initial_stage() {
	return 'flxlm_new';
}

/**
 * Whether a stage key is one of ours.
 *
 * @param string $stage Stage key.
 * @return bool
 */
function flxlm_ats_is_stage( $stage ) {
	return array_key_exists( (string) $stage, flxlm_ats_stages() );
}

/**
 * Human label for a stage.
 *
 * @param string $stage Stage key.
 * @return string
 */
function flxlm_ats_stage_label( $stage ) {
	$stages = flxlm_ats_stages();
	return isset( $stages[ $stage ] ) ? $stages[ $stage ]['label'] : 'Unknown';
}

/**
 * Register each stage as a post status.
 *
 * Every stage is internal and non-public. An application must never be
 * queryable on the front end, exposed in a feed, or turn up in site search:
 * these records hold home addresses and phone numbers.
 */
function flxlm_ats_register_stages() {
	foreach ( flxlm_ats_stages() as $key => $stage ) {
		register_post_status(
			$key,
			array(
				'label'                     => $stage['label'],
				'public'                    => false,
				'internal'                  => false,
				'private'                   => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: applicant count. */
				'label_count'               => _n_noop(
					$stage['label'] . ' <span class="count">(%s)</span>',
					$stage['label'] . ' <span class="count">(%s)</span>',
					'flxlm-ats'
				),
			)
		);
	}
}
add_action( 'init', 'flxlm_ats_register_stages' );

/**
 * Move an application to a stage, recording everything the EEO report needs.
 *
 * This is the ONLY supported way to change an application's stage. It does four
 * things that a bare wp_update_post() would not:
 *
 *   1. Stamps _flxlm_interviewed_at the first time the applicant reaches the
 *      Interviewed rung, and NEVER clears it afterward. An interview is a fact
 *      about the past; later stage moves cannot un-happen it. This is what
 *      makes the EEO interviewee count trustworthy.
 *   2. Stamps _flxlm_hired_at on reaching Hired, which is what marks a vacancy
 *      as filled in the reporting period.
 *   3. Appends to an append-only stage history, so an audit can reconstruct who
 *      moved whom and when. Records are never rewritten in place.
 *   4. Refuses unknown stages rather than writing garbage into post_status.
 *
 * @param int    $application_id Application post ID.
 * @param string $stage          Target stage key.
 * @param string $actor          Optional note about who or what moved it.
 * @return bool|WP_Error True on success.
 */
function flxlm_ats_set_stage( $application_id, $stage, $actor = '' ) {
	$application_id = (int) $application_id;
	$post           = get_post( $application_id );

	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	if ( ! flxlm_ats_is_stage( $stage ) ) {
		return new WP_Error( 'flxlm_ats_bad_stage', 'Unknown stage: ' . $stage );
	}

	$from = $post->post_status;
	if ( $from === $stage ) {
		return true; // Already there. Moving again is not an error, it is a no-op.
	}

	$stages = flxlm_ats_stages();

	// (1) The one-way interview stamp. Only ever set, never cleared.
	if ( ! empty( $stages[ $stage ]['counts_as_interviewed'] ) && ! get_post_meta( $application_id, '_flxlm_interviewed_at', true ) ) {
		update_post_meta( $application_id, '_flxlm_interviewed_at', gmdate( 'Y-m-d H:i:s' ) );
	}

	// (2) The hire stamp, which is what fills a vacancy for the reporting year.
	if ( 'flxlm_hired' === $stage && ! get_post_meta( $application_id, '_flxlm_hired_at', true ) ) {
		update_post_meta( $application_id, '_flxlm_hired_at', gmdate( 'Y-m-d H:i:s' ) );
	}

	$updated = wp_update_post(
		array(
			'ID'          => $application_id,
			'post_status' => $stage,
		),
		true
	);

	if ( is_wp_error( $updated ) ) {
		return $updated;
	}

	// (3) Append-only history.
	$history   = get_post_meta( $application_id, '_flxlm_stage_history', true );
	$history   = is_array( $history ) ? $history : array();
	$history[] = array(
		'from' => $from,
		'to'   => $stage,
		'at'   => gmdate( 'Y-m-d H:i:s' ),
		'by'   => $actor ? $actor : flxlm_ats_current_actor(),
	);
	update_post_meta( $application_id, '_flxlm_stage_history', $history );

	/**
	 * Fires after an application changes stage.
	 *
	 * @param int    $application_id Application ID.
	 * @param string $stage          New stage.
	 * @param string $from           Previous stage.
	 */
	do_action( 'flxlm_ats_stage_changed', $application_id, $stage, $from );

	return true;
}

/**
 * A short description of who is acting, for the history log.
 *
 * @return string
 */
function flxlm_ats_current_actor() {
	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		return 'user:' . $user->user_login;
	}
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return 'cron';
	}
	return 'system';
}

/**
 * Whether this applicant has ever been interviewed.
 *
 * Reads the one-way stamp rather than the current stage, because someone who
 * was interviewed and then rejected is still an interviewee for EEO purposes.
 * Counting only people currently sitting on the Interviewed rung would
 * undercount exactly the way the posted report already does.
 *
 * @param int $application_id Application ID.
 * @return bool
 */
function flxlm_ats_was_interviewed( $application_id ) {
	return (bool) get_post_meta( (int) $application_id, '_flxlm_interviewed_at', true );
}
