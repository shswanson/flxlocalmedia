<?php
/**
 * Retention.
 *
 * 47 CFR 73.2080(c)(5) requires a broadcast licensee to keep the records that
 * document its EEO compliance until final action is taken on its next licence
 * renewal. FLX Local Media's facilities run to 2030-06-01, and the FCC's 2026
 * audit round required licensees to produce their last two annual reports plus
 * the data behind them.
 *
 * So an application is not a post anyone should be able to tidy up. Any user
 * who can edit posts can normally trash one, and a trashed record is one
 * emptied trash away from being gone. That is a compliance failure waiting for
 * a spring clean, and it would be discovered years later, by an auditor.
 *
 * The post type declares its delete capabilities as 'flxlm_delete_applications'
 * (see inc/post-type.php), a capability deliberately granted to nobody. This
 * file closes the remaining routes: the Trash link, bulk trash, and any
 * programmatic delete.
 *
 * REMOVING AN APPLICATION IS STILL POSSIBLE, just not casually: someone with
 * database access can do it deliberately. The bar is "you had to mean it,"
 * which is the correct bar for destroying a compliance record.
 *
 * WHAT THIS DOES NOT DO
 *
 * It does not solve long-term PII minimisation. Resumes accumulate, and a
 * resume holds a home address. Once the retention obligation for a given period
 * has actually expired there is a good argument for purging the FILES while
 * keeping the counts, since the EEO report needs numbers and sources, never the
 * document. That needs a decision about the licence-renewal boundary rather
 * than a guess, so it is deliberately left as a known open item rather than
 * implemented with an invented date. flxlm_ats_retention_note() is where that
 * reasoning is surfaced to whoever files the report.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Refuse to trash an application.
 *
 * @param bool|null $trash Whether to short-circuit.
 * @param WP_Post   $post  Post being trashed.
 * @return bool|null False stops the trash.
 */
function flxlm_ats_block_trash( $trash, $post ) {
	if ( $post && 'flxlm_application' === $post->post_type ) {
		return false;
	}
	return $trash;
}
add_filter( 'pre_trash_post', 'flxlm_ats_block_trash', 10, 2 );

/**
 * Refuse to delete an application.
 *
 * @param bool|null $delete Whether to short-circuit.
 * @param WP_Post   $post   Post being deleted.
 * @return bool|null False stops the delete.
 */
function flxlm_ats_block_delete( $delete, $post ) {
	if ( $post && 'flxlm_application' === $post->post_type ) {
		return false;
	}
	return $delete;
}
add_filter( 'pre_delete_post', 'flxlm_ats_block_delete', 10, 2 );

/**
 * Take Trash out of the bulk actions on the applications list.
 *
 * @param array $actions Bulk actions.
 * @return array
 */
function flxlm_ats_remove_bulk_trash( $actions ) {
	unset( $actions['trash'], $actions['untrash'], $actions['delete'] );
	return $actions;
}
add_filter( 'bulk_actions-edit-flxlm_application', 'flxlm_ats_remove_bulk_trash' );

/**
 * Explain the rule where someone would otherwise go looking for Delete.
 */
function flxlm_ats_retention_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-flxlm_application' !== $screen->id ) {
		return;
	}

	echo '<div class="notice notice-info"><p>'
		. 'Applications are kept, not deleted. FCC rules require these records to be retained as evidence '
		. 'of equal-opportunity compliance, so there is no Trash here. Use <strong>Not Selected</strong> '
		. 'to close out a candidate.'
		. '</p></div>';
}
add_action( 'admin_notices', 'flxlm_ats_retention_notice' );

/**
 * The retention position, in plain language, for the EEO report screen.
 *
 * @return string
 */
function flxlm_ats_retention_note() {
	return 'Applications and resumes are retained and cannot be deleted from this screen. '
		. 'FCC rules require EEO records to be kept until final action on the next licence renewal. '
		. 'Purging old resume files while keeping the reporting numbers is a sensible future step, '
		. 'but it needs a decision on the renewal boundary first, so nothing is deleted automatically today.';
}
