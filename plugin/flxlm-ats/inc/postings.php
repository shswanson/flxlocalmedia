<?php
/**
 * Job postings, managed from the hub.
 *
 * The flxlm_job post type itself is registered by the theme (inc/cpt-jobs.php).
 * This file is the plugin's side of it: the record the hub reads and writes,
 * the validation that decides whether a posting may go live, and the sync that
 * pushes a live posting to fingerlakesdailynews.com. hub-bridge.php only wires
 * routes to the functions here, so there is one definition of "a posting" and
 * not one per caller.
 *
 * WHY THIS LIVES IN THE PLUGIN AND NOT THE THEME
 *
 * The reason for this build is the hiring manager: the person who gets an email
 * for every application. That routing is ATS behaviour (inc/notify.php reads it)
 * and has to survive a theme swap the same way the applications do.
 *
 * WHAT IS PUBLIC AND WHAT IS NOT
 *
 *   job_hiring_manager  A WordPress user ID. INTERNAL ONLY. Never rendered,
 *                       never in structured data, never sent to FLDN.
 *   job_email           The PUBLIC contact only (search engines show it). A
 *                       shared inbox, not a person.
 *   job_canonical_url   Points this copy at the FLDN twin. Set on publish.
 *   job_sync_fldn       JSON record of the last FLDN sync attempt.
 *   job_closed_at       UTC 'Y-m-d H:i:s' when the posting was closed.
 *
 * STATUS
 *
 *   draft      post_status 'draft' (or 'pending'). Never synced.
 *   published  post_status 'publish'.
 *   closed     post_status 'private' plus job_closed_at. Private is never public.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** The public contact a new posting starts with. A shared inbox Dennie and Frank already read. */
const FLXLM_ATS_POSTING_DEFAULT_EMAIL = 'jobs@flxlocalmedia.com';

/** Where FLDN serves its copy of a posting. The slug is appended. */
const FLXLM_ATS_POSTING_FLDN_BASE = 'https://www.fingerlakesdailynews.com/careers/';

/** The pay units Google Jobs understands, which is also what the templates render. */
const FLXLM_ATS_POSTING_PAY_UNITS = array( 'HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR' );

/**
 * The FLDN receiver endpoint. Overridable in wp-config.php so a test box can
 * point at a staging receiver without a code change.
 *
 * @return string
 */
function flxlm_ats_posting_fldn_endpoint() {
	return defined( 'FLXLM_FLDN_POSTING_ENDPOINT' ) && '' !== (string) FLXLM_FLDN_POSTING_ENDPOINT
		? (string) FLXLM_FLDN_POSTING_ENDPOINT
		: 'https://www.fingerlakesdailynews.com/wp-json/fldn-postings/v1/posting';
}

/**
 * Load a posting, or null when the ID is not a flxlm_job.
 *
 * Every hub route runs a caller-supplied ID through this before touching any
 * meta, the same post-type guard the applicant routes apply.
 *
 * @param int $post_id Post ID.
 * @return WP_Post|null
 */
function flxlm_ats_posting_get( $post_id ) {
	$post = get_post( (int) $post_id );
	return ( $post && 'flxlm_job' === $post->post_type ) ? $post : null;
}

/**
 * The hub's word for a posting's post_status.
 *
 * @param WP_Post $post Posting.
 * @return string 'draft' | 'published' | 'closed'
 */
function flxlm_ats_posting_status( $post ) {
	if ( 'publish' === $post->post_status ) {
		return 'published';
	}
	if ( 'private' === $post->post_status ) {
		return 'closed';
	}
	return 'draft';
}

/**
 * Whether a job type is paid per piece rather than on a wage.
 *
 * The same needles the theme uses to decide benefits eligibility, minus
 * intern and temporary: an intern or a temp is still paid a wage, so still
 * owes the New York pay range.
 *
 * @param string $job_type Job type text.
 * @return bool
 */
function flxlm_ats_posting_is_freelance( $job_type ) {
	return (bool) preg_match( '/freelance|contract|per story|stringer/i', (string) $job_type );
}

/**
 * The hiring manager, as {id, name}, or null.
 *
 * @param int $post_id Posting ID.
 * @return array|null
 */
function flxlm_ats_posting_hiring_manager( $post_id ) {
	$user_id = (int) get_post_meta( $post_id, 'job_hiring_manager', true );
	if ( $user_id < 1 ) {
		return null;
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return null;
	}
	return array(
		'id'   => $user_id,
		'name' => $user->display_name,
	);
}

/**
 * Whether a user ID may be a hiring manager.
 *
 * Must hold flxlm_view_applications, because the notification email links
 * into the application, and a manager who cannot open it has been told about
 * a candidate they cannot see.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function flxlm_ats_posting_valid_manager( $user_id ) {
	$user_id = (int) $user_id;
	return $user_id > 0 && get_userdata( $user_id ) && user_can( $user_id, 'flxlm_view_applications' );
}

/**
 * Everyone who can be picked as a hiring manager, sorted by name.
 *
 * @return array[] { id, name, email }
 */
function flxlm_ats_posting_hiring_managers() {
	$users = get_users(
		array(
			'capability' => 'flxlm_view_applications',
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'fields'     => array( 'ID', 'display_name', 'user_email' ),
		)
	);

	$out = array();
	foreach ( $users as $user ) {
		// The capability query matches on the serialized caps meta, which also
		// matches a cap explicitly granted as false. Re-check the real answer.
		if ( ! user_can( (int) $user->ID, 'flxlm_view_applications' ) ) {
			continue;
		}
		$out[] = array(
			'id'    => (int) $user->ID,
			'name'  => $user->display_name,
			'email' => $user->user_email,
		);
	}

	usort(
		$out,
		function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		}
	);

	return $out;
}

// ---------------------------------------------------------------------------
// List fields: stored as HTML, edited as lines.
// ---------------------------------------------------------------------------

/**
 * Turn a stored list field into plain-text lines.
 *
 * The stored value is inconsistent by history (see flxlm_careers_offer_items()
 * in the theme): some postings carry a <ul>, some bare <li> rows, some a
 * closing <p> after the list. Every block boundary becomes a line break rather
 * than only the <li> items being kept, so a closing paragraph survives a round
 * trip through the hub as a line instead of silently disappearing on the first
 * save.
 *
 * @param string $html Stored meta.
 * @return string[]
 */
function flxlm_ats_posting_html_to_lines( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return array();
	}

	$html = preg_replace( '#<\s*br\s*/?>|</\s*(li|p|div|h[1-6]|ul|ol)\s*>#i', "\n", $html );
	$text = html_entity_decode( wp_strip_all_tags( $html, false ), ENT_QUOTES, 'UTF-8' );

	$lines = array();
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$lines[] = $line;
		}
	}
	return $lines;
}

/**
 * Turn lines into the stored list HTML: "<ul>\n\t<li>..</li>\n</ul>".
 *
 * Each line is escaped, so a list line can never carry markup onto a public
 * page. Empty lines are dropped; no lines at all stores an empty string.
 *
 * @param string[] $lines Lines.
 * @return string
 */
function flxlm_ats_posting_lines_to_html( $lines ) {
	$items = array();
	foreach ( (array) $lines as $line ) {
		$line = trim( (string) $line );
		if ( '' !== $line ) {
			$items[] = "\t<li>" . esc_html( $line ) . '</li>';
		}
	}
	return $items ? "<ul>\n" . implode( "\n", $items ) . "\n</ul>" : '';
}

/**
 * Normalise a list input to an array of trimmed, non-empty lines.
 *
 * @param mixed $value Array of lines, or a newline-separated string.
 * @return string[]
 */
function flxlm_ats_posting_input_lines( $value ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/\R/', $value );
	}
	$lines = array();
	foreach ( (array) $value as $line ) {
		if ( ! is_scalar( $line ) ) {
			continue;
		}
		$line = sanitize_text_field( (string) $line );
		if ( '' !== $line ) {
			$lines[] = $line;
		}
	}
	return $lines;
}

// ---------------------------------------------------------------------------
// The record.
// ---------------------------------------------------------------------------

/**
 * A number from meta or input, or null. Integers stay integers so the hub
 * shows "18" rather than "18.0".
 *
 * @param mixed $value Raw value.
 * @return int|float|null
 */
function flxlm_ats_posting_number( $value ) {
	if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
		return null;
	}
	return 0 + $value;
}

/**
 * Read a posting into the editable field set, the shape validation runs on.
 *
 * @param WP_Post $post Posting.
 * @return array
 */
function flxlm_ats_posting_fields( $post ) {
	$id = $post->ID;
	return array(
		'title'             => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
		'slug'              => (string) $post->post_name,
		'intro'             => (string) $post->post_content,
		'excerpt'           => html_entity_decode( (string) $post->post_excerpt, ENT_QUOTES, 'UTF-8' ),
		'location'          => (string) get_post_meta( $id, 'job_location', true ),
		'job_type'          => (string) get_post_meta( $id, 'job_type', true ),
		'job_email'         => (string) get_post_meta( $id, 'job_email', true ),
		'hiring_manager_id' => (int) get_post_meta( $id, 'job_hiring_manager', true ),
		'responsibilities'  => flxlm_ats_posting_html_to_lines( get_post_meta( $id, 'job_responsibilities', true ) ),
		'qualifications'    => flxlm_ats_posting_html_to_lines( get_post_meta( $id, 'job_qualifications', true ) ),
		'offer'             => flxlm_ats_posting_html_to_lines( get_post_meta( $id, 'job_offer', true ) ),
		'pay_min'           => flxlm_ats_posting_number( get_post_meta( $id, 'job_salary_min', true ) ),
		'pay_max'           => flxlm_ats_posting_number( get_post_meta( $id, 'job_salary_max', true ) ),
		'pay_unit'          => strtoupper( (string) get_post_meta( $id, 'job_salary_unit', true ) ),
		'close_date'        => (string) get_post_meta( $id, 'job_close_date', true ),
		'locality'          => (string) get_post_meta( $id, 'job_locality', true ),
		'postal_code'       => (string) get_post_meta( $id, 'job_postal_code', true ),
	);
}

/**
 * The last FLDN sync result, as the hub shows it.
 *
 * A published posting with no record at all predates the hub: it may well be
 * on FLDN already, but nothing here can say so, so it reads as out of sync
 * with a Retry rather than as a green light nobody earned.
 *
 * @param WP_Post $post Posting.
 * @return array { state, at, error, url }
 */
function flxlm_ats_posting_sync_state( $post ) {
	$status = flxlm_ats_posting_status( $post );
	if ( 'draft' === $status ) {
		return array( 'state' => 'never', 'at' => null, 'error' => null, 'url' => null );
	}

	$record = json_decode( (string) get_post_meta( $post->ID, 'job_sync_fldn', true ), true );
	if ( ! is_array( $record ) || empty( $record['state'] ) ) {
		if ( 'closed' === $status ) {
			return array( 'state' => 'not_applicable', 'at' => null, 'error' => null, 'url' => null );
		}
		return array(
			'state' => 'out_of_sync',
			'at'    => null,
			'error' => 'Not yet sent to the news site from the hub.',
			'url'   => null,
		);
	}

	return array(
		'state' => (string) $record['state'],
		'at'    => isset( $record['at'] ) ? (string) $record['at'] : null,
		'error' => isset( $record['error'] ) && '' !== (string) $record['error'] ? (string) $record['error'] : null,
		'url'   => isset( $record['url'] ) && '' !== (string) $record['url'] ? (string) $record['url'] : null,
	);
}

/**
 * How many applications a posting has, across every stage.
 *
 * @param int $post_id Posting ID.
 * @return int
 */
function flxlm_ats_posting_applicant_count( $post_id ) {
	$ids = get_posts(
		array(
			'post_type'        => 'flxlm_application',
			'post_status'      => array_keys( flxlm_ats_stages() ),
			'posts_per_page'   => -1,
			'meta_key'         => '_flxlm_job_id',
			'meta_value'       => (string) $post_id,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	return count( $ids );
}

/**
 * PostingSummary: one row of the hub's postings list.
 *
 * @param WP_Post $post Posting.
 * @return array
 */
function flxlm_ats_posting_summary( $post ) {
	$fields = flxlm_ats_posting_fields( $post );
	$status = flxlm_ats_posting_status( $post );

	return array(
		'id'              => $post->ID,
		'title'           => $fields['title'],
		'slug'            => $fields['slug'],
		'status'          => $status,
		'job_type'        => $fields['job_type'],
		'location'        => $fields['location'],
		'hiring_manager'  => flxlm_ats_posting_hiring_manager( $post->ID ),
		'pay'             => array(
			'min'  => $fields['pay_min'],
			'max'  => $fields['pay_max'],
			'unit' => '' !== $fields['pay_unit'] ? $fields['pay_unit'] : null,
		),
		'close_date'      => '' !== $fields['close_date'] ? $fields['close_date'] : null,
		'applicant_count' => flxlm_ats_posting_applicant_count( $post->ID ),
		'sync'            => array( 'fldn' => flxlm_ats_posting_sync_state( $post ) ),
		'public_url'      => 'published' === $status ? get_permalink( $post ) : null,
	);
}

/**
 * PostingFull: the summary plus everything the editor needs.
 *
 * @param WP_Post $post Posting.
 * @return array
 */
function flxlm_ats_posting_full( $post ) {
	$fields = flxlm_ats_posting_fields( $post );

	return array_merge(
		flxlm_ats_posting_summary( $post ),
		array(
			'excerpt'          => $fields['excerpt'],
			'intro'            => $fields['intro'],
			'job_email'        => $fields['job_email'],
			'responsibilities' => $fields['responsibilities'],
			'qualifications'   => $fields['qualifications'],
			'offer'            => $fields['offer'],
			'locality'         => $fields['locality'],
			'postal_code'      => $fields['postal_code'],
			'validation'       => flxlm_ats_posting_validate( $fields ),
		)
	);
}

/**
 * Every posting the hub lists: draft, published and closed, newest first.
 *
 * @return WP_Post[]
 */
function flxlm_ats_posting_list() {
	return get_posts(
		array(
			'post_type'        => 'flxlm_job',
			'post_status'      => array( 'draft', 'pending', 'publish', 'private' ),
			'posts_per_page'   => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
}

// ---------------------------------------------------------------------------
// Validation.
// ---------------------------------------------------------------------------

/**
 * Whether text carries an em dash or an en dash, including as an entity.
 *
 * House rule for anything published. Checked after entity decoding so
 * "&mdash;" in the intro HTML is caught the same as the character itself.
 *
 * @param string $text Text or HTML.
 * @return bool
 */
function flxlm_ats_posting_has_dash( $text ) {
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return false !== strpos( $text, "\u{2014}" ) || false !== strpos( $text, "\u{2013}" );
}

/**
 * Decide whether a posting may be published.
 *
 * Server-side and authoritative: the hub shows these, and /publish enforces
 * them. Errors block publishing; warnings do not.
 *
 * @param array $fields Output of flxlm_ats_posting_fields(), or a merged candidate of the same shape.
 * @return array { ok: bool, errors: string[], warnings: string[] }
 */
function flxlm_ats_posting_validate( $fields ) {
	$errors   = array();
	$warnings = array();

	if ( '' === trim( $fields['title'] ) ) {
		$errors[] = 'Title is required.';
	}
	if ( '' === trim( $fields['location'] ) ) {
		$errors[] = 'Location is required.';
	}
	if ( '' === trim( $fields['job_type'] ) ) {
		$errors[] = 'Job type is required.';
	}

	if ( '' === $fields['close_date'] ) {
		$errors[] = 'Close date is required.';
	} elseif ( $fields['close_date'] < wp_date( 'Y-m-d' ) ) {
		$errors[] = 'Close date is in the past. Pick today or a later date.';
	}

	if ( $fields['hiring_manager_id'] < 1 ) {
		$errors[] = 'Pick a hiring manager. They get an email for every application.';
	} elseif ( ! flxlm_ats_posting_valid_manager( $fields['hiring_manager_id'] ) ) {
		$errors[] = 'The hiring manager must be someone who can view applications.';
	}

	if ( ! $fields['responsibilities'] ) {
		$errors[] = 'Add at least one line under responsibilities.';
	}
	if ( ! $fields['qualifications'] ) {
		$errors[] = 'Add at least one line under qualifications.';
	}

	$dash_fields = array(
		'Title'            => $fields['title'],
		'Intro'            => $fields['intro'],
		'Excerpt'          => $fields['excerpt'],
		'Responsibilities' => implode( "\n", $fields['responsibilities'] ),
		'Qualifications'   => implode( "\n", $fields['qualifications'] ),
		'What we offer'    => implode( "\n", $fields['offer'] ),
	);
	foreach ( $dash_fields as $label => $text ) {
		if ( flxlm_ats_posting_has_dash( $text ) ) {
			$errors[] = $label . ' contains an em dash or en dash. Use a comma, colon or period instead.';
		}
	}

	$min = $fields['pay_min'];
	$max = $fields['pay_max'];
	if ( ! flxlm_ats_posting_is_freelance( $fields['job_type'] ) ) {
		// New York Labor Law 194-b: a good faith minimum AND maximum on any
		// posting for work performed in the state.
		$pay_ok = null !== $min && null !== $max && $min > 0 && $max >= $min
			&& in_array( $fields['pay_unit'], FLXLM_ATS_POSTING_PAY_UNITS, true );
		if ( ! $pay_ok ) {
			$errors[] = 'New York requires a good-faith pay range (minimum and maximum) on this posting.';
		}
	} elseif ( null === $min && null === $max ) {
		$warnings[] = 'No pay figure. Paid-per-story roles have no Google Jobs pay unit; confirm with counsel whether a per-story rate should be published.';
	}

	return array(
		'ok'       => ! $errors,
		'errors'   => $errors,
		'warnings' => $warnings,
	);
}

// ---------------------------------------------------------------------------
// Writing.
// ---------------------------------------------------------------------------

/**
 * Whether a slug is already taken by a different flxlm_job.
 *
 * @param string $slug    Slug.
 * @param int    $post_id The posting being saved (excluded), 0 for a new one.
 * @return bool
 */
function flxlm_ats_posting_slug_taken( $slug, $post_id ) {
	$found = get_posts(
		array(
			'post_type'        => 'flxlm_job',
			'name'             => $slug,
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'post__not_in'     => $post_id ? array( (int) $post_id ) : array(),
		)
	);
	return (bool) $found;
}

/**
 * Parse a PostingInput against the posting it will change.
 *
 * Merge semantics: only keys present in the input are changed. Anything that
 * can never be valid (a bad email, a non-number pay, a manager who cannot see
 * applications, a slug change on a live posting) is rejected here, before any
 * write, so a 422 means nothing changed.
 *
 * @param array        $input PostingInput from the hub.
 * @param WP_Post|null $post  Existing posting, or null when creating.
 * @return array { fields: merged candidate, changed: string[] keys present, errors: string[] }
 */
function flxlm_ats_posting_parse_input( $input, $post ) {
	$fields  = $post ? flxlm_ats_posting_fields( $post ) : array(
		'title'             => '',
		'slug'              => '',
		'intro'             => '',
		'excerpt'           => '',
		'location'          => '',
		'job_type'          => '',
		'job_email'         => FLXLM_ATS_POSTING_DEFAULT_EMAIL,
		'hiring_manager_id' => 0,
		'responsibilities'  => array(),
		'qualifications'    => array(),
		'offer'             => array(),
		'pay_min'           => null,
		'pay_max'           => null,
		'pay_unit'          => '',
		'close_date'        => '',
		'locality'          => '',
		'postal_code'       => '',
	);
	$changed = array();
	$errors  = array();
	$has     = function ( $key ) use ( $input ) {
		return array_key_exists( $key, $input );
	};
	// A scalar as a string; anything else (an array where text belongs) as ''.
	$str     = function ( $key ) use ( $input ) {
		return is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
	};

	foreach ( array( 'title', 'location', 'job_type', 'locality', 'postal_code' ) as $key ) {
		if ( $has( $key ) ) {
			$fields[ $key ] = sanitize_text_field( $str( $key ) );
			$changed[]      = $key;
		}
	}

	if ( $has( 'excerpt' ) ) {
		$fields['excerpt'] = sanitize_textarea_field( $str( 'excerpt' ) );
		$changed[]         = 'excerpt';
	}

	if ( $has( 'intro' ) ) {
		$fields['intro'] = wp_kses_post( $str( 'intro' ) );
		$changed[]       = 'intro';
	}

	foreach ( array( 'responsibilities', 'qualifications', 'offer' ) as $key ) {
		if ( $has( $key ) ) {
			$fields[ $key ] = flxlm_ats_posting_input_lines( $input[ $key ] );
			$changed[]      = $key;
		}
	}

	if ( $has( 'slug' ) ) {
		$slug = sanitize_title( $str( 'slug' ) );
		if ( $post && in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			// The public URL is indexed in Google Jobs. Changing it would break
			// every link to it and orphan the FLDN twin, which is keyed on slug.
			if ( $slug !== $post->post_name ) {
				$errors[] = 'slug is locked once published';
			}
		} elseif ( '' !== $slug && flxlm_ats_posting_slug_taken( $slug, $post ? $post->ID : 0 ) ) {
			$errors[] = 'That slug is already used by another posting.';
		} else {
			$fields['slug'] = $slug;
			$changed[]      = 'slug';
		}
	}

	if ( $has( 'job_email' ) ) {
		$email = sanitize_text_field( $str( 'job_email' ) );
		if ( '' === $email ) {
			$email = FLXLM_ATS_POSTING_DEFAULT_EMAIL;
		}
		foreach ( explode( ',', $email ) as $candidate ) {
			if ( ! is_email( trim( $candidate ) ) ) {
				$errors[] = 'The public contact email is not a valid address.';
				break;
			}
		}
		$fields['job_email'] = $email;
		$changed[]           = 'job_email';
	}

	if ( $has( 'hiring_manager_id' ) ) {
		$manager = (int) $str( 'hiring_manager_id' );
		if ( $manager > 0 && ! flxlm_ats_posting_valid_manager( $manager ) ) {
			$errors[] = 'The hiring manager must be someone who can view applications.';
		} else {
			$fields['hiring_manager_id'] = max( 0, $manager );
			$changed[]                   = 'hiring_manager_id';
		}
	}

	foreach ( array( 'pay_min', 'pay_max' ) as $key ) {
		if ( $has( $key ) ) {
			$raw = $input[ $key ];
			if ( null !== $raw && '' !== $raw && ( ! is_numeric( $raw ) || $raw < 0 ) ) {
				$errors[] = ( 'pay_min' === $key ? 'Pay minimum' : 'Pay maximum' ) . ' must be a number.';
				continue;
			}
			$fields[ $key ] = flxlm_ats_posting_number( $raw );
			$changed[]      = $key;
		}
	}

	if ( $has( 'pay_unit' ) ) {
		$unit = strtoupper( trim( $str( 'pay_unit' ) ) );
		if ( '' !== $unit && ! in_array( $unit, FLXLM_ATS_POSTING_PAY_UNITS, true ) ) {
			$errors[] = 'Pay unit must be one of HOUR, DAY, WEEK, MONTH or YEAR.';
		} else {
			$fields['pay_unit'] = $unit;
			$changed[]          = 'pay_unit';
		}
	}

	if ( $has( 'close_date' ) ) {
		$date   = trim( $str( 'close_date' ) );
		$parsed = '' !== $date ? DateTime::createFromFormat( '!Y-m-d', $date ) : null;
		if ( '' !== $date && ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) ) {
			$errors[] = 'Close date must be a date in the form YYYY-MM-DD.';
		} else {
			$fields['close_date'] = $date;
			$changed[]            = 'close_date';
		}
	}

	return array(
		'fields'  => $fields,
		'changed' => $changed,
		'errors'  => $errors,
	);
}

/**
 * Write one meta value, without creating an empty key that never existed.
 *
 * The hub form sends every field on every save, blanks included. Writing ''
 * into a key this posting never had would then travel to FLDN as "set this to
 * empty" and wipe values only FLDN carries (a freelance posting's
 * job_locality, for one). A blank for a key that was never set is therefore a
 * no-op; a blank for a key that was set is a real clear and is stored as ''
 * so the sync carries it.
 *
 * Values are slashed because update_post_meta() unslashes, and a backslash in
 * a posting (or in the JSON sync record) must survive.
 *
 * @param int    $post_id Posting ID.
 * @param string $key     Meta key.
 * @param string $value   Value.
 */
function flxlm_ats_posting_put_meta( $post_id, $key, $value ) {
	$value = (string) $value;
	if ( '' === $value && ! metadata_exists( 'post', $post_id, $key ) ) {
		return;
	}
	update_post_meta( $post_id, $key, wp_slash( $value ) );
}

/**
 * Write the changed fields of a parsed input to a posting.
 *
 * Calls wp_update_post() directly with no capability check of its own: the
 * bridge's permission callbacks already decided who may do this, and the wp-admin
 * lock (inc/postings-lock.php) only applies to wp-admin requests, so this path
 * keeps working when the lock is on.
 *
 * @param int   $post_id Posting ID.
 * @param array $parsed  Output of flxlm_ats_posting_parse_input().
 * @return true|WP_Error
 */
function flxlm_ats_posting_write( $post_id, $parsed ) {
	$fields  = $parsed['fields'];
	$changed = array_flip( $parsed['changed'] );

	$postarr = array( 'ID' => $post_id );
	if ( isset( $changed['title'] ) ) {
		$postarr['post_title'] = $fields['title'];
	}
	if ( isset( $changed['slug'] ) ) {
		$postarr['post_name'] = $fields['slug'];
	}
	if ( isset( $changed['intro'] ) ) {
		$postarr['post_content'] = $fields['intro'];
	}
	if ( isset( $changed['excerpt'] ) ) {
		$postarr['post_excerpt'] = $fields['excerpt'];
	}
	if ( count( $postarr ) > 1 ) {
		$result = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	$simple = array(
		'location'    => 'job_location',
		'job_type'    => 'job_type',
		'job_email'   => 'job_email',
		'pay_unit'    => 'job_salary_unit',
		'close_date'  => 'job_close_date',
		'locality'    => 'job_locality',
		'postal_code' => 'job_postal_code',
	);
	foreach ( $simple as $field => $key ) {
		if ( isset( $changed[ $field ] ) ) {
			flxlm_ats_posting_put_meta( $post_id, $key, $fields[ $field ] );
		}
	}

	foreach ( array( 'pay_min' => 'job_salary_min', 'pay_max' => 'job_salary_max' ) as $field => $key ) {
		if ( isset( $changed[ $field ] ) ) {
			flxlm_ats_posting_put_meta( $post_id, $key, null === $fields[ $field ] ? '' : (string) $fields[ $field ] );
		}
	}

	foreach ( array( 'responsibilities' => 'job_responsibilities', 'qualifications' => 'job_qualifications', 'offer' => 'job_offer' ) as $field => $key ) {
		if ( isset( $changed[ $field ] ) ) {
			flxlm_ats_posting_put_meta( $post_id, $key, flxlm_ats_posting_lines_to_html( $fields[ $field ] ) );
		}
	}

	if ( isset( $changed['hiring_manager_id'] ) ) {
		if ( $fields['hiring_manager_id'] > 0 ) {
			update_post_meta( $post_id, 'job_hiring_manager', (int) $fields['hiring_manager_id'] );
		} else {
			delete_post_meta( $post_id, 'job_hiring_manager' );
		}
	}

	return true;
}

/**
 * Create a draft posting.
 *
 * @param array   $parsed Output of flxlm_ats_posting_parse_input( $input, null ).
 * @param WP_User $author Hub user creating it.
 * @return int|WP_Error Posting ID.
 */
function flxlm_ats_posting_create( $parsed, $author ) {
	$fields = $parsed['fields'];

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'flxlm_job',
				'post_status'  => 'draft',
				'post_title'   => $fields['title'],
				'post_name'    => $fields['slug'],
				'post_content' => $fields['intro'],
				'post_excerpt' => $fields['excerpt'],
				'post_author'  => $author instanceof WP_User ? $author->ID : 0,
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// A new posting always starts with the shared inbox as its public contact,
	// whether or not the hub sent one.
	$parsed['changed'][] = 'job_email';
	$parsed['changed']   = array_diff( $parsed['changed'], array( 'title', 'slug', 'intro', 'excerpt' ) );

	$written = flxlm_ats_posting_write( $post_id, $parsed );
	return is_wp_error( $written ) ? $written : $post_id;
}

/**
 * Publish a posting: validate, go live, point canonical at FLDN, sync.
 *
 * Every check runs before the first write, so a refusal changes nothing.
 *
 * @param WP_Post $post Posting.
 * @return true|string[] True, or the errors that blocked it.
 */
function flxlm_ats_posting_publish( $post ) {
	$fields     = flxlm_ats_posting_fields( $post );
	$validation = flxlm_ats_posting_validate( $fields );
	$errors     = $validation['errors'];

	// A draft may have no slug yet (WordPress does not assign one to drafts).
	// Derive it the way WordPress would, but check it is free first: letting
	// wp_update_post() quietly append "-2" would publish a URL nobody chose and
	// hand FLDN a slug that does not match its own copy.
	$slug = '' !== $fields['slug'] ? $fields['slug'] : sanitize_title( $fields['title'] );
	if ( '' === $slug ) {
		$errors[] = 'This posting needs a slug before it can be published.';
	} elseif ( flxlm_ats_posting_slug_taken( $slug, $post->ID ) ) {
		$errors[] = 'That slug is already used by another posting.';
	}

	if ( $errors ) {
		return $errors;
	}

	$result = wp_update_post(
		wp_slash(
			array(
				'ID'          => $post->ID,
				'post_status' => 'publish',
				'post_name'   => $slug,
			)
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		return array( $result->get_error_message() );
	}

	$slug = get_post_field( 'post_name', $post->ID );
	delete_post_meta( $post->ID, 'job_closed_at' );
	update_post_meta( $post->ID, 'job_canonical_url', esc_url_raw( FLXLM_ATS_POSTING_FLDN_BASE . $slug . '/' ) );

	flxlm_ats_sync_posting_to_fldn( $post->ID );
	return true;
}

/**
 * Close a posting: private here, draft on FLDN.
 *
 * @param WP_Post $post Posting.
 * @return true|WP_Error
 */
function flxlm_ats_posting_close( $post ) {
	$result = wp_update_post(
		array(
			'ID'          => $post->ID,
			'post_status' => 'private',
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	update_post_meta( $post->ID, 'job_closed_at', gmdate( 'Y-m-d H:i:s' ) );
	flxlm_ats_sync_posting_to_fldn( $post->ID );
	return true;
}

// ---------------------------------------------------------------------------
// FLDN sync.
// ---------------------------------------------------------------------------

/**
 * Store a sync result, keeping the last known FLDN url and id across failures
 * so "View on site" still works while a retry is pending.
 *
 * @param int    $post_id Posting ID.
 * @param string $state   'in_sync' | 'out_of_sync'.
 * @param string $error   Error text, '' on success.
 * @param string $url     FLDN url, '' to keep the previous one.
 * @param int    $fldn_id FLDN post ID, 0 to keep the previous one.
 * @return array The stored record.
 */
function flxlm_ats_posting_record_sync( $post_id, $state, $error, $url = '', $fldn_id = 0 ) {
	$previous = json_decode( (string) get_post_meta( $post_id, 'job_sync_fldn', true ), true );
	$previous = is_array( $previous ) ? $previous : array();

	$record = array(
		'state'   => $state,
		'at'      => gmdate( 'Y-m-d H:i:s' ),
		'error'   => '' !== $error ? $error : null,
		'url'     => '' !== $url ? $url : ( $previous['url'] ?? null ),
		'fldn_id' => $fldn_id > 0 ? $fldn_id : ( $previous['fldn_id'] ?? null ),
	);

	update_post_meta( $post_id, 'job_sync_fldn', wp_slash( wp_json_encode( $record ) ) );
	return $record;
}

/**
 * Push a posting to fingerlakesdailynews.com.
 *
 * FLDN is where the search traffic and the Google Jobs listing are, so a
 * posting published here is not really live until this succeeds. Server to
 * server, signed with the relay secret both sites already share for
 * application relay, but over a different payload ("posting-v1." prefix) so a
 * posting-sync signature can never be replayed as an application relay or the
 * reverse.
 *
 * NEVER THROWS. A failure is recorded as out_of_sync with the reason, which
 * the hub shows next to a Retry button. The FLXLM-side change has already
 * happened by the time this runs and must not be undone by the other site
 * being slow.
 *
 * Deliberately NOT in the body: job_hiring_manager (internal only) and
 * job_canonical_url (FLDN is the canonical copy; it must not point at itself
 * through us).
 *
 * @param int $post_id Posting ID.
 * @return array The sync record.
 */
function flxlm_ats_sync_posting_to_fldn( $post_id ) {
	try {
		$post = flxlm_ats_posting_get( $post_id );
		if ( ! $post ) {
			return array( 'state' => 'never', 'at' => null, 'error' => null, 'url' => null );
		}

		$status = flxlm_ats_posting_status( $post );
		if ( 'draft' === $status ) {
			return array( 'state' => 'never', 'at' => null, 'error' => null, 'url' => null );
		}

		if ( ! defined( 'FLXLM_ATS_RELAY_SECRET' ) || '' === (string) FLXLM_ATS_RELAY_SECRET ) {
			return flxlm_ats_posting_record_sync( $post_id, 'out_of_sync', 'sync secret not configured' );
		}

		// A key this posting never had goes as null, which FLDN reads as "leave
		// yours alone". A key that exists goes as its value, '' included, so a
		// field cleared in the hub is cleared on FLDN too.
		$meta = array();
		foreach ( array( 'job_location', 'job_type', 'job_email', 'job_responsibilities', 'job_qualifications', 'job_offer', 'job_salary_min', 'job_salary_max', 'job_salary_unit', 'job_close_date', 'job_locality', 'job_postal_code' ) as $key ) {
			$meta[ $key ] = metadata_exists( 'post', $post_id, $key ) ? (string) get_post_meta( $post_id, $key, true ) : null;
		}

		$body = wp_json_encode(
			array(
				'slug'    => $post->post_name,
				'status'  => 'published' === $status ? 'publish' : 'closed',
				'title'   => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
				'content' => $post->post_content,
				'excerpt' => $post->post_excerpt,
				'meta'    => $meta,
			)
		);

		$timestamp = (string) time();
		$signature = hash_hmac( 'sha256', 'posting-v1.' . $timestamp . '.' . $body, (string) FLXLM_ATS_RELAY_SECRET );

		$response = wp_remote_post(
			flxlm_ats_posting_fldn_endpoint(),
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-FLX-Sync-Timestamp' => $timestamp,
					'X-FLX-Sync-Signature' => $signature,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return flxlm_ats_posting_record_sync( $post_id, 'out_of_sync', 'Could not reach the news site: ' . $response->get_error_message() );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && is_array( $decoded ) && ! empty( $decoded['ok'] ) ) {
			return flxlm_ats_posting_record_sync(
				$post_id,
				'in_sync',
				'',
				isset( $decoded['url'] ) ? esc_url_raw( (string) $decoded['url'] ) : '',
				isset( $decoded['id'] ) ? (int) $decoded['id'] : 0
			);
		}

		$message = is_array( $decoded ) && ! empty( $decoded['message'] ) ? (string) $decoded['message'] : 'unexpected response';
		return flxlm_ats_posting_record_sync(
			$post_id,
			'out_of_sync',
			'The news site answered HTTP ' . $code . ': ' . substr( wp_strip_all_tags( $message ), 0, 300 )
		);
	} catch ( Throwable $e ) {
		error_log( 'flxlm-ats: posting sync failed for ' . (int) $post_id . ': ' . $e->getMessage() );
		return flxlm_ats_posting_record_sync( $post_id, 'out_of_sync', 'Sync failed: ' . $e->getMessage() );
	}
}
