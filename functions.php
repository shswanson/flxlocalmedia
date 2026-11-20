<?php
/**
 * FLX Local Media theme functions.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLXLM_VERSION', '2.3.0' );

/**
 * Theme setup.
 */
function flxlm_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
	add_theme_support( 'custom-logo' );

	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'flxlm' ),
		'footer'  => __( 'Footer Navigation', 'flxlm' ),
	) );
}
add_action( 'after_setup_theme', 'flxlm_setup' );

/**
 * Enqueue styles and scripts.
 */
function flxlm_enqueue_assets() {
	$css_ver = filemtime( get_template_directory() . '/assets/css/style.css' );
	$js_ver  = filemtime( get_template_directory() . '/assets/js/main.js' );

	wp_enqueue_style( 'flxlm-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'flxlm-style', get_template_directory_uri() . '/assets/css/style.css', array( 'flxlm-fonts' ), $css_ver );
	wp_enqueue_script( 'flxlm-main', get_template_directory_uri() . '/assets/js/main.js', array(), $js_ver, true );
}
add_action( 'wp_enqueue_scripts', 'flxlm_enqueue_assets' );

/**
 * Favicon, from the final FLX mark (white line art on a navy rounded tile).
 * Only printed when no Site Icon is set in the Customizer, so a Site Icon
 * still wins if someone sets one later. Files live in assets/images/brand/.
 */
function flxlm_favicon_links() {
	if ( has_site_icon() ) {
		return;
	}

	$base = get_template_directory_uri() . '/assets/images/brand/';
	$ver  = rawurlencode( FLXLM_VERSION );

	echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $base . 'favicon.svg?ver=' . $ver ) . '" />' . "\n";
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $base . 'favicon-32.png?ver=' . $ver ) . '" />' . "\n";
	echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $base . 'apple-touch-icon.png?ver=' . $ver ) . '" />' . "\n";
}
add_action( 'wp_head', 'flxlm_favicon_links' );

/**
 * Station page helpers (option D template).
 * ------------------------------------------------------------------------
 * flxlm_station_page_data() turns a station's optional 'page' array in
 * flxlm_get_station_data() into a safe, fully defaulted array for the
 * template. Missing data never raises a notice: empty strings and empty
 * arrays mean "skip that piece".
 */

/**
 * A valid #rrggbb colour, or the default.
 *
 * @param mixed  $value   Candidate colour.
 * @param string $default Fallback.
 * @return string
 */
function flxlm_station_hex( $value, $default ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( preg_match( '/^#([0-9a-f]{3})$/i', $value, $m ) ) {
		$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	return preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtolower( $value ) : $default;
}

/**
 * @param string $hex #rrggbb.
 * @return int[] r, g, b.
 */
function flxlm_station_rgb( $hex ) {
	return array(
		hexdec( substr( $hex, 1, 2 ) ),
		hexdec( substr( $hex, 3, 2 ) ),
		hexdec( substr( $hex, 5, 2 ) ),
	);
}

/**
 * WCAG relative luminance of a #rrggbb colour.
 *
 * @param string $hex Colour.
 * @return float
 */
function flxlm_station_luminance( $hex ) {
	$out = array();
	foreach ( flxlm_station_rgb( $hex ) as $c ) {
		$c     = $c / 255;
		$out[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
}

/**
 * WCAG contrast ratio between two #rrggbb colours.
 *
 * @param string $a Colour.
 * @param string $b Colour.
 * @return float
 */
function flxlm_station_contrast( $a, $b ) {
	$la = flxlm_station_luminance( $a );
	$lb = flxlm_station_luminance( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * Blend $hex toward $toward by $amount (0 to 1).
 *
 * @param string $hex    Base colour.
 * @param string $toward Colour to move toward.
 * @param float  $amount Share of $toward.
 * @return string
 */
function flxlm_station_blend( $hex, $toward, $amount ) {
	$x = flxlm_station_rgb( $hex );
	$y = flxlm_station_rgb( $toward );
	$o = '#';
	for ( $i = 0; $i < 3; $i++ ) {
		$o .= str_pad( dechex( (int) round( $x[ $i ] * ( 1 - $amount ) + $y[ $i ] * $amount ) ), 2, '0', STR_PAD_LEFT );
	}
	return $o;
}

/**
 * Text colour (white or near-black) that reads best on a background.
 *
 * @param string $bg   Background colour.
 * @param string $dark Dark text colour.
 * @return string
 */
function flxlm_station_on_color( $bg, $dark ) {
	return flxlm_station_contrast( $bg, '#ffffff' ) >= flxlm_station_contrast( $bg, $dark ) ? '#ffffff' : $dark;
}

/**
 * Resolve a station asset to a URL, only when the file exists in the theme.
 * Absolute https URLs pass through. Anything else must be a clean relative
 * path: images under assets/images/stations/<slug>/, audio under assets/audio/.
 *
 * @param string $slug Station slug.
 * @param mixed  $rel  Relative path or https URL.
 * @param string $type 'image' or 'audio'.
 * @return string URL, or '' when missing.
 */
function flxlm_station_asset( $slug, $rel, $type = 'image' ) {
	if ( ! is_string( $rel ) || '' === trim( $rel ) ) {
		return '';
	}
	$rel = trim( $rel );
	if ( preg_match( '#^https://#i', $rel ) ) {
		return esc_url_raw( $rel );
	}
	if ( false !== strpos( $rel, '..' ) || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $rel ) || 0 === strpos( $rel, '/' ) ) {
		return '';
	}
	$sub  = 'audio' === $type ? 'assets/audio/' : 'assets/images/stations/' . $slug . '/';
	$path = get_template_directory() . '/' . $sub . $rel;
	if ( ! is_readable( $path ) ) {
		return '';
	}
	return add_query_arg( 'ver', (int) filemtime( $path ), get_template_directory_uri() . '/' . $sub . $rel );
}

/**
 * Normalised page content for one station (the 'page' array), or null if the
 * station does not exist.
 *
 * Schema (every key optional; types after the arrow):
 *   colors            => [ primary, deep, accent ]            #rrggbb strings
 *   logo_white        => string   file under assets/images/stations/<slug>/ or https URL
 *   eyebrow           => string   default "<CALL> · Radio advertising"
 *   frequency_display => string   the main FM frequency only (no AM on the page; Scott 2026-11-19)
 *   frequency_also    => string   other FM frequencies, e.g. 'Also on 96.1 · 96.9 · 101.9 FM'
 *   format_label      => string   default station 'format'
 *   tagline           => string   default station 'tagline'
 *   lead              => string
 *   profile_audio     => string   file under assets/audio/ (no file: hero shows "Listen live")
 *   profile_seconds   => int
 *   nowplaying_slug   => string   FLDN now-playing slug, e.g. mix985
 *   stream_url        => string   https stream URL
 *   stats             => [ weekly int, daily int, in_car int, streaming_hours int, streaming_window string ]
 *   counties          => string[] default station 'coverage_counties'
 *   target            => string   default station 'demo'
 *   persona           => [ name, age int|string, line1, line2, photo, story string[], facts [ [value,label], ... ] ]
 *   voices            => [ [ name, when, blurb, photo, syndicated bool, photo_alt?, photo_pos? ], ... ]
 *   market            => [ population string, label string, towns string[] ]
 *   map_note          => string   optional footnote under the map
 *   ways              => [ [ title, text, icon ], ... ]       icon: spots|sponsorship|remote|stream|contest|news|sports|season
 *   testimonial       => [ person, business, quote, page_url, video_seconds int, thumb ]
 *
 * @param string $slug Station slug.
 * @return array|null
 */
/**
 * One station's facts from the exported station registry, or null.
 */
function flxlm_station_registry( $slug ) {
	static $all = null;
	if ( null === $all ) {
		$file = get_template_directory() . '/assets/data/stations-registry.json';
		$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		$all  = is_array( $json ) && isset( $json['stations'] ) && is_array( $json['stations'] ) ? $json['stations'] : array();
	}
	return isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : null;
}

function flxlm_station_page_data( $slug ) {
	$st = flxlm_get_station_data( $slug );
	if ( ! $st || ! is_array( $st ) ) {
		return null;
	}
	$pg    = isset( $st['page'] ) && is_array( $st['page'] ) ? $st['page'] : array();
	$reach = isset( $st['estimated_reach'] ) && is_array( $st['estimated_reach'] ) ? $st['estimated_reach'] : array();

	// Station facts come from the registry (shswanson/fldn stations/registry.json, exported to
	// assets/data/stations-registry.json). It wins over anything in helpers.php: one source per fact.
	$reg = flxlm_station_registry( $slug );
	if ( $reg ) {
		$map = array( 'frequency_display', 'frequency_also', 'tagline', 'target', 'counties', 'colors', 'nowplaying_slug', 'stream_url', 'profile_audio' );
		foreach ( $map as $k ) {
			if ( array_key_exists( $k, $reg ) ) {
				$pg[ $k ] = $reg[ $k ];
			}
		}
		if ( isset( $reg['format'] ) ) {
			$pg['format_label'] = $reg['format'];
		}
	}

	$str = function ( $arr, $key, $default = '' ) {
		return isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ? trim( (string) $arr[ $key ] ) : $default;
	};
	$list = function ( $arr, $key ) {
		$out = array();
		if ( isset( $arr[ $key ] ) && is_array( $arr[ $key ] ) ) {
			foreach ( $arr[ $key ] as $v ) {
				if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
					$out[] = trim( (string) $v );
				}
			}
		}
		return $out;
	};
	$num = function ( $v ) {
		return is_numeric( $v ) && $v > 0 ? (int) $v : 0;
	};

	$name = isset( $st['name'] ) ? (string) $st['name'] : '';
	$call = isset( $st['call_sign'] ) ? (string) $st['call_sign'] : '';

	// Colours and the derived tokens the stylesheet reads.
	$col     = isset( $pg['colors'] ) && is_array( $pg['colors'] ) ? $pg['colors'] : array();
	$primary = flxlm_station_hex( isset( $col['primary'] ) ? $col['primary'] : '', '#003b5b' );
	$deep    = flxlm_station_hex( isset( $col['deep'] ) ? $col['deep'] : '', flxlm_station_blend( $primary, '#000000', 0.3 ) );
	$accent  = flxlm_station_hex( isset( $col['accent'] ) ? $col['accent'] : '', '#52cfd8' );
	$ink     = flxlm_station_blend( $deep, '#000000', 0.62 );
	// Station colour for small text on white: the primary, or the deep colour, darkened until it reads (4.5:1).
	$link = flxlm_station_contrast( $primary, '#ffffff' ) >= 4.5 ? $primary : $deep;
	for ( $i = 0; $i < 8 && flxlm_station_contrast( $link, '#ffffff' ) < 4.5; $i++ ) {
		$link = flxlm_station_blend( $link, '#000000', 0.2 );
	}
	$vars = array(
		'--st-primary'    => $primary,
		'--st-deep'       => $deep,
		'--st-accent'     => $accent,
		'--st-accent-rgb' => implode( ',', flxlm_station_rgb( $accent ) ),
		'--st-ink'        => $ink,
		'--st-link'       => $link,
		'--st-tint'       => flxlm_station_blend( $primary, '#ffffff', 0.93 ),
		'--st-on-primary' => flxlm_station_on_color( $primary, $ink ),
		'--st-on-accent'  => flxlm_station_on_color( $accent, $ink ),
	);
	$css_vars = '';
	foreach ( $vars as $k => $v ) {
		$css_vars .= $k . ':' . $v . ';';
	}

	// Stats: page values first, else the station's estimated reach.
	$stats_in = isset( $pg['stats'] ) && is_array( $pg['stats'] ) ? $pg['stats'] : array();
	$stats    = array(
		'weekly'           => $num( isset( $stats_in['weekly'] ) ? $stats_in['weekly'] : ( isset( $reach['weekly'] ) ? $reach['weekly'] : 0 ) ),
		'daily'            => $num( isset( $stats_in['daily'] ) ? $stats_in['daily'] : ( isset( $reach['daily'] ) ? $reach['daily'] : 0 ) ),
		'in_car'           => $num( isset( $stats_in['in_car'] ) ? $stats_in['in_car'] : ( isset( $reach['in_car'] ) ? $reach['in_car'] : 0 ) ),
		'streaming_hours'  => $num( isset( $stats_in['streaming_hours'] ) ? $stats_in['streaming_hours'] : 0 ),
		'streaming_window' => $str( $stats_in, 'streaming_window' ),
	);

	// Persona.
	$persona = null;
	if ( isset( $pg['persona'] ) && is_array( $pg['persona'] ) ) {
		$pp    = $pg['persona'];
		$facts = array();
		if ( isset( $pp['facts'] ) && is_array( $pp['facts'] ) ) {
			foreach ( $pp['facts'] as $f ) {
				if ( is_array( $f ) && isset( $f[0], $f[1] ) && is_scalar( $f[0] ) && is_scalar( $f[1] ) && '' !== trim( (string) $f[0] ) ) {
					$facts[] = array( trim( (string) $f[0] ), trim( (string) $f[1] ) );
				}
			}
		}
		$persona = array(
			'name'  => $str( $pp, 'name' ),
			'age'   => $str( $pp, 'age' ),
			'line1' => $str( $pp, 'line1' ),
			'line2' => $str( $pp, 'line2' ),
			'photo' => flxlm_station_asset( $slug, $str( $pp, 'photo' ) ),
			'story' => $list( $pp, 'story' ),
			'facts' => $facts,
		);
		if ( '' === $persona['name'] && ! $persona['story'] ) {
			$persona = null;
		}
	}

	// Voices.
	$voices = array();
	if ( isset( $pg['voices'] ) && is_array( $pg['voices'] ) ) {
		foreach ( $pg['voices'] as $v ) {
			if ( ! is_array( $v ) || '' === $str( $v, 'name' ) ) {
				continue;
			}
			$voices[] = array(
				'name'       => $str( $v, 'name' ),
				'when'       => $str( $v, 'when' ),
				'blurb'      => $str( $v, 'blurb' ),
				'photo'      => flxlm_station_asset( $slug, $str( $v, 'photo' ) ),
				'photo_alt'  => $str( $v, 'photo_alt', $str( $v, 'name' ) ),
				'photo_pos'  => preg_match( '/^[a-z0-9% .-]{1,30}$/i', $str( $v, 'photo_pos' ) ) ? $str( $v, 'photo_pos' ) : '',
				'syndicated' => ! empty( $v['syndicated'] ),
			);
		}
	}

	// Market.
	$mk     = isset( $pg['market'] ) && is_array( $pg['market'] ) ? $pg['market'] : array();
	$market = array(
		'population' => $str( $mk, 'population' ),
		'label'      => $str( $mk, 'label' ),
		'towns'      => $list( $mk, 'towns' ),
	);

	// Ways to advertise.
	$ways = array();
	if ( isset( $pg['ways'] ) && is_array( $pg['ways'] ) ) {
		foreach ( $pg['ways'] as $w ) {
			if ( is_array( $w ) && '' !== $str( $w, 'title' ) ) {
				$ways[] = array(
					'title' => $str( $w, 'title' ),
					'text'  => $str( $w, 'text' ),
					'icon'  => $str( $w, 'icon' ),
				);
			}
		}
	}

	// Testimonial.
	$testimonial = null;
	if ( isset( $pg['testimonial'] ) && is_array( $pg['testimonial'] ) && '' !== $str( $pg['testimonial'], 'quote' ) ) {
		$t           = $pg['testimonial'];
		$testimonial = array(
			'person'        => $str( $t, 'person' ),
			'business'      => $str( $t, 'business' ),
			'quote'         => $str( $t, 'quote' ),
			'page_url'      => preg_match( '#^https?://#i', $str( $t, 'page_url' ) ) ? esc_url_raw( $str( $t, 'page_url' ) ) : '',
			'video_seconds' => $num( isset( $t['video_seconds'] ) ? $t['video_seconds'] : 0 ),
			'thumb'         => flxlm_station_asset( $slug, $str( $t, 'thumb' ) ),
		);
	}

	$stream = $str( $pg, 'stream_url' );

	return array(
		'slug'              => $slug,
		'name'              => $name,
		'call_sign'         => $call,
		'css_vars'          => $css_vars,
		'colors'            => array(
			'primary' => $primary,
			'deep'    => $deep,
			'accent'  => $accent,
		),
		'logo_white'        => flxlm_station_asset( $slug, $str( $pg, 'logo_white' ) ),
		'eyebrow'           => $str( $pg, 'eyebrow', $call ? $call . ' · Radio advertising' : '' ),
		'frequency_display' => $str( $pg, 'frequency_display', isset( $st['frequency'] ) ? (string) $st['frequency'] : '' ),
		'frequency_also'    => $str( $pg, 'frequency_also' ),
		'format_label'      => $str( $pg, 'format_label', isset( $st['format'] ) ? (string) $st['format'] : '' ),
		'tagline'           => $str( $pg, 'tagline', isset( $st['tagline'] ) ? (string) $st['tagline'] : '' ),
		'lead'              => $str( $pg, 'lead' ),
		'profile_audio'     => flxlm_station_asset( $slug, $str( $pg, 'profile_audio' ), 'audio' ),
		'profile_seconds'   => $num( isset( $pg['profile_seconds'] ) ? $pg['profile_seconds'] : 0 ),
		'nowplaying_slug'   => preg_replace( '/[^a-z0-9_-]/i', '', $str( $pg, 'nowplaying_slug' ) ),
		'stream_url'        => preg_match( '#^https://#i', $stream ) ? esc_url_raw( $stream ) : '',
		'stats'             => $stats,
		'counties'          => isset( $pg['counties'] ) ? $list( $pg, 'counties' ) : ( isset( $st['coverage_counties'] ) && is_array( $st['coverage_counties'] ) ? array_values( array_filter( array_map( 'strval', $st['coverage_counties'] ) ) ) : array() ),
		'target'            => $str( $pg, 'target', isset( $st['demo'] ) ? (string) $st['demo'] : '' ),
		'persona'           => $persona,
		'voices'            => $voices,
		'market'            => $market,
		'map_note'          => $str( $pg, 'map_note' ),
		'ways'              => $ways,
		'testimonial'       => $testimonial,
	);
}

/**
 * Small inline SVG icons for the "Ways to advertise" tiles.
 *
 * @param string $name Icon key. Unknown keys get the spots icon.
 * @return string SVG markup (static, safe).
 */
function flxlm_station_way_icon( $name ) {
	$open  = '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
	$icons = array(
		'spots'       => '<path d="M6 24v0M13 16v16M20 8v32M27 14v20M34 19v10M41 22v4"/>',
		'sponsorship' => '<path d="M24 5l5.5 12 13 1.5-9.7 8.8 2.7 12.9L24 33.3 12.5 40.2l2.7-12.9L5.5 18.5l13-1.5z"/>',
		'remote'      => '<path d="M24 44S10 30 10 20a14 14 0 0128 0c0 10-14 24-14 24z"/><circle cx="24" cy="20" r="5"/>',
		'stream'      => '<circle cx="24" cy="24" r="19"/><path d="M20 15l14 9-14 9z" fill="currentColor"/>',
		'contest'     => '<rect x="6" y="18" width="36" height="24" rx="3"/><path d="M24 18v24M6 28h36M24 18c-6 0-10-3-8-6s8 0 8 6c0-6 6-9 8-6s-2 6-8 6z"/>',
		'news'        => '<rect x="7" y="8" width="34" height="32" rx="3"/><path d="M14 16h20M14 23h20M14 30h12"/>',
		'sports'      => '<circle cx="24" cy="24" r="18"/><path d="M6 24h36M24 6c-8 6-8 30 0 36M24 6c8 6 8 30 0 36"/>',
		'season'      => '<rect x="7" y="10" width="34" height="31" rx="3"/><path d="M7 20h34M16 6v8M32 6v8"/>',
	);
	$key = isset( $icons[ $name ] ) ? $name : 'spots';
	return $open . $icons[ $key ] . '</svg>';
}

/**
 * Enqueue station page assets (template-station.php only): Leaflet, the
 * coverage map, the option D stylesheet and the page script.
 */
function flxlm_enqueue_station_assets() {
	if ( ! is_page_template( 'page-templates/template-station.php' ) ) {
		return;
	}

	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style( 'flxlm-station-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@800;900&display=swap', array(), null );
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_style( 'flxlm-station', $uri . '/assets/css/station.css', array( 'flxlm-style', 'leaflet' ), filemtime( $dir . '/assets/css/station.css' ) );

	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

	$map_ver = filemtime( $dir . '/assets/js/station-map.js' );
	wp_enqueue_script( 'flxlm-station-map', $uri . '/assets/js/station-map.js', array( 'leaflet' ), $map_ver, true );

	$page_slug = get_post_field( 'post_name', get_post() );
	$page      = flxlm_station_page_data( $page_slug );

	if ( $page ) {
		wp_localize_script( 'flxlm-station-map', 'flxlmStation', array(
			'callSign'            => $page['call_sign'],
			'color'               => $page['colors']['primary'],
			'contoursUrl'         => $uri . '/assets/data/contours.geojson',
			'contoursExtendedUrl' => $uri . '/assets/data/contours-coverage.geojson?v=' . filemtime( $dir . '/assets/data/contours-coverage.geojson' ),
		) );

		wp_enqueue_script( 'flxlm-station-page', $uri . '/assets/js/station-page.js', array(), filemtime( $dir . '/assets/js/station-page.js' ), true );
		wp_localize_script( 'flxlm-station-page', 'flxlmStationPage', array(
			'nowPlayingUrl'   => $page['nowplaying_slug'] ? 'https://www.fingerlakesdailynews.com/wp-json/fldn/v1/nowplaying/' . rawurlencode( $page['nowplaying_slug'] ) . '/' : '',
			'streamUrl'       => $page['stream_url'],
			'profileSeconds'  => $page['profile_seconds'],
			'minSongSeconds'  => 90,
			'stationName'     => $page['name'],
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'flxlm_enqueue_station_assets' );

// Include CPT, helpers, forms, SEO.
require_once get_template_directory() . '/inc/cpt-testimonials.php';
require_once get_template_directory() . '/inc/cpt-jobs.php';
require_once get_template_directory() . '/inc/careers-blocks.php';
require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/hidden-testimonials.php';
require_once get_template_directory() . '/inc/forms.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/disable-block-editor.php';
