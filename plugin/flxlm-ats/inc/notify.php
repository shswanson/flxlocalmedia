<?php
/**
 * Notifications.
 *
 * Two messages go out when an application arrives: one to the hiring manager so
 * the candidate gets looked at, and one to the applicant so they know the thing
 * they just spent twenty minutes on actually arrived.
 *
 * EMAIL, NOT SLACK. The open question in the original ticket was which to use.
 * Email, because the people who decide on candidates are station and department
 * managers, and not all of them are in Slack day to day. An application that
 * lands somewhere the decision-maker does not look is the same as no
 * application. Slack can be added later as an additional destination; the
 * flxlm_ats_application_received hook is the place to do it.
 *
 * THE RESUME IS A LINK, NEVER AN ATTACHMENT. Attaching it would copy someone's
 * home address and phone number into every mailbox the message is forwarded to,
 * where it lives forever and outside any retention control. A signed link
 * expires in three days, can be revoked by rotating one option, and leaves the
 * only durable copy of the file on the server where it belongs.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Who gets told about an application.
 *
 * Prefers the job posting's own application email, which is the field that
 * already routes each posting to the right manager. Falls back to the site
 * admin so an application is never received silently.
 *
 * @param int $application_id Application ID.
 * @return string[] Email addresses.
 */
function flxlm_ats_notify_recipients( $application_id ) {
	$recipients = array();
	$job_id     = (int) get_post_meta( $application_id, '_flxlm_job_id', true );

	if ( $job_id ) {
		$job_email = (string) get_post_meta( $job_id, 'job_email', true );
		foreach ( explode( ',', $job_email ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( is_email( $candidate ) ) {
				$recipients[] = $candidate;
			}
		}
	}

	if ( ! $recipients ) {
		$admin = get_option( 'admin_email' );
		if ( is_email( $admin ) ) {
			$recipients[] = $admin;
		}
	}

	/**
	 * Filter who is notified about a new application.
	 *
	 * @param string[] $recipients     Email addresses.
	 * @param int      $application_id Application ID.
	 */
	return apply_filters( 'flxlm_ats_notify_recipients', array_unique( $recipients ), $application_id );
}

/**
 * Email the hiring manager.
 *
 * @param int $application_id Application ID.
 */
function flxlm_ats_notify_manager( $application_id ) {
	$recipients = flxlm_ats_notify_recipients( $application_id );
	if ( ! $recipients ) {
		return;
	}

	$name   = flxlm_ats_applicant_name( $application_id );
	$job    = flxlm_ats_job_title( $application_id );
	$email  = (string) get_post_meta( $application_id, '_flxlm_email', true );
	$phone  = (string) get_post_meta( $application_id, '_flxlm_phone', true );
	$links  = (string) get_post_meta( $application_id, '_flxlm_links', true );
	$note   = (string) get_post_meta( $application_id, '_flxlm_message', true );
	$source = flxlm_ats_source_label( get_post_meta( $application_id, '_flxlm_source', true ) );
	$has_cv = (bool) get_post_meta( $application_id, '_flxlm_resume_file', true );

	$resume_url  = $has_cv ? flxlm_ats_resume_url( $application_id ) : '';
	$screen_url  = flxlm_ats_action_url( $application_id, 'flxlm_screening' );
	$manager_url = flxlm_ats_action_url( $application_id, 'flxlm_manager' );
	$reject_url  = flxlm_ats_action_url( $application_id, 'flxlm_rejected' );
	$admin_url   = admin_url( 'post.php?post=' . $application_id . '&action=edit' );

	$subject = 'New application: ' . $name . ' — ' . $job;

	ob_start();
	?>
	<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#222;line-height:1.55;max-width:36rem">
		<h2 style="margin:0 0 .25rem;font-size:1.25rem"><?php echo esc_html( $name ); ?></h2>
		<p style="margin:0 0 1.25rem;color:#555"><?php echo esc_html( $job ); ?></p>

		<table cellpadding="0" cellspacing="0" style="font-size:.95rem;margin-bottom:1.25rem">
			<tr>
				<td style="padding:.2rem 1rem .2rem 0;color:#666">Email</td>
				<td style="padding:.2rem 0"><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></td>
			</tr>
			<?php if ( $phone ) : ?>
			<tr>
				<td style="padding:.2rem 1rem .2rem 0;color:#666">Phone</td>
				<td style="padding:.2rem 0"><?php echo esc_html( $phone ); ?></td>
			</tr>
			<?php endif; ?>
			<tr>
				<td style="padding:.2rem 1rem .2rem 0;color:#666">Heard about us</td>
				<td style="padding:.2rem 0"><?php echo esc_html( $source ); ?></td>
			</tr>
		</table>

		<?php if ( $links ) : ?>
			<p style="margin:0 0 .35rem;color:#666;font-size:.95rem">Their work</p>
			<p style="margin:0 0 1.25rem;white-space:pre-line"><?php echo esc_html( $links ); ?></p>
		<?php endif; ?>

		<?php if ( $note ) : ?>
			<p style="margin:0 0 .35rem;color:#666;font-size:.95rem">What they said</p>
			<p style="margin:0 0 1.25rem;white-space:pre-line"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>

		<?php if ( $resume_url ) : ?>
			<p style="margin:0 0 1.5rem">
				<a href="<?php echo esc_url( $resume_url ); ?>"
					style="display:inline-block;background:#512DA8;color:#fff;text-decoration:none;
						padding:.75rem 1.4rem;border-radius:6px">Read the resume</a>
			</p>
			<p style="margin:0 0 1.5rem;color:#777;font-size:.8rem">
				That resume link works for three days and is private to you. Please do not forward it.
			</p>
		<?php endif; ?>

		<p style="margin:0 0 .4rem;color:#666;font-size:.95rem">Move this applicant</p>
		<p style="margin:0 0 1.25rem;font-size:.95rem">
			<a href="<?php echo esc_url( $screen_url ); ?>">Screening</a>
			&nbsp;·&nbsp;
			<a href="<?php echo esc_url( $manager_url ); ?>">Manager Review</a>
			&nbsp;·&nbsp;
			<a href="<?php echo esc_url( $reject_url ); ?>">Not Selected</a>
		</p>

		<p style="margin:0;color:#777;font-size:.8rem">
			Each link opens a page that asks you to confirm before anything changes.
			You can also <a href="<?php echo esc_url( $admin_url ); ?>">open this application in the admin</a>.
		</p>
	</div>
	<?php
	$html = ob_get_clean();

	flxlm_ats_send_html( $recipients, $subject, $html );
}

/**
 * Tell the applicant we have it.
 *
 * Small courtesy with a real payoff: the most common reason a candidate chases
 * an employer, or gives up on one, is not knowing whether the application
 * arrived at all.
 *
 * @param int $application_id Application ID.
 */
function flxlm_ats_notify_applicant( $application_id ) {
	$email = (string) get_post_meta( $application_id, '_flxlm_email', true );
	if ( ! is_email( $email ) ) {
		return;
	}

	// A hand-entered applicant does not get an automated receipt. The staff
	// member is sitting with them or has already spoken to them, and a machine
	// thanking someone for a conversation that happened in person reads as a
	// machine that was not paying attention.
	if ( 'manual' === get_post_meta( $application_id, '_flxlm_entered_by', true ) ) {
		return;
	}

	$first = (string) get_post_meta( $application_id, '_flxlm_first_name', true );
	$job   = flxlm_ats_job_title( $application_id );

	ob_start();
	?>
	<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#222;line-height:1.55;max-width:36rem">
		<p>Hi <?php echo esc_html( $first ); ?>,</p>
		<p>Thanks for applying for <strong><?php echo esc_html( $job ); ?></strong>. We have your application and someone here will read it.</p>
		<p>If we would like to talk, we will be in touch at this address.</p>
		<p style="margin-top:1.5rem">FLX Local Media<br />
			<span style="color:#666">Seven radio stations and Finger Lakes Daily News</span></p>
		<p style="color:#777;font-size:.8rem;margin-top:1.5rem">
			FLX Local Media is an equal opportunity employer.
		</p>
	</div>
	<?php
	$html = ob_get_clean();

	flxlm_ats_send_html( array( $email ), 'We received your application — ' . $job, $html );
}

/**
 * Send an HTML mail without leaving the site's global mail format changed.
 *
 * The content-type filter is added and removed around the single send, because
 * leaving it attached would silently turn every other plugin's plain-text mail
 * into HTML.
 *
 * @param string[] $to      Recipients.
 * @param string   $subject Subject.
 * @param string   $html    Body.
 * @return bool
 */
function flxlm_ats_send_html( $to, $subject, $html ) {
	$as_html = function () {
		return 'text/html';
	};

	add_filter( 'wp_mail_content_type', $as_html );
	$sent = wp_mail( $to, $subject, $html );
	remove_filter( 'wp_mail_content_type', $as_html );

	return $sent;
}

/**
 * Wire notifications to intake.
 *
 * Both sends are wrapped so that a mail failure can never take down the intake
 * request: by the time this runs the application is already safely stored, and
 * an applicant must not see an error because a mail server was slow.
 */
add_action(
	'flxlm_ats_application_received',
	function ( $application_id ) {
		try {
			flxlm_ats_notify_manager( $application_id );
			flxlm_ats_notify_applicant( $application_id );
		} catch ( Exception $e ) {
			// Deliberately swallowed. The record exists; notification is best effort.
			error_log( 'flxlm-ats: notification failed for application ' . $application_id . ': ' . $e->getMessage() );
		}
	},
	10,
	1
);
