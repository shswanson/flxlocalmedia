<?php
/**
 * Template Name: Case Study: Contests Built an Email List
 *
 * Standalone case study page covering how radio contests built the Finger Lakes Daily News email list.
 * Content lives in case-study-contests-email-content.html in the theme root.
 *
 * @package flxlm
 */

flxlm_pg_begin( array(
	'accent'         => 'navy',
	'hero'           => false,
	'dark_hero'      => true,
	'band'           => false,
	'no_footer_band' => true,
) );
get_header();
flxlm_pg_open();

$html_path = get_template_directory() . '/case-study-contests-email-content.html';
if ( file_exists( $html_path ) ) {
	echo '<div class="cs-wrap">';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static HTML file
	echo str_replace( '{{THEME_URI}}', esc_url( get_template_directory_uri() ), file_get_contents( $html_path ) );
	echo '</div>';
} else {
	echo '<div class="container" style="padding:4rem 0;text-align:center;">';
	echo '<h1>Case Study</h1><p>Content file not found.</p>';
	echo '</div>';
}

flxlm_pg_close();
get_footer();
