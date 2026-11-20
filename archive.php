<?php
/**
 * Archive template.
 *
 * @package flxlm
 */

flxlm_pg_begin( array(
	'accent'    => 'navy',
	'eyebrow'   => 'Archive',
	'title'     => wp_strip_all_tags( get_the_archive_title() ),
	'lead_html' => wp_strip_all_tags( get_the_archive_description() ),
	'cta'       => false,
) );
get_header();
flxlm_pg_open();
?>

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
			<p class="text-center" style="color: var(--color-text-muted);">No posts found.</p>
		<?php endif; ?>
	</div>
</section>

<?php
flxlm_pg_close();
get_footer();

