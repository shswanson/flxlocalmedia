<?php
/**
 * Default page template.
 *
 * @package flxlm
 */

flxlm_pg_begin( array(
	'accent'  => 'navy',
	'eyebrow' => 'FLX Local Media',
	'title'   => get_the_title( get_queried_object_id() ),
	'cta'     => false,
) );
get_header();
flxlm_pg_open();

while ( have_posts() ) : the_post();
?>

<section class="section">
	<div class="container">
		<div class="post-content">
			<?php the_content(); ?>
		</div>
	</div>
</section>

<?php endwhile; ?>

<?php
flxlm_pg_close();
get_footer();
