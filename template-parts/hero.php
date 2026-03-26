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
			<p class="hero__problem">Local Solutions for Local Businesses</p>
			<h1 class="hero__title">Your customers drive 30&nbsp;minutes a day across the Finger&nbsp;Lakes. What are they hearing about&nbsp;you?</h1>
			<p class="hero__subtitle">Most local businesses advertise in one place and hope for the best. We put you everywhere your customers already are — on 7 radio stations, across digital, at community events, and in the local news.</p>
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
				<img
					class="hero__image"
					src="https://images.unsplash.com/photo-1506377247377-2a5b3b417ebb?w=1200&q=80"
					alt="Finger Lakes vineyard overlooking the water"
					width="1200"
					height="800"
					loading="eager"
				/>
			<?php endif; ?>
		</div>
	</div>
</section>
