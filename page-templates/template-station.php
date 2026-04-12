<?php
/**
 * Template Name: Station
 *
 * Full station profile page with audience demographics, coverage map,
 * personalities, programming, estimated reach, and sports/programs.
 *
 * @package flxlm
 */

$page_slug    = get_post_field( 'post_name', get_post() );
$station_data = flxlm_get_station_data( $page_slug );

if ( ! $station_data ) {
	get_header();
	?>
	<div class="page-header">
		<div class="container">
			<h1 class="page-header__title">Station Not Found</h1>
			<p class="page-header__subtitle">We couldn't find data for this station. Please check the URL and try again.</p>
		</div>
	</div>
	<?php
	get_footer();
	return;
}

// Estimated reach data (from audience estimation model).
$reach = isset( $station_data['estimated_reach'] ) ? $station_data['estimated_reach'] : array();

get_header();
?>

<!-- Hero -->
<div class="page-header">
	<div class="container">
		<h1 class="page-header__title"><?php echo esc_html( $station_data['name'] ); ?></h1>
		<p class="page-header__subtitle">
			<?php echo esc_html( $station_data['call_sign'] ); ?> &mdash; <?php echo esc_html( $station_data['frequency'] ); ?>
			<br><?php echo esc_html( $station_data['format'] ); ?> &middot; <?php echo esc_html( $station_data['demo'] ); ?>
		</p>
	</div>
</div>

<!-- At a Glance -->
<section class="stats-row">
	<div class="container stats-row__inner">
		<?php if ( ! empty( $reach['weekly'] ) ) : ?>
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( number_format( $reach['weekly'] / 1000 ) ); ?>K</div>
			<div class="stats-row__label">Est. Weekly Listeners</div>
		</div>
		<?php endif; ?>
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( number_format( $station_data['market_population'] / 1000 ) ); ?>K</div>
			<div class="stats-row__label">Market Population</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number">$<?php echo esc_html( number_format( $station_data['median_hhi'] / 1000 ) ); ?>K</div>
			<div class="stats-row__label">Median HHI (<?php echo esc_html( $station_data['demo'] ); ?>)</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( $station_data['homeownership_pct'] ); ?><span>%</span></div>
			<div class="stats-row__label">Homeownership</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( $station_data['drive_alone_pct'] ); ?><span>%</span></div>
			<div class="stats-row__label">Drive Alone to Work</div>
		</div>
	</div>
</section>

<!-- Audience Profile -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Who Listens to <?php echo esc_html( $station_data['name'] ); ?></h2>
			<p class="section__subtitle">Demographics of our <?php echo esc_html( strtolower( $station_data['demo'] ) ); ?> audience in <?php echo esc_html( implode( ', ', $station_data['coverage_counties'] ) ); ?> <?php echo count( $station_data['coverage_counties'] ) > 1 ? 'counties' : 'County'; ?></p>
		</div>

		<div class="audience-profile-grid">
			<div class="audience-stat">
				<div class="audience-stat__value">$<?php echo esc_html( number_format( $station_data['median_hhi'] ) ); ?></div>
				<div class="audience-stat__label">Median Household Income</div>
				<div class="audience-stat__detail"><?php echo esc_html( $station_data['demo'] ); ?> households</div>
			</div>
			<div class="audience-stat">
				<div class="audience-stat__value"><?php echo esc_html( $station_data['homeownership_pct'] ); ?>%</div>
				<div class="audience-stat__label">Homeowners</div>
				<div class="audience-stat__detail">vs. 65% national average</div>
			</div>
			<div class="audience-stat">
				<div class="audience-stat__value"><?php echo esc_html( $station_data['drive_alone_pct'] ); ?>%</div>
				<div class="audience-stat__label">Drive Alone to Work</div>
				<div class="audience-stat__detail">Captive radio audience</div>
			</div>
			<?php if ( ! empty( $reach['in_car'] ) ) : ?>
			<div class="audience-stat">
				<div class="audience-stat__value"><?php echo esc_html( number_format( $reach['in_car'] / 1000 ) ); ?>K</div>
				<div class="audience-stat__label">Daily In-Car Audience</div>
				<div class="audience-stat__detail">Estimated drive-time listeners</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<!-- Coverage Map -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Coverage Area</h2>
			<p class="section__subtitle">FCC-licensed broadcast coverage</p>
		</div>
		<div id="station-map" class="station-map" data-station-call="<?php echo esc_attr( $station_data['call_sign'] ); ?>"></div>
		<div class="post-content" style="margin-top: var(--space-lg);">
			<p><?php echo esc_html( $station_data['coverage_description'] ); ?></p>
			<p><strong>Counties served:</strong> <?php echo esc_html( implode( ', ', $station_data['coverage_counties'] ) ); ?>.</p>
		</div>
	</div>
</section>

<!-- About This Station -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">About <?php echo esc_html( $station_data['name'] ); ?></h2>
		</div>
		<div class="post-content">
			<?php echo wp_kses_post( $station_data['description'] ); ?>
		</div>
	</div>
</section>

<?php if ( ! empty( $station_data['personalities'] ) ) : ?>
<!-- On Air & Programming -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">On Air</h2>
			<p class="section__subtitle">The voices of <?php echo esc_html( $station_data['name'] ); ?></p>
		</div>

		<div class="programming-layout">
			<!-- Personalities -->
			<div class="personality-grid">
				<?php foreach ( $station_data['personalities'] as $person ) : ?>
					<div class="personality-card">
						<div class="personality-card__initials"><?php
							$words = explode( ' ', $person['name'] );
							echo esc_html( substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? substr( $words[1], 0, 1 ) : '' ) );
						?></div>
						<div>
							<h3 class="personality-card__name"><?php echo esc_html( $person['name'] ); ?></h3>
							<p class="personality-card__show"><?php echo esc_html( $person['show'] ); ?></p>
							<p class="personality-card__time"><?php echo esc_html( $person['time'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Programming Schedule -->
			<?php if ( ! empty( $station_data['programming'] ) ) : ?>
			<div class="programming-schedule">
				<h3 class="programming-schedule__title">Daily Schedule</h3>
				<?php foreach ( $station_data['programming'] as $slot ) : ?>
					<div class="programming-slot">
						<span class="programming-slot__time"><?php echo esc_html( $slot['time'] ); ?></span>
						<span class="programming-slot__show"><?php echo esc_html( $slot['show'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( ! empty( $station_data['sports'] ) ) : ?>
<!-- Sports -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Sports on <?php echo esc_html( $station_data['name'] ); ?></h2>
		</div>
		<div class="sports-grid">
			<?php foreach ( $station_data['sports'] as $sport ) : ?>
				<div class="sport-card">
					<h3 class="sport-card__name"><?php echo esc_html( $sport['name'] ); ?></h3>
					<p class="sport-card__season"><?php echo esc_html( $sport['season'] ); ?></p>
					<?php if ( ! empty( $sport['details'] ) ) : ?>
						<p class="sport-card__details"><?php echo esc_html( $sport['details'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( ! empty( $reach['weekly'] ) ) : ?>
<!-- Estimated Reach -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Estimated Audience Reach</h2>
			<p class="section__subtitle">Based on Census market data and Nielsen format share research</p>
		</div>
		<div class="reach-grid">
			<div class="reach-stat">
				<div class="reach-stat__value"><?php echo esc_html( number_format( $reach['weekly'] ) ); ?></div>
				<div class="reach-stat__label">Weekly Listeners</div>
			</div>
			<div class="reach-stat">
				<div class="reach-stat__value"><?php echo esc_html( number_format( $reach['daily'] ) ); ?></div>
				<div class="reach-stat__label">Daily Listeners</div>
			</div>
			<div class="reach-stat">
				<div class="reach-stat__value"><?php echo esc_html( number_format( $reach['monthly'] ) ); ?></div>
				<div class="reach-stat__label">Monthly Reach</div>
			</div>
			<?php if ( ! empty( $reach['in_car'] ) ) : ?>
			<div class="reach-stat">
				<div class="reach-stat__value"><?php echo esc_html( number_format( $reach['in_car'] ) ); ?></div>
				<div class="reach-stat__label">Daily In-Car Audience</div>
			</div>
			<?php endif; ?>
		</div>
		<p class="methodology-link">Estimates based on Census ACS 2022 population data and Nielsen Audio Today 2026 format share research. <a href="<?php echo esc_url( home_url( '/methodology/' ) ); ?>">See our methodology.</a></p>
	</div>
</section>
<?php endif; ?>

<!-- Why Radio callout -->
<section class="section">
	<div class="container">
		<div class="post-content" style="max-width: 720px; margin: 0 auto; text-align: center;">
			<p style="font-size: 1.1rem; color: var(--color-text-muted);">Radio reaches <strong>93% of American adults</strong> every month and delivers the <strong>second-highest ROI</strong> of any advertising channel. In the Finger Lakes, <?php echo esc_html( $station_data['drive_alone_pct'] ); ?>% of workers drive alone to work — a captive audience with nothing between them and your message.</p>
			<p><a href="<?php echo esc_url( home_url( '/solutions/radio/why-radio/' ) ); ?>" style="color: var(--color-lake); font-weight: 600;">Learn why radio works &rarr;</a></p>
		</div>
	</div>
</section>

<!-- CTA -->
<section class="section section--wash">
	<div class="container">
		<div class="section__header section__header--center">
			<h2 class="section__title">Ready to reach <?php echo esc_html( strtolower( $station_data['demo'] ) ); ?> in the Finger Lakes?</h2>
			<p class="section__subtitle">Let's talk about how <?php echo esc_html( $station_data['name'] ); ?> can work for your business.</p>
		</div>
		<div class="text-center">
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn--primary btn--lg">Contact Us</a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
