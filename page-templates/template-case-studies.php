<?php
/**
 * Template Name: Case Studies
 *
 * Index of the published child pages of the current page. Each child is a
 * case study; the card shows its title (linked), a short summary and its
 * featured image if it has one.
 *
 * @package flxlm
 */

get_header();

$parent_id = get_the_ID();

$case_studies = new WP_Query( array(
	'post_type'      => 'page',
	'post_status'    => 'publish',
	'post_parent'    => $parent_id,
	'posts_per_page' => 50,
	'orderby'        => array(
		'menu_order' => 'ASC',
		'title'      => 'ASC',
	),
	'no_found_rows'  => true,
) );
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title"><?php echo esc_html( get_the_title( $parent_id ) ); ?></h1>
		<p class="page-header__subtitle">Real work for Finger Lakes businesses, and what came out of it.</p>
	</div>
</div>

<section class="section">
	<div class="container">
		<?php if ( $case_studies->have_posts() ) : ?>
			<div class="archive-grid case-study-index">
				<?php
				while ( $case_studies->have_posts() ) :
					$case_studies->the_post();

					// Prefer the page excerpt; otherwise use the first paragraph of the content.
					$summary = trim( (string) get_post_field( 'post_excerpt', get_the_ID() ) );
					if ( '' === $summary ) {
						$content = strip_shortcodes( (string) get_post_field( 'post_content', get_the_ID() ) );
						$parts   = preg_split( '/(<\/p>|\r?\n\s*\r?\n)/i', trim( $content ) );
						foreach ( (array) $parts as $part ) {
							$text = trim( wp_strip_all_tags( $part ) );
							if ( '' !== $text ) {
								$summary = $text;
								break;
							}
						}
					}
					$summary = wp_trim_words( wp_strip_all_tags( $summary ), 40, '...' );
				?>
					<article class="archive-card">
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="archive-card__image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
								<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
							</a>
						<?php endif; ?>
						<div class="archive-card__body">
							<h2 class="archive-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<?php if ( '' !== $summary ) : ?>
								<p class="archive-card__excerpt"><?php echo esc_html( $summary ); ?></p>
							<?php endif; ?>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php else : ?>
			<p class="text-center" style="color: var(--color-text-muted, #64748b);">Case studies are on the way.</p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
