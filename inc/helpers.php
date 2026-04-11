<?php
/**
 * Helper functions.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query testimonials.
 *
 * @param array $args Optional. Override WP_Query args.
 * @return WP_Query
 */
function flxlm_get_testimonials( $args = array() ) {
	$defaults = array(
		'post_type'      => 'flxlm_testimonial',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	);

	// Filter by service taxonomy.
	if ( ! empty( $args['service'] ) ) {
		$defaults['tax_query'] = array(
			array(
				'taxonomy' => 'flxlm_service',
				'field'    => 'slug',
				'terms'    => $args['service'],
			),
		);
		unset( $args['service'] );
	}

	// Filter by featured.
	if ( isset( $args['featured'] ) ) {
		$defaults['meta_query'] = array(
			array(
				'key'   => 'is_featured',
				'value' => '1',
			),
		);
		unset( $args['featured'] );
	}

	return new WP_Query( wp_parse_args( $args, $defaults ) );
}

/**
 * Get best available video URL for a testimonial.
 *
 * @param int $post_id Post ID.
 * @return string Video URL or empty string.
 */
function flxlm_get_video_url( $post_id ) {
	$fields = array( 'video_1080p', 'video_720p', 'video_4k' );
	foreach ( $fields as $field ) {
		$url = get_post_meta( $post_id, $field, true );
		if ( $url ) {
			return $url;
		}
	}
	return '';
}

/**
 * Render video facade markup.
 *
 * @param int   $post_id Post ID of testimonial.
 * @param array $args    Optional. 'class' for wrapper, 'size' for poster dimensions.
 */
function flxlm_video_facade( $post_id, $args = array() ) {
	$video_url  = flxlm_get_video_url( $post_id );
	$poster_webp = get_post_meta( $post_id, 'poster_webp', true );
	$poster_jpg  = get_post_meta( $post_id, 'poster_jpg', true );
	$captions    = get_post_meta( $post_id, 'captions_vtt', true );
	$person      = get_post_meta( $post_id, 'person_name', true );
	$business    = get_post_meta( $post_id, 'business_name', true );
	$class       = ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '';

	if ( ! $video_url ) {
		return;
	}

	$alt = sprintf( '%s from %s testimonial video', esc_attr( $person ), esc_attr( $business ) );
	$poster = $poster_webp ? $poster_webp : $poster_jpg;

	$data_attrs = 'data-video="' . esc_url( $video_url ) . '"';
	if ( $captions ) {
		$data_attrs .= ' data-captions="' . esc_url( $captions ) . '"';
	}
	?>
	<div class="video-facade<?php echo $class; ?>" <?php echo $data_attrs; ?>>
		<?php if ( $poster ) : ?>
			<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo $alt; ?>" width="960" height="540" loading="lazy" />
		<?php else : ?>
			<div class="video-facade__placeholder"></div>
		<?php endif; ?>
		<button class="video-facade__play" aria-label="Play testimonial from <?php echo esc_attr( $person ); ?>">
			<svg width="68" height="68" viewBox="0 0 68 68" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<circle cx="34" cy="34" r="34" fill="rgba(0,0,0,0.6)"/>
				<polygon points="26,20 52,34 26,48" fill="#fff"/>
			</svg>
		</button>
	</div>
	<?php
}

/**
 * Render station card.
 *
 * @param array $station Station data array.
 */
function flxlm_station_card( $station ) {
	?>
	<div class="station-card">
		<h3 class="station-card__name"><?php echo esc_html( $station['name'] ); ?></h3>
		<p class="station-card__call"><?php echo esc_html( $station['call_sign'] ); ?> — <?php echo esc_html( $station['frequency'] ); ?></p>
		<p class="station-card__format"><?php echo esc_html( $station['format'] ); ?></p>
		<?php if ( ! empty( $station['demo'] ) ) : ?>
			<p class="station-card__demo"><?php echo esc_html( $station['demo'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $station['personalities'] ) ) : ?>
			<p class="station-card__personalities"><?php echo esc_html( $station['personalities'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $station['reach'] ) ) : ?>
			<p class="station-card__reach"><?php echo esc_html( $station['reach'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Get studio locations with mapped stations.
 *
 * @return array
 */
function flxlm_get_studios() {
	return array(
		array(
			'name'     => 'Geneva',
			'address'  => '3568 Lenox Road, Suite A',
			'city'     => 'Geneva',
			'state'    => 'NY',
			'zip'      => '14456',
			'phone'    => '(315) 781-7000',
			'stations' => array(
				array( 'name' => 'Mix 98.5', 'slug' => 'mix985' ),
				array( 'name' => 'Classic Hits 99.3', 'slug' => 'classichits993' ),
				array( 'name' => 'The Lake', 'slug' => 'thelake' ),
			),
		),
		array(
			'name'     => 'Auburn',
			'address'  => '5998 Experimental Road, Suite A',
			'city'     => 'Auburn',
			'state'    => 'NY',
			'zip'      => '13021',
			'phone'    => '(315) 258-8413',
			'stations' => array(
				array( 'name' => 'WGVA', 'slug' => 'wgva' ),
				array( 'name' => 'WAUB', 'slug' => 'waub' ),
				array( 'name' => '101.7 The Wall', 'slug' => '1017thewall' ),
			),
		),
		array(
			'name'     => 'Penn Yan',
			'address'  => '103 Main Street, Suite A',
			'city'     => 'Penn Yan',
			'state'    => 'NY',
			'zip'      => '14527',
			'phone'    => '(800) 776-9357',
			'stations' => array(
				array( 'name' => 'WFLR', 'slug' => 'wflr' ),
			),
		),
	);
}

/**
 * Get station data.
 *
 * @return array
 */
function flxlm_get_stations() {
	return array(
		array(
			'name'          => 'Mix 98.5',
			'call_sign'     => 'WNYR',
			'frequency'     => '98.5 FM',
			'format'        => 'Adult contemporary',
			'demo'          => 'Women 25-54',
			'personalities' => 'Jim & Sorah (mornings) · Lisa Cruz · John Tesh',
			'reach'         => '338K market · $70K median HHI',
		),
		array(
			'name'          => 'Classic Hits 99.3',
			'call_sign'     => 'WFLK',
			'frequency'     => '99.3 FM',
			'format'        => 'Greatest hits of the \'70s, \'80s, and \'90s',
			'demo'          => 'Adults 35-64',
			'personalities' => 'Lisa Cruz (mornings) · Ken Paradise',
			'reach'         => '313K market · $70K median HHI',
		),
		array(
			'name'          => '101.7 The Wall',
			'call_sign'     => 'WLLW',
			'frequency'     => '101.7 FM',
			'format'        => 'Classic rock',
			'demo'          => 'Men 25-64',
			'personalities' => 'Ken & Woody (mornings)',
			'reach'         => '237K market · $73K median HHI',
		),
		array(
			'name'          => 'Finger Lakes Country',
			'call_sign'     => 'WFLR',
			'frequency'     => '95.9 / 96.1 / 96.9 / 101.9 FM / 1570 AM',
			'format'        => 'Country — #1 format nationally',
			'demo'          => 'Adults 25-64',
			'personalities' => 'Larry Timko (mornings) · Lucas Day (news)',
			'reach'         => '137K market · NASCAR · HS sports',
		),
		array(
			'name'          => 'WGVA News Radio',
			'call_sign'     => 'WGVA',
			'frequency'     => '106.3 FM / 1240 AM',
			'format'        => 'News, talk, and community',
			'demo'          => 'Adults 35+',
			'personalities' => 'Paul Szmal (mornings) · Kilmeade · Levin',
			'reach'         => '112K market · $77K median HHI · Bills/SU sports',
		),
		array(
			'name'          => 'WAUB News Radio',
			'call_sign'     => 'WAUB',
			'frequency'     => '96.3 FM / 1590 AM',
			'format'        => 'News, talk, and local sports',
			'demo'          => 'Adults 35+',
			'personalities' => 'Paul Szmal (mornings) · Kilmeade · Levin',
			'reach'         => '76K market · Auburn/Cayuga County · HS sports',
		),
		array(
			'name'          => 'The Lake',
			'call_sign'     => 'WCGR',
			'frequency'     => '100.1 / 104.5 FM',
			'format'        => 'Easy rock — 6 min/hr commercial limit',
			'demo'          => 'Women 35-64',
			'personalities' => 'Premium low-clutter format',
			'reach'         => '756K market (Rochester) · $71K median HHI',
		),
	);
}
