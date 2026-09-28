<?php
/**
 * The weekly stuck-candidate digest.
 *
 * V1 deliberately ships no automatic reminder email to a late interviewer and
 * no hard block on a stage move with an unmet exit test (see the contract's
 * NOT v1 list) — this digest is the entire mechanism that catches a candidate
 * quietly going stale. It runs once a week, lists exactly what is stuck and
 * why, and links each line straight to the hub so acting on it costs one
 * click. If this digest goes silent, nothing else in v1 will ever surface a
 * forgotten candidate.
 *
 * WHO GETS WHAT
 *
 * The business manager address(es) get every flagged application, company
 * wide. Each hiring manager gets only the ones against a job they are
 * assigned to (job_hiring_manager — inc/hiring-manager.php). An application
 * with no job, or an unknown-source application on a job with no manager
 * assigned, has nobody to attribute it to and so only ever reaches the
 * business managers: that is a feature, not a gap, because "which of the many
 * unassigned things is actually anyone's job" is precisely the business
 * manager's job to triage.
 *
 * WHAT "STUCK" MEANS
 *
 * All four conditions below are evaluated independently and an application
 * can trip more than one; each recipient sees ONE line per application with
 * every reason that matched, not one line per reason:
 *
 *   1. Sitting in New, Phone screen, Decision or Offer for more than 5
 *      business days since its last stage change.
 *   2. Sitting in Interview with an active interviewer who has not submitted
 *      feedback 2+ business days after being assigned.
 *   3. Recruitment source is 'unknown' (manual-entry-only; see inc/sources.php).
 *   4. No job attached at all (email intake that could not be matched, or an
 *      application explicitly assigned to the hub's "Unassigned" pseudo-vacancy).
 *
 * Only non-terminal applications (not Hired, not Not hired) are ever
 * considered: a closed candidate being "stuck" is not a fact that needs
 * chasing.
 *
 * HOW THIS RUNS
 *
 * WP-Cron, scheduled for Monday 13:00 UTC with a weekly recurrence this file
 * registers (WordPress ships hourly/twicedaily/daily but not weekly). This
 * site's wp-config defines DISABLE_WP_CRON => the pseudo-cron that normally
 * fires wp-cron.php on a visitor request is OFF here (confirmed against the
 * local QA install's wp-config.php, which sets it for the same reason the QA
 * README gives: a single-threaded PHP built-in server deadlocks on it — see
 * ~/.claude/memory/infra_local_qa_wordpress.md). On flxlocalmedia.com itself
 * this needs verifying against that site's OWN wp-config before relying on
 * WP-Cron firing unattended in production: if DISABLE_WP_CRON is also set
 * there (common on managed WordPress hosting, which runs cron from the
 * system crontab instead), this event will sit registered but never fire on
 * its own, and `wp flxlm-ats digest` needs a real system cron entry calling
 * it, not WP-Cron. `wp cron event list` on the live box is how to check.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** Business days a New/Phone screen/Decision/Offer application may sit unmoved. */
const FLXLM_ATS_DIGEST_STUCK_DAYS = 5;

/** Business days an assigned interviewer has to submit feedback. */
const FLXLM_ATS_DIGEST_FEEDBACK_DAYS = 2;

/**
 * Register the weekly recurrence WordPress does not ship by default.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function flxlm_ats_add_weekly_schedule( $schedules ) {
	if ( ! isset( $schedules['flxlm_ats_weekly'] ) ) {
		$schedules['flxlm_ats_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => 'Once weekly (FLX ATS digest)',
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'flxlm_ats_add_weekly_schedule' ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- registering our own named schedule, not altering a core one.

/**
 * Schedule the digest for the next Monday 13:00 UTC, if it is not already
 * scheduled. Idempotent: safe to call on every plugin load.
 */
function flxlm_ats_schedule_digest() {
	if ( wp_next_scheduled( 'flxlm_ats_weekly_digest' ) ) {
		return;
	}

	$next = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
	$next->modify( 'next monday' );
	$next->setTime( 13, 0, 0 );
	// DateTime::modify('next monday') from a Monday still lands on the
	// FOLLOWING Monday, which is correct: if today is already past 13:00 on a
	// Monday, this should not fire again immediately, it should wait a week.

	wp_schedule_event( $next->getTimestamp(), 'flxlm_ats_weekly', 'flxlm_ats_weekly_digest' );
}
add_action( 'init', 'flxlm_ats_schedule_digest' );

add_action( 'flxlm_ats_weekly_digest', 'flxlm_ats_send_digest' );

/**
 * Business days elapsed between a UTC datetime string and now (or a supplied
 * "now", for testability). Counts whole weekdays strictly after $from,
 * up to and including $to. Saturday and Sunday never count.
 *
 * @param string      $from_gmt 'Y-m-d H:i:s' UTC.
 * @param string|null $to_gmt   'Y-m-d H:i:s' UTC, defaults to now.
 * @return int
 */
function flxlm_ats_business_days_since( $from_gmt, $to_gmt = null ) {
	if ( ! $from_gmt ) {
		return 0;
	}

	$from = strtotime( $from_gmt . ' UTC' );
	$to   = $to_gmt ? strtotime( $to_gmt . ' UTC' ) : time();

	if ( ! $from || ! $to || $to <= $from ) {
		return 0;
	}

	$days   = 0;
	$cursor = strtotime( '+1 day', strtotime( gmdate( 'Y-m-d', $from ) . ' UTC' ) );

	while ( $cursor <= $to ) {
		$dow = (int) gmdate( 'N', $cursor ); // 1 = Monday ... 7 = Sunday.
		if ( $dow < 6 ) {
			++$days;
		}
		$cursor = strtotime( '+1 day', $cursor );
	}

	return $days;
}

/**
 * When an application last entered its CURRENT stage, falling back to when it
 * was submitted (for a record that has never moved, so has no matching
 * history entry) and finally to its post_date (belt and braces, should never
 * be reached).
 *
 * @param int $application_id Application ID.
 * @return string 'Y-m-d H:i:s' UTC.
 */
function flxlm_ats_current_stage_entered_at( $application_id ) {
	$application_id = (int) $application_id;
	$stage          = get_post_status( $application_id );

	$entered = flxlm_ats_entered_stage_at( $application_id, $stage );
	if ( $entered ) {
		return $entered;
	}

	$submitted = get_post_meta( $application_id, '_flxlm_submitted_at', true );
	if ( $submitted ) {
		return $submitted;
	}

	$post = get_post( $application_id );
	return $post ? get_gmt_from_date( $post->post_date ) : '';
}

/**
 * Build the digest: every non-terminal application that trips at least one
 * "stuck" condition, with the reasons it tripped.
 *
 * @return array[] {application_id, job_id, reasons:string[]}
 */
function flxlm_ats_build_digest() {
	$stages    = flxlm_ats_movable_stages();
	$non_term  = array();
	foreach ( $stages as $key => $stage ) {
		if ( empty( $stage['terminal'] ) ) {
			$non_term[] = $key;
		}
	}

	$ids = get_posts(
		array(
			'post_type'      => 'flxlm_application',
			'post_status'    => $non_term,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$flagged = array();

	$get_entry = function ( $application_id ) use ( &$flagged ) {
		$application_id = (int) $application_id;
		if ( ! isset( $flagged[ $application_id ] ) ) {
			$flagged[ $application_id ] = array(
				'application_id' => $application_id,
				'job_id'         => (int) get_post_meta( $application_id, '_flxlm_job_id', true ),
				'reasons'        => array(),
			);
		}
		return $application_id;
	};

	foreach ( $ids as $application_id ) {
		$application_id = (int) $application_id;
		$stage          = get_post_status( $application_id );

		// (1) Sitting in New / Phone screen / Decision / Offer too long.
		if ( in_array( $stage, array( 'flxlm_new', 'flxlm_phone', 'flxlm_decision', 'flxlm_offer' ), true ) ) {
			$entered = flxlm_ats_current_stage_entered_at( $application_id );
			$days    = flxlm_ats_business_days_since( $entered );
			if ( $days > FLXLM_ATS_DIGEST_STUCK_DAYS ) {
				$get_entry( $application_id );
				$flagged[ $application_id ]['reasons'][] = sprintf(
					'%s for %d business day%s',
					flxlm_ats_stage_label( $stage ),
					$days,
					1 === $days ? '' : 's'
				);
			}
		}

		// (2) Interview: any active interviewer overdue on feedback.
		if ( 'flxlm_interviewed' === $stage ) {
			foreach ( flxlm_ats_active_interviewers( $application_id ) as $interviewer ) {
				if ( $interviewer['feedback_submitted'] ) {
					continue;
				}
				$days = flxlm_ats_business_days_since( $interviewer['assigned_at'] );
				if ( $days >= FLXLM_ATS_DIGEST_FEEDBACK_DAYS ) {
					$get_entry( $application_id );
					$flagged[ $application_id ]['reasons'][] = sprintf(
						'waiting on feedback from %s (%d business day%s)',
						$interviewer['name'] ? $interviewer['name'] : $interviewer['email'],
						$days,
						1 === $days ? '' : 's'
					);
				}
			}
		}

		// (3) Source unknown.
		$source = get_post_meta( $application_id, '_flxlm_source', true );
		if ( 'unknown' === $source ) {
			$get_entry( $application_id );
			$flagged[ $application_id ]['reasons'][] = 'recruitment source not known yet';
		}

		// (4) No job.
		$job_id = (int) get_post_meta( $application_id, '_flxlm_job_id', true );
		if ( ! $job_id ) {
			$get_entry( $application_id );
			$flagged[ $application_id ]['reasons'][] = 'not attached to a job posting';
		}
	}

	return array_values( $flagged );
}

/**
 * Group digest entries by recipient email.
 *
 * @param array[] $entries From flxlm_ats_build_digest().
 * @return array<string,array[]> email => entries.
 */
function flxlm_ats_digest_by_recipient( $entries ) {
	$by_recipient = array();

	$business = flxlm_ats_business_manager_emails();
	foreach ( $business as $email ) {
		$by_recipient[ strtolower( $email ) ] = array();
	}

	foreach ( $entries as $entry ) {
		foreach ( $business as $email ) {
			$by_recipient[ strtolower( $email ) ][] = $entry;
		}

		if ( $entry['job_id'] ) {
			$manager = flxlm_ats_job_hiring_manager( $entry['job_id'] );
			if ( $manager && is_email( $manager->user_email ) ) {
				$key = strtolower( $manager->user_email );
				if ( ! isset( $by_recipient[ $key ] ) ) {
					$by_recipient[ $key ] = array();
				}
				$by_recipient[ $key ][] = $entry;
			}
		}
	}

	// Nobody gets an empty email.
	return array_filter( $by_recipient );
}

/**
 * Send the digest to every recipient with at least one flagged application.
 *
 * @param bool $dry_run True to compute and log without sending mail.
 * @return array<string,int> email => how many applications they were sent (or would be sent).
 */
function flxlm_ats_send_digest( $dry_run = false ) {
	$entries      = flxlm_ats_build_digest();
	$by_recipient = flxlm_ats_digest_by_recipient( $entries );

	$summary = array();

	foreach ( $by_recipient as $email => $rows ) {
		$summary[ $email ] = count( $rows );

		if ( $dry_run ) {
			continue;
		}

		$subject = sprintf( 'FLX hiring: %d candidate%s need attention', count( $rows ), 1 === count( $rows ) ? '' : 's' );

		ob_start();
		?>
		<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#222;line-height:1.55;max-width:40rem">
			<p>These candidates have not moved in a while, or are missing something the report needs:</p>
			<ul style="padding-left:1.1rem">
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$name = flxlm_ats_applicant_name( $row['application_id'] );
					$job  = flxlm_ats_job_title( $row['application_id'] );
					$url  = 'https://hub.flxlocalmedia.com/hiring/?application=' . (int) $row['application_id'];
					?>
					<li style="margin-bottom:.6rem">
						<a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $name ); ?></strong></a>
						, <?php echo esc_html( $job ); ?>:
						<?php echo esc_html( implode( '; ', $row['reasons'] ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		$html = ob_get_clean();

		flxlm_ats_send_html( array( $email ), $subject, $html );
	}

	return $summary;
}

/**
 * `wp flxlm-ats digest [--dry-run]` — run the digest on demand.
 *
 * @param array $args       Positional args (unused).
 * @param array $assoc_args Flags: --dry-run.
 */
function flxlm_ats_cli_digest( $args, $assoc_args ) {
	$dry_run = ! empty( $assoc_args['dry-run'] );

	$summary = flxlm_ats_send_digest( $dry_run );

	if ( ! $summary ) {
		WP_CLI::success( 'Nothing stuck. No digest is due.' );
		return;
	}

	foreach ( $summary as $email => $count ) {
		WP_CLI::log( sprintf( '%s: %d candidate(s)', $email, $count ) );
	}

	WP_CLI::success(
		$dry_run
			? sprintf( 'Dry run: would have emailed %d recipient(s). Nothing was sent.', count( $summary ) )
			: sprintf( 'Emailed %d recipient(s).', count( $summary ) )
	);
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'flxlm-ats digest', 'flxlm_ats_cli_digest' );
}
