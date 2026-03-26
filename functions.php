<?php
/**
 * FLX Local Media theme functions.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLXLM_VERSION', '2.0.0' );

/**
 * Theme setup.
 */
function flxlm_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
	add_theme_support( 'custom-logo' );

	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'flxlm' ),
		'footer'  => __( 'Footer Navigation', 'flxlm' ),
	) );
}
add_action( 'after_setup_theme', 'flxlm_setup' );

/**
 * Enqueue styles and scripts.
 */
function flxlm_enqueue_assets() {
	wp_enqueue_style( 'flxlm-fonts', 'https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600;700;800&display=swap', array(), null );
	wp_enqueue_style( 'flxlm-style', get_template_directory_uri() . '/assets/css/style.css', array( 'flxlm-fonts' ), FLXLM_VERSION );
	wp_enqueue_script( 'flxlm-main', get_template_directory_uri() . '/assets/js/main.js', array(), FLXLM_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'flxlm_enqueue_assets' );

// Include CPT, helpers, forms.
require_once get_template_directory() . '/inc/cpt-testimonials.php';
require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/forms.php';
