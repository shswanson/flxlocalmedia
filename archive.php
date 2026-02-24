<?php
/**
 * Archive template.
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title"><?php the_archive_title(); ?></h1>
		<?php if ( the_archive_description() ) : ?>
			<p class="page-header__subtitle"><?php the_archive_description(); ?></p>
		<?php endif; ?>
	</div>
</div>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="archive-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<article class="archive-card">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="archive-card__image">
								<?php the_post_thumbnail( 'medium_large' ); ?>
							</div>
						<?php endif; ?>
						<div class="archive-card__body">
							<h2 class="archive-card__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<p class="archive-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
						</div>
					</article>
				<?php endwhile; ?>
			</div>

			<div class="pagination">
				<?php
				the_posts_pagination( array(
					'mid_size'  => 2,
					'prev_text' => '&larr;',
					'next_text' => '&rarr;',
				) );
				?>
			</div>
		<?php else : ?>
			<p class="text-center" style="color: var(--color-gray-500);">No posts found.</p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
