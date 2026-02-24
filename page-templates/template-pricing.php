<?php
/**
 * Template Name: Pricing Guide
 *
 * Unlisted page — noindex, nofollow. Accessed via UUID slug.
 *
 * @package flxlm
 */

// Block indexing.
add_action( 'wp_head', function() {
	echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
}, 1 );

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
			<p style="color: var(--color-gray-500); margin-bottom: var(--space-lg);">Ready to get started?</p>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn--primary btn--lg">Contact Us</a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
