<?php
/**
 * SEO content: one "entry" per public page.
 *
 * An entry holds everything search engines and share cards need for a page:
 * title, description, canonical URL, share-image key and the copy printed on
 * the share image. The head tags (inc/seo.php), the JSON-LD (inc/seo-schema.php),
 * llms.txt and the share-image generator (/scripts/flxlm-og/ in the fldn repo)
 * all read the same entries, so a fact is written once.
 *
 * Where a page has its own data, the entry is built from it:
 *   stations     flxlm_station_page_data()  (registry backed)
 *   products     flxlm_get_product()
 *   testimonials post meta
 *   offices      flxlm_get_studios()
 * Everything else is written by hand in flxlm_seo_static_pages() below.
 *
 * House style for copy: plain English, no em dashes, no metaphors, no hype,
 * no numbers that are not already on the site.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*--------------------------------------------------------------
 * 1. Small helpers
 *--------------------------------------------------------------*/

/**
 * The brand suffix every title ends with.
 *
 * @return string
 */
function flxlm_seo_suffix() {
	return ' | FLX Local Media';
}

/**
 * The public origin canonical URLs and JSON-LD use. Always the www host, so
 * the staging copy (next.flxlocalmedia.com) and a local preview both point
 * search engines at the real site. Filter: 'flxlm_seo_public_origin'.
 *
 * @return string
 */
function flxlm_seo_origin() {
	return untrailingslashit( apply_filters( 'flxlm_seo_public_origin', 'https://www.flxlocalmedia.com' ) );
}

/**
 * Turn a URL on this site (any host) into its public www URL, with a trailing
 * slash on page paths. Other hosts pass through unchanged.
 *
 * @param string $url URL or path.
 * @return string
 */
function flxlm_seo_public_url( $url ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return '';
	}
	if ( 0 === strpos( $url, '/' ) ) {
		$url = home_url( $url );
	}
	$parts     = wp_parse_url( $url );
	$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host      = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	if ( '' === $host ) {
		return $url;
	}
	$ours = ( $host === strtolower( $home_host ) || preg_match( '/(^|\.)flxlocalmedia\.com$/', $host ) );
	if ( ! $ours ) {
		return $url;
	}
	$path = isset( $parts['path'] ) ? $parts['path'] : '/';
	if ( '' === $path ) {
		$path = '/';
	}
	// Page paths end in a slash; file paths (png, txt, xml) do not.
	if ( '/' !== substr( $path, -1 ) && ! preg_match( '/\.[a-z0-9]{2,5}$/i', $path ) ) {
		$path .= '/';
	}
	return flxlm_seo_origin() . $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
}

/**
 * Pick the first title lead that fits, with the brand suffix, in under 60 characters.
 * Falls back to the last lead cut at a word.
 *
 * @param string[] $leads  Candidates, best first.
 * @param bool     $suffix Add the brand suffix.
 * @return string
 */
function flxlm_seo_fit_title( array $leads, $suffix = true ) {
	$tail = $suffix ? flxlm_seo_suffix() : '';
	$max  = 59; // Under 60 characters, brand included.
	foreach ( $leads as $lead ) {
		$lead = trim( (string) $lead );
		if ( '' !== $lead && mb_strlen( $lead . $tail ) <= $max ) {
			return $lead . $tail;
		}
	}
	$lead = trim( (string) end( $leads ) );
	$room = $max - mb_strlen( $tail );
	if ( mb_strlen( $lead ) > $room ) {
		$lead = mb_substr( $lead, 0, $room );
		$cut  = mb_strrpos( $lead, ' ' );
		$lead = $cut ? mb_substr( $lead, 0, $cut ) : $lead;
	}
	return rtrim( $lead, " ,;:-" ) . $tail;
}

/**
 * Pick the first description that is 120 to 160 characters; otherwise the
 * longest one that is 160 or less; otherwise the first, cut at a word.
 *
 * @param string[] $candidates Candidates, best first.
 * @return string
 */
function flxlm_seo_fit_desc( array $candidates ) {
	$best = '';
	foreach ( $candidates as $c ) {
		$c = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $c ) ) );
		$n = mb_strlen( $c );
		if ( $n >= 120 && $n <= 160 ) {
			return $c;
		}
		if ( $n <= 160 && $n > mb_strlen( $best ) ) {
			$best = $c;
		}
	}
	if ( '' !== $best ) {
		return $best;
	}
	return flxlm_seo_truncate( (string) reset( $candidates ), 160 );
}

/**
 * "A, B and C" from a list.
 *
 * @param string[] $items Items.
 * @return string
 */
function flxlm_seo_and_list( array $items ) {
	$items = array_values( array_filter( array_map( 'trim', $items ) ) );
	$n     = count( $items );
	if ( $n < 2 ) {
		return $n ? $items[0] : '';
	}
	return implode( ', ', array_slice( $items, 0, -1 ) ) . ' and ' . $items[ $n - 1 ];
}

/**
 * A plain-text title for a post (entities decoded).
 *
 * @param WP_Post $post Post.
 * @return string
 */
function flxlm_seo_plain_title( $post ) {
	return trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );
}

/*--------------------------------------------------------------
 * 2. Colors for share images
 *--------------------------------------------------------------*/

/**
 * Section colors, from Scott's color system (2026-11-19). Navy is the base of
 * the frame, gold is the second color.
 *
 * @return array name => #rrggbb
 */
function flxlm_seo_palette() {
	return array(
		'navy'   => '#003b5b',
		'gold'   => '#e3a72f',
		'red'    => '#db4a42',
		'orange' => '#ef7f2e',
		'green'  => '#36995a',
		'blue'   => '#3488d0',
		'purple' => '#7a4ba9',
	);
}

/**
 * A color pair for a share image from a palette name or a hex.
 *
 * @param string $name Palette name (navy, gold, red...) or #rrggbb.
 * @param string $deep Optional deeper color for the gradient.
 * @return array bg, deep, ink (text color on bg), logo ('white' or 'navy').
 */
function flxlm_seo_color( $name, $deep = '' ) {
	$pal = flxlm_seo_palette();
	$bg  = isset( $pal[ $name ] ) ? $pal[ $name ] : flxlm_station_hex( $name, $pal['navy'] );
	if ( ! preg_match( '/^#[0-9a-f]{6}$/i', $deep ) ) {
		$deep = flxlm_station_blend( $bg, '#001627', 'gold' === $name ? 0.25 : 0.38 );
	}
	$ink = flxlm_station_on_color( $bg, '#00273e' );
	return array(
		'bg'   => $bg,
		'deep' => strtolower( $deep ),
		'ink'  => $ink,
		'logo' => '#ffffff' === $ink ? 'white' : 'navy',
	);
}

/**
 * Local file path for a theme asset URL, or ''.
 *
 * @param string $url Asset URL (may carry ?ver=).
 * @return string
 */
function flxlm_seo_asset_path( $url ) {
	$url  = (string) $url;
	$base = get_template_directory_uri();
	if ( '' === $url || 0 !== strpos( $url, $base ) ) {
		return '';
	}
	$rel  = strtok( substr( $url, strlen( $base ) ), '?' );
	$path = get_template_directory() . $rel;
	return is_readable( $path ) ? $path : '';
}

/**
 * Local logo files (white versions) for stations, by name or call sign. No
 * names means all seven.
 *
 * @param string[] $names Station names or call signs, in the order wanted.
 * @return string[] File paths.
 */
function flxlm_seo_station_logos( array $names = array() ) {
	$stations = flxlm_get_stations();
	$files    = array();
	$pick     = function ( $st ) use ( &$files ) {
		$p = flxlm_station_page_data( $st['slug'] );
		$f = $p ? flxlm_seo_asset_path( $p['logo_white'] ) : '';
		if ( $f ) {
			$files[] = $f;
		}
	};
	if ( ! $names ) {
		foreach ( $stations as $st ) {
			$pick( $st );
		}
		return $files;
	}
	foreach ( $names as $n ) {
		foreach ( $stations as $st ) {
			if ( $st['name'] === $n || $st['call_sign'] === $n ) {
				$pick( $st );
				break;
			}
		}
	}
	return $files;
}

/*--------------------------------------------------------------
 * 3. Hand-written pages
 *--------------------------------------------------------------*/

/**
 * Hand-written entries, keyed by page path (get_page_uri). 'key' is the share
 * image file name. 'color' is a palette name. 'crumb' is the breadcrumb label.
 *
 * @return array
 */
function flxlm_seo_static_pages() {
	return array(
		'solutions'              => array(
			'key'   => 'solutions',
			'kind'  => 'hub',
			'crumb' => 'Solutions',
			'lead'  => array( 'Finger Lakes Marketing Solutions' ),
			'desc'  => 'Radio, news, digital, events and sponsorships from one local team. See every FLX Local Media product for businesses in Geneva, Auburn and Penn Yan.',
			'color' => 'navy',
			'og'    => array( 'kicker' => 'Solutions', 'headline' => 'Marketing solutions', 'promise' => 'Radio, news, digital, events and sponsorships from one local team.' ),
		),
		'solutions/radio'        => array(
			'key'   => 'radio',
			'kind'  => 'hub',
			'crumb' => 'Radio',
			'lead'  => array( 'Radio Advertising in the Finger Lakes' ),
			'color' => 'blue',
			'og'    => array( 'kicker' => 'Radio advertising', 'headline' => 'Seven stations. One call.', 'promise' => 'Compare formats, audiences and coverage across the Finger Lakes.' ),
		),
		'solutions/why-radio'    => array(
			'key'   => 'why-radio',
			'kind'  => 'proof',
			'crumb' => 'Why Radio',
			'lead'  => array( 'Why Radio Works in the Finger Lakes', 'Why Radio Works' ),
			'desc'  => 'Radio reaches people in the car, at work and at home. See why it works for local Finger Lakes businesses, with market figures from the U.S. Census Bureau.',
			'color' => 'gold',
			'og'    => array( 'kicker' => 'Proof', 'headline' => 'Why radio works', 'promise' => 'The case for radio in the Finger Lakes, with Census Bureau numbers.' ),
		),
		'methodology'            => array(
			'key'   => 'methodology',
			'kind'  => 'proof',
			'crumb' => 'Methodology',
			'lead'  => array( 'How We Count Radio Listeners' ),
			'desc'  => 'The Finger Lakes is not a rated radio market, so we build audience estimates from U.S. Census Bureau data. Here is every source and how we compute each number.',
			'color' => 'gold',
			'og'    => array( 'kicker' => 'Proof', 'headline' => 'How we count listeners', 'promise' => 'Our sources and our method, in plain English.' ),
		),
		'methodology-2'          => array(
			'key'       => 'methodology',
			'kind'      => 'proof',
			'crumb'     => 'Methodology',
			'canonical' => 'methodology',
			'noindex'   => true,
			'lead'      => array( 'How We Count Radio Listeners' ),
			'desc'      => 'The Finger Lakes is not a rated radio market, so we build audience estimates from U.S. Census Bureau data. Here is every source and how we compute each number.',
			'color'     => 'gold',
			'og'        => array( 'kicker' => 'Proof', 'headline' => 'How we count listeners', 'promise' => 'Our sources and our method, in plain English.' ),
		),
		'testimonials'           => array(
			'key'   => 'testimonials',
			'kind'  => 'proof',
			'crumb' => 'Client Stories',
			'lead'  => array( 'Finger Lakes Client Stories on Video' ),
			'desc'  => 'Watch Finger Lakes business owners explain how radio and digital advertising with FLX Local Media worked for them, in their own words.',
			'color' => 'gold',
			'og'    => array( 'kicker' => 'Proof', 'headline' => 'Radio works.', 'promise' => 'Local owners on camera, in their own words.' ),
		),
		'case-study'             => array(
			'key'   => 'case-study',
			'kind'  => 'proof',
			'crumb' => 'Case Studies',
			'lead'  => array( 'Marketing Case Studies', 'Case Studies' ),
			'desc'  => 'Real work for Finger Lakes businesses and what came out of it: search, spam defense and storm alerts, with the numbers behind each project.',
			'color' => 'gold',
			'og'    => array( 'kicker' => 'Proof', 'headline' => 'Case studies', 'promise' => 'Real work for Finger Lakes businesses, and what came out of it.' ),
		),
		'about'                  => array(
			'key'   => 'about',
			'kind'  => 'company',
			'crumb' => 'About',
			'lead'  => array( 'About Our Finger Lakes Media Company', 'About Us' ),
			'desc'  => 'FLX Local Media is a Finger Lakes marketing company with seven radio stations, Finger Lakes Daily News and a digital team, based in Geneva, Auburn and Penn Yan.',
			'color' => 'navy',
			'og'    => array( 'kicker' => 'About us', 'headline' => 'Local roots. Local results.', 'promise' => 'A Finger Lakes marketing company with seven radio stations and a news site.' ),
		),
		'contact'                => array(
			'key'   => 'contact',
			'kind'  => 'company',
			'crumb' => 'Contact',
			'lead'  => array( 'Get a Free Local Marketing Review' ),
			'desc'  => 'Tell us what is holding your business back and get a free marketing plan from FLX Local Media. Call or visit our offices in Geneva, Auburn or Penn Yan, NY.',
			'color' => 'navy',
			'og'    => array( 'kicker' => 'Contact', 'headline' => 'Get a free plan', 'promise' => 'Tell us what is holding your business back. We come back with a plan, not a pitch.' ),
		),
		'apps'                   => array(
			'key'   => 'apps',
			'kind'  => 'product',
			'crumb' => 'FLX.fm App',
			'lead'  => array( 'FLX.fm App for Finger Lakes Radio' ),
			'desc'  => 'Listen to all seven FLX Local Media radio stations in the free FLX.fm app for iPhone and iPad. On Android, stream every station at fingerlakesdailynews.com.',
			'color' => 'purple',
			'og'    => array( 'kicker' => 'Free app', 'headline' => 'FLX.fm', 'promise' => 'All seven Finger Lakes radio stations in one free app.', 'logo_product' => 'flx-fm' ),
		),
		'privacy-policy'         => array(
			'key'   => 'privacy-policy',
			'kind'  => 'legal',
			'crumb' => 'Privacy Policy',
			'lead'  => array( 'Privacy Policy' ),
			'desc'  => 'How FLX Local Media, a d/b/a of TOTIB Media, Inc., collects, uses and protects information from visitors to flxlocalmedia.com, including forms and texting.',
			'color' => 'navy',
			'og'    => array( 'kicker' => 'Legal', 'headline' => 'Privacy Policy', 'promise' => 'How FLX Local Media collects, uses and protects your information.' ),
		),
		'terms'                  => array(
			'key'   => 'terms',
			'kind'  => 'legal',
			'crumb' => 'Terms of Service',
			'lead'  => array( 'Terms of Service' ),
			'desc'  => 'The terms for using flxlocalmedia.com and the services of FLX Local Media, a d/b/a of TOTIB Media, Inc., based at 3568 Lenox Road in Geneva, New York.',
			'color' => 'navy',
			'og'    => array( 'kicker' => 'Legal', 'headline' => 'Terms of Service', 'promise' => 'The terms for using our website and services.' ),
		),
		// Pages that stay out of search: an unlisted pricing page, an internal brand document.
		'240e1cc9-pricing-2026'  => array(
			'key'     => 'default',
			'kind'    => 'hidden',
			'noindex' => true,
			'lead'    => array( 'Pricing' ),
			'desc'    => '',
			'color'   => 'navy',
			'og'      => array(),
		),
		'brand'                  => array(
			'key'     => 'default',
			'kind'    => 'hidden',
			'noindex' => true,
			'lead'    => array( 'Brand' ),
			'desc'    => '',
			'color'   => 'navy',
			'og'      => array(),
		),
		// The resources page redirects to the home page until it has content.
		'resources'              => array(
			'key'     => 'default',
			'kind'    => 'hidden',
			'noindex' => true,
			'lead'    => array( 'Resources' ),
			'desc'    => '',
			'color'   => 'navy',
			'og'      => array(),
		),
	);
}

/**
 * Hand-written case study entries, keyed by the page slug.
 *
 * @return array
 */
function flxlm_seo_case_studies() {
	return array(
		'feed-spam-case-study'      => array(
			'crumb'    => 'Feed Spam Attack',
			'lead'     => array( 'Case Study: Stopping a Feed Spam Attack', 'Feed Spam Attack Case Study' ),
			'desc'     => 'How FLX Local Media found and stopped an SEO spam attack on the Finger Lakes Daily News RSS feeds in a single afternoon, with no legitimate feed broken.',
			'headline' => 'Stopping a feed spam attack',
			'promise'  => 'Caught and shut down in a single afternoon, with no legitimate feed broken.',
		),
		'fldn-seo'                  => array(
			'crumb'    => 'Technical SEO for a News Site',
			'lead'     => array( 'Case Study: SEO for a Local News Site', 'Local News SEO Case Study' ),
			'desc'     => 'How FLX Local Media raised Finger Lakes Daily News from a 54 to a 99 mobile PageSpeed score and lifted its organic click rate from 4.6% to 7.4%.',
			'headline' => 'Technical SEO for a local news site',
			'promise'  => 'Mobile PageSpeed from 54 to 99. Organic click rate from 4.6% to 7.4%.',
		),
		'finger-lakes-daily-news-com-seo' => array(
			'crumb'     => 'Technical SEO for a News Site',
			'canonical' => 'case-study/fldn-seo',
			'noindex'   => true,
			'og_key'    => 'fldn-seo',
			'lead'      => array( 'Case Study: SEO for a Local News Site', 'Local News SEO Case Study' ),
			'desc'      => 'How FLX Local Media raised Finger Lakes Daily News from a 54 to a 99 mobile PageSpeed score and lifted its organic click rate from 4.6% to 7.4%.',
			'headline'  => 'Technical SEO for a local news site',
			'promise'   => 'Mobile PageSpeed from 54 to 99. Organic click rate from 4.6% to 7.4%.',
		),
		'weather-alerts-case-study' => array(
			'crumb'    => 'Automated Storm Alerts',
			'lead'     => array( 'Case Study: Automated Storm Alerts', 'Storm Alerts Case Study' ),
			'desc'     => 'How FLX Local Media built a storm desk for Finger Lakes Daily News that checks the National Weather Service every 60 seconds and posts branded alerts itself.',
			'headline' => 'Automated storm alerts, built from zero',
			'promise'  => 'A storm desk that checks the National Weather Service every 60 seconds.',
		),
	);
}

/**
 * Hand-written copy for the products, keyed by slug: the title lead, the meta
 * description and the one line on the share image.
 *
 * @return array
 */
function flxlm_seo_product_copy() {
	return array(
		'fldn'              => array(
			'lead'    => array( 'Advertise on Finger Lakes Daily News' ),
			'desc'    => 'Put your business next to the news, weather, sports, police blotter and obituaries that Finger Lakes readers check every day. Display ads from FLX Local Media.',
			'promise' => 'Next to the local stories people read every day.',
		),
		'newsletter'        => array(
			'lead'    => array( 'FLDN Newsletter Advertising' ),
			'desc'    => 'Put your ad above or below the Finger Lakes Daily News email that goes to subscribers every morning, with clicks reported for each day your ad runs.',
			'promise' => 'In the local news email subscribers open every morning.',
		),
		'discover-flx'      => array(
			'lead'    => array( 'Discover FLX: Finger Lakes Local Guide', 'Discover FLX Local Guide' ),
			'desc'    => 'Discover FLX is a Finger Lakes guide to the best places to eat, shop, stay and visit, ranked by what locals voted in FLX Finest. See how a listing works.',
			'promise' => 'Ranked by what locals voted. Winners come first.',
		),
		'flx-finest'        => array(
			'lead'    => array( 'FLX Finest Awards Sponsorship', 'FLX Finest Sponsorship' ),
			'desc'    => 'Sponsor FLX Finest, the Finger Lakes readers\' choice awards, and put your name in front of voters. Ads, category sponsorships and a printed winners magazine.',
			'promise' => 'Sponsor the Finger Lakes readers\' choice awards.',
		),
		'flx-fm'            => array(
			'lead'    => array( 'FLX.fm App and Streaming Ads' ),
			'desc'    => 'Reach people who listen to our seven stations online, in the free FLX.fm app and on smart speakers, with listening hours we count from our own stream logs.',
			'promise' => 'Every Finger Lakes station. One free app.',
		),
		'sports'            => array(
			'lead'    => array( 'Finger Lakes Sports Radio Advertising', 'Sports Radio Advertising' ),
			'desc'    => 'Put your commercials in live play-by-play of Buffalo Bills and Syracuse games, Finger Lakes high school football and basketball, and a full NASCAR season.',
			'promise' => 'Bills, Syracuse, high school games and NASCAR.',
		),
		'shop-local'        => array(
			'lead'    => array( 'Shop Local Radio Campaign' ),
			'desc'    => 'A yearly program for locally owned Finger Lakes businesses: your name and what you do read on the air every day, plus an online listing with your logo.',
			'promise' => 'A year of on-air mentions and an online listing.',
		),
		'contests'          => array(
			'lead'    => array( 'Radio Contests and Promotions' ),
			'desc'    => 'Give away a prize, tickets or a gift card on our Finger Lakes stations and Finger Lakes Daily News. Listeners enter online on a page that names your business.',
			'promise' => 'Give something away. Listeners enter on a page with your name.',
		),
		'seasonal'          => array(
			'lead'    => array( 'Holiday and Seasonal Radio Packages', 'Seasonal Radio Packages' ),
			'desc'    => 'Sponsor the holiday season on Mix 98.5, send holiday greetings on any of our seven stations, or put your name on winter storm updates and closings reports.',
			'promise' => 'Holiday and winter storm sponsorships on our stations.',
		),
		'events'            => array(
			'lead'    => array( 'Event Promotion and Live Remotes', 'Event Promotion' ),
			'desc'    => 'Bring a station host to your location, promote your event on the air and on the Finger Lakes Daily News calendar, and follow it with a recap article.',
			'promise' => 'A station host at your event, promoted on air and online.',
		),
		'digital'           => array(
			'lead'    => array( 'Digital Marketing in the Finger Lakes' ),
			'desc'    => 'FLX Local Media builds your website, keeps your business listings correct, runs your Google and Facebook ads and makes video, from Geneva, Auburn and Penn Yan.',
			'promise' => 'Websites, listings, ads and video from a local team.',
		),
		'website-starter'   => array(
			'lead'    => array( 'Website Starter for Local Businesses', 'Website Starter' ),
			'desc'    => 'We build your website, put it live on your own web address and run it for a full year for $1,500. Local web design from our team in Geneva, NY.',
			'promise' => 'A website built, put live and run for you.',
		),
		'listings'          => array(
			'lead'    => array( 'Business Listings Management', 'Business Listings' ),
			'desc'    => 'We confirm your name, address, phone, website and hours once, then keep them matching on Google, Facebook, Bing and other listing sites, checked every month.',
			'promise' => 'Correct hours, phone and address on Google, Facebook and Bing.',
		),
		'content-marketing' => array(
			'lead'    => array( 'Content Marketing on a Local News Site', 'Content Marketing' ),
			'desc'    => 'Tell your story on Finger Lakes Daily News with labeled sponsored articles, ads in the sections your customers read, and video, produced by FLX Local Media.',
			'promise' => 'Your story on Finger Lakes Daily News.',
		),
	);
}

/**
 * Towns each station is best known for, taken from its coverage text in
 * inc/helpers.php. Used in the title ("in Geneva, NY") and nowhere else.
 *
 * @return array slug => place
 */
function flxlm_seo_station_places() {
	return array(
		'mix-985'              => 'Geneva, NY',
		'classic-hits-993'     => 'Geneva, NY',
		'the-wall'             => 'Geneva, NY',
		'finger-lakes-country' => 'Penn Yan, NY',
		'wgva'                 => 'Geneva, NY',
		'waub'                 => 'Auburn, NY',
		'the-lake'             => 'Rochester, NY',
	);
}

/**
 * Counties the core audience lives in, from the About page.
 *
 * @return string[]
 */
function flxlm_seo_counties() {
	return array( 'Ontario', 'Cayuga', 'Seneca', 'Wayne', 'Yates', 'Schuyler' );
}

/*--------------------------------------------------------------
 * 4. Entry builders
 *--------------------------------------------------------------*/

/**
 * Fill the keys every entry carries.
 *
 * @param array $e Partial entry.
 * @return array
 */
function flxlm_seo_entry_defaults( array $e ) {
	$e = array_merge( array(
		'key'         => 'default',
		'kind'        => 'page',
		'url'         => '',
		'canonical'   => '',
		'title'       => '',
		'og_title'    => '',
		'description' => '',
		'og_type'     => 'website',
		'noindex'     => false,
		'crumbs'      => array(),
		'color'       => 'navy',
		'og'          => array(),
		'data'        => array(),
		'post_id'     => 0,
		'published'   => '',
		'modified'    => '',
		'menu_group'  => '',
	), $e );

	if ( '' === $e['og_title'] ) {
		$e['og_title'] = preg_replace( '/' . preg_quote( flxlm_seo_suffix(), '/' ) . '$/', '', $e['title'] );
	}
	if ( '' === $e['canonical'] ) {
		$e['canonical'] = $e['url'];
	}
	return $e;
}

/**
 * The share-image fields shared by every entry: color pair, headline, promise.
 *
 * @param array  $og    kicker, headline, promise (and optional logo, logos, poster, quote).
 * @param string $color Palette name or hex.
 * @param string $deep   Optional deep color.
 * @param string $accent Accent for small marks on a dark background (the station's accent; gold by default).
 * @return array
 */
function flxlm_seo_og_fields( array $og, $color, $deep = '', $accent = '#e3a72f' ) {
	$c = flxlm_seo_color( $color, $deep );
	return array_merge( array(
		'kicker'   => '',
		'headline' => '',
		'promise'  => '',
		'logo'     => '',
		'logos'    => array(),
		'poster'   => '',
		'quote'    => '',
		'who'      => '',
		'position' => '',
	), $og, array(
		'bg'        => $c['bg'],
		'accent'    => flxlm_station_hex( $accent, '#e3a72f' ),
		'deep'      => $c['deep'],
		'ink'       => $c['ink'],
		'logo_ink'  => $c['logo'],
	) );
}

/**
 * Entry for the front page.
 *
 * @param WP_Post $post The front page.
 * @return array
 */
function flxlm_seo_entry_home( $post ) {
	return flxlm_seo_entry_defaults( array(
		'key'         => 'home',
		'kind'        => 'home',
		'url'         => flxlm_seo_public_url( '/' ),
		'title'       => flxlm_seo_fit_title( array( 'Finger Lakes Radio & Digital Advertising' ) ),
		'description' => 'Advertise on seven Finger Lakes radio stations and Finger Lakes Daily News. FLX Local Media also builds websites and runs events across the region.',
		'crumbs'      => array(),
		'color'       => 'navy',
		'post_id'     => $post ? $post->ID : 0,
		'published'   => $post ? $post->post_date_gmt : '',
		'modified'    => $post ? $post->post_modified_gmt : '',
		'og'          => flxlm_seo_og_fields( array(
			'kicker'   => 'Finger Lakes marketing',
			'headline' => 'Local solutions for local businesses',
			'promise'  => 'Seven radio stations, Finger Lakes Daily News, digital and events from one team.',
			'logos'    => flxlm_seo_station_logos(),
		), 'navy' ),
	) );
}

/**
 * Entry for one station page.
 *
 * @param WP_Post $post Station page.
 * @param string  $slug Station slug.
 * @return array|null
 */
function flxlm_seo_entry_station( $post, $slug ) {
	$p = function_exists( 'flxlm_station_page_data' ) ? flxlm_station_page_data( $slug ) : null;
	if ( ! $p ) {
		return null;
	}
	$name    = $p['name'];
	$call    = $p['call_sign'];
	$freq    = $p['frequency_display'];
	$format  = $p['format_label'];
	$target  = $p['target'];
	$places  = flxlm_seo_station_places();
	$place   = isset( $places[ $slug ] ) ? $places[ $slug ] : '';
	$counties = $p['counties'];
	$county_text = $counties ? flxlm_seo_and_list( $counties ) . ( count( $counties ) > 1 ? ' counties' : ' County' ) : 'the Finger Lakes';

	// Title candidates, best first. A name that already holds the call letters
	// (WAUB News Radio) is not repeated.
	$has_call = false !== stripos( $name, $call );
	$leads    = array();
	if ( $place ) {
		$leads[] = 'Advertise on ' . $name . ' in ' . $place;
		if ( $has_call ) {
			$leads[] = $call . ' Radio Advertising in ' . $place;
		}
	}
	if ( ! $has_call ) {
		$leads[] = $name . ' ' . $call . ' Radio Advertising';
	}
	$leads[] = $name . ' Radio Advertising';
	$leads[] = $name . ' Advertising';

	$id   = $freq ? $call . ', ' . $freq : $call;
	$base = 'Advertise on ' . $name . ' (' . $id . ')';
	$mid  = trim( $format . ( $target ? ' for ' . $target : '' ) );
	$desc = flxlm_seo_fit_desc( array(
		$base . '. ' . $mid . ' in ' . $county_text . '. Get a free plan from FLX Local Media.',
		$base . '. ' . $mid . ' in ' . $county_text . ' with FLX Local Media.',
		$base . '. ' . $mid . ' in ' . $county_text . '.',
		$base . '. ' . $mid . ' across the Finger Lakes. Get a free plan from FLX Local Media.',
		$base . '. ' . $mid . ' across the Finger Lakes, from FLX Local Media.',
	) );

	return flxlm_seo_entry_defaults( array(
		'key'         => $slug,
		'kind'        => 'station',
		'url'         => flxlm_seo_public_url( get_permalink( $post ) ),
		'title'       => flxlm_seo_fit_title( $leads ),
		'description' => $desc,
		'color'       => $p['colors']['primary'],
		'post_id'     => $post->ID,
		'published'   => $post->post_date_gmt,
		'modified'    => $post->post_modified_gmt,
		'menu_group'  => 'Stations',
		'data'        => array(
			'slug'     => $slug,
			'name'     => $name,
			'call'     => $call,
			'freq'     => $freq,
			'also'     => $p['frequency_also'],
			'format'   => $format,
			'tagline'  => $p['tagline'],
			'target'   => $target,
			'counties' => $counties,
			'place'    => $place,
		),
		'og'          => flxlm_seo_og_fields( array(
			'kicker'   => $call . ' · Radio advertising',
			'headline' => '' !== $freq ? $freq : $name,
			'name'     => $name,
			'promise'  => '' !== $p['lead'] ? $p['lead'] : 'Radio advertising in the Finger Lakes.',
			'logo'     => flxlm_seo_asset_path( $p['logo_white'] ),
		), $p['colors']['primary'], $p['colors']['deep'], $p['colors']['accent'] ),
	) );
}

/**
 * Entry for one product page.
 *
 * @param WP_Post $post    Product page.
 * @param string  $slug    Product slug.
 * @return array|null
 */
function flxlm_seo_entry_product( $post, $slug ) {
	$prod = function_exists( 'flxlm_get_product' ) && 'example' !== $slug ? flxlm_get_product( $slug ) : null;
	if ( ! $prod ) {
		return null;
	}
	$copy  = flxlm_seo_product_copy();
	$c     = isset( $copy[ $slug ] ) ? $copy[ $slug ] : array();
	$all   = flxlm_get_products();
	$group = isset( $all[ $slug ]['menu_group'] ) ? (string) $all[ $slug ]['menu_group'] : '';

	$leads = isset( $c['lead'] ) ? $c['lead'] : array( $prod['name'] . ' from FLX Local Media', $prod['name'] );
	$desc  = isset( $c['desc'] ) ? $c['desc'] : flxlm_seo_fit_desc( array( $prod['lead'] ) );

	// Color: the product's own color from its data, unless that is the default navy;
	// then the section color from the color system.
	$section = array(
		'Media'            => 'purple',
		'Advertising'      => 'orange',
		'Digital Services' => 'green',
		'Proof'            => 'gold',
	);
	$primary = $prod['colors']['primary'];
	if ( 'sports' === $slug ) {
		$color = 'navy' === strtolower( $primary ) || '#003b5b' === strtolower( $primary ) ? 'red' : $primary;
	} elseif ( '#003b5b' === strtolower( $primary ) ) {
		$color = isset( $section[ $group ] ) ? $section[ $group ] : 'navy';
	} else {
		$color = $primary;
	}
	$deep = $color === $primary ? $prod['colors']['deep'] : '';

	return flxlm_seo_entry_defaults( array(
		'key'         => $slug,
		'kind'        => 'product',
		'url'         => flxlm_seo_public_url( get_permalink( $post ) ),
		'title'       => flxlm_seo_fit_title( $leads ),
		'description' => $desc,
		'color'       => $color,
		'post_id'     => $post->ID,
		'published'   => $post->post_date_gmt,
		'modified'    => $post->post_modified_gmt,
		'menu_group'  => $group,
		'data'        => array(
			'slug'    => $slug,
			'name'    => $prod['name'],
			'group'   => $group,
			'lead'    => $prod['lead'],
			'service' => isset( $c['promise'] ) ? $c['promise'] : '',
		),
		'og'          => flxlm_seo_og_fields( array(
			'kicker'   => $group ? $group . ' · FLX Local Media' : 'FLX Local Media',
			'headline' => $prod['name'],
			'promise'  => isset( $c['promise'] ) ? $c['promise'] : ( '' !== $prod['tagline'] ? $prod['tagline'] : $prod['name'] ),
			'logo'     => flxlm_seo_asset_path( $prod['logo'] ),
		), $color, $deep ),
	) );
}

/**
 * Entry for one of the three office pages (/contact/geneva/ and so on).
 * They show the same content as /contact/, so their canonical is /contact/.
 *
 * @param WP_Post $post Office page.
 * @param string  $slug geneva, auburn or penn-yan.
 * @return array|null
 */
function flxlm_seo_entry_office( $post, $slug ) {
	$studio = null;
	foreach ( flxlm_get_studios() as $s ) {
		if ( sanitize_title( $s['name'] ) === $slug ) {
			$studio = $s;
			break;
		}
	}
	if ( ! $studio ) {
		return null;
	}
	$names = wp_list_pluck( $studio['stations'], 'name' );
	$place = $studio['city'] . ', ' . $studio['state'];
	$desc  = flxlm_seo_fit_desc( array(
		sprintf( 'Visit or call the FLX Local Media office at %s in %s, home of %s. Call %s for radio, digital and event marketing.', $studio['address'], $place, flxlm_seo_and_list( $names ), $studio['phone'] ),
		sprintf( 'Visit or call the FLX Local Media office at %s in %s, home of %s. Call %s.', $studio['address'], $place, flxlm_seo_and_list( $names ), $studio['phone'] ),
		sprintf( 'Visit or call the FLX Local Media office at %s in %s. Call %s for radio, digital and event marketing.', $studio['address'], $place, $studio['phone'] ),
	) );

	$logos = flxlm_seo_station_logos( wp_list_pluck( $studio['stations'], 'name' ) );

	return flxlm_seo_entry_defaults( array(
		'key'         => $slug,
		'kind'        => 'office',
		'url'         => flxlm_seo_public_url( get_permalink( $post ) ),
		'canonical'   => flxlm_seo_public_url( '/contact/' ),
		'title'       => flxlm_seo_fit_title( array( 'Marketing and Radio in ' . $place, 'Our ' . $studio['city'] . ' Office' ) ),
		'description' => $desc,
		'color'       => 'navy',
		'post_id'     => $post->ID,
		'published'   => $post->post_date_gmt,
		'modified'    => $post->post_modified_gmt,
		'crumbs'      => array( array( 'Contact', flxlm_seo_public_url( '/contact/' ) ), array( $studio['city'], flxlm_seo_public_url( get_permalink( $post ) ) ) ),
		'data'        => array( 'studio' => $studio ),
		'og'          => flxlm_seo_og_fields( array(
			'kicker'     => 'Our office',
			'headline'   => $place,
			'promise'    => $studio['address'] . "\n" . $studio['phone'],
			'logos'      => $logos,
		), 'navy' ),
	) );
}

/**
 * Entry for one case study page.
 *
 * @param WP_Post $post Case study page.
 * @return array
 */
function flxlm_seo_entry_case_study( $post ) {
	$cs    = flxlm_seo_case_studies();
	$slug  = $post->post_name;
	$title = flxlm_seo_plain_title( $post );
	$c     = isset( $cs[ $slug ] ) ? $cs[ $slug ] : array(
		'crumb'    => $title,
		'lead'     => array( 'Case Study: ' . $title, $title ),
		'desc'     => flxlm_seo_fit_desc( array( $post->post_excerpt ? $post->post_excerpt : 'A case study from FLX Local Media: ' . $title . '.' ) ),
		'headline' => $title,
		'promise'  => 'Real work for a Finger Lakes business, and what came out of it.',
	);

	$canonical = '';
	if ( ! empty( $c['canonical'] ) ) {
		$target    = get_page_by_path( $c['canonical'] );
		$canonical = $target ? flxlm_seo_public_url( get_permalink( $target ) ) : '';
	}

	return flxlm_seo_entry_defaults( array(
		'key'         => ! empty( $c['og_key'] ) ? $c['og_key'] : $slug,
		'kind'        => 'case-study',
		'url'         => flxlm_seo_public_url( get_permalink( $post ) ),
		'canonical'   => $canonical,
		'noindex'     => ! empty( $c['noindex'] ),
		'title'       => flxlm_seo_fit_title( $c['lead'] ),
		'description' => $c['desc'],
		'og_type'     => 'article',
		'color'       => 'gold',
		'post_id'     => $post->ID,
		'published'   => $post->post_date_gmt,
		'modified'    => $post->post_modified_gmt,
		'menu_group'  => 'Proof',
		'crumbs'      => array( array( 'Case Studies', flxlm_seo_public_url( '/case-study/' ) ), array( $c['crumb'], flxlm_seo_public_url( get_permalink( $post ) ) ) ),
		'data'        => array( 'headline' => $title ),
		'og'          => flxlm_seo_og_fields( array(
			'kicker'   => 'Case study',
			'headline' => $c['headline'],
			'promise'  => $c['promise'],
		), 'gold' ),
	) );
}

/**
 * Entry for one testimonial.
 *
 * @param WP_Post $post Testimonial.
 * @return array
 */
function flxlm_seo_entry_testimonial( $post ) {
	$id       = $post->ID;
	$person   = (string) get_post_meta( $id, 'person_name', true );
	$role     = (string) get_post_meta( $id, 'person_title', true );
	$business = (string) get_post_meta( $id, 'business_name', true );
	$place    = (string) get_post_meta( $id, 'location', true );
	$quote    = trim( (string) get_post_meta( $id, 'quote_short', true ) );
	$poster   = (string) get_post_meta( $id, 'poster_jpg', true );
	$position = (string) get_post_meta( $id, 'hero_object_position', true );
	$products = (string) get_post_meta( $id, 'products_used', true );
	$seconds  = function_exists( 'flxlm_hub_video_seconds' ) ? (int) flxlm_hub_video_seconds( $id ) : 0;
	$hidden   = (bool) get_post_meta( $id, 'is_hidden', true );
	$business = '' !== $business ? $business : flxlm_seo_plain_title( $post );

	// "Owner" is a stand-in when the person's name is not given.
	$generic = ( '' === $person || in_array( strtolower( $person ), array( 'owner', 'manager', 'client' ), true ) );
	$who     = $generic ? trim( $role . ( $role && $business ? ', ' : '' ) . $business ) : trim( $person . ( $role ? ', ' . $role : '' ) . ( $business ? ', ' . $business : '' ) );
	$by      = $generic ? $business : $person . ', ' . $business;

	$dur = $seconds ? floor( $seconds / 60 ) . ':' . str_pad( (string) ( $seconds % 60 ), 2, '0', STR_PAD_LEFT ) : '';
	$ctx = 'Watch the ' . ( $dur ? $dur . ' ' : '' ) . 'video from ' . $business . ( $place ? ' in ' . $place : '' ) . ' on advertising with FLX Local Media.';
	$desc_c = array();
	if ( '' !== $quote ) {
		$desc_c[] = $quote . ' ' . $ctx;
		$desc_c[] = $quote . ' Watch the ' . ( $dur ? $dur . ' ' : '' ) . 'video from ' . $business . ' on advertising with FLX Local Media.';
		$desc_c[] = $quote . ' Watch the ' . ( $dur ? $dur . ' ' : '' ) . 'video from ' . $business . '.';
		$desc_c[] = $quote . ' ' . $by . '.';
		$desc_c[] = $quote;
	}
	$desc_c[] = $business . ( $place ? ' in ' . $place : '' ) . ' shares how advertising with FLX Local Media worked. ' . $ctx;
	$desc = flxlm_seo_fit_desc( $desc_c );

	return flxlm_seo_entry_defaults( array(
		'key'         => 'testimonial-' . $post->post_name,
		'kind'        => 'testimonial',
		'url'         => flxlm_seo_public_url( get_permalink( $post ) ),
		'title'       => flxlm_seo_fit_title( array( $business . ' Client Story', $business . ' Story', $business ) ),
		'description' => $desc,
		'og_type'     => 'article',
		'noindex'     => $hidden,
		'color'       => 'gold',
		'post_id'     => $id,
		'published'   => $post->post_date_gmt,
		'modified'    => $post->post_modified_gmt,
		'menu_group'  => 'Proof',
		'crumbs'      => array( array( 'Client Stories', flxlm_seo_public_url( '/testimonials/' ) ), array( $business, flxlm_seo_public_url( get_permalink( $post ) ) ) ),
		'data'        => array(
			'business' => $business,
			'person'   => $generic ? '' : $person,
			'role'     => $role,
			'place'    => $place,
			'quote'    => $quote,
			'poster'   => $poster,
			'video'    => function_exists( 'flxlm_get_video_url' ) ? flxlm_get_video_url( $id ) : '',
			'seconds'  => $seconds,
			'products' => $products,
		),
		'og'          => flxlm_seo_og_fields( array(
			'kicker'   => 'Client story',
			'headline' => $business,
			'promise'  => $products ? $products . ' · ' . ( $place ? $place : 'Finger Lakes' ) : ( $place ? $place : 'Finger Lakes' ),
			'quote'    => $quote,
			'who'      => $who,
			'poster'   => $poster,
			'position' => $position,
		), 'gold' ),
	) );
}

/**
 * Entry for one job post.
 *
 * @param WP_Post $post Job.
 * @return array
 */
function flxlm_seo_entry_job( $post ) {
	$location  = (string) get_post_meta( $post->ID, 'job_location', true );
	$type      = (string) get_post_meta( $post->ID, 'job_type', true );
	$canonical = (string) get_post_meta( $post->ID, 'job_canonical_url', true );
	$title     = flxlm_seo_plain_title( $post );
	$excerpt   = $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags( $post->post_content );
	$lead      = trim( ( $location && $type ? $location . ' · ' . $type . '. ' : '' ) . $excerpt );
	$desc      = flxlm_seo_fit_desc( array(
		$lead . ' See the details and how to apply with FLX Local Media.',
		$lead . ' See the details and how to apply.',
		$lead,
		$title . ' at FLX Local Media' . ( $location ? ' in ' . $location : '' ) . '. See the details and how to apply.',
	) );

	return flxlm_seo_entry_defaults( array(
		'key'         => 'job-' . $post->post_name,
		'kind'        => 'job',
		'url'         => flxlm_seo_public_url( get_permalink( $post ) ),
		'canonical'   => $canonical ? $canonical : '',
		'title'       => flxlm_seo_fit_title( array( $title . ' Job' . ( $location ? ' in ' . $location : '' ), $title . ' Job', $title ), true ),
		'description' => $desc,
		'og_type'     => 'article',
		'color'       => 'navy',
		'post_id'     => $post->ID,
		'published'   => $post->post_date_gmt,
		'modified'    => $post->post_modified_gmt,
		'crumbs'      => array( array( 'Careers', flxlm_seo_public_url( '/careers/' ) ), array( $title, flxlm_seo_public_url( get_permalink( $post ) ) ) ),
		'data'        => array( 'location' => $location, 'type' => $type ),
		'og'          => flxlm_seo_og_fields( array(
			'kicker'   => 'Careers' . ( $location ? ' · ' . $location : '' ),
			'headline' => $title,
			'promise'  => 'Join FLX Local Media in the Finger Lakes.',
		), 'navy' ),
	) );
}

/**
 * Entry for the careers listing (/careers/).
 *
 * @return array
 */
function flxlm_seo_entry_careers() {
	$link = get_post_type_archive_link( 'flxlm_job' );
	return flxlm_seo_entry_defaults( array(
		'key'         => 'careers',
		'kind'        => 'hub',
		'url'         => flxlm_seo_public_url( $link ? $link : '/careers/' ),
		'title'       => flxlm_seo_fit_title( array( 'Radio and Media Jobs in the Finger Lakes' ) ),
		'description' => 'See open jobs at FLX Local Media, with seven radio stations and Finger Lakes Daily News based in Geneva, Auburn and Penn Yan, New York.',
		'color'       => 'navy',
		'crumbs'      => array( array( 'Careers', flxlm_seo_public_url( $link ? $link : '/careers/' ) ) ),
		'og'          => flxlm_seo_og_fields( array(
			'kicker'   => 'Careers',
			'headline' => 'Join the team',
			'promise'  => 'Radio and media jobs in the Finger Lakes.',
		), 'navy' ),
	) );
}

/**
 * Entry for any published page, found by its path.
 *
 * @param WP_Post $post Page.
 * @return array
 */
function flxlm_seo_entry_page( $post ) {
	$uri = get_page_uri( $post );

	if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
		return flxlm_seo_entry_home( $post );
	}

	$entry = null;

	if ( preg_match( '#^solutions/radio/([^/]+)$#', $uri, $m ) ) {
		$entry = flxlm_seo_entry_station( $post, $m[1] );
	} elseif ( preg_match( '#^solutions/([^/]+)$#', $uri, $m ) && ! isset( flxlm_seo_static_pages()[ $uri ] ) ) {
		$entry = flxlm_seo_entry_product( $post, $m[1] );
	} elseif ( preg_match( '#^contact/(geneva|auburn|penn-yan)$#', $uri, $m ) ) {
		$entry = flxlm_seo_entry_office( $post, $m[1] );
	} elseif ( preg_match( '#^case-study/[^/]+$#', $uri ) || 'finger-lakes-daily-news-com-seo' === $post->post_name ) {
		$entry = flxlm_seo_entry_case_study( $post );
	}

	if ( ! $entry ) {
		$static = flxlm_seo_static_pages();
		$last   = $post->post_name;
		$s      = isset( $static[ $uri ] ) ? $static[ $uri ] : ( isset( $static[ $last ] ) && false === strpos( $uri, '/' ) ? $static[ $last ] : null );
		if ( $s ) {
			$canonical = '';
			if ( ! empty( $s['canonical'] ) ) {
				$target    = get_page_by_path( $s['canonical'] );
				$canonical = $target ? flxlm_seo_public_url( get_permalink( $target ) ) : '';
			}
			$url   = flxlm_seo_public_url( get_permalink( $post ) );
			$crumb = isset( $s['crumb'] ) ? $s['crumb'] : flxlm_seo_plain_title( $post );
			$crumbs = array();
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $aid ) {
				$a_uri = get_page_uri( $aid );
				$a_s   = isset( $static[ $a_uri ] ) ? $static[ $a_uri ] : null;
				$crumbs[] = array( $a_s && isset( $a_s['crumb'] ) ? $a_s['crumb'] : flxlm_seo_plain_title( get_post( $aid ) ), flxlm_seo_public_url( get_permalink( $aid ) ) );
			}
			$crumbs[] = array( $crumb, $url );
			$entry = flxlm_seo_entry_defaults( array(
				'key'         => $s['key'],
				'kind'        => $s['kind'],
				'url'         => $url,
				'canonical'   => $canonical,
				'noindex'     => ! empty( $s['noindex'] ),
				'title'       => flxlm_seo_fit_title( $s['lead'] ),
				'description' => isset( $s['desc'] ) ? $s['desc'] : '',
				'color'       => $s['color'],
				'post_id'     => $post->ID,
				'published'   => $post->post_date_gmt,
				'modified'    => $post->post_modified_gmt,
				'menu_group'  => 'proof' === $s['kind'] ? 'Proof' : '',
				'crumbs'      => $crumbs,
				'og'          => ! empty( $s['og'] ) ? flxlm_seo_og_fields( array_diff_key( $s['og'], array( 'logo_product' => 1 ) ), $s['color'] ) : array(),
				'data'        => isset( $s['og']['logo_product'] ) ? array( 'logo_product' => $s['og']['logo_product'] ) : array(),
			) );
		}
	}

	if ( ! $entry ) {
		// A page nobody described yet: use what the page itself says.
		$title = flxlm_seo_plain_title( $post );
		$text  = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$url   = flxlm_seo_public_url( get_permalink( $post ) );
		$entry = flxlm_seo_entry_defaults( array(
			'key'         => 'default',
			'kind'        => 'page',
			'url'         => $url,
			'title'       => flxlm_seo_fit_title( array( $title ) ),
			'description' => flxlm_seo_fit_desc( array( $text, get_bloginfo( 'description' ) ) ),
			'post_id'     => $post->ID,
			'published'   => $post->post_date_gmt,
			'modified'    => $post->post_modified_gmt,
			'crumbs'      => array( array( $title, $url ) ),
			'og'          => array(),
		) );
	}

	// Hub and app pages: logos and a description built from data.
	if ( 'radio' === $entry['key'] && 'hub' === $entry['kind'] ) {
		$names = wp_list_pluck( flxlm_get_stations(), 'name' );
		$entry['description'] = flxlm_seo_fit_desc( array(
			'Compare our seven Finger Lakes radio stations by format, audience and coverage: ' . flxlm_seo_and_list( $names ) . '.',
			'Compare our seven Finger Lakes radio stations by format, audience and coverage, then get a free plan from FLX Local Media.',
		) );
		if ( ! empty( $entry['og'] ) ) {
			$entry['og']['logos'] = flxlm_seo_station_logos();
		}
	}
	if ( 'apps' === $entry['key'] && ! empty( $entry['data']['logo_product'] ) && ! empty( $entry['og'] ) ) {
		$prod = flxlm_get_product( $entry['data']['logo_product'] );
		if ( $prod ) {
			$entry['og']['logo'] = flxlm_seo_asset_path( $prod['logo'] );
		}
	}

	// Breadcrumb trail for entries that did not set one: Solutions then the page.
	if ( empty( $entry['crumbs'] ) && ! in_array( $entry['kind'], array( 'home' ), true ) ) {
		$crumbs = array();
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $aid ) {
			$a_uri    = get_page_uri( $aid );
			$static   = flxlm_seo_static_pages();
			$a_s      = isset( $static[ $a_uri ] ) ? $static[ $a_uri ] : null;
			$crumbs[] = array( $a_s && isset( $a_s['crumb'] ) ? $a_s['crumb'] : flxlm_seo_plain_title( get_post( $aid ) ), flxlm_seo_public_url( get_permalink( $aid ) ) );
		}
		$name = 'station' === $entry['kind'] || 'product' === $entry['kind'] ? $entry['data']['name'] : flxlm_seo_plain_title( $post );
		$crumbs[]          = array( $name, $entry['url'] );
		$entry['crumbs']   = $crumbs;
	}

	return $entry;
}

/**
 * Entry for any WP_Post (page, testimonial, job).
 *
 * @param WP_Post $post Post.
 * @return array|null
 */
function flxlm_seo_entry_for_post( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	switch ( $post->post_type ) {
		case 'page':
			return flxlm_seo_entry_page( $post );
		case 'flxlm_testimonial':
			return flxlm_seo_entry_testimonial( $post );
		case 'flxlm_job':
			return flxlm_seo_entry_job( $post );
	}
	// Anything else that is public (a blog post): what the post itself says.
	$title = flxlm_seo_plain_title( $post );
	$url   = flxlm_seo_public_url( get_permalink( $post ) );
	return flxlm_seo_entry_defaults( array(
		'key'         => 'default',
		'kind'        => 'article',
		'url'         => $url,
		'title'       => flxlm_seo_fit_title( array( $title ) ),
		'description' => flxlm_seo_fit_desc( array( $post->post_excerpt ? $post->post_excerpt : $post->post_content, get_bloginfo( 'description' ) ) ),
		'og_type'     => 'article',
		'post_id'     => $post->ID,
		'published'   => $post->post_date_gmt,
		'modified'    => $post->post_modified_gmt,
		'crumbs'      => array( array( $title, $url ) ),
	) );
}

/**
 * The entry for the page being viewed, or null for a page we do not describe
 * (search results, 404, plain posts).
 *
 * @return array|null
 */
function flxlm_seo_current_entry() {
	static $cache = array();

	if ( is_404() || is_search() ) {
		return null;
	}
	if ( is_post_type_archive( 'flxlm_job' ) ) {
		return flxlm_seo_entry_careers();
	}
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			if ( ! isset( $cache[ $post->ID ] ) ) {
				$cache[ $post->ID ] = flxlm_seo_entry_for_post( $post );
			}
			return $cache[ $post->ID ];
		}
	}
	return null;
}

/**
 * Every public page as an entry: the source for llms.txt, the sitemap checks
 * and the share-image generator.
 *
 * @param bool $include_noindex Keep pages that stay out of search.
 * @return array[] key => entry (keys are unique; a duplicate key keeps the first).
 */
function flxlm_seo_manifest( $include_noindex = false ) {
	$out   = array();
	$pages = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
	) );
	$posts = $pages;

	$tests = get_posts( array(
		'post_type'      => 'flxlm_testimonial',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'post__not_in'   => function_exists( 'flxlm_hidden_testimonial_ids' ) ? flxlm_hidden_testimonial_ids() : array(),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	$jobs  = get_posts( array(
		'post_type'      => 'flxlm_job',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	) );

	foreach ( array_merge( $posts, $tests, $jobs ) as $post ) {
		$e = flxlm_seo_entry_for_post( $post );
		if ( ! $e ) {
			continue;
		}
		if ( ( $e['noindex'] || 'hidden' === $e['kind'] ) && ! $include_noindex ) {
			continue;
		}
		$k = $e['key'];
		if ( ! isset( $out[ $k ] ) || ( $out[ $k ]['noindex'] && ! $e['noindex'] ) ) {
			$out[ $k ] = $e;
		}
	}
	$careers = flxlm_seo_entry_careers();
	if ( ! isset( $out[ $careers['key'] ] ) ) {
		$out[ $careers['key'] ] = $careers;
	}
	return $out;
}
