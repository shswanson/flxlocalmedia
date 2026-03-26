<?php
/**
 * Template Part: Homepage Hero
 *
 * H1, subtitle, CTAs + featured testimonial video on right.
 *
 * @package flxlm
 */

$featured = flxlm_get_testimonials( array(
	'featured'       => true,
	'posts_per_page' => 1,
) );
?>
<section class="hero">
	<div class="container hero__inner">
		<div class="hero__content">
			<h1 class="hero__title">Grow Your Business in the Finger Lakes</h1>
			<p class="hero__subtitle">Radio. Digital. Events. Content. One team with total market reach across the Finger Lakes region.</p>
			<div class="hero__actions">
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn--primary btn--lg">Let's Talk</a>
				<a href="<?php echo esc_url( home_url( '/solutions/' ) ); ?>" class="btn btn--outline btn--lg">Our Solutions</a>
			</div>
		</div>
		<div class="hero__media">
			<?php if ( $featured->have_posts() ) : ?>
				<?php while ( $featured->have_posts() ) : $featured->the_post(); ?>
					<?php flxlm_video_facade( get_the_ID() ); ?>
				<?php endwhile; wp_reset_postdata(); ?>
			<?php else : ?>
				<div class="video-facade">
					<div class="video-facade__placeholder"></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
