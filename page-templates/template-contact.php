<?php
/**
 * Template Name: Contact
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Contact Us</h1>
		<p class="page-header__subtitle">Let's talk about growing your business in the Finger Lakes.</p>
	</div>
</div>

<section class="section">
	<div class="container">

		<!-- Testimonial quote above form -->
		<?php
		$quote_testimonial = flxlm_get_testimonials( array(
			'posts_per_page' => 1,
			'orderby'        => 'rand',
		) );
		if ( $quote_testimonial->have_posts() ) :
			while ( $quote_testimonial->have_posts() ) : $quote_testimonial->the_post();
				get_template_part( 'template-parts/testimonial-quote' );
			endwhile;
			wp_reset_postdata();
		endif;
		?>

		<div class="contact-layout">
			<div class="contact-layout__form">
				<h2>Send Us a Message</h2>

				<?php if ( isset( $_GET['contact'] ) && 'success' === $_GET['contact'] ) : ?>
					<div class="flxlm-form-success">
						Thanks for reaching out! We'll be in touch within one business day.
					</div>
				<?php endif; ?>

				<?php flxlm_render_contact_form(); ?>
			</div>

			<div class="contact-layout__info">
				<h2>Our Offices</h2>
				<div class="office-list">
					<div class="office">
						<h3 class="office__name">Geneva</h3>
						<p class="office__address">
							3568 Lenox Road, Suite A<br />
							Geneva, NY 14456
						</p>
					</div>
					<div class="office">
						<h3 class="office__name">Auburn</h3>
						<p class="office__address">
							5998 Experimental Road, Suite A<br />
							Auburn, NY 13021
						</p>
					</div>
					<div class="office">
						<h3 class="office__name">Penn Yan</h3>
						<p class="office__address">
							103 Main Street, Suite A<br />
							Penn Yan, NY 14527
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
