<?php
/**
 * The 1.2.0 data migration: move anything still sitting in a retired stage.
 *
 * flxlm_screening and flxlm_manager were removed from the working ladder when
 * v1 shipped (inc/stages.php). Neither key is deleted from the database by
 * this migration and neither is ever deleted from the code: it only moves the
 * APPLICATIONS off them, onto flxlm_new, so nothing goes invisible and the
 * ladder stays honest about where every candidate actually is.
 *
 * IDEMPOTENT AND SAFE TO RUN ANY NUMBER OF TIMES. It queries for applications
 * currently in a retired status; once none remain, every subsequent run finds
 * nothing to do and returns immediately. That is what lets it run from BOTH
 * admin_init (so a site nobody remembers to run wp-cli against still migrates
 * itself the first time an admin loads a screen after the deploy) and
 * `wp flxlm-ats upgrade` (so a deploy script or a person can run it
 * explicitly and see the count).
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** The plugin version this migration brings data up to date with. */
const FLXLM_ATS_MIGRATION_1_2_0 = '1.2.0';

/**
 * Retired stage keys this migration clears out.
 *
 * @return string[]
 */
function flxlm_ats_retired_stage_keys() {
	return array( 'flxlm_screening', 'flxlm_manager' );
}

/**
 * Run the 1.2.0 migration: move every application in a retired stage to New,
 * appending a history entry that says exactly why.
 *
 * Deliberately does NOT go through flxlm_ats_set_stage(): that function's
 * required-field checks (inc/stages.php) are about a HUMAN choosing to move a
 * candidate forward with incomplete data, which does not describe this case
 * at all. This is a mechanical relabelling of stages that no longer exist,
 * and it must succeed unconditionally — an old application sitting in
 * Screening did not suddenly gain a recruitment source because the ladder was
 * redrawn, and that record's own New-stage exit test will correctly flag it
 * as still needing one afterward.
 *
 * @return array{moved:int, ids:int[]} How many applications were moved, and their IDs.
 */
function flxlm_ats_run_1_2_0_migration() {
	$ids = get_posts(
		array(
			'post_type'      => 'flxlm_application',
			'post_status'    => flxlm_ats_retired_stage_keys(),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$moved = array();

	foreach ( $ids as $application_id ) {
		$from = get_post_status( $application_id );

		$updated = wp_update_post(
			array(
				'ID'          => $application_id,
				'post_status' => flxlm_ats_initial_stage(),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			continue; // Left in place; the next run will retry it.
		}

		$history   = get_post_meta( $application_id, '_flxlm_stage_history', true );
		$history   = is_array( $history ) ? $history : array();
		$history[] = array(
			'from' => $from,
			'to'   => flxlm_ats_initial_stage(),
			'at'   => gmdate( 'Y-m-d H:i:s' ),
			'by'   => 'migration:' . FLXLM_ATS_MIGRATION_1_2_0,
		);
		update_post_meta( $application_id, '_flxlm_stage_history', $history );

		if ( function_exists( 'flxlm_ats_add_note' ) ) {
			flxlm_ats_add_note(
				(int) $application_id,
				'system',
				array(
					'body'  => sprintf(
						'Moved from the retired %s stage to New by the 1.2.0 upgrade.',
						flxlm_ats_stage_label( $from )
					),
					'stage' => flxlm_ats_initial_stage(),
				)
			);
		}

		$moved[] = (int) $application_id;
	}

	return array(
		'moved' => count( $moved ),
		'ids'   => $moved,
	);
}

/**
 * Run the migration on admin_init, once, gated on the same
 * 'flxlm_ats_version' option the plugin's own version-bump handler already
 * uses (flxlm-ats.php) — so this never re-scans the applications table on
 * every admin page load, only the first one after an upgrade.
 */
function flxlm_ats_maybe_run_1_2_0_migration() {
	if ( get_option( 'flxlm_ats_migration_1_2_0_done' ) ) {
		return;
	}

	flxlm_ats_run_1_2_0_migration();
	update_option( 'flxlm_ats_migration_1_2_0_done', gmdate( 'Y-m-d H:i:s' ) );
}
add_action( 'admin_init', 'flxlm_ats_maybe_run_1_2_0_migration' );

/**
 * `wp flxlm-ats upgrade` — run the migration explicitly and report the count.
 *
 * Unlike the admin_init hook, this ALWAYS runs the query (never gated on the
 * "already done" option): someone running this by hand wants to know "is
 * there anything left in a retired stage right now", and the answer must
 * reflect the live data, not whether the flag has been set before.
 */
function flxlm_ats_cli_upgrade( $args, $assoc_args ) {
	$result = flxlm_ats_run_1_2_0_migration();

	update_option( 'flxlm_ats_migration_1_2_0_done', gmdate( 'Y-m-d H:i:s' ) );

	if ( 0 === $result['moved'] ) {
		WP_CLI::success( 'Nothing to migrate. No applications are sitting in a retired stage.' );
		return;
	}

	WP_CLI::success(
		sprintf(
			'Moved %d application(s) to New: %s',
			$result['moved'],
			implode( ', ', $result['ids'] )
		)
	);
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'flxlm-ats upgrade', 'flxlm_ats_cli_upgrade' );
}
