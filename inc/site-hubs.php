<?php
/**
 * Site hubs: the stations hub (/solutions/radio/), the Proof page (/testimonials/)
 * and the homepage share one stylesheet (assets/css/site-hubs.css) and these helpers.
 *
 * Station facts come from flxlm_get_stations() and flxlm_station_page_data()
 * (the station registry wins there), so there is one source per fact.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact URL for the one call to action on every page.
 *
 * @param string $product Product slug, or '' for the general plan request.
 * @return string
 */
function flxlm_hub_contact_url( $product = '' ) {
	$base = home_url( '/contact/' );
	return '' !== $product ? add_query_arg( 'product', rawurlencode( $product ), $base ) : $base . '#review';
}

/**
 * m:ss from seconds.
 *
 * @param int $seconds Seconds.
 * @return string
 */
function flxlm_hub_clock( $seconds ) {
	$seconds = max( 0, (int) $seconds );
	return floor( $seconds / 60 ) . ':' . str_pad( (string) ( $seconds % 60 ), 2, '0', STR_PAD_LEFT );
}

/**
 * Length of a testimonial video in seconds, or 0 when unknown.
 * An optional "video_seconds" meta value wins; otherwise the length measured
 * from the published video file (ffprobe, 2026-11-19), keyed by the page slug.
 *
 * @param int $post_id Testimonial post ID.
 * @return int
 */
function flxlm_hub_video_seconds( $post_id ) {
	$meta = (int) get_post_meta( $post_id, 'video_seconds', true );
	if ( $meta > 0 ) {
		return $meta;
	}
	$measured = array(
		'aster-financial-group'   => 75,
		'church-creative-flooring' => 86,
		'e-v-energy'              => 91,
		'enstech-automotive'      => 90,
		'jd-chapman-insurance'    => 94,
		'main-deck'               => 105,
		'ryan-phelps-auto-sales'  => 93,
		'speno-music'             => 60,
	);
	$slug = get_post_field( 'post_name', $post_id );
	return isset( $measured[ $slug ] ) ? $measured[ $slug ] : 0;
}

/**
 * Everything the hub tiles, the strip and the map need about the seven stations.
 *
 * @return array[]
 */
function flxlm_hub_stations() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out  = array();
	$used = array(); // Map colours already taken, as [r,g,b].
	foreach ( flxlm_get_stations() as $st ) {
		$slug = isset( $st['slug'] ) ? $st['slug'] : '';
		$p    = '' !== $slug ? flxlm_station_page_data( $slug ) : null;
		if ( ! $p ) {
			continue;
		}

		// Map colour: the brand colour, unless another station already uses one
		// too close to it (Mix 98.5 and Finger Lakes Country are both red, WGVA and
		// WAUB share a navy). Then the deeper brand colour, drawn dashed.
		$map_color = $p['colors']['primary'];
		$map_dash  = '';
		$rgb       = flxlm_station_rgb( $map_color );
		foreach ( $used as $u ) {
			$d = sqrt( pow( $rgb[0] - $u[0], 2 ) + pow( $rgb[1] - $u[1], 2 ) + pow( $rgb[2] - $u[2], 2 ) );
			if ( $d < 70 ) {
				$map_color = $p['colors']['deep'];
				$map_dash  = '8 6';
				break;
			}
		}
		$used[] = $rgb;

		$freq     = '' !== $p['frequency_display'] ? $p['frequency_display'] : ( isset( $st['frequency'] ) ? (string) $st['frequency'] : '' );
		$np_slug  = $p['nowplaying_slug'];
		$out[]    = array(
			'slug'      => $slug,
			'name'      => '' !== $p['name'] ? $p['name'] : ( isset( $st['name'] ) ? (string) $st['name'] : $slug ),
			'call'      => $p['call_sign'],
			'url'       => home_url( '/solutions/radio/' . $slug . '/' ),
			'css_vars'  => $p['css_vars'],
			'logo'      => $p['logo_white'],
			'freq'      => $freq,
			'also'      => $p['frequency_also'],
			'format'    => $p['format_label'],
			'target'    => $p['target'],
			'counties'  => $p['counties'],
			'np_url'    => '' !== $np_slug ? 'https://www.fingerlakesdailynews.com/wp-json/fldn/v1/nowplaying/' . rawurlencode( $np_slug ) . '/' : '',
			'map_color' => $map_color,
			'map_dash'  => $map_dash,
		);
	}
	return $out;
}

/**
 * "98.5 FM" with the band in the accent colour (safe HTML).
 *
 * @param string $freq Frequency text.
 * @return string
 */
function flxlm_hub_freq_html( $freq ) {
	return wp_kses( preg_replace( '/\b(FM|AM)\b/', '<span>$1</span>', esc_html( $freq ) ), array( 'span' => array() ) );
}

/**
 * One station tile. $compact is the smaller version used on the homepage strip.
 * The now-playing line starts as "Live now on <frequency>" and is replaced by the
 * song, host or show when the now-playing request succeeds, so it never shows empty.
 *
 * @param array $t       Entry from flxlm_hub_stations().
 * @param bool  $compact Small version.
 * @param bool  $show_np Print the now-playing line (off on the homepage).
 */
function flxlm_hub_station_tile( $t, $compact = false, $show_np = true ) {
	$live = preg_match( '/^[0-9.]+ (FM|AM)$/', $t['freq'] ) ? $t['freq'] : $t['name'];
	?>
	<article class="hb-tile<?php echo $compact ? ' hb-tile--compact' : ''; ?>" style="<?php echo esc_attr( $t['css_vars'] ); ?>">
		<div class="hb-tile__logo">
			<?php if ( $t['logo'] ) : ?>
				<img src="<?php echo esc_url( $t['logo'] ); ?>" alt="<?php echo esc_attr( $t['name'] . ' logo' ); ?>" loading="lazy">
			<?php else : ?>
				<span class="hb-tile__name"><?php echo esc_html( $t['name'] ); ?></span>
			<?php endif; ?>
		</div>
		<h3 class="hb-tile__freq">
			<span class="sr-only"><?php echo esc_html( $t['name'] ); ?>, </span>
			<?php echo flxlm_hub_freq_html( '' !== $t['freq'] ? $t['freq'] : $t['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside the helper. ?>
		</h3>
		<?php if ( '' !== $t['format'] ) : ?>
			<p class="hb-tile__format"><?php echo esc_html( $t['format'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! $compact && '' !== $t['target'] ) : ?>
			<p class="hb-tile__target"><span>Target audience</span> <?php echo esc_html( $t['target'] ); ?></p>
		<?php endif; ?>
		<?php if ( $show_np ) : ?>
		<p class="hb-np"<?php echo '' !== $t['np_url'] ? ' data-np="' . esc_url( $t['np_url'] ) . '"' : ''; ?>>
			<span class="hb-np__dot" aria-hidden="true"></span>
			<span class="hb-np__lab">On air now</span>
			<span class="hb-np__txt"><?php echo esc_html( 'Live now on ' . $live ); ?></span>
		</p>
		<?php endif; ?>
		<a class="hb-tile__link" href="<?php echo esc_url( $t['url'] ); ?>">
			<span><?php echo $compact ? esc_html( 'See ' . $t['name'] ) : 'See the station'; ?><span class="sr-only"><?php echo $compact ? '' : esc_html( ': ' . $t['name'] ); ?></span></span>
			<span aria-hidden="true">&rarr;</span>
		</a>
	</article>
	<?php
}

/**
 * Client video card for the current testimonial post (call inside the loop).
 * Poster with play button and length, the strongest short quote, person and business.
 */
function flxlm_hub_video_card() {
	$id       = get_the_ID();
	$person   = (string) get_post_meta( $id, 'person_name', true );
	$business = (string) get_post_meta( $id, 'business_name', true );
	$industry = (string) get_post_meta( $id, 'industry', true );
	$quote    = trim( (string) get_post_meta( $id, 'quote_short', true ) );
	if ( '' === $quote ) {
		$quote = wp_trim_words( (string) get_post_meta( $id, 'quote_full', true ), 30, '...' );
	}
	$poster = get_post_meta( $id, 'poster_webp', true );
	$poster = $poster ? $poster : get_post_meta( $id, 'poster_jpg', true );
	$pos    = (string) get_post_meta( $id, 'hero_object_position', true );
	$pos    = preg_match( '/^[a-z0-9% .-]{1,30}$/i', $pos ) ? $pos : 'center 35%';
	$has_v  = '' !== flxlm_get_video_url( $id );
	$secs   = $has_v ? flxlm_hub_video_seconds( $id ) : 0;
	$who    = trim( $person . ( '' !== $person && '' !== $business ? ', ' : '' ) . $business );
	?>
	<article class="hb-vcard">
		<div class="hb-vcard__media">
			<?php if ( $poster ) : ?>
				<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( '' !== $who ? $who : 'Client video' ); ?>" width="640" height="360" loading="lazy" style="object-position: <?php echo esc_attr( $pos ); ?>;">
			<?php endif; ?>
			<?php if ( $has_v ) : ?>
				<span class="hb-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg></span>
				<?php if ( $secs ) : ?><span class="hb-len"><?php echo esc_html( flxlm_hub_clock( $secs ) ); ?></span><?php endif; ?>
			<?php endif; ?>
		</div>
		<div class="hb-vcard__body">
			<?php if ( '' !== $industry ) : ?>
				<span class="hb-vcard__tag"><?php echo esc_html( $industry ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $quote ) : ?>
				<p class="hb-vcard__quote">&ldquo;<?php echo esc_html( $quote ); ?>&rdquo;</p>
			<?php endif; ?>
			<p class="hb-vcard__who">
				<?php if ( '' !== $person ) : ?><b><?php echo esc_html( $person ); ?></b><?php endif; ?>
				<?php if ( '' !== $business ) : ?><span><?php echo esc_html( $business ); ?></span><?php endif; ?>
			</p>
			<a class="hb-vcard__go" href="<?php the_permalink(); ?>"><?php echo $has_v ? 'Watch the story' : 'Read the story'; ?> <span aria-hidden="true">&rarr;</span><span class="sr-only"><?php echo esc_html( '' !== $who ? ': ' . $who : '' ); ?></span></a>
		</div>
	</article>
	<?php
}

/**
 * Case studies: the published child pages of the /case-study/ page, newest menu order first.
 * Same summary rule as the case study index template (excerpt, else first paragraph).
 *
 * @return array[] Each: title, url, summary.
 */
function flxlm_hub_case_studies() {
	$parent = get_page_by_path( 'case-study' );
	if ( ! $parent ) {
		return array();
	}
	$q = new WP_Query( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_parent'    => $parent->ID,
		'posts_per_page' => 12,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
	) );
	$out = array();
	while ( $q->have_posts() ) {
		$q->the_post();
		$summary = trim( (string) get_post_field( 'post_excerpt', get_the_ID() ) );
		if ( '' === $summary ) {
			$content = strip_shortcodes( (string) get_post_field( 'post_content', get_the_ID() ) );
			foreach ( (array) preg_split( '/(<\/p>|\r?\n\s*\r?\n)/i', trim( $content ) ) as $part ) {
				$text = trim( wp_strip_all_tags( $part ) );
				if ( '' !== $text ) {
					$summary = $text;
					break;
				}
			}
		}
		$out[] = array(
			'title'   => get_the_title(),
			'url'     => get_permalink(),
			'summary' => wp_trim_words( wp_strip_all_tags( $summary ), 40, '...' ),
		);
	}
	wp_reset_postdata();
	return $out;
}

/**
 * Stylesheet and scripts for the three hub templates.
 */
function flxlm_enqueue_site_hubs_assets() {
	$is_hub   = is_page_template( 'page-templates/template-solutions-radio.php' );
	$is_proof = is_page_template( 'page-templates/template-testimonials.php' );
	$is_home  = is_front_page();
	if ( ! $is_hub && ! $is_proof && ! $is_home ) {
		return;
	}

	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style( 'flxlm-station-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@800;900&display=swap', array(), null );
	$deps = array( 'flxlm-style' );
	if ( $is_hub ) {
		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		$deps[] = 'leaflet';
	}
	wp_enqueue_style( 'flxlm-site-hubs', $uri . '/assets/css/site-hubs.css', $deps, filemtime( $dir . '/assets/css/site-hubs.css' ) );

	if ( $is_hub || $is_home ) {
		$script_deps = array();
		if ( $is_hub ) {
			wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
			$script_deps[] = 'leaflet';
		}
		wp_enqueue_script( 'flxlm-site-hubs', $uri . '/assets/js/site-hubs.js', $script_deps, filemtime( $dir . '/assets/js/site-hubs.js' ), true );

		$map = array();
		if ( $is_hub ) {
			foreach ( flxlm_hub_stations() as $t ) {
				$map[] = array(
					'call'  => $t['call'],
					'name'  => $t['name'],
					'freq'  => $t['freq'],
					'url'   => $t['url'],
					'color' => $t['map_color'],
					'dash'  => $t['map_dash'],
				);
			}
		}
		wp_localize_script( 'flxlm-site-hubs', 'flxlmHubs', array(
			'minSongSeconds' => 90,
			'contoursUrl'    => $uri . '/assets/data/contours-coverage.geojson?v=' . (int) filemtime( $dir . '/assets/data/contours-coverage.geojson' ),
			'stations'       => $map,
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'flxlm_enqueue_site_hubs_assets' );
