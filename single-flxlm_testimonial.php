<?php
/**
 * Single Testimonial template.
 *
 * Full video + hero pull quote + collapsible transcript + "More Stories".
 *
 * @package flxlm
 */

get_header();

while ( have_posts() ) : the_post();
	$person     = get_post_meta( get_the_ID(), 'person_name', true );
	$title      = get_post_meta( get_the_ID(), 'person_title', true );
	$business   = get_post_meta( get_the_ID(), 'business_name', true );
	$industry   = get_post_meta( get_the_ID(), 'industry', true );
	$quote      = get_post_meta( get_the_ID(), 'quote_full', true );
	$transcript = get_post_meta( get_the_ID(), 'transcript_full', true );
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title"><?php echo esc_html( $person ); ?> — <?php echo esc_html( $business ); ?></h1>
		<?php if ( $industry ) : ?>
			<p class="page-header__subtitle"><?php echo esc_html( $industry ); ?></p>
		<?php endif; ?>
	</div>
</div>

<section class="section">
	<div class="container">
		<!-- Full video -->
		<?php flxlm_video_facade( get_the_ID() ); ?>

		<!-- Pull quote -->
		<?php if ( $quote ) : ?>
			<blockquote class="testimonial-full__quote">
				<?php echo esc_html( $quote ); ?>
			</blockquote>
			<div class="testimonial-full__cite">
				<span class="testimonial-full__name"><?php echo esc_html( $person ); ?></span>
				<?php if ( $title ) : ?>
					<span class="testimonial-full__title"><?php echo esc_html( $title ); ?></span>
				<?php endif; ?>
				<span class="testimonial-full__business"><?php echo esc_html( $business ); ?></span>
			</div>
		<?php endif; ?>

		<!-- Post content (business context) -->
		<?php if ( get_the_content() ) : ?>
			<div class="post-content" style="margin-top: var(--space-2xl);">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<!-- Transcript -->
		<?php if ( $transcript ) : ?>
			<div class="transcript">
				<button class="transcript__toggle">Show Full Transcript</button>
				<div class="transcript__content">
					<?php echo nl2br( esc_html( $transcript ) ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<!-- More Stories -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">More Client Stories</h2>
		</div>
		<?php
		$more = flxlm_get_testimonials( array(
			'posts_per_page' => 3,
			'post__not_in'   => array( get_the_ID() ),
			'orderby'        => 'rand',
		) );
		if ( $more->have_posts() ) :
		?>
			<div class="testimonial-grid">
				<?php while ( $more->have_posts() ) : $more->the_post(); ?>
					<?php get_template_part( 'template-parts/testimonial-card' ); ?>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php endwhile; ?>

<?php get_footer(); ?>
