<?php
/**
 * SEO for flxlocalmedia.com: titles, descriptions, canonicals, Open Graph,
 * Twitter cards, JSON-LD, sitemap, robots.txt, llms.txt and IndexNow.
 *
 * Where the work lives:
 *   inc/seo-content.php   one "entry" per public page (title, description, canonical,
 *                         share-image copy), built from each page's own data
 *   inc/seo-schema.php    the JSON-LD graph for an entry
 *   inc/seo.php           (this file) prints the tags and owns the sitemap, robots.txt,
 *                         llms.txt and IndexNow
 *
 * Share images are files in assets/images/og/<key>.png, made by the generator in
 * the fldn repo (scripts/flxlm-og/). A page without its own image gets default.png.
 *
 * Hooks into wp_head at priority 1 (meta tags) and priority 5 (JSON-LD).
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/seo-content.php';
require_once get_template_directory() . '/inc/seo-schema.php';

/*--------------------------------------------------------------
 * 1. Share images
 *--------------------------------------------------------------*/

/**
 * URL of a page's share image (1200 x 630 PNG), or the default image when that
 * page has none yet.
 *
 * @param string $key Share-image key from the page's entry.
 * @return string
 */
function flxlm_seo_og_url( $key ) {
	$dir = get_template_directory() . '/assets/images/og/';
	$rel = 'assets/images/og/' . sanitize_file_name( $key ) . '.png';
	if ( '' === $key || ! is_readable( $dir . sanitize_file_name( $key ) . '.png' ) ) {
		$key = 'default';
		$rel = 'assets/images/og/default.png';
		if ( ! is_readable( $dir . 'default.png' ) ) {
			// Before the generator has run: the old default image.
			return get_theme_file_uri( 'assets/images/og-default.png' );
		}
	}
	return add_query_arg( 'v', (int) filemtime( get_template_directory() . '/' . $rel ), get_theme_file_uri( $rel ) );
}

/**
 * What the share-image generator needs, one row per image: the copy, colors and
 * local files. Read by scripts/flxlm-og/export-manifest.php in the fldn repo.
 *
 * @return array[]
 */
function flxlm_seo_og_manifest() {
	$rows = array();
	foreach ( flxlm_seo_manifest( false ) as $key => $e ) {
		if ( empty( $e['og'] ) ) {
			continue;
		}
		$rows[] = array(
			'key'   => $key,
			'kind'  => $e['kind'],
			'url'   => $e['url'],
			'title' => $e['og_title'],
			'og'    => $e['og'],
		);
	}
	$c      = flxlm_seo_color( 'navy' );
	$rows[] = array(
		'key'   => 'default',
		'kind'  => 'default',
		'url'   => flxlm_seo_public_url( '/' ),
		'title' => 'FLX Local Media',
		'og'    => array_merge( array(
			'kicker'   => 'Finger Lakes marketing',
			'headline' => 'Radio, news and digital for the Finger Lakes',
			'promise'  => 'Seven radio stations, Finger Lakes Daily News, digital and events from one local team.',
			'logo'     => '',
			'logos'    => flxlm_seo_station_logos(),
			'poster'   => '',
			'quote'    => '',
			'who'      => '',
			'position' => '',
		), array( 'bg' => $c['bg'], 'accent' => '#e3a72f', 'deep' => $c['deep'], 'ink' => $c['ink'], 'logo_ink' => $c['logo'] ) ),
	);
	return $rows;
}

/*--------------------------------------------------------------
 * 2. Meta Tags (description, canonical, Open Graph, Twitter Cards)
 *--------------------------------------------------------------*/

// One canonical per page, from the page's entry (www host, trailing slash), instead of core's.
add_action( 'init', function () {
	remove_action( 'wp_head', 'rel_canonical' );
} );

add_action( 'wp_head', 'flxlm_seo_meta_tags', 1 );

/**
 * Output the canonical, meta description, Open Graph and Twitter Card tags.
 */
function flxlm_seo_meta_tags() {
	$e = flxlm_seo_current_entry();
	if ( ! $e || '' === $e['title'] ) {
		return;
	}

	$image = flxlm_seo_og_url( $e['key'] );
	$alt   = $e['og_title'] . ' (FLX Local Media)';

	echo "\n<!-- FLXLM SEO -->\n";

	if ( '' !== $e['description'] ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $e['description'] ) );
	}
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $e['canonical'] ) );

	$og = array(
		'og:title'            => $e['og_title'],
		'og:description'      => $e['description'],
		'og:url'              => $e['canonical'],
		'og:site_name'        => 'FLX Local Media',
		'og:locale'           => 'en_US',
		'og:type'             => $e['og_type'],
		'og:image'            => $image,
		'og:image:secure_url' => $image,
		'og:image:type'       => 'image/png',
		'og:image:width'      => '1200',
		'og:image:height'     => '630',
		'og:image:alt'        => $alt,
	);
	if ( 'article' === $e['og_type'] ) {
		$og['article:published_time'] = flxlm_seo_iso( $e['published'] );
		$og['article:modified_time']  = flxlm_seo_iso( $e['modified'] );
	}
	foreach ( $og as $prop => $val ) {
		if ( '' === $val || null === $val ) {
			continue;
		}
		printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $prop ), esc_attr( $val ) );
	}

	$twitter = array(
		'twitter:card'        => 'summary_large_image',
		'twitter:title'       => $e['og_title'],
		'twitter:description' => $e['description'],
		'twitter:image'       => $image,
		'twitter:image:alt'   => $alt,
	);
	foreach ( $twitter as $name => $val ) {
		if ( '' === $val || null === $val ) {
			continue;
		}
		printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $name ), esc_attr( $val ) );
	}

	echo "<!-- /FLXLM SEO -->\n\n";
}

/**
 * Pages that stay out of search say so through core's robots tag. Hidden
 * testimonials already print their own tag (inc/hidden-testimonials.php).
 */
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_singular( 'flxlm_testimonial' ) ) {
		return $robots;
	}
	$e = flxlm_seo_current_entry();
	if ( $e && ! empty( $e['noindex'] ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

/*--------------------------------------------------------------
 * 2b. Canonical overrides (used by IndexNow and anything that asks core)
 *--------------------------------------------------------------*/

add_filter( 'get_canonical_url', 'flxlm_seo_contact_canonical', 10, 2 );

/**
 * Point contact child pages (Geneva, Auburn, Penn Yan) to /contact/.
 *
 * @param string  $canonical The canonical URL.
 * @param WP_Post $post      The post object.
 * @return string
 */
function flxlm_seo_contact_canonical( $canonical, $post ) {
	if ( $post->post_parent ) {
		$parent = get_post( $post->post_parent );
		if ( $parent && 'contact' === $parent->post_name ) {
			return get_permalink( $parent );
		}
	}
	return $canonical;
}

/*--------------------------------------------------------------
 * 2c. Methodology: /methodology-2/ -> /methodology/
 *--------------------------------------------------------------*/

add_action( 'template_redirect', 'flxlm_seo_methodology_redirect' );

/**
 * 301 the duplicate methodology page to /methodology/. The duplicate also
 * carries a canonical to /methodology/ and stays out of the sitemap (its entry
 * in inc/seo-content.php), so a search engine that sees it first gets the same
 * answer.
 *
 * Only fires once /methodology/ is using the methodology template, so nothing
 * breaks while /methodology/ is still empty. Matched by slug: page IDs differ
 * between the live site and staging.
 */
function flxlm_seo_methodology_redirect() {
	if ( ! is_page( 'methodology-2' ) ) {
		return;
	}

	$target = get_page_by_path( 'methodology' );
	if ( ! $target || 'publish' !== $target->post_status ) {
		return;
	}

	$slug = (string) get_page_template_slug( $target->ID );
	if ( 'template-methodology.php' !== basename( $slug ) ) {
		return;
	}

	wp_safe_redirect( get_permalink( $target ), 301 );
	exit;
}

add_filter( 'get_canonical_url', 'flxlm_seo_job_canonical', 10, 2 );

/**
 * Honor `job_canonical_url` meta on flxlm_job singles.
 *
 * Jobs are mirrored on fingerlakesdailynews.com which owns the JobPosting
 * schema and the Indexing API ping; this points the canonical signal at
 * the FLDN twin so search engines treat them as one listing.
 *
 * @param string  $canonical The canonical URL.
 * @param WP_Post $post      The post object.
 * @return string
 */
function flxlm_seo_job_canonical( $canonical, $post ) {
	if ( 'flxlm_job' !== $post->post_type ) {
		return $canonical;
	}
	$override = get_post_meta( $post->ID, 'job_canonical_url', true );
	return $override ?: $canonical;
}

/*--------------------------------------------------------------
 * 3. Title Tag Filter
 *--------------------------------------------------------------*/

add_filter( 'pre_get_document_title', 'flxlm_seo_document_title', 10 );

// Browser-tab titles use a pipe, never a dash, between the page and the site name.
add_filter( 'document_title_separator', function () {
	return '|';
} );

/**
 * Override WordPress document title with the page's SEO title (60 characters
 * or fewer, brand last).
 *
 * @param string $title The current title.
 * @return string
 */
function flxlm_seo_document_title( $title ) {
	$e = flxlm_seo_current_entry();
	if ( $e && '' !== $e['title'] ) {
		return $e['title'];
	}
	return $title;
}

/*--------------------------------------------------------------
 * 4. JSON-LD Structured Data
 *--------------------------------------------------------------*/

add_action( 'wp_head', 'flxlm_seo_jsonld', 5 );

/**
 * Output the page's JSON-LD graph (see inc/seo-schema.php).
 */
function flxlm_seo_jsonld() {
	$e = flxlm_seo_current_entry();
	if ( ! $e ) {
		return;
	}
	$graph = flxlm_seo_graph( $e );
	if ( ! $graph ) {
		return;
	}
	// "<" is escaped so no field can close the script tag early.
	$json = wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
	if ( ! $json ) {
		return;
	}
	echo '<script type="application/ld+json">' . $json . "</script>\n";
}

/*--------------------------------------------------------------
 * 5. Sitemap
 *--------------------------------------------------------------*/

/*
 * Drop the XSL stylesheet line from the core sitemaps. Chrome is removing
 * XSLT and warns on every page that uses it. Search engines never read the
 * stylesheet; without it, browsers show their own plain XML view instead.
 */
add_filter( 'wp_sitemaps_stylesheet_url', '__return_false' );
add_filter( 'wp_sitemaps_stylesheet_index_url', '__return_false' );

/**
 * Pages kept out of the sitemap, by path. Page IDs differ between the live
 * site and staging, so these are paths.
 *
 *   240e1cc9-pricing-2026   unlisted pricing page (noindex)
 *   methodology-2           duplicate of /methodology/ (301 and canonical)
 *   brand                   internal brand document (noindex)
 *   resources               redirects to the home page until it has content
 *   contact/*               office pages show the /contact/ content and name it as their canonical
 *   finger-lakes-daily-news-com-seo   older copy of the FLDN SEO case study
 *   solutions/example       the product template, never a real page
 *
 * @return string[]
 */
function flxlm_seo_sitemap_excluded_paths() {
	return apply_filters( 'flxlm_seo_sitemap_excluded_paths', array(
		'240e1cc9-pricing-2026',
		'methodology-2',
		'brand',
		'resources',
		'contact/geneva',
		'contact/auburn',
		'contact/penn-yan',
		'finger-lakes-daily-news-com-seo',
		'case-study/finger-lakes-daily-news-com-seo',
		'solutions/example',
	) );
}

add_filter( 'wp_sitemaps_posts_query_args', 'flxlm_seo_sitemap_exclude', 10, 2 );

/**
 * Keep duplicates and private pages out of the core sitemap.
 *
 * @param array  $args      WP_Query args for the sitemap.
 * @param string $post_type The post type.
 * @return array
 */
function flxlm_seo_sitemap_exclude( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$exclude = array();
		foreach ( flxlm_seo_sitemap_excluded_paths() as $path ) {
			$p = get_page_by_path( $path );
			if ( $p ) {
				$exclude[] = (int) $p->ID;
			}
		}
		$args['post__not_in'] = array_values( array_unique( array_merge( ! empty( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $exclude ) ) );
	}

	// Jobs mirrored on Finger Lakes Daily News name that copy as their canonical.
	if ( 'flxlm_job' === $post_type ) {
		$args['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => 'job_canonical_url', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'job_canonical_url', 'value' => '', 'compare' => '=' ),
		);
	}
	return $args;
}

// No author pages and no thin taxonomy pages in the sitemap.
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );

add_filter( 'wp_sitemaps_taxonomies', function ( $taxonomies ) {
	unset( $taxonomies['flxlm_service'] );
	return $taxonomies;
} );

add_action( 'wp_sitemaps_init', 'flxlm_seo_register_sitemap_extras' );

/**
 * List the careers page in the sitemap. It is a post type archive, which core
 * sitemaps do not include.
 *
 * @return void
 */
function flxlm_seo_register_sitemap_extras() {
	if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
		return;
	}

	if ( ! class_exists( 'FLXLM_Sitemap_Extras' ) ) {
		/**
		 * One extra sitemap: pages that are archives, not posts.
		 */
		class FLXLM_Sitemap_Extras extends WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'flxlm';
				$this->object_type = 'flxlm';
			}

			public function get_url_list( $page_num, $object_subtype = '' ) {
				$link = get_post_type_archive_link( 'flxlm_job' );
				return $link ? array( array( 'loc' => $link ) ) : array();
			}

			public function get_max_num_pages( $object_subtype = '' ) {
				return get_post_type_archive_link( 'flxlm_job' ) ? 1 : 0;
			}
		}
	}

	wp_register_sitemap_provider( 'flxlm', new FLXLM_Sitemap_Extras() );
}

/*--------------------------------------------------------------
 * 5b. robots.txt
 *--------------------------------------------------------------*/

// Priority -1: core's own rules are already in $output, and the Sitemap line is added at 0.
add_filter( 'robots_txt', 'flxlm_seo_robots_txt', -1, 2 );

/**
 * Add our rules to the public robots.txt. A private site keeps core's
 * "Disallow: /" untouched.
 *
 * @param string $output Robots.txt so far.
 * @param bool   $public Whether the site is public.
 * @return string
 */
function flxlm_seo_robots_txt( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	$output .= "Disallow: /?s=\n";
	$output .= "Disallow: /search/\n";
	$output .= "Disallow: /*?flxlm_vtt=\n";
	$output .= "# For AI assistants: " . flxlm_seo_public_url( '/llms.txt' ) . "\n";
	return $output;
}

/*--------------------------------------------------------------
 * 5c. llms.txt
 *
 * A plain list of the key pages with one line each, for AI assistants
 * (llmstxt.org). Built from the same entries as the head tags, so it never
 * disagrees with them. Cached for six hours and cleared whenever a post saves.
 *--------------------------------------------------------------*/

add_action( 'template_redirect', 'flxlm_seo_serve_llms', 0 );

/**
 * Serve /llms.txt.
 */
function flxlm_seo_serve_llms() {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( 'llms.txt' !== $path ) {
		return;
	}

	$text = get_transient( 'flxlm_llms_txt' );
	if ( ! is_string( $text ) || '' === $text ) {
		$text = flxlm_seo_llms_txt();
		set_transient( 'flxlm_llms_txt', $text, 6 * HOUR_IN_SECONDS );
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=3600' );
	if ( ! get_option( 'blog_public' ) ) {
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
	echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text.
	exit;
}

add_action( 'save_post', function () {
	delete_transient( 'flxlm_llms_txt' );
} );
add_action( 'deleted_post', function () {
	delete_transient( 'flxlm_llms_txt' );
} );

/**
 * A short label for a page in a list.
 *
 * @param array $e Entry.
 * @return string
 */
function flxlm_seo_label( array $e ) {
	if ( 'home' === $e['kind'] ) {
		return 'Home';
	}
	if ( ! empty( $e['data']['name'] ) ) {
		return $e['data']['name'];
	}
	if ( ! empty( $e['crumbs'] ) ) {
		$last = end( $e['crumbs'] );
		return $last[0];
	}
	return $e['og_title'];
}

/**
 * One list line: - [Label](url): description.
 *
 * @param array $e Entry.
 * @return string
 */
function flxlm_seo_llms_line( array $e ) {
	$text = $e['description'];
	if ( 'testimonial' === $e['kind'] && ! empty( $e['data']['quote'] ) ) {
		// "Client video, Speno Music in Downtown Auburn, NY: quote".
		$text = 'Client video, ' . $e['data']['business'] . ( $e['data']['place'] ? ' in ' . $e['data']['place'] : '' ) . ': ' . $e['data']['quote'];
	}
	return '- [' . flxlm_seo_label( $e ) . '](' . $e['url'] . '): ' . $text . "\n";
}

/**
 * Where a page sits in its llms.txt list. Hubs first, then the pages in menu
 * order; anything not named keeps its place after them.
 *
 * @param array $e Entry.
 * @return int
 */
function flxlm_seo_llms_rank( array $e ) {
	$order = array(
		'home', 'solutions', 'about', 'contact',
		'radio',
		'fldn', 'newsletter', 'discover-flx', 'flx-finest', 'flx-fm', 'apps',
		'sports', 'shop-local', 'contests', 'seasonal', 'events',
		'digital', 'website-starter', 'listings', 'digital-advertising', 'content-marketing',
		'why-radio', 'methodology', 'testimonials', 'case-study',
		'privacy-policy', 'terms',
	);
	$i = array_search( $e['key'], $order, true );
	if ( false !== $i ) {
		return (int) $i;
	}
	// Stations, testimonials, case studies and jobs follow their hub.
	return 100 + ( 'case-study' === $e['kind'] ? 1 : 0 );
}

/**
 * The text of /llms.txt.
 *
 * @return string
 */
function flxlm_seo_llms_txt() {
	$all = flxlm_seo_manifest( false );

	$groups = array(
		'Company'                      => array(),
		'Radio stations'               => array(),
		'Media products'               => array(),
		'Advertising products'         => array(),
		'Digital'                      => array(),
		'Proof: client stories and case studies' => array(),
		'Careers'                      => array(),
		'Legal'                        => array(),
	);
	$other = array();
	foreach ( $all as $e ) {
		$k = $e['kind'];
		if ( in_array( $k, array( 'home', 'company', 'office' ), true ) ) {
			// Offices share the contact page's canonical; list the contact page, not each office.
			if ( 'office' !== $k ) {
				$groups['Company'][] = $e;
			}
		} elseif ( 'station' === $k || 'radio' === $e['key'] ) {
			$groups['Radio stations'][] = $e;
		} elseif ( 'product' === $k ) {
			$g = $e['menu_group'];
			if ( 'Advertising' === $g ) {
				$groups['Advertising products'][] = $e;
			} elseif ( 'Digital' === $g ) {
				$groups['Digital'][] = $e;
			} else {
				$groups['Media products'][] = $e;
			}
		} elseif ( in_array( $k, array( 'proof', 'testimonial', 'case-study' ), true ) || in_array( $e['key'], array( 'testimonials', 'case-study' ), true ) ) {
			$groups['Proof: client stories and case studies'][] = $e;
		} elseif ( 'job' === $k || 'careers' === $e['key'] ) {
			$groups['Careers'][] = $e;
		} elseif ( 'legal' === $k ) {
			$groups['Legal'][] = $e;
		} elseif ( 'solutions' === $e['key'] ) {
			$groups['Company'][] = $e;
		} else {
			$other[] = $e;
		}
	}
	$groups['Company'] = array_merge( $groups['Company'], $other );

	$out  = "# FLX Local Media\n\n";
	$out .= "> FLX Local Media (a d/b/a of TOTIB Media, Inc.) is a marketing and media company in the Finger Lakes of New York. It runs seven radio stations and the news site Finger Lakes Daily News, and sells radio, digital, event and content marketing to local businesses. Offices are in Geneva, Auburn and Penn Yan.\n\n";
	$out .= "The Finger Lakes is not a rated radio market. Audience figures on this site come from U.S. Census Bureau data and our own stream logs, and the method is explained on the methodology page.\n";

	$studios = flxlm_get_studios();
	$out    .= "\n## Offices\n\n";
	foreach ( $studios as $s ) {
		$out .= '- ' . $s['name'] . ': ' . $s['address'] . ', ' . $s['city'] . ', ' . $s['state'] . ' ' . $s['zip'] . ', ' . $s['phone'] . "\n";
	}

	foreach ( $groups as $heading => $items ) {
		if ( ! $items ) {
			continue;
		}
		// A stable sort: pages of the same rank keep the order they came in.
		foreach ( $items as $n => $e ) {
			$items[ $n ]['_n'] = $n;
		}
		usort( $items, function ( $a, $b ) {
			$d = flxlm_seo_llms_rank( $a ) - flxlm_seo_llms_rank( $b );
			return $d ? $d : $a['_n'] - $b['_n'];
		} );
		$out .= "\n## " . $heading . "\n\n";
		foreach ( $items as $e ) {
			$out .= flxlm_seo_llms_line( $e );
		}
	}
	return $out;
}

/*--------------------------------------------------------------
 * 6. IndexNow — Instant search engine notification
 *
 * Pings the IndexNow API when posts are published or updated,
 * notifying Bing, Yandex, Seznam, and other participating engines
 * within minutes instead of waiting for natural crawl discovery.
 * Same mechanism as fldn-theme inc/seo.php section 10.
 *
 * Key file is served at /{key}.txt (required by the protocol).
 *--------------------------------------------------------------*/

/**
 * Get or generate the IndexNow API key.
 *
 * Generates a 32-character lowercase hex key on first call, stored as
 * a non-autoloaded option. Validates format on retrieval and regenerates
 * if corrupted.
 *
 * @return string 32-character hex key.
 */
function flxlm_seo_indexnow_key() {
	$key = get_option( 'flxlm_indexnow_key', '' );
	if ( ! $key || ! preg_match( '/^[a-f0-9]{8,128}$/', $key ) ) {
		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'flxlm_indexnow_key', $key, false );
	}
	return $key;
}

/**
 * Serve the IndexNow key verification file at /{key}.txt.
 *
 * WordPress has already decided the request is a 404 by the time
 * template_redirect fires, so the status must be reset to 200 or
 * the engines reject the key (fldn#386).
 */
add_action( 'template_redirect', function () {
	$path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( ! preg_match( '/^[a-f0-9]{8,128}\.txt$/', $path ) ) {
		return;
	}

	$key = flxlm_seo_indexnow_key();
	if ( $key . '.txt' !== $path ) {
		return;
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=86400' );
	echo $key;
	exit;
}, 0 );

add_action( 'transition_post_status', 'flxlm_seo_indexnow_ping', 10, 3 );

/**
 * Ping IndexNow when a post transitions to 'publish'.
 *
 * Fires a non-blocking GET to api.indexnow.org which distributes
 * the URL to all participating search engines. Only pings on
 * production, only for public post types, and only when the post's
 * canonical URL lives on this site (jobs mirrored to FLDN point
 * their canonical there, and FLDN pings for them).
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Previous post status.
 * @param WP_Post $post       Post object.
 */
function flxlm_seo_indexnow_ping( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status ) {
		return;
	}

	$pt = get_post_type_object( $post->post_type );
	if ( ! $pt || ! $pt->public ) {
		return;
	}

	// Non-production: don't ping search engines.
	if ( false === strpos( home_url(), 'flxlocalmedia.com' ) ) {
		return;
	}

	$url = wp_get_canonical_url( $post );
	if ( ! $url || wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return;
	}

	$key      = flxlm_seo_indexnow_key();
	$endpoint = add_query_arg( array(
		'url'         => rawurlencode( $url ),
		'key'         => $key,
		'keyLocation' => rawurlencode( home_url( '/' . $key . '.txt' ) ),
	), 'https://api.indexnow.org/indexnow' );

	wp_remote_get( $endpoint, array(
		'timeout'  => 5,
		'blocking' => false,
	) );
}

/*--------------------------------------------------------------
 * 7. Utility
 *--------------------------------------------------------------*/

/**
 * Truncate string to max length at word boundary.
 *
 * @param string $text Text to truncate.
 * @param int    $max  Max character length.
 * @return string
 */
function flxlm_seo_truncate( $text, $max = 160 ) {
	$text = wp_strip_all_tags( $text );
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$text = mb_substr( $text, 0, $max );
	$text = mb_substr( $text, 0, mb_strrpos( $text, ' ' ) );
	return rtrim( $text, '.,;:!? ' ) . '...';
}
