<?php
/**
 * Template Name: Case Study — SEO
 *
 * Standalone case study page using the Helm dashboard design system.
 * The site header/footer provide navigation; content area is self-styled.
 *
 * @package flxlm
 */

get_header();

// Pull the static case study HTML from the dashboard repo (or inline it).
// Using a self-contained approach: all styles + charts inline.
?>

<style>
/* Override the site's cream background for case study sections */
.cs-wrap { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; color: #1d2327; line-height: 1.6; }
.cs-wrap *, .cs-wrap *::before, .cs-wrap *::after { box-sizing: border-box; }
.cs-wrap img { max-width: 100%; height: auto; }
.cs-wrap a { color: #1E5B70; }
.cs-wrap ul { list-style: disc; }
</style>

<?php
// Read the static HTML and extract only the body content (between <body> and </body>)
$html_path = get_template_directory() . '/case-study-seo-content.html';
if ( file_exists( $html_path ) ) {
	echo '<div class="cs-wrap">';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted static HTML file
	echo file_get_contents( $html_path );
	echo '</div>';
} else {
	echo '<div class="container" style="padding:4rem 0;text-align:center;">';
	echo '<h1>Case Study</h1><p>Content file not found. Deploy case-study-seo-content.html to the theme directory.</p>';
	echo '</div>';
}

get_footer();
