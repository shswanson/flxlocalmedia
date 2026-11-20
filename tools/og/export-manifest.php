<?php
/**
 * Print the share-image manifest of flxlocalmedia.com as JSON.
 *
 * The FLX Local Media theme (shswanson/flxlocalmedia, inc/seo-content.php) holds
 * the copy, colors and logo files for every public page. This script loads
 * WordPress, asks the theme for that list and prints it, so generate.mjs never
 * has its own copy of any page's wording.
 *
 * Usage:
 *   php export-manifest.php /path/to/wordpress [host] > manifest.json
 *
 * host defaults to www.flxlocalmedia.com. It only has to be a host the site
 * answers to; the canonical URLs in the manifest are always the www host.
 *
 * Run it on a machine that has the site's database (a staging copy is fine).
 *
 * @package flxlm-og
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
if ( '' === $root || ! is_readable( $root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php export-manifest.php /path/to/wordpress [host]\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']   = isset( $argv[2] ) ? $argv[2] : 'www.flxlocalmedia.com';
$_SERVER['HTTPS']       = 'on';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];

define( 'WP_USE_THEMES', false );
require $root . '/wp-load.php';

if ( ! function_exists( 'flxlm_seo_og_manifest' ) ) {
	fwrite( STDERR, "The FLX Local Media theme is not the active theme on this site.\n" );
	exit( 1 );
}

echo wp_json_encode(
	array(
		'theme' => get_template_directory(),
		'rows'  => flxlm_seo_og_manifest(),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
echo "\n";
