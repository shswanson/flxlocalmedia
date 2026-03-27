<?php
/**
 * Template Name: Solutions — Content Marketing
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Content Marketing</h1>
		<p class="page-header__subtitle">Reach 700K+ monthly readers on Finger Lakes Daily News.</p>
	</div>
</div>

<section class="section">
	<div class="container">
		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<h2>Sponsored Articles</h2>
			<p>Your story, told through professionally written articles on Finger Lakes Daily News. Sponsored content gets your brand in front of an engaged local audience who trusts the platform.</p>

			<h2>Native Advertising</h2>
			<p>In-feed placements that match the look and feel of editorial content. Native ads outperform traditional display by 5-10x because readers engage with them like regular content.</p>

			<h2>Content Placement</h2>
			<p>Strategic placement of your content alongside related editorial coverage. A restaurant ad next to a dining guide. A home services ad next to a real estate section. Context drives clicks.</p>

			<h2>Video Content</h2>
			<p>Video testimonials, product showcases, and brand stories distributed across our digital properties and social media channels.</p>
		</div>
	</div>
</section>

<!-- Content Testimonials -->
<?php
$testimonials = flxlm_get_testimonials( array(
	'service'        => 'content',
	'posts_per_page' => 2,
) );
if ( $testimonials->have_posts() ) :
?>
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Content Marketing Results</h2>
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
