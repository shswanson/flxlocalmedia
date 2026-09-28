<?php
/**
 * Team comments on an application, and who gets told about one.
 *
 * A comment is a 'comment'-kind note (inc/notes.php); this file is only the
 * business logic of WHO hears about it. That set is deliberately wide —
 * everyone with a stake in the candidate, not just the person who happened to
 * be looking at the record — because the failure mode this replaces is a
 * hallway conversation about a candidate that never reaches the person who
 * would have made the call differently for having heard it.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

const FLXLM_ATS_BUSINESS_MANAGER_OPTION = 'flxlm_ats_business_manager_emails';

/**
 * The business manager address(es): the Offer-stage owner and the fallback
 * recipient for anything with no other route (see inc/notify.php).
 *
 * Stored as an option rather than a constant so it can be corrected without a
 * deploy; defaults to the one address the v1 contract names.
 *
 * @return string[]
 */
function flxlm_ats_business_manager_emails() {
	$emails = get_option( FLXLM_ATS_BUSINESS_MANAGER_OPTION, array( 'dlynn@flxlocalmedia.com' ) );
	$emails = is_array( $emails ) ? $emails : array( $emails );

	$emails = array_values( array_filter( array_map( 'sanitize_email', $emails ), 'is_email' ) );

	/**
	 * Filter the business manager recipient list.
	 *
	 * @param string[] $emails
	 */
	return apply_filters( 'flxlm_ats_business_manager_emails', $emails );
}

/**
 * Add a comment and notify everyone with a stake in this candidate, except
 * whoever just wrote it.
 *
 * @param int    $application_id Application ID.
 * @param string $body           Comment text.
 * @param string $author_email   Commenter's email.
 * @param string $author_name    Commenter's display name.
 * @return int|WP_Error New note id.
 */
function flxlm_ats_add_comment( $application_id, $body, $author_email, $author_name = '' ) {
	$note_id = flxlm_ats_add_note(
		$application_id,
		'comment',
		array(
			'body'         => $body,
			'author_email' => $author_email,
			'author_name'  => $author_name,
		)
	);

	if ( is_wp_error( $note_id ) ) {
		return $note_id;
	}

	flxlm_ats_notify_comment( $application_id, $body, $author_email, $author_name );

	return $note_id;
}

/**
 * Email everyone involved in this application except the comment's author:
 * the job's hiring manager, active interviewers, earlier commenters, and the
 * business manager address(es).
 *
 * @param int    $application_id Application ID.
 * @param string $body           Comment text.
 * @param string $author_email   Commenter's email, excluded from the send.
 * @param string $author_name    Commenter's display name.
 */
function flxlm_ats_notify_comment( $application_id, $body, $author_email, $author_name ) {
	$application_id = (int) $application_id;
	$author_email   = strtolower( trim( (string) $author_email ) );

	$recipients = array();

	// The job's hiring manager, else its notification recipients (job_email or
	// the business managers) — same routing notify.php already uses to decide
	// who is "involved" with this posting.
	foreach ( flxlm_ats_notify_recipients( $application_id ) as $email ) {
		$recipients[] = $email;
	}

	foreach ( flxlm_ats_active_interviewers( $application_id ) as $entry ) {
		$recipients[] = $entry['email'];
	}

	foreach ( flxlm_ats_get_notes( $application_id, array( 'comment' ) ) as $note ) {
		if ( $note['author_email'] ) {
			$recipients[] = $note['author_email'];
		}
	}

	foreach ( flxlm_ats_business_manager_emails() as $email ) {
		$recipients[] = $email;
	}

	$recipients = array_unique( array_filter( array_map( 'strtolower', $recipients ), 'is_email' ) );
	$recipients = array_values( array_diff( $recipients, array( $author_email ) ) );

	if ( ! $recipients ) {
		return;
	}

	$candidate = flxlm_ats_applicant_name( $application_id );
	$job       = flxlm_ats_job_title( $application_id );
	$subject   = 'New comment on ' . $candidate . ' (' . $job . ')';
	$hub_url   = 'https://hub.flxlocalmedia.com/hiring/?application=' . $application_id;
	$who       = $author_name ? $author_name : $author_email;

	ob_start();
	?>
	<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#222;line-height:1.55;max-width:36rem">
		<p><strong><?php echo esc_html( $who ); ?></strong> commented on <strong><?php echo esc_html( $candidate ); ?></strong> (<?php echo esc_html( $job ); ?>):</p>
		<p style="background:#f4f4f6;border-radius:6px;padding:.9rem 1.1rem;white-space:pre-line"><?php echo esc_html( $body ); ?></p>
		<p><a href="<?php echo esc_url( $hub_url ); ?>">Open in the hub</a></p>
	</div>
	<?php
	$html = ob_get_clean();

	flxlm_ats_send_html( $recipients, $subject, $html );
}
