<?php
/**
 * SEO — Meta descriptions, Open Graph, Twitter Cards, JSON-LD.
 *
 * Single file handles all structured data output for flxlocalmedia.com.
 * Hooks into wp_head at priority 1 (meta tags) and priority 5 (JSON-LD).
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*--------------------------------------------------------------
 * 1. Meta Tags (descriptions, OG, Twitter Cards)
 *--------------------------------------------------------------*/

add_action( 'wp_head', 'flxlm_seo_meta_tags', 1 );

/**
 * Output meta description, Open Graph, and Twitter Card tags.
 */
function flxlm_seo_meta_tags() {
	$meta = flxlm_seo_get_meta();

	if ( empty( $meta['title'] ) ) {
		return;
	}

	echo "\n<!-- FLXLM SEO -->\n";

	// Meta description.
	if ( ! empty( $meta['description'] ) ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $meta['description'] ) );
	}

	// Open Graph.
	$og = array(
		'og:title'       => $meta['title'],
		'og:description' => $meta['description'],
		'og:url'         => $meta['url'],
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:locale'      => get_locale(),
		'og:type'        => $meta['og_type'],
	);

	if ( ! empty( $meta['image'] ) ) {
		$og['og:image']        = $meta['image'];
		$og['og:image:width']  = '1200';
		$og['og:image:height'] = '630';
	}

	foreach ( $og as $prop => $val ) {
		if ( '' === $val || null === $val ) {
			continue;
		}
		printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $prop ), esc_attr( $val ) );
	}

	// Twitter Card.
	$twitter = array(
		'twitter:card'        => ! empty( $meta['image'] ) ? 'summary_large_image' : 'summary',
		'twitter:title'       => $meta['title'],
		'twitter:description' => $meta['description'],
	);
	if ( ! empty( $meta['image'] ) ) {
		$twitter['twitter:image'] = $meta['image'];
	}

	foreach ( $twitter as $name => $val ) {
		if ( '' === $val || null === $val ) {
			continue;
		}
		printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $name ), esc_attr( $val ) );
	}

	echo "<!-- /FLXLM SEO -->\n\n";
}

/**
 * Build meta tag values for the current page.
 *
 * Uses page slug to look up hand-written descriptions. For testimonial singles,
 * builds description from post meta. Falls back to site tagline.
 *
 * @return array Associative array of meta values.
 */
function flxlm_seo_get_meta() {
	$defaults = array(
		'title'       => '',
		'description' => '',
		'url'         => '',
		'image'       => '',
		'og_type'     => 'website',
	);

	$site_name     = get_bloginfo( 'name' );
	$default_image = get_theme_file_uri( 'assets/images/og-default.png' );

	// Page-level descriptions keyed by slug.
	$page_descriptions = flxlm_seo_page_descriptions();

	// Front page.
	if ( is_front_page() ) {
		return array_merge( $defaults, array(
			'title'       => 'FLX Local Media | Radio, Digital, Events & Content Marketing in the Finger Lakes',
			'description' => 'FLX Local Media reaches 160,000+ people across the Finger Lakes with seven radio stations, Finger Lakes Daily News, digital marketing, events, and content solutions.',
			'url'         => home_url( '/' ),
			'image'       => $default_image,
		) );
	}

	// Singular testimonial.
	if ( is_singular( 'flxlm_testimonial' ) ) {
		$person   = get_post_meta( get_the_ID(), 'person_name', true );
		$business = get_post_meta( get_the_ID(), 'business_name', true );
		$quote    = get_post_meta( get_the_ID(), 'quote_short', true );

		$title = sprintf( '%s — %s | %s', $person, $business, $site_name );
		$desc  = $quote ? $quote : sprintf( 'See how %s grew their business with FLX Local Media.', $business );

		$poster = get_post_meta( get_the_ID(), 'poster_jpg', true );

		return array_merge( $defaults, array(
			'title'       => $title,
			'description' => flxlm_seo_truncate( $desc, 160 ),
			'url'         => get_permalink(),
			'image'       => $poster ? $poster : $default_image,
			'og_type'     => 'article',
		) );
	}

	// Singular page — use slug-based lookup.
	if ( is_singular( 'page' ) ) {
		$slug = get_queried_object()->post_name;

		// Check page descriptions map.
		$desc = isset( $page_descriptions[ $slug ] ) ? $page_descriptions[ $slug ] : '';

		// Build title: use the WP page title with site name.
		$page_title = get_the_title();
		$title      = $page_title . ' | ' . $site_name;

		// Override titles for key pages.
		$title_overrides = flxlm_seo_title_overrides();
		if ( isset( $title_overrides[ $slug ] ) ) {
			$title = $title_overrides[ $slug ];
		}

		// Per-page OG images.
		$page_images = array(
			'finger-lakes-daily-news-com-seo' => get_theme_file_uri( 'assets/images/og-case-study-seo.png' ),
		);
		$page_image = isset( $page_images[ $slug ] ) ? $page_images[ $slug ] : $default_image;

		return array_merge( $defaults, array(
			'title'       => $title,
			'description' => $desc,
			'url'         => get_permalink(),
			'image'       => $page_image,
		) );
	}

	// Archive: testimonials.
	if ( is_post_type_archive( 'flxlm_testimonial' ) ) {
		return array_merge( $defaults, array(
			'title'       => 'Client Stories | ' . $site_name,
			'description' => 'Hear from Finger Lakes businesses growing with FLX Local Media. Video testimonials, results, and real stories from our clients.',
			'url'         => get_post_type_archive_link( 'flxlm_testimonial' ),
			'image'       => $default_image,
		) );
	}

	// Fallback.
	return array_merge( $defaults, array(
		'title'       => wp_get_document_title(),
		'description' => get_bloginfo( 'description' ),
		'url'         => home_url( $_SERVER['REQUEST_URI'] ?? '/' ),
		'image'       => $default_image,
	) );
}

/**
 * Hand-written meta descriptions keyed by page slug.
 *
 * @return array
 */
function flxlm_seo_page_descriptions() {
	return array(
		'solutions'         => 'Radio, digital, events, and content marketing solutions for Finger Lakes businesses. One team, total market reach across the region.',
		'radio'             => 'Advertise on seven Finger Lakes radio stations reaching 160,000+ listeners. WGVA, WAUB, WFLR, WCGR, WFLK, WLLW, and WNYR.',
		'digital'           => 'Full-service digital marketing for Finger Lakes businesses. Web design, SEO, Google Ads, social media, video production, and geo-targeting.',
		'events'            => 'Event marketing and sponsorship opportunities across the Finger Lakes. Concerts, festivals, community events, and custom brand activations.',
		'content-marketing' => 'Content marketing on Finger Lakes Daily News. Sponsored content, display advertising, and native articles reaching 700,000+ monthly pageviews.',
		'about'             => 'FLX Local Media owns seven radio stations and Finger Lakes Daily News. Offices in Geneva, Auburn, and Penn Yan serving the Finger Lakes region.',
		'contact'           => 'Contact FLX Local Media for advertising, marketing, and media solutions in the Finger Lakes. Offices in Geneva, Auburn, and Penn Yan.',
		'geneva'            => 'Contact FLX Local Media\'s Geneva office for radio, digital, events, and content marketing solutions in the Finger Lakes.',
		'auburn'            => 'Contact FLX Local Media\'s Auburn office for radio, digital, events, and content marketing solutions in the Finger Lakes.',
		'penn-yan'          => 'Contact FLX Local Media\'s Penn Yan office for radio, digital, events, and content marketing solutions in the Finger Lakes.',
		'testimonials'      => 'Hear from Finger Lakes businesses growing with FLX Local Media. Video testimonials, results, and real stories from our clients.',
	);
}

/**
 * Custom title tag overrides keyed by page slug.
 *
 * @return array
 */
function flxlm_seo_title_overrides() {
	return array(
		'solutions'         => 'Marketing Solutions | Radio, Digital, Events & Content | FLX Local Media',
		'radio'             => 'Radio Advertising | 7 Finger Lakes Stations | FLX Local Media',
		'digital'           => 'Digital Marketing for Finger Lakes Businesses | FLX Local Media',
		'events'            => 'Event Marketing & Sponsorships | FLX Local Media',
		'content-marketing' => 'Content Marketing on Finger Lakes Daily News | FLX Local Media',
		'about'             => 'About FLX Local Media | Local Media Company in the Finger Lakes',
		'contact'           => 'Contact Us | FLX Local Media | Geneva, Auburn & Penn Yan',
		'geneva'            => 'Contact Our Geneva Office | FLX Local Media',
		'auburn'            => 'Contact Our Auburn Office | FLX Local Media',
		'penn-yan'          => 'Contact Our Penn Yan Office | FLX Local Media',
		'testimonials'      => 'Client Stories | FLX Local Media',
	);
}

/*--------------------------------------------------------------
 * 1b. Canonical Override for Contact Child Pages
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
 * 2. Title Tag Filter
 *--------------------------------------------------------------*/

add_filter( 'pre_get_document_title', 'flxlm_seo_document_title', 10 );

/**
 * Override WordPress document title with our SEO titles.
 *
 * @param string $title The current title.
 * @return string
 */
function flxlm_seo_document_title( $title ) {
	if ( is_front_page() ) {
		return 'FLX Local Media | Radio, Digital, Events & Content Marketing in the Finger Lakes';
	}

	if ( is_singular( 'page' ) ) {
		$overrides = flxlm_seo_title_overrides();
		$slug      = get_queried_object()->post_name;

		if ( isset( $overrides[ $slug ] ) ) {
			return $overrides[ $slug ];
		}
	}

	return $title;
}

/*--------------------------------------------------------------
 * 3. JSON-LD Structured Data
 *--------------------------------------------------------------*/

add_action( 'wp_head', 'flxlm_seo_jsonld', 5 );

/**
 * Output JSON-LD structured data blocks.
 */
function flxlm_seo_jsonld() {
	$blocks = array();

	// Site-wide: Organization + WebSite.
	$blocks[] = flxlm_seo_jsonld_organization();
	$blocks[] = flxlm_seo_jsonld_website();

	// Breadcrumbs.
	$breadcrumb = flxlm_seo_jsonld_breadcrumbs();
	if ( $breadcrumb ) {
		$blocks[] = $breadcrumb;
	}

	// Testimonial singles — Review schema.
	if ( is_singular( 'flxlm_testimonial' ) ) {
		$review = flxlm_seo_jsonld_review();
		if ( $review ) {
			$blocks[] = $review;
		}
	}

	$blocks = array_filter( $blocks );
	if ( empty( $blocks ) ) {
		return;
	}

	foreach ( $blocks as $data ) {
		echo '<script type="application/ld+json">';
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo "</script>\n";
	}
}

/**
 * Organization schema — LocalBusiness with multiple locations.
 */
function flxlm_seo_jsonld_organization() {
	return array(
		'@context'    => 'https://schema.org',
		'@type'       => 'LocalBusiness',
		'@id'         => home_url( '/#organization' ),
		'name'        => 'FLX Local Media',
		'url'         => home_url( '/' ),
		'description' => 'FLX Local Media owns seven radio stations and Finger Lakes Daily News, providing radio, digital, events, and content marketing solutions across the Finger Lakes region.',
		'telephone'   => '+1-315-781-7000',
		'address'     => array(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => '3568 Lenox Road',
				'addressLocality' => 'Geneva',
				'addressRegion'   => 'NY',
				'postalCode'      => '14456',
				'addressCountry'  => 'US',
			),
			array(
				'@type'           => 'PostalAddress',
				'addressLocality' => 'Auburn',
				'addressRegion'   => 'NY',
				'postalCode'      => '13021',
				'addressCountry'  => 'US',
			),
			array(
				'@type'           => 'PostalAddress',
				'addressLocality' => 'Penn Yan',
				'addressRegion'   => 'NY',
				'postalCode'      => '14527',
				'addressCountry'  => 'US',
			),
		),
		'areaServed'  => array(
			'@type' => 'GeoCircle',
			'geoMidpoint' => array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => 42.7284,
				'longitude' => -76.9278,
			),
			'geoRadius' => '80000',
		),
		'sameAs'      => array(
			'https://www.facebook.com/flxlocalmedia',
			'https://www.fingerlakesdailynews.com',
		),
	);
}

/**
 * WebSite schema.
 */
function flxlm_seo_jsonld_website() {
	return array(
		'@context' => 'https://schema.org',
		'@type'    => 'WebSite',
		'@id'      => home_url( '/#website' ),
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
	);
}

/**
 * BreadcrumbList schema.
 */
function flxlm_seo_jsonld_breadcrumbs() {
	$items = array();
	$pos   = 1;

	// Home is always first.
	$items[] = array(
		'@type'    => 'ListItem',
		'position' => $pos++,
		'name'     => 'Home',
		'item'     => home_url( '/' ),
	);

	if ( is_front_page() ) {
		return null; // No breadcrumbs on homepage.
	}

	// Solutions sub-pages.
	if ( is_singular( 'page' ) ) {
		$slug = get_queried_object()->post_name;

		$solution_slugs = array( 'radio', 'digital', 'events', 'content-marketing' );
		if ( in_array( $slug, $solution_slugs, true ) ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => 'Solutions',
				'item'     => home_url( '/solutions/' ),
			);
		}

		$contact_location_slugs = array( 'geneva', 'auburn', 'penn-yan' );
		if ( in_array( $slug, $contact_location_slugs, true ) ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => 'Contact',
				'item'     => home_url( '/contact/' ),
			);
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => get_the_title(),
			'item'     => get_permalink(),
		);
	}

	// Testimonial single.
	if ( is_singular( 'flxlm_testimonial' ) ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => 'Client Stories',
			'item'     => home_url( '/testimonials/' ),
		);
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => get_post_meta( get_the_ID(), 'person_name', true ) ?: get_the_title(),
			'item'     => get_permalink(),
		);
	}

	if ( count( $items ) < 2 ) {
		return null;
	}

	return array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}

/**
 * Review schema for testimonial singles.
 */
function flxlm_seo_jsonld_review() {
	$person   = get_post_meta( get_the_ID(), 'person_name', true );
	$business = get_post_meta( get_the_ID(), 'business_name', true );
	$quote    = get_post_meta( get_the_ID(), 'quote_full', true );

	if ( ! $person || ! $quote ) {
		return null;
	}

	return array(
		'@context'      => 'https://schema.org',
		'@type'         => 'Review',
		'author'        => array(
			'@type' => 'Person',
			'name'  => $person,
		),
		'itemReviewed'  => array(
			'@type' => 'LocalBusiness',
			'@id'   => home_url( '/#organization' ),
			'name'  => 'FLX Local Media',
		),
		'reviewBody'    => $quote,
		'publisher'     => array(
			'@type' => 'Organization',
			'name'  => $business,
		),
	);
}

/*--------------------------------------------------------------
 * 4. Sitemap Exclusions
 *--------------------------------------------------------------*/

add_filter( 'wp_sitemaps_posts_query_args', 'flxlm_seo_sitemap_exclude', 10, 2 );

/**
 * Exclude specific pages from the WP core sitemap.
 *
 * @param array  $args      WP_Query args for the sitemap.
 * @param string $post_type The post type.
 * @return array
 */
function flxlm_seo_sitemap_exclude( $args, $post_type ) {
	if ( 'page' !== $post_type ) {
		return $args;
	}

	// Page IDs to exclude from sitemap.
	$exclude = array(
		39, // fldn-seo case study
	);

	if ( ! empty( $args['post__not_in'] ) ) {
		$args['post__not_in'] = array_merge( $args['post__not_in'], $exclude );
	} else {
		$args['post__not_in'] = $exclude;
	}

	return $args;
}

/*--------------------------------------------------------------
 * 5. Utility
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
