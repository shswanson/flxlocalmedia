<?php
/**
 * Single post template.
 *
 * @package flxlm
 */

$flxlm_meta = '';
if ( is_singular() ) {
	$flxlm_post = get_queried_object();
	$flxlm_meta = esc_html( get_the_date( '', $flxlm_post ) );
	$flxlm_cats = get_the_category_list( ', ', '', $flxlm_post->ID );
	if ( $flxlm_cats ) {
		$flxlm_meta .= ' &middot; ' . $flxlm_cats;
	}
}

flxlm_pg_begin( array(
	'accent'    => 'navy',
	'eyebrow'   => 'From the blog',
	'title'     => get_the_title( get_queried_object_id() ),
	'lead_html' => $flxlm_meta,
	'cta'       => false,
) );
get_header();
flxlm_pg_open();

while ( have_posts() ) : the_post();
?>

<article class="section">
	<div class="container">
		<div class="post-content">
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

<?php
flxlm_pg_close();
get_footer();
