<?php
/**
 * Template Name: Resources
 *
 * Content hub landing page.
 *
 * @package flxlm
 */

// Resources page is hidden until content is ready.
wp_safe_redirect( home_url( '/' ), 302 );
exit;

flxlm_pg_begin( array(
	'accent'  => 'navy',
	'eyebrow' => 'Resources',
	'title'   => 'Resources',
	'lead'    => 'Guides, tools, and insights to help you market smarter in the Finger Lakes.',
) );
get_header();
flxlm_pg_open();
?>

<!-- Featured Resources -->
<section class="section">
	<div class="container">
		<?php if ( wp_count_posts( 'flxlm_testimonial' )->publish > 0 ) : ?>
			<div class="resources-featured">
				<div class="resource-card">
					<span class="resource-card__tag">Client Stories</span>
					<h2 class="resource-card__title">Video Testimonials</h2>
					<p class="resource-card__desc">Watch Finger Lakes business owners share how radio, digital, and multi-channel marketing helped them grow.</p>
					<a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>" class="flx-btn">Watch Stories</a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<!-- Latest Blog Posts -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Latest from the Blog</h2>
		</div>
		<?php
		$posts = new WP_Query( array(
			'posts_per_page' => 3,
			'post_status'    => 'publish',
		) );
		if ( $posts->have_posts() ) :
		?>
			<div class="archive-grid">
				<?php while ( $posts->have_posts() ) : $posts->the_post(); ?>
					<article class="archive-card">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="archive-card__image">
								<?php the_post_thumbnail( 'medium_large' ); ?>
							</div>
						<?php endif; ?>
						<div class="archive-card__body">
							<h3 class="archive-card__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h3>
							<p class="archive-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php else : ?>
			<p class="text-center" style="color: var(--color-text-muted, #64748b);">Blog posts coming soon.</p>
		<?php endif; ?>
	</div>
</section>

<?php
flxlm_pg_close();
get_footer();

