<?php
/**
 * Template Name: Pricing Guide
 *
 * Unlisted page — noindex, nofollow. Accessed via UUID slug.
 *
 * @package flxlm
 */

// Block indexing, through core's single robots tag (inc/seo.php adds the same pair from the page entry).
add_filter( 'wp_robots', function ( $robots ) {
	$robots['noindex']  = true;
	$robots['nofollow'] = true;
	return $robots;
} );

get_header();
?>

<section class="section">
	<div class="pricing-page">
		<h1>Pricing Guide</h1>
		<p class="pricing-disclaimer">Effective as of <?php echo esc_html( date( 'F Y' ) ); ?>. Pricing is subject to change. Contact us for a custom proposal.</p>

		<div class="post-content">
			<?php the_content(); ?>
		</div>

		<div style="margin-top: var(--space-3xl); text-align: center;">
			<p style="color: var(--color-text-muted, #64748b); margin-bottom: var(--space-lg);">Ready to get started?</p>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="flx-btn">Contact Us</a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
