<?php
/**
 * Template Name: Contact
 *
 * @package flxlm
 */

flxlm_pg_begin( array(
	'accent'         => 'navy',
	'eyebrow'        => 'Contact',
	'title'          => 'Get a free local marketing review',
	'lead'           => "Tell us who you are and how to reach you. We'll call to learn about your business and come back with a plan, not a pitch.",
	'cta'            => false,
	'band'           => false,
	'no_footer_band' => true,
) );
get_header();
flxlm_pg_open();
?>

<!-- Contact Form Section -->
<section class="section" id="review">
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

		<div class="contact-form-wrap">
			<h2>Request your free review</h2>

			<?php if ( isset( $_GET['contact'] ) && 'success' === $_GET['contact'] ) : ?>
				<div class="flxlm-form-success">
					Thanks for reaching out! We'll be in touch soon to set up your free review.
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['contact'] ) && 'missing' === $_GET['contact'] ) : ?>
				<div class="flxlm-form-error" role="alert">
					Please fill in your name and email, then send it again.
				</div>
			<?php endif; ?>

			<?php flxlm_render_contact_form(); ?>
		</div>
	</div>
</section>

<!-- Studios Section -->
<section class="section section--gray pg-studios">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="flx-eyebrow">Visit us</span>
			<h2 class="section__title">Our Studios</h2>
			<p class="section__subtitle">Three studios across the Finger Lakes, broadcasting seven stations across the region.</p>
		</div>

		<div class="studio-grid">
			<?php
			$studios = flxlm_get_studios();
			foreach ( $studios as $studio ) :
				$map_query = urlencode( $studio['address'] . ', ' . $studio['city'] . ', ' . $studio['state'] . ' ' . $studio['zip'] );
			?>
				<div class="studio-card">
					<iframe
						class="studio-card__map"
						src="https://maps.google.com/maps?q=<?php echo esc_attr( $map_query ); ?>&amp;z=15&amp;output=embed"
						loading="lazy"
						title="<?php echo esc_attr( $studio['name'] ); ?> studio location"
					></iframe>
					<div class="studio-card__body">
						<h3 class="studio-card__name"><?php echo esc_html( $studio['name'] ); ?></h3>
						<div class="studio-card__stations">
							<?php foreach ( $studio['stations'] as $station ) : ?>
								<span class="studio-card__badge studio-card__badge--<?php echo esc_attr( $station['slug'] ); ?>"><?php echo esc_html( $station['name'] ); ?></span>
							<?php endforeach; ?>
						</div>
						<p class="studio-card__address">
							<?php echo esc_html( $studio['address'] ); ?><br>
							<?php echo esc_html( $studio['city'] . ', ' . $studio['state'] . ' ' . $studio['zip'] ); ?>
						</p>
						<p class="studio-card__phone">
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $studio['phone'] ) ); ?>"><?php echo esc_html( $studio['phone'] ); ?></a>
						</p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php
flxlm_pg_close();
get_footer();

