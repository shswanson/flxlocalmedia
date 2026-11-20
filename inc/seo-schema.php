<?php
/**
 * SEO JSON-LD (schema.org) for flxlocalmedia.com.
 *
 * One <script type="application/ld+json"> per page, holding an @graph:
 *
 *   Organization (always) + the three offices (home, About, Contact, offices)
 *   WebSite (always)
 *   WebPage or a subtype (CollectionPage, AboutPage, ContactPage), with its image and breadcrumb
 *   BreadcrumbList (every page but the home page)
 *   RadioStation + RadioBroadcastService   station pages
 *   Service                                product pages
 *   VideoObject                            testimonials
 *   Article                                case studies
 *   ProfessionalService                    the office pages
 *
 * No FAQPage: no page on this site has real questions and answers yet. Add one
 * through the 'flxlm_seo_graph' filter when a page does.
 *
 * Every value comes from the page's entry (inc/seo-content.php), which reads
 * the page's own data. Nothing here is invented.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Absolute @id for a node on a page.
 *
 * @param string $url      Page URL.
 * @param string $fragment Fragment without #.
 * @return string
 */
function flxlm_seo_id( $url, $fragment ) {
	return trailingslashit( strtok( $url, '#' ) ) . '#' . $fragment;
}

/**
 * The organization's public profiles. Only pages that list FLX Local Media
 * itself. FLX Local Media has no Facebook or LinkedIn page of its own yet, so
 * none is listed. Filter: 'flxlm_seo_same_as'.
 *
 * @return string[]
 */
function flxlm_seo_same_as() {
	return apply_filters( 'flxlm_seo_same_as', array(
		'https://www.google.com/maps/place/?q=place_id:ChIJM5WA39Xa0IkRrnC4mkSru98',
		'https://www.discoverflx.com/directory/flx-local-media/',
		'https://members.flxchamber.com/list/member/flx-local-media-526',
		'https://www.discoverseneca.com/seneca-chamber/directory/flx-local-media',
	) );
}

/**
 * A phone number as +1-315-781-7000.
 *
 * @param string $phone Phone as written on the site, e.g. (315) 781-7000.
 * @return string
 */
function flxlm_seo_e164( $phone ) {
	$d = preg_replace( '/\D/', '', (string) $phone );
	if ( 10 === strlen( $d ) ) {
		return '+1-' . substr( $d, 0, 3 ) . '-' . substr( $d, 3, 3 ) . '-' . substr( $d, 6 );
	}
	return '';
}

/**
 * "Ontario County, New York" nodes for a list of county names.
 *
 * @param string[] $counties Names like "Ontario".
 * @return array[]
 */
function flxlm_seo_counties_nodes( array $counties ) {
	$out = array();
	foreach ( $counties as $c ) {
		$c = trim( (string) $c );
		if ( '' === $c ) {
			continue;
		}
		$out[] = array(
			'@type'        => 'AdministrativeArea',
			'name'         => $c . ' County, New York',
		);
	}
	return $out;
}

/**
 * A PostalAddress node.
 *
 * @param array $studio Row from flxlm_get_studios().
 * @return array
 */
function flxlm_seo_address( array $studio ) {
	return array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => $studio['address'],
		'addressLocality' => $studio['city'],
		'addressRegion'   => $studio['state'],
		'postalCode'      => $studio['zip'],
		'addressCountry'  => 'US',
	);
}

/**
 * The organization node.
 *
 * @param bool $with_offices List the three offices as locations.
 * @return array
 */
function flxlm_seo_node_organization( $with_offices ) {
	$origin  = flxlm_seo_origin();
	$studios = flxlm_get_studios();
	$hq      = $studios[0];
	$icon    = get_theme_file_uri( 'assets/images/brand/apple-touch-icon.png' );

	$org = array(
		'@type'        => 'Organization',
		'@id'          => $origin . '/#organization',
		'name'         => 'FLX Local Media',
		// FLX Local Media is the d/b/a of TOTIB Media, Inc.; Apple Business verifies the legal entity against the site.
		'legalName'    => 'TOTIB Media, Inc.',
		'url'          => $origin . '/',
		'logo'         => array(
			'@type'  => 'ImageObject',
			'@id'    => $origin . '/#logo',
			'url'    => $icon,
			'width'  => 180,
			'height' => 180,
		),
		'image'        => flxlm_seo_og_url( 'home' ),
		'description'  => 'FLX Local Media runs seven radio stations and Finger Lakes Daily News, and sells radio, digital, event and content marketing to businesses across the Finger Lakes.',
		'telephone'    => flxlm_seo_e164( $hq['phone'] ),
		'address'      => flxlm_seo_address( $hq ),
		'areaServed'   => flxlm_seo_counties_nodes( flxlm_seo_counties() ),
		'contactPoint' => array(
			'@type'             => 'ContactPoint',
			'telephone'         => flxlm_seo_e164( $hq['phone'] ),
			'contactType'       => 'sales',
			'areaServed'        => 'US',
			'availableLanguage' => 'English',
		),
		'sameAs'       => flxlm_seo_same_as(),
		// Our own news brand is a brand, not the same entity.
		'brand'        => array(
			'@type' => 'Brand',
			'name'  => 'Finger Lakes Daily News',
			'url'   => 'https://www.fingerlakesdailynews.com',
		),
	);

	if ( $with_offices ) {
		$org['location'] = array();
		foreach ( $studios as $s ) {
			$org['location'][] = array( '@id' => flxlm_seo_office_id( $s ) );
		}
	}
	return $org;
}

/**
 * @id of an office node.
 *
 * @param array $studio Row from flxlm_get_studios().
 * @return string
 */
function flxlm_seo_office_id( array $studio ) {
	return flxlm_seo_origin() . '/contact/#' . sanitize_title( $studio['name'] );
}

/**
 * The office nodes (ProfessionalService, a LocalBusiness type).
 *
 * @return array[]
 */
function flxlm_seo_node_offices() {
	$out = array();
	foreach ( flxlm_get_studios() as $s ) {
		$q     = rawurlencode( $s['address'] . ', ' . $s['city'] . ', ' . $s['state'] . ' ' . $s['zip'] );
		$out[] = array(
			'@type'              => 'ProfessionalService',
			'@id'                => flxlm_seo_office_id( $s ),
			'name'               => 'FLX Local Media, ' . $s['name'] . ' office',
			'url'                => flxlm_seo_origin() . '/contact/',
			'telephone'          => flxlm_seo_e164( $s['phone'] ),
			'address'            => flxlm_seo_address( $s ),
			'hasMap'             => 'https://www.google.com/maps/search/?api=1&query=' . $q,
			'image'              => flxlm_seo_og_url( sanitize_title( $s['name'] ) ),
			'areaServed'         => flxlm_seo_counties_nodes( flxlm_seo_counties() ),
			'parentOrganization' => array( '@id' => flxlm_seo_origin() . '/#organization' ),
		);
	}
	return $out;
}

/**
 * The WebSite node.
 *
 * @return array
 */
function flxlm_seo_node_website() {
	$origin = flxlm_seo_origin();
	return array(
		'@type'      => 'WebSite',
		'@id'        => $origin . '/#website',
		'name'       => 'FLX Local Media',
		'url'        => $origin . '/',
		'inLanguage' => 'en-US',
		'publisher'  => array( '@id' => $origin . '/#organization' ),
	);
}

/**
 * BreadcrumbList from an entry's trail.
 *
 * @param array $entry Entry.
 * @return array|null
 */
function flxlm_seo_node_breadcrumbs( array $entry ) {
	if ( empty( $entry['crumbs'] ) ) {
		return null;
	}
	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => 'Home',
			'item'     => flxlm_seo_origin() . '/',
		),
	);
	$pos = 2;
	foreach ( $entry['crumbs'] as $c ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => $c[0],
			'item'     => $c[1],
		);
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => flxlm_seo_id( $entry['canonical'], 'breadcrumb' ),
		'itemListElement' => $items,
	);
}

/**
 * ISO 8601 with offset from a GMT date string.
 *
 * @param string $gmt Date in GMT (Y-m-d H:i:s).
 * @return string
 */
function flxlm_seo_iso( $gmt ) {
	if ( ! $gmt || '0000-00-00 00:00:00' === $gmt ) {
		return '';
	}
	$ts = strtotime( $gmt . ' UTC' );
	return $ts ? gmdate( 'c', $ts ) : '';
}

/**
 * ISO 8601 duration (PT1M31S) from seconds.
 *
 * @param int $seconds Seconds.
 * @return string
 */
function flxlm_seo_duration( $seconds ) {
	$seconds = (int) $seconds;
	if ( $seconds <= 0 ) {
		return '';
	}
	$h = (int) floor( $seconds / 3600 );
	$m = (int) floor( ( $seconds % 3600 ) / 60 );
	$s = $seconds % 60;
	return 'PT' . ( $h ? $h . 'H' : '' ) . ( $m ? $m . 'M' : '' ) . ( $s || ( ! $h && ! $m ) ? $s . 'S' : '' );
}

/**
 * Frequencies as BroadcastFrequencySpecification nodes, FM only.
 *
 * @param string $main Main frequency, e.g. "96.1 FM".
 * @param string $also Other frequencies, e.g. "Also on 95.9 · 96.9 · 101.9 FM".
 * @return array[]
 */
function flxlm_seo_frequencies( $main, $also ) {
	$vals = array();
	if ( preg_match_all( '/\d{2,3}\.\d/', (string) $main, $m ) ) {
		$vals = array_merge( $vals, $m[0] );
	}
	if ( preg_match_all( '/\d{2,3}\.\d/', (string) $also, $m ) ) {
		$vals = array_merge( $vals, $m[0] );
	}
	$out = array();
	foreach ( array_unique( $vals ) as $v ) {
		$out[] = array(
			'@type'                     => 'BroadcastFrequencySpecification',
			'broadcastFrequencyValue'   => $v,
			'broadcastSignalModulation' => 'FM',
		);
	}
	return $out;
}

/**
 * The share image as an ImageObject.
 *
 * @param array $entry Entry.
 * @return array
 */
function flxlm_seo_node_image( array $entry ) {
	return array(
		'@type'      => 'ImageObject',
		'@id'        => flxlm_seo_id( $entry['canonical'], 'primaryimage' ),
		'url'        => flxlm_seo_og_url( $entry['key'] ),
		'width'      => 1200,
		'height'     => 630,
		'caption'    => $entry['og_title'],
	);
}

/**
 * The main node(s) a page adds beyond WebPage.
 *
 * @param array $entry Entry.
 * @return array[] Nodes; the first is the page's mainEntity.
 */
function flxlm_seo_nodes_for_entry( array $entry ) {
	$origin = flxlm_seo_origin();
	$org    = array( '@id' => $origin . '/#organization' );
	$url    = $entry['url'];
	$d      = $entry['data'];
	$nodes  = array();

	switch ( $entry['kind'] ) {
		case 'station':
			$station = array(
				'@type'                 => array( 'RadioStation', 'RadioBroadcastService' ),
				'@id'                   => flxlm_seo_id( $url, 'station' ),
				'name'                  => $d['name'],
				'broadcastDisplayName'  => $d['name'],
				'callSign'              => $d['call'],
				'url'                   => $url,
				'description'           => $entry['description'],
				'broadcastFrequency'    => flxlm_seo_frequencies( $d['freq'], $d['also'] ),
				'areaServed'            => flxlm_seo_counties_nodes( $d['counties'] ),
				'inLanguage'            => 'en-US',
				'image'                 => flxlm_seo_og_url( $entry['key'] ),
				'parentOrganization'    => $org,
				'broadcaster'           => $org,
			);
			if ( ! empty( $d['tagline'] ) ) {
				$station['slogan'] = $d['tagline'];
			}
			if ( ! empty( $d['place'] ) && preg_match( '/^(.+), (NY)$/', $d['place'], $m ) ) {
				$station['address'] = array(
					'@type'           => 'PostalAddress',
					'addressLocality' => $m[1],
					'addressRegion'   => $m[2],
					'addressCountry'  => 'US',
				);
			}
			if ( empty( $station['broadcastFrequency'] ) ) {
				unset( $station['broadcastFrequency'] );
			}
			$nodes[] = $station;
			break;

		case 'product':
			$nodes[] = array(
				'@type'       => 'Service',
				'@id'         => flxlm_seo_id( $url, 'service' ),
				'name'        => $d['name'],
				'serviceType' => $d['group'] ? $d['group'] : 'Marketing',
				'description' => $entry['description'],
				'url'         => $url,
				'image'       => flxlm_seo_og_url( $entry['key'] ),
				'provider'    => $org,
				'areaServed'  => flxlm_seo_counties_nodes( flxlm_seo_counties() ),
			);
			break;

		case 'testimonial':
			$video = array(
				'@type'        => 'VideoObject',
				'@id'          => flxlm_seo_id( $url, 'video' ),
				'name'         => $d['business'] . ' client story',
				'description'  => $entry['description'],
				'inLanguage'   => 'en-US',
				'publisher'    => $org,
			);
			if ( ! empty( $d['poster'] ) ) {
				$video['thumbnailUrl'] = $d['poster'];
			} else {
				$video['thumbnailUrl'] = flxlm_seo_og_url( $entry['key'] );
			}
			if ( ! empty( $d['video'] ) ) {
				$video['contentUrl'] = $d['video'];
			}
			$iso = flxlm_seo_iso( $entry['published'] );
			if ( $iso ) {
				$video['uploadDate'] = $iso;
			}
			$dur = flxlm_seo_duration( $d['seconds'] );
			if ( $dur ) {
				$video['duration'] = $dur;
			}
			$nodes[] = $video;
			break;

		case 'case-study':
			$article = array(
				'@type'            => 'Article',
				'@id'              => flxlm_seo_id( $url, 'article' ),
				'headline'         => $d['headline'],
				'description'      => $entry['description'],
				'image'            => flxlm_seo_og_url( $entry['key'] ),
				'author'           => $org,
				'publisher'        => $org,
				'mainEntityOfPage' => array( '@id' => flxlm_seo_id( $url, 'webpage' ) ),
				'inLanguage'       => 'en-US',
			);
			$pub = flxlm_seo_iso( $entry['published'] );
			$mod = flxlm_seo_iso( $entry['modified'] );
			if ( $pub ) {
				$article['datePublished'] = $pub;
			}
			if ( $mod ) {
				$article['dateModified'] = $mod;
			}
			$nodes[] = $article;
			break;

		case 'office':
			foreach ( flxlm_seo_node_offices() as $node ) {
				if ( flxlm_seo_office_id( $d['studio'] ) === $node['@id'] ) {
					$nodes[] = array( '@id' => $node['@id'] );
					break;
				}
			}
			break;
	}

	return $nodes;
}

/**
 * The full graph for an entry.
 *
 * @param array $entry Entry.
 * @return array|null Graph document, or null for a page that stays out of search.
 */
function flxlm_seo_graph( array $entry ) {
	if ( ! empty( $entry['noindex'] ) ) {
		return null;
	}
	$origin = flxlm_seo_origin();
	$url    = $entry['url'];
	$kind   = $entry['kind'];

	$with_offices = in_array( $kind, array( 'home', 'office' ), true ) || ( 'company' === $kind );
	$graph        = array();
	$graph[]      = flxlm_seo_node_organization( $with_offices );
	if ( $with_offices ) {
		$graph = array_merge( $graph, flxlm_seo_node_offices() );
	}
	$graph[] = flxlm_seo_node_website();

	$page_type = 'WebPage';
	if ( 'hub' === $kind || 'testimonials' === $entry['key'] || 'case-study' === $entry['key'] ) {
		$page_type = 'CollectionPage';
	}
	if ( 'about' === $entry['key'] ) {
		$page_type = 'AboutPage';
	}
	if ( 'contact' === $entry['key'] || 'office' === $kind ) {
		$page_type = 'ContactPage';
	}

	$main  = flxlm_seo_nodes_for_entry( $entry );
	$crumb = 'home' === $kind ? null : flxlm_seo_node_breadcrumbs( $entry );

	$page = array(
		'@type'              => $page_type,
		'@id'                => flxlm_seo_id( $url, 'webpage' ),
		'url'                => $url,
		'name'               => $entry['title'],
		'description'        => $entry['description'],
		'inLanguage'         => 'en-US',
		'isPartOf'           => array( '@id' => $origin . '/#website' ),
		'about'              => array( '@id' => $main ? $main[0]['@id'] : $origin . '/#organization' ),
		'primaryImageOfPage' => array( '@id' => flxlm_seo_id( $entry['canonical'], 'primaryimage' ) ),
	);
	if ( $crumb ) {
		$page['breadcrumb'] = array( '@id' => $crumb['@id'] );
	}
	if ( $main ) {
		$page['mainEntity'] = array( '@id' => $main[0]['@id'] );
	}
	$pub = flxlm_seo_iso( $entry['published'] );
	$mod = flxlm_seo_iso( $entry['modified'] );
	if ( $pub ) {
		$page['datePublished'] = $pub;
	}
	if ( $mod ) {
		$page['dateModified'] = $mod;
	}

	$graph[] = $page;
	$graph[] = flxlm_seo_node_image( $entry );
	if ( $crumb ) {
		$graph[] = $crumb;
	}
	$graph = array_merge( $graph, array_filter( $main, function ( $n ) {
		// An office page points at an office node already in the graph.
		return count( $n ) > 1;
	} ) );

	return apply_filters( 'flxlm_seo_graph', array(
		'@context' => 'https://schema.org',
		'@graph'   => array_values( $graph ),
	), $entry );
}
