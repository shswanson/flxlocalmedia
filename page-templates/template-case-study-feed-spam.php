<?php
/**
 * Template Name: Case Study — Feed Spam Attack
 *
 * Standalone case study page covering the April 2026 feed-spam mitigation.
 * Content lives in case-study-feed-spam-content.html in the theme root.
 *
 * @package flxlm
 */

get_header();
?>

<style>
.cs-wrap { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; color: #1d2327; line-height: 1.6; }
.cs-wrap *, .cs-wrap *::before, .cs-wrap *::after { box-sizing: border-box; }
.cs-wrap img { max-width: 100%; height: auto; }
.cs-wrap a { color: #1E5B70; }
.cs-wrap ul { list-style: disc; }
.cs-wrap .hero h1,
.cs-wrap .hero__title,
.cs-wrap h1 { color: #fff !important; }
.cta-banner { display: none !important; }
</style>

<?php
$html_path = get_template_directory() . '/case-study-feed-spam-content.html';
if ( file_exists( $html_path ) ) {
	echo '<div class="cs-wrap">';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static HTML file
	echo file_get_contents( $html_path );
	echo '</div>';
} else {
	echo '<div class="container" style="padding:4rem 0;text-align:center;">';
	echo '<h1>Case Study</h1><p>Content file not found.</p>';
	echo '</div>';
}

get_footer();
