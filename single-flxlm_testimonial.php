<?php
/**
 * Single Testimonial template.
 *
 * Hero with video poster + quote, metadata cards, narrative, transcript, lightbox.
 *
 * @package flxlm
 */

get_header();

while ( have_posts() ) : the_post();
	$person     = get_post_meta( get_the_ID(), 'person_name', true );
	$title_role = get_post_meta( get_the_ID(), 'person_title', true );
	$business   = get_post_meta( get_the_ID(), 'business_name', true );
	$industry   = get_post_meta( get_the_ID(), 'industry', true );
	$location   = get_post_meta( get_the_ID(), 'location', true );
	$size       = get_post_meta( get_the_ID(), 'company_size', true );
	$products   = get_post_meta( get_the_ID(), 'products_used', true );
	$quote      = get_post_meta( get_the_ID(), 'quote_full', true );
	$transcript = get_post_meta( get_the_ID(), 'transcript_full', true );
	$video_url  = flxlm_get_video_url( get_the_ID() );
	$poster_jpg = get_post_meta( get_the_ID(), 'poster_jpg', true );
	$poster_webp = get_post_meta( get_the_ID(), 'poster_webp', true );
	$poster     = $poster_webp ? $poster_webp : $poster_jpg;
	$obj_pos    = get_post_meta( get_the_ID(), 'hero_object_position', true );
	$obj_pos    = $obj_pos ? $obj_pos : 'center 35%';
	$img_style  = 'object-position: ' . esc_attr( $obj_pos ) . ';';
?>

<!-- Hero -->
<section class="story-hero">
	<?php if ( $poster ) : ?>
		<!-- Mobile: clean image with centered play button -->
		<div class="story-hero__image-block">
			<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $person ); ?>" style="<?php echo $img_style; ?>" width="1920" height="1080" />
			<?php if ( $video_url ) : ?>
				<button class="story-hero__image-play" data-video-lightbox aria-label="<?php printf( esc_attr__( 'Play testimonial from %s', 'flxlm' ), $person ); ?>">
					<svg width="24" height="28" viewBox="0 0 28 32" fill="none" aria-hidden="true"><polygon points="4,2 26,16 4,30" fill="#fff"/></svg>
				</button>
			<?php endif; ?>
		</div>

		<!-- Desktop: background image with gradient fade -->
		<div class="story-hero__bg">
			<img src="<?php echo esc_url( $poster ); ?>" alt="" style="<?php echo $img_style; ?>" width="1920" height="1080" />
		</div>
	<?php endif; ?>

	<!-- Quote + CTA -->
	<div class="story-hero__content">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'flxlm_testimonial' ) ?: home_url( '/testimonials/' ) ); ?>" class="story-hero__back">&larr; All Client Stories</a>

		<?php if ( $quote ) : ?>
			<p class="story-hero__quote"><?php echo esc_html( $quote ); ?></p>
		<?php endif; ?>

		<div class="story-hero__name"><?php echo esc_html( $person ); ?></div>
		<div class="story-hero__role"><?php echo esc_html( $title_role ); ?>, <?php echo esc_html( $business ); ?></div>

		<?php if ( $video_url ) : ?>
			<button class="story-hero__play-cta" data-video-lightbox aria-label="<?php printf( esc_attr__( 'Play testimonial from %s', 'flxlm' ), $person ); ?>">
				<svg width="18" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true"><polygon points="3,1 14,8 3,15" fill="#fff"/></svg>
				Watch the Full Story
			</button>
		<?php endif; ?>
	</div>
</section>

<?php
// Build metadata items.
$meta_items = array();
if ( $industry ) {
	$meta_items[] = array( 'label' => 'Industry', 'value' => $industry );
}
if ( $location ) {
	$meta_items[] = array( 'label' => 'Location', 'value' => $location );
}
if ( $size ) {
	$meta_items[] = array( 'label' => 'Size', 'value' => $size );
}
if ( $products ) {
	$meta_items[] = array( 'label' => 'Products', 'value' => $products );
}

if ( ! empty( $meta_items ) ) : ?>
<!-- Metadata Cards -->
<div class="meta-cards">
	<div class="container">
		<div class="meta-cards__grid meta-cards__grid--<?php echo count( $meta_items ); ?>">
			<?php foreach ( $meta_items as $item ) : ?>
				<div class="meta-cards__card">
					<div class="meta-cards__label"><?php echo esc_html( $item['label'] ); ?></div>
					<div class="meta-cards__value"><?php echo esc_html( $item['value'] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- Post content (narrative) -->
<?php if ( get_the_content() ) : ?>
<section class="section">
	<div class="container">
		<div class="case-study__narrative">
			<?php the_content(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- Transcript -->
<?php if ( $transcript ) : ?>
<section class="section section--cream">
	<div class="container" style="max-width: 800px;">
		<div class="transcript">
			<button class="transcript__toggle">Show Full Transcript</button>
			<div class="transcript__content">
				<?php echo nl2br( esc_html( $transcript ) ); ?>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- Video Lightbox -->
<?php if ( $video_url ) : ?>
<div class="video-lightbox" id="video-lightbox">
	<div class="video-lightbox__inner">
		<button class="video-lightbox__close" aria-label="Close video">&times;</button>
		<video id="lightbox-video" controls preload="none" <?php if ( $poster ) : ?>poster="<?php echo esc_url( $poster ); ?>"<?php endif; ?>>
			<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4" />
		</video>
	</div>
</div>
<?php endif; ?>

<?php
$current_id = get_the_ID();
endwhile;
?>

<!-- More Stories -->
<?php
$more_args = array(
	'post_type'      => 'flxlm_testimonial',
	'posts_per_page' => 3,
	'post__not_in'   => array( (int) $current_id ),
	'orderby'        => 'rand',
	'post_status'    => 'publish',
);
$more_query = new WP_Query( $more_args );
?>
<?php if ( is_object( $more_query ) && $more_query->have_posts() ) : ?>
<section class="section section--warm-gray">
	<div class="container">
		<div class="section__header" style="text-align: center; margin-bottom: var(--space-xl);">
			<h2 class="section__title">More Client Stories</h2>
		</div>
		<div class="testimonial-grid">
			<?php while ( $more_query->have_posts() ) : $more_query->the_post(); ?>
				<?php get_template_part( 'template-parts/testimonial-card' ); ?>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
