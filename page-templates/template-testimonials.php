<?php
/**
 * Template Name: Testimonials
 *
 * The Proof page (/testimonials/): every published client video, newest first,
 * then the case studies, then two cards that explain why radio works and how
 * we count listeners.
 *
 * @package flxlm
 */

$testimonials = flxlm_get_testimonials( array(
	'orderby' => 'date',
	'order'   => 'DESC',
) );
$case_studies = flxlm_hub_case_studies();

get_header();
?>

<div class="flxlm-hub flxlm-hub--proof">

	<!-- 1. Hero -->
	<section class="hb-hero" aria-labelledby="hb-title">
		<div class="hb-wrap hb-hero__inner">
			<p class="flx-eyebrow">Proof</p>
			<h1 class="hb-title" id="hb-title">Radio works.</h1>
			<p class="hb-lead">Hear it from Finger Lakes businesses that advertise with FLX Local Media. These are their words, in their own videos.</p>
			<div class="hb-actions">
				<a class="hb-btn hb-btn--primary" href="<?php echo esc_url( flxlm_hub_contact_url( 'radio' ) ); ?>">Get a free plan for radio</a>
			</div>
		</div>
	</section>

	<!-- 2. Client videos -->
	<section class="hb-section hb-section--lift" id="videos" aria-labelledby="hb-videos-title">
		<div class="hb-wrap">
			<h2 class="sr-only" id="hb-videos-title">Client videos</h2>
			<?php if ( $testimonials->have_posts() ) : ?>
				<div class="hb-vgrid">
					<?php while ( $testimonials->have_posts() ) : $testimonials->the_post(); ?>
						<?php flxlm_hub_video_card(); ?>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<p class="hb-empty">Client videos are on the way.</p>
			<?php endif; ?>
		</div>
	</section>

	<!-- 3. Case studies -->
	<section class="hb-section" id="case-studies" aria-labelledby="hb-cases-title">
		<div class="hb-wrap">
			<div class="hb-head">
				<h2 id="hb-cases-title">Case studies</h2>
				<p>Real work for Finger Lakes businesses, and what came out of it.</p>
			</div>
			<?php if ( $case_studies ) : ?>
				<div class="hb-cases">
					<?php foreach ( $case_studies as $cs ) : ?>
						<a class="hb-case" href="<?php echo esc_url( $cs['url'] ); ?>">
							<h3><?php echo esc_html( $cs['title'] ); ?></h3>
							<?php if ( '' !== $cs['summary'] ) : ?>
								<p><?php echo esc_html( $cs['summary'] ); ?></p>
							<?php endif; ?>
							<span class="hb-case__go">Read the case study <span aria-hidden="true">&rarr;</span></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="hb-more hb-more--center"><a href="<?php echo esc_url( home_url( '/case-study/' ) ); ?>">See the case studies <span aria-hidden="true">&rarr;</span></a></p>
			<?php endif; ?>
		</div>
	</section>

	<!-- 4. How we know -->
	<section class="hb-section hb-section--tint" id="how-we-know" aria-labelledby="hb-know-title">
		<div class="hb-wrap">
			<div class="hb-head">
				<h2 id="hb-know-title">The reasoning behind it</h2>
			</div>
			<div class="hb-pair">
				<a class="hb-pcard" href="<?php echo esc_url( home_url( '/solutions/why-radio/' ) ); ?>">
					<h3>Why radio works</h3>
					<p>Why radio reaches your customers where they already are, including in the car, and what it looks like in the Finger Lakes media landscape.</p>
					<span class="hb-pcard__go">Read why radio works <span aria-hidden="true">&rarr;</span></span>
				</a>
				<a class="hb-pcard" href="<?php echo esc_url( home_url( '/methodology/' ) ); ?>">
					<h3>How we count listeners</h3>
					<p>The steps we use to turn Census population and station coverage into audience estimates, and the sources behind each one.</p>
					<span class="hb-pcard__go">See how we count <span aria-hidden="true">&rarr;</span></span>
				</a>
			</div>
		</div>
	</section>

</div><!-- .flxlm-hub -->

<?php get_footer(); ?>
