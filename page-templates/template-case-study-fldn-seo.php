<?php
/**
 * Template Name: Case Study: FLDN SEO
 *
 * Standalone case study page using the Helm dashboard design system.
 * The site header/footer provide navigation; content area is self-styled.
 *
 * @package flxlm
 */

get_header();
?>

<style>
/* Override the site's white background for case study sections */
.cs-wrap { font-family: var(--font-body, 'Poppins'), -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; color: #2b3a45; line-height: 1.6; }
.cs-wrap *, .cs-wrap *::before, .cs-wrap *::after { box-sizing: border-box; }
.cs-wrap img { max-width: 100%; height: auto; }
.cs-wrap a { color: var(--color-teal-ink, #077a89); }
.cs-wrap ul { list-style: disc; }
/* Fix hero h1 — site heading styles override to blue; force white in the dark hero */
.cs-wrap .hero h1,
.cs-wrap .hero__title,
.cs-wrap h1 { color: #fff !important; }
/* Hide the site-wide "Ready to grow?" CTA on this page */
.cta-banner { display: none !important; }
</style>

<?php
$html_path = get_template_directory() . '/case-study-fldn-seo-content.html';
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
