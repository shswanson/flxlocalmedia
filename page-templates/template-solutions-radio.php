<?php
/**
 * Template Name: Solutions — Radio
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Radio Advertising</h1>
		<p class="page-header__subtitle">7 stations. Every format. Total Finger Lakes coverage.</p>
	</div>
</div>

<!-- Market Reach -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Our Stations</h2>
			<p class="section__subtitle">From country to classic rock, talk to AC — we reach every listener in the market.</p>
		</div>

		<div class="station-grid">
			<?php
			$stations = flxlm_get_stations();
			foreach ( $stations as $station ) :
				flxlm_station_card( $station );
			endforeach;
			?>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/stats-row' ); ?>

<!-- Better Together -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Better Together</h2>
			<p class="section__subtitle">Multi-station packages deliver cross-format reach. Your message hits country fans in the morning, rock listeners at lunch, and news audiences in the evening.</p>
		</div>

		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<p>Most businesses advertise on one station and hope for the best. Our clients advertise across formats — because different customers listen to different stations at different times.</p>
			<p>A multi-station package means your ad reaches a country music fan driving to work at 7 AM, a classic rock listener on lunch break at noon, and a talk radio audience heading home at 5 PM. Same message, three audiences, one buy.</p>
			<p>That's how our clients consistently outperform single-station advertisers in the market.</p>
		</div>
	</div>
</section>

<!-- Radio Testimonials -->
<?php
$testimonials = flxlm_get_testimonials( array(
	'service'        => 'radio',
	'posts_per_page' => 3,
) );
if ( $testimonials->have_posts() ) :
?>
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">What Radio Advertisers Say</h2>
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
