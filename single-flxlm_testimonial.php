<?php
/**
 * Single Testimonial template.
 *
 * Hero (window card: wide frame, white quote card on top; old poster hero when the
 * hero_* meta is empty), metadata cards, full quote, narrative, transcript, lightbox.
 *
 * @package flxlm
 */

// The window-card hero is light, so the menu bar takes its own strip above it; the old
// poster hero is dark and sits under the bar.
$flxlm_story_id   = (int) get_queried_object_id();
$flxlm_has_window = get_post_meta( $flxlm_story_id, 'hero_wide', true ) && get_post_meta( $flxlm_story_id, 'hero_portrait', true );

flxlm_pg_begin( array(
	'accent'    => 'navy',
	'hero'      => false,
	'dark_hero' => ! $flxlm_has_window,
) );
get_header();
flxlm_pg_open();

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
	$captions_vtt = flxlm_captions_url( get_the_ID() );
	$poster_jpg = get_post_meta( get_the_ID(), 'poster_jpg', true );
	$poster_webp = get_post_meta( get_the_ID(), 'poster_webp', true );
	$poster     = $poster_webp ? $poster_webp : $poster_jpg;
	$obj_pos    = get_post_meta( get_the_ID(), 'hero_object_position', true );
	$obj_pos    = $obj_pos ? $obj_pos : 'center 35%';
	$img_style  = 'object-position: ' . esc_attr( $obj_pos ) . ';';

	// Window card: wide frame (phone: portrait crop), hero quote, face avatar.
	$hero_wide     = get_post_meta( get_the_ID(), 'hero_wide', true );
	$hero_portrait = get_post_meta( get_the_ID(), 'hero_portrait', true );
	$hero_quote    = get_post_meta( get_the_ID(), 'hero_quote', true );
	$hero_duration = get_post_meta( get_the_ID(), 'hero_duration', true );
	$has_window    = $hero_wide && $hero_portrait;
	$hero_name     = ( 'owner' === strtolower( trim( (string) $person ) ) && $business ) ? trim( explode( ' / ', $business )[0] ) . ' Owner' : $person;
	$webp_of       = function ( $rel ) {
		$webp = preg_replace( '/\.jpe?g$/i', '.webp', $rel );
		return ( $webp !== $rel && is_readable( get_template_directory() . '/' . ltrim( $webp, '/' ) ) ) ? flxlm_asset_url( $webp ) : '';
	};
	$tag_bits      = array_filter( array( $industry, $products ) );
	$back_url      = get_post_type_archive_link( 'flxlm_testimonial' ) ?: home_url( '/testimonials/' );
?>

<?php if ( $has_window ) : ?>
<!-- Hero: window card -->
<section class="story-win">
	<a href="<?php echo esc_url( $back_url ); ?>" class="story-win__back">&larr; All Client Stories</a>
	<h1 class="sr-only"><?php echo esc_html( $business ? $business : $person ); ?>: client story</h1>
	<div class="story-win__stage">
		<div class="story-win__window">
			<picture>
				<?php if ( $webp_of( $hero_portrait ) ) : ?><source media="(max-width: 767px)" type="image/webp" srcset="<?php echo esc_url( $webp_of( $hero_portrait ) ); ?>" /><?php endif; ?>
				<source media="(max-width: 767px)" srcset="<?php echo esc_url( flxlm_asset_url( $hero_portrait ) ); ?>" />
				<?php if ( $webp_of( $hero_wide ) ) : ?><source type="image/webp" srcset="<?php echo esc_url( $webp_of( $hero_wide ) ); ?>" /><?php endif; ?>
				<img src="<?php echo esc_url( flxlm_asset_url( $hero_wide ) ); ?>" alt="<?php echo esc_attr( $hero_name ); ?>, <?php echo esc_attr( $business ); ?>" width="1600" height="900" fetchpriority="high" />
			</picture>
			<?php if ( $tag_bits ) : ?>
				<span class="story-win__tag"><?php echo esc_html( implode( ' · ', $tag_bits ) ); ?></span>
			<?php endif; ?>
			<?php if ( $video_url ) : ?>
				<button class="story-win__play" data-video-lightbox aria-label="<?php printf( esc_attr__( 'Play testimonial from %s', 'flxlm' ), $person ); ?>">
					<svg width="24" height="28" viewBox="0 0 28 32" fill="none" aria-hidden="true"><polygon points="4,2 26,16 4,30" fill="currentColor"/></svg>
				</button>
				<?php if ( $hero_duration ) : ?><span class="story-win__dur"><?php echo esc_html( $hero_duration ); ?></span><?php endif; ?>
			<?php endif; ?>
		</div>
		<div class="story-win__card">
			<span class="flx-eyebrow">Client story</span>
			<?php if ( $hero_quote ) : ?>
				<?php // The card prints its own outer quote marks, so a quote inside the quote uses single marks. ?>
				<p class="story-win__quote"><?php echo esc_html( str_replace( array( '“', '”' ), array( '‘', '’' ), $hero_quote ) ); ?></p>
			<?php endif; ?>
			<div class="story-win__by">
				<img src="<?php echo esc_url( flxlm_asset_url( $hero_portrait ) ); ?>" alt="" width="64" height="64" />
				<div>
					<b><?php echo esc_html( $hero_name ); ?></b>
					<span><?php echo esc_html( $title_role ); ?>, <?php echo esc_html( $business ); ?></span>
				</div>
			</div>
			<?php if ( $video_url ) : ?>
				<button class="flx-btn" data-video-lightbox aria-label="<?php printf( esc_attr__( 'Play testimonial from %s', 'flxlm' ), $person ); ?>">
					<svg width="18" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true"><polygon points="3,1 14,8 3,15" fill="currentColor"/></svg>
					Watch the full story<?php if ( $hero_duration ) : ?> <small><?php echo esc_html( $hero_duration ); ?></small><?php endif; ?>
				</button>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php else : ?>
<!-- Hero -->
<section class="story-hero">
	<?php if ( $poster ) : ?>
		<!-- Mobile: clean image with centered play button -->
		<div class="story-hero__image-block">
			<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $person ); ?>" style="<?php echo $img_style; ?>" width="1920" height="1080" />
			<?php if ( $video_url ) : ?>
				<button class="story-hero__image-play" data-video-lightbox aria-label="<?php printf( esc_attr__( 'Play testimonial from %s', 'flxlm' ), $person ); ?>">
					<svg width="24" height="28" viewBox="0 0 28 32" fill="none" aria-hidden="true"><polygon points="4,2 26,16 4,30" fill="currentColor"/></svg>
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

		<h1 class="sr-only"><?php echo esc_html( $business ? $business : $person ); ?>: client story</h1>

		<?php if ( $quote ) : ?>
			<p class="story-hero__quote"><?php echo esc_html( $quote ); ?></p>
		<?php endif; ?>

		<div class="story-hero__name"><?php echo esc_html( $person ); ?></div>
		<div class="story-hero__role"><?php echo esc_html( $title_role ); ?>, <?php echo esc_html( $business ); ?></div>

		<?php if ( $video_url ) : ?>
			<button class="flx-btn" data-video-lightbox aria-label="<?php printf( esc_attr__( 'Play testimonial from %s', 'flxlm' ), $person ); ?>">
				<svg width="18" height="20" viewBox="0 0 16 16" fill="none" aria-hidden="true"><polygon points="3,1 14,8 3,15" fill="currentColor"/></svg>
				Watch the Full Story
			</button>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

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

<?php if ( $has_window && $quote ) : ?>
<!-- The longer quote, in the client's own words -->
<section class="section">
	<div class="container">
		<blockquote class="story-win__full"><?php echo esc_html( $quote ); ?></blockquote>
	</div>
</section>
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
			<button class="transcript__toggle flx-btn flx-btn--ghost flx-btn--sm">Show Full Transcript</button>
			<div class="transcript__content">
				<?php echo wp_kses( $transcript, array( 'p' => array(), 'strong' => array(), 'br' => array(), 'em' => array() ) ); ?>
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
			<?php if ( $captions_vtt ) : ?>
				<track kind="captions" src="<?php echo esc_url( $captions_vtt ); ?>" srclang="en" label="English" default />
			<?php endif; ?>
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
	'post__not_in'   => flxlm_exclude_hidden( array( (int) $current_id ) ),
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

<?php
flxlm_pg_close();
get_footer();

