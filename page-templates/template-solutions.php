<?php
/**
 * Template Name: Solutions Overview
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Marketing Solutions</h1>
		<p class="page-header__subtitle">Four integrated channels to reach every customer in the Finger Lakes region.</p>
	</div>
</div>

<section class="section">
	<div class="container">
		<div class="solutions-grid">
			<a href="<?php echo esc_url( home_url( '/solutions/radio/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">📻</div>
				<h3 class="solution-card__title">Radio Advertising</h3>
				<p class="solution-card__desc">7 stations reaching every corner of the Finger Lakes. Country, rock, talk, AC — your audience is listening. Multi-station packages for maximum reach.</p>
				<span class="solution-card__link">Explore radio →</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/digital/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">💻</div>
				<h3 class="solution-card__title">Digital Marketing</h3>
				<p class="solution-card__desc">Full-service digital marketing: SEO, Google Ads, social media, video production, reputation management, SMS marketing, and more.</p>
				<span class="solution-card__link">Explore digital →</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/events/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">🎪</div>
				<h3 class="solution-card__title">Event Marketing</h3>
				<p class="solution-card__desc">Live remotes, sponsorships, and community events that create face-to-face connections with your customers.</p>
				<span class="solution-card__link">Explore events →</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/content-marketing/' ) ); ?>" class="solution-card">
				<div class="solution-card__icon">📝</div>
				<h3 class="solution-card__title">Content Marketing</h3>
				<p class="solution-card__desc">Sponsored articles, native advertising, and content placement on Finger Lakes Daily News — 700K+ monthly pageviews.</p>
				<span class="solution-card__link">Explore content →</span>
			</a>
		</div>
	</div>
</section>

<!-- Testimonial section -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Our Clients' Results</h2>
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
