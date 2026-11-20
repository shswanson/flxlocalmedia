<?php
/**
 * Template Name: Solutions: Radio
 *
 * Stations hub: bold hero, seven station tiles in their brand colours with a
 * live "on air now" line, one coverage map with all seven outlines, how the
 * stations work together, and what radio advertisers say.
 *
 * Station facts come from flxlm_hub_stations() (inc/site-hubs.php), which reads
 * the station registry. A section with no data is skipped, never printed empty.
 *
 * @package flxlm
 */

$stations = flxlm_hub_stations();

$radio_quotes = flxlm_get_testimonials( array(
	'service'        => 'radio',
	'posts_per_page' => 3,
) );

get_header();
?>

<div class="flxlm-hub flxlm-hub--stations">

	<!-- 1. Hero -->
	<section class="hb-hero" aria-labelledby="hb-title">
		<div class="hb-wrap hb-hero__inner">
			<p class="hb-eyebrow">Radio advertising</p>
			<h1 class="hb-title" id="hb-title">Seven stations. <span>One call.</span></h1>
			<p class="hb-lead">Each station has its own format and its own listeners. Pick one, or let us build a schedule across several of them.</p>
			<div class="hb-actions">
				<a class="hb-btn hb-btn--primary" href="<?php echo esc_url( flxlm_hub_contact_url( 'radio' ) ); ?>">Get a free plan for radio</a>
				<?php if ( $stations ) : ?>
					<a class="hb-btn hb-btn--ghost" href="#coverage">See the coverage map</a>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<?php if ( $stations ) : ?>
		<!-- 2. The seven stations -->
		<section class="hb-section hb-section--lift" id="stations" aria-label="Our seven stations">
			<div class="hb-wrap">
				<div class="hb-tiles">
					<?php foreach ( $stations as $t ) : ?>
						<?php flxlm_hub_station_tile( $t ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<!-- 3. Coverage map -->
		<section class="hb-section" id="coverage" aria-labelledby="hb-map-title">
			<div class="hb-wrap">
				<div class="hb-head">
					<h2 id="hb-map-title">Where you can hear us</h2>
					<p>Each outline is the area where one station can typically be heard. Select a name to open that station.</p>
				</div>
				<div class="hb-map-grid">
					<div class="hb-map-box">
						<div id="hub-map" class="hb-map" role="img" aria-label="Map of the Finger Lakes with the coverage area of each of our seven stations"></div>
					</div>
					<ul class="hb-legend">
						<?php foreach ( $stations as $t ) : ?>
							<li>
								<a href="<?php echo esc_url( $t['url'] ); ?>" data-legend-call="<?php echo esc_attr( $t['call'] ); ?>">
									<span class="hb-swatch<?php echo '' !== $t['map_dash'] ? ' hb-swatch--dash' : ''; ?>" style="--sw: <?php echo esc_attr( $t['map_color'] ); ?>;" aria-hidden="true"></span>
									<span class="hb-legend__text">
										<b><?php echo esc_html( $t['name'] ); ?></b>
										<span><?php echo esc_html( trim( $t['freq'] . ( '' !== $t['also'] ? ', ' . lcfirst( $t['also'] ) : '' ) ) ); ?></span>
										<?php if ( $t['counties'] ) : ?>
											<span><?php echo esc_html( implode( ', ', $t['counties'] ) . ( count( $t['counties'] ) > 1 ? ' counties' : ' County' ) ); ?></span>
										<?php endif; ?>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<p class="hb-note">Each area is where a station can typically be heard on all of its signals combined. FM areas are modelled on real terrain to the 54 dBu level (60 dBu for some translators). Reception varies with terrain, buildings and the radio. <a href="<?php echo esc_url( home_url( '/methodology/' ) ); ?>">How we count listeners</a></p>
			</div>
		</section>
	<?php endif; ?>

	<!-- 4. Better together -->
	<section class="hb-section hb-section--tint" id="together" aria-labelledby="hb-together-title">
		<div class="hb-wrap hb-wrap--narrow">
			<div class="hb-head">
				<h2 id="hb-together-title">Better together</h2>
				<p>We build schedules across the stations that reach your customers. Different formats reach different listeners at different times of day.</p>
			</div>
			<div class="hb-prose">
				<p>Most businesses advertise on one station and hope for the best. Our clients advertise across formats, because different customers listen to different stations at different times.</p>
				<p>We start with who your customers are, then look at which of our stations they listen to and when. A country fan driving to work at 7 AM, a classic rock listener on a lunch break at noon, and a news and talk listener heading home at 5 PM can all be your customers, and your schedule can reach each of them.</p>
				<p>Tell us who you are trying to reach and we will build the schedule from there.</p>
			</div>
			<p class="hb-more"><a href="<?php echo esc_url( home_url( '/solutions/why-radio/' ) ); ?>">Why radio works for local businesses <span aria-hidden="true">&rarr;</span></a></p>
		</div>
	</section>

	<!-- 5. What radio advertisers say -->
	<?php if ( $radio_quotes->have_posts() ) : ?>
		<section class="hb-section" id="advertisers" aria-labelledby="hb-adv-title">
			<div class="hb-wrap">
				<div class="hb-head">
					<h2 id="hb-adv-title">What radio advertisers say</h2>
				</div>
				<div class="hb-vgrid hb-vgrid--three">
					<?php while ( $radio_quotes->have_posts() ) : $radio_quotes->the_post(); ?>
						<?php flxlm_hub_video_card(); ?>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
				<p class="hb-more hb-more--center"><a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>">See all client videos <span aria-hidden="true">&rarr;</span></a></p>
			</div>
		</section>
	<?php endif; ?>

</div><!-- .flxlm-hub -->

<?php get_footer(); ?>
