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
	</div>
	<?php
}

/**
 * Get station data.
 *
 * @return array
 */
function flxlm_get_stations() {
	return array(
		array(
			'name'      => 'Finger Lakes Country',
			'call_sign' => 'WFLR',
			'frequency' => '1570 AM / 95.9 FM',
			'format'    => 'Country',
			'demo'      => 'Adults 25-54',
		),
		array(
			'name'      => 'Mix 100.3',
			'call_sign' => 'WMXO',
			'frequency' => '100.3 FM',
			'format'    => 'Hot AC',
			'demo'      => 'Adults 18-49',
		),
		array(
			'name'      => '1390 The Word',
			'call_sign' => 'WLKA',
			'frequency' => '1390 AM',
			'format'    => 'Talk',
			'demo'      => 'Adults 35-64',
		),
		array(
			'name'      => '104.3 The Dinosaur',
			'call_sign' => 'WDNR',
			'frequency' => '104.3 FM',
			'format'    => 'Classic Rock',
			'demo'      => 'Adults 25-54',
		),
		array(
			'name'      => 'Lite 92.1',
			'call_sign' => 'WLYT',
			'frequency' => '92.1 FM',
			'format'    => 'Lite AC',
			'demo'      => 'Adults 25-54',
		),
		array(
			'name'      => 'Finger Lakes Daily News',
			'call_sign' => 'FLDN',
			'frequency' => 'Digital',
			'format'    => 'News & Information',
			'demo'      => 'Adults 25-64',
		),
		array(
			'name'      => 'FLDN Radio',
			'call_sign' => 'FLDN',
			'frequency' => 'Online',
			'format'    => 'Streaming',
			'demo'      => 'All Adults',
		),
	);
}
