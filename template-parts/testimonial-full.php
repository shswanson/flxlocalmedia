<?php
/**
 * Template Part: Testimonial Full
 *
 * Full video player + large quote + cite. For hero and case studies.
 * Expects global $post to be a flxlm_testimonial.
 *
 * @package flxlm
 */

$person   = get_post_meta( get_the_ID(), 'person_name', true );
$title    = get_post_meta( get_the_ID(), 'person_title', true );
$business = get_post_meta( get_the_ID(), 'business_name', true );
$quote    = get_post_meta( get_the_ID(), 'quote_full', true );
if ( ! $quote ) {
	$quote = get_post_meta( get_the_ID(), 'quote_short', true );
}
?>
<div class="testimonial-full">
	<div class="testimonial-full__video">
		<?php flxlm_video_facade( get_the_ID() ); ?>
	</div>
	<div class="testimonial-full__content">
		<?php if ( $quote ) : ?>
			<blockquote class="testimonial-full__quote">
				<?php echo esc_html( $quote ); ?>
			</blockquote>
		<?php endif; ?>
		<div class="testimonial-full__cite">
			<span class="testimonial-full__name"><?php echo esc_html( $person ); ?></span>
			<?php if ( $title ) : ?>
				<span class="testimonial-full__title"><?php echo esc_html( $title ); ?></span>
			<?php endif; ?>
			<span class="testimonial-full__business"><?php echo esc_html( $business ); ?></span>
		</div>
	</div>
</div>
