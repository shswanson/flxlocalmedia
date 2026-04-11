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
	$slug = isset( $station['slug'] ) ? $station['slug'] : sanitize_title( $station['name'] );
	$link = home_url( '/solutions/radio/' . $slug . '/' );
	?>
	<a href="<?php echo esc_url( $link ); ?>" class="station-card" style="text-decoration: none; color: inherit; display: block;">
		<h3 class="station-card__name"><?php echo esc_html( $station['name'] ); ?></h3>
		<p class="station-card__call"><?php echo esc_html( $station['call_sign'] ); ?> — <?php echo esc_html( $station['frequency'] ); ?></p>
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
			'median_hhi'          => 69906,
			'homeownership_pct'   => 74.2,
			'drive_alone_pct'     => 86.9,
			'description'         => '<p>Mix 98.5 is the Finger Lakes at-work station. From offices in Geneva to shops in Canandaigua, Mix 98.5 is the soundtrack of the workday for women across Ontario, Cayuga, Seneca, Wayne, and Yates counties. The station plays the music people know and love — a mix of current hits and familiar favorites that keeps the energy up without overwhelming the room.</p>
<p>Mornings start with Jim Schreck and Sorah Devlin, who have become the way tens of thousands of Finger Lakes residents begin their day. Jim stays on through mid-morning, Lisa Cruz takes over afternoons, and John Tesh brings his nationally syndicated Intelligence for Your Life show to the evening hours. It is a lineup that keeps listeners engaged from the first alarm to dinnertime.</p>
<p>With the largest estimated weekly audience of any station in the group, Mix 98.5 is the top choice for advertisers who want to reach women with real purchasing power — the household decision-makers who drive consumer spending in the Finger Lakes.</p>',
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
					'time' => '5:00 - 9:00 PM',
				),
			),
			'coverage_counties'    => array( 'Ontario', 'Cayuga', 'Seneca', 'Wayne', 'Yates' ),
			'coverage_description' => 'Mix 98.5 covers the heart of the Finger Lakes, reaching Geneva, Canandaigua, Auburn, Penn Yan, Seneca Falls, and surrounding communities across five counties. The signal also extends into Rochester\'s eastern suburbs.',
		),
		'classic-hits-993' => array(
			'name'                => 'Classic Hits 99.3',
			'call_sign'           => 'WFLK',
			'frequency'           => '99.3 FM',
			'format'              => 'Classic Hits',
			'demo'                => 'Adults 35-64',
			'tagline'             => 'Greatest hits of the \'70s, \'80s, and \'90s',
			'market_population'   => 313434,
			'median_hhi'          => 70374,
			'homeownership_pct'   => 74.0,
			'drive_alone_pct'     => 87.3,
			'description'         => '<p>Classic Hits 99.3 plays the greatest hits of the \'70s, \'80s, and \'90s — the songs that everyone knows, from Fleetwood Mac to Tom Petty to Whitney Houston. It is the station where people sing along on the drive to work and nod their heads while running errands. The format appeals to adults 35-64 with balanced gender reach, making it one of the most versatile advertising platforms in the market.</p>
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
		),
		'the-wall' => array(
			'name'                => '101.7 The Wall',
			'call_sign'           => 'WLLW',
			'frequency'           => '101.7 FM',
			'format'              => 'Classic Rock',
			'demo'                => 'Men 25-64',
			'tagline'             => 'Classic rock for the Finger Lakes',
			'market_population'   => 237263,
			'median_hhi'          => 72669,
			'homeownership_pct'   => 75.3,
			'drive_alone_pct'     => 87.2,
			'description'         => '<p>101.7 The Wall is the Finger Lakes\' home for classic rock — Led Zeppelin, AC/DC, Pink Floyd, the Stones, and everything that defined rock and roll. The format skews male and attracts a loyal audience of men 25-64, many of whom work in trades, construction, manufacturing, and management across the region.</p>
<p>Mornings belong to Ken Paradise and Jeff "Woody" Woodruff, whose Ken and Woody show has built a dedicated following. Woody stays on through mid-morning, keeping the energy and the rock going. It is the kind of station where listeners do not just tune in — they turn it up.</p>
<p>The Wall\'s audience has the highest concentration of construction and trades workers of any station in the group, making it a natural fit for building materials, auto services, equipment suppliers, and any business that serves the working men of the Finger Lakes.</p>',
			'personalities'       => array(
				array(
					'name' => 'Ken Paradise & Jeff "Woody" Woodruff',
					'show' => 'Ken and Woody',
					'time' => '5:30 - 9:00 AM',
				),
				array(
					'name' => 'Jeff "Woody" Woodruff',
					'show' => 'Woody',
					'time' => '9:00 AM - 12:00 PM',
				),
			),
			'coverage_counties'    => array( 'Ontario', 'Seneca', 'Wayne' ),
			'coverage_description' => 'The Wall covers Geneva, Penn Yan, Canandaigua, Bath, and Watkins Glen across Ontario, Seneca, and Wayne counties.',
		),
		'finger-lakes-country' => array(
			'name'                => 'Finger Lakes Country',
			'call_sign'           => 'WFLR',
			'frequency'           => '95.9 / 96.1 / 96.9 / 101.9 FM / 1570 AM',
			'format'              => 'Country',
			'demo'                => 'Adults 25-64',
			'tagline'             => 'Finger Lakes Country!',
			'market_population'   => 137001,
			'median_hhi'          => 74325,
			'homeownership_pct'   => 73.3,
			'drive_alone_pct'     => 84.8,
			'description'         => '<p>Finger Lakes Country is the region\'s only country music station, broadcasting across five frequencies to cover the widest geographic area of any station in the group. From Penn Yan to Dundee, Watkins Glen to Corning, Geneva to Elmira — if you are in the southern Finger Lakes, WFLR is the station people know.</p>
<p>Mornings start with Larry Timko, a Marconi Award-winning host who has been a fixture of the Finger Lakes for decades. Larry does not just play music — he is a trusted voice in the community, the kind of host whose live read carries the weight of a personal recommendation. Lucas Day handles local news throughout the day, keeping the community connected.</p>
<p>Country is the number one radio format in America with a 13.8% national audience share. In a region like the Finger Lakes — rural, agricultural, community-oriented — that share is even higher. WFLR also carries NASCAR and high school sports, deepening its connection with listeners who care about the things that matter here.</p>',
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
			'coverage_description' => 'With five frequencies, Finger Lakes Country reaches Penn Yan, Dundee, Watkins Glen, Corning, Elmira, Geneva, Waterloo, and Seneca Falls. The signal covers Ontario, Yates, Schuyler, Steuben, Chemung, and Seneca counties — the broadest coverage footprint of any station in the group.',
		),
		'wgva' => array(
			'name'                => 'WGVA News Radio',
			'call_sign'           => 'WGVA',
			'frequency'           => '106.3 FM / 1240 AM',
			'format'              => 'News, Talk, and Community',
			'demo'                => 'Adults 35+',
			'tagline'             => 'Finger Lakes News Radio',
			'market_population'   => 112288,
			'median_hhi'          => 76603,
			'homeownership_pct'   => 72.5,
			'drive_alone_pct'     => 85.7,
			'description'         => '<p>WGVA is the Finger Lakes\' flagship news and talk station, serving Geneva, Canandaigua, Seneca Falls, Victor, and the broader Ontario County area. For listeners who want to know what is happening — locally, nationally, and in the world — WGVA is where they turn.</p>
<p>Paul Szmal anchors FLX Mornings with local news, community conversation, and the kind of coverage that only a station rooted in the community can deliver. The rest of the day features Gordon Deal, Brian Kilmeade, Jimmy Failla, Markley Van Camp and Robbins, and Mark Levin — a lineup that keeps news and talk listeners engaged from early morning through the evening.</p>
<p>WGVA\'s audience has the highest median household income and the highest rate of college education of any station in the group. These are informed, engaged decision-makers — business owners, professionals, and community leaders who pay attention and take action.</p>',
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
			'call_sign'           => 'WAUB',
			'frequency'           => '96.3 FM / 1590 AM',
			'format'              => 'News, Talk, and Local Sports',
			'demo'                => 'Adults 35+',
			'tagline'             => 'Finger Lakes News Radio',
			'market_population'   => 76171,
			'median_hhi'          => 63227,
			'homeownership_pct'   => 70.0,
			'drive_alone_pct'     => 87.6,
			'description'         => '<p>WAUB is Auburn and Cayuga County\'s news and talk station, delivering the same strong programming lineup as its sister station WGVA but focused on the Auburn market. For the communities of Auburn, Weedsport, Skaneateles, and surrounding Cayuga County, WAUB is the station that keeps people informed.</p>
<p>Paul Szmal\'s FLX Mornings provides the local connection, with Gordon Deal, Brian Kilmeade, Jimmy Failla, and Mark Levin rounding out a full day of news and talk. WAUB also carries Buffalo Bills and Syracuse University sports, along with Auburn and Cayuga County high school sports — a deep commitment to the local sports community that builds listener loyalty.</p>
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
			'call_sign'           => 'WCGR',
			'frequency'           => '100.1 / 104.5 FM',
			'format'              => 'Easy Rock',
			'demo'                => 'Women 35-64',
			'tagline'             => 'The Lake 100.1 / 104.5',
			'market_population'   => 756406,
			'median_hhi'          => 71450,
			'homeownership_pct'   => 63.7,
			'drive_alone_pct'     => 82.4,
			'description'         => '<p>The Lake is a premium easy rock station with a strict six-minute-per-hour commercial limit — the lowest commercial load of any station in the group. That means when your ad runs on The Lake, it is not buried in a long commercial break. It stands out. Listeners stay tuned because they know the music is never far away.</p>
<p>The format features the easy rock classics — Eagles, Springsteen, Jackson Browne, James Taylor, and the artists that define relaxed, feel-good listening. The Lake broadcasts on two frequencies, 100.1 and 104.5 FM, covering the largest potential audience of any station in the group by reaching deep into the Rochester market.</p>
<p>The Lake\'s audience skews female, 35-64, and includes the Rochester suburbs of Canandaigua, Pittsford, Geneseo, and Honeoye as well as communities like Naples, Le Roy, and Dansville. For advertisers who want to reach women with purchasing power across the western Finger Lakes and greater Rochester, The Lake delivers a unique combination of premium positioning and broad reach.</p>',
			'personalities'       => array(),
			'coverage_counties'    => array( 'Ontario', 'Livingston', 'Monroe' ),
			'coverage_description' => 'The Lake covers the western Finger Lakes and greater Rochester market, including Canandaigua, Pittsford, Geneseo, Honeoye, Naples, Le Roy, and Dansville across Ontario, Livingston, and Monroe counties. It reaches the largest potential audience of any station in the group.',
		),
	);

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
	return array(
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
			'demo'          => 'Men 25-64',
		),
		array(
			'name'          => 'Finger Lakes Country',
			'slug'          => 'finger-lakes-country',
			'call_sign'     => 'WFLR',
			'frequency'     => '95.9 / 96.1 / 96.9 / 101.9 FM / 1570 AM',
			'format'        => 'Country — #1 format nationally',
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
			'format'        => 'Easy rock — 6 min/hr commercial limit',
			'demo'          => 'Women 35-64',
			'personalities' => 'Premium low-clutter format',
			'reach'         => '756K market (Rochester) · $71K median HHI',
		),
	);
}
