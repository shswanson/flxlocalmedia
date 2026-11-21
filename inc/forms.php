<?php
/**
 * Generic form rendering + fallback handler.
 *
 * Form action is pluggable via FLXLM_FORM_ACTION constant or flxlm_form_action filter.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the form action URL.
 *
 * Priority: constant > filter > admin-post fallback.
 *
 * @return string
 */
function flxlm_get_form_action() {
	if ( defined( 'FLXLM_FORM_ACTION' ) && FLXLM_FORM_ACTION ) {
		return FLXLM_FORM_ACTION;
	}

	$action = admin_url( 'admin-post.php' );

	return apply_filters( 'flxlm_form_action', $action );
}

/**
 * Allowed answers for the annual marketing spend question.
 *
 * Used for both rendering the select and validating the submission.
 *
 * @return string[]
 */
function flxlm_form_spend_options() {
	return array(
		'Under $3,000',
		'$3,000 to $12,000',
		'Over $12,000',
		'Not sure',
	);
}

/**
 * Who gets every inbound lead from the site, to assign to a rep.
 *
 * Priority: FLXLM_LEAD_ASSIGNER constant > flxlm_lead_assigner option > Frank Lischak.
 * (Never use frank@, it bounces.)
 *
 * @return string
 */
function flxlm_get_lead_assigner() {
	$assigner = '';
	if ( defined( 'FLXLM_LEAD_ASSIGNER' ) && FLXLM_LEAD_ASSIGNER ) {
		$assigner = FLXLM_LEAD_ASSIGNER;
	} else {
		$assigner = get_option( 'flxlm_lead_assigner', '' );
	}

	return is_email( $assigner ) ? $assigner : 'flischak@flxlocalmedia.com';
}

/**
 * The campaign values we carry from the landing URL through the form.
 *
 * @return string[]
 */
function flxlm_form_utm_keys() {
	return array( 'utm_source', 'utm_medium', 'utm_campaign' );
}

/**
 * Render the contact form (the free local marketing review request).
 */
function flxlm_render_contact_form() {
	$action = flxlm_get_form_action();
	$nonce_action = 'flxlm_contact_form';
	?>
	<form class="flxlm-form" method="post" action="<?php echo esc_url( $action ); ?>">
		<?php wp_nonce_field( $nonce_action, 'flxlm_form_nonce' ); ?>
		<input type="hidden" name="action" value="flxlm_contact_submit" />
		<input type="hidden" name="source_page" value="<?php echo esc_attr( get_the_title() . ' (' . get_permalink() . ')' ); ?>" />
<?php foreach ( flxlm_form_utm_keys() as $utm_key ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $utm_key ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET[ $utm_key ] ?? '' ) ) ); ?>" />
<?php endforeach; ?>

		<div class="flxlm-form__row flxlm-form__row--two">
			<div class="flxlm-form__field">
				<label for="flxlm-name">Your Name <span aria-hidden="true">*</span></label>
				<input type="text" id="flxlm-name" name="name" autocomplete="name" required />
			</div>
			<div class="flxlm-form__field">
				<label for="flxlm-business">Business Name <span aria-hidden="true">*</span></label>
				<input type="text" id="flxlm-business" name="business_name" autocomplete="organization" required />
			</div>
		</div>

		<div class="flxlm-form__row flxlm-form__row--two">
			<div class="flxlm-form__field">
				<label for="flxlm-email">Email <span aria-hidden="true">*</span></label>
				<input type="email" id="flxlm-email" name="email" autocomplete="email" required />
			</div>
			<div class="flxlm-form__field">
				<label for="flxlm-phone">Phone</label>
				<input type="tel" id="flxlm-phone" name="phone" autocomplete="tel" />
			</div>
		</div>

		<div class="flxlm-form__row">
			<button type="submit" class="flx-btn">Request My Free Review</button>
		</div>
	</form>
	<script>
	// Pages can be served from cache, so fill the campaign fields from the address bar too.
	(function () {
		var q = new URLSearchParams(window.location.search);
		['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) {
			var f = document.querySelector('.flxlm-form input[name="' + k + '"]');
			if (f && !f.value && q.get(k)) { f.value = q.get(k); }
		});
	})();
	</script>
	<?php
}

/**
 * Handle form submission (admin-post fallback).
 */
function flxlm_handle_contact_submit() {
	if ( ! isset( $_POST['flxlm_form_nonce'] ) || ! wp_verify_nonce( $_POST['flxlm_form_nonce'], 'flxlm_contact_form' ) ) {
		wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
	}

	// The form asks for one name field. Pages served from cache before 2026-11-21
	// may still post first_name/last_name and the longer question set, so accept both.
	$full_name = trim( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) );
	if ( '' === $full_name ) {
		$full_name = trim( sanitize_text_field( wp_unslash( ( $_POST['first_name'] ?? '' ) . ' ' . ( $_POST['last_name'] ?? '' ) ) ) );
	}
	$name_parts = preg_split( '/\s+/', $full_name, 2 );

	$data = array(
		'name'          => $full_name,
		'first_name'    => $name_parts[0] ?? '',
		'last_name'     => $name_parts[1] ?? '',
		'email'         => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
		'business_name' => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
		'interest'      => sanitize_text_field( wp_unslash( $_POST['interest'] ?? '' ) ),
		'holding_back'  => sanitize_textarea_field( wp_unslash( $_POST['holding_back'] ?? '' ) ),
		'business_type' => sanitize_text_field( wp_unslash( $_POST['business_type'] ?? '' ) ),
		'annual_spend'  => sanitize_text_field( wp_unslash( $_POST['annual_spend'] ?? '' ) ),
		'message'       => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
		'source_page'   => sanitize_text_field( wp_unslash( $_POST['source_page'] ?? '' ) ),
		'utm_source'    => sanitize_text_field( wp_unslash( $_POST['utm_source'] ?? '' ) ),
		'utm_medium'    => sanitize_text_field( wp_unslash( $_POST['utm_medium'] ?? '' ) ),
		'utm_campaign'  => sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ?? '' ) ),
	);

	// Only accept a spend answer from the list we offered.
	if ( ! in_array( $data['annual_spend'], flxlm_form_spend_options(), true ) ) {
		$data['annual_spend'] = '';
	}

	// The browser enforces the required fields; check again here for anyone who bypasses it.
	if ( '' === $data['name'] || ! is_email( $data['email'] ) ) {
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/contact/' );
		wp_safe_redirect( add_query_arg( 'contact', 'missing', remove_query_arg( 'contact', $back ) ) . '#review' );
		exit;
	}

	// Every lead goes to the assigner, who picks a rep and replies to all. Scott stays copied.
	$to      = flxlm_get_lead_assigner();
	$who     = '' !== $data['business_name'] ? $data['business_name'] : $data['name'];
	$source  = '' !== $data['utm_source'] ? $data['utm_source'] : 'direct';
	$subject = 'New lead to assign: ' . $who . ' (' . $source . ')';
	$headers = array(
		'Cc: ' . get_option( 'admin_email' ),
		'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
	);

	$body  = "Please assign this lead to a rep and reply-all with who has it.\n";
	$body .= "The form only asks for the basics; the rep gets the details on the first call.\n\n";
	$body .= "Name: {$data['name']}\n";
	$body .= "Business: {$data['business_name']}\n";
	$body .= "Email: {$data['email']}\n";
	$body .= "Phone: {$data['phone']}\n\n";

	// Older cached pages may still send these; include them only when filled in.
	$extras = array(
		'holding_back'  => 'Holding the business back',
		'business_type' => 'Kind of business',
		'annual_spend'  => 'Annual marketing spend',
		'interest'      => 'Interest',
		'message'       => 'Message',
	);
	foreach ( $extras as $key => $label ) {
		if ( '' !== $data[ $key ] ) {
			$body .= "{$label}: {$data[ $key ]}\n";
		}
	}

	$body .= "Source page: {$data['source_page']}\n";
	$body .= "UTM source: {$data['utm_source']}\n";
	$body .= "UTM medium: {$data['utm_medium']}\n";
	$body .= "UTM campaign: {$data['utm_campaign']}\n";

	wp_mail( $to, $subject, $body, $headers );

	// Allow plugins/integrations to hook in.
	do_action( 'flxlm_contact_submitted', $data );

	// Redirect back to contact page with success flag.
	$redirect = add_query_arg( 'contact', 'success', wp_get_referer() ? wp_get_referer() : home_url( '/contact/' ) );
	wp_safe_redirect( $redirect . '#review' );
	exit;
}
add_action( 'admin_post_flxlm_contact_submit', 'flxlm_handle_contact_submit' );
add_action( 'admin_post_nopriv_flxlm_contact_submit', 'flxlm_handle_contact_submit' );
