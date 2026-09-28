<?php
/**
 * Contact editing and the recruitment-source change control.
 *
 * Two things the v1 hub bridge shipped with no way to do, both raised by the
 * owner after using the tool live: fix a typo'd email or phone once an
 * application already exists, and set the recruitment source from anywhere
 * other than the original intake form. A source stuck on "unknown" (or never
 * set at all, on a manually entered or email-intake applicant) can never pass
 * New's own exit test ("Source is known" — inc/stages.php), which was a dead
 * end before this file existed: nothing in the product could set it.
 *
 * WHY THESE ARE PARTIAL UPDATES
 *
 * The contact route accepts any subset of the fields this plugin stores for a
 * person (first name, last name, email, phone) rather than requiring the
 * whole set on every call, because a caller fixing one typo'd digit in a phone
 * number should not have to also resend a name and email it already has
 * correct and unchanged.
 *
 * WHY EVERY CHANGE WRITES A SYSTEM NOTE
 *
 * An application's contact details and its recruitment source both feed the
 * FCC EEO report. A silent edit to either is exactly the kind of change a
 * regulator, or the candidate themselves, might reasonably want an account of
 * later — see inc/notes.php for why this plugin has no update/delete on
 * anything, only new append-only entries.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * The contact fields this plugin will let something edit, mapped to their
 * post meta keys. Order is display order.
 *
 * @return array<string,string> field => meta key.
 */
function flxlm_ats_contact_fields() {
	return array(
		'first_name' => '_flxlm_first_name',
		'last_name'  => '_flxlm_last_name',
		'email'      => '_flxlm_email',
		'phone'      => '_flxlm_phone',
	);
}

/**
 * Validate one contact field's proposed new value.
 *
 * @param string $field Field key (a key of flxlm_ats_contact_fields()).
 * @param string $value Proposed value, already trimmed.
 * @return true|string True, or an error message.
 */
function flxlm_ats_validate_contact_field( $field, $value ) {
	switch ( $field ) {
		case 'first_name':
			return ( '' === $value ) ? 'First name cannot be empty.' : true;

		case 'last_name':
			return ( '' === $value ) ? 'Last name cannot be empty.' : true;

		case 'email':
			if ( '' === $value ) {
				return 'Email cannot be empty.';
			}
			return is_email( $value ) ? true : 'Enter a valid email address.';

		case 'phone':
			// Phone is optional on intake (inc/form.php has no `required` on
			// it), so clearing it is a legitimate edit, not an error. A
			// non-empty value has to look like a phone number: digits, and
			// the punctuation a person actually types around them, with at
			// least 7 digits so "call me" or a stray "x" cannot pass.
			if ( '' === $value ) {
				return true;
			}
			if ( ! preg_match( '/^[0-9+()\-.\sx]+$/i', $value ) ) {
				return 'That does not look like a phone number.';
			}
			$digits = preg_replace( '/\D/', '', $value );
			return ( strlen( $digits ) >= 7 ) ? true : 'That does not look like a phone number.';

		default:
			return 'Unknown field.';
	}
}

/**
 * Update an applicant's contact details.
 *
 * @param int    $application_id Application ID.
 * @param array  $fields         Any subset of {first_name, last_name, email, phone}.
 * @param string $author_email   Who made the change, for the system note.
 * @param string $author_name    Display name to go with author_email.
 * @return true|WP_Error True on success (including a no-op with nothing
 *                        recognised to change). On invalid input, a WP_Error
 *                        with code 'flxlm_ats_invalid_contact' whose error
 *                        data carries 'errors' => array of {field, message},
 *                        matching the hub bridge's 422 {error, errors:[...]}
 *                        contract used elsewhere in this plugin (see
 *                        inc/stages.php's flxlm_ats_set_stage()).
 */
function flxlm_ats_update_contact( $application_id, $fields, $author_email = '', $author_name = '' ) {
	$application_id = (int) $application_id;
	$post           = get_post( $application_id );
	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	$known  = flxlm_ats_contact_fields();
	$errors = array();
	$clean  = array();

	foreach ( $known as $field => $meta_key ) {
		if ( ! array_key_exists( $field, $fields ) ) {
			continue;
		}

		// Email is validated against the raw trimmed input, not the
		// sanitized one: sanitize_email() silently returns '' for a string
		// that does not even loosely resemble an address ("not-an-email"),
		// which would otherwise surface as the misleading "cannot be empty"
		// rather than "enter a valid email address".
		$raw   = trim( (string) $fields[ $field ] );
		$value = 'email' === $field ? $raw : sanitize_text_field( $raw );

		$valid = flxlm_ats_validate_contact_field( $field, $value );
		if ( true !== $valid ) {
			$errors[] = array( 'field' => $field, 'message' => $valid );
			continue;
		}

		// Now that it has passed validation, run email through sanitize_email()
		// too (a no-op for anything is_email() already accepted) so the stored
		// value matches every other write path in this plugin.
		$clean[ $field ] = ( 'email' === $field ) ? sanitize_email( $value ) : $value;
	}

	if ( $errors ) {
		return new WP_Error(
			'flxlm_ats_invalid_contact',
			'That contact information could not be saved.',
			array( 'errors' => $errors )
		);
	}

	if ( ! $clean ) {
		return true; // Nothing recognised was sent. Not an error: a no-op save.
	}

	$labels  = array(
		'first_name' => 'First name',
		'last_name'  => 'Last name',
		'email'      => 'Email',
		'phone'      => 'Phone',
	);
	$changes = array();

	foreach ( $clean as $field => $new_value ) {
		$meta_key = $known[ $field ];
		$old_value = (string) get_post_meta( $application_id, $meta_key, true );
		if ( $old_value === $new_value ) {
			continue; // Genuinely unchanged; do not write, do not log.
		}
		update_post_meta( $application_id, $meta_key, $new_value );
		$changes[] = sprintf(
			'%s: "%s" -> "%s"',
			$labels[ $field ],
			'' === $old_value ? '(blank)' : $old_value,
			'' === $new_value ? '(blank)' : $new_value
		);
	}

	if ( $changes && function_exists( 'flxlm_ats_add_note' ) ) {
		flxlm_ats_add_note(
			$application_id,
			'system',
			array(
				'body'         => "Contact information updated.\n" . implode( "\n", $changes ),
				'author_email' => $author_email,
				'author_name'  => $author_name,
			)
		);
	}

	/**
	 * Fires after an applicant's contact details are edited.
	 *
	 * @param int   $application_id Application ID.
	 * @param array $changed_fields Field keys that actually changed.
	 */
	do_action( 'flxlm_ats_contact_updated', $application_id, array_keys( $clean ) );

	return true;
}

/**
 * Update an applicant's recruitment source.
 *
 * Accepts anything flxlm_ats_is_source() recognises, including the
 * staff-only 'unknown' key (inc/sources.php) — this route serves both "fix
 * the real source" and "explicitly mark it not known yet", the same choice
 * the manual-entry screen already offers. flxlm_ats_hub_selectable_sources()
 * is the narrower list ("never 'unknown' as a choice") a picker should
 * actually present when the point is to CLEAR an unknown source.
 *
 * @param int    $application_id Application ID.
 * @param string $source         Source key.
 * @param string $author_email   Who made the change, for the system note.
 * @param string $author_name    Display name to go with author_email.
 * @return true|WP_Error True on success. On an invalid key, a WP_Error with
 *                        code 'flxlm_ats_invalid_source' whose error data
 *                        carries 'errors' => array of one {field, message}.
 */
function flxlm_ats_update_source( $application_id, $source, $author_email = '', $author_name = '' ) {
	$application_id = (int) $application_id;
	$post           = get_post( $application_id );
	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return new WP_Error( 'flxlm_ats_no_application', 'No such application.' );
	}

	$source = sanitize_key( (string) $source );

	if ( ! flxlm_ats_is_source( $source ) ) {
		return new WP_Error(
			'flxlm_ats_invalid_source',
			'That is not a recognised recruitment source.',
			array( 'errors' => array( array( 'field' => 'source', 'message' => 'Choose a source from the list.' ) ) )
		);
	}

	$old = (string) get_post_meta( $application_id, '_flxlm_source', true );
	if ( $old === $source ) {
		return true; // No-op.
	}

	update_post_meta( $application_id, '_flxlm_source', $source );

	if ( function_exists( 'flxlm_ats_add_note' ) ) {
		flxlm_ats_add_note(
			$application_id,
			'system',
			array(
				'body'         => sprintf(
					'Recruitment source changed: "%s" -> "%s"',
					$old ? flxlm_ats_source_label( $old ) : '(not set)',
					flxlm_ats_source_label( $source )
				),
				'author_email' => $author_email,
				'author_name'  => $author_name,
			)
		);
	}

	/**
	 * Fires after an applicant's recruitment source changes.
	 *
	 * @param int    $application_id Application ID.
	 * @param string $source         New source key.
	 * @param string $old            Previous source key.
	 */
	do_action( 'flxlm_ats_source_updated', $application_id, $source, $old );

	return true;
}

/**
 * The sources a picker should actually offer, e.g. to fix an unmet
 * "source is known" exit test: the real vocabulary, with 'unknown' left out
 * on purpose. Offering "Not known yet" as the answer to "this applicant's
 * source is not known yet" would let the exit test be satisfied by nothing
 * changing at all.
 *
 * @return array<string,string> key => label.
 */
function flxlm_ats_hub_selectable_sources() {
	return flxlm_ats_sources();
}

// ---------------------------------------------------------------------------
// Small display helpers used everywhere contact details are shown: wp-admin
// and every signed page this plugin renders. One function each, so "make the
// email/phone clickable" is one edit, not a find-and-replace across the repo.
// ---------------------------------------------------------------------------

/**
 * A clickable mailto: link for an email address, or nothing.
 *
 * @param string $email Email address.
 * @return string Escaped HTML, or ''.
 */
function flxlm_ats_mailto_html( $email ) {
	$email = trim( (string) $email );
	if ( '' === $email || ! is_email( $email ) ) {
		return esc_html( $email );
	}
	return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
}

/**
 * A clickable tel: link for a phone number, or nothing.
 *
 * The tel: URI keeps the number exactly as displayed (a person reads
 * "607-555-0148" more easily than a stripped string) but the href strips
 * everything except leading + and digits, which is what a phone dialler
 * actually needs; punctuation left in a tel: href is undefined behaviour on
 * some devices.
 *
 * @param string $phone Phone number as stored.
 * @return string Escaped HTML, or ''.
 */
function flxlm_ats_tel_html( $phone ) {
	$phone = trim( (string) $phone );
	if ( '' === $phone ) {
		return '';
	}
	$digits = preg_replace( '/[^0-9+]/', '', $phone );
	if ( '' === $digits ) {
		return esc_html( $phone );
	}
	return sprintf( '<a href="tel:%s">%s</a>', esc_attr( $digits ), esc_html( $phone ) );
}

// ---------------------------------------------------------------------------
// wp-admin: the Edit control on the contact card, and the source change
// control, both on the single-application screen (inc/admin-detail.php).
// Same admin-post.php + nonce pattern as the stage moves in
// inc/admin-list.php's flxlm_ats_handle_admin_stage() — one more handler
// registered the same way, not a second mechanism.
// ---------------------------------------------------------------------------

/**
 * Handle the wp-admin contact-edit form submission.
 */
function flxlm_ats_handle_admin_contact() {
	$application_id = isset( $_POST['application'] ) ? (int) $_POST['application'] : 0;

	check_admin_referer( 'flxlm_ats_contact_' . $application_id );

	if ( ! current_user_can( 'flxlm_manage_applications' ) ) {
		wp_die( 'You do not have permission to edit this application.', 'Not allowed', array( 'response' => 403 ) );
	}

	$fields = array();
	foreach ( array_keys( flxlm_ats_contact_fields() ) as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			$fields[ $field ] = wp_unslash( $_POST[ $field ] );
		}
	}

	$user   = wp_get_current_user();
	$result = flxlm_ats_update_contact( $application_id, $fields, $user->user_email, $user->display_name );

	$back = wp_get_referer();
	$back = $back ? $back : admin_url( 'edit.php?post_type=flxlm_application' );

	if ( is_wp_error( $result ) ) {
		$data     = $result->get_error_data();
		$messages = array();
		foreach ( (array) ( $data['errors'] ?? array() ) as $error ) {
			$messages[] = $error['message'];
		}
		set_transient( 'flxlm_ats_contact_error_' . $application_id, $messages ? $messages : array( $result->get_error_message() ), MINUTE_IN_SECONDS );
		wp_safe_redirect( $back . '#flxlm_ats_contact' );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'flxlm_ats_contact_saved', '1', $back ) . '#flxlm_ats_contact' );
	exit;
}
add_action( 'admin_post_flxlm_ats_contact', 'flxlm_ats_handle_admin_contact' );

/**
 * Handle the wp-admin source-change form submission.
 */
function flxlm_ats_handle_admin_source() {
	$application_id = isset( $_POST['application'] ) ? (int) $_POST['application'] : 0;

	check_admin_referer( 'flxlm_ats_source_' . $application_id );

	if ( ! current_user_can( 'flxlm_manage_applications' ) ) {
		wp_die( 'You do not have permission to edit this application.', 'Not allowed', array( 'response' => 403 ) );
	}

	$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';

	$user   = wp_get_current_user();
	$result = flxlm_ats_update_source( $application_id, $source, $user->user_email, $user->display_name );

	$back = wp_get_referer();
	$back = $back ? $back : admin_url( 'edit.php?post_type=flxlm_application' );

	if ( is_wp_error( $result ) ) {
		set_transient( 'flxlm_ats_source_error_' . $application_id, $result->get_error_message(), MINUTE_IN_SECONDS );
		wp_safe_redirect( $back . '#flxlm_ats_source' );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'flxlm_ats_source_saved', '1', $back ) . '#flxlm_ats_source' );
	exit;
}
add_action( 'admin_post_flxlm_ats_source', 'flxlm_ats_handle_admin_source' );

/**
 * Notices for the contact/source forms above. Separate from
 * flxlm_ats_moved_notice() (inc/admin-list.php) because these carry
 * per-field validation messages a plain "it worked / it didn't" cannot.
 */
function flxlm_ats_contact_source_notices() {
	$application_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

	if ( ! empty( $_GET['flxlm_ats_contact_saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Contact information saved.</p></div>';
	}
	if ( $application_id ) {
		$errors = get_transient( 'flxlm_ats_contact_error_' . $application_id );
		if ( $errors ) {
			delete_transient( 'flxlm_ats_contact_error_' . $application_id );
			echo '<div class="notice notice-error is-dismissible"><p>Could not save contact information: '
				. esc_html( implode( ' ', (array) $errors ) ) . '</p></div>';
		}
	}

	if ( ! empty( $_GET['flxlm_ats_source_saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Recruitment source saved.</p></div>';
	}
	if ( $application_id ) {
		$error = get_transient( 'flxlm_ats_source_error_' . $application_id );
		if ( $error ) {
			delete_transient( 'flxlm_ats_source_error_' . $application_id );
			echo '<div class="notice notice-error is-dismissible"><p>Could not save recruitment source: ' . esc_html( $error ) . '</p></div>';
		}
	}
}
add_action( 'admin_notices', 'flxlm_ats_contact_source_notices' );
