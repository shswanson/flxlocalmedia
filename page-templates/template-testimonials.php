<?php
/**
 * Template Name: Testimonials
 *
 * Grid of all testimonial videos.
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Client Stories</h1>
		<p class="page-header__subtitle">Our clients' words, not ours. Hear how Finger Lakes businesses are growing with FLX Local Media.</p>
	</div>
</div>

<section class="section">
	<div class="container">
		<?php
		$testimonials = flxlm_get_testimonials();
		if ( $testimonials->have_posts() ) :
		?>
			<div class="testimonial-grid">
				<?php while ( $testimonials->have_posts() ) : $testimonials->the_post(); ?>
					<?php get_template_part( 'template-parts/testimonial-card' ); ?>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php else : ?>
			<p class="text-center" style="color: var(--color-gray-500);">Testimonial videos coming soon.</p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
