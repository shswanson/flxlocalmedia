<?php
/**
 * Template Name: About
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">About FLX Local Media</h1>
		<p class="page-header__subtitle">Local roots. Total market reach.</p>
	</div>
</div>

<!-- Story -->
<section class="section">
	<div class="container">
		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<h2>Our Story</h2>
			<p>FLX Local Media is a full-service marketing company rooted in the Finger Lakes region. We combine the trusted reach of 7 radio stations with modern digital marketing to help local businesses grow.</p>
			<p>We know this market because we live in it. Our team is in Geneva, Canandaigua, and Penn Yan — attending the same events, shopping at the same stores, and supporting the same communities as your customers.</p>
			<p>That local knowledge, combined with professional marketing expertise, is what sets us apart from agencies that "cover" the Finger Lakes from an office in Rochester or Syracuse.</p>
		</div>
	</div>
</section>

<!-- Market Coverage -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Market Coverage</h2>
			<p class="section__subtitle">7 stations across the Finger Lakes region, serving Ontario, Yates, Seneca, Wayne, and Schuyler counties.</p>
		</div>

		<?php get_template_part( 'template-parts/stats-row' ); ?>

		<div class="station-grid" style="margin-top: var(--space-2xl);">
			<?php
			$stations = flxlm_get_stations();
			foreach ( $stations as $station ) :
				flxlm_station_card( $station );
			endforeach;
			?>
		</div>
	</div>
</section>

<!-- Team -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Our Team</h2>
			<p class="section__subtitle">Marketing professionals who know the Finger Lakes market.</p>
		</div>
		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<p>Our team of marketing professionals brings together decades of experience in radio, digital, events, and content marketing — all focused on the Finger Lakes region.</p>
			<!-- Team member cards will be added when content is provided -->
		</div>
	</div>
</section>

<!-- Testimonials -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Don't Take Our Word for It</h2>
		</div>
		<?php
		$testimonials = flxlm_get_testimonials( array( 'posts_per_page' => 3 ) );
		if ( $testimonials->have_posts() ) :
		?>
			<div class="testimonial-grid">
				<?php while ( $testimonials->have_posts() ) : $testimonials->the_post(); ?>
					<?php get_template_part( 'template-parts/testimonial-card' ); ?>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
