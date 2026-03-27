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

<!-- Problems We Solve -->
<section class="section section--wash section--painted-edge" style="background: var(--color-white, #fff);">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow">Sound familiar?</span>
			<h2 class="section__title">The Challenges Local Businesses Tell Us About</h2>
			<p class="section__subtitle">If you've said any of these out loud, you're not alone — and we can help.</p>
		</div>

		<div class="problems-grid">
			<a href="<?php echo esc_url( home_url( '/solutions/radio/' ) ); ?>" class="problem-card">
				<h3 class="problem-card__question">"They're listening to the radio every day. But they're not hearing about me."</h3>
				<p class="problem-card__answer">7 stations, every format. Country fans in the morning, rock listeners at lunch, talk radio on the drive home. Your ad reaches all of them in one buy.</p>
				<span class="problem-card__channel">Radio Advertising</span>
				<span class="problem-card__link">How radio works &#8594;</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/digital/' ) ); ?>" class="problem-card">
				<h3 class="problem-card__question">"People search for what I sell, but they find my competitor first."</h3>
				<p class="problem-card__answer">SEO, Google Ads, social media, reputation management — we handle all of it so you show up when and where it matters.</p>
				<span class="problem-card__channel">Digital Marketing</span>
				<span class="problem-card__link">How digital works &#8594;</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/events/' ) ); ?>" class="problem-card">
				<h3 class="problem-card__question">"I sponsor things but never feel like it moves the needle."</h3>
				<p class="problem-card__answer">Live remotes, community events, and sponsorships that put your brand in front of thousands — with real follow-through, not just a logo on a banner.</p>
				<span class="problem-card__channel">Event Marketing</span>
				<span class="problem-card__link">How events work &#8594;</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/solutions/content-marketing/' ) ); ?>" class="problem-card">
				<h3 class="problem-card__question">"I know I need content but I don't have time to create it."</h3>
				<p class="problem-card__answer">Sponsored articles and native content on Finger Lakes Daily News. We write it, publish it, and put it in front of the readers who matter.</p>
				<span class="problem-card__channel">Content Marketing</span>
				<span class="problem-card__link">How content works &#8594;</span>
			</a>
		</div>
	</div>
</section>

<!-- Testimonials Section -->
<?php
$testimonials = flxlm_get_testimonials( array( 'posts_per_page' => 3 ) );
if ( $testimonials->have_posts() ) :
?>
<section class="section section--wash" style="background: var(--color-white, #fff);">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow">Local businesses, real results</span>
			<h2 class="section__title">Your Neighbors Trust Us With Their Marketing</h2>
			<p class="section__subtitle">Auto dealers, restaurants, insurance agents, flooring companies — hundreds of businesses across the Finger Lakes.</p>
		</div>
		<div class="testimonial-grid">
			<?php while ( $testimonials->have_posts() ) : $testimonials->the_post(); ?>
				<?php get_template_part( 'template-parts/testimonial-card' ); ?>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
		<div class="text-center" style="margin-top: var(--space-2xl);">
			<a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>" class="btn btn--secondary">See All Client Stories</a>
		</div>
	</div>
</section>
<?php endif; ?>

<?php
get_footer();
