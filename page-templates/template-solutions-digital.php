<?php
/**
 * Template Name: Solutions — Digital
 *
 * FLX Digital rolled into FLX Local Media brand.
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Digital Marketing</h1>
		<p class="page-header__subtitle">Full-service digital marketing — from SEO to social media, all under one roof.</p>
	</div>
</div>

<!-- Services Grid -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Our Digital Services</h2>
			<p class="section__subtitle">Everything you need to compete online in the Finger Lakes market.</p>
		</div>

		<div class="services-grid">
			<div class="service-item">
				<h3 class="service-item__title">Website Design</h3>
				<p class="service-item__desc">Mobile-first websites built for conversion and local SEO.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">SEO</h3>
				<p class="service-item__desc">Rank higher in local search results. On-page, technical, and content SEO.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Google Ads</h3>
				<p class="service-item__desc">Targeted paid search campaigns that drive qualified leads.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Social Media</h3>
				<p class="service-item__desc">Content creation, scheduling, and paid social across all platforms.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Video Production</h3>
				<p class="service-item__desc">Professional video for social, ads, and your website.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Reputation Management</h3>
				<p class="service-item__desc">Monitor and improve your online reviews and brand presence.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">SMS Marketing</h3>
				<p class="service-item__desc">Direct text message campaigns with high open rates.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Geo-Targeting</h3>
				<p class="service-item__desc">Reach customers based on their location with precision targeting.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Display Advertising</h3>
				<p class="service-item__desc">Banner ads on Finger Lakes Daily News and partner sites.</p>
			</div>
		</div>
	</div>
</section>

<!-- Digital Testimonials -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">What Digital Clients Say</h2>
		</div>
		<?php
		$testimonials = flxlm_get_testimonials( array(
			'service'        => 'digital',
			'posts_per_page' => 2,
		) );
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
