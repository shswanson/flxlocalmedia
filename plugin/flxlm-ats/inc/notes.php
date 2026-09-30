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
	return array( 'comment', 'feedback', 'decision', 'system', 'ai_summary' );
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

	// 'system' notes are machine-generated log lines (contact-edit and
	// source-change diffs, stage moves, interviewer changes) with no
	// legitimate HTML in them. wp_kses_post() HTML-encodes a lone '>' (as in
	// an old -> new diff arrow), which round-trips through the DB as literal
	// '&gt;' text; wp-admin happens to hide this by rendering notes
	// unescaped, but the hub correctly HTML-escapes note bodies before
	// display, double-encoding the entity into visibly garbled text. Plain
	// sanitization avoids the corruption at the source. 'ai_summary' notes
	// (inc/ai-summary.php) are machine text that the validator there has
	// already reduced to plain text, so they take the same path. 'comment',
	// 'feedback' and 'decision' notes are staff-authored free text and keep
	// the existing HTML allowlist.
	$sanitized_body = in_array( $kind, array( 'system', 'ai_summary' ), true ) ? sanitize_textarea_field( $body ) : wp_kses_post( $body );

	$result = $wpdb->insert(
		flxlm_ats_notes_table(),
		array(
			'application_id' => $application_id,
			'kind'           => $kind,
			'author_email'   => sanitize_email( $author_email ),
			'author_name'    => sanitize_text_field( $author_name ),
			'stage'          => sanitize_key( $stage ),
			'rating'         => $rating,
			'body'           => $sanitized_body,
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

// ---------------------------------------------------------------------------
// The "thumbs" recommendation: one visual vocabulary for a feedback rating,
// used on the emailed/signed feedback page, in wp-admin's discussion
// timeline and interviewer list, and (via the same field names) by the hub.
// Stored values are unchanged (strong_no/no/yes/strong_yes — see
// flxlm_ats_note_ratings() above); this is display only.
// ---------------------------------------------------------------------------

/**
 * Plain-English label, short context phrase, thumb count/direction and
 * accent color for each rating value, red to green in the order the buttons
 * read left to right.
 *
 * @return array<string,array> rating key => {label, context, thumbs, up, color}
 */
function flxlm_ats_rating_display_map() {
	return array(
		'strong_no'  => array( 'label' => 'Strong no', 'context' => 'Would not hire', 'thumbs' => 2, 'up' => false, 'color' => '#b3261e' ),
		'no'         => array( 'label' => 'No', 'context' => 'Leaning no', 'thumbs' => 1, 'up' => false, 'color' => '#c9704a' ),
		'yes'        => array( 'label' => 'Yes', 'context' => 'Leaning yes', 'thumbs' => 1, 'up' => true, 'color' => '#4f9d5d' ),
		'strong_yes' => array( 'label' => 'Strong yes', 'context' => 'Hire', 'thumbs' => 2, 'up' => true, 'color' => '#1e7e34' ),
	);
}

/**
 * One thumb icon, inline SVG, currentColor so it inherits whatever the
 * caller's CSS sets. A "down" thumb is the same path turned upside down
 * (CSS transform) rather than a second drawn path — one shape, one place a
 * fix to it ever needs making.
 *
 * @param bool $up   True for thumbs-up, false for thumbs-down.
 * @param int  $size Pixel size, square.
 * @return string SVG markup. Not escaped — static, hardcoded markup only.
 */
function flxlm_ats_thumb_svg( $up = true, $size = 18 ) {
	$size  = (int) $size;
	$style = $up ? '' : 'transform:rotate(180deg)';
	return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" style="' . esc_attr( $style ) . '" aria-hidden="true">'
		. '<path d="M2 21h3a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H2v11zM22 12.5V11a2 2 0 0 0-2-2h-5.6l.8-3.86.03-.32c0-.4-.16-.77-.43-1.04L13.7 2.5 7.6 8.6A2 2 0 0 0 7 10v9a2 2 0 0 0 2 2h7.4a2 2 0 0 0 1.83-1.2l2.62-6.02c.1-.24.15-.5.15-.78v-.5z"/></svg>';
}

/**
 * Two thumb icons side by side ("two thumbs down") when $count is 2, one when
 * $count is 1.
 *
 * @param bool $up    Direction.
 * @param int  $count 1 or 2.
 * @param int  $size  Pixel size per icon.
 * @return string HTML.
 */
function flxlm_ats_thumbs_svg( $up, $count, $size = 18 ) {
	$out = '<span style="display:inline-flex;gap:2px;align-items:center">';
	for ( $i = 0; $i < max( 1, (int) $count ); $i++ ) {
		$out .= flxlm_ats_thumb_svg( $up, $size );
	}
	return $out . '</span>';
}

/**
 * A small colored capsule showing a rating visually: icon(s) + label. Used
 * anywhere a piece of feedback is displayed after the fact (wp-admin's
 * discussion timeline, the interviewer list) so a reader sees the same
 * thumbs vocabulary the person who left it clicked on, not a plain text
 * string ("Strong Yes") that reads as a database value.
 *
 * @param string $rating One of flxlm_ats_note_ratings(), or '' / unknown.
 * @return string HTML, or '' if the rating is not recognised.
 */
function flxlm_ats_rating_badge_html( $rating ) {
	$map = flxlm_ats_rating_display_map();
	if ( ! isset( $map[ $rating ] ) ) {
		return '';
	}
	$r = $map[ $rating ];
	return sprintf(
		'<span style="display:inline-flex;align-items:center;gap:.3rem;background:%1$s1a;color:%1$s;border:1px solid %1$s40;border-radius:999px;padding:.15rem .6rem;font-size:.82rem;font-weight:600;white-space:nowrap">%2$s %3$s</span>',
		esc_attr( $r['color'] ),
		flxlm_ats_thumbs_svg( $r['up'], $r['thumbs'], 13 ),
		esc_html( $r['label'] )
	);
}

/**
 * The four large, clickable thumbs buttons that replace a plain radio list
 * everywhere a rating is collected: the signed feedback page
 * (inc/interviewers.php) and, via the same markup and field name (`rating`,
 * values unchanged), wherever the hub's own feedback form reuses this
 * function's output.
 *
 * Radio semantics throughout: this is four native <input type="radio">
 * elements, one per option, so keyboard (Tab, arrow keys, Space) and screen
 * readers work exactly as they do for any radio group. Only the visual
 * presentation changes — each input is paired with a large clickable label
 * styled as a button, not hidden or replaced with a div. A small inline
 * script toggles a `.is-checked` class for the selected-state styling
 * (border/fill), rather than relying on the CSS :has() selector alone, so
 * the selected state renders correctly in older WebKit/Safari releases still
 * in use on some phones.
 *
 * @param string $name     Field name (always 'rating' in this plugin).
 * @param string $selected Currently selected value, if any (sticky on a
 *                            failed submission).
 * @return string HTML. Include the returned <style> and <script> once per page.
 */
function flxlm_ats_render_thumbs_field( $name, $selected = '' ) {
	$map = flxlm_ats_rating_display_map();

	ob_start();
	?>
	<div class="flxlm-thumbs" role="radiogroup" aria-label="Recommendation">
		<?php foreach ( $map as $key => $r ) : ?>
			<label class="flxlm-thumb flxlm-thumb--<?php echo esc_attr( $key ); ?><?php echo checked( $selected, $key, false ) ? ' is-checked' : ''; ?>"
				style="--flxlm-thumb-color: <?php echo esc_attr( $r['color'] ); ?>">
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $selected, $key ); ?> required />
				<span class="flxlm-thumb-icon"><?php echo flxlm_ats_thumbs_svg( $r['up'], $r['thumbs'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup only. ?></span>
				<span class="flxlm-thumb-label"><?php echo esc_html( $r['label'] ); ?></span>
				<span class="flxlm-thumb-context"><?php echo esc_html( $r['context'] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
	<style>
		.flxlm-thumbs{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem;margin:.5rem 0 1rem}
		.flxlm-thumb{position:relative;display:flex;flex-direction:column;align-items:center;gap:.3rem;
			padding:.85rem .4rem .7rem;border:1.5px solid var(--flxlm-line,#e0d8ce);border-radius:12px;
			background:#fff;cursor:pointer;text-align:center;transition:border-color .12s,background .12s,transform .06s;user-select:none}
		.flxlm-thumb:active{transform:scale(.97)}
		.flxlm-thumb input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
		.flxlm-thumb-icon{color:var(--flxlm-thumb-color);opacity:.55}
		.flxlm-thumb-label{font-weight:600;font-size:.88rem;color:#22262b}
		.flxlm-thumb-context{font-size:.72rem;color:#8a8f98;line-height:1.25}
		.flxlm-thumb:hover{border-color:var(--flxlm-thumb-color)}
		.flxlm-thumb:focus-within{outline:2px solid var(--flxlm-thumb-color);outline-offset:2px}
		.flxlm-thumb.is-checked{border-color:var(--flxlm-thumb-color);background:color-mix(in srgb, var(--flxlm-thumb-color) 10%, #fff)}
		.flxlm-thumb.is-checked .flxlm-thumb-icon{opacity:1}
		@media (max-width:420px){
			.flxlm-thumbs{grid-template-columns:repeat(2,1fr)}
			.flxlm-thumb-context{display:none}
		}
	</style>
	<script>
		(function(){
			document.querySelectorAll('.flxlm-thumbs').forEach(function(group){
				group.addEventListener('change', function(e){
					if (e.target && 'radio' === e.target.type) {
						group.querySelectorAll('.flxlm-thumb').forEach(function(l){ l.classList.remove('is-checked'); });
						e.target.closest('.flxlm-thumb').classList.add('is-checked');
					}
				});
			});
		})();
	</script>
	<?php
	return ob_get_clean();
}
