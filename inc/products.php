<?php
/**
 * Product pages and the primary navigation data.
 *
 * Three jobs live here so one file holds all of the "content to be filled later":
 *
 *   1. flxlm_get_products() / flxlm_get_product( $slug )
 *      The data behind page-templates/template-product.php. Add one entry per
 *      product to the $products array. A section with no data is skipped.
 *
 *   2. flxlm_nav_groups()
 *      The Media, Advertising, Digital Services and Proof dropdowns (header.php),
 *      plus the settings of the Stations dropdown, whose tiles come from
 *      flxlm_get_stations() (registry backed).
 *
 *   3. flxlm_sdvosb_badge()
 *      The SBA-certified SDVOSB badge in the footer. Set FLXLM_SDVOSB_BADGE.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Footer badge. Set the image here (or define the constant in wp-config.php
 * before the theme loads, or use the 'flxlm_sdvosb_badge' filter).
 *
 * FLXLM_SDVOSB_BADGE  Path inside the theme, e.g. 'assets/images/brand/sdvosb-badge.png',
 *                     or a full https URL. Empty means the footer prints no badge.
 * FLXLM_SDVOSB_URL    Where the badge links.
 * FLXLM_SDVOSB_ALT    Alt text for the badge image.
 */
if ( ! defined( 'FLXLM_SDVOSB_BADGE' ) ) {
	define( 'FLXLM_SDVOSB_BADGE', 'assets/images/brand/sba-sdvosb-400.png' );
}
if ( ! defined( 'FLXLM_SDVOSB_URL' ) ) {
	define( 'FLXLM_SDVOSB_URL', 'https://veterans.certify.sba.gov/' );
}
if ( ! defined( 'FLXLM_SDVOSB_ALT' ) ) {
	define( 'FLXLM_SDVOSB_ALT', 'SBA-certified Service-Disabled Veteran-Owned Small Business (SDVOSB)' );
}

/**
 * The footer badge as array( src, url, alt ), or null when no badge is set or
 * the file is missing. Filter: 'flxlm_sdvosb_badge'.
 *
 * @return array|null
 */
function flxlm_sdvosb_badge() {
	$src = trim( (string) FLXLM_SDVOSB_BADGE );
	if ( '' !== $src && ! preg_match( '#^https://#i', $src ) ) {
		$path = get_template_directory() . '/' . ltrim( $src, '/' );
		$src  = ( false === strpos( $src, '..' ) && is_readable( $path ) )
			? add_query_arg( 'ver', (int) filemtime( $path ), get_template_directory_uri() . '/' . ltrim( $src, '/' ) )
			: '';
	}
	$badge = '' !== $src ? array(
		'src' => esc_url_raw( $src ),
		'url' => esc_url_raw( (string) FLXLM_SDVOSB_URL ),
		'alt' => (string) FLXLM_SDVOSB_ALT,
	) : null;

	$badge = apply_filters( 'flxlm_sdvosb_badge', $badge );
	return is_array( $badge ) && ! empty( $badge['src'] ) ? $badge : null;
}

/* ==========================================================================
   Colours: the same --st-* custom properties the station page uses.
   ========================================================================== */

/**
 * Derived colour tokens for a page, same maths as flxlm_station_page_data()
 * so a product page and a station page look like one family. The station
 * function is left alone; this is the same derivation for any colour set.
 *
 * @param array $colors Optional keys primary, deep, accent (#rrggbb).
 * @return array { css: string for a style attribute, vars: array, primary, deep, accent }
 */
function flxlm_page_css_vars( $colors ) {
	$col     = is_array( $colors ) ? $colors : array();
	$primary = flxlm_station_hex( isset( $col['primary'] ) ? $col['primary'] : '', '#003b5b' );
	$deep    = flxlm_station_hex( isset( $col['deep'] ) ? $col['deep'] : '', flxlm_station_blend( $primary, '#000000', 0.3 ) );
	$accent  = flxlm_station_hex( isset( $col['accent'] ) ? $col['accent'] : '', '#e3a72f' );
	$ink     = flxlm_station_blend( $deep, '#000000', 0.62 );

	// Colour for small text on white: the primary, or the deep colour, darkened until it reads (4.5:1).
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
	$css  = '';
	foreach ( $vars as $k => $v ) {
		$css .= $k . ':' . $v . ';';
	}

	return array(
		'css'     => $css,
		'vars'    => $vars,
		'primary' => $primary,
		'deep'    => $deep,
		'accent'  => $accent,
	);
}

/* ==========================================================================
   Product data
   ========================================================================== */

/**
 * Resolve a product image to a URL, only when the file exists in the theme.
 * Absolute https URLs pass through. Otherwise the path must be clean and
 * relative to assets/images/products/<slug>/.
 *
 * @param string $slug Product slug.
 * @param mixed  $rel  Relative path or https URL.
 * @return string URL, or '' when missing.
 */
function flxlm_product_asset( $slug, $rel ) {
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
	$sub  = 'assets/images/products/' . preg_replace( '/[^a-z0-9_-]/i', '', (string) $slug ) . '/';
	$path = get_template_directory() . '/' . $sub . $rel;
	if ( ! is_readable( $path ) ) {
		return '';
	}
	return add_query_arg( 'ver', (int) filemtime( $path ), get_template_directory_uri() . '/' . $sub . $rel );
}

/**
 * Inline SVG icon for an "offers" tile. The eight station icons (spots,
 * sponsorship, remote, stream, contest, news, sports, season) come from
 * flxlm_station_way_icon(); the rest are added here.
 *
 * @param string $name Icon key. Unknown keys get the spots icon.
 * @return string Static SVG markup.
 */
function flxlm_product_icon( $name ) {
	$open  = '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
	$extra = array(
		'email'  => '<rect x="5" y="10" width="38" height="28" rx="3"/><path d="M6 13l18 14 18-14"/>',
		'web'    => '<rect x="5" y="8" width="38" height="32" rx="3"/><path d="M5 17h38M11 12.5h0M16 12.5h0"/>',
		'video'  => '<rect x="5" y="10" width="38" height="28" rx="3"/><path d="M20 17l11 7-11 7z" fill="currentColor"/>',
		'award'  => '<circle cx="24" cy="19" r="12"/><path d="M17 29l-4 14 11-5 11 5-4-14"/>',
		'app'    => '<rect x="13" y="4" width="22" height="40" rx="4"/><path d="M21 38h6"/>',
		'search' => '<circle cx="21" cy="21" r="14"/><path d="M31 31l12 12"/>',
		'people' => '<circle cx="17" cy="17" r="6"/><circle cx="33" cy="19" r="5"/><path d="M5 40c0-8 5-12 12-12s12 4 12 12M31 30c7 0 12 3 12 10"/>',
		'list'   => '<path d="M17 12h26M17 24h26M17 36h26"/><path d="M6 12h0M6 24h0M6 36h0" stroke-width="5"/>',
	);
	if ( ! isset( $extra[ $name ] ) ) {
		// One of the eight station icons, or the spots icon for an unknown key.
		return flxlm_station_way_icon( $name );
	}
	return $open . $extra[ $name ] . '</svg>';
}

/**
 * The raw product entries, keyed by slug. The slug is the page slug of the
 * WordPress page that uses the "Product" template (/solutions/<slug>/).
 *
 * Schema (every key optional except name; types after the arrow):
 *   name         => string   product name, used in "Get a free plan for <name>"
 *   eyebrow      => string   small line above the title, e.g. 'Advertising · Sports'
 *   title        => string   the one promise, shown as the page heading (default: name)
 *   tagline      => string   short line on the tilted sticker
 *   lead         => string   one or two plain sentences under the title
 *   cta_label    => string   button text (default 'Get a free plan for <name>')
 *   colors       => [ primary, deep, accent ]    #rrggbb strings, all optional
 *   logo         => string   file under assets/images/products/<slug>/ or https URL (white or light art on the colour)
 *   hero_mark   => string   optional real brand mark for the hero (file under assets/images/products/<slug>/), shown instead of logo on the page; logo stays the white art used for share images
 *   hero_mark_style => string   'panel' (dark art on a white rounded panel) or 'app' (square app icon with rounded corners)
 *   hero_logos   => [ [ file, alt, group, w?, h?, fit? ], ... ]   optional band of logos right under the hero. Groups render in order of first appearance, each with a small caption. Entries with w/h (pixel box for optical sizing) are plain marks; entries without are identical round badges, fit => 'disc' | 'wide' | '' tunes how the art sits in the ring
 *   stats        => [ [ value, label, source ], ... ]   bar of numbers; source is the plain-English origin of the number
 *   stats_note   => string   one line under the bar, e.g. how the numbers are made
 *   who          => [ heading, paragraphs => string[] ]   "Who you reach"
 *   offers       => [ [ title, text, icon, logos? ], ... ]       "Ways to advertise". icon: spots|sponsorship|remote|stream|contest|news|sports|season|email|web|video|award|app|search|people|list
 *                   logos (optional): [ [ file, alt, chip? ] | [ text ], ... ] files under assets/images/products/logos/; chip => true puts light art on a navy chip; text => a text badge when no file exists. With logos, they replace the icon.
 *   extra        => [ [ heading, items => string[] ], ... ]   more lists, each its own panel
 *   testimonial  => [ person, business, quote, page_url, video_seconds int, thumb ]   thumb: file under assets/images/products/<slug>/ or https URL
 *
 * Filter 'flxlm_products' lets other code add entries.
 *
 * @return array
 */
function flxlm_get_products() {
	$products = array(
		'discover-flx' => array(
			'name' => 'Discover FLX',
			'eyebrow' => 'Discover FLX · Media',
			'title' => 'Discover FLX',
			'tagline' => 'Here\'s what the locals actually love.',
			'lead' => 'A Finger Lakes guide to the best places to eat, shop, stay and visit, ranked by what locals voted in FLX Finest.',
			'menu_group' => 'Media', 'menu_label' => 'Discover FLX', 'menu_blurb' => 'The Finger Lakes guide where FLX Finest winners come first.',
			'colors' => array( 'primary' => '#1f5c38', 'deep' => '#002d14', 'accent' => '#e3a72f' ),
			'logo' => 'logo.svg',
			'stats' => array(
				array( 'value' => '822', 'label' => 'Business pages in the directory', 'source' => 'discoverflx.com directory, Nov 2026', 'source_detail' => 'discoverflx.com directory, counted from the site\'s public API on November 19, 2026' ),
				array( 'value' => '125', 'label' => 'Guides, articles and itineraries', 'source' => 'discoverflx.com, Nov 2026', 'source_detail' => 'discoverflx.com, 41 guides, 78 articles and 6 itineraries, counted on November 19, 2026' ),
				array( 'value' => '2,189', 'label' => 'FLX Finest awards on record, 2022 to 2026', 'source' => 'FLX Finest results, 2022-2026', 'source_detail' => 'Official FLX Finest results for 2022 to 2026, compiled by FLX Local Media (discoverflx-theme data/discover-v1 report)' ),
			),
			'who' => array( 'heading' => 'Who uses Discover FLX', 'paragraphs' => array( 'Discover FLX is for people deciding where to go. A family picking a restaurant in Geneva, a couple planning a weekend on Seneca Lake and a neighbor looking for the best pizza in town all use the same guides, itineraries and business pages. The site is written for locals and visitors, and it describes itself as the Finger Lakes seen by the people who live here.', 'Every list on Discover FLX is ranked by FLX Finest, the readers\' choice awards that FLX Local Media runs. Winners come first, and a business cannot pay to move up a list. That is what makes a page here worth having for an advertiser: readers can trust the order, so a listing earns attention instead of buying it.' ) ),
			'offers' => array(
				array( 'title' => 'A page for every winner', 'text' => 'Every FLX Finest winner gets a business page with a ribbon for each award and year, a map, and the details people need. Businesses we have fact-checked that are open also get a Call, Directions and Website row. The page is free and stays up all year.', 'icon' => 'award', 'logos' => array( array( 'file' => 'discover-flx-logo.svg', 'alt' => 'Discover FLX' ) ) ),
				array( 'title' => 'Best-of guides', 'text' => 'Guides for restaurants, breweries, hikes and towns list FLX Finest winners first, so a win puts your name in the guide people read before they choose.', 'icon' => 'news', 'logos' => array( array( 'file' => 'discover-flx-logo.svg', 'alt' => 'Discover FLX' ) ) ),
				array( 'title' => 'Fact-checked profiles', 'text' => 'We research each business, check the details, and link to outside press where it exists. A form on every page sends corrections straight to our team.', 'icon' => 'search', 'logos' => array( array( 'file' => 'discover-flx-logo.svg', 'alt' => 'Discover FLX' ) ) ),
				array( 'title' => 'Events on the community calendar', 'text' => 'Events from the Finger Lakes Daily News community calendar also show on Discover FLX, with credit, so one event listing reaches both audiences.', 'icon' => 'people', 'logos' => array( array( 'file' => 'discover-flx-logo.svg', 'alt' => 'Discover FLX' ), array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'The way in is FLX Finest', 'text' => 'Businesses get on Discover FLX by being nominated and voted on in FLX Finest. It is free to be nominated, voted on or to win, and sponsors can put their name in front of voters while they choose.', 'icon' => 'contest', 'logos' => array( array( 'file' => 'flx-finest-icon.png', 'alt' => 'FLX Finest' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'What a business page includes', 'items' => array( 'One ribbon for each FLX Finest award, with the year; the category shows on hover or tap.', 'Address, phone, website and map pin, shown on listings we have fact-checked.', 'A researched profile with links to outside press.', 'Links to the guides and itineraries that mention the business.', 'More places that won the same FLX Finest categories.' ) ),
			),
			'testimonial' => array( 'person' => 'Mike Speno', 'business' => 'Speno Music', 'quote' => 'There’s no yellow pages or phone book, and there certainly isn’t any local newspaper coverage … we’ve reached all of our demographics through the FLX Local Media.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/speno-music/', 'video_seconds' => 60, 'thumb' => 'testimonial.jpg' ),
		),
		'flx-finest' => array(
			'name' => 'FLX Finest',
			'eyebrow' => 'FLX Finest · Media',
			'title' => 'FLX Finest',
			'tagline' => '',
			'lead' => 'Sponsor the Finger Lakes readers\' choice awards and put your name in front of the people voting.',
			'menu_group' => 'Media', 'menu_label' => 'FLX Finest', 'menu_blurb' => 'The Finger Lakes readers\' choice awards: sponsorships, ads and a winners program.',
			'colors' => array( 'primary' => '#151716', 'deep' => '#232524', 'accent' => '#e3a72f' ),
			'logo' => 'logo.svg',
			'stats' => array(
				array( 'value' => '179,000+', 'label' => 'Votes cast in the 2026 contest', 'source' => 'FLX Finest contest totals, 2026', 'source_detail' => 'FLX Finest 2026 contest totals as recorded in the FLX Local Media sales platform data (customer-flx data/flx-finest-categories.json, collected April 10, 2026); still to be confirmed against FLXFinest.com' ),
				array( 'value' => '639', 'label' => 'Gold, silver and bronze awards in 213 categories, 2026', 'source' => 'FLX Finest results, 2026', 'source_detail' => 'Official FLX Finest 2026 results, compiled by FLX Local Media (discoverflx-theme data/discover-v1 report)' ),
				array( 'value' => '7', 'label' => 'FLX Local Media stations promote it', 'source' => 'FLX Finest media kit, 2026', 'source_detail' => 'FLX Finest 2026 media kit (customer-flx docs/programs/flx-finest.md)' ),
				array( 'value' => '5,000', 'label' => 'Printed winners magazines', 'source' => 'FLX Finest media kit, 2026', 'source_detail' => 'FLX Finest 2026 media kit, copies distributed at the winners event' ),
			),
			'who' => array( 'heading' => 'Who votes, and why it matters', 'paragraphs' => array( 'People who live in the Finger Lakes nominate and vote for their favorite local businesses in categories such as restaurants, health and beauty, home and garden, motors and services. Gold, silver and bronze are named in each category; bronze was added in 2026. It costs a business nothing to be nominated, voted on or to win, so the winners are the businesses people chose, not the ones that bought the most.', 'That is why a sponsor name next to the voting means something. Voters are thinking about local businesses by category at the moment they see your name, and a win is a recommendation from customers that no ad can make for you. Winners stay visible after the contest: every winner has a page on Discover FLX all year, with a ribbon for each award.' ) ),
			'offers' => array(
				array( 'title' => 'Category sponsorship', 'text' => 'Put your name on one of the nine main categories (Eat & Drink, Education, Health & Beauty, Home & Garden, Marine, Motors, Services, Shopping, Things to Do) or on a single subcategory. Each has one sponsor, and a main category sponsor\'s logo opens that section of the winners magazine. Returning sponsors get first right of acceptance.', 'icon' => 'sponsorship', 'logos' => array( array( 'file' => 'flx-finest-icon.png', 'alt' => 'FLX Finest' ) ) ),
				array( 'title' => 'Ballot and voting-page ads', 'text' => 'Ballot buttons with a picture or a video, a large banner on the voting entry and exit pages, and an ad shown after each vote is cast. They run for the whole voting period.', 'icon' => 'web', 'logos' => array( array( 'file' => 'flx-finest-icon.png', 'alt' => 'FLX Finest' ) ) ),
				array( 'title' => 'Your own voting page', 'text' => 'A page with your own web address and a QR code for the categories you are in, to share with customers by email, social media or flyer. Visitors can leave for any web address you choose.', 'icon' => 'contest', 'logos' => array( array( 'file' => 'flx-finest-icon.png', 'alt' => 'FLX Finest' ) ) ),
				array( 'title' => 'The winners magazine', 'text' => 'A full-size printed magazine handed out at the winners event. Gold winners get a write-up, a photo and contact details; silver and bronze winners get contact details. Ads run from one-sixth of a page up to the covers.', 'icon' => 'list', 'logos' => array( array( 'file' => 'flx-finest-icon.png', 'alt' => 'FLX Finest' ) ) ),
				array( 'title' => 'Year-round on FLXFinest.com', 'text' => 'A full business profile page, a rotating home page slider, or a sponsored online story written by our team after an interview with you.', 'icon' => 'award', 'logos' => array( array( 'file' => 'flx-finest-icon.png', 'alt' => 'FLX Finest' ) ) ),
				array( 'title' => 'Targeted social campaigns', 'text' => 'A two-week Facebook and Instagram campaign with an image or a video. For video, we edit a 15-second spot from your assets or from your radio audio.', 'icon' => 'people', 'logos' => array( array( 'file' => 'ext-facebook-f.svg', 'alt' => 'Facebook' ), array( 'file' => 'ext-instagram.svg', 'alt' => 'Instagram' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'How a contest year runs', 'items' => array( 'Nominations open online; an email address is required to nominate, not to vote.', 'Voting opens on the ballot.', 'Results are counted by a third party.', 'Gold, silver and bronze winners are named in each category.', 'Results are published on FLXFinest.com and in the printed winners magazine.' ) ),
				array( 'heading' => 'Promotion on all seven stations', 'items' => array( 'Before nominations open.', 'During nominations.', 'Before voting.', 'During voting.', 'Before the results.', 'At the winners reveal.', 'Recorded commercials, live on-air support, outdoor advertising and paid and organic social media.' ) ),
				array( 'heading' => 'The winners program', 'items' => array( 'Free for any business to be nominated, voted on or to win.', 'Gold, silver and bronze logos are licensed for marketing to businesses that spend the minimum on FLX Finest marketing.', 'Every winner is listed on Discover FLX, with a ribbon for each award and year.', 'Results from 2022 to 2026 stay on Discover FLX.' ) ),
			),
			'testimonial' => array( 'person' => 'Kim Minro', 'business' => 'Aster Financial Group', 'quote' => 'One of the great things about Finger Lakes Finest is that it puts all of our names out there over and over on top of the marketing we already get from the program.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/aster-financial-group/', 'video_seconds' => 75, 'thumb' => 'testimonial.jpg' ),
		),
		'sports' => array(
			'name' => 'Sports',
			'eyebrow' => 'Sports · Advertising',
			'title' => 'Sponsor local sports',
			'tagline' => '',
			'lead' => 'Put your commercials in live play-by-play of Buffalo Bills and Syracuse games, Finger Lakes high school football and basketball, and a full NASCAR season.',
			'menu_group' => 'Advertising', 'menu_label' => 'Sports', 'menu_blurb' => 'Bills, Syracuse, high school games and NASCAR on WAUB, WGVA and WFLR.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'hero_logos' => array(
				array( 'file' => 'band-bills.png', 'alt' => 'Buffalo Bills', 'group' => 'Pro & college', 'w' => 64.2, 'h' => 42.9 ),
				array( 'file' => 'band-syracuse.png', 'alt' => 'Syracuse Orange', 'group' => 'Pro & college', 'w' => 41.4, 'h' => 56.1 ),
				array( 'file' => 'band-nascar.png', 'alt' => 'NASCAR', 'group' => 'Racing', 'w' => 151.6, 'h' => 25.3 ),
				array( 'file' => 'band-mrn.png', 'alt' => 'Motor Racing Network', 'group' => 'Racing', 'w' => 109.1, 'h' => 31.0 ),
				array( 'file' => 'band-prn.png', 'alt' => 'Performance Racing Network', 'group' => 'Racing', 'w' => 93.8, 'h' => 34.0 ),
				array( 'file' => 'band-auburn.png', 'alt' => 'Auburn Maroons', 'group' => 'High school', 'fit' => '' ),
				array( 'file' => 'band-dundee.png', 'alt' => 'Dundee', 'group' => 'High school', 'fit' => 'wide' ),
				array( 'file' => 'band-geneva.png', 'alt' => 'Geneva Panthers', 'group' => 'High school', 'fit' => 'disc' ),
				array( 'file' => 'band-je.png', 'alt' => 'Jordan-Elbridge', 'group' => 'High school', 'fit' => '' ),
				array( 'file' => 'band-mynderse.png', 'alt' => 'Seneca Falls Mynderse', 'group' => 'High school', 'fit' => 'disc' ),
				array( 'file' => 'band-pennyan.png', 'alt' => 'Penn Yan Mustangs', 'group' => 'High school', 'fit' => 'disc' ),
				array( 'file' => 'band-waterloo.png', 'alt' => 'Waterloo Tigers', 'group' => 'High school', 'fit' => '' ),
				array( 'file' => 'band-weedsport.png', 'alt' => 'Weedsport Wolverines', 'group' => 'High school', 'fit' => 'disc' ),
			),
			'stats' => array(
				array( 'value' => '239', 'label' => 'live sports broadcasts scheduled on our stations and Finger Lakes Daily News, September 2025 through August 2026', 'source' => 'FLX sports broadcast calendar, 2025-26', 'source_detail' => 'FLX sports broadcast calendar (BigQuery fldn_analytics.streaming_sports_events), cancelled events and talk shows removed, counted November 19, 2026' ),
				array( 'value' => '60+', 'label' => 'games in the Bills and Syracuse football and basketball package, with at least three commercials in each football game and two in each basketball game', 'source' => 'Bills and Syracuse sponsor sheet, 2025-26', 'source_detail' => '2025-26 Buffalo Bills and Syracuse sponsor sheet, September through March' ),
				array( 'value' => '39', 'label' => 'NASCAR races on WFLR in a season, with two of your commercials in every one', 'source' => 'NASCAR on WFLR sponsor sheet, 2026', 'source_detail' => '2026 NASCAR on WFLR sponsor sheet' ),
			),
			'who' => array( 'heading' => 'Who tunes in for the game', 'paragraphs' => array( 'A person who tunes in for a game picked that time on purpose. Some are parents and grandparents following a team from the stands or from the kitchen. Some are graduates of Auburn, Geneva, Penn Yan or Waterloo who still follow the school. Others are Bills and Syracuse fans who want the radio call while they drive or work. Local teams come first on our stations, then Syracuse and Buffalo.', 'A game gives you a long stretch of listening that has a set start time. In the Bills and Syracuse package your commercial airs at least three times in a football game and at least twice in a basketball game, and your name is read in the opening and closing billboards. Listeners hear you more than once in the same sitting, from a voice they are already paying attention to.' ) ),
			'offers' => array(
				array( 'title' => 'Bills and Syracuse football and basketball', 'text' => 'Your commercials in Buffalo Bills and Syracuse University football and basketball broadcasts on WAUB and WGVA, from September through March. The package adds a commercial in the weekly Syracuse Coaches Show and your name in the opening and closing billboards of each game.', 'icon' => 'sports', 'logos' => array( array( 'file' => 'bills.svg', 'alt' => 'Buffalo Bills' ), array( 'file' => 'syracuse.svg', 'alt' => 'Syracuse Orange' ) ) ),
				array( 'title' => 'Auburn and Weedsport on WAUB', 'text' => 'High school football, basketball, hockey, lacrosse and baseball for Auburn and Weedsport, from September through May. Your commercials run in each game, with billboards and promotional announcements through the week.', 'icon' => 'sports', 'logos' => array( array( 'file' => 'auburn-navy.png', 'alt' => 'Auburn Maroons' ), array( 'file' => 'weedsport.png', 'alt' => 'Weedsport Wolverines' ), array( 'file' => 'je-navy.png', 'alt' => 'Jordan-Elbridge' ) ) ),
				array( 'title' => 'Geneva, Mynderse and Waterloo football on WGVA', 'text' => 'A football game of the week in September and October with at least three of your commercials in each game, live mentions during the game and promotional announcements on WGVA all week.', 'icon' => 'spots', 'logos' => array( array( 'file' => 'geneva.png', 'alt' => 'Geneva' ), array( 'file' => 'mynderse.png', 'alt' => 'Seneca Falls (Mynderse)' ), array( 'file' => 'waterloo.png', 'alt' => 'Waterloo' ) ) ),
				array( 'title' => 'Penn Yan and Dundee on WFLR', 'text' => 'Football, basketball and lacrosse from September through June, with at least three commercials per game and live mentions. Sponsor every sport for the school year or choose one sport.', 'icon' => 'sponsorship', 'logos' => array( array( 'file' => 'pennyan.png', 'alt' => 'Penn Yan Mustangs' ), array( 'file' => 'dundee.png', 'alt' => 'Dundee' ) ) ),
				array( 'title' => 'NASCAR on WFLR', 'text' => 'Two commercials in every race, plus one each week in Fast Talk on Monday evenings, NASCAR LIVE on Tuesday evenings and the Saturday morning Kyle Petty Countdown. The season runs from early spring through November.', 'icon' => 'sports', 'logos' => array( array( 'file' => 'nascar.svg', 'alt' => 'NASCAR' ), array( 'file' => 'mrn.svg', 'alt' => 'Motor Racing Network' ), array( 'file' => 'prn.png', 'alt' => 'Performance Racing Network' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'On the air this fall', 'items' => array( 'WAUB: Auburn football and Weedsport/Jordan-Elbridge football', 'WGVA: Geneva football and Mynderse/Waterloo football', 'WFLR: Penn Yan/Dundee football and NASCAR', 'WAUB and WGVA: Syracuse football and basketball, and the Buffalo Bills' ) ),
				array( 'heading' => 'When each package runs', 'items' => array( 'Bills and Syracuse football and basketball: September through March', 'Auburn and Weedsport: September through May', 'Geneva, Mynderse and Waterloo football: September and October', 'Penn Yan and Dundee: September through June', 'NASCAR on WFLR: early spring through November' ) ),
			),
			'testimonial' => array( 'person' => 'Jeff Leavens', 'business' => 'E & V Energy', 'quote' => 'Frank always calls me every year for the sports part of it. Being the guy who gets that phone call, "hey, we\'re doing this, I thought of you first," because of the relationship we\'ve created over the years, that puts me there.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/e-v-energy/', 'video_seconds' => 91, 'thumb' => 'testimonial.jpg' ),
		),
		'shop-local' => array(
			'name' => 'Shop Local',
			'eyebrow' => 'Shop Local · Advertising',
			'title' => 'Shop Local',
			'tagline' => '',
			'lead' => 'A yearly program for locally owned businesses: your name, what you do and how to reach you, read on the air every day and listed online with your logo and a link to your website.',
			'menu_group' => 'Advertising', 'menu_label' => 'Shop Local', 'menu_blurb' => 'A year of on-air mentions and an online listing for locally owned businesses.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '92', 'label' => 'local businesses in the 2026 Shop Local program, up from 68 in 2025 and 63 in 2024', 'source' => 'FLX Shop Local orders, 2026', 'source_detail' => 'FLX Local Media order records (NL9 Shop Local orders by program year), counted November 18, 2026' ),
				array( 'value' => 'About 2', 'label' => 'on-air mentions a day on each station a member chose, across the 2026 program year', 'source' => 'FLX Shop Local airings, 2026', 'source_detail' => 'NL9 airing records for the Shop Local Promos orders, 2026 program year, November 18, 2026' ),
			),
			'who' => array( 'heading' => 'Who hears Shop Local', 'paragraphs' => array( 'Listeners hear Shop Local on the stations they already keep on. Each 60-second spot names three locally owned businesses, says what each one does and tells listeners how to reach it. A listener who wants to buy from a neighbor learns who is nearby and how to call.', 'The program is for businesses owned in the Finger Lakes. A mention is short and plain, so it fits an owner who wants the name heard all year without planning a campaign. You join once for the program year, which runs from February through January.' ) ),
			'offers' => array(
				array( 'title' => 'Named in a shared 60-second spot', 'text' => 'Your business is read in a Shop Local spot together with two other local businesses: your name, what you do and how to reach you. The spot is specific to your area.', 'icon' => 'spots', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
				array( 'title' => 'Choose your stations', 'text' => 'Pair WAUB or WGVA with Classic Hits 99.3, Mix 98.5 or 101.7 The Wall, or choose one premium station: WFLR or WCGR The Lake.', 'icon' => 'stream', 'logos' => array( array( 'file' => 'station-waub.svg', 'alt' => 'WAUB' ), array( 'file' => 'station-wgva.svg', 'alt' => 'WGVA' ), array( 'file' => 'station-finger-lakes-country.svg', 'alt' => 'WFLR Finger Lakes Country 95.9' ), array( 'file' => 'station-classic-hits-993.svg', 'alt' => 'Classic Hits 99.3' ), array( 'file' => 'station-mix-985.svg', 'alt' => 'Mix 98.5' ), array( 'file' => 'station-the-wall.webp', 'alt' => '101.7 The Wall' ), array( 'file' => 'station-the-lake.svg', 'alt' => 'WCGR The Lake' ) ) ),
				array( 'title' => 'An online listing with a link', 'text' => 'Your company name and logo appear on the Shop Local page of Finger Lakes Daily News, with a link to your website.', 'icon' => 'web', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'One membership for the year', 'text' => 'Join once for the February to January program year. Members from the year before can renew for the next one.', 'icon' => 'season', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'Station choices', 'items' => array( 'WAUB with Classic Hits 99.3, Mix 98.5 or 101.7 The Wall', 'WGVA with Classic Hits 99.3, Mix 98.5 or 101.7 The Wall', 'WFLR on its own', 'WCGR The Lake on its own' ) ),
			),
			'testimonial' => array( 'person' => 'Mike Speno', 'business' => 'Speno Music', 'quote' => 'There\'s no yellow pages or phone book, and there certainly isn\'t any local newspaper coverage … we\'ve reached all of our demographics through the FLX Local Media.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/speno-music/', 'video_seconds' => 60, 'thumb' => 'testimonial.jpg' ),
		),
		'contests' => array(
			'name' => 'Contests and Promotions',
			'eyebrow' => 'Contests and promotions · Advertising',
			'title' => 'Giveaways listeners enter',
			'tagline' => '',
			'lead' => 'Give away a prize, tickets or a gift card on our stations and Finger Lakes Daily News, and listeners enter online on a page that names your business.',
			'menu_group' => 'Advertising', 'menu_label' => 'Contests and promotions', 'menu_blurb' => 'Giveaways and station promotions that send listeners to your business.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '5,097', 'label' => 'entries on 16 giveaways since March 29, 2026', 'source' => 'FLX contest entries, 2026', 'source_detail' => 'FLX contest entry database (BigQuery fldn_analytics.contest_entries), entries held as ineligible removed, counted November 19, 2026' ),
				array( 'value' => '1,875', 'label' => 'different people entered at least one of those giveaways, counted by email address', 'source' => 'FLX contest entries, 2026', 'source_detail' => 'Same table and date, distinct email addresses' ),
				array( 'value' => '244', 'label' => 'new Finger Lakes Daily News email sign-ups in 10 days from one advertiser\'s cabin-stay giveaway (682 entries, May 19 to 28, 2026)', 'source' => 'FLDN contest records, July 2026', 'source_detail' => 'FLDN contest records cross-checked against the Mailchimp subscriber count, IBIB report, July 2026' ),
			),
			'who' => array( 'heading' => 'Who enters', 'paragraphs' => array( 'People enter a giveaway because they heard about it on the air or saw it on the station\'s page, and then took the time to fill in a form. That is a stronger signal than hearing a commercial in the background. The entry page names the prize and the business that is giving it.', 'The entry form has one box, unchecked, to join the Finger Lakes Daily News email. Nobody is signed up automatically. A household that won a station prize in the last 30 days cannot win again, so prizes go to more different people. The rules are posted on the General Contest Rules page and we follow them.' ) ),
			'offers' => array(
				array( 'title' => 'Your prize, announced on air', 'text' => 'Station hosts announce the prize, say who is giving it and tell listeners where to enter. Your business is named each time the giveaway is promoted.', 'icon' => 'contest', 'logos' => array( array( 'file' => 'station-waub.svg', 'alt' => 'WAUB' ), array( 'file' => 'station-wgva.svg', 'alt' => 'WGVA' ), array( 'file' => 'station-finger-lakes-country.svg', 'alt' => 'WFLR Finger Lakes Country 95.9' ), array( 'file' => 'station-classic-hits-993.svg', 'alt' => 'Classic Hits 99.3' ), array( 'file' => 'station-mix-985.svg', 'alt' => 'Mix 98.5' ), array( 'file' => 'station-the-wall.webp', 'alt' => '101.7 The Wall' ), array( 'file' => 'station-the-lake.svg', 'alt' => 'WCGR The Lake' ) ) ),
				array( 'title' => 'Your own entry page', 'text' => 'Each giveaway gets an entry page on Finger Lakes Daily News with the prize, the dates and the rules. It is listed under Contests and appears on the page of the station that runs it.', 'icon' => 'web', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Gift cards, tickets, stays and services', 'text' => 'Prizes we have run include gift cards, concert and show tickets, a cabin stay, a boat rental, a guitar and a pizza dinner. Any prize a local business can give works.', 'icon' => 'award' ),
				array( 'title' => 'Rules and the drawing handled for you', 'text' => 'We write the entry rules, take the entries and draw the winner at random from eligible entries. You supply the prize and decide the dates.', 'icon' => 'email' ),
			),
			'extra' => array(
				array( 'heading' => 'How a giveaway runs', 'items' => array( 'You choose the prize and the dates', 'We announce it on the air and publish the entry page', 'Listeners enter online', 'We draw the winner at random from eligible entries' ) ),
				array( 'heading' => 'Promotions we run ourselves', 'items' => array( 'Rock the Mic, May 2026: 101.7 The Wall asked listeners to send a 60 to 90 second audition video for a part-time morning co-host job', 'Top 250, June and July 2026: listeners voted online for the songs on a country ballot for WFLR and a rock ballot for 101.7 The Wall, and the countdowns aired July 3 and 4' ) ),
			),
			'testimonial' => array( 'person' => 'Ryan Phelps', 'business' => 'Ryan Phelps Auto Sales', 'quote' => 'That individual won a contest on Classic Hits. I said, well, it\'s a pretty good chance that we sold that car to her because she\'s a Classic Hits listener.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/ryan-phelps-auto-sales/', 'video_seconds' => 93, 'thumb' => 'testimonial.jpg' ),
		),
		'seasonal' => array(
			'name' => 'Seasonal Packages',
			'eyebrow' => 'Seasonal packages · Advertising',
			'title' => 'Packages for the time of year',
			'tagline' => '',
			'lead' => 'Sponsor the holiday season on Mix 98.5, send holiday greetings on any of our seven stations, or put your name on winter storm updates and closings reports.',
			'menu_group' => 'Advertising', 'menu_label' => 'Seasonal packages', 'menu_blurb' => 'Holiday, winter storm and other dated packages, with when each one runs.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '3', 'label' => 'presenting sponsors on Mix 98.5\'s all-Christmas music, which starts Monday, November 23', 'source' => 'Mix 98.5 sponsorship sheet, 2025', 'source_detail' => 'Mix 98.5 Christmas Music Sponsorship sheet, 2025; limit of three and the November 23 start confirmed at the November 2, 2026 sales meeting (fldn#2588)' ),
				array( 'value' => '7', 'label' => 'stations that can carry your holiday greeting', 'source' => 'Christmas Greetings sheet, 2025', 'source_detail' => 'Christmas Greetings sheet, 2025 (any or all of the seven stations)' ),
				array( 'value' => '7%', 'label' => 'more in-car radio listening than normal in the morning before Hurricane Isaias, 9 to 11 AM, in Mobile, Alabama. A Gulf hurricane is not a lake-effect storm, and we have no local storm measurement yet', 'source' => 'Westwood One, Oct 2026', 'source_detail' => 'Westwood One, October 19, 2026, using Xperi DTS AutoStage data' ),
			),
			'who' => array( 'heading' => 'Who listens in the season', 'paragraphs' => array( 'In late November the Christmas listener is the same person who listens the rest of the year, with a reason to keep the radio on longer. Mix 98.5 reaches about 44,600 people in a typical week in Ontario, Cayuga, Seneca, Wayne and Yates counties, and it plays Christmas music continuously during the run. A sponsor is heard by that audience in a season when many households are deciding where to shop and who to thank.', 'In winter the listener is a parent or an employee checking whether school or work is open. A storm update sponsor is heard at the moment people check, and a holiday greeting lets you say thank you in your own voice or your team\'s. Seasonal buyers often keep one schedule all year and change the message to match the season.' ) ),
			'offers' => array(
				array( 'title' => 'Holiday greetings', 'text' => 'Short produced holiday greetings that thank your customers, family and friends. Choose how many and on which stations, from late November through Christmas Day.', 'icon' => 'season', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
				array( 'title' => 'Mix 98.5 all-Christmas music sponsorship', 'text' => 'One of three presenting sponsors of the station\'s continuous Christmas music. Your owner and staff record short greetings that air through the run, your commercials run on Mix 98.5 and your name is read in the station\'s promotion of the event.', 'icon' => 'sponsorship', 'logos' => array( array( 'file' => 'station-mix-985.svg', 'alt' => 'Mix 98.5' ) ) ),
				array( 'title' => 'Winter storm updates', 'text' => 'Your name on winter storm updates and school and business closings reports, from December through March. A limited number of sponsors are available on each station.', 'icon' => 'news', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
				array( 'title' => 'One schedule, a new message each season', 'text' => 'Keep a yearly schedule on the air and change the copy when the season changes: winter vehicles, tax time, back to school or a service promotion. You change the message, not the contract.', 'icon' => 'spots', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'When each package runs (2026-27)', 'items' => array( 'Mix 98.5 all-Christmas music: starts Monday, November 23, 2026', 'Holiday greetings: late November through Christmas Day', 'Winter storm updates: December through March' ) ),
			),
			'testimonial' => array( 'person' => 'Ryan Phelps', 'business' => 'Ryan Phelps Auto Sales', 'quote' => 'What\'s nice is as we\'re changing our messaging, winter SUVs, then tax time, then back to school … we know that we have the annual commitment with you guys and all we have to do is change up our messages.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/ryan-phelps-auto-sales/', 'video_seconds' => 93, 'thumb' => 'testimonial.jpg' ),
		),
		'events' => array(
			'name' => 'Events',
			'eyebrow' => 'Events · Advertising',
			'title' => 'Events and live remotes',
			'tagline' => '',
			'lead' => 'Bring a station host to your location, promote your event on the air and on the Finger Lakes Daily News calendar, and follow it with a recap article.',
			'menu_group' => 'Advertising', 'menu_label' => 'Events', 'menu_blurb' => 'Live remotes, event promotion and sponsorships for your next event.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '484', 'label' => 'community events approved for the Finger Lakes Daily News calendar since March 30, 2026', 'source' => 'FLX event calendar records, Nov 2026', 'source_detail' => 'FLX event pipeline records (BigQuery fldn_analytics.event_pipeline_state, status approved), counted November 19, 2026' ),
				array( 'value' => '210K+', 'label' => 'people use Finger Lakes Daily News each month, where your event is listed', 'source' => 'Google Analytics, Sep 2026', 'source_detail' => 'Google Analytics 4, September 2026, as published on flxlocalmedia.com' ),
				array( 'value' => '1 hour', 'label' => 'live from your location with a station host in a live remote broadcast', 'source' => 'FLX Live Remote product sheet', 'source_detail' => 'FLX Local Media Live Remote Broadcast product sheet, customer-flx products.json' ),
			),
			'who' => array( 'heading' => 'Who comes to local events', 'paragraphs' => array( 'People hear about an event from the radio, from a friend and from a search, and the three work together. The owner of The Main Deck in Penn Yan tells of a woman who works at a store near her who asked whether the restaurant\'s February event was this weekend, because she had heard it on the radio. She was not a customer yet. She had the date because she had heard it.', 'Finger Lakes Daily News is where people look to see what is on this weekend. Station pages also show upcoming events that are tagged to the station. A promoted event listing, an on-air campaign and a live remote reach the same community at three points: while they plan, on the day and afterward.' ) ),
			'offers' => array(
				array( 'title' => 'Live remote broadcast', 'text' => 'A one-hour live broadcast from your business with a station host, live mentions during the hour and promotional spots the week before. Add a Facebook Live video of the event.', 'icon' => 'remote', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
				array( 'title' => 'Facebook Live event', 'text' => 'A live video from your location with on-air talent for up to an hour, promoted on the radio before the event. It can be shared on other stations\' pages.', 'icon' => 'people', 'logos' => array( array( 'file' => 'ext-facebook-f.svg', 'alt' => 'Facebook' ) ) ),
				array( 'title' => 'Event promotion on air and on the calendar', 'text' => 'Radio promotion before your event, plus a promoted listing on the Finger Lakes Daily News calendar with featured placement, your poster, a mention in the Local Daily Headlines newsletter and a post on our social channels.', 'icon' => 'people', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ), array( 'file' => 'ext-facebook-f.svg', 'alt' => 'Facebook' ), array( 'file' => 'ext-instagram.svg', 'alt' => 'Instagram' ) ) ),
				array( 'title' => 'Community event sponsorships', 'text' => 'The Finger Lakes has dozens of festivals, fairs and community events every year. We help you choose the ones that match your customers and plan the sponsorship and its promotion.', 'icon' => 'sponsorship', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
				array( 'title' => 'Custom events', 'text' => 'Grand openings, anniversary celebrations and customer appreciation days. We help plan the event and promote it across radio, digital and content.', 'icon' => 'award', 'logos' => array( array( 'file' => 'flx-local-media-logo.svg', 'alt' => 'FLX Local Media' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'How an event campaign runs', 'items' => array( 'Before: radio promotion and a promoted listing on the Finger Lakes Daily News calendar', 'During: a live remote or a Facebook Live video from your location', 'After: a recap article on Finger Lakes Daily News and social posts' ) ),
			),
			'testimonial' => array( 'person' => 'Alex Thompson', 'business' => 'The Main Deck', 'quote' => 'I went into a local store and one of my friend\'s moms works there, and she was asking about our event … She said, "That\'s this weekend, right? I heard it on the radio." It was kind of cool to hear that she had actually heard it on that platform.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/main-deck/', 'video_seconds' => 105, 'thumb' => 'testimonial.jpg' ),
		),
		'fldn' => array(
			'name' => 'Finger Lakes Daily News',
			'eyebrow' => 'FLDN · Advertising',
			'title' => 'Finger Lakes Daily News',
			'tagline' => '',
			'lead' => 'Put your business next to the local stories people read every day: news, the police blotter, obituaries, weather and sports.',
			'menu_group' => 'Media', 'menu_label' => 'Finger Lakes Daily News', 'menu_blurb' => 'Display ads and sponsorships on the local news site.',
			'colors' => array( 'primary' => '#1e5b70', 'deep' => '#002936', 'accent' => '#e3a72f' ),
			'logo' => 'logo.png',
			'hero_mark' => 'mark.webp', 'hero_mark_style' => 'panel',
			'stats' => array(
				array( 'value' => '380,494', 'label' => 'visits to the site in October 2026', 'source' => 'Google Analytics, Oct 2026', 'source_detail' => 'Google Analytics sessions, BigQuery fldn_analytics.ga4_daily_traffic, Oct 1 to 31, 2026. Automated desktop traffic is removed, so this is about 6% below the Google Analytics screen.' ),
				array( 'value' => '717,725', 'label' => 'pages viewed in October 2026', 'source' => 'Google Analytics, Oct 2026', 'source_detail' => 'Google Analytics pageviews, BigQuery fldn_analytics.ga4_daily_traffic, Oct 1 to 31, 2026, same bot filter.' ),
				array( 'value' => '236,816', 'label' => 'of those page views were on the police blotter and obituary pages', 'source' => 'Google Analytics, Oct 2026', 'source_detail' => 'BigQuery fldn_analytics.ga4_daily_pages, Oct 2026: paths starting /police-blotter (150,901) plus /obituar (85,915).' ),
				array( 'value' => '75%', 'label' => 'of visits come from a phone or tablet', 'source' => 'Google Analytics, Oct 2026', 'source_detail' => 'Google Analytics raw export (BigQuery analytics_357581011.events_*), Oct 2026: 296,703 of 395,448 distinct sessions, desktop Linux visits excluded.' ),
			),
			'who' => array( 'heading' => 'Who reads Finger Lakes Daily News', 'paragraphs' => array( 'Finger Lakes Daily News reports on Cayuga, Ontario, Seneca, Wayne, Yates, Schuyler, Steuben and Tompkins counties, from Auburn and Geneva to Penn Yan and Ithaca. Local news is the most-read section, followed by the police blotter and the obituaries. People open those pages because they want to know what happened in their own town.', 'Most visits come from a phone or tablet, so every ad runs in a size that fits a small screen as well as a computer. Your ad appears on the same pages as the stories readers came for, and FLX Local Media reports impressions and clicks for each ad.' ) ),
			'offers' => array(
				array( 'title' => 'Home page ads', 'text' => 'A top banner or box ad on the home page, shown on phones and computers.', 'icon' => 'web', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Run of site ads', 'text' => 'Your banner on article pages and the other inside pages, or a sitewide top banner that also covers the home page.', 'icon' => 'web', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Section sponsorships', 'text' => 'Your banner at the top of the police blotter, obituaries or sports section.', 'icon' => 'sponsorship', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Sponsored articles', 'text' => 'A story about your business, published on the site beside our news. Options add a home page feature, social promotion and a mention in the daily email.', 'icon' => 'news', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Home page video', 'text' => 'Your video plays in a player on the home page. We can film and edit it at your location.', 'icon' => 'web', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Website plus daily email', 'text' => 'A website ad and an ad in the FLDN daily email, in one package.', 'icon' => 'email', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'Where your ad can run', 'items' => array( 'Home page: top banner or box ad', 'Every page: a sitewide top banner', 'Inside pages: articles, weather, the calendar and more (run of site)', 'Police blotter section: top banner', 'Obituaries section: top banner', 'Sports section: top banner', 'Home page video' ) ),
				array( 'heading' => 'Ad sizes', 'items' => array( 'Desktop banner: 728 by 90 pixels', 'Phone banner: 600 by 200 pixels', 'Box ad: 300 by 250 pixels', 'Send your logo and your offer and we will build the ad for you.' ) ),
			),
			'testimonial' => array( 'person' => 'Alex Thompson', 'business' => 'The Main Deck', 'quote' => 'We’re in such a digital era right now. People get on their phones to look up things. So I think it’s just really important to stay relevant.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/main-deck/', 'video_seconds' => 105, 'thumb' => 'testimonial.jpg' ),
		),
		'newsletter' => array(
			'name' => 'FLDN Daily Newsletter',
			'eyebrow' => 'FLDN Newsletter · Advertising',
			'title' => 'The FLDN Daily Email',
			'tagline' => '',
			'lead' => 'Put your ad above or below the local news email that goes to subscribers every morning, with clicks reported for each day it runs.',
			'menu_group' => 'Media', 'menu_label' => 'FLDN newsletter', 'menu_blurb' => 'Sponsor the daily local news email.',
			'colors' => array( 'primary' => '#1e5b70', 'deep' => '#002936', 'accent' => '#e3a72f' ),
			'logo' => 'logo.png',
			'hero_mark' => 'mark.webp', 'hero_mark_style' => 'panel',
			'stats' => array(
				array( 'value' => '5,065', 'label' => 'subscribers received the November 19, 2026 email, up from 2,980 on March 2', 'source' => 'Mailchimp send records, 2026', 'source_detail' => 'BigQuery fldn_analytics.mailchimp_campaigns, emails_sent for the Finger Lakes Daily News RSS campaign on 2026-11-19 (5,065) and 2026-03-02 (2,980).' ),
				array( 'value' => '11.6%', 'label' => 'of subscribers clicked a link in the average send in October 2026', 'source' => 'Mailchimp send records, Oct 2026', 'source_detail' => 'BigQuery fldn_analytics.mailchimp_campaigns, 31 daily sends Oct 1 to 31, 2026: sum of subscriber_clicks (17,643) divided by sum of emails_sent. Each subscriber counts once per send.' ),
				array( 'value' => '569', 'label' => 'readers clicked through to the site from the average send', 'source' => 'Mailchimp send records, Oct 2026', 'source_detail' => 'Same table and period: average subscriber_clicks per send, range 277 to 806.' ),
				array( 'value' => '31 of 31', 'label' => 'mornings in October the email went out, between 7:02 and 7:04 AM Eastern', 'source' => 'Mailchimp send records, Oct 2026', 'source_detail' => 'Same table: send_time of the 31 daily sends, converted to America/New_York.' ),
			),
			'who' => array( 'heading' => 'Who reads the FLDN daily email', 'paragraphs' => array( 'Readers join by signing up on the Finger Lakes Daily News website to get the top local stories each morning. Each email lists up to ten of the newest local stories from the past day, so it covers the towns your customers live in.', 'The email goes out every day, including weekends, and the list has grown every month this year. Each ad is a tracked link, so you can see how many readers clicked it on each day it ran.' ) ),
			'offers' => array(
				array( 'title' => 'Top banner', 'text' => 'Your ad above the day\'s stories in every send for the term you choose.', 'icon' => 'email', 'logos' => array( array( 'file' => 'fldn-icon.png', 'alt' => 'FLDN' ) ) ),
				array( 'title' => 'Bottom banner', 'text' => 'Your ad below the stories, for readers who read to the end.', 'icon' => 'email', 'logos' => array( array( 'file' => 'fldn-icon.png', 'alt' => 'FLDN' ) ) ),
				array( 'title' => 'Full newsletter sponsorship', 'text' => 'Exclusive branding in the daily email, so your business is the one sponsor readers see.', 'icon' => 'sponsorship', 'logos' => array( array( 'file' => 'fldn-icon.png', 'alt' => 'FLDN' ) ) ),
				array( 'title' => 'Single-send feature', 'text' => 'A sponsored section in one day\'s email, for a sale, an event or a deadline.', 'icon' => 'news', 'logos' => array( array( 'file' => 'fldn-icon.png', 'alt' => 'FLDN' ) ) ),
				array( 'title' => 'Website plus email package', 'text' => 'An ad on fingerlakesdailynews.com and an ad in the daily email, in one package.', 'icon' => 'web', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'How the email works', 'items' => array( 'Sent every day at about 7 AM Eastern, weekends included', 'Up to ten of the newest local stories from the past day, taken from our reporting', 'Two ad positions: a banner above the stories and a banner below them', 'Banners are 600 pixels wide. Send your logo and your offer and we will build the ad for you.', 'Clicks are reported for each ad' ) ),
			),
			'testimonial' => array( 'person' => 'Brian Chapman', 'business' => 'JD Chapman Insurance', 'quote' => 'Consistency is highly important … to keep that brand recognition out there, to keep your name front of mind.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/jd-chapman-insurance/', 'video_seconds' => 94, 'thumb' => 'testimonial.jpg' ),
		),
		'flx-fm' => array(
			'name' => 'FLX.fm',
			'eyebrow' => 'FLX.fm · Advertising',
			'title' => 'FLX.fm',
			'tagline' => 'Every Finger Lakes station. One free app.',
			'lead' => 'Reach people who listen to our seven stations online, in the free FLX.fm app and on smart speakers, with listening hours we count from our own stream logs.',
			'menu_group' => 'Media', 'menu_label' => 'FLX.fm app and streaming', 'menu_blurb' => 'Ads on the live streams, with listening we count ourselves.',
			'colors' => array( 'primary' => '#6b2fa0', 'deep' => '#340057', 'accent' => '#e3a72f' ),
			'logo' => 'logo.png',
			'hero_mark' => 'mark.webp', 'hero_mark_style' => 'app',
			'stats' => array(
				array( 'value' => '45,159', 'label' => 'hours of listening on our streams in the last 28 days', 'source' => 'FLX.fm stream logs, Oct-Nov 2026', 'source_detail' => 'BigQuery fldn_analytics.v_streaming_raw_sessions (sessions of 60 seconds or longer, bots and re-streamers removed), all seven stations, Oct 22 to Nov 18, 2026, sum of duration_secs divided by 3,600.' ),
				array( 'value' => '11,141', 'label' => 'different internet connections tuned in', 'source' => 'FLX.fm stream logs, Oct-Nov 2026', 'source_detail' => 'Same view and period: distinct ip_address, the dashboard\'s definition of unique listeners (v_dash_streaming_daily). One connection can be several people, and one person can use several connections.' ),
				array( 'value' => '76%', 'label' => 'of listening hours fall between 6 AM and 6 PM Eastern', 'source' => 'FLX.fm stream logs, Oct-Nov 2026', 'source_detail' => 'Same view and period: share of duration_secs with the session timestamp in America/New_York hours 6 through 17 (75.6%).' ),
				array( 'value' => '52%', 'label' => 'of listening hours come from the Rochester, Syracuse and Elmira areas', 'source' => 'FLX.fm stream logs, Oct-Nov 2026', 'source_detail' => 'Same view and period: dma_code 538, 555 and 565 (51.7%). Another 9.8% has no location.' ),
			),
			'who' => array( 'heading' => 'Who listens on FLX.fm and the streams', 'paragraphs' => array( 'Most listening happens during the workday and on weekdays, so the streams reach people while they work. They listen in the FLX.fm app, in a web browser, on Alexa speakers and through TuneIn, and every listen is counted in our own stream logs.', 'About half of listening hours come from the Rochester, Syracuse and Elmira areas where our stations are heard. The rest come from elsewhere in New York and the country, including Alexa listeners outside our market. We say this plainly so a local business knows which part of the audience is nearby.' ) ),
			'offers' => array(
				array( 'title' => 'Spots on the live streams', 'text' => 'Commercials in a station\'s schedule are heard on that station\'s stream as well as on the radio.', 'icon' => 'spots', 'logos' => array( array( 'file' => 'flx-fm-icon.png', 'alt' => 'FLX.fm' ) ) ),
				array( 'title' => 'Show and hour sponsorships', 'text' => 'Put your name on a show, a music hour or a newscast. Listeners on the stream hear the same programming.', 'icon' => 'sponsorship', 'logos' => array( array( 'file' => 'flx-fm-icon.png', 'alt' => 'FLX.fm' ) ) ),
				array( 'title' => 'Player banner', 'text' => 'A banner beside the stream player on fingerlakesdailynews.com, where listeners choose a station.', 'icon' => 'web', 'logos' => array( array( 'file' => 'flx-fm-icon.png', 'alt' => 'FLX.fm' ), array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Listening numbers for your stations', 'text' => 'Your rep can show the streaming hours and listeners for the stations in your plan, counted from our own stream logs.', 'icon' => 'stream', 'logos' => array( array( 'file' => 'flx-fm-icon.png', 'alt' => 'FLX.fm' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'How people listen (share of listening hours, last 28 days)', 'items' => array( 'Phone and tablet apps, including FLX.fm and older station apps: 30%', 'Computer web browsers: 23%', 'Alexa smart speakers: 18%', 'TuneIn: 10%', 'Phone web browsers: 9%', 'Everything else: 10%' ) ),
			),
			'testimonial' => array( 'person' => 'Michael Enslow', 'business' => 'Enstech Automotive and Trailer', 'quote' => '…to have the staff actually come here and show you where you’re spending your money and show you how it’s paying off … Other forms of advertising we do, I don’t really get that.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/enstech-automotive/', 'video_seconds' => 90, 'thumb' => 'testimonial.jpg' ),
		),
		'digital' => array(
			'name' => 'Digital Services',
			'eyebrow' => 'Digital Services · Overview',
			'title' => 'Be easy to find when customers look you up',
			'tagline' => '',
			'lead' => 'FLX Local Media builds your website, keeps your business listings correct, runs your Google and Facebook ads and produces your video, from offices in Geneva, Auburn and Penn Yan.',
			'menu_group' => 'Digital Services', 'menu_label' => 'Overview', 'menu_blurb' => 'Websites, search, ads and video from a team in Geneva, Auburn and Penn Yan.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '3', 'label' => 'Local offices: Geneva, Auburn and Penn Yan', 'source' => 'FLX Local Media office list', 'source_detail' => 'FLX Local Media office list (existing digital page and contact pages)' ),
				array( 'value' => '7', 'label' => 'Radio stations your digital campaigns can work alongside', 'source' => 'FLX Local Media station list', 'source_detail' => 'FLX Local Media station list' ),
				array( 'value' => '210K+', 'label' => 'Monthly users on Finger Lakes Daily News, where display ads run', 'source' => 'Google Analytics, Sep 2026', 'source_detail' => 'Google Analytics 4, September 2026 (BigQuery export: 217,441 users)' ),
				array( 'value' => '730K+', 'label' => 'Monthly pageviews on Finger Lakes Daily News', 'source' => 'Google Analytics, Sep 2026', 'source_detail' => 'Google Analytics 4, September 2026 (BigQuery export: 737,096 page views)' ),
			),
			'who' => array( 'heading' => 'Who finds you online', 'paragraphs' => array( 'A customer who hears your name on the radio or sees it in the news often looks it up on a phone a little later. Three things decide what happens next: whether your business comes up, whether the phone number can be tapped, and whether the hours are right.', 'Picture a homeowner whose furnace stops on a cold night. She heard a heating company\'s radio spot last month, searches for the name and calls the result that gives her a number she can tap. This is an example, not a real customer. Digital Services is the work that makes sure that result is yours, and that your radio, website, listings and search ads all say the same thing.' ) ),
			'offers' => array(
				array( 'title' => 'Websites', 'text' => 'A website on your own web address that loads fast on phones, with tap-to-call, directions and a contact form. Plans, prices and what each includes are on the Website Starter page.', 'icon' => 'web' ),
				array( 'title' => 'Found on Google and AI', 'text' => 'Your business details kept correct on Google, Facebook and Bing, business-details code on your site that search engines and AI assistants can read, a review link with a printable QR card, and a monthly progress report.', 'icon' => 'list', 'logos' => array( array( 'file' => 'ext-google.svg', 'alt' => 'Google' ), array( 'file' => 'ext-facebook-f.svg', 'alt' => 'Facebook' ), array( 'file' => 'ext-bing.svg', 'alt' => 'Bing' ) ) ),
				array( 'title' => 'Google Ads', 'text' => 'Paid search that shows your business to people searching for what you sell. We choose the keywords, write the ads, manage the bids and optimize for qualified leads.', 'icon' => 'search', 'logos' => array( array( 'file' => 'ext-google-ads.svg', 'alt' => 'Google Ads' ) ) ),
				array( 'title' => 'Retargeting and geo-targeting', 'text' => 'Stay in front of people who have visited your website, and reach people in the places you choose, on Google and on their phones.', 'icon' => 'people', 'logos' => array( array( 'file' => 'ext-google-ads.svg', 'alt' => 'Google Ads' ) ) ),
				array( 'title' => 'Facebook and Instagram', 'text' => 'Ads aimed at the areas and audiences you pick, and posts written for your page that you approve before they go out.', 'icon' => 'people', 'logos' => array( array( 'file' => 'ext-facebook-f.svg', 'alt' => 'Facebook' ), array( 'file' => 'ext-instagram.svg', 'alt' => 'Instagram' ), array( 'file' => 'ext-meta.svg', 'alt' => 'Meta' ) ) ),
				array( 'title' => 'Video production', 'text' => 'Planning, filming on location, editing, graphics and distribution across digital channels.', 'icon' => 'video' ),
			),
			'extra' => array(
				array( 'heading' => 'Works with the rest of FLX Local Media', 'items' => array( 'Display ads on Finger Lakes Daily News, including the police blotter, obituaries and sports pages', 'Sponsored articles and video through content marketing', 'Radio on seven stations, so people who hear your name can find you when they search', 'Reporting you can read, so you can see what is working' ) ),
			),
			'testimonial' => array( 'person' => 'Brian Chapman', 'business' => 'JD Chapman Insurance', 'quote' => 'We\'ve been using FLX Digital to help drive traffic to our website to produce more leads and write more business. So that\'s been a good success for us.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/jd-chapman-insurance/', 'video_seconds' => 94, 'thumb' => 'testimonial.jpg' ),
		),
		'website-starter' => array(
			'name' => 'Website Starter',
			'eyebrow' => 'Digital Services · Websites',
			'title' => 'A website built, run and kept current for you',
			'tagline' => '',
			'lead' => 'We build your website, put it live on your own web address and run it for a full year on our Found plan for $1,500, then $80 a month, or $170 for Growing, and you can cancel any time after year one.',
			'menu_group' => 'Digital Services', 'menu_label' => 'Website Starter', 'menu_blurb' => 'A website built, put live and run for you: $1,500 the first year.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '$1,500', 'label' => 'First year: the build, your web address, going live and a full year of the Found plan', 'source' => 'FLX website plans, Nov 2026', 'source_detail' => 'FLX Local Media website plans, set November 6, 2026' ),
				array( 'value' => '$80', 'label' => 'Per month for Found after year one, month to month', 'source' => 'FLX website plans, Nov 2026', 'source_detail' => 'FLX Local Media website plans, set November 6, 2026' ),
				array( 'value' => '$170', 'label' => 'Per month for Growing after year one, month to month', 'source' => 'FLX website plans, Nov 2026', 'source_detail' => 'FLX Local Media website plans, set November 6, 2026' ),
				array( 'value' => '1 day', 'label' => 'To make a small change you send by email or text, on Found and Growing', 'source' => 'FLX website plans, Nov 2026', 'source_detail' => 'FLX Local Media website plans, set November 6, 2026 (next business day)' ),
			),
			'who' => array( 'heading' => 'Who this is for', 'paragraphs' => array( 'Website Starter is for a Finger Lakes business that has no website, or only a Facebook page. A Facebook page belongs to Facebook, not to you, and your hours and prices get buried under newer posts. A website on your own web address gives customers one place to see what you do, tap to call and get directions.', 'Picture the owner of a plumbing, hair or landscaping business who gets most work from word of mouth. Someone who has never heard of that business searches for the trade and the town. The owner needs a site that explains what the business does, shows photos of real work and gives a number to call. This is an example, not a real customer. We often start by building a first version from your public details, so you can look at a real page before you decide.' ) ),
			'offers' => array(
				array( 'title' => 'Year one: build and go live', 'text' => 'The build, your web address, getting the site live and a full year of the Found plan. Nothing goes live until you approve it, and nothing is billed monthly until year two.', 'icon' => 'web' ),
				array( 'title' => 'The Found plan', 'text' => 'Up to 10 pages. Unlimited small changes by email or text. Your Google listing managed with photos and weekly posts, online booking or ordering linked to your site, an email sign-up with the list sent to you, a monthly report and a call every quarter.', 'icon' => 'list', 'logos' => array( array( 'file' => 'ext-google.svg', 'alt' => 'Google' ), array( 'file' => 'ext-google-maps.svg', 'alt' => 'Google Maps' ) ) ),
				array( 'title' => 'The Growing plan', 'text' => 'Everything in Found with up to 25 pages, plus your business on more than 40 directories, review requests with a counter card, a new page or seasonal update and an email to your customers every month, a spotlight in the Finger Lakes Daily News newsletter every quarter and a call every month.', 'icon' => 'email', 'logos' => array( array( 'file' => 'ext-google.svg', 'alt' => 'Google' ), array( 'file' => 'ext-bing.svg', 'alt' => 'Bing' ), array( 'file' => 'ext-yelp.svg', 'alt' => 'Yelp' ), array( 'file' => 'ext-facebook-f.svg', 'alt' => 'Facebook' ), array( 'file' => 'ext-nextdoor.svg', 'alt' => 'Nextdoor' ) ) ),
				array( 'title' => 'Small changes by email or text', 'text' => 'New hours, text, photos, prices, a swapped menu or flyer. Anything we can do in under 30 minutes is a small change, and small changes are unlimited. Larger work, such as a new section or a redesign, is quoted before we start.', 'icon' => 'email' ),
				array( 'title' => 'Found on Google and AI search', 'text' => 'We describe your business to Google, Bing and AI assistants such as ChatGPT in the form they read: business type, address, hours, services and links to your listings. We do not promise a ranking.', 'icon' => 'search', 'logos' => array( array( 'file' => 'ext-google.svg', 'alt' => 'Google' ), array( 'file' => 'ext-bing.svg', 'alt' => 'Bing' ) ) ),
				array( 'title' => 'A plain report on how it is doing', 'text' => 'A report by email each month, and a call with us every quarter on Found or every month on Growing.', 'icon' => 'award' ),
			),
			'extra' => array(
				array( 'heading' => 'In every plan', 'items' => array( 'Fast on phones, secure, backed up and watched around the clock', 'Your web address registered and renewed by us, and transferred to you if you ever leave', 'A listing in the Finger Lakes Daily News directory', 'Google Analytics, Google Search Console and Bing Webmaster Tools set up in your name', 'If you are switching from an old site, every old page address redirected', 'A monthly speed and search check, with fixes included' ) ),
				array( 'heading' => 'What no plan includes', 'items' => array( 'Online stores with a cart and checkout. We connect the ordering or store link you already use, such as Square, Shopify, Toast or Clover. A full store is a separate quote.', 'More than 25 pages, or custom features such as member logins, forms that feed another system or several languages. These are quoted separately.', 'A second location. It is its own plan at 15% off.', 'Video files stored on the site. Video plays from YouTube or Vimeo.' ) ),
				array( 'heading' => 'What we will not promise', 'items' => array( 'A Google ranking. Nobody controls Google\'s results.', 'That a listing in the Finger Lakes Daily News directory moves you up in Google. It puts you in front of local readers.' ) ),
				array( 'heading' => 'How year two works', 'items' => array( 'We write to you in writing 15 to 45 days before year one ends and ask which plan you want. If you do nothing, you stay on Found.', 'Growing includes directory setup on a 12-month commitment. Month to month, a $295 setup applies.', 'After year one you can cancel any time.' ) ),
			),
			'testimonial' => array( 'person' => 'Alex Thompson', 'business' => 'The Main Deck', 'quote' => 'If you have a radio ad that says ‘go check the website,’ and then things on the digital side that say ‘check out the radio spot at such and such a time,’ that gets people more engaged across both platforms. They feed each other.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/main-deck/', 'video_seconds' => 105, 'thumb' => 'testimonial.jpg' ),
		),
		'listings' => array(
			'name' => 'Listings',
			'eyebrow' => 'Digital Services · Listings',
			'title' => 'Correct details everywhere customers look you up',
			'tagline' => '',
			'lead' => 'We confirm your name, address, phone, website and hours once, then keep them matching on Google, Facebook, Bing and dozens of other listing sites, and check every month that nothing has drifted.',
			'menu_group' => 'Digital Services', 'menu_label' => 'Listings', 'menu_blurb' => 'Correct hours, phone and address on Google, Facebook, Bing and dozens more sites.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '42', 'label' => 'Listing sites in our catalog: 19 core sites plus 23 chamber, industry and extra sites, chosen to fit your business', 'source' => 'FLX listings catalog, Nov 2026', 'source_detail' => 'FLX Local Media listings tool site catalog, November 2026' ),
				array( 'value' => '5', 'label' => 'Minutes between our checks for a change to your details. Google, Facebook and Discover FLX are updated right after, once you approve our access', 'source' => 'FLX listings tool', 'source_detail' => 'FLX Local Media listings tool, change watcher runs every 5 minutes' ),
				array( 'value' => '7 to 12', 'label' => 'Days for a new Bing listing to go live after it is imported from Google', 'source' => 'Bing Places help pages, Oct 2026', 'source_detail' => 'Microsoft Bing Places help pages, read October 23, 2026' ),
			),
			'who' => array( 'heading' => 'Who this is for', 'paragraphs' => array( 'Listings is for any business that customers look up on a phone, whether or not it has a website. When the hours, phone number or website address differ from one place to the next, customers call a number that no longer works or arrive when you are closed. We fix that once and keep it fixed.', 'We ran our own business through the check first. On October 24, 2026 it found that Google listed an old website address for our Geneva office and that a Facebook listing sent visitors to a page that no longer exists. We corrected the Google profile on October 27.' ) ),
			'offers' => array(
				array( 'title' => 'One confirmed record', 'text' => 'We write down your business name, address, phone, website, hours, categories and the phrases customers search for. You confirm it, and that record is what every listing is checked against.', 'icon' => 'list' ),
				array( 'title' => 'Google, Facebook and Bing kept current', 'text' => 'When a detail changes, Google and Facebook are updated within minutes once you have approved our access request. Bing copies Google every week.', 'icon' => 'search', 'logos' => array( array( 'file' => 'ext-google-maps.svg', 'alt' => 'Google Maps' ), array( 'file' => 'ext-facebook-f.svg', 'alt' => 'Facebook' ), array( 'file' => 'ext-bing.svg', 'alt' => 'Bing' ) ) ),
				array( 'title' => 'Other listing sites, handled by our team', 'text' => 'Yelp, Foursquare, Nextdoor, Better Business Bureau, Yellow Pages, Alignable and more. Where a site needs you to verify by phone or email, we tell you what to expect.', 'icon' => 'people', 'logos' => array( array( 'file' => 'ext-yelp.svg', 'alt' => 'Yelp' ), array( 'file' => 'ext-foursquare.svg', 'alt' => 'Foursquare' ), array( 'file' => 'ext-nextdoor.svg', 'alt' => 'Nextdoor' ), array( 'file' => 'ext-bbb.svg', 'alt' => 'Better Business Bureau' ) ) ),
				array( 'title' => 'A monthly check and report', 'text' => 'Every morning we recheck every client and put back anything that has drifted. On the 1st of each month we run a full check of Google, your website, Facebook, Bing data and our directories, and send you a one-page report.', 'icon' => 'email' ),
				array( 'title' => 'Review link and counter card', 'text' => 'A direct link to your Google review page and a printable QR card for the counter, so a happy customer can leave a review in a few taps.', 'icon' => 'award', 'logos' => array( array( 'file' => 'ext-google.svg', 'alt' => 'Google' ) ) ),
				array( 'title' => 'Website check and business-details code', 'text' => 'We check your website the way Google and AI assistants read it, show you what to fix, and give you code that states your business details and links your listings together. We also check where you show up on Google Maps for the phrases your customers search.', 'icon' => 'web', 'logos' => array( array( 'file' => 'ext-google.svg', 'alt' => 'Google' ), array( 'file' => 'ext-google-maps.svg', 'alt' => 'Google Maps' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'Where your details are kept correct', 'items' => array( 'Google Search, Maps and Waze', 'Facebook and Instagram', 'Bing, Copilot and Windows maps', 'The Finger Lakes Daily News directory and Discover FLX', 'Yelp, Foursquare, Nextdoor, Better Business Bureau, Yellow Pages, Alignable, Data Axle and OpenStreetMap', 'Chamber of commerce directories in Geneva, Yates, Seneca and Cayuga, where they apply', 'Industry directories such as Houzz, Angi, Tripadvisor and Healthgrades, only for businesses they fit' ) ),
				array( 'heading' => 'How it works with you', 'items' => array( 'You confirm your details once.', 'You approve the access request that Google and Facebook send you, so we can update your listings.', 'Changes go through us. If you edit your hours directly on Facebook, our next morning check puts back what your confirmed record says, so ask us and we update every site at once.' ) ),
				array( 'heading' => 'Also inside Website Starter', 'items' => array( 'The Found plan includes your Google listing managed by us', 'The Growing plan adds your business on more than 40 directories and review requests' ) ),
			),
			'testimonial' => array( 'person' => 'Michael Enslow', 'business' => 'Enstech Automotive and Trailer', 'quote' => '…later on, when you\'re home and relaxing, you search for it. And being easy to find online pays off, because there\'s so much competition in the digital space.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/enstech-automotive/', 'video_seconds' => 90, 'thumb' => 'testimonial.jpg' ),
		),
		'content-marketing' => array(
			'name' => 'Content Marketing',
			'eyebrow' => 'Digital Services · Content marketing',
			'title' => 'Explain what you do to people who read local news',
			'tagline' => '',
			'lead' => 'We help you tell your story on Finger Lakes Daily News with labeled sponsored articles, ads in the sections your customers read, and video.',
			'menu_group' => 'Digital Services', 'menu_label' => 'Content marketing', 'menu_blurb' => 'Sponsored articles, section ads and video on Finger Lakes Daily News.',
			'colors' => array( 'primary' => '#003b5b', 'deep' => '#00273e', 'accent' => '#e3a72f' ),
			'logo' => '',
			'stats' => array(
				array( 'value' => '210K+', 'label' => 'Monthly users on Finger Lakes Daily News', 'source' => 'Google Analytics, Sep 2026', 'source_detail' => 'Google Analytics 4, September 2026 (BigQuery export: 217,441 users)' ),
				array( 'value' => '730K+', 'label' => 'Monthly pageviews', 'source' => 'Google Analytics, Sep 2026', 'source_detail' => 'Google Analytics 4, September 2026 (BigQuery export: 737,096 page views)' ),
				array( 'value' => '64%', 'label' => 'Of October 2026 readers were in New York State', 'source' => 'Google Analytics, Oct 2026', 'source_detail' => 'Google Analytics 4 BigQuery export, October 2026: 137,531 of 214,284 users with a New York location' ),
				array( 'value' => '42%', 'label' => 'Of October 2026 pageviews were local news stories', 'source' => 'Google Analytics, Oct 2026', 'source_detail' => 'Google Analytics 4 page report in BigQuery (ga4_daily_pages), October 2026: 302,666 of 717,725' ),
			),
			'who' => array( 'heading' => 'Who reads Finger Lakes Daily News', 'paragraphs' => array( 'People come to Finger Lakes Daily News to find out what is happening near them: local news, the police blotter, obituaries, weather and sports. Most of them are in New York State, and most of what they read is about their own towns. They arrive with a question about their community, which is a good moment to explain something useful about your business.', 'Sponsored content reads best when it teaches the reader something: how to choose a contractor, what to check before buying a trailer, what a heating tune-up includes. Every sponsored article is labeled as paid and sits in its own section, so write it as useful information and not as news.' ) ),
			'offers' => array(
				array( 'title' => 'Sponsored articles', 'text' => 'A full article about your business, labeled Sponsored Content, with your name as the sponsor and a link to your website. You supply the facts, our team helps with the writing, and a person reviews it before it publishes. Finger Lakes Daily News editors are not involved.', 'icon' => 'news', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Section placement', 'text' => 'Display ads on the pages your customers already use: the police blotter, obituaries and sports pages, plus the homepage and article pages. A law firm, a funeral home and a team sponsor each fit a different section. Section ads run on a three-month minimum.', 'icon' => 'spots', 'logos' => array( array( 'file' => 'fldn-logo.png', 'alt' => 'Finger Lakes Daily News' ) ) ),
				array( 'title' => 'Video', 'text' => 'Client testimonials, product showcases and brand stories, filmed on location and shared on our digital properties and social channels.', 'icon' => 'video' ),
				array( 'title' => 'Content for your own website', 'text' => 'On the Growing website plan we add a new page or seasonal update to your site every month and send an email to your customers every month.', 'icon' => 'web' ),
				array( 'title' => 'An ad in the daily email', 'text' => 'Pair your article with an ad in the Finger Lakes Daily News newsletter that sends readers to it.', 'icon' => 'email', 'logos' => array( array( 'file' => 'fldn-icon.png', 'alt' => 'FLDN' ) ) ),
			),
			'extra' => array(
				array( 'heading' => 'How sponsored content is handled', 'items' => array( 'Every article carries a Sponsored Content label, a byline reading Sponsored by your business name, and the line: This content is sponsored by your business. FLDN editorial staff were not involved in its creation.', 'It is written as information from you, not as news coverage of your business.', 'It sits in its own Sponsored Content section and is kept out of the daily news email and the site\'s RSS feed.', 'Search engines are asked not to index it, so it will not raise your Google ranking. Readers reach it through the links and promotion we agree on with you, such as a newsletter ad.', 'The facts come from you. We do not add claims about your products that you did not give us.' ) ),
				array( 'heading' => 'What we will not do', 'items' => array( 'Present an ad as news', 'Hide that content is paid', 'Promise a Google ranking' ) ),
			),
			'testimonial' => array( 'person' => 'Jeff Leavens', 'business' => 'E & V Energy', 'quote' => 'Our biggest trouble is getting the word out that, hey, we also do heating and air conditioning on top of the fuel and propane.', 'page_url' => 'https://www.flxlocalmedia.com/testimonials/e-v-energy/', 'video_seconds' => 91, 'thumb' => 'testimonial.jpg' ),
		),
		// A complete worked example. Use it as the pattern; it is not a live page.
		'example' => array(
			'name'        => 'Example Product',
			'eyebrow'     => 'Advertising · Example',
			'title'       => 'One plain promise for the buyer',
			'tagline'     => 'Short line for the sticker',
			'lead'        => 'One or two plain sentences that say what the product is and who it is for. No hype words and no metaphors.',
			'cta_label'   => '',
			'colors'      => array(
				'primary' => '#003b5b',
				'deep'    => '#002a42',
				'accent'  => '#e3a72f',
			),
			'logo'        => '',
			'stats'       => array(
				array(
					'value'  => '7',
					'label'  => 'Radio stations',
					'source' => 'FLX Local Media station list',
				),
				array(
					'value'  => '3',
					'label'  => 'Offices: Geneva, Auburn, Penn Yan',
					'source' => 'FLX Local Media contact pages',
				),
			),
			'stats_note'  => 'Every number on this page appears once, with its source.',
			'who'         => array(
				'heading'    => 'The people this product puts you in front of',
				'paragraphs' => array(
					'First paragraph about the audience, in full sentences.',
					'Second paragraph, optional.',
				),
			),
			'offers'      => array(
				array(
					'title' => 'First way to buy',
					'text'  => 'One or two sentences on what the advertiser gets.',
					'icon'  => 'spots',
				),
				array(
					'title' => 'Second way to buy',
					'text'  => 'One or two sentences on what the advertiser gets.',
					'icon'  => 'email',
				),
				array(
					'title' => 'Third way to buy',
					'text'  => 'One or two sentences on what the advertiser gets.',
					'icon'  => 'video',
				),
			),
			'extra'       => array(
				array(
					'heading' => 'What is included',
					'items'   => array( 'First included item', 'Second included item', 'Third included item' ),
				),
			),
			'testimonial' => array(
				'person'        => 'Advertiser name',
				'business'      => 'Business name',
				'quote'         => 'Replace this with a real quote from an advertiser.',
				'page_url'      => '',
				'video_seconds' => 0,
				'thumb'         => '',
			),
		),
	);

	return apply_filters( 'flxlm_products', $products );
}

/**
 * Newsletter subscriber count, read from the newsletter product's first stat
 * (the one place the number is kept).
 *
 * @return int
 */
function flxlm_newsletter_subscribers() {
	$prod = flxlm_get_product( 'newsletter' );
	return $prod && ! empty( $prod['stats'][0]['value'] ) ? (int) preg_replace( '/\D/', '', $prod['stats'][0]['value'] ) : 0;
}

/**
 * One product, normalised and fully defaulted for the template, or null when
 * the slug is unknown. Missing data becomes '' or array(); the template skips
 * every section that has nothing in it.
 *
 * Returns: slug, name, eyebrow, title, tagline, lead, cta_label, cta_url,
 * colors{primary,deep,accent}, css_vars, logo (URL or ''), stats[{value,label,source}],
 * stats_note, who{heading,paragraphs[]}, offers[{title,text,icon}],
 * extra[{heading,items[]}], testimonial{person,business,quote,page_url,video_seconds,thumb}|null.
 *
 * @param string $slug Product slug.
 * @return array|null
 */
function flxlm_get_product( $slug ) {
	$slug = is_string( $slug ) ? sanitize_title( $slug ) : '';
	$all  = flxlm_get_products();
	if ( '' === $slug || ! isset( $all[ $slug ] ) || ! is_array( $all[ $slug ] ) ) {
		return null;
	}
	$in = $all[ $slug ];

	$str = function ( $arr, $key, $default = '' ) {
		return is_array( $arr ) && isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ? trim( (string) $arr[ $key ] ) : $default;
	};
	$list = function ( $arr, $key ) {
		$out = array();
		if ( is_array( $arr ) && isset( $arr[ $key ] ) && is_array( $arr[ $key ] ) ) {
			foreach ( $arr[ $key ] as $v ) {
				if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
					$out[] = trim( (string) $v );
				}
			}
		}
		return $out;
	};

	$name = $str( $in, 'name' );
	if ( '' === $name ) {
		return null;
	}

	$colors = flxlm_page_css_vars( isset( $in['colors'] ) ? $in['colors'] : array() );

	$stats = array();
	if ( isset( $in['stats'] ) && is_array( $in['stats'] ) ) {
		foreach ( $in['stats'] as $s ) {
			if ( is_array( $s ) && '' !== $str( $s, 'value' ) ) {
				$stats[] = array(
					'value'  => $str( $s, 'value' ),
					'label'  => $str( $s, 'label' ),
					'source' => $str( $s, 'source' ),
				);
			}
		}
	}

	$who_in = isset( $in['who'] ) && is_array( $in['who'] ) ? $in['who'] : array();
	$who    = array(
		'heading'    => $str( $who_in, 'heading' ),
		'paragraphs' => $list( $who_in, 'paragraphs' ),
	);

	$offers = array();
	if ( isset( $in['offers'] ) && is_array( $in['offers'] ) ) {
		foreach ( $in['offers'] as $o ) {
			if ( is_array( $o ) && '' !== $str( $o, 'title' ) ) {
				$logos = array();
				if ( isset( $o['logos'] ) && is_array( $o['logos'] ) ) {
					foreach ( $o['logos'] as $lg ) {
						if ( ! is_array( $lg ) ) {
							continue;
						}
						if ( '' !== $str( $lg, 'file' ) ) {
							$logos[] = array(
								'file' => basename( $str( $lg, 'file' ) ),
								'alt'  => $str( $lg, 'alt' ),
								'chip' => ! empty( $lg['chip'] ),
							);
						} elseif ( '' !== $str( $lg, 'text' ) ) {
							$logos[] = array( 'text' => $str( $lg, 'text' ) );
						}
					}
				}
				$offers[] = array(
					'title' => $str( $o, 'title' ),
					'text'  => $str( $o, 'text' ),
					'icon'  => $str( $o, 'icon' ),
					'logos' => $logos,
				);
			}
		}
	}

	$hero_logos = array();
	if ( isset( $in['hero_logos'] ) && is_array( $in['hero_logos'] ) ) {
		foreach ( $in['hero_logos'] as $lg ) {
			if ( is_array( $lg ) && '' !== $str( $lg, 'file' ) ) {
				$hero_logos[] = array(
					'file'  => basename( $str( $lg, 'file' ) ),
					'alt'   => $str( $lg, 'alt' ),
					'group' => $str( $lg, 'group' ),
					'w'     => isset( $lg['w'] ) ? (float) $lg['w'] : 0.0,
					'h'     => isset( $lg['h'] ) ? (float) $lg['h'] : 0.0,
					'fit'   => $str( $lg, 'fit' ),
				);
			}
		}
	}

	$extra = array();
	if ( isset( $in['extra'] ) && is_array( $in['extra'] ) ) {
		foreach ( $in['extra'] as $e ) {
			if ( is_array( $e ) && '' !== $str( $e, 'heading' ) && $list( $e, 'items' ) ) {
				$extra[] = array(
					'heading' => $str( $e, 'heading' ),
					'items'   => $list( $e, 'items' ),
				);
			}
		}
	}

	$testimonial = null;
	if ( isset( $in['testimonial'] ) && is_array( $in['testimonial'] ) && '' !== $str( $in['testimonial'], 'quote' ) ) {
		$t           = $in['testimonial'];
		$page_url    = $str( $t, 'page_url' );
		$seconds     = isset( $t['video_seconds'] ) && is_numeric( $t['video_seconds'] ) && $t['video_seconds'] > 0 ? (int) $t['video_seconds'] : 0;
		$testimonial = array(
			'person'        => $str( $t, 'person' ),
			'business'      => $str( $t, 'business' ),
			'quote'         => $str( $t, 'quote' ),
			'page_url'      => preg_match( '#^https?://#i', $page_url ) ? esc_url_raw( $page_url ) : ( 0 === strpos( $page_url, '/' ) ? esc_url_raw( home_url( $page_url ) ) : '' ),
			'video_seconds' => $seconds,
			'thumb'         => flxlm_product_asset( $slug, $str( $t, 'thumb' ) ),
		);
	}

	return array(
		'slug'        => $slug,
		'name'        => $name,
		'eyebrow'     => $str( $in, 'eyebrow' ),
		'title'       => '' !== $str( $in, 'title' ) ? $str( $in, 'title' ) : $name,
		'tagline'     => $str( $in, 'tagline' ),
		'lead'        => $str( $in, 'lead' ),
		'cta_label'   => '' !== $str( $in, 'cta_label' ) ? $str( $in, 'cta_label' ) : 'Get a free plan for ' . $name,
		'cta_url'     => add_query_arg( 'product', $slug, home_url( '/contact/' ) ),
		'colors'      => array(
			'primary' => $colors['primary'],
			'deep'    => $colors['deep'],
			'accent'  => $colors['accent'],
		),
		'css_vars'    => $colors['css'],
		'logo'        => flxlm_product_asset( $slug, $str( $in, 'logo' ) ),
		'hero_mark'   => flxlm_product_asset( $slug, $str( $in, 'hero_mark' ) ),
		'hero_mark_style' => 'app' === $str( $in, 'hero_mark_style' ) ? 'app' : 'panel',
		'hero_logos'  => $hero_logos,
		'stats'       => $stats,
		'stats_note'  => $str( $in, 'stats_note' ),
		'who'         => $who,
		'offers'      => $offers,
		'extra'       => $extra,
		'testimonial' => $testimonial,
	);
}

/* ==========================================================================
   Primary navigation data
   ========================================================================== */

/**
 * The primary navigation groups, in menu order. header.php prints them.
 *
 * Group keys:
 *   key      => string  id fragment, also the CSS hook
 *   label    => string  text on the menu bar
 *   type     => 'stations' | 'links'
 *   url      => string  (stations only) where "All stations" goes
 *   feature  => [ title, text, label, url ]   (stations only) the side card
 *   items    => [ [ label, url | slug, blurb ], ... ]   (links only)
 *
 * An item's address is its 'url' (a path like '/solutions/events/' or a full
 * https URL). With only a 'slug' it becomes /solutions/<slug>/. The Stations
 * tiles are not listed here: they are read from flxlm_get_stations(), so the
 * station name, call letters and target audience always match the station pages.
 *
 * Fill in or change blurbs here. Filter 'flxlm_nav_groups' lets other code edit them.
 *
 * @return array
 */
function flxlm_nav_groups() {
	$groups = array(
		array(
			'key'       => 'stations',
			'label'     => 'Stations',
			'type'      => 'stations',
			'url'       => '/solutions/radio/',
			'all_label' => 'All stations',
			'all_blurb' => 'Compare coverage',
			'feature'   => array(
				'title' => 'Not sure which station?',
				'text'  => 'Tell us who you want to reach and we will build the mix.',
				'label' => 'Get a free plan',
				'url'   => '/contact/',
			),
		),
		array(
			'key'   => 'media',
			'label' => 'Media',
			'type'  => 'links',
			'items' => array(
				array( 'label' => 'Finger Lakes Daily News', 'slug' => 'fldn', 'blurb' => 'Local news and weather for the Finger Lakes' ),
				array( 'label' => 'FLDN newsletter', 'slug' => 'newsletter', 'blurb' => 'The Finger Lakes Daily News email' ),
				array( 'label' => 'Discover FLX', 'slug' => 'discover-flx', 'blurb' => 'Our magazine for the Finger Lakes' ),
				array( 'label' => 'FLX Finest', 'slug' => 'flx-finest', 'blurb' => 'Local awards with gold, silver and bronze medals' ),
				array( 'label' => 'FLX.fm app and streaming', 'slug' => 'flx-fm', 'blurb' => 'Our stations in an app and online' ),
			),
		),
		array(
			'key'   => 'advertising',
			'label' => 'Advertising',
			'type'  => 'links',
			'items' => array(
				array( 'label' => 'Sports', 'slug' => 'sports', 'blurb' => 'Local sports on air and online' ),
				array( 'label' => 'Shop Local', 'slug' => 'shop-local', 'blurb' => 'Campaigns that send neighbors to local businesses' ),
				array( 'label' => 'Contests and promotions', 'slug' => 'contests', 'blurb' => 'Give-aways that bring listeners to you' ),
				array( 'label' => 'Seasonal packages', 'slug' => 'seasonal', 'blurb' => 'Holiday and seasonal campaigns' ),
				array( 'label' => 'Events', 'url' => '/solutions/events/', 'blurb' => 'Live remotes and community events' ),
			),
		),
		array(
			'key'   => 'digital',
			'label' => 'Digital Services',
			'type'  => 'links',
			'items' => array(
				array( 'label' => 'Digital services overview', 'url' => '/solutions/digital/', 'blurb' => 'Websites, search, social and streaming' ),
				array( 'label' => 'Website Starter', 'slug' => 'website-starter', 'blurb' => 'A professional website for your business' ),
				array( 'label' => 'Listings', 'slug' => 'listings', 'blurb' => 'Accurate business listings across search and maps' ),
				array( 'label' => 'Content marketing', 'url' => '/solutions/content-marketing/', 'blurb' => 'Stories that bring customers in' ),
			),
		),
		array(
			'key'   => 'proof',
			'label' => 'Proof',
			'type'  => 'links',
			'items' => array(
				array( 'label' => 'Client videos', 'url' => '/testimonials/', 'blurb' => 'Local owners on camera' ),
				array( 'label' => 'Case studies', 'url' => '/case-study/', 'blurb' => 'The numbers behind the work' ),
				array( 'label' => 'Why radio works', 'url' => '/solutions/why-radio/', 'blurb' => 'The case for radio in the Finger Lakes' ),
				array( 'label' => 'How we count listeners', 'url' => '/methodology/', 'blurb' => 'Our method, in plain English' ),
			),
		),
	);

	return apply_filters( 'flxlm_nav_groups', $groups );
}

/**
 * Address for a nav item or group: a full https URL as given, a path through
 * home_url(), or /solutions/<slug>/ from a slug.
 *
 * @param array|string $item Item array (url or slug) or a path string.
 * @return string
 */
function flxlm_nav_url( $item ) {
	$url = '';
	if ( is_array( $item ) ) {
		if ( ! empty( $item['url'] ) ) {
			$url = (string) $item['url'];
		} elseif ( ! empty( $item['slug'] ) ) {
			$url = '/solutions/' . sanitize_title( (string) $item['slug'] ) . '/';
		}
	} else {
		$url = (string) $item;
	}
	if ( '' === $url ) {
		return '';
	}
	return preg_match( '#^https?://#i', $url ) ? esc_url_raw( $url ) : home_url( '/' . ltrim( $url, '/' ) );
}

/**
 * Is this nav address the page being viewed (or a parent of it)?
 *
 * @param string $url Absolute address from flxlm_nav_url().
 * @return bool
 */
function flxlm_nav_is_current( $url ) {
	if ( '' === $url || empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}
	$here = wp_parse_url( home_url( '/' ) . ltrim( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), '/' ), PHP_URL_PATH );
	$want = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! $here || ! $want || '/' === $want ) {
		return false;
	}
	return trailingslashit( $here ) === trailingslashit( $want );
}

/**
 * Small square brand icon for a menu link (Media group), from
 * assets/images/products/<slug>/icon.png, or '' when the product has none.
 *
 * @param string $slug Product slug.
 * @return string
 */
function flxlm_nav_item_icon( $slug ) {
	$slug = preg_replace( '/[^a-z0-9_-]/i', '', (string) $slug );
	if ( '' === $slug ) {
		return '';
	}
	$rel  = 'assets/images/products/' . $slug . '/icon.png';
	$path = get_template_directory() . '/' . $rel;
	if ( ! is_readable( $path ) ) {
		return '';
	}
	return add_query_arg( 'ver', (int) filemtime( $path ), get_template_directory_uri() . '/' . $rel );
}

/**
 * Logo URL for a station tile in the menu: the colour logo when the station
 * has one, the Wall's brick logo, or '' (the tile then shows the name only).
 *
 * @param string $slug Station slug.
 * @return string
 */
function flxlm_nav_station_logo( $slug ) {
	$slug = preg_replace( '/[^a-z0-9_-]/i', '', (string) $slug );
	foreach ( array( 'logo-color.svg', 'logo-brick.webp' ) as $file ) {
		$rel  = 'assets/images/stations/' . $slug . '/' . $file;
		$path = get_template_directory() . '/' . $rel;
		if ( is_readable( $path ) ) {
			return add_query_arg( 'ver', (int) filemtime( $path ), get_template_directory_uri() . '/' . $rel );
		}
	}
	return '';
}
