<?php
/**
 * Permanently disable the block editor (Gutenberg) site-wide.
 *
 * Reverts every post type to the classic editor, disables the block-based
 * widgets screen, and drops Gutenberg's front-end block CSS (this theme
 * renders no block content). Kept in the theme so it survives deploys
 * rather than depending on a togglable plugin.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Use the classic editor for every post and every post type.
add_filter( 'use_block_editor_for_post', '__return_false', 100 );
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );

// Revert the Appearance → Widgets screen to classic widgets.
add_filter( 'use_widgets_block_editor', '__return_false' );

// Drop Gutenberg's front-end block-library CSS — unused on this site.
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
}, 100 );
