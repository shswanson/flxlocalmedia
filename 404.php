<?php
/**
 * 404 template.
 *
 * @package flxlm
 */

get_header();
?>

<section class="error-404">
	<div class="container">
		<div class="error-404__code">404</div>
		<h1 class="error-404__title">Page Not Found</h1>
		<p class="error-404__text">The page you're looking for doesn't exist or has been moved. Let's get you back on track.</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary">Go to Homepage</a>
	</div>
</section>

<?php get_footer(); ?>
