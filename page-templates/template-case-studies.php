<?php
/**
 * Template Name: Case Studies
 *
 * Index of the published child pages of the current page. Each child is a
 * case study; each card reuses the testimonial card: share image, title, description,
 * three headline numbers from the case study's own stat row, and a link.
 *
 * @package flxlm
 */

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

flxlm_pg_begin( array(
	'accent'  => 'navy',
	'eyebrow' => 'Proof',
	'title'   => get_the_title( $parent_id ),
	'lead'    => 'Real work for Finger Lakes businesses, and what came out of it.',
) );
get_header();
flxlm_pg_open();
?>

<section class="section">
	<div class="container">
		<?php if ( $case_studies->have_posts() ) : ?>
			<?php $seo_copy = function_exists( 'flxlm_seo_case_studies' ) ? flxlm_seo_case_studies() : array(); ?>
			<div class="testimonial-grid">
				<?php
				while ( $case_studies->have_posts() ) :
					$case_studies->the_post();
					$slug = get_post_field( 'post_name', get_the_ID() );
					$raw  = (string) get_post_field( 'post_content', get_the_ID() );

					// The case study bodies live in theme files (see the case study page templates).
					$files = array(
						'fldn-seo'                  => 'case-study-fldn-seo-content.html',
						'feed-spam-case-study'      => 'case-study-feed-spam-content.html',
						'weather-alerts-case-study' => 'case-study-weather-alerts-content.html',
					);
					if ( isset( $files[ $slug ] ) && is_readable( get_template_directory() . '/' . $files[ $slug ] ) ) {
						$raw = (string) file_get_contents( get_template_directory() . '/' . $files[ $slug ] );
					}

					// Description: the case study's own search description; else the page excerpt, else its hero line.
					$summary = isset( $seo_copy[ $slug ]['desc'] ) ? $seo_copy[ $slug ]['desc'] : trim( (string) get_post_field( 'post_excerpt', get_the_ID() ) );
					if ( '' === $summary && preg_match( '#<p class="hero__sub">(.*?)</p>#is', $raw, $m ) ) {
						$summary = wp_strip_all_tags( $m[1] );
					}

					// Headline numbers: the first three tiles of the case study's own hero stat row.
					$results = array();
					if ( preg_match_all( '#<div class="kpi__value[^"]*">(.*?)</div>\s*<div class="kpi__label">(.*?)</div>#is', $raw, $kpis, PREG_SET_ORDER ) ) {
						foreach ( array_slice( $kpis, 0, 3 ) as $k ) {
							$results[] = array( wp_strip_all_tags( html_entity_decode( $k[1] ) ), wp_strip_all_tags( html_entity_decode( $k[2] ) ) );
						}
					}

					// Image: the case study's share image, else its featured image.
					$img_file = get_template_directory() . '/assets/images/og/' . $slug . '.png';
					$img_url  = file_exists( $img_file ) ? get_template_directory_uri() . '/assets/images/og/' . $slug . '.png' : get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
				?>
					<article class="testimonial-card">
						<?php if ( $img_url ) : ?>
							<div class="testimonial-card__image testimonial-card__image--wide">
								<img src="<?php echo esc_url( $img_url ); ?>" alt="" width="1200" height="630" loading="lazy" />
							</div>
						<?php endif; ?>
						<div class="testimonial-card__body">
							<span class="flx-eyebrow">Case study</span>
							<h2 class="archive-card__title"><?php the_title(); ?></h2>
							<?php if ( '' !== $summary ) : ?>
								<p class="archive-card__excerpt"><?php echo esc_html( $summary ); ?></p>
							<?php endif; ?>
							<?php if ( $results ) : ?>
								<div class="testimonial-card__cite">
									<?php foreach ( $results as $r ) : ?>
										<span class="testimonial-card__business"><strong class="testimonial-card__name"><?php echo esc_html( $r[0] ); ?></strong> <?php echo esc_html( $r[1] ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<a href="<?php the_permalink(); ?>" class="testimonial-card__link">Read the case study &rarr;</a>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php else : ?>
			<p class="text-center" style="color: var(--color-text-muted, #64748b);">Case studies are on the way.</p>
		<?php endif; ?>
	</div>
</section>

<?php
flxlm_pg_close();
get_footer();

