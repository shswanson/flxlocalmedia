<?php
/**
 * Template Name: Solutions — Events
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Event Marketing</h1>
		<p class="page-header__subtitle">Put your brand in front of thousands with live events and sponsorships.</p>
	</div>
</div>

<section class="section">
	<div class="container">
		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<h2>Live Remotes &amp; On-Site Events</h2>
			<p>Nothing beats a face-to-face connection. Our live remotes bring radio personalities to your location, drawing foot traffic and creating buzz that digital channels can't replicate.</p>

			<h2>Community Event Sponsorships</h2>
			<p>The Finger Lakes region has dozens of festivals, fairs, and community events every year. We help you find the right sponsorship opportunities that align your brand with the community.</p>

			<h2>Custom Events</h2>
			<p>Grand openings, anniversary celebrations, customer appreciation days — we help you plan and promote events that drive business results.</p>

			<h2>The Multi-Channel Advantage</h2>
			<p>Events work best when amplified across radio, digital, and content channels. A live remote with on-air promotion, social media coverage, and a recap article on Finger Lakes Daily News creates a marketing moment that lasts well beyond the event itself.</p>
		</div>
	</div>
</section>

<!-- Event Testimonials -->
<?php
$testimonials = flxlm_get_testimonials( array(
	'service'        => 'events',
	'posts_per_page' => 2,
) );
if ( $testimonials->have_posts() ) :
?>
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Event Marketing in Action</h2>
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
