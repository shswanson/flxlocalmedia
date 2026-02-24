<?php
/**
 * Default page template.
 *
 * @package flxlm
 */

get_header();

while ( have_posts() ) : the_post();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title"><?php the_title(); ?></h1>
	</div>
</div>

<section class="section">
	<div class="container">
		<div class="post-content">
			<?php the_content(); ?>
		</div>
	</div>
</section>

<?php endwhile; ?>

<?php get_footer(); ?>
