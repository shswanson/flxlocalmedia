<?php
/**
 * The stage ladder.
 *
 * Stages are WordPress post statuses rather than a taxonomy or a meta field.
 * That choice buys the whole native admin experience for free: the "All (12) |
 * New (3) | Phone screen (2) | ..." links above the applications list, the
 * status filter, and the counts, all without writing a custom list screen. An
 * applicant is in exactly one stage, which is what a post status models and
 * what a taxonomy does not.
 *
 * V1 CONTRACT LADDER (2026-09-28), owner-approved. Seven working stages plus
 * two retired keys kept only so old data stays visible:
 *
 *   New -> Phone screen -> Interview -> Decision -> Offer -> Hired
 *                                                          \-> Not hired
 *
 * Each stage carries an OWNER (whose job it is to move the candidate) and an
 * EXIT TEST: the plain-English answer to "what has to be true to move this
 * candidate on." Exit tests are SOFT in v1 — see flxlm_ats_stage_exit_checks()
 * — a UI shows them and asks "Move anyway?" when one is unmet, it never blocks
 * the move. The exception is the small set of REQUIRED FIELDS below
 * (start date, close reason, a decision note), which flxlm_ats_set_stage()
 * enforces server-side because the data genuinely cannot be reconstructed
 * later. That is a narrower, harder rule than the exit test text, and the two
 * are deliberately not the same mechanism: an exit test is a nudge, a required
 * field is a fact this record cannot be correct without.
 *
 * WHY "INTERVIEW" IS ITS OWN RUNG
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
 * A generic "reviewed" flag cannot answer the question. So Interview is a
 * distinct, timestamped state, and the timestamp is ONE-WAY: once someone has
 * reached it, that is a historical fact about the world, and moving them
 * onward to Decision, Offer, Hired, or out to Not Hired must never erase it.
 * The same one-way discipline now applies to Phone screen
 * (_flxlm_phone_screened_at) and to Hired (_flxlm_hired_at). See
 * flxlm_ats_set_stage().
 *
 * RETIRED KEYS
 *
 * flxlm_screening and flxlm_manager are the pre-1.2.0 "Screening" and "Manager
 * Review" rungs. They stay REGISTERED as post statuses (so an application still
 * sitting in one is not silently invisible on a site that has not run the
 * 1.2.0 upgrade routine yet — see inc/upgrade.php) but are excluded from every
 * "what stage can this move to" list. Nothing should still be in them once the
 * migration has run.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valid values for _flxlm_close_reason, the reason an application closed as
 * Not Hired.
 *
 * @return array<string,string> key => label.
 */
function flxlm_ats_close_reasons() {
	return array(
		'not_selected'   => 'Not selected',
		'withdrew'       => 'Candidate withdrew',
		'offer_declined' => 'Offer declined',
	);
}

/**
 * Whether a value is a valid close reason.
 *
 * @param string $reason Candidate value.
 * @return bool
 */
function flxlm_ats_is_close_reason( $reason ) {
	return array_key_exists( (string) $reason, flxlm_ats_close_reasons() );
}

/**
 * The ladder, in order, plus the two retired keys at the end.
 *
 * 'terminal'               marks a stage nobody advances out of in the normal course.
 * 'retired'                marks a pre-1.2.0 key kept only so old data is visible;
 *                           never offered as a move target (see flxlm_ats_movable_stages()).
 * 'counts_as_interviewed'  marks the rung that stamps the EEO interview fact.
 * 'stamps'                 the one-way post-meta key this stage's first entry sets,
 *                           or '' when the stage stamps nothing.
 * 'owner'                  whose job it is to move the candidate out of this stage.
 *                           '' for a terminal stage — nobody moves a candidate OUT
 *                           of Hired or Not Hired in the normal course.
 * 'exit_test'               plain-English description of what has to be true to
 *                           move on. Shown verbatim by every UI so wording never
 *                           drifts between wp-admin, the hub and email.
 * 'requires'                meta fields flxlm_ats_set_stage() will refuse to move
 *                           to this stage without. See flxlm_ats_stage_required_fields().
 *
 * @return array<string,array>
 */
function flxlm_ats_stages() {
	return array(
		'flxlm_new'         => array(
			'label'       => 'New',
			'owner'       => 'hiring manager',
			'exit_test'   => "Source is known (not 'unknown') and the applicant is on a job.",
			'description' => 'Received, nobody has looked yet.',
		),
		'flxlm_phone'       => array(
			'label'       => 'Phone screen',
			'owner'       => 'hiring manager',
			'exit_test'   => 'At least one feedback note recorded while in Phone screen.',
			'description' => 'A quick call to confirm the basics before an interview.',
			'stamps'      => '_flxlm_phone_screened_at',
		),
		'flxlm_interviewed' => array(
			'label'                 => 'Interview',
			'owner'                 => 'hiring manager',
			'exit_test'             => 'At least one interviewer assigned, and every active interviewer has submitted feedback.',
			'description'           => 'Has been interviewed. Recorded for the FCC EEO report.',
			'stamps'                => '_flxlm_interviewed_at',
			'counts_as_interviewed' => true,
		),
		'flxlm_decision'    => array(
			'label'       => 'Decision',
			'owner'       => 'hiring manager',
			'exit_test'   => "Leaving Decision requires a decision note (saved as a note of kind 'decision').",
			'description' => 'The interview panel is deciding whether to extend an offer.',
		),
		'flxlm_offer'       => array(
			'label'       => 'Offer',
			'owner'       => 'business manager',
			'exit_test'   => "Moving to Hired requires a start date. Moving to Not hired requires reason 'offer declined'.",
			'description' => 'An offer has been extended.',
		),
		'flxlm_hired'       => array(
			'label'       => 'Hired',
			'owner'       => '',
			'exit_test'   => 'Terminal. Entry stamps the hire date and fills the vacancy for EEO reporting.',
			'description' => 'Accepted and hired. Fills the vacancy for EEO reporting.',
			'terminal'    => true,
			'stamps'      => '_flxlm_hired_at',
			'requires'    => array( 'start_date' ),
		),
		'flxlm_rejected'    => array(
			'label'       => 'Not hired',
			'owner'       => '',
			'exit_test'   => 'Terminal. Reachable from any stage.',
			'description' => 'Not moving forward. Reachable from any stage.',
			'terminal'    => true,
			'requires'    => array( 'close_reason' ),
		),
		// --- Retired: 1.2.0 upgrade moves anything still here to flxlm_new. ---
		'flxlm_screening'   => array(
			'label'       => '(retired) Screening',
			'owner'       => '',
			'exit_test'   => 'Retired stage. Run the 1.2.0 upgrade (wp flxlm-ats upgrade) to move this off it.',
			'description' => 'Pre-1.2.0 stage, replaced by New / Phone screen.',
			'retired'     => true,
		),
		'flxlm_manager'     => array(
			'label'       => '(retired) Manager Review',
			'owner'       => '',
			'exit_test'   => 'Retired stage. Run the 1.2.0 upgrade (wp flxlm-ats upgrade) to move this off it.',
			'description' => 'Pre-1.2.0 stage, replaced by Decision.',
			'retired'     => true,
		),
	);
}

/**
 * Stages a candidate can actually be MOVED to: the working ladder, minus the
 * retired keys. Every "pick a stage" UI (admin row actions, the stage meta
 * box, the hub bridge's /stages listing's implied move set) is built from
 * this, never from flxlm_ats_stages() directly, so a retired key can never be
 * chosen as a destination even though it stays visible on old records.
 *
 * @return array<string,array>
 */
function flxlm_ats_movable_stages() {
	return array_filter(
		flxlm_ats_stages(),
		function ( $stage ) {
			return empty( $stage['retired'] );
		}
	);
}

/**
 * The first stage every application enters.
 */
function flxlm_ats_initial_stage() {
	return 'flxlm_new';
}

/**
 * Whether a stage key is one of ours (working OR retired — this answers "is
 * this a status we registered", not "can this be moved to").
 *
 * @param string $stage Stage key.
 * @return bool
 */
function flxlm_ats_is_stage( $stage ) {
	return array_key_exists( (string) $stage, flxlm_ats_stages() );
}

/**
 * Whether a stage key can be a move TARGET (working ladder, not retired).
 *
 * @param string $stage Stage key.
 * @return bool
 */
function flxlm_ats_is_movable_stage( $stage ) {
	return array_key_exists( (string) $stage, flxlm_ats_movable_stages() );
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
 * Who owns moving a candidate out of a stage.
 *
 * @param string $stage Stage key.
 * @return string e.g. 'hiring manager', 'business manager', or '' (terminal/retired).
 */
function flxlm_ats_stage_owner( $stage ) {
	$stages = flxlm_ats_stages();
	return isset( $stages[ $stage ] ) ? (string) ( $stages[ $stage ]['owner'] ?? '' ) : '';
}

/**
 * The meta fields flxlm_ats_set_stage() requires before it will move TO a
 * given stage. This is the hard-enforced subset of each stage's exit test —
 * see the file docblock for why the two are not the same mechanism.
 *
 * @param string $stage Target stage key.
 * @return string[] e.g. array( 'start_date' ).
 */
function flxlm_ats_stage_required_fields( $stage ) {
	$stages = flxlm_ats_stages();
	if ( ! isset( $stages[ $stage ]['requires'] ) ) {
		return array();
	}
	return (array) $stages[ $stage ]['requires'];
}

/**
 * Register each stage as a post status, retired keys included: an application
 * a site has not migrated yet must stay visible in the admin list, not vanish
 * from it.
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
 * Move an application to a stage, recording everything the EEO report and the
 * v1 contract need.
 *
 * This is the ONLY supported way to change an application's stage. It does
 * five things a bare wp_update_post() would not:
 *
 *   1. Refuses a target that is not a real, MOVABLE stage (unknown key, or one
 *      of the two retired keys) rather than writing garbage into post_status.
 *   2. Enforces the required fields for the target stage (start date on Hired,
 *      a close reason on Not hired) and the "a decision note exists" rule when
 *      LEAVING Decision, returning a WP_Error carrying every missing field so
 *      a caller (the hub bridge, wp-admin) can report all of them at once
 *      rather than one round trip per field.
 *   3. Stamps the stage's one-way meta key (_flxlm_phone_screened_at,
 *      _flxlm_interviewed_at, _flxlm_hired_at) the first time an application
 *      reaches it, and NEVER clears it afterward. Each of these is a fact
 *      about the past; later stage moves cannot un-happen it.
 *   4. Appends to an append-only stage history, so an audit can reconstruct
 *      who moved whom and when. Records are never rewritten in place.
 *   5. Writes a 'system' note (see inc/notes.php) so the discussion timeline
 *      on the applicant record reads as one list rather than two.
 *
 * @param int    $application_id Application ID.
 * @param string $stage          Target stage key.
 * @param string $actor          Optional note about who or what moved it.
 * @param array  $args           {
 *     Optional. Data needed to satisfy a required field on this move.
 *
 *     @type string $note         Free text. Required content when leaving
 *                                 Decision (saved as a 'decision' note); used
 *                                 as the body of the 'system' note otherwise.
 *     @type string $close_reason One of flxlm_ats_close_reasons(). Required to
 *                                 enter flxlm_rejected.
 *     @type string $start_date   YYYY-MM-DD. Required to enter flxlm_hired.
 *     @type string $author_email Who is providing the note/close_reason, for
 *                                 the note's authorship. Defaults to the
 *                                 current actor.
 *     @type string $author_name  Display name to go with author_email.
 * }
 * @return true|WP_Error True on success. On a missing required field, a
 *                        WP_Error with code 'flxlm_ats_missing_fields' whose
 *                        error data carries 'errors' => array of
 *                        {field, message}, matching the hub bridge's
 *                        422 {error, errors:[...]} contract.
 */
function flxlm_ats_set_stage( $application_id, $stage, $actor = '', $args = array() ) {
	$application_id = (int) $application_id;
	$post           = get_post( $application_id );

	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	if ( ! flxlm_ats_is_movable_stage( $stage ) ) {
		$message = flxlm_ats_is_stage( $stage )
			? 'That stage is retired and cannot be moved to. Use New instead.'
			: ( 'Unknown stage: ' . $stage );
		return new WP_Error( 'flxlm_ats_bad_stage', $message );
	}

	$from = $post->post_status;
	if ( $from === $stage ) {
		return true; // Already there. Moving again is not an error, it is a no-op.
	}

	$args = is_array( $args ) ? $args : array();

	// (2) Required fields for the target, plus the Decision-exit note rule.
	$errors = flxlm_ats_check_required_fields( $application_id, $from, $stage, $args );
	if ( $errors ) {
		return new WP_Error(
			'flxlm_ats_missing_fields',
			'Missing required field' . ( count( $errors ) > 1 ? 's' : '' ) . ' for this move.',
			array( 'errors' => $errors )
		);
	}

	$actor = $actor ? $actor : flxlm_ats_current_actor();

	// Persist whatever the required-field check validated, before the status
	// change, so a hire or a rejection is never in a post_status that implies
	// data which was not actually saved yet.
	if ( isset( $args['close_reason'] ) && '' !== $args['close_reason'] ) {
		update_post_meta( $application_id, '_flxlm_close_reason', sanitize_key( $args['close_reason'] ) );
	}
	if ( isset( $args['start_date'] ) && '' !== $args['start_date'] ) {
		update_post_meta( $application_id, '_flxlm_start_date', sanitize_text_field( $args['start_date'] ) );
	}

	$stages = flxlm_ats_stages();

	// (3) One-way stamps. Only ever set, never cleared.
	$stamp_key = $stages[ $stage ]['stamps'] ?? '';
	if ( $stamp_key && ! get_post_meta( $application_id, $stamp_key, true ) ) {
		update_post_meta( $application_id, $stamp_key, gmdate( 'Y-m-d H:i:s' ) );
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

	// (4) Append-only history.
	$history   = get_post_meta( $application_id, '_flxlm_stage_history', true );
	$history   = is_array( $history ) ? $history : array();
	$history[] = array(
		'from' => $from,
		'to'   => $stage,
		'at'   => gmdate( 'Y-m-d H:i:s' ),
		'by'   => $actor,
	);
	update_post_meta( $application_id, '_flxlm_stage_history', $history );

	// If leaving Decision was satisfied by an inline note rather than one
	// already on the record, save it now as the decision note.
	if ( 'flxlm_decision' === $from && ! empty( $args['note'] ) && function_exists( 'flxlm_ats_add_note' )
		&& ! flxlm_ats_has_decision_note_since( $application_id, $from ) ) {
		flxlm_ats_add_note(
			$application_id,
			'decision',
			array(
				'body'         => $args['note'],
				'stage'        => $from,
				'author_email' => $args['author_email'] ?? '',
				'author_name'  => $args['author_name'] ?? '',
			)
		);
	}

	// (5) A system note, so the timeline is one list. Best-effort: notes.php
	// loads before stages.php's callers ever run this in practice, but a
	// missing function must never turn a successful stage move into an error.
	if ( function_exists( 'flxlm_ats_add_note' ) ) {
		flxlm_ats_add_note(
			$application_id,
			'system',
			array(
				'body'         => sprintf( 'Moved from %s to %s.', flxlm_ats_stage_label( $from ), flxlm_ats_stage_label( $stage ) ),
				'stage'        => $stage,
				'author_email' => $args['author_email'] ?? '',
				'author_name'  => $args['author_name'] ?? $actor,
			)
		);
	}

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
 * Validate the required fields for a stage move. Pure validation, no writes:
 * flxlm_ats_set_stage() is the only thing that persists anything.
 *
 * @param int    $application_id Application ID.
 * @param string $from           Current stage.
 * @param string $stage          Target stage.
 * @param array  $args           See flxlm_ats_set_stage().
 * @return array List of {field, message}. Empty when nothing is missing.
 */
function flxlm_ats_check_required_fields( $application_id, $from, $stage, $args ) {
	$errors = array();

	foreach ( flxlm_ats_stage_required_fields( $stage ) as $field ) {
		if ( 'start_date' === $field ) {
			$value = ! empty( $args['start_date'] ) ? $args['start_date'] : get_post_meta( $application_id, '_flxlm_start_date', true );
			if ( ! $value || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ) {
				$errors[] = array(
					'field'   => 'start_date',
					'message' => 'A start date (YYYY-MM-DD) is required to move to Hired.',
				);
			}
		}

		if ( 'close_reason' === $field ) {
			$value = ! empty( $args['close_reason'] ) ? $args['close_reason'] : get_post_meta( $application_id, '_flxlm_close_reason', true );
			if ( ! flxlm_ats_is_close_reason( $value ) ) {
				$errors[] = array(
					'field'   => 'close_reason',
					'message' => 'A close reason (not selected, withdrew, or offer declined) is required to move to Not hired.',
				);
			}
		}
	}

	// Leaving Decision requires a decision note, either already on the record
	// or supplied on this move.
	if ( 'flxlm_decision' === $from && 'flxlm_decision' !== $stage ) {
		$has_note = ! empty( $args['note'] ) || flxlm_ats_has_decision_note_since( $application_id, $from );
		if ( ! $has_note ) {
			$errors[] = array(
				'field'   => 'note',
				'message' => 'A decision note is required to leave Decision.',
			);
		}
	}

	return $errors;
}

/**
 * Whether a 'decision' note already exists for this application. Checked
 * without a time bound (any decision note on the record satisfies the exit
 * requirement, not only one written during the current visit to Decision):
 * a note kind is append-only and a decision recorded once does not need to be
 * re-justified because someone bounced the candidate back and forward again.
 *
 * @param int    $application_id Application ID.
 * @param string $stage          Unused; kept for call-site symmetry with other
 *                                 "since" checks in this file.
 * @return bool
 */
function flxlm_ats_has_decision_note_since( $application_id, $stage = '' ) {
	if ( ! function_exists( 'flxlm_ats_get_notes' ) ) {
		return false;
	}
	$notes = flxlm_ats_get_notes( $application_id, array( 'decision' ) );
	return ! empty( $notes );
}

/**
 * Soft exit checks for the CURRENT stage of an application: "what has to be
 * true to move on", evaluated against the data actually on the record.
 *
 * These are advisory. A UI shows unmet checks and asks "Move anyway?"; only
 * the required-field subset in flxlm_ats_check_required_fields() is ever hard
 * enforced. See the file docblock for why the two are separate.
 *
 * @param int $application_id Application ID.
 * @return array List of {key, label, met, detail}.
 */
function flxlm_ats_stage_exit_checks( $application_id ) {
	$application_id = (int) $application_id;
	$post           = get_post( $application_id );
	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return array();
	}

	$stage = $post->post_status;
	$out   = array();

	switch ( $stage ) {
		case 'flxlm_new':
			$source  = (string) get_post_meta( $application_id, '_flxlm_source', true );
			$job_id  = (int) get_post_meta( $application_id, '_flxlm_job_id', true );
			$out[] = array(
				'key'    => 'source_known',
				'label'  => 'Source is known',
				'met'    => ( '' !== $source && 'unknown' !== $source ),
				'detail' => 'How this applicant heard about us must be recorded before the report can trust it.',
			);
			$out[] = array(
				'key'    => 'on_a_job',
				'label'  => 'On a job',
				'met'    => ( $job_id > 0 ),
				'detail' => 'This application has no posting attached. Assign one with POST /applications/{id}/job.',
			);
			break;

		case 'flxlm_phone':
			$met = flxlm_ats_has_feedback_since( $application_id, $stage );
			$out[] = array(
				'key'    => 'phone_feedback',
				'label'  => 'Phone screen feedback recorded',
				'met'    => $met,
				'detail' => 'At least one feedback note while in Phone screen.',
			);
			break;

		case 'flxlm_interviewed':
			$active = function_exists( 'flxlm_ats_active_interviewers' ) ? flxlm_ats_active_interviewers( $application_id ) : array();
			$assigned = ! empty( $active );
			$all_in   = true;
			foreach ( $active as $interviewer ) {
				if ( empty( $interviewer['feedback_submitted'] ) ) {
					$all_in = false;
					break;
				}
			}
			$out[] = array(
				'key'    => 'interviewer_assigned',
				'label'  => 'At least one interviewer assigned',
				'met'    => $assigned,
				'detail' => 'Add an interviewer from the applicant record.',
			);
			$out[] = array(
				'key'    => 'all_feedback_in',
				'label'  => 'Every active interviewer has submitted feedback',
				'met'    => ( $assigned && $all_in ),
				'detail' => 'Waiting on feedback from one or more interviewers.',
			);
			break;

		case 'flxlm_decision':
			$out[] = array(
				'key'    => 'decision_note',
				'label'  => 'Decision note recorded',
				'met'    => flxlm_ats_has_decision_note_since( $application_id, $stage ),
				'detail' => 'A decision note is required before leaving Decision.',
			);
			break;

		case 'flxlm_offer':
			$has_start = (bool) get_post_meta( $application_id, '_flxlm_start_date', true );
			$reason    = (string) get_post_meta( $application_id, '_flxlm_close_reason', true );
			$out[] = array(
				'key'    => 'offer_resolved',
				'label'  => "Start date set (for Hired) or reason 'offer declined' set (for Not hired)",
				'met'    => ( $has_start || 'offer_declined' === $reason ),
				'detail' => 'Set a start date before moving to Hired, or record the reason as offer declined before moving to Not hired.',
			);
			break;
	}

	return $out;
}

/**
 * Whether a 'feedback' note exists on this application recorded at or after
 * the most recent time it entered the given stage. Used for Phone screen's
 * exit check, which is deliberately not tied to any one author the way
 * interviewer feedback is: a phone screen is usually the hiring manager
 * talking to the candidate directly, not a panel.
 *
 * @param int    $application_id Application ID.
 * @param string $stage          Stage key.
 * @return bool
 */
function flxlm_ats_has_feedback_since( $application_id, $stage ) {
	if ( ! function_exists( 'flxlm_ats_get_notes' ) ) {
		return false;
	}

	$entered_at = flxlm_ats_entered_stage_at( $application_id, $stage );
	$notes      = flxlm_ats_get_notes( $application_id, array( 'feedback' ) );

	foreach ( $notes as $note ) {
		if ( ! $entered_at || $note['created_at'] >= $entered_at ) {
			return true;
		}
	}

	return false;
}

/**
 * The most recent time an application entered a given stage, from the
 * append-only stage history.
 *
 * @param int    $application_id Application ID.
 * @param string $stage          Stage key.
 * @return string UTC 'Y-m-d H:i:s', or '' if it has never been recorded
 *                 entering that stage (e.g. it was created there, before any
 *                 history entry existed).
 */
function flxlm_ats_entered_stage_at( $application_id, $stage ) {
	$history = get_post_meta( (int) $application_id, '_flxlm_stage_history', true );
	$history = is_array( $history ) ? $history : array();

	$latest = '';
	foreach ( $history as $entry ) {
		if ( isset( $entry['to'], $entry['at'] ) && $entry['to'] === $stage ) {
			$latest = $entry['at'];
		}
	}

	return $latest;
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
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return 'wp-cli';
	}
	return 'system';
}

/**
 * Whether this applicant has ever been interviewed.
 *
 * Reads the one-way stamp rather than the current stage, because someone who
 * was interviewed and then rejected is still an interviewee for EEO purposes.
 * Counting only people currently sitting on the Interview rung would
 * undercount exactly the way the posted report already does.
 *
 * @param int $application_id Application ID.
 * @return bool
 */
function flxlm_ats_was_interviewed( $application_id ) {
	return (bool) get_post_meta( (int) $application_id, '_flxlm_interviewed_at', true );
}

/**
 * Whether this applicant has ever had a phone screen. One-way, same reasoning
 * as flxlm_ats_was_interviewed(): informational for the EEO report (73.2080
 * does not ask for it), never counted.
 *
 * @param int $application_id Application ID.
 * @return bool
 */
function flxlm_ats_was_phone_screened( $application_id ) {
	return (bool) get_post_meta( (int) $application_id, '_flxlm_phone_screened_at', true );
}
