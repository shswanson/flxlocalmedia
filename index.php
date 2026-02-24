<?php
/**
 * Default template fallback.
 *
 * WordPress requires index.php. In practice, more specific templates
 * (front-page.php, single.php, page.php, archive.php) handle all requests.
 *
 * @package flxlm
 */

get_header();

if ( have_posts() ) :
?>
<section class="section">
	<div class="container">
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
	</div>
</section>
<?php else : ?>
<section class="section">
	<div class="container text-center">
		<p>No content found.</p>
	</div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
