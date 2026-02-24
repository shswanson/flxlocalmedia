<?php
/**
 * Single post template.
 *
 * @package flxlm
 */

get_header();

while ( have_posts() ) : the_post();
?>

<article class="section">
	<div class="container">
		<div class="post-content">
			<header>
				<h1><?php the_title(); ?></h1>
				<p class="post-meta">
					<?php echo esc_html( get_the_date() ); ?>
					<?php if ( get_the_category_list() ) : ?>
						&middot; <?php the_category( ', ' ); ?>
					<?php endif; ?>
				</p>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div style="margin: var(--space-xl) 0;">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>

			<?php the_content(); ?>
		</div>
	</div>
</article>

<?php endwhile; ?>

<?php get_footer(); ?>
