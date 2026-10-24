<?php
/**
 * FLX Local Media theme functions.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLXLM_VERSION', '2.3.0' );

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
	$css_ver = filemtime( get_template_directory() . '/assets/css/style.css' );
	$js_ver  = filemtime( get_template_directory() . '/assets/js/main.js' );

	wp_enqueue_style( 'flxlm-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'flxlm-style', get_template_directory_uri() . '/assets/css/style.css', array( 'flxlm-fonts' ), $css_ver );
	wp_enqueue_script( 'flxlm-main', get_template_directory_uri() . '/assets/js/main.js', array(), $js_ver, true );
}
add_action( 'wp_enqueue_scripts', 'flxlm_enqueue_assets' );

/**
 * Favicon, from the final FLX mark (white line art on a navy rounded tile).
 * Only printed when no Site Icon is set in the Customizer, so a Site Icon
 * still wins if someone sets one later. Files live in assets/images/brand/.
 */
function flxlm_favicon_links() {
	if ( has_site_icon() ) {
		return;
	}

	$base = get_template_directory_uri() . '/assets/images/brand/';
	$ver  = rawurlencode( FLXLM_VERSION );

	echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $base . 'favicon.svg?ver=' . $ver ) . '" />' . "\n";
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $base . 'favicon-32.png?ver=' . $ver ) . '" />' . "\n";
	echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $base . 'apple-touch-icon.png?ver=' . $ver ) . '" />' . "\n";
}
add_action( 'wp_head', 'flxlm_favicon_links' );

/**
 * Enqueue Leaflet and station map assets on station pages only.
 */
function flxlm_enqueue_station_assets() {
	if ( ! is_page_template( 'page-templates/template-station.php' ) ) {
		return;
	}

	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

	$map_ver = filemtime( get_template_directory() . '/assets/js/station-map.js' );
	wp_enqueue_script( 'flxlm-station-map', get_template_directory_uri() . '/assets/js/station-map.js', array( 'leaflet' ), $map_ver, true );

	$page_slug    = get_post_field( 'post_name', get_post() );
	$station_data = flxlm_get_station_data( $page_slug );

	$station_colors = array(
		'WNYR' => '#E91E63',
		'WFLK' => '#FFC107',
		'WLLW' => '#F44336',
		'WFLR' => '#4CAF50',
		'WGVA' => '#2196F3',
		'WAUB' => '#1565C0',
		'WCGR' => '#00897B',
	);

	if ( $station_data ) {
		$call = $station_data['call_sign'];
		wp_localize_script( 'flxlm-station-map', 'flxlmStation', array(
			'callSign'            => $call,
			'color'               => isset( $station_colors[ $call ] ) ? $station_colors[ $call ] : '#512DA8',
			'contoursUrl'         => get_template_directory_uri() . '/assets/data/contours.geojson',
			'contoursExtendedUrl' => get_template_directory_uri() . '/assets/data/contours-extended.geojson',
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'flxlm_enqueue_station_assets' );

// Include CPT, helpers, forms, SEO.
require_once get_template_directory() . '/inc/cpt-testimonials.php';
require_once get_template_directory() . '/inc/cpt-jobs.php';
require_once get_template_directory() . '/inc/careers-blocks.php';
require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/hidden-testimonials.php';
require_once get_template_directory() . '/inc/forms.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/disable-block-editor.php';
