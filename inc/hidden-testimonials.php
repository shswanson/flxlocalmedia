<?php
/**
 * Hidden ("unlisted") testimonials.
 *
 * A testimonial flagged `is_hidden` stays reachable by its direct URL
 * (/testimonials/<slug>/) but is pulled OUT of every in-site listing, the
 * XML sitemap, and search indexing. Used to hand a client a working review
 * link before their story goes public. Un-tick the "Hidden" box in the
 * Testimonial editor to promote it to the live grid — no deploy needed.
 *
 * Mirrors the unlisted pattern in page-templates/template-pricing.php.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IDs of every testimonial flagged hidden. Cached per request.
 *
 * @return int[] Post IDs (empty array if none).
 */
function flxlm_hidden_testimonial_ids() {
	static $ids = null;

	if ( null !== $ids ) {
		return $ids;
	}

	$ids = get_posts( array(
		'post_type'        => 'flxlm_testimonial',
		'post_status'      => 'any',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'meta_key'         => 'is_hidden',
		'meta_value'       => '1',
		'no_found_rows'    => true,
		'suppress_filters' => true,
	) );

	$ids = array_map( 'intval', (array) $ids );

	return $ids;
}

/**
 * Merge hidden IDs into a query's post__not_in.
 *
 * @param array $exclude Existing exclusions.
 * @return int[] Combined, de-duped exclusion list.
 */
function flxlm_exclude_hidden( $exclude = array() ) {
	return array_values( array_unique( array_merge(
		array_map( 'intval', (array) $exclude ),
		flxlm_hidden_testimonial_ids()
	) ) );
}

/**
 * Keep hidden testimonials out of the core XML sitemap.
 *
 * @param array  $args      WP_Query args for the sitemap.
 * @param string $post_type The post type being mapped.
 * @return array
 */
function flxlm_hidden_sitemap_exclude( $args, $post_type ) {
	if ( 'flxlm_testimonial' !== $post_type ) {
		return $args;
	}

	$hidden = flxlm_hidden_testimonial_ids();
	if ( empty( $hidden ) ) {
		return $args;
	}

	$args['post__not_in'] = isset( $args['post__not_in'] )
		? array_merge( (array) $args['post__not_in'], $hidden )
		: $hidden;

	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'flxlm_hidden_sitemap_exclude', 10, 2 );

/**
 * Emit noindex,nofollow on a hidden testimonial's single view.
 */
function flxlm_hidden_noindex() {
	if ( ! is_singular( 'flxlm_testimonial' ) ) {
		return;
	}

	if ( get_post_meta( get_queried_object_id(), 'is_hidden', true ) ) {
		echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
	}
}
add_action( 'wp_head', 'flxlm_hidden_noindex', 1 );
