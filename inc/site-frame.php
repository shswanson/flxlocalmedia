<?php
/**
 * Site frame: the part of the site every property shares.
 *
 * One fixed frame (layout, Poppins, spacing, card and button shapes, the glass
 * menu, navy and gold chrome, the gold "Get a free plan" button, the navy
 * footer). Each property fills one colour slot.
 *
 * What lives here:
 *   1. The palette stylesheet (assets/css/flx-tokens.css), enqueued first on every page.
 *   2. The body class contract for the glass menu (flxlm-dark-hero).
 *   3. The footer's SBA badge details.
 *
 * BODY CLASS CONTRACT
 * -------------------
 * The glass menu floats over the top of the page. It has two looks:
 *
 *   body.flxlm-dark-hero   The page opens with a dark or colour hero. The bar is
 *                          navy glass with the white logo, and it overlaps the top
 *                          of the hero (the hero starts at the very top of the
 *                          page, under the bar). The hero must leave room for the
 *                          bar: padding-top of calc( var(--header-height) + 40px ).
 *   (no class)             Light glass with the navy logo. The bar sits in its own
 *                          strip above the content and takes its own space.
 *
 * The class is added for: the front page, the Station template, the Product
 * template, the Solutions: Radio hub and the Testimonials (Proof) page.
 *
 * A template with its own dark hero adds itself with either:
 *   - the filter:  add_filter( 'flxlm_dark_hero_templates', fn( $t ) => array_merge( $t, array( 'page-templates/template-x.php' ) ) );
 *   - or a direct body_class filter that appends 'flxlm-dark-hero'.
 * Any .page-header (the navy title band) on a page with this class also clears the bar.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Palette tokens, before every other stylesheet. Priority 1 puts the handle first
 * in the queue, ahead of the theme's own styles (priority 10) and the site layers (20).
 */
function flxlm_frame_enqueue_tokens() {
	$file = get_template_directory() . '/assets/css/flx-tokens.css';
	wp_enqueue_style(
		'flxlm-tokens',
		get_template_directory_uri() . '/assets/css/flx-tokens.css',
		array(),
		is_readable( $file ) ? (int) filemtime( $file ) : FLXLM_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'flxlm_frame_enqueue_tokens', 1 );

/**
 * Make sure the theme's main stylesheet waits for the tokens, whatever order
 * other code enqueues things in.
 */
function flxlm_frame_style_deps() {
	$styles = wp_styles();
	if ( isset( $styles->registered['flxlm-style'] ) && ! in_array( 'flxlm-tokens', $styles->registered['flxlm-style']->deps, true ) ) {
		$styles->registered['flxlm-style']->deps[] = 'flxlm-tokens';
	}
}
add_action( 'wp_enqueue_scripts', 'flxlm_frame_style_deps', 11 );

/**
 * Templates whose first screen is a dark or colour hero.
 *
 * @return string[] Template file paths, as is_page_template() takes them.
 */
function flxlm_frame_dark_hero_templates() {
	$templates = array(
		'page-templates/template-station.php',
		'page-templates/template-product.php',
		'page-templates/template-solutions-radio.php',
		'page-templates/template-testimonials.php',
	);
	$templates = apply_filters( 'flxlm_dark_hero_templates', $templates );
	return is_array( $templates ) ? array_values( array_filter( array_map( 'strval', $templates ) ) ) : array();
}

/**
 * Add flxlm-dark-hero to the body on pages that open with a dark hero.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function flxlm_frame_body_class( $classes ) {
	$dark = is_front_page() || is_page_template( flxlm_frame_dark_hero_templates() );
	if ( $dark && ! in_array( 'flxlm-dark-hero', $classes, true ) ) {
		$classes[] = 'flxlm-dark-hero';
	}
	return $classes;
}
add_filter( 'body_class', 'flxlm_frame_body_class' );

/**
 * Footer badge details. products.php defines the badge constants before this
 * file loads, so the right link and alt text are set here through the filter
 * that flxlm_sdvosb_badge() already runs. The image itself is shown as supplied.
 *
 * @param array|null $badge src, url, alt.
 * @return array|null
 */
function flxlm_frame_sdvosb_badge( $badge ) {
	if ( ! is_array( $badge ) || empty( $badge['src'] ) ) {
		return $badge;
	}
	$badge['url'] = 'https://search.certifications.sba.gov/profile/W1J2NFT4ZTH9';
	$badge['alt'] = 'TOTIB Media Inc, doing business as FLX Local Media, is an SBA-certified Service-Disabled Veteran-Owned Small Business.';
	return $badge;
}
add_filter( 'flxlm_sdvosb_badge', 'flxlm_frame_sdvosb_badge', 20 );
