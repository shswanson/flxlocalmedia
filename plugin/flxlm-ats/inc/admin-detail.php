<?php
/**
 * The single-application screen.
 *
 * Everything about an applicant on one screen, with the stage buttons at the
 * top where a decision can be made without scrolling. The WordPress editor is
 * suppressed for this post type: an application is a record, not a document,
 * and nothing about it should look editable by hand.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the detail meta box and hide the pieces that do not apply.
 */
function flxlm_ats_add_detail_box() {
	add_meta_box(
		'flxlm_ats_detail',
		'Application',
		'flxlm_ats_render_detail_box',
		'flxlm_application',
		'normal',
		'high'
	);

	// The publish box offers Draft/Pending/Publish, which are meaningless here
	// and would let someone knock a record out of the stage ladder entirely.
	remove_meta_box( 'submitdiv', 'flxlm_application', 'side' );

	add_meta_box(
		'flxlm_ats_stagebox',
		'Stage',
		'flxlm_ats_render_stage_box',
		'flxlm_application',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_flxlm_application', 'flxlm_ats_add_detail_box' );

/**
 * The stage control.
 *
 * Most moves are a single button: no dropdown, no Save step, one click moves
 * the candidate and reloads. A move whose target needs a field the required-
 * fields check on flxlm_ats_set_stage() will actually enforce (a start date
 * for Hired, a close reason for Not hired), or that requires a decision note
 * because the candidate is currently sitting in Decision, gets a small inline
 * form instead of a bare link — there is nowhere else on this button to put
 * the field, and a link that always failed server-side would be worse than
 * one that asks first. See flxlm_ats_stage_move_extra_fields().
 *
 * @param WP_Post $post Application.
 */
function flxlm_ats_render_stage_box( $post ) {
	$current = $post->post_status;
	$can     = current_user_can( 'flxlm_manage_applications' );

	echo '<p style="margin-top:0">Currently <strong>' . esc_html( flxlm_ats_stage_label( $current ) ) . '</strong>.</p>';

	if ( flxlm_ats_is_stage( $current ) && ! flxlm_ats_is_movable_stage( $current ) ) {
		echo '<p style="color:#8a6d3b;font-size:.9em">This stage is no longer used. Use the button below to move this applicant to New.</p>';
	}

	if ( flxlm_ats_was_phone_screened( $post->ID ) ) {
		echo '<p style="color:#1e7e34;font-size:.85em;margin-bottom:.3rem">Phone screened '
			. esc_html( mysql2date( 'M j, Y', get_post_meta( $post->ID, '_flxlm_phone_screened_at', true ) ) ) . '.</p>';
	}
	if ( flxlm_ats_was_interviewed( $post->ID ) ) {
		$when = get_post_meta( $post->ID, '_flxlm_interviewed_at', true );
		echo '<p style="color:#1e7e34;font-size:.9em">Interviewed '
			. esc_html( mysql2date( 'M j, Y', $when ) )
			. '. This is recorded for the FCC EEO report and cannot be undone.</p>';
	}

	$exit_checks = flxlm_ats_stage_exit_checks( $post->ID );
	if ( $exit_checks ) {
		echo '<p style="margin:.75rem 0 .3rem;font-weight:600">What has to be true to move on</p><ul style="margin:0 0 .75rem;padding-left:1.1rem;font-size:.85em">';
		foreach ( $exit_checks as $check ) {
			printf(
				'<li style="color:%s">%s %s</li>',
				$check['met'] ? '#1e7e34' : '#8a6d3b',
				$check['met'] ? '&#10003;' : '&#9675;',
				esc_html( $check['label'] )
			);
		}
		echo '</ul>';
	}

	if ( ! $can ) {
		echo '<p style="color:#666">You can read this application but not move it.</p>';
		return;
	}

	echo '<div style="display:flex;flex-direction:column;gap:.5rem">';

	if ( flxlm_ats_is_stage( $current ) && ! flxlm_ats_is_movable_stage( $current ) ) {
		printf(
			'<a href="%s" class="button button-primary">Move to New</a>',
			esc_url( flxlm_ats_admin_stage_url( $post->ID, flxlm_ats_initial_stage() ) )
		);
	}

	foreach ( flxlm_ats_movable_stages() as $key => $stage ) {
		if ( $key === $current ) {
			continue;
		}

		$is_reject = ( 'flxlm_rejected' === $key );
		$extra     = flxlm_ats_stage_move_extra_fields( $post->ID, $current, $key );

		if ( ! $extra ) {
			printf(
				'<a href="%s" class="button %s"%s>%s</a>',
				esc_url( flxlm_ats_admin_stage_url( $post->ID, $key ) ),
				$is_reject ? '' : 'button-primary',
				$is_reject ? ' style="color:#b32d2e"' : '',
				esc_html( 'Move to ' . $stage['label'] )
			);
			continue;
		}

		flxlm_ats_render_stage_move_form( $post->ID, $current, $key, $stage['label'], $extra, $is_reject );
	}
	echo '</div>';
}

/**
 * The extra fields a stage move needs beyond a bare click.
 *
 * @param int    $application_id Application ID.
 * @param string $current        Current stage.
 * @param string $target         Target stage.
 * @return string[] Subset of 'close_reason', 'start_date', 'note'.
 */
function flxlm_ats_stage_move_extra_fields( $application_id, $current, $target ) {
	$fields = flxlm_ats_stage_required_fields( $target );

	// Leaving Decision needs a note regardless of where it is going, unless a
	// decision note already exists on the record (flxlm_ats_set_stage()
	// checks the record either way; this only decides whether to ask again).
	if ( 'flxlm_decision' === $current && $target !== $current && ! flxlm_ats_has_decision_note_since( $application_id ) ) {
		$fields[] = 'note';
	}

	return array_values( array_unique( $fields ) );
}

/**
 * Render the inline "Move to X" form for a target that needs extra data.
 *
 * Uses a <details> disclosure rather than showing every field-collecting form
 * open at once: most visits to this box are a single, obvious next move, and
 * a screen with three open forms for moves nobody is making this visit is
 * exactly the "seven links is a menu" problem the original one-click design
 * (see this file's history) was built to avoid.
 *
 * @param int      $application_id Application ID.
 * @param string   $current        Current stage.
 * @param string   $target         Target stage.
 * @param string   $label          Target stage label.
 * @param string[] $fields         From flxlm_ats_stage_move_extra_fields().
 * @param bool     $is_reject      Style as the rejection action.
 */
function flxlm_ats_render_stage_move_form( $application_id, $current, $target, $label, $fields, $is_reject ) {
	$url = flxlm_ats_admin_stage_url( $application_id, $target );
	// The nonce and action already live in $url's query string. A <form
	// method="post"> to admin-post.php reads its target and its fields the
	// same way the plain GET link does (flxlm_ats_handle_admin_stage() reads
	// $_REQUEST), so posting the extra fields alongside them is all this needs.
	?>
	<details style="border:1px solid #dcdcde;border-radius:6px;padding:.5rem .75rem">
		<summary style="cursor:pointer;<?php echo $is_reject ? 'color:#b32d2e' : ''; ?>">Move to <?php echo esc_html( $label ); ?>&hellip;</summary>
		<form method="post" action="<?php echo esc_url( $url ); ?>" style="margin-top:.6rem">
			<?php if ( in_array( 'close_reason', $fields, true ) ) : ?>
				<label style="display:block;font-size:.85em;margin-bottom:.2rem">Reason</label>
				<select name="close_reason" required style="width:100%;margin-bottom:.5rem">
					<option value="">Choose one&hellip;</option>
					<?php foreach ( flxlm_ats_close_reasons() as $key => $reason_label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $reason_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<?php if ( in_array( 'start_date', $fields, true ) ) : ?>
				<label style="display:block;font-size:.85em;margin-bottom:.2rem">Start date</label>
				<input type="date" name="start_date" required style="width:100%;margin-bottom:.5rem" />
			<?php endif; ?>

			<?php if ( in_array( 'note', $fields, true ) ) : ?>
				<label style="display:block;font-size:.85em;margin-bottom:.2rem">Decision note</label>
				<textarea name="note" rows="3" required style="width:100%;margin-bottom:.5rem;font:inherit"></textarea>
			<?php endif; ?>

			<button type="submit" class="button <?php echo $is_reject ? '' : 'button-primary'; ?>">Confirm: move to <?php echo esc_html( $label ); ?></button>
		</form>
	</details>
	<?php
}

/**
 * The application itself.
 *
 * @param WP_Post $post Application.
 */
function flxlm_ats_render_detail_box( $post ) {
	$id = $post->ID;

	flxlm_ats_render_contact_card( $id );

	$rows = array(
		'Applied for'    => flxlm_ats_job_title( $id ),
		'Pay they want'  => get_post_meta( $id, '_flxlm_salary_expectation', true ),
		'Applied'        => mysql2date( 'F j, Y g:ia', get_post_meta( $id, '_flxlm_submitted_at', true ) ),
		'Came in via'    => flxlm_ats_entered_by_label( get_post_meta( $id, '_flxlm_entered_by', true ) ),
	);

	echo '<table class="form-table"><tbody>';
	foreach ( $rows as $label => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}
		echo '<tr><th style="width:11rem">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
	echo '</tbody></table>';

	flxlm_ats_render_source_control( $id );

	// Above the resume so it is seen before the tall PDF viewer pushes the rest of the screen down.
	flxlm_ats_render_ai_summary_card( $id );

	flxlm_ats_render_resume_box( $id );

	$links = (string) get_post_meta( $id, '_flxlm_links', true );
	if ( $links ) {
		echo '<h3>Their work</h3><p style="white-space:pre-line">'
			. wp_kses_post( make_clickable( esc_html( $links ) ) ) . '</p>';
	}

	$message = (string) get_post_meta( $id, '_flxlm_message', true );
	if ( $message ) {
		echo '<h3>What they said</h3><p style="white-space:pre-line">' . esc_html( $message ) . '</p>';
	}

	$history = get_post_meta( $id, '_flxlm_stage_history', true );
	if ( is_array( $history ) && $history ) {
		echo '<h3>Stage history</h3><ul style="margin:0;color:#555">';
		foreach ( array_reverse( $history ) as $entry ) {
			printf(
				'<li>%s &rarr; <strong>%s</strong> &middot; %s &middot; %s</li>',
				esc_html( flxlm_ats_stage_label( $entry['from'] ) ),
				esc_html( flxlm_ats_stage_label( $entry['to'] ) ),
				esc_html( mysql2date( 'M j, Y g:ia', $entry['at'] ) ),
				esc_html( $entry['by'] )
			);
		}
		echo '</ul>';
	}

	flxlm_ats_render_interviewers_box( $id );
	flxlm_ats_render_notes_box( $id );

	printf(
		'<p style="margin-top:1.5rem"><a href="%s">Open in the hub</a> &middot; full history, comments and feedback in one timeline.</p>',
		esc_url( 'https://hub.flxlocalmedia.com/hiring/?application=' . (int) $id )
	);
}

/**
 * The contact card: name, email, phone, with an Edit control. Email and
 * phone render as clickable mailto:/tel: links (flxlm_ats_mailto_html() /
 * flxlm_ats_tel_html(), inc/contact.php) even in read mode, so "start an
 * email" and "start a call" never require switching into edit mode first.
 *
 * The edit form is a native <details>/<summary> disclosure — the same
 * pattern flxlm_ats_render_stage_move_form() already uses for a stage move
 * that needs extra fields — so Cancel is just closing the disclosure; no JS
 * is needed to open, close or cancel it. Save posts to admin-post.php and
 * reloads the screen, the same round trip every other write in this plugin's
 * wp-admin already uses.
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_contact_card( $id ) {
	$can    = current_user_can( 'flxlm_manage_applications' );
	$first  = (string) get_post_meta( $id, '_flxlm_first_name', true );
	$last   = (string) get_post_meta( $id, '_flxlm_last_name', true );
	$email  = (string) get_post_meta( $id, '_flxlm_email', true );
	$phone  = (string) get_post_meta( $id, '_flxlm_phone', true );

	echo '<div id="flxlm_ats_contact" style="border:1px solid #dcdcde;border-radius:6px;padding:.85rem 1rem;margin-bottom:1rem;background:#fbfbfc">';
	echo '<table class="form-table" style="margin:0"><tbody>';
	echo '<tr><th style="width:11rem">Name</th><td>' . esc_html( trim( $first . ' ' . $last ) ) . '</td></tr>';
	if ( $email ) {
		echo '<tr><th>Email</th><td>' . wp_kses_post( flxlm_ats_mailto_html( $email ) ) . '</td></tr>';
	}
	if ( $phone ) {
		echo '<tr><th>Phone</th><td>' . wp_kses_post( flxlm_ats_tel_html( $phone ) ) . '</td></tr>';
	}
	echo '</tbody></table>';

	if ( $can ) {
		?>
		<details style="margin-top:.6rem">
			<summary style="cursor:pointer;color:#2271b1">Edit contact information&hellip;</summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:.75rem;max-width:26rem">
				<input type="hidden" name="action" value="flxlm_ats_contact" />
				<input type="hidden" name="application" value="<?php echo esc_attr( $id ); ?>" />
				<?php wp_nonce_field( 'flxlm_ats_contact_' . $id ); ?>

				<label style="display:block;font-size:.85em;margin-bottom:.2rem">First name</label>
				<input type="text" name="first_name" value="<?php echo esc_attr( $first ); ?>" required style="width:100%;margin-bottom:.6rem" />

				<label style="display:block;font-size:.85em;margin-bottom:.2rem">Last name</label>
				<input type="text" name="last_name" value="<?php echo esc_attr( $last ); ?>" required style="width:100%;margin-bottom:.6rem" />

				<label style="display:block;font-size:.85em;margin-bottom:.2rem">Email</label>
				<input type="email" name="email" value="<?php echo esc_attr( $email ); ?>" required style="width:100%;margin-bottom:.6rem" />

				<label style="display:block;font-size:.85em;margin-bottom:.2rem">Phone</label>
				<input type="tel" name="phone" value="<?php echo esc_attr( $phone ); ?>" style="width:100%;margin-bottom:.75rem" />

				<button type="submit" class="button button-primary">Save</button>
				<a href="#" class="button flxlm-ats-cancel-details" onclick="this.closest('details').removeAttribute('open');return false;">Cancel</a>
			</form>
		</details>
		<?php
	}
	echo '</div>';
}

/**
 * The "Heard about us" row, with a change control next to it. wp-admin's
 * only way to set a recruitment source before this shipped was the intake
 * form itself, which left every manually entered or email-intake applicant
 * (and anyone whose real source turned out different from what they
 * originally typed) with no way to fix it — the exact gap New's own "source
 * is known" exit test exists to catch (inc/stages.php).
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_source_control( $id ) {
	$current = (string) get_post_meta( $id, '_flxlm_source', true );
	$can     = current_user_can( 'flxlm_manage_applications' );

	echo '<p style="margin:0 0 .3rem"><strong>Heard about us</strong><br />' . esc_html( flxlm_ats_source_label( $current ) ) . '</p>';

	if ( ! $can ) {
		return;
	}

	// The full vocabulary (including the staff-only "Not known yet") is
	// offered here, on purpose: wp-admin is a staff screen, the same context
	// as the manual-entry form (inc/admin-manual-entry.php), which already
	// offers it. A picker that should never offer 'unknown' (fixing an exit
	// test that unknown itself failed) is flxlm_ats_hub_selectable_sources(),
	// used by the hub bridge's GET /sources instead.
	?>
	<details id="flxlm_ats_source" style="margin:0 0 1rem">
		<summary style="cursor:pointer;color:#2271b1">Change source&hellip;</summary>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:.6rem;max-width:22rem">
			<input type="hidden" name="action" value="flxlm_ats_source" />
			<input type="hidden" name="application" value="<?php echo esc_attr( $id ); ?>" />
			<?php wp_nonce_field( 'flxlm_ats_source_' . $id ); ?>
			<select name="source" required style="width:100%;margin-bottom:.5rem">
				<?php foreach ( flxlm_ats_manual_only_sources() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
				<?php foreach ( flxlm_ats_sources() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary">Save</button>
			<a href="#" class="button" onclick="this.closest('details').removeAttribute('open');return false;">Cancel</a>
		</form>
	</details>
	<?php
}

/**
 * Interviewers, read-only: who is on the panel, whether they have submitted
 * feedback, and when they were removed if they were. Adding or removing an
 * interviewer, and writing a team comment, happen in the hub
 * (flxlm-ats-hub/v1 — inc/hub-bridge.php), not here: wp-admin is where a
 * reviewer catches up on a candidate, the hub is where the team works one.
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_interviewers_box( $id ) {
	$all = flxlm_ats_all_interviewers( $id );
	if ( ! $all ) {
		return;
	}

	echo '<h3>Interviewers</h3><ul style="margin:0;padding-left:1.1rem">';
	foreach ( $all as $entry ) {
		$removed  = ! empty( $entry['removed_at'] );
		$external = ! empty( $entry['external'] );
		$badge    = $external ? ' <span style="display:inline-block;background:#fdf6ea;border:1px solid #ecd9ad;color:#6b5620;border-radius:999px;padding:0 .5em;font-size:.78em;font-weight:600">Outside FLX</span>' : '';
		$who      = ( $entry['name'] ? esc_html( $entry['name'] ) . ' ' : '' ) . wp_kses_post( flxlm_ats_mailto_html( $entry['email'] ) ) . $badge;

		if ( $removed ) {
			printf(
				'<li style="color:#999">%s, removed %s: %s</li>',
				$who, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html()/mailto helper/static badge markup above.
				esc_html( mysql2date( 'M j, Y', $entry['removed_at'] ) ),
				esc_html( $entry['removal_note'] )
			);
			continue;
		}

		$feedback_at = '';
		$rating      = '';
		foreach ( flxlm_ats_active_interviewers( $id ) as $active ) {
			if ( strtolower( $active['email'] ) === strtolower( $entry['email'] ) ) {
				$feedback_at = $active['feedback_at'];
				$rating      = $active['rating'];
				break;
			}
		}

		printf(
			'<li style="margin-bottom:.3rem">%s &middot; %s</li>',
			$who, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see above.
			$feedback_at
				? flxlm_ats_rating_badge_html( $rating ) . ' <span style="color:#999;font-size:.85em">' . esc_html( mysql2date( 'M j, Y', $feedback_at ) ) . '</span>'
				: '<span style="color:#8a6d3b">feedback not in yet</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup / helper-built badge, not user input.
		);
	}
	echo '</ul>';
}

/**
 * The discussion timeline: every note in one list, oldest first, same
 * ordering the hub bridge's GET /applications/{id} returns them in.
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_notes_box( $id ) {
	$notes = flxlm_ats_get_notes( $id );
	if ( ! $notes ) {
		return;
	}

	$kind_labels = array(
		'comment'  => 'Comment',
		'feedback' => 'Feedback',
		'decision' => 'Decision',
		'system'   => 'System',
		'ai_summary' => 'AI summary',
	);
	// An AI summary note reads differently from a person's words: same tinted,
	// dashed treatment as the card at the top of the screen.
	$ai_style = 'margin-bottom:.6rem;padding:.5rem .6rem;border:1px dashed #8b7cc8;border-radius:6px;background:#f6f4fc';
	$li_style = 'margin-bottom:.6rem;padding-bottom:.6rem;border-bottom:1px solid #f0f0f1';

	echo '<h3>Discussion</h3><ul style="margin:0;padding-left:1.1rem;list-style:none">';
	foreach ( array_reverse( $notes ) as $note ) {
		$who = $note['author_name'] ? $note['author_name'] : ( $note['author_email'] ? $note['author_email'] : 'System' );
		printf(
			'<li style="%s"><strong>%s</strong> <span style="color:#999;font-size:.85em">%s %s</span>%s<br />%s</li>',
			esc_attr( 'ai_summary' === $note['kind'] ? $ai_style : $li_style ),
			esc_html( $kind_labels[ $note['kind'] ] ?? $note['kind'] ),
			esc_html( $who ),
			esc_html( mysql2date( 'M j, Y g:ia', $note['created_at'] ) ),
			$note['rating'] ? ' &nbsp;' . flxlm_ats_rating_badge_html( $note['rating'] ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper-built badge from a validated rating value, not user input.
			// $note['body'] is already wp_kses_post()'d at write time
			// (inc/notes.php), so it is trusted, sanitized HTML by the time it
			// gets here. Running it through esc_html() first, as this used to,
			// HTML-entity-encoded that already-safe markup into literal
			// "&lt;...&gt;" text that wp_kses_post() could then do nothing
			// useful with. nl2br() first (turning raw newlines into <br>),
			// then wp_kses_post() again as a defensive, idempotent re-check.
			wp_kses_post( nl2br( $note['body'] ) )
		);
	}
	echo '</ul>';
}

/**
 * The resume block on the application screen.
 *
 * A PDF renders inline in an iframe on the same screen the reviewer is
 * already on, next to a Download button that always forces a save-as,
 * regardless of file type. Anything that is not a PDF (doc/docx/odt/rtf/txt)
 * is offered as a download only: flxlm_ats_serve_resume() will not render a
 * non-PDF inline, because trusting the browser to render an arbitrary
 * document inline is how stored XSS happens. There is no server-side
 * doc-to-PDF conversion yet, so a Word resume stays a download until that
 * lands.
 *
 * @param int $id Application ID.
 */
function flxlm_ats_render_resume_box( $id ) {
	$resume_id = (int) get_post_meta( $id, '_flxlm_resume_file', true );

	if ( $resume_id < 1 ) {
		echo '<p style="color:#666">No resume on file.</p>';
		return;
	}

	$meta         = flxlm_ats_get_resume_meta( $resume_id );
	$is_pdf       = $meta && 'application/pdf' === $meta['mime_type'];
	$resume_name  = (string) get_post_meta( $id, '_flxlm_resume_name', true );
	$view_url     = flxlm_ats_admin_resume_url( $id, 'view' );
	$download_url = flxlm_ats_admin_resume_url( $id, 'download' );

	echo '<div class="flxlm-ats-resume" style="margin:1rem 0">';

	if ( $is_pdf ) {
		printf(
			'<iframe src="%s" title="Resume" style="width:100%%;max-width:56rem;height:70vh;border:1px solid #dcdcde;border-radius:4px;background:#fff"></iframe>',
			esc_url( $view_url )
		);
		echo '<p style="margin-top:.6rem">';
		printf(
			'<a href="%s" class="button" target="_blank" rel="noopener">Open in new tab</a> ',
			esc_url( $view_url )
		);
		printf(
			'<a href="%s" class="button button-primary">Download (%s)</a>',
			esc_url( $download_url ),
			esc_html( $resume_name )
		);
		echo '</p>';
		echo '<p style="color:#999;font-size:.85em;margin-top:.3rem">Not loading? Some phones can\'t preview a PDF inside the page. Use "Open in new tab" instead.</p>';
	} else {
		printf(
			'<p><a href="%s" class="button button-primary">Download resume (%s)</a></p>',
			esc_url( $download_url ),
			esc_html( $resume_name )
		);
	}

	echo '</div>';
}

/**
 * Human label for how an application arrived.
 *
 * @param string $key Stored value.
 * @return string
 */
function flxlm_ats_entered_by_label( $key ) {
	$map = array(
		'web'          => 'The form on flxlocalmedia.com',
		'fldn-relay'   => 'The form on Finger Lakes Daily News',
		'manual'       => 'Entered by hand',
		'email-intake' => 'Email to jobs@flxlocalmedia.com',
	);
	return $map[ $key ] ?? (string) $key;
}

/**
 * Register the hidden admin route that streams a resume.
 *
 * Registered with a null parent so it has a URL but no menu item.
 *
 * The page's own render callback fires too late to stream a file: admin.php
 * requires wp-admin/admin-header.php (which prints the doctype, admin menu and
 * a Content-Type: text/html header) BEFORE it runs the page callback, so by
 * the time flxlm_ats_admin_resume_screen() sets its own headers and echoes
 * bytes, the response is already committed to text/html with WordPress's own
 * admin chrome as the body. The route never worked correctly for this reason:
 * both a PDF view and a download came back as a corrupted HTML page with no
 * Content-Disposition, caught while proving out the inline viewer in a real
 * browser rather than assuming the existing "Open resume" link was sound.
 *
 * `load-{$hook_suffix}` fires from admin.php before admin-header.php is
 * required, so hooking the same screen function there runs it while headers
 * are still open. It always exits (success streams and exits; failure calls
 * wp_die(), which also exits), so admin.php never reaches the header include.
 * The page-callback registration is left in place only as a defensive
 * fallback for a request that somehow skips the load- hook.
 */
function flxlm_ats_register_hidden_screens() {
	$hook = add_submenu_page(
		'',
		'Resume',
		'Resume',
		'flxlm_view_applications',
		'flxlm-ats-resume',
		'flxlm_ats_admin_resume_screen'
	);

	if ( $hook ) {
		add_action( 'load-' . $hook, 'flxlm_ats_admin_resume_screen' );
	}
}
add_action( 'admin_menu', 'flxlm_ats_register_hidden_screens' );
