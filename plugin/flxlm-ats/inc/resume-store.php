<?php
/**
 * Where resume files actually live.
 *
 * In the database, in their own table, as bytes. Not on disk.
 *
 * WHY NOT THE FILESYSTEM, WHICH IS THE OBVIOUS ANSWER
 *
 * Three facts about this host, each verified rather than assumed, rule it out:
 *
 *   1. It runs nginx, which has no .htaccess mechanism at all. The standard
 *      WordPress trick of dropping a deny-all .htaccess into an uploads
 *      subfolder is not weak protection here, it is a no-op. Confirmed against
 *      the live site: any file under wp-content/uploads is served directly,
 *      unauthenticated, and Cloudflare caches it with max-age=315360000, which
 *      is ten years. Confirmed separately that a .php file in wp-content
 *      executes. So nothing inside the docroot can hold a resume.
 *
 *   2. The web server runs as www-data (uid 33). The docroot's parent,
 *      /home/claude, is drwxr-x--- owned by claude:claude, so www-data cannot
 *      even traverse into it, let alone write. There is therefore no directory
 *      outside the docroot that the process handling an upload can write to.
 *
 *   3. Making one would mean changing ownership or permissions on a home
 *      directory on managed hosting, which nobody would remember after the
 *      move off this platform, and which would fail silently the moment the
 *      host reset it.
 *
 * The database has none of those problems. It is not web-accessible, it needs
 * no filesystem permissions, it travels with the site in an ordinary dump, and
 * it behaves identically on whatever host this lands on next. A resume is a few
 * hundred kilobytes and the company posts a dozen jobs a year; this is not the
 * kind of data volume a database minds.
 *
 * The bytes are only ever handed back by flxlm_ats_serve_resume(), which checks
 * a capability first. There is no URL that maps to a row here.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema version, bumped when the table changes.
 */
const FLXLM_ATS_RESUME_DB_VERSION = 1;

/**
 * The resumes table name.
 *
 * @return string
 */
function flxlm_ats_resume_table() {
	global $wpdb;
	return $wpdb->prefix . 'flxlm_ats_resumes';
}

/**
 * Create or update the resumes table.
 *
 * Safe to call repeatedly; dbDelta only applies differences.
 */
function flxlm_ats_install_resume_table() {
	global $wpdb;

	$table   = flxlm_ats_resume_table();
	$collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// LONGBLOB rather than MEDIUMBLOB: MEDIUMBLOB tops out at 16MB, and while
	// the upload ceiling is below that today, a silently truncated resume is a
	// far worse failure than a slightly larger column type.
	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		application_id bigint(20) unsigned NOT NULL DEFAULT 0,
		file_name varchar(255) NOT NULL DEFAULT '',
		extension varchar(10) NOT NULL DEFAULT '',
		mime_type varchar(100) NOT NULL DEFAULT '',
		byte_size bigint(20) unsigned NOT NULL DEFAULT 0,
		content longblob NOT NULL,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY application_id (application_id)
	) {$collate};";

	dbDelta( $sql );

	update_option( 'flxlm_ats_resume_db_version', FLXLM_ATS_RESUME_DB_VERSION );
}

/**
 * Make sure the table exists before anything tries to use it.
 *
 * Runs on admin_init as well as activation, because a plugin deployed by rsync
 * and activated by wp-cli can miss an activation hook, and a missing table
 * would mean a rejected application rather than a stored one.
 */
function flxlm_ats_maybe_install_resume_table() {
	if ( (int) get_option( 'flxlm_ats_resume_db_version' ) === FLXLM_ATS_RESUME_DB_VERSION ) {
		return;
	}
	flxlm_ats_install_resume_table();
}
add_action( 'admin_init', 'flxlm_ats_maybe_install_resume_table' );

/**
 * Store validated resume bytes.
 *
 * The caller is responsible for having proved these bytes really are the type
 * they claim; see inc/storage.php. This function only stores.
 *
 * @param string $bytes     Raw file contents.
 * @param string $extension Validated extension, no dot.
 * @param string $file_name Original filename, for display only.
 * @return int|WP_Error Row id.
 */
function flxlm_ats_put_resume( $bytes, $extension, $file_name ) {
	global $wpdb;

	if ( '' === $bytes ) {
		return new WP_Error( 'flxlm_ats_empty_resume', 'That file is empty.' );
	}

	// Belt and braces: a request that got this far should already have the
	// table, but a missing table must be an explicit error rather than a silent
	// insert failure.
	flxlm_ats_maybe_install_resume_table();

	$mimes  = flxlm_ats_upload_mimes();
	$table  = flxlm_ats_resume_table();
	$result = $wpdb->insert(
		$table,
		array(
			'application_id' => 0,
			'file_name'      => $file_name,
			'extension'      => $extension,
			'mime_type'      => $mimes[ $extension ] ?? 'application/octet-stream',
			'byte_size'      => strlen( $bytes ),
			'content'        => $bytes,
			'created_at'     => gmdate( 'Y-m-d H:i:s' ),
		),
		array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
	);

	if ( false === $result ) {
		return new WP_Error(
			'flxlm_ats_resume_insert_failed',
			'The resume could not be saved: ' . $wpdb->last_error
		);
	}

	return (int) $wpdb->insert_id;
}

/**
 * Attach a stored resume to the application it belongs to.
 *
 * Done as a second step because the row is written while the file is being
 * validated, before the application post exists.
 *
 * @param int $resume_id      Row id.
 * @param int $application_id Application post ID.
 */
function flxlm_ats_link_resume( $resume_id, $application_id ) {
	global $wpdb;

	$wpdb->update(
		flxlm_ats_resume_table(),
		array( 'application_id' => (int) $application_id ),
		array( 'id' => (int) $resume_id ),
		array( '%d' ),
		array( '%d' )
	);
}

/**
 * Read a stored resume's metadata, without its bytes.
 *
 * Kept separate from the content so listing screens never pull megabytes of
 * file into memory to render a link.
 *
 * @param int $resume_id Row id.
 * @return array|null
 */
function flxlm_ats_get_resume_meta( $resume_id ) {
	global $wpdb;

	$table = flxlm_ats_resume_table();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
			"SELECT id, application_id, file_name, extension, mime_type, byte_size, created_at FROM {$table} WHERE id = %d",
			(int) $resume_id
		),
		ARRAY_A
	);

	return $row ? $row : null;
}

/**
 * Read a stored resume's bytes.
 *
 * @param int $resume_id Row id.
 * @return string|null
 */
function flxlm_ats_get_resume_bytes( $resume_id ) {
	global $wpdb;

	$table = flxlm_ats_resume_table();

	return $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
			"SELECT content FROM {$table} WHERE id = %d",
			(int) $resume_id
		)
	);
}
