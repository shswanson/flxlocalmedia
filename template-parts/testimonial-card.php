<?php
/**
 * Template Part: Testimonial Card
 *
 * Poster thumbnail + short quote + business name. For grids.
 * Expects global $post to be a flxlm_testimonial.
 *
 * @package flxlm
 */

$person   = get_post_meta( get_the_ID(), 'person_name', true );
$business = get_post_meta( get_the_ID(), 'business_name', true );
$quote    = get_post_meta( get_the_ID(), 'quote_short', true );
?>
<article class="testimonial-card">
	<div class="testimonial-card__video">
		<?php flxlm_video_facade( get_the_ID() ); ?>
	</div>
	<div class="testimonial-card__body">
		<?php if ( $quote ) : ?>
			<p class="testimonial-card__quote"><?php echo esc_html( $quote ); ?></p>
		<?php endif; ?>
		<div class="testimonial-card__cite">
			<span class="testimonial-card__name"><?php echo esc_html( $person ); ?></span>
			<span class="testimonial-card__business"><?php echo esc_html( $business ); ?></span>
		</div>
		<a href="<?php the_permalink(); ?>" class="testimonial-card__link sr-only">Read full story</a>
	</div>
</article>
