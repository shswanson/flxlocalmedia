<?php
/**
 * The discussion timeline: one append-only table behind every note, feedback
 * entry, decision and system event on an application.
 *
 * WHY ONE TABLE FOR FOUR DIFFERENT-LOOKING THINGS
 *
 * A team comment, an interviewer's feedback, the decision note that closes out
 * Decision, and the automatic "moved from X to Y" line all answer the same
 * question a reviewer actually has when they open a candidate: "what has
 * happened here, in order." Four separate tables would mean four queries and a
 * merge-sort every time that screen renders, and it would mean four places a
 * future feature could add a fifth kind of event and forget to wire it into
 * the timeline. One table, one 'kind' column, one function that reads it back
 * in order, means the timeline is a query, not a reconstruction — the same
 * design principle the EEO report is built on (see inc/eeo-report.php).
 *
 * WHY THERE IS NO UPDATE OR DELETE FUNCTION
 *
 * A note is a record of what someone said and when, not a field to correct.
 * Interviewer feedback and decision notes both feed the EEO record's shadow:
 * "assume the candidate or a regulator may read them someday" is the guidance
 * this plugin puts in the interviewer's own inbox (inc/interviewers.php), and
 * a system that lets a note be quietly edited after the fact undermines that
 * promise. A correction is a NEW note; the old one stays.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** Schema version, bumped when the notes table changes. */
const FLXLM_ATS_NOTES_DB_VERSION = 1;

/** The kinds a note may be. */
function flxlm_ats_note_kinds() {
	return array( 'comment', 'feedback', 'decision', 'system' );
}

/** The ratings a 'feedback' note may carry. Null for every other kind. */
function flxlm_ats_note_ratings() {
	return array( 'strong_yes', 'yes', 'no', 'strong_no' );
}

/**
 * The notes table name.
 *
 * @return string
 */
function flxlm_ats_notes_table() {
	global $wpdb;
	return $wpdb->prefix . 'flxlm_ats_notes';
}

/**
 * Create or update the notes table. Safe to call repeatedly; dbDelta only
 * applies differences.
 */
function flxlm_ats_install_notes_table() {
	global $wpdb;

	$table   = flxlm_ats_notes_table();
	$collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		application_id bigint(20) unsigned NOT NULL DEFAULT 0,
		kind varchar(20) NOT NULL DEFAULT 'comment',
		author_email varchar(190) NOT NULL DEFAULT '',
		author_name varchar(190) NOT NULL DEFAULT '',
		stage varchar(40) NOT NULL DEFAULT '',
		rating varchar(20) DEFAULT NULL,
		body longtext NOT NULL,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY application_id (application_id),
		KEY kind (kind)
	) {$collate};";

	dbDelta( $sql );

	update_option( 'flxlm_ats_notes_db_version', FLXLM_ATS_NOTES_DB_VERSION );
}

/**
 * Make sure the table exists before anything writes to it. Same
 * belt-and-braces pattern as the resume table (inc/resume-store.php) and the
 * hub audit table (inc/hub-bridge.php): a plugin update deployed by rsync can
 * miss its activation hook, and a missing table must be an explicit,
 * recoverable condition rather than a silent write failure.
 */
function flxlm_ats_maybe_install_notes_table() {
	if ( (int) get_option( 'flxlm_ats_notes_db_version' ) === FLXLM_ATS_NOTES_DB_VERSION ) {
		return;
	}
	flxlm_ats_install_notes_table();
}
add_action( 'admin_init', 'flxlm_ats_maybe_install_notes_table' );
add_action( 'rest_api_init', 'flxlm_ats_maybe_install_notes_table' );
add_action( 'cli_init', 'flxlm_ats_maybe_install_notes_table' );

/**
 * Append a note to an application's timeline.
 *
 * @param int    $application_id Application ID.
 * @param string $kind           One of flxlm_ats_note_kinds().
 * @param array  $args           {
 *     @type string $body         Required. The note text.
 *     @type string $author_email Optional. Defaults to the current WP user's email,
 *                                 or '' for a system/unauthenticated actor.
 *     @type string $author_name  Optional. Defaults to the current WP user's display name.
 *     @type string $stage        Optional. Stage at time of writing; defaults to the
 *                                 application's current stage.
 *     @type string $rating       Optional, 'feedback' only. One of flxlm_ats_note_ratings().
 * }
 * @return int|WP_Error New row id.
 */
function flxlm_ats_add_note( $application_id, $kind, $args = array() ) {
	global $wpdb;

	$application_id = (int) $application_id;
	$post           = get_post( $application_id );
	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	if ( ! in_array( $kind, flxlm_ats_note_kinds(), true ) ) {
		return new WP_Error( 'flxlm_ats_bad_note_kind', 'Unknown note kind: ' . $kind );
	}

	$body = isset( $args['body'] ) ? trim( (string) $args['body'] ) : '';
	if ( '' === $body ) {
		return new WP_Error( 'flxlm_ats_empty_note', 'A note needs a body.' );
	}

	$rating = null;
	if ( isset( $args['rating'] ) && '' !== $args['rating'] ) {
		if ( 'feedback' !== $kind ) {
			return new WP_Error( 'flxlm_ats_rating_wrong_kind', 'Only a feedback note may carry a rating.' );
		}
		if ( ! in_array( $args['rating'], flxlm_ats_note_ratings(), true ) ) {
			return new WP_Error( 'flxlm_ats_bad_rating', 'Unknown rating: ' . $args['rating'] );
		}
		$rating = $args['rating'];
	}

	$author_email = isset( $args['author_email'] ) ? (string) $args['author_email'] : '';
	$author_name  = isset( $args['author_name'] ) ? (string) $args['author_name'] : '';

	// Default to the current logged-in user when the caller did not supply an
	// author, which covers every wp-admin and WP-CLI call site so they do not
	// each have to look this up themselves.
	if ( '' === $author_email && is_user_logged_in() ) {
		$user         = wp_get_current_user();
		$author_email = $user->user_email;
		$author_name  = $author_name ? $author_name : $user->display_name;
	}

	$stage = isset( $args['stage'] ) && '' !== $args['stage'] ? (string) $args['stage'] : $post->post_status;

	flxlm_ats_maybe_install_notes_table();

	$result = $wpdb->insert(
		flxlm_ats_notes_table(),
		array(
			'application_id' => $application_id,
			'kind'           => $kind,
			'author_email'   => sanitize_email( $author_email ),
			'author_name'    => sanitize_text_field( $author_name ),
			'stage'          => sanitize_key( $stage ),
			'rating'         => $rating,
			'body'           => wp_kses_post( $body ),
			'created_at'     => gmdate( 'Y-m-d H:i:s' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', $rating ? '%s' : null, '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error( 'flxlm_ats_note_insert_failed', 'The note could not be saved: ' . $wpdb->last_error );
	}

	$note_id = (int) $wpdb->insert_id;

	/**
	 * Fires after a note is saved.
	 *
	 * @param int    $note_id        New note row id.
	 * @param int    $application_id Application ID.
	 * @param string $kind           Note kind.
	 * @param array  $args           Original arguments.
	 */
	do_action( 'flxlm_ats_note_added', $note_id, $application_id, $kind, $args );

	return $note_id;
}

/**
 * Read an application's notes, oldest first (the order a timeline reads in).
 *
 * @param int        $application_id Application ID.
 * @param array|null $kinds          Restrict to these kinds, or null for all.
 * @return array List of {id, kind, author_email, author_name, stage, rating, body, created_at}.
 */
function flxlm_ats_get_notes( $application_id, $kinds = null ) {
	global $wpdb;

	flxlm_ats_maybe_install_notes_table();

	$table = flxlm_ats_notes_table();
	$where = 'application_id = %d';
	$args  = array( (int) $application_id );

	if ( is_array( $kinds ) && $kinds ) {
		$placeholders = implode( ',', array_fill( 0, count( $kinds ), '%s' ) );
		$where       .= " AND kind IN ({$placeholders})";
		$args         = array_merge( $args, array_values( $kinds ) );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input; $where's placeholders are all bound below.
	$sql = "SELECT id, application_id, kind, author_email, author_name, stage, rating, body, created_at FROM {$table} WHERE {$where} ORDER BY id ASC";

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

	return $rows ? $rows : array();
}

/**
 * The most recent note of a given kind on an application, or null.
 *
 * @param int    $application_id Application ID.
 * @param string $kind           Note kind.
 * @return array|null
 */
function flxlm_ats_latest_note( $application_id, $kind ) {
	$notes = flxlm_ats_get_notes( $application_id, array( $kind ) );
	return $notes ? end( $notes ) : null;
}
