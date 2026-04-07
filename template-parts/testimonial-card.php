<?php
/**
 * Template Part: Testimonial Card
 *
 * Poster image + short quote + business name + link. For grids.
 * Expects global $post to be a flxlm_testimonial.
 *
 * @package flxlm
 */

$person      = get_post_meta( get_the_ID(), 'person_name', true );
$business    = get_post_meta( get_the_ID(), 'business_name', true );
$industry    = get_post_meta( get_the_ID(), 'industry', true );
$quote       = get_post_meta( get_the_ID(), 'quote_short', true );
$poster_jpg  = get_post_meta( get_the_ID(), 'poster_jpg', true );
$poster_webp = get_post_meta( get_the_ID(), 'poster_webp', true );
$poster      = $poster_webp ? $poster_webp : $poster_jpg;
$obj_pos     = get_post_meta( get_the_ID(), 'hero_object_position', true );
$obj_pos     = $obj_pos ? $obj_pos : 'center 35%';
?>
<article class="testimonial-card">
	<?php if ( $poster ) : ?>
		<div class="testimonial-card__image">
			<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $person ); ?> from <?php echo esc_attr( $business ); ?>" width="640" height="360" loading="lazy" style="object-position: <?php echo esc_attr( $obj_pos ); ?>;" />
		</div>
	<?php endif; ?>
	<div class="testimonial-card__body">
		<?php if ( $industry ) : ?>
			<span class="testimonial-card__industry"><?php echo esc_html( $industry ); ?></span>
		<?php endif; ?>
		<?php if ( $quote ) : ?>
			<p class="testimonial-card__quote"><?php echo esc_html( $quote ); ?></p>
		<?php endif; ?>
		<div class="testimonial-card__cite">
			<span class="testimonial-card__name"><?php echo esc_html( $person ); ?></span>
			<span class="testimonial-card__business"><?php echo esc_html( $business ); ?></span>
		</div>
		<a href="<?php the_permalink(); ?>" class="testimonial-card__link">Read full story &rarr;</a>
	</div>
</article>
