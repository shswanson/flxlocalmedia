<?php
/**
 * The wp-admin lock on job postings.
 *
 * Once the hub is the place postings are written, a second editor in wp-admin
 * is a second source of truth: a change made there never reaches FLDN, and the
 * next hub save silently overwrites it. So when wp-config.php defines
 *
 *     define( 'FLXLM_POSTINGS_LOCKED', true );
 *
 * nobody can edit, add, trash, quick-edit or bulk-edit a flxlm_job in wp-admin.
 * OFF by default; the switch is flipped after the hub path is verified live.
 *
 * A REAL LOCK, NOT HIDDEN BUTTONS. The capability check itself says no
 * (map_meta_cap on edit_post, delete_post and publish_post), so a hand-built POST to post.php or admin-ajax.php fails the
 * same way the missing button does. The UI changes below only stop people
 * clicking into a screen that would refuse them anyway.
 *
 * WHAT STILL WRITES. The hub bridge (REST) and the FLDN sync call
 * wp_update_post() directly, which never asks for a capability, and this lock
 * only applies inside wp-admin requests that are not REST and not WP-CLI. So
 * the one sanctioned writer keeps working and an operator at the command line
 * is not locked out of a repair.
 *
 * Keyed on the post type NAME, not on the theme's registration, so it holds
 * even if the theme that registers flxlm_job changes.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** The hub page the notice sends people to. */
const FLXLM_ATS_POSTINGS_HUB_URL = 'https://hub.flxlocalmedia.com/hiring/';

/**
 * Whether this request is subject to the lock.
 *
 * is_admin() is true for admin-ajax.php too, which is where quick edit runs,
 * so that is covered.
 *
 * @return bool
 */
function flxlm_ats_postings_locked() {
	if ( ! defined( 'FLXLM_POSTINGS_LOCKED' ) || ! FLXLM_POSTINGS_LOCKED ) {
		return false;
	}
	if ( ! is_admin() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}
	return true;
}

/**
 * Deny edit, delete and publish on any flxlm_job while locked.
 *
 * @param string[] $caps    Primitive caps the meta cap maps to.
 * @param string   $cap     Meta cap being checked.
 * @param int      $user_id User.
 * @param array    $args    [0] is the post ID for these caps.
 * @return string[]
 */
function flxlm_ats_postings_lock_map_meta_cap( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'publish_post' ), true ) ) {
		return $caps;
	}
	if ( empty( $args[0] ) || ! flxlm_ats_postings_locked() ) {
		return $caps;
	}
	$post = get_post( (int) $args[0] );
	if ( ! $post || 'flxlm_job' !== $post->post_type ) {
		return $caps;
	}
	return array( 'do_not_allow' );
}
add_filter( 'map_meta_cap', 'flxlm_ats_postings_lock_map_meta_cap', 10, 4 );

/**
 * Refuse the Add New screen outright with the same message as the notice.
 *
 * flxlm_job uses capability_type 'post', so its create_posts is the same
 * primitive cap as an ordinary news post and cannot be revoked without locking
 * the newsroom out too. The screen is refused instead, and even a draft that
 * slipped through could never be saved, because saving checks edit_post.
 */
function flxlm_ats_postings_lock_block_new() {
	if ( ! flxlm_ats_postings_locked() ) {
		return;
	}
	$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'flxlm_job' !== $type ) {
		return;
	}
	wp_die(
		wp_kses( flxlm_ats_postings_lock_message(), array( 'a' => array( 'href' => array() ) ) ),
		'Job postings are managed in the hub',
		array( 'response' => 403, 'back_link' => true )
	);
}
add_action( 'load-post-new.php', 'flxlm_ats_postings_lock_block_new' );

/**
 * The notice text, with the hub link.
 *
 * @return string HTML.
 */
function flxlm_ats_postings_lock_message() {
	return 'Job postings are managed in the hub at <a href="' . esc_url( FLXLM_ATS_POSTINGS_HUB_URL ) . '">'
		. esc_html( FLXLM_ATS_POSTINGS_HUB_URL ) . '</a> . Changes made here would be overwritten.';
}

/**
 * Show the notice on every flxlm_job admin screen.
 */
function flxlm_ats_postings_lock_notice() {
	if ( ! flxlm_ats_postings_locked() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'flxlm_job' !== $screen->post_type ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>'
		. wp_kses( flxlm_ats_postings_lock_message(), array( 'a' => array( 'href' => array() ) ) )
		. '</p></div>';
}
add_action( 'admin_notices', 'flxlm_ats_postings_lock_notice' );

/**
 * Drop the bulk actions (Edit, Move to Trash) from the postings list.
 *
 * @param array $actions Bulk actions.
 * @return array
 */
function flxlm_ats_postings_lock_bulk_actions( $actions ) {
	return flxlm_ats_postings_locked() ? array() : $actions;
}
add_filter( 'bulk_actions-edit-flxlm_job', 'flxlm_ats_postings_lock_bulk_actions' );

/**
 * Drop the row actions (Edit, Quick Edit, Trash) but keep View.
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function flxlm_ats_postings_lock_row_actions( $actions, $post ) {
	if ( ! flxlm_ats_postings_locked() || 'flxlm_job' !== $post->post_type ) {
		return $actions;
	}
	return array_intersect_key( $actions, array( 'view' => true, 'preview' => true ) );
}
add_filter( 'post_row_actions', 'flxlm_ats_postings_lock_row_actions', 10, 2 );

/**
 * Remove the Add New menu item and button.
 */
function flxlm_ats_postings_lock_menu() {
	if ( flxlm_ats_postings_locked() ) {
		remove_submenu_page( 'edit.php?post_type=flxlm_job', 'post-new.php?post_type=flxlm_job' );
	}
}
add_action( 'admin_menu', 'flxlm_ats_postings_lock_menu', 999 );

/**
 * Hide the "Add New" button beside the list title. The button is printed
 * unconditionally by core when create_posts is held, so CSS is the only way
 * to hide it; post-new.php itself is refused above, so this is cosmetic.
 */
function flxlm_ats_postings_lock_admin_css() {
	if ( ! flxlm_ats_postings_locked() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'flxlm_job' === $screen->post_type ) {
		echo '<style>.post-type-flxlm_job .page-title-action{display:none}</style>';
	}
}
add_action( 'admin_head', 'flxlm_ats_postings_lock_admin_css' );
