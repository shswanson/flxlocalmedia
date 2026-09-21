<?php
/**
 * Resume storage.
 *
 * THE ORDER OF OPERATIONS IN THIS FILE IS THE SAFETY PROPERTY OF THE FEATURE.
 * It follows the same discipline as the newsroom's reader-photo pipeline, with
 * one important difference: a resume is a document, not an image, so the trick
 * that file uses to prove a file is real (wp_getimagesize succeeds) does not
 * transfer. The equivalent proof for a document is its magic bytes, checked
 * against the claimed extension, and that is what happens here.
 *
 * WHY RESUMES ARE NOT IN wp-content/uploads
 *
 * This host runs nginx, which has no .htaccess mechanism at all, so the usual
 * "drop a deny-all .htaccess in the folder" protection is not weak here, it is
 * inert. Verified against the live site: any file under wp-content/uploads is
 * served directly, unauthenticated, and Cloudflare caches it with
 * max-age=315360000 (ten years). Verified separately: a .php file dropped into
 * wp-content EXECUTES when requested over the web.
 *
 * Put those two facts together and the uploads directory is the worst possible
 * home for a file a stranger chose the contents and the name of. A resume holds
 * someone's full name, home address, phone number and work history. So resumes
 * are written outside the docroot, where no URL maps to them under any
 * webserver configuration, and served back only through
 * flxlm_ats_serve_resume() after a capability check.
 *
 * WHAT THIS FILE NEVER DOES
 *
 *   - It never trusts the filename. The stored name is generated here; the
 *     applicant's original name is kept only as a display label and is never
 *     used to build a path.
 *   - It never trusts the browser-supplied MIME type ($_FILES['type']), which
 *     is attacker-controlled.
 *   - It never creates an attachment post. An attachment is a public URL and a
 *     REST-exposed record; that is the opposite of what a resume needs.
 *   - It never writes a file it could not positively identify. Unrecognised
 *     content is rejected, not stored "just in case".
 *   - It never resolves a stored filename into a path without re-checking that
 *     the result is still inside the private directory, so a traversal
 *     sequence smuggled into a database value cannot read /etc/passwd.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * The ceiling this plugin imposes, in bytes.
 *
 * Eight megabytes is generous for a document and small enough that a flood of
 * them cannot fill the disk before the rate limiter notices. It is a CEILING,
 * not a promise: see flxlm_ats_max_upload_bytes(), which is what the form and
 * the validator actually use.
 */
const FLXLM_ATS_MAX_RESUME_BYTES = 8388608;

/**
 * The real largest upload this server will accept.
 *
 * PHP has the final say here and it is usually stricter than we are. This host
 * ships upload_max_filesize=2M and post_max_size=8M, so the plugin's own 8 MB
 * ceiling is irrelevant: a 3 MB resume, which is an ordinary PDF with a photo
 * or a letterhead in it, is refused by PHP before a single line of this plugin
 * runs.
 *
 * That matters more than it sounds. Telling an applicant "up to 8 MB" when the
 * server stops at 2 MB produces a failure the applicant cannot understand, on
 * the one form the company most needs people to complete. So the honest number
 * is computed and shown, rather than a constant being asserted.
 *
 * post_max_size covers the whole request, so a little headroom is left for the
 * other fields.
 *
 * @return int Bytes.
 */
function flxlm_ats_max_upload_bytes() {
	$limits = array( FLXLM_ATS_MAX_RESUME_BYTES );

	$upload = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
	if ( $upload > 0 ) {
		$limits[] = $upload;
	}

	$post = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
	if ( $post > 0 ) {
		// Leave room for the text fields travelling in the same request.
		$limits[] = max( 0, $post - 65536 );
	}

	/**
	 * Filter the effective maximum resume size.
	 *
	 * @param int $bytes Effective limit.
	 */
	return (int) apply_filters( 'flxlm_ats_max_upload_bytes', min( $limits ) );
}

/**
 * The effective limit, phrased for a human.
 *
 * @return string e.g. "2 MB".
 */
function flxlm_ats_max_upload_label() {
	return size_format( flxlm_ats_max_upload_bytes() );
}

/**
 * Whether PHP threw this request away before it reached us.
 *
 * When a POST exceeds post_max_size, PHP does not hand the script a truncated
 * request or raise an upload error. It discards $_POST and $_FILES entirely and
 * carries on, so the handler sees what looks like an empty submission with no
 * indication anything was wrong. Left unhandled that is an applicant who filled
 * in the form, pressed submit, and was bounced back to a blank page with no
 * explanation and no record kept.
 *
 * The signature is a request that declared a body and arrived with nothing in it.
 *
 * @return bool
 */
function flxlm_ats_post_was_discarded() {
	if ( ! empty( $_POST ) || ! empty( $_FILES ) ) {
		return false;
	}

	$declared = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

	return $declared > 0;
}

/**
 * Accepted document types.
 *
 * Each entry maps an extension to the MIME types finfo may legitimately report
 * for it and the byte signatures the file must start with. An empty signature
 * list means the format has no magic number and needs a content check instead
 * (see flxlm_ats_looks_like_plain_text).
 *
 * @return array<string,array>
 */
function flxlm_ats_allowed_resume_types() {
	return array(
		'pdf'  => array(
			'mimes'      => array( 'application/pdf' ),
			'signatures' => array( "%PDF-" ),
		),
		'docx' => array(
			// A .docx is a zip. So is every other OOXML file.
			'mimes'      => array(
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/zip',
			),
			'signatures' => array( "PK\x03\x04", "PK\x05\x06" ),
		),
		'doc'  => array(
			// Legacy Word is an OLE2 compound document.
			'mimes'      => array( 'application/msword', 'application/vnd.ms-office', 'application/x-ole-storage' ),
			'signatures' => array( "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ),
		),
		'odt'  => array(
			'mimes'      => array( 'application/vnd.oasis.opendocument.text', 'application/zip' ),
			'signatures' => array( "PK\x03\x04" ),
		),
		'rtf'  => array(
			'mimes'      => array( 'application/rtf', 'text/rtf' ),
			'signatures' => array( '{\\rtf' ),
		),
		'txt'  => array(
			'mimes'      => array( 'text/plain' ),
			'signatures' => array(), // No magic number; content-checked below.
		),
	);
}

/**
 * The directory resumes are written to, proven to be outside the docroot.
 *
 * Returns a WP_Error rather than a path when the configured directory is inside
 * the web root. That is a fail-closed check with a specific migration in mind:
 * when this site moves off WPMU DEV hosting, someone will set
 * FLXLM_ATS_PRIVATE_DIR on the new box, and if they point it somewhere servable
 * every resume the company holds becomes public at once. Better that uploads
 * stop working loudly than that they keep working dangerously.
 *
 * @return string|WP_Error Absolute path with no trailing slash.
 */
function flxlm_ats_private_dir() {
	$dir = rtrim( (string) FLXLM_ATS_PRIVATE_DIR, '/' );

	if ( '' === $dir ) {
		return new WP_Error( 'flxlm_ats_no_private_dir', 'No private resume directory is configured.' );
	}

	$docroot = rtrim( (string) ABSPATH, '/' );

	// Compare resolved paths where possible so a symlink cannot hide the fact
	// that the "private" directory is actually inside the docroot.
	$real_dir     = realpath( $dir );
	$real_docroot = realpath( $docroot );
	$check_dir    = $real_dir ? $real_dir : $dir;
	$check_root   = $real_docroot ? $real_docroot : $docroot;

	if ( 0 === strpos( $check_dir . '/', $check_root . '/' ) ) {
		return new WP_Error(
			'flxlm_ats_private_dir_public',
			'Refusing to store resumes at ' . $dir . ' because it is inside the web root (' . $check_root . '). '
				. 'Set FLXLM_ATS_PRIVATE_DIR in wp-config.php to a directory outside the docroot.'
		);
	}

	return $dir;
}

/**
 * Create the private directory if it is missing.
 *
 * @return true|WP_Error
 */
function flxlm_ats_prepare_private_dir() {
	$dir = flxlm_ats_private_dir();
	if ( is_wp_error( $dir ) ) {
		return $dir;
	}

	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'flxlm_ats_mkdir_failed', 'Could not create the resume directory at ' . $dir );
	}

	// 0700: only the user PHP runs as. Nothing else on the box needs to read it.
	@chmod( $dir, 0700 );

	if ( ! is_writable( $dir ) ) {
		return new WP_Error( 'flxlm_ats_dir_not_writable', 'The resume directory at ' . $dir . ' is not writable.' );
	}

	return true;
}

/**
 * Whether a buffer looks like plain text a human typed.
 *
 * Used only for .txt, which has no magic number. A NUL byte means the file is
 * binary wearing a .txt extension. The PHP-open-tag check is belt and braces:
 * the file is stored outside the docroot and can never be executed, but a
 * stored web shell is still not something to keep on disk.
 *
 * @param string $head First bytes of the file.
 * @return bool
 */
function flxlm_ats_looks_like_plain_text( $head ) {
	if ( false !== strpos( $head, "\0" ) ) {
		return false;
	}
	if ( false !== stripos( $head, '<?php' ) || false !== stripos( $head, '<?=' ) ) {
		return false;
	}
	return true;
}

/**
 * Validate and store an uploaded resume.
 *
 * Deliberately does NOT use wp_handle_upload(): that function's whole job is to
 * place a file in the public uploads tree and it has no notion of a private
 * destination. move_uploaded_file() is used directly, which also guarantees the
 * source really was an HTTP upload and not an arbitrary server path.
 *
 * @param array $file One entry from $_FILES.
 * @return array|WP_Error {stored_name, original_name, bytes} on success.
 */
function flxlm_ats_store_resume( $file ) {
	// ---- Checks that cost nothing, before the file is opened by anything. ----

	if ( ! is_array( $file ) || ! isset( $file['error'] ) ) {
		return new WP_Error( 'flxlm_ats_no_file', 'No file was received.' );
	}

	if ( UPLOAD_ERR_NO_FILE === $file['error'] ) {
		return new WP_Error( 'flxlm_ats_no_file', 'No file was received.' );
	}

	if ( UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] ) {
		return new WP_Error(
			'flxlm_ats_too_big',
			'That file is too large. Please keep your resume under ' . flxlm_ats_max_upload_label() . '.'
		);
	}

	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		return new WP_Error( 'flxlm_ats_upload_failed', 'The upload did not complete. Please try again.' );
	}

	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'flxlm_ats_not_an_upload', 'That file could not be read.' );
	}

	$size = (int) @filesize( $file['tmp_name'] );
	if ( $size <= 0 ) {
		return new WP_Error( 'flxlm_ats_empty_file', 'That file is empty.' );
	}
	if ( $size > flxlm_ats_max_upload_bytes() ) {
		return new WP_Error(
			'flxlm_ats_too_big',
			'That file is too large. Please keep your resume under ' . flxlm_ats_max_upload_label() . '.'
		);
	}

	// ---- Identify the file. Extension first, then prove it. ----

	$original = (string) ( isset( $file['name'] ) ? $file['name'] : 'resume' );
	$ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
	$allowed  = flxlm_ats_allowed_resume_types();

	if ( ! isset( $allowed[ $ext ] ) ) {
		return new WP_Error(
			'flxlm_ats_bad_type',
			'Please upload a PDF or Word document. We accept PDF, DOC, DOCX, ODT, RTF and TXT.'
		);
	}

	// WordPress's own extension/MIME cross-check. This is what catches a
	// "resume.pdf.php" double extension, because it reads the REAL extension.
	$checked = wp_check_filetype_and_ext( $file['tmp_name'], $original, flxlm_ats_upload_mimes() );
	if ( empty( $checked['ext'] ) || strtolower( $checked['ext'] ) !== $ext ) {
		return new WP_Error(
			'flxlm_ats_bad_type',
			'That file type is not accepted. Please upload a PDF or Word document.'
		);
	}

	// finfo on the actual bytes. The browser-supplied $file['type'] is ignored
	// entirely; it is whatever the client felt like claiming.
	if ( function_exists( 'finfo_open' ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( $finfo ) {
			$real_mime = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );

			if ( $real_mime && ! in_array( $real_mime, $allowed[ $ext ]['mimes'], true ) ) {
				// text/plain is reported for RTF and for some plain resumes; allow
				// it only where the signature check below can still vouch for it.
				$lenient = ( 'text/plain' === $real_mime && in_array( $ext, array( 'rtf', 'txt' ), true ) );
				if ( ! $lenient ) {
					return new WP_Error(
						'flxlm_ats_content_mismatch',
						'That file does not look like a ' . strtoupper( $ext ) . '. Please re-save it and try again.'
					);
				}
			}
		}
	}

	// Magic bytes. An extension is a filename; this is the file.
	$handle = @fopen( $file['tmp_name'], 'rb' );
	if ( ! $handle ) {
		return new WP_Error( 'flxlm_ats_unreadable', 'That file could not be read.' );
	}
	$head = (string) fread( $handle, 16 );
	fclose( $handle );

	$signatures = $allowed[ $ext ]['signatures'];
	if ( $signatures ) {
		$matched = false;
		foreach ( $signatures as $signature ) {
			if ( 0 === strncmp( $head, $signature, strlen( $signature ) ) ) {
				$matched = true;
				break;
			}
		}
		if ( ! $matched ) {
			return new WP_Error(
				'flxlm_ats_content_mismatch',
				'That file does not look like a ' . strtoupper( $ext ) . '. Please re-save it and try again.'
			);
		}
	} elseif ( ! flxlm_ats_looks_like_plain_text( $head ) ) {
		return new WP_Error( 'flxlm_ats_content_mismatch', 'That file does not look like a text document.' );
	}

	// ---- Only now is there a destination. ----

	$dir = flxlm_ats_private_dir();
	if ( is_wp_error( $dir ) ) {
		return $dir;
	}

	$prepared = flxlm_ats_prepare_private_dir();
	if ( is_wp_error( $prepared ) ) {
		return $prepared;
	}

	// The stored name is ours, not theirs. wp_generate_password with special
	// characters off gives 32 alphanumerics from a CSPRNG.
	$stored = gmdate( 'Y/m' ) . '/' . wp_generate_password( 32, false, false ) . '.' . $ext;
	$target = $dir . '/' . $stored;

	if ( ! wp_mkdir_p( dirname( $target ) ) ) {
		return new WP_Error( 'flxlm_ats_mkdir_failed', 'Could not prepare storage for that file.' );
	}

	if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
		return new WP_Error( 'flxlm_ats_move_failed', 'That file could not be saved. Please try again.' );
	}

	// 0600: readable only by the user PHP runs as.
	@chmod( $target, 0600 );

	return array(
		'stored_name'   => $stored,
		'original_name' => sanitize_file_name( $original ),
		'bytes'         => $size,
	);
}

/**
 * The MIME map handed to wp_check_filetype_and_ext().
 *
 * Passed explicitly rather than relying on the site's global upload_mimes,
 * so this check cannot be widened by an unrelated plugin filtering that list.
 *
 * @return array<string,string>
 */
function flxlm_ats_upload_mimes() {
	return array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'odt'  => 'application/vnd.oasis.opendocument.text',
		'rtf'  => 'application/rtf',
		'txt'  => 'text/plain',
	);
}

/**
 * Turn a stored filename back into an absolute path, safely.
 *
 * The containment re-check is the point of this function. A stored name comes
 * out of the database, and a database value is not automatically trustworthy:
 * if anything ever wrote "../../../../etc/passwd" into that column, naive
 * concatenation would happily read it. Resolving the path and confirming it is
 * still under the private directory makes that impossible.
 *
 * @param string $stored_name Value of _flxlm_resume_file.
 * @return string|WP_Error
 */
function flxlm_ats_resume_path( $stored_name ) {
	$stored_name = (string) $stored_name;
	if ( '' === $stored_name ) {
		return new WP_Error( 'flxlm_ats_no_resume', 'No resume on file.' );
	}

	$dir = flxlm_ats_private_dir();
	if ( is_wp_error( $dir ) ) {
		return $dir;
	}

	$candidate = $dir . '/' . ltrim( $stored_name, '/' );
	$real      = realpath( $candidate );
	$real_dir  = realpath( $dir );

	if ( ! $real || ! $real_dir || 0 !== strpos( $real, $real_dir . '/' ) ) {
		return new WP_Error( 'flxlm_ats_resume_missing', 'That resume file is not available.' );
	}

	if ( ! is_file( $real ) || ! is_readable( $real ) ) {
		return new WP_Error( 'flxlm_ats_resume_missing', 'That resume file is not available.' );
	}

	return $real;
}

/**
 * Content type to send when handing a resume back to a browser.
 *
 * @param string $path File path.
 * @return string
 */
function flxlm_ats_resume_content_type( $path ) {
	$ext   = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	$mimes = flxlm_ats_upload_mimes();
	return isset( $mimes[ $ext ] ) ? $mimes[ $ext ] : 'application/octet-stream';
}

/**
 * Stream a resume to the current request and exit.
 *
 * The caller is responsible for authorising the request; this function only
 * moves bytes. Both callers (the admin download link and the signed hiring
 * manager link) check first.
 *
 * Content-Disposition is "inline" for PDFs so a manager can read the resume in
 * the browser without downloading it, and "attachment" for everything else,
 * because telling a browser to render an arbitrary document inline is how
 * stored-XSS happens. X-Content-Type-Options stops MIME sniffing either way.
 *
 * @param int $application_id Application ID.
 * @return void|WP_Error Exits on success.
 */
function flxlm_ats_serve_resume( $application_id ) {
	$stored = get_post_meta( (int) $application_id, '_flxlm_resume_file', true );
	$path   = flxlm_ats_resume_path( $stored );

	if ( is_wp_error( $path ) ) {
		return $path;
	}

	$type     = flxlm_ats_resume_content_type( $path );
	$original = (string) get_post_meta( (int) $application_id, '_flxlm_resume_name', true );
	$filename = $original ? $original : basename( $path );
	$inline   = ( 'application/pdf' === $type );

	nocache_headers();
	header( 'Content-Type: ' . $type );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	header( 'Referrer-Policy: no-referrer' );
	header(
		'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' )
			. '; filename="' . sanitize_file_name( $filename ) . '"'
	);

	readfile( $path );
	exit;
}

/**
 * Store a resume that arrived as base64 over the cross-site relay.
 *
 * The FLDN site cannot hand this site a $_FILES entry, so a relayed resume
 * arrives as base64 in the request body. It is written to a temp file and then
 * put through checks equivalent to the upload path: same size ceiling, same
 * extension allowlist, same magic-byte proof. Relayed input is not trusted more
 * than a stranger's upload, because it ultimately IS a stranger's upload.
 *
 * @param string $base64   Encoded file contents.
 * @param string $filename Claimed original filename.
 * @return array|WP_Error
 */
function flxlm_ats_store_relayed_resume( $base64, $filename ) {
	$raw = base64_decode( (string) $base64, true );
	if ( false === $raw || '' === $raw ) {
		return new WP_Error( 'flxlm_ats_bad_payload', 'The attached file could not be decoded.' );
	}

	// A relayed resume arrives inside a JSON body, not as a file upload, so
	// upload_max_filesize does not apply to it. The plugin ceiling is the bound.
	$size = strlen( $raw );
	if ( $size > FLXLM_ATS_MAX_RESUME_BYTES ) {
		return new WP_Error( 'flxlm_ats_too_big', 'That file is too large.' );
	}

	$ext     = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
	$allowed = flxlm_ats_allowed_resume_types();
	if ( ! isset( $allowed[ $ext ] ) ) {
		return new WP_Error( 'flxlm_ats_bad_type', 'That file type is not accepted.' );
	}

	$signatures = $allowed[ $ext ]['signatures'];
	if ( $signatures ) {
		$matched = false;
		foreach ( $signatures as $signature ) {
			if ( 0 === strncmp( $raw, $signature, strlen( $signature ) ) ) {
				$matched = true;
				break;
			}
		}
		if ( ! $matched ) {
			return new WP_Error( 'flxlm_ats_content_mismatch', 'That file does not match its extension.' );
		}
	} elseif ( ! flxlm_ats_looks_like_plain_text( substr( $raw, 0, 16 ) ) ) {
		return new WP_Error( 'flxlm_ats_content_mismatch', 'That file does not look like a text document.' );
	}

	$dir = flxlm_ats_private_dir();
	if ( is_wp_error( $dir ) ) {
		return $dir;
	}

	$prepared = flxlm_ats_prepare_private_dir();
	if ( is_wp_error( $prepared ) ) {
		return $prepared;
	}

	$stored = gmdate( 'Y/m' ) . '/' . wp_generate_password( 32, false, false ) . '.' . $ext;
	$target = $dir . '/' . $stored;

	if ( ! wp_mkdir_p( dirname( $target ) ) ) {
		return new WP_Error( 'flxlm_ats_mkdir_failed', 'Could not prepare storage for that file.' );
	}

	if ( false === file_put_contents( $target, $raw ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'flxlm_ats_move_failed', 'That file could not be saved.' );
	}

	@chmod( $target, 0600 );

	return array(
		'stored_name'   => $stored,
		'original_name' => sanitize_file_name( (string) $filename ),
		'bytes'         => $size,
	);
}
