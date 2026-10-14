<?php
/**
 * "Right person? Right job?", the other applications of the same person, and
 * "also consider for".
 *
 * Owner feedback, 2026-10-14, after using the hub live: "from /app/116 there's
 * no way to go back to the job she applied for. nor is there any way to know
 * if she applied for any other jobs ... with that, there should be a right
 * person? right job? sort of evaluation."
 *
 * Applicants for a company with thirteen near-identical reporter postings, and
 * a radio group where a good sales hire is sometimes a better fit for a
 * different station, regularly apply for the wrong thing. The screening step
 * is therefore two questions, not one: is this the right PERSON, and is this
 * the right JOB for them. If the answer to the second is "another job", the
 * team needs a one-click way to put them in front of that job's hiring manager
 * without asking the candidate to re-apply.
 *
 * THIS IS A HUMAN JUDGMENT
 *
 * A fit check is typed by a member of staff. The AI resume summary
 * (inc/ai-summary.php) must never suggest, score or pre-fill it, and nothing
 * here reads the summary. The check is also not a gate: the New stage's exit
 * test for it is SOFT, like every other exit test on this ladder (see
 * inc/stages.php), so an unreviewed candidate can still be moved on.
 *
 * APPEND-ONLY
 *
 * A fit check is a 'fit' note (inc/notes.php) like a comment or feedback: it is
 * never edited or deleted, and a changed mind is a NEW check. The hub shows the
 * latest check from each person. The structured answers live in the note's JSON
 * `meta` column; the note body carries the same answers in plain words so the
 * discussion timeline in wp-admin and the hub reads as a sentence.
 *
 * "SAME PERSON"
 *
 * Two applications belong to the same person when their lowercased, trimmed
 * email addresses are equal. Email is the one identifier every intake path
 * (the web form, the FLDN relay, email intake, manual entry) collects.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** Longest note a fit check may carry. */
const FLXLM_ATS_FIT_NOTE_MAX = 1000;

/**
 * Answers to "right person?", in the order the hub shows them.
 *
 * @return array<string,string> key => label.
 */
function flxlm_ats_fit_person_options() {
	return array(
		'yes'   => 'Yes',
		'maybe' => 'Maybe',
		'no'    => 'No',
	);
}

/**
 * Answers to "right job?", in the order the hub shows them.
 *
 * @return array<string,string> key => label.
 */
function flxlm_ats_fit_job_options() {
	return array(
		'this_job'  => 'This job',
		'other_job' => 'Better fit for another job',
		'no_job'    => 'Not a fit for any opening',
	);
}

/**
 * One fit-check note as the API returns it.
 *
 * @param array $note A 'fit' row from the notes table.
 * @return array
 */
function flxlm_ats_fit_check_from_note( $note ) {
	$meta        = is_array( $note['meta'] ?? null ) ? $note['meta'] : array();
	$other_id    = (int) ( $meta['other_job_id'] ?? 0 );
	$other_job   = null;
	if ( $other_id ) {
		$live      = get_post( $other_id );
		$other_job = array(
			'id'    => $other_id,
			'title' => ( $live && 'flxlm_job' === $live->post_type )
				? wp_specialchars_decode( get_the_title( $live ), ENT_QUOTES )
				: (string) ( $meta['other_job_title'] ?? '' ),
		);
	}

	return array(
		'id'           => (int) $note['id'],
		'author_name'  => (string) $note['author_name'],
		'author_email' => (string) $note['author_email'],
		'person'       => (string) ( $meta['person'] ?? '' ),
		'job'          => (string) ( $meta['job'] ?? '' ),
		'other_job'    => $other_job,
		'note'         => (string) ( $meta['note'] ?? '' ),
		'created_at'   => (string) $note['created_at'],
	);
}

/**
 * The state of an application's fit checks, from its notes.
 *
 * @param array[] $notes Any notes for ONE application (other kinds are ignored).
 * @return array {
 *     @type int     $count        How many people have a current check.
 *     @type array[] $checks       The latest check per author, newest first.
 *     @type array   $summary      {yes, maybe, no}: how many of those answered each.
 *     @type array|null $list_summary  The compact form the applicant list shows.
 * }
 */
function flxlm_ats_fit_state( $notes ) {
	$latest = array(); // author key => note. Notes arrive oldest first, so the last one wins.
	foreach ( (array) $notes as $note ) {
		if ( 'fit' !== ( $note['kind'] ?? '' ) ) {
			continue;
		}
		$key            = strtolower( $note['author_email'] ? $note['author_email'] : $note['author_name'] );
		$latest[ $key ] = $note;
	}

	$checks = array();
	foreach ( $latest as $note ) {
		$checks[] = flxlm_ats_fit_check_from_note( $note );
	}
	usort(
		$checks,
		function ( $a, $b ) {
			return $b['id'] <=> $a['id'];
		}
	);

	$summary = array( 'yes' => 0, 'maybe' => 0, 'no' => 0 );
	foreach ( $checks as $check ) {
		if ( isset( $summary[ $check['person'] ] ) ) {
			++$summary[ $check['person'] ];
		}
	}

	$list_summary = null;
	if ( $checks ) {
		$newest = $checks[0];
		if ( 'no' === $newest['person'] || 'no_job' === $newest['job'] ) {
			$result = 'not_a_fit';
		} elseif ( 'other_job' === $newest['job'] ) {
			$result = 'other_job';
		} elseif ( 'maybe' === $newest['person'] ) {
			$result = 'maybe';
		} else {
			$result = 'right_fit';
		}

		$result_labels = array(
			'right_fit' => 'Right person, right job',
			'maybe'     => 'Maybe the right person',
			'other_job' => 'Better fit for another job',
			'not_a_fit' => 'Not a fit',
		);

		$list_summary = array(
			'count'        => count( $checks ),
			'result'       => $result,
			'result_label' => $result_labels[ $result ],
			'person'       => $newest['person'],
			'job'          => $newest['job'],
			'other_job'    => $newest['other_job'],
			'summary'      => $summary,
			'created_at'   => $newest['created_at'],
		);
	}

	return array(
		'count'        => count( $checks ),
		'checks'       => $checks,
		'summary'      => $summary,
		'list_summary' => $list_summary,
	);
}

/**
 * The fit state for one application, reading its notes.
 *
 * @param int $application_id Application ID.
 * @return array See flxlm_ats_fit_state().
 */
function flxlm_ats_application_fit( $application_id ) {
	return flxlm_ats_fit_state( flxlm_ats_get_notes( (int) $application_id, array( 'fit' ) ) );
}

/**
 * Whether anyone has recorded a fit check on this application. Backs the soft
 * "Right person, right job check done" exit test on New.
 *
 * @param int $application_id Application ID.
 * @return bool
 */
function flxlm_ats_has_fit_check( $application_id ) {
	return (bool) flxlm_ats_get_notes( (int) $application_id, array( 'fit' ) );
}

/**
 * An open posting, or null: the only kind of job a person can be steered to.
 *
 * @param int $job_id Job posting ID.
 * @return WP_Post|null
 */
function flxlm_ats_open_job( $job_id ) {
	$job = get_post( (int) $job_id );
	return ( $job && 'flxlm_job' === $job->post_type && 'publish' === $job->post_status ) ? $job : null;
}

/**
 * Record a fit check.
 *
 * @param int    $application_id Application ID.
 * @param array  $input          {person, job, other_job_id?, note?}.
 * @param string $author_email   Who is judging.
 * @param string $author_name    Display name.
 * @return int|WP_Error The note id. On a bad answer, a WP_Error with code
 *                       'flxlm_ats_invalid_fit' whose data carries
 *                       'errors' => array of {field, message}; for an unknown
 *                       application, 'flxlm_ats_no_application'.
 */
function flxlm_ats_add_fit_check( $application_id, $input, $author_email = '', $author_name = '' ) {
	$application_id = (int) $application_id;
	$application    = flxlm_ats_get_application( $application_id );
	if ( ! $application ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	$input  = is_array( $input ) ? $input : array();
	$errors = array();

	$person = is_string( $input['person'] ?? null ) ? $input['person'] : '';
	if ( ! array_key_exists( $person, flxlm_ats_fit_person_options() ) ) {
		$errors[] = array( 'field' => 'person', 'message' => "Right person must be 'yes', 'maybe' or 'no'." );
	}

	$job = is_string( $input['job'] ?? null ) ? $input['job'] : '';
	if ( ! array_key_exists( $job, flxlm_ats_fit_job_options() ) ) {
		$errors[] = array( 'field' => 'job', 'message' => "Right job must be 'this_job', 'other_job' or 'no_job'." );
	}

	$application_job_id = (int) $application['job_id'];
	$other_job          = null;

	if ( 'this_job' === $job && ! $application_job_id ) {
		$errors[] = array( 'field' => 'job', 'message' => 'This application is not attached to a job yet, so "This job" cannot be the answer. Attach it to a job, or pick another job.' );
	}

	if ( 'other_job' === $job ) {
		$other_job_id = isset( $input['other_job_id'] ) && is_numeric( $input['other_job_id'] ) ? (int) $input['other_job_id'] : 0;
		if ( ! $other_job_id ) {
			$errors[] = array( 'field' => 'other_job_id', 'message' => 'Pick the job this person is a better fit for.' );
		} elseif ( $other_job_id === $application_job_id ) {
			$errors[] = array( 'field' => 'other_job_id', 'message' => 'That is the job they already applied for. Pick a different one.' );
		} else {
			$other_job = flxlm_ats_open_job( $other_job_id );
			if ( ! $other_job ) {
				$errors[] = array( 'field' => 'other_job_id', 'message' => 'That job is not an open posting.' );
			}
		}
	}

	$note = '';
	if ( isset( $input['note'] ) ) {
		if ( ! is_string( $input['note'] ) ) {
			$errors[] = array( 'field' => 'note', 'message' => 'The note must be text.' );
		} else {
			$note = trim( sanitize_textarea_field( $input['note'] ) );
			if ( function_exists( 'mb_strlen' ) ? mb_strlen( $note ) > FLXLM_ATS_FIT_NOTE_MAX : strlen( $note ) > FLXLM_ATS_FIT_NOTE_MAX ) {
				$errors[] = array( 'field' => 'note', 'message' => 'Keep the note under ' . FLXLM_ATS_FIT_NOTE_MAX . ' characters.' );
			}
		}
	}

	if ( $errors ) {
		return new WP_Error( 'flxlm_ats_invalid_fit', 'The fit check was not saved.', array( 'errors' => $errors ) );
	}

	$people = flxlm_ats_fit_person_options();
	$jobs   = flxlm_ats_fit_job_options();

	$other_title = $other_job ? wp_specialchars_decode( get_the_title( $other_job ), ENT_QUOTES ) : '';
	$body        = 'Right person? ' . $people[ $person ] . '. Right job? '
		. ( 'other_job' === $job ? 'Better fit for ' . $other_title : $jobs[ $job ] ) . '.';
	if ( '' !== $note ) {
		$body .= ' ' . $note;
	}

	$note_id = flxlm_ats_add_note(
		$application_id,
		'fit',
		array(
			'body'         => $body,
			'author_email' => $author_email,
			'author_name'  => $author_name,
			'meta'         => array(
				'person'          => $person,
				'job'             => $job,
				'other_job_id'    => $other_job ? (int) $other_job->ID : 0,
				'other_job_title' => $other_title,
				'note'            => $note,
			),
		)
	);

	return $note_id;
}

/**
 * The other applications from the same person.
 *
 * Matched on the lowercased, trimmed email. Never includes the record asked
 * about. Newest first.
 *
 * @param int $application_id Application ID.
 * @return array[] {id, job_id, job_title, stage:{key,label}, applied_at}
 */
function flxlm_ats_other_applications( $application_id ) {
	global $wpdb;

	$application_id = (int) $application_id;
	$email          = strtolower( trim( (string) get_post_meta( $application_id, '_flxlm_email', true ) ) );
	if ( '' === $email ) {
		return array();
	}

	$statuses = array_keys( flxlm_ats_stages() );
	$holders  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table names; every value is a bound placeholder.
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_flxlm_email'
			 WHERE p.post_type = 'flxlm_application' AND p.post_status IN ({$holders})
			   AND LOWER(TRIM(m.meta_value)) = %s AND p.ID <> %d
			 ORDER BY p.ID DESC",
			array_merge( $statuses, array( $email, $application_id ) )
		)
	);
	// phpcs:enable

	$out = array();
	foreach ( (array) $ids as $other_id ) {
		$other_id = (int) $other_id;
		$stage    = (string) get_post_status( $other_id );
		$applied  = (string) get_post_meta( $other_id, '_flxlm_submitted_at', true );
		if ( '' === $applied ) {
			$post    = get_post( $other_id );
			$applied = $post ? get_gmt_from_date( $post->post_date ) : '';
		}
		$out[] = array(
			'id'         => $other_id,
			'job_id'     => (int) get_post_meta( $other_id, '_flxlm_job_id', true ),
			'job_title'  => wp_specialchars_decode( flxlm_ats_job_title( $other_id ), ENT_QUOTES ),
			'stage'      => flxlm_ats_stage_info( $stage ),
			'applied_at' => flxlm_ats_iso( $applied ),
		);
	}

	return $out;
}

/**
 * Create a new application on another open job for the same person.
 *
 * "Also consider for": a staff member decided the person would be a better fit
 * for a different posting. This does what the person would have done if they
 * had applied there themselves, minus the work: it copies their name, email,
 * phone, links, cover note and recruitment source, and POINTS the new record
 * at the SAME stored resume (one set of bytes, not a second copy of someone's
 * home address). The new job's hiring manager and the business manager are
 * emailed through the normal new-application notice, and the person is NOT
 * sent a receipt (inc/notify.php), because they did not apply and must not be
 * told they did.
 *
 * Both records get a system note naming the other, so either timeline explains
 * the pairing. Salary expectation is deliberately not copied: it was stated for
 * a different job.
 *
 * @param int    $application_id The application being used as the source.
 * @param int    $job_id         The open posting to consider them for.
 * @param string $author_email   Who is doing it.
 * @param string $author_name    Display name.
 * @return int|WP_Error The NEW application's id. 'flxlm_ats_no_application' for an
 *                       unknown source; 'flxlm_ats_invalid_consider' with
 *                       data['errors'] = array of {field, message} otherwise.
 */
function flxlm_ats_consider_for( $application_id, $job_id, $author_email = '', $author_name = '' ) {
	$application_id = (int) $application_id;
	$job_id         = (int) $job_id;
	$source         = flxlm_ats_get_application( $application_id );
	if ( ! $source ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	$job = flxlm_ats_open_job( $job_id );
	if ( ! $job ) {
		return new WP_Error(
			'flxlm_ats_invalid_consider',
			'That job is not an open posting.',
			array( 'errors' => array( array( 'field' => 'job_id', 'message' => 'That job is not an open posting.' ) ) )
		);
	}

	// A double click, or two people acting at once, must not create two records.
	// add_option() is atomic (option names are unique), which a read-then-write
	// on the applications themselves is not. A lock older than a minute is a
	// crashed request and is ignored.
	$lock_key = 'flxlm_ats_lock_consider_' . md5( strtolower( trim( (string) $source['email'] ) ) . '|' . $job_id );
	$held     = (int) get_option( $lock_key, 0 );
	if ( $held && ( time() - $held ) < MINUTE_IN_SECONDS ) {
		return new WP_Error(
			'flxlm_ats_invalid_consider',
			'This person is already being added to that job. Give it a moment and refresh.',
			array( 'errors' => array( array( 'field' => 'job_id', 'message' => 'This person is already being added to that job. Give it a moment and refresh.' ) ) )
		);
	}
	if ( $held ) {
		delete_option( $lock_key ); // Stale: a request that died while holding it.
	}
	if ( ! add_option( $lock_key, time(), '', 'no' ) ) {
		return new WP_Error(
			'flxlm_ats_invalid_consider',
			'This person is already being added to that job. Give it a moment and refresh.',
			array( 'errors' => array( array( 'field' => 'job_id', 'message' => 'This person is already being added to that job. Give it a moment and refresh.' ) ) )
		);
	}

	$result = flxlm_ats_consider_for_locked( $application_id, $source, $job, $author_email, $author_name );
	delete_option( $lock_key );

	return $result;
}

/**
 * The body of flxlm_ats_consider_for(), run while its lock is held.
 *
 * @param int     $application_id The source application.
 * @param array   $source         flxlm_ats_get_application() of it.
 * @param WP_Post $job            The open posting.
 * @param string  $author_email   Who is doing it.
 * @param string  $author_name    Display name.
 * @return int|WP_Error
 */
function flxlm_ats_consider_for_locked( $application_id, $source, $job, $author_email, $author_name ) {
	$job_id = (int) $job->ID;

	// Already on that job? Their original application there counts, and so does
	// this very record if it is the one on that job.
	$already = ( (int) $source['job_id'] === $job_id );
	if ( ! $already ) {
		foreach ( flxlm_ats_other_applications( $application_id ) as $other ) {
			if ( $other['job_id'] === $job_id ) {
				$already = true;
				break;
			}
		}
	}
	if ( $already ) {
		return new WP_Error(
			'flxlm_ats_invalid_consider',
			'This person already has an application for that job.',
			array( 'errors' => array( array( 'field' => 'job_id', 'message' => 'This person already has an application for that job.' ) ) )
		);
	}

	$job_title = wp_specialchars_decode( get_the_title( $job ), ENT_QUOTES );

	$resume = null;
	if ( $source['resume_file'] ) {
		$resume = array(
			'stored_name'   => (string) $source['resume_file'],
			'original_name' => (string) $source['resume_name'],
			'shared'        => true, // Same stored bytes: do not re-point the resume row at the new record.
		);
	}

	$new_id = flxlm_ats_create_application(
		array(
			'fields'      => array(
				'first_name'         => (string) $source['first_name'],
				'last_name'          => (string) $source['last_name'],
				'email'              => (string) $source['email'],
				'phone'              => (string) $source['phone'],
				'source'             => (string) $source['source'],
				'message'            => (string) $source['message'],
				'links'              => (string) $source['links'],
				'salary_expectation' => '',
			),
			'job_id'      => $job_id,
			'job_title'   => $job_title,
			'resume'      => $resume,
			'entered_by'  => 'consider-for',
			'source_site' => $source['source_site'] ? (string) $source['source_site'] : 'flxlocalmedia',
		)
	);

	if ( is_wp_error( $new_id ) ) {
		return $new_id;
	}

	$by = $author_name ? $author_name : ( $author_email ? $author_email : 'a team member' );

	flxlm_ats_add_note(
		(int) $new_id,
		'system',
		array(
			'body'         => sprintf(
				'Added by %s to be considered for this job. The same person applied for %s (application #%d).',
				$by,
				flxlm_ats_job_title( $application_id ),
				$application_id
			),
			'author_email' => $author_email,
			'author_name'  => $author_name,
		)
	);
	flxlm_ats_add_note(
		$application_id,
		'system',
		array(
			'body'         => sprintf(
				'%s added this person to be considered for %s (application #%d).',
				$by,
				$job_title,
				(int) $new_id
			),
			'author_email' => $author_email,
			'author_name'  => $author_name,
		)
	);

	return (int) $new_id;
}

// ---------------------------------------------------------------------------
// wp-admin: read-only panels on the application screen.
// ---------------------------------------------------------------------------

/**
 * The job this application was filed against, as a link.
 *
 * @param int $id Application ID.
 * @return string HTML (already escaped).
 */
function flxlm_ats_job_link_html( $id ) {
	$title  = flxlm_ats_job_title( $id );
	$job_id = (int) get_post_meta( $id, '_flxlm_job_id', true );
	if ( ! $job_id ) {
		return esc_html( $title ) . ' <span style="color:#8a6d3b">(not attached to a job)</span>';
	}

	$url = '';
	if ( current_user_can( 'edit_post', $job_id ) ) {
		$url = get_edit_post_link( $job_id, 'raw' );
	}
	if ( ! $url && 'publish' === get_post_status( $job_id ) ) {
		$url = get_permalink( $job_id );
	}

	$closed = ( 'publish' !== get_post_status( $job_id ) ) ? ' <span style="color:#8a6d3b">(posting closed)</span>' : '';

	return ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . $closed;
}

/**
 * "Also applied for": the same person's other applications.
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_other_applications_box( $id ) {
	$others = flxlm_ats_other_applications( $id );

	echo '<h3>Also applied for</h3>';
	if ( ! $others ) {
		echo '<p style="color:#666">No other applications from this email address.</p>';
		return;
	}

	echo '<ul style="margin:0;padding-left:1.1rem">';
	foreach ( $others as $other ) {
		printf(
			'<li><a href="%s">%s</a> &middot; %s &middot; applied %s</li>',
			esc_url( get_edit_post_link( $other['id'], 'raw' ) ),
			esc_html( $other['job_title'] ),
			esc_html( $other['stage']['label'] ),
			esc_html( $other['applied_at'] ? mysql2date( 'M j, Y', str_replace( array( 'T', 'Z' ), array( ' ', '' ), $other['applied_at'] ) ) : 'date unknown' )
		);
	}
	echo '</ul>';
}

/**
 * "Right person? Right job?": everyone's latest check, read-only. Checks are
 * recorded from the hub.
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_fit_box( $id ) {
	$state = flxlm_ats_application_fit( $id );

	echo '<h3>Right person? Right job?</h3>';
	if ( ! $state['checks'] ) {
		echo '<p style="color:#666">Nobody has answered this yet. It is recorded from the hub.</p>';
		return;
	}

	$people = flxlm_ats_fit_person_options();
	$jobs   = flxlm_ats_fit_job_options();

	echo '<ul style="margin:0;padding-left:1.1rem">';
	foreach ( $state['checks'] as $check ) {
		$job_text = $jobs[ $check['job'] ] ?? $check['job'];
		if ( 'other_job' === $check['job'] && $check['other_job'] ) {
			$job_text = 'Better fit for ' . $check['other_job']['title'];
		}
		printf(
			'<li><strong>%s</strong> &middot; right person: %s &middot; right job: %s%s <span style="color:#999;font-size:.85em">%s</span></li>',
			esc_html( $check['author_name'] ? $check['author_name'] : $check['author_email'] ),
			esc_html( $people[ $check['person'] ] ?? $check['person'] ),
			esc_html( $job_text ),
			$check['note'] ? ' &middot; ' . esc_html( $check['note'] ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static separator plus esc_html() output.
			esc_html( mysql2date( 'M j, Y g:ia', $check['created_at'] ) )
		);
	}
	echo '</ul>';
}
