<?php
/**
 * Homepage template.
 *
 * @package flxlm
 */

get_header();

// Hero section.
get_template_part( 'template-parts/hero' );

// Stats row.
get_template_part( 'template-parts/stats-row' );
?>

<!-- Testimonials Section -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">What Our Clients Say</h2>
			<p class="section__subtitle">Don't take our word for it — hear from businesses just like yours.</p>
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
			<div class="text-center" style="margin-top: var(--space-2xl);">
				<a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>" class="btn btn--secondary">See All Client Stories</a>
			</div>
		<?php endif; ?>
	</div>
</section>

<!-- Solutions Section -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">How We Help You Grow</h2>
			<p class="section__subtitle">Four integrated channels. One team. Total market reach.</p>
		</div>

		<div class="solutions-grid">
			<a href="<?php echo esc_url( home_url( '/solutions/radio/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">📻</div>
				<h3 class="solution-card__title">Radio Advertising</h3>
				<p class="solution-card__desc">7 stations reaching every corner of the Finger Lakes. Country, rock, talk, AC — your audience is listening.</p>
				<span class="solution-card__link">Learn more →</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/digital/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">💻</div>
				<h3 class="solution-card__title">Digital Marketing</h3>
				<p class="solution-card__desc">SEO, Google Ads, social media, video production, reputation management — full-service digital under one roof.</p>
				<span class="solution-card__link">Learn more →</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/events/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">🎪</div>
				<h3 class="solution-card__title">Event Marketing</h3>
				<p class="solution-card__desc">Live remotes, sponsorships, and community events that put your brand in front of thousands.</p>
				<span class="solution-card__link">Learn more →</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/content-marketing/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">📝</div>
				<h3 class="solution-card__title">Content Marketing</h3>
				<p class="solution-card__desc">Sponsored articles, native advertising, and content placement on Finger Lakes Daily News.</p>
				<span class="solution-card__link">Learn more →</span>
			</a>
		</div>
	</div>
</section>

<?php
get_footer();
