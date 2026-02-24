<?php
/**
 * Template Part: Testimonial Quote (text only)
 *
 * Blockquote + cite, no video. For contact page and inline usage.
 * Expects global $post to be a flxlm_testimonial.
 *
 * @package flxlm
 */

$person   = get_post_meta( get_the_ID(), 'person_name', true );
$business = get_post_meta( get_the_ID(), 'business_name', true );
$quote    = get_post_meta( get_the_ID(), 'quote_short', true );
?>
<blockquote class="testimonial-quote">
	<p class="testimonial-quote__text"><?php echo esc_html( $quote ); ?></p>
	<footer class="testimonial-quote__cite">
		<span class="testimonial-quote__name"><?php echo esc_html( $person ); ?></span>
		&mdash;
		<span class="testimonial-quote__business"><?php echo esc_html( $business ); ?></span>
	</footer>
</blockquote>
