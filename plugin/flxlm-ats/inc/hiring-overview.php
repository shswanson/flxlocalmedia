<?php
/**
 * What the hub's list screens need to know about many applications at once.
 *
 * Added in 1.4.0 for the hub usability pass (owner feedback 2026-10-14: the
 * job list was "a big wall of text", stages had no colour, and nothing said
 * which candidates need a person). Everything here is READ-ONLY and derived
 * from records that already exist; nothing in this file writes anything.
 *
 * ONE DEFINITION PER IDEA
 *
 * The hub's "stuck" count, its "over target" pill, its "feedback overdue" count
 * and the Monday digest email must agree about who is stuck, or the count in
 * the strip and the list underneath it contradict each other and nobody trusts
 * either. So the rules are not re-implemented here: "stuck" is
 * flxlm_ats_is_stuck() from inc/digest.php, feedback timing uses the digest's
 * own constant and business-day counter, and the stage a candidate is in comes
 * from the same history the digest reads.
 *
 * THE AI SUMMARY IS NEVER AN INPUT
 *
 * Nothing in this file reads the AI resume summary (inc/ai-summary.php). It is
 * advisory text for a person to read; it must never feed a sort, a filter, a
 * count or an exit test, and a signal that depended on it would be exactly
 * that. The fit check (inc/fit-check.php) IS an input here, but it is a human
 * judgment typed by staff.
 *
 * WHY A CONTEXT OBJECT
 *
 * A list of 200 applicants must not run 200 sets of queries. The context reads
 * the postmeta, the feedback and fit notes, the resume types and the
 * same-email counts for all of them in a handful of queries, then each
 * applicant's signals are computed from memory.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * The hub page for one candidate.
 *
 * These are the real, History-API URLs the hub serves (hiring.html reads
 * /hiring/app/{id}). The old ?application={id} form still redirects in the
 * hub, so emails already sitting in someone's inbox keep working.
 *
 * @param int $application_id Application ID.
 * @return string
 */
function flxlm_ats_hub_application_url( $application_id ) {
	return 'https://hub.flxlocalmedia.com/hiring/app/' . (int) $application_id;
}

/**
 * A stored UTC 'Y-m-d H:i:s' as an ISO 8601 string ('2026-10-14T15:04:05Z').
 *
 * @param string $mysql_gmt UTC datetime, or ''.
 * @return string ISO string, or '' when there is nothing to convert.
 */
function flxlm_ats_iso( $mysql_gmt ) {
	$mysql_gmt = (string) $mysql_gmt;
	if ( '' === $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
		return '';
	}
	$ts = strtotime( $mysql_gmt . ' UTC' );
	return $ts ? gmdate( 'Y-m-d\TH:i:s\Z', $ts ) : '';
}

/**
 * {key,label} for a stage.
 *
 * @param string $stage Stage key.
 * @return array{key:string,label:string}
 */
function flxlm_ats_stage_info( $stage ) {
	return array( 'key' => (string) $stage, 'label' => flxlm_ats_stage_label( $stage ) );
}

/**
 * Whether a stage is closed (Hired or Not hired).
 *
 * @param string $stage Stage key.
 * @return bool
 */
function flxlm_ats_is_terminal_stage( $stage ) {
	$stages = flxlm_ats_stages();
	return ! empty( $stages[ $stage ]['terminal'] );
}

/**
 * IDs of every application in any stage (retired keys included).
 *
 * @return int[]
 */
function flxlm_ats_all_application_ids() {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => 'flxlm_application',
				'post_status'      => array_keys( flxlm_ats_stages() ),
				'posts_per_page'   => -1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		)
	);
}

/**
 * Whether a job posting is open. Published is open; anything else (draft,
 * pending, private, scheduled, trashed) is closed.
 *
 * @param WP_Post $job Job posting.
 * @return string 'open' or 'closed'.
 */
function flxlm_ats_job_status_key( $job ) {
	return ( $job && 'publish' === $job->post_status ) ? 'open' : 'closed';
}

/**
 * A job as the hub needs to link to it: {id, title, status, permalink}.
 *
 * Null for an application attached to no job. For an application whose job
 * post has since been deleted, the stored title (the historical fact, see
 * flxlm_ats_job_title()) comes back with status 'closed' and no permalink.
 *
 * @param int    $job_id       Job posting ID, or 0.
 * @param string $stored_title Title stored on the application, for a deleted posting.
 * @return array|null
 */
function flxlm_ats_job_summary( $job_id, $stored_title = '' ) {
	$job_id = (int) $job_id;
	if ( ! $job_id ) {
		return null;
	}

	$job = get_post( $job_id );
	if ( ! $job || 'flxlm_job' !== $job->post_type ) {
		return array(
			'id'        => $job_id,
			'title'     => wp_specialchars_decode( (string) $stored_title, ENT_QUOTES ),
			'status'    => 'closed',
			'permalink' => '',
		);
	}

	return array(
		'id'        => $job_id,
		'title'     => wp_specialchars_decode( get_the_title( $job ), ENT_QUOTES ),
		'status'    => flxlm_ats_job_status_key( $job ),
		'permalink' => 'publish' === $job->post_status ? (string) get_permalink( $job ) : '',
	);
}

/**
 * A hiring manager as the hub shows them: {id, name, email}, or null.
 *
 * @param int $job_id Job posting ID.
 * @return array|null
 */
function flxlm_ats_hiring_manager_summary( $job_id ) {
	$manager = $job_id ? flxlm_ats_job_hiring_manager( $job_id ) : null;
	return $manager ? array( 'id' => (int) $manager->ID, 'name' => $manager->display_name, 'email' => $manager->user_email ) : null;
}

/**
 * Preload everything the per-applicant signals read, for a set of applications.
 *
 * @param int[] $application_ids The applications a screen is about to list.
 * @return array The context: pass it to flxlm_ats_hub_signals().
 */
function flxlm_ats_hub_context( $application_ids ) {
	global $wpdb;

	$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $application_ids ) ) ) );

	$ctx = array(
		'notes'       => array(), // id => feedback + fit notes, oldest first.
		'resume_mime' => array(), // resume id => mime type.
		'email_count' => array(), // lowercased email => applications across every job.
	);

	if ( $ids ) {
		// Two queries for every post row and all their postmeta, so the
		// get_post(), get_post_status() and get_post_meta() calls below never
		// touch the database one application at a time.
		_prime_post_caches( $ids, false, true );

		$ctx['notes'] = flxlm_ats_get_notes_for_applications( $ids, array( 'feedback', 'fit' ) );

		$resume_ids = array();
		foreach ( $ids as $id ) {
			$resume_id = (int) get_post_meta( $id, '_flxlm_resume_file', true );
			if ( $resume_id > 0 ) {
				$resume_ids[ $resume_id ] = true;
			}
		}
		if ( $resume_ids ) {
			$table   = flxlm_ats_resume_table();
			$holders = implode( ',', array_fill( 0, count( $resume_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input; placeholders are bound.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, mime_type FROM {$table} WHERE id IN ({$holders})", array_keys( $resume_ids ) ), ARRAY_A );
			foreach ( (array) $rows as $row ) {
				$ctx['resume_mime'][ (int) $row['id'] ] = (string) $row['mime_type'];
			}
		}
	}

	$ctx['email_count'] = flxlm_ats_application_email_counts();

	return $ctx;
}

/**
 * How many applications exist per applicant email, across every job and stage.
 * Keys are trimmed and lowercased: "Same person" is the lowercased email.
 *
 * @return array<string,int>
 */
function flxlm_ats_application_email_counts() {
	global $wpdb;

	$statuses = array_keys( flxlm_ats_stages() );
	$holders  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table names; every value is a bound placeholder.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT LOWER(TRIM(m.meta_value)) AS e, COUNT(*) AS c
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_flxlm_email'
			 WHERE p.post_type = 'flxlm_application' AND p.post_status IN ({$holders})
			 GROUP BY e",
			$statuses
		),
		ARRAY_A
	);
	// phpcs:enable

	$out = array();
	foreach ( (array) $rows as $row ) {
		if ( '' !== $row['e'] ) {
			$out[ $row['e'] ] = (int) $row['c'];
		}
	}
	return $out;
}

/**
 * Every signal the hub's list screens show for one application.
 *
 * @param int        $application_id Application ID.
 * @param array|null $ctx            From flxlm_ats_hub_context(); built for this one
 *                                    application when null.
 * @return array
 */
function flxlm_ats_hub_signals( $application_id, $ctx = null ) {
	$application_id = (int) $application_id;
	if ( null === $ctx ) {
		$ctx = flxlm_ats_hub_context( array( $application_id ) );
	}

	$stage    = (string) get_post_status( $application_id );
	$terminal = flxlm_ats_is_terminal_stage( $stage );
	$notes    = $ctx['notes'][ $application_id ] ?? array();

	$feedback_notes = array();
	foreach ( $notes as $note ) {
		if ( 'feedback' === $note['kind'] ) {
			$feedback_notes[] = $note;
		}
	}

	// --- Time in stage. Calendar days for display, business days for the rule. ---
	$entered_at    = flxlm_ats_current_stage_entered_at( $application_id );
	$entered_ts    = $entered_at ? strtotime( $entered_at . ' UTC' ) : 0;
	$calendar_days = $entered_ts ? max( 0, (int) floor( ( time() - $entered_ts ) / DAY_IN_SECONDS ) ) : 0;
	$business_days = flxlm_ats_business_days_since( $entered_at );
	$stuck         = ! $terminal && flxlm_ats_is_stuck( $application_id, $stage );

	// --- Interviewers and their feedback. ---
	$interviewers = flxlm_ats_active_interviewers( $application_id, $feedback_notes );
	$ratings      = array_fill_keys( flxlm_ats_note_ratings(), 0 );
	$submitted    = 0;
	$overdue      = false;
	foreach ( $interviewers as $interviewer ) {
		if ( $interviewer['feedback_submitted'] ) {
			++$submitted;
			if ( isset( $ratings[ $interviewer['rating'] ] ) ) {
				++$ratings[ $interviewer['rating'] ];
			}
			continue;
		}
		if ( flxlm_ats_business_days_since( $interviewer['assigned_at'] ) >= FLXLM_ATS_DIGEST_FEEDBACK_DAYS ) {
			$overdue = true;
		}
	}
	$feedback_missing = ! $terminal && $overdue;

	// --- Source. ---
	$source_key     = (string) get_post_meta( $application_id, '_flxlm_source', true );
	$source_unknown = ( '' === $source_key || 'unknown' === $source_key );

	// --- Fit check. ---
	$fit_state = flxlm_ats_fit_state( $notes );
	$unreviewed_fit = ( 'flxlm_new' === $stage && ! $fit_state['count'] );

	// --- Resume. ---
	$resume_id   = (int) get_post_meta( $application_id, '_flxlm_resume_file', true );
	$resume_type = $resume_id > 0 ? (string) ( $ctx['resume_mime'][ $resume_id ] ?? '' ) : '';

	// --- Same person elsewhere. ---
	$email = strtolower( trim( (string) get_post_meta( $application_id, '_flxlm_email', true ) ) );
	$other = '' === $email ? 0 : max( 0, (int) ( $ctx['email_count'][ $email ] ?? 1 ) - 1 );

	$attention = array();
	if ( ! $terminal ) {
		if ( $stuck ) {
			$attention[] = 'stuck';
		}
		if ( $source_unknown ) {
			$attention[] = 'source_unknown';
		}
		if ( $feedback_missing ) {
			$attention[] = 'feedback_missing';
		}
		if ( $unreviewed_fit ) {
			$attention[] = 'unreviewed_fit';
		}
	}

	return array(
		'stage'                  => $stage,
		'terminal'               => $terminal,
		'stage_entered_at'       => flxlm_ats_iso( $entered_at ),
		'days_in_stage'          => $calendar_days,
		'business_days_in_stage' => $business_days,
		// Stuck stages have a target (the digest's 5 business days); Interview is
		// judged on feedback instead, so there it is "an interviewer is overdue".
		'target_days'            => in_array( $stage, flxlm_ats_stuck_stages(), true ) ? FLXLM_ATS_DIGEST_STUCK_DAYS : null,
		'over_target'            => ( $stuck || ( 'flxlm_interviewed' === $stage && $feedback_missing ) ),
		'stuck'                  => $stuck,
		'source_key'             => $source_key,
		'source_unknown'         => $source_unknown,
		'has_resume'             => $resume_id > 0,
		'resume_type'            => $resume_type,
		'interviewers_total'     => count( $interviewers ),
		'feedback_submitted'     => $submitted,
		'feedback_ratings'       => $ratings,
		'feedback_missing'       => $feedback_missing,
		'fit'                    => $fit_state['count'] ? $fit_state['list_summary'] : null,
		'unreviewed_fit'         => $unreviewed_fit,
		'other_applications_count' => $other,
		'attention'              => $attention,
	);
}
