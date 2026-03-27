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
		<p class="page-header__subtitle">Full-service digital marketing for Finger Lakes businesses — websites, SEO, video, social, and more.</p>
	</div>
</div>

<!-- Intro / Value Prop -->
<section class="section">
	<div class="container">
		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<p>FLX Digital is now part of FLX Local Media. Same team, same local expertise — now backed by the full reach of seven radio stations and Finger Lakes Daily News. We build customized marketing plans that combine web design, SEO, social media, targeted display, Google Ads, professional video production, and video targeting campaigns.</p>
			<p>Our digital team has over a decade of experience helping local businesses get found, get chosen, and grow. We serve the Finger Lakes, Rochester, and Syracuse regions from our offices in Geneva, Auburn, and Penn Yan.</p>
		</div>
	</div>
</section>

<!-- Services Grid -->
<section class="section section--wash">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow">Our Services</span>
			<h2 class="section__title">Everything You Need to Compete Online</h2>
		</div>

		<div class="services-grid">
			<div class="service-item">
				<h3 class="service-item__title">Website Design</h3>
				<p class="service-item__desc">Responsive, mobile-first websites built for conversion. Lead-capturing features let visitors schedule appointments, request quotes, and connect with your business through a backend CRM.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Search Engine Optimization</h3>
				<p class="service-item__desc">On-page, technical, and local SEO to help your business rank when customers are actively searching. We optimize your site, build local listings across 50+ directories, and track progress monthly.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Google Ads &amp; PPC</h3>
				<p class="service-item__desc">Targeted paid search campaigns that put your business at the top of results for high-intent keywords. We manage bids, write ad copy, and optimize for qualified leads.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Social Media</h3>
				<p class="service-item__desc">Strategic content creation and scheduling across Facebook, Instagram, and LinkedIn. We help you post with a purpose — building awareness, engagement, and loyalty.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Video Production</h3>
				<p class="service-item__desc">Professional video from concept to delivery. Our team handles pre-production strategy, on-site filming, editing, graphics, and targeted distribution across digital channels.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Reputation Management</h3>
				<p class="service-item__desc">Monitor and improve your online reviews and brand presence. We help you build trust with prospective customers before they ever pick up the phone.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Geo-Targeting &amp; Retargeting</h3>
				<p class="service-item__desc">Reach customers based on their physical location with mobile geo-targeting, and stay in front of past website visitors with retargeting campaigns.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Display Advertising</h3>
				<p class="service-item__desc">Banner ads on Finger Lakes Daily News and partner sites, reaching local audiences where they already read and engage.</p>
			</div>
			<div class="service-item">
				<h3 class="service-item__title">Content Marketing</h3>
				<p class="service-item__desc">Blog posts, SEO content, and sponsored articles that build your authority online and keep search engines seeing you as active and relevant.</p>
			</div>
		</div>
	</div>
</section>

<!-- Stats -->
<section class="section section--dark">
	<div class="container">
		<div class="stat-cards">
			<div class="stat-card">
				<div class="stat-card__ring" style="--stat-pct: 94;">
					<svg viewBox="0 0 120 120" class="stat-card__svg">
						<circle cx="60" cy="60" r="52" class="stat-card__track"/>
						<circle cx="60" cy="60" r="52" class="stat-card__fill"/>
					</svg>
					<span class="stat-card__number">94<span>%</span></span>
				</div>
				<p class="stat-card__label">of smartphone users search for local business info on their device</p>
			</div>
			<div class="stat-card">
				<div class="stat-card__ring" style="--stat-pct: 85;">
					<svg viewBox="0 0 120 120" class="stat-card__svg">
						<circle cx="60" cy="60" r="52" class="stat-card__track"/>
						<circle cx="60" cy="60" r="52" class="stat-card__fill"/>
					</svg>
					<span class="stat-card__number">85<span>%</span></span>
				</div>
				<p class="stat-card__label">of consumers search online before choosing a local business</p>
			</div>
			<div class="stat-card">
				<div class="stat-card__ring" style="--stat-pct: 86;">
					<svg viewBox="0 0 120 120" class="stat-card__svg">
						<circle cx="60" cy="60" r="52" class="stat-card__track"/>
						<circle cx="60" cy="60" r="52" class="stat-card__fill"/>
					</svg>
					<span class="stat-card__number">86<span>%</span></span>
				</div>
				<p class="stat-card__label">increase in conversions when landing pages include video</p>
			</div>
		</div>
	</div>
</section>

<!-- Why FLX Digital -->
<section class="section section--wash">
	<div class="container">
		<div class="section__header section__header--center">
			<h2 class="section__title">Why Local Businesses Choose Us</h2>
		</div>

		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<p>Most digital agencies work remotely for clients they'll never meet. We're different — our offices are in Geneva, Auburn, and Penn Yan because that's where our clients are. We know the Finger Lakes market because we live and work here.</p>
			<p>As part of FLX Local Media, your digital campaigns can work alongside radio, events, and content marketing on Finger Lakes Daily News. That integrated approach means your message reaches customers from multiple angles — online search, social feeds, local news, and the radio they listen to every day.</p>
			<p>Every client gets reporting and analytics so you can see exactly what's working. No black boxes, no vanity metrics — just clear results and a local team you can call anytime.</p>
		</div>
	</div>
</section>

<!-- Digital Testimonials -->
<?php
$testimonials = flxlm_get_testimonials( array(
	'service'        => 'digital',
	'posts_per_page' => 3,
) );
if ( $testimonials->have_posts() ) :
?>
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">What Digital Clients Say</h2>
		</div>
		<div class="testimonial-grid">
			<?php while ( $testimonials->have_posts() ) : $testimonials->the_post(); ?>
				<?php get_template_part( 'template-parts/testimonial-card' ); ?>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
