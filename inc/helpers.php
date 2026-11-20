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
		// Hidden ("unlisted") testimonials never appear in any listing.
		'post__not_in'   => flxlm_hidden_testimonial_ids(),
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
 * Get the captions URL for a testimonial, served through flxlm_serve_captions().
 *
 * The host sends .vtt uploads as application/octet-stream, which Firefox and
 * Safari can refuse for a <track>, so captions go through the theme instead.
 *
 * @param int $post_id Post ID of testimonial.
 * @return string Captions URL or empty string.
 */
function flxlm_captions_url( $post_id ) {
	if ( ! get_post_meta( $post_id, 'captions_vtt', true ) ) {
		return '';
	}
	return add_query_arg( 'flxlm_vtt', $post_id, home_url( '/' ) );
}

add_action( 'init', 'flxlm_serve_captions', 1 );

/**
 * Serve a testimonial's captions file as text/vtt for ?flxlm_vtt=<post id>.
 *
 * Only files inside uploads/testimonials/videos/ ending in .vtt are sent;
 * anything else is a 404.
 */
function flxlm_serve_captions() {
	if ( ! isset( $_GET['flxlm_vtt'] ) ) {
		return;
	}

	$post_id = absint( $_GET['flxlm_vtt'] );
	$post    = $post_id ? get_post( $post_id ) : null;
	$file    = '';

	if ( $post && 'flxlm_testimonial' === $post->post_type && 'publish' === $post->post_status ) {
		$url     = (string) get_post_meta( $post_id, 'captions_vtt', true );
		$uploads = wp_upload_dir();
		// Compare without the scheme so http and https URLs both map.
		$url_rel  = preg_replace( '#^https?:#i', '', $url );
		$base_rel = preg_replace( '#^https?:#i', '', $uploads['baseurl'] );

		if ( $url_rel && 0 === strpos( $url_rel, $base_rel . '/' ) ) {
			$path    = realpath( $uploads['basedir'] . substr( $url_rel, strlen( $base_rel ) ) );
			$allowed = realpath( $uploads['basedir'] . '/testimonials/videos' );

			if ( $path && $allowed && 0 === strpos( $path, $allowed . DIRECTORY_SEPARATOR ) && '.vtt' === strtolower( substr( $path, -4 ) ) && is_file( $path ) ) {
				$file = $path;
			}
		}
	}

	if ( ! $file ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	status_header( 200 );
	header( 'Content-Type: text/vtt; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file );
	exit;
}

/**
 * Render video facade markup.
 *
 * @param int   $post_id Post ID of testimonial.
 * @param array $args    Optional. 'class' for wrapper, 'size' for poster dimensions,
 *                       'eager' to load the poster at once (above-the-fold use, where it is the LCP image).
 */
function flxlm_video_facade( $post_id, $args = array() ) {
	$video_url  = flxlm_get_video_url( $post_id );
	$poster_webp = get_post_meta( $post_id, 'poster_webp', true );
	$poster_jpg  = get_post_meta( $post_id, 'poster_jpg', true );
	$captions    = flxlm_captions_url( $post_id );
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
			<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo $alt; ?>" width="960" height="540" <?php echo ! empty( $args['eager'] ) ? 'fetchpriority="high"' : 'loading="lazy"'; ?> />
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
	$slug = isset( $station['slug'] ) ? $station['slug'] : sanitize_title( $station['name'] );
	$link = home_url( '/solutions/radio/' . $slug . '/' );
	?>
	<a href="<?php echo esc_url( $link ); ?>" class="station-card" style="text-decoration: none; color: inherit; display: block;">
		<h3 class="station-card__name"><?php echo esc_html( $station['name'] ); ?></h3>
		<p class="station-card__call"><?php echo esc_html( $station['call_sign'] ); ?> &middot; <?php echo esc_html( $station['frequency'] ); ?></p>
		<p class="station-card__format"><?php echo esc_html( $station['format'] ); ?></p>
		<?php if ( ! empty( $station['demo'] ) ) : ?>
			<p class="station-card__demo"><?php echo esc_html( $station['demo'] ); ?></p>
		<?php endif; ?>
	</a>
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
			'address'  => '3568 Lenox Road',
			'city'     => 'Geneva',
			'state'    => 'NY',
			'zip'      => '14456',
			'phone'    => '(315) 781-7000',
			'stations' => array(
				array( 'name' => 'Mix 98.5', 'slug' => 'mix985' ),
				array( 'name' => '101.7 The Wall', 'slug' => '1017thewall' ),
				array( 'name' => 'The Lake', 'slug' => 'thelake' ),
			),
		),
		array(
			'name'     => 'Auburn',
			'address'  => '5998 Experimental Road',
			'city'     => 'Auburn',
			'state'    => 'NY',
			'zip'      => '13021',
			'phone'    => '(315) 258-8413',
			'stations' => array(
				array( 'name' => 'WGVA', 'slug' => 'wgva' ),
				array( 'name' => 'WAUB', 'slug' => 'waub' ),
				array( 'name' => 'Classic Hits 99.3', 'slug' => 'classichits993' ),
			),
		),
		array(
			'name'     => 'Penn Yan',
			'address'  => '103 Main Street',
			'city'     => 'Penn Yan',
			'state'    => 'NY',
			'zip'      => '14527',
			'phone'    => '(607) 243-7158',
			'stations' => array(
				array( 'name' => 'WFLR', 'slug' => 'wflr' ),
			),
		),
	);
}

/**
 * Get enriched station data for individual station pages.
 *
 * @param string $slug Station slug (e.g. 'mix-985'). Empty returns all.
 * @return array|null Station data array, all stations, or null if slug not found.
 */
function flxlm_get_station_data( $slug = '' ) {
	$stations = array(
		'mix-985' => array(
			'name'                => 'Mix 98.5',
			'call_sign'           => 'WNYR',
			'frequency'           => '98.5 FM',
			'format'              => 'Adult Contemporary',
			'demo'                => 'Women 25-54',
			'tagline'             => 'The Finger Lakes at-work station',
			'market_population'   => 338147,
			'median_hhi'          => 81353,
			'homeownership_pct'   => 69.9,
			'drive_alone_pct'     => 86.9,
			'description'         => '<p>Mix 98.5 is the Finger Lakes at-work station. From offices in Geneva to shops in Canandaigua, Mix 98.5 is the soundtrack of the workday for women across Ontario, Cayuga, Seneca, Wayne, and Yates counties. The station plays the music people know and love: a mix of current hits and familiar favorites that keeps the energy up without overwhelming the room.</p>
<p>Mornings start with Jim Schreck and Sorah Devlin, who have become the way tens of thousands of Finger Lakes residents begin their day. Jim stays on through mid-morning, Lisa Cruz takes over afternoons, and John Tesh brings his nationally syndicated Intelligence for Your Life show to the evening hours. It is a lineup that keeps listeners engaged from the first alarm to dinnertime.</p>
<p>With the largest estimated weekly audience of any station in the group, Mix 98.5 is the top choice for advertisers who want to reach women with real purchasing power, the household decision-makers who drive consumer spending in the Finger Lakes.</p>',
			'personalities'       => array(
				array(
					'name' => 'Jim Schreck & Sorah Devlin',
					'show' => 'Jim and Sorah',
					'time' => '5:30 - 9:00 AM',
				),
				array(
					'name' => 'Jim Schreck',
					'show' => 'Mid-Morning',
					'time' => '9:00 AM - 12:00 PM',
				),
				array(
					'name' => 'Lisa Cruz',
					'show' => 'Afternoons',
					'time' => '12:00 - 5:00 PM',
				),
				array(
					'name' => 'John Tesh',
					'show' => 'Intelligence for Your Life',
					'time' => '5:00 - 10:00 PM',
				),
			),
			'coverage_counties'    => array( 'Ontario', 'Cayuga', 'Seneca', 'Wayne', 'Yates' ),
			'coverage_description' => 'Mix 98.5 covers the heart of the Finger Lakes, reaching Geneva, Canandaigua, Auburn, Penn Yan, Seneca Falls, and surrounding communities across five counties. The signal also extends into Rochester\'s eastern suburbs.',
			'estimated_reach'      => array(
				'weekly'  => 44622,
				'daily'   => 32496,
				'monthly' => 58008,
				'in_car'  => 13680,
			),
			'programming'          => array(
				array( 'time' => '5:30 - 9:00 AM', 'show' => 'Jim and Sorah' ),
				array( 'time' => '9:00 AM - 12:00 PM', 'show' => 'Jim Schreck' ),
				array( 'time' => '12:00 - 5:00 PM', 'show' => 'Lisa Cruz' ),
				array( 'time' => '5:00 - 10:00 PM', 'show' => 'John Tesh' ),
			),
			'sports'               => array(),
			// Option D page content. Paths are relative: images to assets/images/stations/<slug>/,
			// audio to assets/audio/. Schema documented in flxlm_station_page_data() (functions.php).
			'page'                 => array(
				'logo_white'        => 'logo-white.svg',
				'eyebrow'           => 'WNYR · Radio advertising',
				'lead'              => 'Reach the women who run Finger Lakes households, all workday.',
				'profile_seconds'   => 28,
				'stats'             => array(
					'weekly'           => 44622,
					'daily'            => 32496,
					'in_car'           => 13680,
					'streaming_hours'  => 4842,
					'streaming_window' => 'last 28 days',
				),
				'persona'           => array(
					'name'  => 'Jen',
					'age'   => 42,
					'line1' => 'Office manager, Geneva',
					'line2' => 'Lives in Seneca Falls',
					'photo' => 'persona.jpg',
					'story' => array(
						'Jen works in an office in Geneva and has two kids. Mix 98.5 is on in the car through the school pickup line, on the speaker at her desk through the shift, and in the kitchen at dinner. It keeps her current: new songs she can share with her kids, familiar favorites she knows, and nothing deep or jarring for an office speaker.',
						'She is the household decision-maker: the dentist, the contractor, where the family eats on Friday, the venue for her sister\'s shower. The big purchases run through her too: the next car, the house, the furniture to fill it. In our September programming meeting the room described the Mix listener as moms staying hip with their kids, and Jim\'s point was that Mix should sound like today, not like an oldies station.',
					),
					'facts' => array(
						array( '$81K', 'Typical household income' ),
						array( '69.9%', 'Own their home' ),
						array( '86.9%', 'Drive alone to work' ),
						// Commute: ACS 2024 5-year, B08013 / B08303, five counties combined = 22.7 min (137,695 workers).
						array( '23 min', 'Average commute, each way' ),
					),
				),
				'voices'            => array(
					array(
						'name'       => 'Jim Schreck & Sorah Devlin',
						'when'       => 'Mornings, 5:30 – 9:00 AM',
						'blurb'      => 'Live reads and endorsements from the local voices listeners start their day with.',
						'photo'      => 'voices/jim-sorah.jpg',
						'photo_alt'  => 'Jim Schreck and Sorah Devlin in the Mix 98.5 studio',
						'photo_pos'  => 'center 30%',
						'syndicated' => false,
					),
					array(
						'name'       => 'Jim Schreck',
						'when'       => 'Mid-mornings, 9:00 AM – 12:00 PM',
						'blurb'      => 'Live reads and endorsements from a local host while she is at her desk.',
						'photo'      => 'voices/jim-schreck.jpg',
						'photo_alt'  => 'Jim Schreck',
						'photo_pos'  => 'center 20%',
						'syndicated' => false,
					),
					array(
						'name'       => 'Lisa Cruz',
						'when'       => 'Afternoons, 12:00 – 5:00 PM',
						'blurb'      => 'Live reads and endorsements from a local host through the afternoon and the school pickup.',
						'photo'      => 'voices/lisa-cruz.jpg',
						'photo_alt'  => 'Lisa Cruz',
						'photo_pos'  => 'center 35%',
						'syndicated' => false,
					),
					array(
						'name'       => 'John Tesh',
						'when'       => 'Intelligence for Your Life, 5:00 to 10:00 PM',
						'blurb'      => 'A syndicated show, so no live reads. Your spots run in the breaks around it.',
						'photo'      => 'voices/john-tesh.jpg',
						'photo_alt'  => 'John Tesh',
						'photo_pos'  => 'center 20%',
						'syndicated' => true,
					),
				),
				'market'            => array(
					'population' => '338K',
					'label'      => 'people live in the five counties Mix 98.5 covers.',
					'towns'      => array( 'Geneva', 'Canandaigua', 'Auburn', 'Penn Yan', 'Seneca Falls' ),
				),
				'map_note'          => 'Shaded area: where Mix 98.5 can typically be heard. Modelled on real terrain to the 54 dBu level. Reception varies with terrain, buildings and the radio.',
				'ways'              => array(
					array( 'title' => 'Spots', 'text' => 'Your commercial in rotation across the workday, when she is at her desk and in the car.', 'icon' => 'spots' ),
					array( 'title' => 'Sponsorships', 'text' => 'Put your name on a show, a music hour, the weather chat or a newscast.', 'icon' => 'sponsorship' ),
					array( 'title' => 'Live remotes', 'text' => 'The Mix team broadcasts from your location and brings the crowd to your door.', 'icon' => 'remote' ),
					array( 'title' => 'Streaming on FLX.fm', 'text' => 'Reach the same listeners on the FLX.fm stream and app, wherever they are.', 'icon' => 'stream' ),
					array( 'title' => 'Contests', 'text' => 'Give listeners a reason to visit you: prizes and giveaways announced on air.', 'icon' => 'contest' ),
				),
				'testimonial'       => array(
					'person'        => 'Kim Minro',
					'business'      => 'Aster Financial Group',
					'quote'         => 'The number of people that walk through the door and say, ‘Oh, I heard your ad on the radio.’ It proves to me that [radio] works.',
					'page_url'      => 'https://www.flxlocalmedia.com/testimonials/aster-financial-group/',
					'video_seconds' => 75,
					'thumb'         => 'testimonial.jpg',
				),
			),
		),
		'classic-hits-993' => array(
			'name'                => 'Classic Hits 99.3',
			'page' => array(
				'logo_white' => 'logo-white.svg',
				'eyebrow' => 'WFLK · Radio advertising',
				'lead' => 'Reach the Finger Lakes adults who keep this one station on, from the morning show to the afternoon drive.',
				'profile_seconds' => 25,
				'stats' => array( 'weekly' => 38423, 'daily' => 27981, 'in_car' => 10984, 'streaming_hours' => 5864, 'streaming_window' => 'last 28 days' ),
				'persona' => array(
					'name' => 'Dave', 'age' => 51, 'line1' => 'Service manager, Auburn', 'line2' => 'Lives in Owasco', 'photo' => 'persona.jpg',
					'story' => array(
						'Dave runs the service department at a car dealership in Auburn. Classic Hits 99.3 is on in his truck on the way in and on the shop radio through the day. He learned these songs in a parked car and at the school dance and never stopped knowing them. He wants a host who talks to him like a friend and a station that sounds like people having a good time.',
						'He and his wife, a teacher, make the big household decisions together: the next truck, the roof, the tires, the dinners out and the shows they go to. They spend with the Auburn businesses they hear every day and trust the owners they know by name. In our September programming meeting the room described the Classic Hits listener as a mix of ages who are all just having fun, and asked for the funkier hip-hop and dance hits to be played here.',
					),
					'facts' => array(
						// flxlocalmedia inc/helpers.php, classic-hits-993 median_hhi 83,993 (adults 35-64 households, US Census ACS 2022, Ontario, Cayuga, Seneca and Wayne counties)
						array( '$84K', 'Typical household income' ),
						// flxlocalmedia inc/helpers.php, classic-hits-993 homeownership_pct 78.2 (US Census ACS 2022, same four counties)
						array( '78.2%', 'Own their home' ),
						// flxlocalmedia inc/helpers.php, classic-hits-993 drive_alone_pct 87.3 (US Census ACS 2022, same four counties)
						array( '87.3%', 'Drive alone to work' ),
						// Census Reporter, ACS 2024 5-year (2020-2024), tables B08013 aggregate travel time and B08303 workers, Ontario 36069 + Cayuga 36011 + Seneca 36099 + Wayne 36117 combined: 2,933,110 
						array( '23 min', 'Average commute, each way' ),
					),
				),
				'voices' => array(
					array( 'name' => 'Lisa Cruz', 'when' => 'Mornings, 6:00 AM – 12:00 PM', 'blurb' => 'Live reads and endorsements from the local host listeners wake up to and take to work.', 'photo' => 'voices/lisa-cruz.jpg', 'photo_alt' => 'Lisa Cruz', 'syndicated' => false ),
					array( 'name' => 'Ken Paradise', 'when' => 'Afternoons, 12:00 – 5:00 PM', 'blurb' => 'Live reads and endorsements from a local host through the afternoon and the drive home.', 'photo' => '', 'photo_alt' => 'Ken Paradise', 'syndicated' => false ),
					array( 'name' => 'Casey\'s Top 40', 'when' => 'Weekend mornings: Saturday 8:00 AM – 12:00 PM, Sunday 9:00 AM – 12:00 PM', 'blurb' => 'A syndicated countdown, so no live reads. Your spots run in the breaks around it.', 'photo' => '', 'photo_alt' => 'Casey\'s Top 40', 'syndicated' => true ),
				),
				'market' => array( 'population' => '313K', 'label' => 'people live in the four counties Classic Hits 99.3 covers.', 'towns' => array( 'Auburn', 'Seneca Falls', 'Geneva', 'Canandaigua', 'Newark', 'Waterloo' ) ),
				'ways' => array(
					array( 'title' => 'Spots', 'text' => 'Your commercial in rotation from the morning show through the afternoon drive, when listeners are in the truck and at work.', 'icon' => 'spots' ),
					array( 'title' => 'Sponsorships', 'text' => 'Put your name on an hour of music, the top of the hour or the Classic Hits studio.', 'icon' => 'sponsorship' ),
					array( 'title' => 'Live remotes', 'text' => 'Lisa or Ken broadcasts from your location and brings the crowd to your door.', 'icon' => 'remote' ),
					array( 'title' => 'Weekend countdowns', 'text' => 'Your spots in the breaks of the Casey\'s Top 40 countdowns on Saturday and Sunday mornings.', 'icon' => 'spots' ),
					array( 'title' => 'Streaming on FLX.fm', 'text' => 'Reach the same listeners on the FLX.fm stream and app, wherever they are.', 'icon' => 'stream' ),
				),
				'testimonial' => array( 'person' => 'Ryan Phelps', 'business' => 'Ryan Phelps Auto Sales', 'quote' => '[Classic Hits] listeners seem very loyal. They might not listen to six other stations and hop around.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/ryan-phelps-auto-sales/', 'video_seconds' => 93, 'thumb' => 'testimonial.jpg' ),
			),
			'call_sign'           => 'WFLK',
			'frequency'           => '99.3 FM',
			'format'              => 'Classic Hits',
			'demo'                => 'Adults 35-64',
			'tagline'             => 'Greatest hits of the \'70s, \'80s, and \'90s',
			'market_population'   => 313434,
			'median_hhi'          => 83993,
			'homeownership_pct'   => 78.2,
			'drive_alone_pct'     => 87.3,
			'description'         => '<p>Classic Hits 99.3 plays the greatest hits of the \'70s, \'80s, and \'90s, the songs that everyone knows, from Fleetwood Mac to Tom Petty to Whitney Houston. It is the station where people sing along on the drive to work and nod their heads while running errands. The format appeals to adults 35-64 with balanced gender reach, making it one of the most versatile advertising platforms in the market.</p>
<p>Lisa Cruz starts the day with mornings, bringing familiar energy and local connection to the Finger Lakes audience. Ken Paradise takes over for afternoons, rounding out a lineup that keeps listeners tuned in through the full workday.</p>
<p>With strong coverage across Ontario, Cayuga, Seneca, and Wayne counties, Classic Hits 99.3 delivers a broad audience that includes both men and women in their peak earning and spending years.</p>',
			'personalities'       => array(
				array(
					'name' => 'Lisa Cruz',
					'show' => 'Morning Show',
					'time' => '5:30 AM - 12:00 PM',
				),
				array(
					'name' => 'Ken Paradise',
					'show' => 'Afternoons',
					'time' => '12:00 - 5:00 PM',
				),
			),
			'coverage_counties'    => array( 'Ontario', 'Cayuga', 'Seneca', 'Wayne' ),
			'coverage_description' => 'Classic Hits 99.3 covers Geneva, Auburn, Seneca Falls, Canandaigua, and the surrounding communities across four counties, with signal extending into Rochester\'s eastern suburbs and Syracuse\'s western suburbs.',
			'estimated_reach'      => array(
				'weekly'  => 38423,
				'daily'   => 27981,
				'monthly' => 49949,
				'in_car'  => 10984,
			),
			'programming'          => array(
				array( 'time' => '5:30 AM - 12:00 PM', 'show' => 'Lisa Cruz' ),
				array( 'time' => '12:00 - 5:00 PM', 'show' => 'Ken Paradise' ),
			),
			'sports'               => array(),
		),
		'the-wall' => array(
			'name'                => '101.7 The Wall',
			'page' => array(
				'logo_white' => 'logo-brick.webp',
				'eyebrow' => 'WLLW · Radio advertising',
				'lead' => 'Reach the working men of the Finger Lakes on the job, in the truck and on the weekend.',
				'profile_seconds' => 30,
				'stats' => array( 'weekly' => 19610, 'daily' => 14281, 'in_car' => 7778, 'streaming_hours' => 6445, 'streaming_window' => 'last 28 days' ),
				'persona' => array(
					'name' => 'Mike', 'age' => 46, 'line1' => 'Construction foreman, Geneva', 'line2' => 'Lives in Newark', 'photo' => 'persona.jpg',
					'story' => array(
						'Mike is a foreman for a contractor in Geneva and has been up before six every workday since he started. The Wall is on in the truck on the way to the job, on the shop radio at lunch, and in the truck again on the drive home. He wants rock that hits hard and a host who is dry, knows the album cuts, and stays out of the way of the song.',
						'He buys what the job and the weekend call for: the next truck, tools, roofing, boots, and a camper for the lake. When he trusts a business he tells the crew, and the crew listens. In our September programming meeting the room called The Wall the clearest identity in the group, and Lisa\'s point was that it should never sound like soft rock.',
					),
					'facts' => array(
						// flxlocalmedia/inc/helpers.php, the-wall, median_hhi 88,143 (Census-based, Ontario, Seneca and Wayne counties; built for the old Men 35-64 demo)
						array( '$88K', 'Typical household income' ),
						// flxlocalmedia/inc/helpers.php, the-wall, homeownership_pct 79.0
						array( '79%', 'Own their home' ),
						// flxlocalmedia/inc/helpers.php, the-wall, drive_alone_pct 87.2
						array( '87.2%', 'Drive alone to work' ),
						// Census Reporter, ACS 2024 5-year (2020-2024), tables B08013 aggregate travel time / B08303 workers, Ontario + Seneca + Wayne combined: 2,216,645 min / 97,426 workers = 22.75 min, r
						array( '23 min', 'Average commute, each way' ),
					),
				),
				'voices' => array(
					array( 'name' => 'Ken Paradise & Rockin\' Rob', 'when' => 'Mornings, 6:00 – 9:00 AM', 'blurb' => 'Live reads and endorsements from the local morning team, heard in the truck on the way to the job.', 'photo' => 'voices/ken-paradise-rob-goodell.jpg', 'photo_alt' => 'Ken Paradise & Rockin\' Rob', 'syndicated' => false ),
					array( 'name' => 'The Afternoon Grind with Drew', 'when' => 'Afternoons, 12:00 – 5:00 PM', 'blurb' => 'Live reads and endorsements from a local host through the afternoon, with daily features your name can sit beside.', 'photo' => '', 'photo_alt' => 'The Afternoon Grind with Drew', 'syndicated' => false ),
					array( 'name' => 'Sammy Hagar\'s Top Rock Countdown', 'when' => 'Saturdays, 6:00 – 8:00 PM', 'blurb' => 'A syndicated show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/sammy-hagar.jpg', 'photo_alt' => 'Sammy Hagar\'s Top Rock Countdown', 'syndicated' => true ),
					array( 'name' => 'Dee Snider\'s House of Hair', 'when' => 'Saturdays, 8:00 – 11:00 PM', 'blurb' => 'A syndicated show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/dee-snider.jpg', 'photo_alt' => 'Dee Snider\'s House of Hair', 'syndicated' => true ),
					array( 'name' => 'Classic Rock Live & Floydian Slip', 'when' => 'Sundays, 7:00 – 11:00 PM', 'blurb' => 'Two syndicated shows, so no live reads. Your spots run in the breaks around them.', 'photo' => '', 'photo_alt' => 'Classic Rock Live & Floydian Slip', 'syndicated' => true ),
				),
				'market' => array( 'population' => '237K', 'label' => 'people live in the three counties 101.7 The Wall covers.', 'towns' => array( 'Geneva', 'Canandaigua', 'Seneca Falls', 'Waterloo', 'Newark', 'Lyons' ) ),
				'ways' => array(
					array( 'title' => 'Spots', 'text' => 'Your commercial in rotation through the morning and afternoon shows, when the crew is in the truck and on the job.', 'icon' => 'spots' ),
					array( 'title' => 'Sponsorships', 'text' => 'Put your name on the morning show or on a daily feature such as This Day in Music History.', 'icon' => 'sponsorship' ),
					array( 'title' => 'Weekend rock shows', 'text' => 'Sponsor Sammy Hagar\'s Top Rock Countdown, House of Hair, Classic Rock Live or Floydian Slip, the shows listeners plan their weekend around.', 'icon' => 'sponsorship' ),
					array( 'title' => 'Streaming on FLX.fm', 'text' => 'Reach the same listeners on the FLX.fm stream and app, in the shop or on the job site.', 'icon' => 'stream' ),
					array( 'title' => 'Contests', 'text' => 'Give listeners a reason to visit you: prizes and giveaways announced on air.', 'icon' => 'contest' ),
				),
				'testimonial' => array( 'person' => 'Michael Enslow', 'business' => 'Enstech Automotive and Trailer', 'quote' => 'I was skeptical a few times, but I can honestly say after trying it and trying it, it does work.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/enstech-automotive/', 'video_seconds' => 90, 'thumb' => 'testimonial.jpg' ),
			),
			'call_sign'           => 'WLLW',
			'frequency'           => '101.7 FM',
			'format'              => 'Classic Rock',
			'demo'                => 'Men 35-64',
			'tagline'             => 'Classic rock for the Finger Lakes',
			'market_population'   => 237263,
			'median_hhi'          => 88143,
			'homeownership_pct'   => 79.0,
			'drive_alone_pct'     => 87.2,
			'description'         => '<p>101.7 The Wall is the Finger Lakes\' home for classic rock: Led Zeppelin, AC/DC, Pink Floyd, the Stones, and everything that defined rock and roll. The format skews male and attracts a loyal audience of men 35-64, the generation that grew up on this music and still turns it up on the way to work.</p>
<p>Mornings belong to Ken Paradise and Rockin\' Rob, weekdays 6 to 9, and Drew carries the afternoon drive with The Afternoon Grind, weekdays noon to 5. Weekends bring the syndicated rock franchises listeners plan their night around: Sammy Hagar\'s Top Rock Countdown and Dee Snider\'s House of Hair on Saturday, Classic Rock Live and Floydian Slip on Sunday. It is the kind of station where listeners do not just tune in. They turn it up.</p>
<p>The Wall\'s audience has the highest concentration of construction and trades workers of any station in the group, making it a natural fit for building materials, auto services, equipment suppliers, and any business that serves the working men of the Finger Lakes.</p>',
			'personalities'       => array(
				array(
					'name' => 'Ken Paradise & Rockin\' Rob',
					'show' => 'Ken and Rockin\' Rob',
					'time' => 'Weekdays 6:00 - 9:00 AM',
				),
				array(
					'name' => 'Drew',
					'show' => 'The Afternoon Grind',
					'time' => 'Weekdays 12:00 - 5:00 PM',
				),
			),
			'coverage_counties'    => array( 'Ontario', 'Seneca', 'Wayne' ),
			'coverage_description' => 'The Wall covers Geneva, Penn Yan, Canandaigua, Bath, and Watkins Glen across Ontario, Seneca, and Wayne counties.',
			'estimated_reach'      => array(
				'weekly'  => 19610,
				'daily'   => 14281,
				'monthly' => 25493,
				'in_car'  => 7778,
			),
			'programming'          => array(
				array( 'time' => 'Weekdays 6:00 - 9:00 AM', 'show' => 'Ken and Rockin\' Rob' ),
				array( 'time' => 'Weekdays 12:00 - 5:00 PM', 'show' => 'The Afternoon Grind with Drew' ),
				array( 'time' => 'Sat 6:00 - 8:00 PM', 'show' => 'Sammy Hagar\'s Top Rock Countdown' ),
				array( 'time' => 'Sat 8:00 - 11:00 PM', 'show' => 'Dee Snider\'s House of Hair' ),
				array( 'time' => 'Sun 7:00 - 9:00 PM', 'show' => 'Classic Rock Live' ),
				array( 'time' => 'Sun 9:00 - 11:00 PM', 'show' => 'Floydian Slip' ),
			),
			'sports'               => array(),
		),
		'finger-lakes-country' => array(
			'name'                => 'Finger Lakes Country',
			'page' => array(
				'logo_white' => 'logo-white.svg',
				'eyebrow' => 'WFLR · Radio advertising',
				'lead' => 'Reach the working adults who start every day with local news, weather and country music.',
				'profile_seconds' => 32,
				'stats' => array( 'streaming_hours' => 4777, 'streaming_window' => 'last 28 days' ),
				'persona' => array(
					'name' => 'Tammy', 'age' => 38, 'line1' => 'Vineyard crew lead, Dundee', 'line2' => 'Lives in Penn Yan', 'photo' => 'persona.jpg',
					'story' => array(
						'Tammy leads a vineyard crew near Dundee and helps her husband run a small farm stand on the side. WFLR is on in the barn before sunrise, in the minivan on the way to practice, and in the kitchen at supper. She knows Larry and Lucas by name, and she trusts the station because the frost warning, the school closings and the county fair results come right next to a new country single.',
						'She is the one in the family who decides where the money goes: the kids\' sports gear, the truck repair, the new roof, the equipment for the stand. She buys local for the everyday things and the big ones, and she shops where she knows the owner. On Friday nights she is at a Penn Yan Mustangs or Dundee/Bradford BraveScots game, or following it on the station. In our September programming meeting the room called WFLR community radio, country first, and called it unambiguous.',
					),
					'facts' => array(
						// flxlocalmedia inc/helpers.php, finger-lakes-country median_hhi 89,727 (Census ACS 2022, adults 25-64). Basis per customer-flx data/stations.json: market_counties Ontario and Yates 
						array( '$90K', 'Typical household income' ),
						// flxlocalmedia inc/helpers.php, finger-lakes-country homeownership_pct 72.6 (stations.json demo_homeownership_pct, same Ontario and Yates basis).
						array( '72.6%', 'Own their home' ),
						// flxlocalmedia inc/helpers.php, finger-lakes-country drive_alone_pct 84.8 (stations.json, same Ontario and Yates basis).
						array( '84.8%', 'Drive alone to work' ),
						// Census Reporter, ACS 2024 5-year (2020-2024), B08013 aggregate travel time / B08303 workers, six counties combined (Yates, Schuyler, Steuben, Chemung, Seneca, Ontario): 3,143,755 m
						array( '22 min', 'Average commute, each way' ),
					),
				),
				'voices' => array(
					array( 'name' => 'Larry Timko', 'when' => 'Weekday mornings, 6:00 – 10:00 AM', 'blurb' => 'Live reads and endorsements from the Marconi Award-winning host listeners start their day with.', 'photo' => 'voices/larry-timko.jpg', 'photo_alt' => 'Larry Timko', 'syndicated' => false ),
					array( 'name' => 'Lucas Day', 'when' => 'Open Mic, Tuesdays and Thursdays, 8:30 – 9:00 AM', 'blurb' => 'Local news, Open Mic interviews and live Penn Yan and Dundee high school games, with sponsor mentions read on air.', 'photo' => 'voices/lucas-day.jpg', 'photo_alt' => 'Lucas Day', 'syndicated' => false ),
					array( 'name' => 'PRN Fast Talk and NASCAR Live', 'when' => 'Mondays and Tuesdays, 7:00 – 8:00 PM', 'blurb' => 'Syndicated racing shows, so no live reads. Your spots run in the breaks, and the NASCAR sponsorship covers them.', 'photo' => '', 'photo_alt' => 'PRN Fast Talk and NASCAR Live', 'syndicated' => true ),
					array( 'name' => 'Kyle Petty\'s Back Then Again Country Countdown', 'when' => 'Saturdays, 10:00 AM – 12:00 PM', 'blurb' => 'A syndicated show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/kyle-petty-charlie-debbie.jpg', 'photo_alt' => 'Kyle Petty\'s Back Then Again Country Countdown', 'syndicated' => true ),
					array( 'name' => 'Pure American Country', 'when' => 'Sundays, 7:00 – 9:00 AM', 'blurb' => 'A syndicated show of classic country, so no live reads. Your spots run in the breaks around it.', 'photo' => '', 'photo_alt' => 'Pure American Country', 'syndicated' => true ),
				),
				'market' => array( 'population' => '362K', 'label' => 'people live in the six counties Finger Lakes Country covers.', 'towns' => array( 'Penn Yan', 'Dundee', 'Watkins Glen', 'Bath', 'Corning', 'Elmira' ) ),
				'ways' => array(
					array( 'title' => 'Spots', 'text' => 'Your commercial in rotation across the day, led by the weekday morning show.', 'icon' => 'spots' ),
					array( 'title' => 'Sponsorships', 'text' => 'Put your name on the Larry Timko morning show, the morning news and sports, or an hour of music.', 'icon' => 'sports' ),
					array( 'title' => 'High school sports', 'text' => 'Sponsor live Penn Yan and Dundee high school football, basketball and lacrosse, called by Lucas Day.', 'icon' => 'sports' ),
					array( 'title' => 'NASCAR', 'text' => 'Sponsor the full season: race broadcasts, NASCAR Live, Fast Talk and the Kyle Petty countdown, March to November.', 'icon' => 'sports' ),
					array( 'title' => 'Seasonal packages', 'text' => 'Winter Storm Updates, spring Tax Tips and Christmas Greetings, timed to what listeners are listening for.', 'icon' => 'season' ),
					array( 'title' => 'Streaming on FLX.fm', 'text' => 'Reach the same listeners on the FLX.fm stream and app, wherever they are.', 'icon' => 'stream' ),
				),
				'testimonial' => array( 'person' => 'Mike Westfall', 'business' => 'Church Creative Flooring', 'quote' => 'Within six to eight months, the business actually doubled in size … and then right after that, it actually tripled.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/church-creative-flooring/', 'video_seconds' => 86, 'thumb' => 'testimonial.jpg' ),
			),
			'call_sign'           => 'WFLR',
			'frequency'           => '95.9 / 96.1 / 96.9 / 101.9 FM / 1570 AM',
			'format'              => 'Country',
			'demo'                => 'Adults 25-64',
			'tagline'             => 'Finger Lakes Country!',
			'market_population'   => 137001,
			'median_hhi'          => 89727,
			'homeownership_pct'   => 72.6,
			'drive_alone_pct'     => 84.8,
			'description'         => '<p>Finger Lakes Country is the region\'s only country music station, broadcasting across five frequencies to cover the widest geographic area of any station in the group. From Penn Yan to Dundee, Watkins Glen to Corning, Geneva to Elmira, if you are in the southern Finger Lakes, WFLR is the station people know.</p>
<p>Mornings start with Larry Timko, a Marconi Award-winning host who has been a fixture of the Finger Lakes for decades. Larry does not just play music. He is a trusted voice in the community, the kind of host whose live read carries the weight of a personal recommendation. Lucas Day handles local news throughout the day, keeping the community connected.</p>
<p>Country is the number one radio format in America with a 13.8% national audience share. In a region like the Finger Lakes, which is rural, agricultural, and community-oriented, that share is even higher. WFLR also carries NASCAR and high school sports, deepening its connection with listeners who care about the things that matter here.</p>',
			'personalities'       => array(
				array(
					'name' => 'Larry Timko',
					'show' => 'Larry Timko Morning Show',
					'time' => '5:30 - 9:00 AM',
				),
				array(
					'name' => 'Lucas Day',
					'show' => 'Local News',
					'time' => 'All Day',
				),
			),
			'coverage_counties'    => array( 'Ontario', 'Yates', 'Schuyler', 'Steuben', 'Chemung', 'Seneca' ),
			'coverage_description' => 'With five frequencies, Finger Lakes Country reaches Penn Yan, Dundee, Watkins Glen, Corning, Elmira, Geneva, Waterloo, and Seneca Falls. The signal covers Ontario, Yates, Schuyler, Steuben, Chemung, and Seneca counties, the broadest coverage footprint of any station in the group.',
		),
		'wgva' => array(
			'name'                => 'WGVA News Radio',
			'page' => array(
				'logo_white' => 'logo-white.svg',
				'eyebrow' => 'WGVA · Radio advertising',
				'lead' => 'Reach the Finger Lakes homeowners and investors who follow the local news all day.',
				'profile_seconds' => 39,
				'stats' => array( 'streaming_hours' => 4976, 'streaming_window' => 'last 28 days' ),
				'persona' => array(
					'name' => 'Bob', 'age' => 62, 'line1' => 'Accountant, Geneva', 'line2' => 'Village planning board member', 'photo' => 'persona.jpg',
					'story' => array(
						'Bob runs a small accounting practice in Geneva and serves on the village planning board. WGVA is on in his kitchen with the morning coffee, in the car between client visits, and at his desk through the afternoon. He listens first for the local items: the weather, the school and road closings, the obituaries, and what the village board or the school district decided last night and what it will mean for his tax bill.',
						'Bob is also the person other people ask. The office\'s bank, attorney and insurance agent are his choice, and neighbors ask him whom to call for theirs. Paul Szmal, who runs programming for FLX Local Media, describes the WGVA audience as older, higher-income listeners who own real estate, stocks and investments and love to travel, and Bob fits: a homeowner with investments who already has the next trip planned. He stays tuned to the national news and talk between the local hours, and to Bills and Syracuse games on weekends, which is why the radio is rarely off.',
					),
					'facts' => array(
						// /Users/scott/flxlocalmedia/inc/helpers.php, wgva median_hhi 96,513 (Census-based; households in the sold demographic)
						array( '$97K', 'Typical household income' ),
						// /Users/scott/flxlocalmedia/inc/helpers.php, wgva homeownership_pct 77.1
						array( '77.1%', 'Own their home' ),
						// /Users/scott/flxlocalmedia/inc/helpers.php, wgva drive_alone_pct 85.7
						array( '85.7%', 'Drive alone to work' ),
						// Census ACS 2024 5-year (2020-2024), Ontario County (05000US36069): B08013 aggregate travel time 1,032,620 minutes divided by B08303 workers 46,367 = 22.27 minutes, rounded to 22. h
						array( '22 min', 'Average commute, each way' ),
					),
				),
				'voices' => array(
					array( 'name' => 'Paul Szmal', 'when' => 'FLX Morning, 5:00 – 9:00 AM', 'blurb' => 'Live reads and endorsements from Paul, beside the local news, farm report and weather listeners start the day with.', 'photo' => 'voices/paul-szmal.jpg', 'photo_alt' => 'Paul Szmal', 'syndicated' => false ),
					array( 'name' => 'Brian Kilmeade', 'when' => 'The Brian Kilmeade Show, 9:00 AM – 12:00 PM', 'blurb' => 'A syndicated show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/brian-kilmeade.jpg', 'photo_alt' => 'Brian Kilmeade', 'syndicated' => true ),
					array( 'name' => 'Jimmy Failla', 'when' => 'Fox Across America, 12:00 – 3:00 PM', 'blurb' => 'A syndicated show, so no live reads. Your spots run in the breaks through the early afternoon.', 'photo' => 'voices/jimmy-failla.jpg', 'photo_alt' => 'Jimmy Failla', 'syndicated' => true ),
					array( 'name' => 'Markley, Van Camp and Robbins', 'when' => 'Afternoon drive, 3:00 – 6:00 PM', 'blurb' => 'A syndicated show, so no live reads. Your spots run in the breaks through the afternoon drive.', 'photo' => 'voices/markley-van-camp-robbins.jpg', 'photo_alt' => 'Markley, Van Camp and Robbins', 'syndicated' => true ),
					array( 'name' => 'Mark Levin', 'when' => 'The Mark Levin Show, 6:00 – 9:00 PM', 'blurb' => 'A syndicated show, so no live reads. Your spots run in the breaks through the evening.', 'photo' => 'voices/mark-levin.jpg', 'photo_alt' => 'Mark Levin', 'syndicated' => true ),
				),
				'market' => array( 'population' => '112K', 'label' => 'people live in Ontario County, home of WGVA.', 'towns' => array( 'Geneva', 'Canandaigua', 'Victor', 'Phelps', 'Clifton Springs', 'Farmington' ) ),
				'ways' => array(
					array( 'title' => 'Spots', 'text' => 'Your commercial in rotation across the news and talk day, in the car and at the office.', 'icon' => 'spots' ),
					array( 'title' => 'Sponsorships', 'text' => 'Put your name on a newscast, the farm report, the winter storm updates or the tax tips.', 'icon' => 'sponsorship' ),
					array( 'title' => 'Sports', 'text' => 'Sponsor Buffalo Bills and Syracuse University football and basketball, or local high school football, in season.', 'icon' => 'sports' ),
					array( 'title' => 'Live remotes', 'text' => 'The WGVA team broadcasts from your location and brings the crowd to your door.', 'icon' => 'remote' ),
					array( 'title' => 'Streaming on FLX.fm', 'text' => 'Reach the same listeners on the FLX.fm stream and app, wherever they are.', 'icon' => 'stream' ),
				),
				'testimonial' => array( 'person' => 'Brian Chapman', 'business' => 'JD Chapman Insurance', 'quote' => '…one or two times a week we get people that call in and then when I ask, they say, ‘Oh, I heard you on the radio. So I thought I’d give you a call.’ And typically those leads convert at a higher rate just because I think they’d have a familiarity with us.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/jd-chapman-insurance/', 'video_seconds' => 94, 'thumb' => 'testimonial.jpg' ),
			),
			'call_sign'           => 'WGVA',
			'frequency'           => '106.3 FM / 1240 AM',
			'format'              => 'News, Talk, and Community',
			'demo'                => 'Adults 35+',
			'tagline'             => 'Finger Lakes News Radio',
			'market_population'   => 112288,
			'median_hhi'          => 96513,
			'homeownership_pct'   => 77.1,
			'drive_alone_pct'     => 85.7,
			'description'         => '<p>WGVA is the Finger Lakes\' flagship news and talk station, serving Geneva, Canandaigua, Seneca Falls, Victor, and the broader Ontario County area. For listeners who want to know what is happening locally, nationally, and in the world, WGVA is where they turn.</p>
<p>Paul Szmal anchors FLX Mornings with local news, community conversation, and the kind of coverage that only a station rooted in the community can deliver. The rest of the day features Gordon Deal, Brian Kilmeade, Jimmy Failla, Markley Van Camp and Robbins, and Mark Levin, a lineup that keeps news and talk listeners engaged from early morning through the evening.</p>
<p>WGVA\'s audience has the highest median household income and the highest rate of college education of any station in the group. These are informed, engaged decision-makers: business owners, professionals, and community leaders who pay attention and take action.</p>',
			'personalities'       => array(
				array(
					'name' => 'Paul Szmal',
					'show' => 'FLX Mornings',
					'time' => '7:30 - 9:00 AM',
				),
				array(
					'name' => 'Gordon Deal',
					'show' => 'This Morning',
					'time' => '5:00 - 7:30 AM',
				),
				array(
					'name' => 'Brian Kilmeade',
					'show' => 'Brian Kilmeade Show',
					'time' => '9:00 AM - 12:00 PM',
				),
				array(
					'name' => 'Mark Levin',
					'show' => 'The Mark Levin Show',
					'time' => '6:00 - 9:00 PM',
				),
			),
			'coverage_counties'    => array( 'Ontario' ),
			'coverage_description' => 'WGVA covers Geneva, Canandaigua, Seneca Falls, Victor, and the Rochester suburbs across Ontario County. The station serves as the primary local news source for the central Finger Lakes. WGVA also carries Buffalo Bills and Syracuse University football and basketball.',
		),
		'waub' => array(
			'name'                => 'WAUB News Radio',
			'page' => array(
				'logo_white' => 'logo-white.svg',
				'eyebrow' => 'WAUB · Radio advertising',
				'lead' => 'Reach Cayuga County adults with higher incomes while they follow the local news and sports.',
				'profile_seconds' => 39,
				'stats' => array( 'streaming_hours' => 3571, 'streaming_window' => 'last 28 days' ),
				'persona' => array(
					'name' => 'Carol', 'age' => 66, 'line1' => 'Retired school district employee', 'line2' => 'Lives in Auburn', 'photo' => 'persona.jpg',
					'story' => array(
						'Carol retired from the school district and now manages the family\'s rental property. WAUB is on in the kitchen over coffee for the weather and the school and road closings, in the car for the local news, and in the evening for the talk shows. She keeps it on through the day to stay current on what is happening in Cayuga County.',
						'She makes the household\'s buying decisions, and the rental property\'s too: the roofer, the furnace service, the doctor, the car repair, and gifts for the grandchildren. She travels when she can and keeps her savings invested. Carol buys from owners she knows by name, and she knows many of them because they sponsor the Cayuga County games and the local news she follows. Paul Szmal, who runs programming, describes this audience as older, with higher incomes, owning real estate, stocks and investments, and fond of travel.',
					),
					'facts' => array(
						// flxlocalmedia inc/helpers.php, waub median_hhi 79,345 (Census ACS 2022, households in the target age group)
						array( '$79K', 'Typical household income' ),
						// flxlocalmedia inc/helpers.php, waub homeownership_pct 75.4
						array( '75.4%', 'Own their home' ),
						// flxlocalmedia inc/helpers.php, waub drive_alone_pct 87.6
						array( '87.6%', 'Drive alone to work' ),
						// Census Reporter, ACS 2024 5-year (2020-2024), tables B08013 aggregate travel time 716,465 min / B08303 workers 31,472 = 22.8 min, Cayuga County (05000US36011). URL: https://api.cen
						array( '23 min', 'Average commute, each way' ),
					),
				),
				'voices' => array(
					array( 'name' => 'Paul Szmal', 'when' => 'FLX Morning, 5:00 – 9:00 AM', 'blurb' => 'Live reads and endorsements from the local host of the morning show, next to the farm report, local news and weather.', 'photo' => 'voices/paul-szmal.jpg', 'photo_alt' => 'Paul Szmal', 'syndicated' => false ),
					array( 'name' => 'Brian Kilmeade', 'when' => 'The Brian Kilmeade Show, 9:00 AM – 12:00 PM', 'blurb' => 'A syndicated news and talk show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/brian-kilmeade.jpg', 'photo_alt' => 'Brian Kilmeade', 'syndicated' => true ),
					array( 'name' => 'Jimmy Failla', 'when' => 'Fox Across America, 12:00 – 3:00 PM', 'blurb' => 'A syndicated talk show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/jimmy-failla.jpg', 'photo_alt' => 'Jimmy Failla', 'syndicated' => true ),
					array( 'name' => 'Markley, Van Camp & Robbins', 'when' => 'Afternoons, 3:00 – 6:00 PM', 'blurb' => 'A syndicated talk show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/markley-van-camp-robbins.jpg', 'photo_alt' => 'Markley, Van Camp & Robbins', 'syndicated' => true ),
					array( 'name' => 'Mark Levin', 'when' => 'The Mark Levin Show, 6:00 – 9:00 PM', 'blurb' => 'A syndicated talk show, so no live reads. Your spots run in the breaks around it.', 'photo' => 'voices/mark-levin.jpg', 'photo_alt' => 'Mark Levin', 'syndicated' => true ),
				),
				'market' => array( 'population' => '76K', 'label' => 'people live in Cayuga County, home of WAUB.', 'towns' => array( 'Auburn', 'Skaneateles', 'Weedsport', 'Port Byron', 'Union Springs', 'Cato' ) ),
				'ways' => array(
					array( 'title' => 'Spots', 'text' => 'Your commercial in rotation across the news and talk day, heard by listeners who tune in for the local report and the weather.', 'icon' => 'spots' ),
					array( 'title' => 'Sponsorships', 'text' => 'Put your name on a newscast, the Ag Report, Farm to Fork or the winter Storm Update.', 'icon' => 'sponsorship' ),
					array( 'title' => 'Local sports', 'text' => 'Buffalo Bills and Syracuse University football and basketball, plus Auburn and Cayuga County high school games, with your commercials in each broadcast.', 'icon' => 'sports' ),
					array( 'title' => 'Live remotes', 'text' => 'The station broadcasts from your location and brings the crowd to your door.', 'icon' => 'remote' ),
					array( 'title' => 'Streaming on FLX.fm', 'text' => 'Reach the same listeners on the FLX.fm stream and app, wherever they are.', 'icon' => 'stream' ),
				),
				'testimonial' => array( 'person' => 'Mike Speno', 'business' => 'Speno Music', 'quote' => 'People are even tuning in for local news programming, and I hear a lot about that.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/speno-music/', 'video_seconds' => 60, 'thumb' => 'testimonial.jpg' ),
			),
			'call_sign'           => 'WAUB',
			'frequency'           => '96.3 FM / 1590 AM',
			'format'              => 'News, Talk, and Local Sports',
			'demo'                => 'Adults 35+',
			'tagline'             => 'Finger Lakes News Radio',
			'market_population'   => 76171,
			'median_hhi'          => 79345,
			'homeownership_pct'   => 75.4,
			'drive_alone_pct'     => 87.6,
			'description'         => '<p>WAUB is Auburn and Cayuga County\'s news and talk station, delivering the same strong programming lineup as its sister station WGVA but focused on the Auburn market. For the communities of Auburn, Weedsport, Skaneateles, and surrounding Cayuga County, WAUB is the station that keeps people informed.</p>
<p>Paul Szmal\'s FLX Mornings provides the local connection, with Gordon Deal, Brian Kilmeade, Jimmy Failla, and Mark Levin rounding out a full day of news and talk. WAUB also carries Buffalo Bills and Syracuse University sports, along with Auburn and Cayuga County high school sports, a deep commitment to the local sports community that builds listener loyalty.</p>
<p>WAUB\'s audience is deeply rooted in Auburn and Cayuga County. These are homeowners, families, and local business people who support their community and respond to advertising from businesses they know and trust.</p>',
			'personalities'       => array(
				array(
					'name' => 'Paul Szmal',
					'show' => 'FLX Mornings',
					'time' => '7:30 - 9:00 AM',
				),
				array(
					'name' => 'Gordon Deal',
					'show' => 'This Morning',
					'time' => '5:00 - 7:30 AM',
				),
				array(
					'name' => 'Brian Kilmeade',
					'show' => 'Brian Kilmeade Show',
					'time' => '9:00 AM - 12:00 PM',
				),
				array(
					'name' => 'Mark Levin',
					'show' => 'The Mark Levin Show',
					'time' => '6:00 - 9:00 PM',
				),
			),
			'coverage_counties'    => array( 'Cayuga' ),
			'coverage_description' => 'WAUB covers Auburn, Weedsport, Skaneateles, and surrounding communities across Cayuga County. The station carries Buffalo Bills, Syracuse University, and local high school sports for the Auburn area.',
		),
		'the-lake' => array(
			'name'                => 'The Lake',
			'page' => array(
				'logo_white' => 'logo-white.svg',
				'eyebrow' => 'WCGR · Radio advertising',
				'lead' => 'Reach Rochester-area women on the station with the fewest commercials.',
				'profile_seconds' => 0,
				'stats' => array( 'streaming_hours' => 14653, 'streaming_window' => 'last 28 days' ),
				'persona' => array(
					'name' => 'Meg', 'age' => 51, 'line1' => 'Marketing manager, Pittsford', 'line2' => 'Lives in Brighton', 'photo' => 'persona.jpg',
					'story' => array(
						'Meg is a marketing manager in Pittsford, and her kids are grown and out of the house. The Lake is on at her desk through the afternoon and in the car on the drive home to Brighton. It plays the songs she grew up on and a few she has not heard in years, so she never has to build a playlist of her own. The commercial breaks are short, so the ads she does hear get heard.',
						'She shares the big household decisions with her husband: the contractor for the kitchen, the financial planner, the dentist, the next car, and where the family eats when the kids are home for a weekend. In our September programming meeting the room described the Lake listener as affluent, white collar and past the school years, with some older listeners who grew up on this music. Listener emails to the station keep saying the same thing: they have not heard that song in years.',
					),
					'facts' => array(
						// /Users/scott/flxlocalmedia/inc/helpers.php, the-lake median_hhi 83,154 (Census ACS 2022, households in the sold demographic, women 35-64, per customer-flx data/census and docs/audi
						array( '$83K', 'Typical household income' ),
						// /Users/scott/flxlocalmedia/inc/helpers.php, the-lake homeownership_pct 69.9 (Census ACS 2022, same women 35-64 basis as the income figure; the all-household Monroe County rate is 6
						array( '69.9%', 'Own their home' ),
						// /Users/scott/flxlocalmedia/inc/helpers.php, the-lake drive_alone_pct 82.4 (Census ACS 2022, Monroe County, matches ~/customer-flx/data/census/ny_counties.json pct_drive_alone).
						array( '82.4%', 'Drive alone to work' ),
						// Census Reporter, ACS 2024 5-year (2020-2024), table B08013 aggregate travel time to work divided by B08303 workers, Ontario + Livingston + Monroe counties combined: 7,782,135 minut
						array( '21 min', 'Average commute, each way' ),
					),
				),
				'voices' => array(
				),
				'market' => array( 'population' => '931K', 'label' => 'people live in the three counties The Lake covers.', 'towns' => array( 'Canandaigua', 'Pittsford', 'Brighton', 'Victor', 'Geneseo', 'Le Roy' ) ),
				'ways' => array(
					array( 'title' => 'Spots', 'text' => 'Your commercial in short breaks, with a limit of six minutes of commercials an hour, so it is heard and not lost in a long break.', 'icon' => 'spots' ),
					array( 'title' => 'Streaming on FLX.fm', 'text' => 'More listening hours are streamed on The Lake than on any other station we run, on lake.fm and in the FLX.fm app.', 'icon' => 'stream' ),
					array( 'title' => 'Shop Local', 'text' => 'Your business named in a Shop Local spot three times a day all year, with a listing on the Finger Lakes Daily News Shop Local page.', 'icon' => 'sponsorship' ),
					array( 'title' => 'Seasonal campaigns', 'text' => 'Holiday, winter and sale-event packages that run on The Lake alone or alongside a sister station.', 'icon' => 'season' ),
				),
				'testimonial' => array( 'person' => 'Ryan Phelps', 'business' => 'Ryan Phelps Auto Sales', 'quote' => 'It went all the way out to Canandaigua with the new lake station. That\'s a big market we\'re trying to push west into.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/ryan-phelps-auto-sales/', 'video_seconds' => 93, 'thumb' => 'testimonial.jpg' ),
			),
			'call_sign'           => 'WCGR',
			'frequency'           => '100.1 / 104.5 FM',
			'format'              => 'Easy Rock',
			'demo'                => 'Women 35-64',
			'tagline'             => 'The Lake 100.1 / 104.5',
			'market_population'   => 756406,
			'median_hhi'          => 83154,
			'homeownership_pct'   => 69.9,
			'drive_alone_pct'     => 82.4,
			'description'         => '<p>The Lake is a premium easy rock station with a strict six-minute-per-hour commercial limit, the lowest commercial load of any station in the group. That means when your ad runs on The Lake, it is not buried in a long commercial break. It stands out. Listeners stay tuned because they know the music is never far away.</p>
<p>The format features the easy rock classics: Eagles, Springsteen, Jackson Browne, James Taylor, and the artists that define relaxed, feel-good listening. The Lake broadcasts on two frequencies, 100.1 and 104.5 FM, covering the largest potential audience of any station in the group by reaching deep into the Rochester market.</p>
<p>The Lake\'s audience skews female, 35-64, and includes the Rochester suburbs of Canandaigua, Pittsford, Geneseo, and Honeoye as well as communities like Naples, Le Roy, and Dansville. For advertisers who want to reach women with purchasing power across the western Finger Lakes and greater Rochester, The Lake delivers a unique combination of premium positioning and broad reach.</p>',
			'personalities'       => array(),
			'coverage_counties'    => array( 'Ontario', 'Livingston', 'Monroe' ),
			'coverage_description' => 'The Lake covers the western Finger Lakes and greater Rochester market, including Canandaigua, Pittsford, Geneseo, Honeoye, Naples, Le Roy, and Dansville across Ontario, Livingston, and Monroe counties. It reaches the largest potential audience of any station in the group.',
		),
	);

	// The fldn station registry is the source for these facts; it overrides the copies above.
	foreach ( $stations as $k => $st ) {
		$stations[ $k ] = flxlm_apply_station_registry( $k, $st );
	}

	if ( $slug && isset( $stations[ $slug ] ) ) {
		return $stations[ $slug ];
	}

	if ( $slug ) {
		return null;
	}

	return $stations;
}

/**
 * Get station data.
 *
 * @return array
 */
function flxlm_get_stations() {
	$list = array(
		array(
			'name'          => 'Mix 98.5',
			'slug'          => 'mix-985',
			'call_sign'     => 'WNYR',
			'frequency'     => '98.5 FM',
			'format'        => 'Adult contemporary',
			'demo'          => 'Women 25-54',
		),
		array(
			'name'          => 'Classic Hits 99.3',
			'slug'          => 'classic-hits-993',
			'call_sign'     => 'WFLK',
			'frequency'     => '99.3 FM',
			'format'        => 'Greatest hits of the \'70s, \'80s, and \'90s',
			'demo'          => 'Adults 35-64',
		),
		array(
			'name'          => '101.7 The Wall',
			'slug'          => 'the-wall',
			'call_sign'     => 'WLLW',
			'frequency'     => '101.7 FM',
			'format'        => 'Classic rock',
			'demo'          => 'Men 35-64',
		),
		array(
			'name'          => 'Finger Lakes Country',
			'slug'          => 'finger-lakes-country',
			'call_sign'     => 'WFLR',
			'frequency'     => '95.9 / 96.1 / 96.9 / 101.9 FM / 1570 AM',
			'format'        => 'Country: #1 format nationally',
			'demo'          => 'Adults 25-64',
		),
		array(
			'name'          => 'WGVA News Radio',
			'slug'          => 'wgva',
			'call_sign'     => 'WGVA',
			'frequency'     => '106.3 FM / 1240 AM',
			'format'        => 'News, talk, and community',
			'demo'          => 'Adults 35+',
		),
		array(
			'name'          => 'WAUB News Radio',
			'slug'          => 'waub',
			'call_sign'     => 'WAUB',
			'frequency'     => '96.3 FM / 1590 AM',
			'format'        => 'News, talk, and local sports',
			'demo'          => 'Adults 35+',
		),
		array(
			'name'          => 'The Lake',
			'slug'          => 'the-lake',
			'call_sign'     => 'WCGR',
			'frequency'     => '100.1 / 104.5 FM',
			'format'        => 'Easy rock: 6 min/hr commercial limit',
			'demo'          => 'Women 35-64',
			'personalities' => 'Premium low-clutter format',
			'reach'         => '756K market (Rochester) · $71K median HHI',
		),
	);
	foreach ( $list as $i => $st ) {
		$list[ $i ] = flxlm_apply_station_registry( $st['slug'], $st );
	}
	return $list;
}

/**
 * Overlay the station registry (assets/data/stations-registry.json, exported from
 * shswanson/fldn stations/registry.json) onto a station array: frequency (main FM only),
 * format, target audience and tagline.
 */
function flxlm_apply_station_registry( $slug, $st ) {
	$reg = function_exists( 'flxlm_station_registry' ) ? flxlm_station_registry( $slug ) : null;
	if ( ! $reg ) {
		return $st;
	}
	if ( ! empty( $reg['frequency_display'] ) ) {
		$st['frequency'] = $reg['frequency_display'];
	}
	if ( isset( $reg['format'] ) ) {
		$st['format'] = $reg['format'];
	}
	if ( isset( $reg['target'] ) ) {
		$st['demo'] = $reg['target'];
	}
	if ( array_key_exists( 'tagline', $reg ) ) {
		$st['tagline'] = $reg['tagline'];
	}
	return $st;
}
