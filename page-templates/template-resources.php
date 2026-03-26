<?php
/**
 * Template Name: Resources
 *
 * Content hub landing page.
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Resources</h1>
		<p class="page-header__subtitle">Guides, tools, and insights to help you market smarter in the Finger Lakes.</p>
	</div>
</div>

<!-- Featured Resources -->
<section class="section">
	<div class="container">
		<div class="resources-featured">
			<div class="resource-card">
				<span class="resource-card__tag">Client Stories</span>
				<h2 class="resource-card__title">Video Testimonials</h2>
				<p class="resource-card__desc">Watch Finger Lakes business owners share how radio, digital, and multi-channel marketing helped them grow.</p>
				<a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>" class="btn btn--primary">Watch Stories</a>
			</div>
		</div>
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
			<p class="text-center" style="color: var(--color-gray-500);">Blog posts coming soon.</p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
