<?php
/**
 * 404 template.
 *
 * @package flxlm
 */

flxlm_pg_begin( array(
	'accent'         => 'navy',
	'eyebrow'        => 'Error 404',
	'title'          => 'Page Not Found',
	'lead'           => "The page you're looking for doesn't exist or has been moved. Let's get you back on track.",
	'cta'            => false,
	'band'           => false,
	'no_footer_band' => true,
	'hero'           => true,
) );
get_header();
flxlm_pg_open();
?>

<section class="error-404">
	<div class="container">
		<div class="error-404__code">404</div>
		<p class="error-404__text">Try the homepage, or use the menu above to find what you were looking for.</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary">Go to Homepage</a>
	</div>
</section>

<?php
flxlm_pg_close();
get_footer();

