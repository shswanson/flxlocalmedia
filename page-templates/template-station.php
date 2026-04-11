<?php
/**
 * Template Name: Station
 *
 * Individual station page. Reads station slug from the page slug
 * and pulls data from flxlm_get_station_data().
 *
 * @package flxlm
 */

// Determine station slug from page slug.
$page_slug    = get_post_field( 'post_name', get_post() );
$station_data = flxlm_get_station_data( $page_slug );

// Fallback: if no station matches, show a message.
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
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( number_format( $station_data['market_population'] / 1000 ) ); ?>K</div>
			<div class="stats-row__label">Market Population</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number">$<?php echo esc_html( number_format( $station_data['median_hhi'] / 1000 ) ); ?>K</div>
			<div class="stats-row__label">Median Household Income</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( $station_data['homeownership_pct'] ); ?><span>%</span></div>
			<div class="stats-row__label">Homeownership Rate</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( $station_data['drive_alone_pct'] ); ?><span>%</span></div>
			<div class="stats-row__label">Drive Alone to Work</div>
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
<!-- On Air -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">On Air</h2>
			<p class="section__subtitle">The voices of <?php echo esc_html( $station_data['name'] ); ?>.</p>
		</div>
		<div class="personality-grid">
			<?php foreach ( $station_data['personalities'] as $person ) : ?>
				<div class="personality-card">
					<h3 class="personality-card__name"><?php echo esc_html( $person['name'] ); ?></h3>
					<p class="personality-card__show"><?php echo esc_html( $person['show'] ); ?></p>
					<p class="personality-card__time"><?php echo esc_html( $person['time'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- Coverage Area -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Coverage Area</h2>
		</div>
		<div class="post-content">
			<p><?php echo esc_html( $station_data['coverage_description'] ); ?></p>
			<p><strong>Counties served:</strong> <?php echo esc_html( implode( ', ', $station_data['coverage_counties'] ) ); ?>.</p>
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
		<p class="methodology-link">Market data sourced from US Census Bureau ACS 2022 and Nielsen Audio Today 2026. <a href="<?php echo esc_url( home_url( '/methodology/' ) ); ?>">See our methodology.</a></p>
	</div>
</section>

<?php get_footer(); ?>
