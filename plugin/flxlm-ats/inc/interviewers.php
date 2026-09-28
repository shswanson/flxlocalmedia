<?php
/**
 * Interviewers: who is on the panel, and their login-free feedback page.
 *
 * Interviewers are station and department staff, not necessarily anyone with
 * a WordPress account — the exact problem inc/tokens.php already solved once
 * for hiring-manager decision links. So an interviewer is added by email
 * address, stored on the application (post meta _flxlm_interviewers), and
 * given a signed link that opens straight to a feedback form. No login, no
 * account provisioning, no waiting on IT to matter before a panel can leave
 * notes on a candidate they just spoke to.
 *
 * WHY A DOMAIN ALLOWLIST
 *
 * An interviewer link, once issued, is a bearer credential good for 30 days
 * that can read the candidate's name, the job, and post feedback to the
 * record. Restricting who can be ADDED as an interviewer to company email
 * domains (filterable via 'flxlm_ats_interviewer_domains') means a typo or a
 * malicious entry cannot hand that credential to an outside address. It does
 * not restrict who can hold flxlm_view_applications in wp-admin; it only
 * bounds who this specific lightweight, no-login mechanism will mail a link
 * to.
 *
 * WHY REMOVAL KEEPS THE ROW INSTEAD OF DELETING IT
 *
 * _flxlm_interviewers is never rewritten to drop an entry. A removed
 * interviewer's row gets removed_at / removed_by / removal_note set instead,
 * because "who was on this panel and when" is itself part of the record a
 * regulator or a candidate might read someday (see inc/notes.php), and
 * because flxlm_ats_active_interviewers() needs the assigned_at timestamp to
 * exist even for someone since removed, to correctly attribute feedback they
 * left while they were still active.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** How long a feedback link stays valid. Long enough to survive a busy week. */
const FLXLM_ATS_FEEDBACK_TTL = 30 * DAY_IN_SECONDS;

/**
 * Email domains an interviewer may be added from.
 *
 * @return string[]
 */
function flxlm_ats_interviewer_domains() {
	/**
	 * Filter the domains an interviewer's email address must end in.
	 *
	 * @param string[] $domains
	 */
	return apply_filters( 'flxlm_ats_interviewer_domains', array( 'flxlocalmedia.com', 'totib.com' ) );
}

/**
 * Whether an email address is on an allowed interviewer domain.
 *
 * @param string $email Email address.
 * @return bool
 */
function flxlm_ats_is_allowed_interviewer_email( $email ) {
	if ( ! is_email( $email ) ) {
		return false;
	}
	$domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
	foreach ( flxlm_ats_interviewer_domains() as $allowed ) {
		if ( $domain === strtolower( $allowed ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The raw interviewer list on an application, active and removed alike.
 *
 * @param int $application_id Application ID.
 * @return array[]
 */
function flxlm_ats_all_interviewers( $application_id ) {
	$list = get_post_meta( (int) $application_id, '_flxlm_interviewers', true );
	return is_array( $list ) ? $list : array();
}

/**
 * Add an interviewer. Idempotent while the same address is already active: a
 * second "assign" click is a no-op, not a duplicate entry or an error, so a
 * manager double-clicking a slow button never produces two rows.
 *
 * @param int    $application_id Application ID.
 * @param string $email          Interviewer's email.
 * @param string $name           Display name.
 * @param string $by             Who is adding them (see flxlm_ats_current_actor()).
 * @return true|WP_Error
 */
function flxlm_ats_add_interviewer( $application_id, $email, $name, $by = '' ) {
	$application_id = (int) $application_id;
	$post           = get_post( $application_id );
	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	$email = sanitize_email( strtolower( trim( (string) $email ) ) );
	if ( ! flxlm_ats_is_allowed_interviewer_email( $email ) ) {
		return new WP_Error(
			'flxlm_ats_bad_interviewer_domain',
			'Interviewers must use a ' . implode( ' or ', flxlm_ats_interviewer_domains() ) . ' email address.'
		);
	}

	$name = sanitize_text_field( (string) $name );
	$by   = $by ? $by : flxlm_ats_current_actor();

	$list = flxlm_ats_all_interviewers( $application_id );

	foreach ( $list as $entry ) {
		if ( strtolower( $entry['email'] ?? '' ) === $email && empty( $entry['removed_at'] ) ) {
			return true; // Already active. Not an error.
		}
	}

	$list[] = array(
		'email'        => $email,
		'name'         => $name,
		'assigned_at'  => gmdate( 'Y-m-d H:i:s' ),
		'assigned_by'  => $by,
		'removed_at'   => '',
		'removed_by'   => '',
		'removal_note' => '',
	);
	update_post_meta( $application_id, '_flxlm_interviewers', $list );

	if ( function_exists( 'flxlm_ats_add_note' ) ) {
		flxlm_ats_add_note(
			$application_id,
			'system',
			array( 'body' => sprintf( 'Added %s as an interviewer.', $name ? "{$name} ({$email})" : $email ) )
		);
	}

	flxlm_ats_notify_interviewer_assigned( $application_id, $email, $name );

	/**
	 * Fires after an interviewer is added.
	 *
	 * @param int    $application_id Application ID.
	 * @param string $email          Interviewer email.
	 */
	do_action( 'flxlm_ats_interviewer_added', $application_id, $email );

	return true;
}

/**
 * Remove an interviewer. A note is required: someone reading the timeline
 * later needs to know why a panelist dropped off, not just that they did.
 *
 * @param int    $application_id Application ID.
 * @param string $email          Interviewer's email.
 * @param string $by             Who is removing them.
 * @param string $note           Required removal note.
 * @return true|WP_Error
 */
function flxlm_ats_remove_interviewer( $application_id, $email, $by, $note ) {
	$application_id = (int) $application_id;
	$email           = strtolower( trim( (string) $email ) );
	$note            = trim( (string) $note );

	if ( '' === $note ) {
		return new WP_Error( 'flxlm_ats_removal_note_required', 'A note is required to remove an interviewer.' );
	}

	$list  = flxlm_ats_all_interviewers( $application_id );
	$found = false;

	foreach ( $list as &$entry ) {
		if ( strtolower( $entry['email'] ?? '' ) === $email && empty( $entry['removed_at'] ) ) {
			$entry['removed_at']   = gmdate( 'Y-m-d H:i:s' );
			$entry['removed_by']   = $by ? $by : flxlm_ats_current_actor();
			$entry['removal_note'] = sanitize_textarea_field( $note );
			$found                 = true;
			break;
		}
	}
	unset( $entry );

	if ( ! $found ) {
		return new WP_Error( 'flxlm_ats_interviewer_not_active', 'That person is not an active interviewer on this application.' );
	}

	update_post_meta( $application_id, '_flxlm_interviewers', $list );

	if ( function_exists( 'flxlm_ats_add_note' ) ) {
		flxlm_ats_add_note(
			$application_id,
			'system',
			array( 'body' => sprintf( 'Removed %s as an interviewer: %s', $email, $note ) )
		);
	}

	do_action( 'flxlm_ats_interviewer_removed', $application_id, $email );

	return true;
}

/**
 * Active interviewers, with feedback status computed from the notes table.
 *
 * "Submitted" means a 'feedback' note exists authored by this exact email,
 * written at or after the moment they were assigned — so feedback from a
 * PRIOR stint as interviewer (they were removed and later re-added) does not
 * silently count as satisfying the current assignment.
 *
 * @param int $application_id Application ID.
 * @return array[] {email, name, assigned_at, feedback_submitted, feedback_at}
 */
function flxlm_ats_active_interviewers( $application_id ) {
	$application_id = (int) $application_id;
	$active         = array();

	foreach ( flxlm_ats_all_interviewers( $application_id ) as $entry ) {
		if ( ! empty( $entry['removed_at'] ) ) {
			continue;
		}

		$feedback_at = '';
		if ( function_exists( 'flxlm_ats_get_notes' ) ) {
			foreach ( flxlm_ats_get_notes( $application_id, array( 'feedback' ) ) as $note ) {
				if ( strtolower( $note['author_email'] ) === strtolower( $entry['email'] )
					&& $note['created_at'] >= $entry['assigned_at'] ) {
					$feedback_at = $note['created_at']; // Notes are read oldest-first; keep the latest.
				}
			}
		}

		$active[] = array(
			'email'               => $entry['email'],
			'name'                => $entry['name'],
			'assigned_at'         => $entry['assigned_at'],
			'feedback_submitted'  => ( '' !== $feedback_at ),
			'feedback_at'         => $feedback_at,
		);
	}

	return $active;
}

/**
 * Whether an email address is (still) an active interviewer on an application.
 *
 * @param int    $application_id Application ID.
 * @param string $email          Email address.
 * @return bool
 */
function flxlm_ats_is_active_interviewer( $application_id, $email ) {
	$email = strtolower( trim( (string) $email ) );
	foreach ( flxlm_ats_active_interviewers( $application_id ) as $entry ) {
		if ( strtolower( $entry['email'] ) === $email ) {
			return true;
		}
	}
	return false;
}

// ---------------------------------------------------------------------------
// Emails and the signed feedback page.
// ---------------------------------------------------------------------------

/**
 * The signed link that opens straight to this interviewer's feedback form.
 *
 * @param int    $application_id Application ID.
 * @param string $email          Interviewer's email.
 * @return string
 */
function flxlm_ats_feedback_url( $application_id, $email ) {
	$email = strtolower( trim( (string) $email ) );
	return add_query_arg(
		array(
			'flxlm_ats'   => 'feedback',
			'application' => (int) $application_id,
			'interviewer' => rawurlencode( $email ),
			'token'       => rawurlencode( flxlm_ats_make_token( $application_id, 'feedback:' . $email, FLXLM_ATS_FEEDBACK_TTL ) ),
		),
		home_url( '/' )
	);
}

/**
 * Email a newly assigned interviewer their feedback link.
 *
 * Deliberately omits the candidate's phone and address: an interviewer needs
 * enough to do the interview and leave feedback, not the full application
 * record. The resume link is included because it is the one thing every
 * interviewer legitimately needs before a conversation.
 *
 * @param int    $application_id Application ID.
 * @param string $email          Interviewer's email.
 * @param string $name           Interviewer's display name.
 */
function flxlm_ats_notify_interviewer_assigned( $application_id, $email, $name ) {
	$candidate  = flxlm_ats_applicant_name( $application_id );
	$job        = flxlm_ats_job_title( $application_id );
	$stage      = flxlm_ats_stage_label( get_post_status( $application_id ) );
	$has_resume = (bool) get_post_meta( $application_id, '_flxlm_resume_file', true );

	$resume_url   = $has_resume ? flxlm_ats_resume_url( $application_id ) : '';
	$feedback_url = flxlm_ats_feedback_url( $application_id, $email );

	$subject = 'Feedback requested: ' . $candidate . ', ' . $job;

	ob_start();
	?>
	<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#222;line-height:1.55;max-width:36rem">
		<p>Hi <?php echo esc_html( $name ? $name : $email ); ?>,</p>
		<p>You have been added as an interviewer for <strong><?php echo esc_html( $candidate ); ?></strong>, applying for <strong><?php echo esc_html( $job ); ?></strong> (currently <?php echo esc_html( $stage ); ?>).</p>

		<?php if ( $resume_url ) : ?>
			<p style="margin:0 0 1.25rem">
				<a href="<?php echo esc_url( $resume_url ); ?>"
					style="display:inline-block;background:#512DA8;color:#fff;text-decoration:none;
						padding:.7rem 1.3rem;border-radius:6px">Read the resume</a>
			</p>
		<?php endif; ?>

		<p style="margin:0 0 1.25rem">
			<a href="<?php echo esc_url( $feedback_url ); ?>"
				style="display:inline-block;background:#1e7e34;color:#fff;text-decoration:none;
					padding:.7rem 1.3rem;border-radius:6px">Leave feedback</a>
		</p>

		<p style="color:#666;font-size:.9rem">Keep notes factual and about the job. Assume the candidate or a regulator may read them someday.</p>
		<p style="color:#777;font-size:.8rem">That link works for 30 days and is private to you. Please do not forward it.</p>
	</div>
	<?php
	$html = ob_get_clean();

	flxlm_ats_send_html( array( $email ), $subject, $html );
}

/**
 * Email the hiring manager once a piece of interviewer feedback lands.
 *
 * @param int $application_id Application ID.
 * @param string $interviewer_email Who just submitted feedback.
 */
function flxlm_ats_notify_feedback_received( $application_id, $interviewer_email ) {
	$recipients = flxlm_ats_notify_recipients( $application_id );
	if ( ! $recipients ) {
		return;
	}

	$candidate = flxlm_ats_applicant_name( $application_id );
	$active    = flxlm_ats_active_interviewers( $application_id );
	$total     = count( $active );
	$in        = 0;
	foreach ( $active as $entry ) {
		if ( $entry['feedback_submitted'] ) {
			++$in;
		}
	}

	$interviewer_name = $interviewer_email;
	foreach ( $active as $entry ) {
		if ( strtolower( $entry['email'] ) === strtolower( $interviewer_email ) ) {
			$interviewer_name = $entry['name'] ? $entry['name'] : $interviewer_email;
			break;
		}
	}

	$subject = sprintf( 'Feedback from %s on %s (%d of %d in)', $interviewer_name, $candidate, $in, $total );
	$url     = admin_url( 'post.php?post=' . (int) $application_id . '&action=edit' );

	$html = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Arial,sans-serif;color:#222;line-height:1.55;max-width:36rem">'
		. '<p>' . esc_html( $interviewer_name ) . ' left feedback on <strong>' . esc_html( $candidate ) . '</strong>.</p>'
		. '<p>' . esc_html( $in ) . ' of ' . esc_html( $total ) . ' interviewer(s) have submitted feedback.</p>'
		. '<p><a href="' . esc_url( $url ) . '">Open this application</a></p>'
		. '</div>';

	flxlm_ats_send_html( $recipients, $subject, $html );
}

/**
 * The login-free feedback route: ?flxlm_ats=feedback.
 *
 * GET renders the form; only the POST saves anything. Same GET-is-inert
 * discipline as the confirm-page decision links (inc/confirm-page.php) and
 * for the same reason: a mail security scanner prefetches every URL in an
 * incoming message, and a GET that saved feedback would let the scanner
 * "leave feedback" before the interviewer ever opens the email.
 */
function flxlm_ats_handle_feedback_route() {
	$route = isset( $_REQUEST['flxlm_ats'] ) ? sanitize_key( wp_unslash( $_REQUEST['flxlm_ats'] ) ) : '';
	if ( 'feedback' !== $route ) {
		return;
	}

	$application_id = isset( $_REQUEST['application'] ) ? (int) $_REQUEST['application'] : 0;
	$email          = isset( $_REQUEST['interviewer'] ) ? sanitize_email( wp_unslash( $_REQUEST['interviewer'] ) ) : '';
	$token          = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';

	if ( ! is_email( $email ) ) {
		flxlm_ats_simple_page( 'Link not valid', 'That link does not name an interviewer we recognise.' );
	}

	$verified = flxlm_ats_verify_token( $application_id, 'feedback:' . strtolower( $email ), $token );
	if ( is_wp_error( $verified ) ) {
		flxlm_ats_simple_page( 'Link not valid', $verified->get_error_message() );
	}

	$application = flxlm_ats_get_application( $application_id );
	if ( ! $application ) {
		flxlm_ats_simple_page( 'Not found', 'That application no longer exists.' );
	}

	if ( ! flxlm_ats_is_active_interviewer( $application_id, $email ) ) {
		flxlm_ats_simple_page(
			'No longer an interviewer',
			'You have been removed as an interviewer on this application, so this link no longer accepts feedback. If that is a mistake, ask whoever is hiring for this role to re-add you.'
		);
	}

	$candidate = flxlm_ats_applicant_name( $application_id );
	$job       = flxlm_ats_job_title( $application_id );

	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		$rating = isset( $_POST['rating'] ) ? sanitize_key( wp_unslash( $_POST['rating'] ) ) : '';
		$notes  = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$when   = isset( $_POST['interview_date'] ) ? sanitize_text_field( wp_unslash( $_POST['interview_date'] ) ) : '';

		$errors = array();
		if ( ! in_array( $rating, flxlm_ats_note_ratings(), true ) ) {
			$errors[] = 'Please choose a recommendation.';
		}
		if ( strlen( $notes ) < 20 ) {
			$errors[] = 'Please write at least a couple of sentences (20 characters minimum).';
		}

		if ( $errors ) {
			flxlm_ats_render_feedback_form( $application_id, $email, $candidate, $job, $token, implode( ' ', $errors ), $rating, $notes, $when );
			exit;
		}

		$body = $notes;
		if ( $when ) {
			$body .= "\n\nInterview date: " . $when;
		}

		$active = flxlm_ats_all_interviewers( $application_id );
		$name   = $email;
		foreach ( $active as $entry ) {
			if ( strtolower( $entry['email'] ?? '' ) === strtolower( $email ) ) {
				$name = $entry['name'] ? $entry['name'] : $email;
				break;
			}
		}

		$note_id = flxlm_ats_add_note(
			$application_id,
			'feedback',
			array(
				'body'         => $body,
				'rating'       => $rating,
				'author_email' => $email,
				'author_name'  => $name,
			)
		);

		if ( is_wp_error( $note_id ) ) {
			flxlm_ats_simple_page( 'Could not save', $note_id->get_error_message() );
		}

		flxlm_ats_notify_feedback_received( $application_id, $email );

		flxlm_ats_simple_page(
			'Thank you',
			'Your feedback on <strong>' . esc_html( $candidate ) . '</strong> has been recorded. You can submit again later if anything changes; the most recent note is what counts.',
			true,
			false
		);
	}

	flxlm_ats_render_feedback_form( $application_id, $email, $candidate, $job, $token );
}
add_action( 'template_redirect', 'flxlm_ats_handle_feedback_route' );

/**
 * Render the feedback form (GET, and re-rendered with errors on a failed POST).
 *
 * Deliberately shows only the candidate's name and the job — no phone, email
 * or address. An interviewer authenticated by a mailed link does not need the
 * rest of the file, and this page is one the candidate's contact details
 * should never be one forwarded link away from.
 *
 * @param int    $application_id Application ID.
 * @param string $email          Interviewer email.
 * @param string $candidate      Candidate display name.
 * @param string $job            Job title.
 * @param string $token          Signed token, re-emitted in the form.
 * @param string $error          Optional validation error to show.
 * @param string $rating         Sticky field value.
 * @param string $notes          Sticky field value.
 * @param string $when           Sticky field value.
 */
function flxlm_ats_render_feedback_form( $application_id, $email, $candidate, $job, $token, $error = '', $rating = '', $notes = '', $when = '' ) {
	$labels = array(
		'strong_yes' => 'Strong yes',
		'yes'        => 'Yes',
		'no'         => 'No',
		'strong_no'  => 'Strong no',
	);

	ob_start();
	?>
	<?php if ( $error ) : ?>
		<p style="color:#b32d2e"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<p><strong><?php echo esc_html( $candidate ); ?></strong><br /><?php echo esc_html( $job ); ?></p>

	<form method="post">
		<input type="hidden" name="flxlm_ats" value="feedback" />
		<input type="hidden" name="application" value="<?php echo esc_attr( $application_id ); ?>" />
		<input type="hidden" name="interviewer" value="<?php echo esc_attr( $email ); ?>" />
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />

		<p style="margin-bottom:.4rem"><strong>Recommendation</strong></p>
		<?php foreach ( $labels as $key => $label ) : ?>
			<label style="display:block;margin-bottom:.3rem">
				<input type="radio" name="rating" value="<?php echo esc_attr( $key ); ?>" <?php checked( $rating, $key ); ?> required />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>

		<p style="margin:1rem 0 .4rem"><strong>Notes</strong> <span style="color:#666;font-weight:normal">(at least a couple of sentences)</span></p>
		<textarea name="notes" rows="5" style="width:100%;font:inherit" required><?php echo esc_textarea( $notes ); ?></textarea>

		<p style="margin:1rem 0 .4rem"><strong>Interview date</strong> <span style="color:#666;font-weight:normal">(optional)</span></p>
		<input type="date" name="interview_date" value="<?php echo esc_attr( $when ); ?>" />

		<p style="margin-top:1.25rem"><button type="submit" class="flxlm-ats-btn">Submit feedback</button></p>
	</form>
	<?php

	flxlm_ats_simple_page( 'Feedback', ob_get_clean(), false, false );
}
