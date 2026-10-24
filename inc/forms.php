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

		<div class="flxlm-form__row flxlm-form__row--two">
			<div class="flxlm-form__field">
				<label for="flxlm-first-name">First Name <span aria-hidden="true">*</span></label>
				<input type="text" id="flxlm-first-name" name="first_name" required />
			</div>
			<div class="flxlm-form__field">
				<label for="flxlm-last-name">Last Name <span aria-hidden="true">*</span></label>
				<input type="text" id="flxlm-last-name" name="last_name" required />
			</div>
		</div>

		<div class="flxlm-form__row flxlm-form__row--two">
			<div class="flxlm-form__field">
				<label for="flxlm-email">Email <span aria-hidden="true">*</span></label>
				<input type="email" id="flxlm-email" name="email" required />
			</div>
			<div class="flxlm-form__field">
				<label for="flxlm-phone">Phone</label>
				<input type="tel" id="flxlm-phone" name="phone" />
			</div>
		</div>

		<div class="flxlm-form__row">
			<div class="flxlm-form__field">
				<label for="flxlm-business">Business Name</label>
				<input type="text" id="flxlm-business" name="business_name" />
			</div>
		</div>

		<div class="flxlm-form__row">
			<div class="flxlm-form__field">
				<label for="flxlm-holding-back">What is the one thing holding your business back? <span aria-hidden="true">*</span></label>
				<textarea id="flxlm-holding-back" name="holding_back" rows="4" required></textarea>
			</div>
		</div>

		<div class="flxlm-form__row flxlm-form__row--two">
			<div class="flxlm-form__field">
				<label for="flxlm-business-type">What kind of business is it?</label>
				<input type="text" id="flxlm-business-type" name="business_type" />
			</div>
			<div class="flxlm-form__field">
				<label for="flxlm-annual-spend">About how much do you spend on marketing in a year?</label>
				<select id="flxlm-annual-spend" name="annual_spend">
					<option value="">Select an option...</option>
<?php foreach ( flxlm_form_spend_options() as $spend_option ) : ?>
					<option value="<?php echo esc_attr( $spend_option ); ?>"><?php echo esc_html( $spend_option ); ?></option>
<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="flxlm-form__row">
			<div class="flxlm-form__field">
				<label for="flxlm-interest">I'm Interested In (optional)</label>
				<select id="flxlm-interest" name="interest">
					<option value="">Select an option...</option>
					<option value="radio">Radio Advertising</option>
					<option value="digital">Digital Marketing</option>
					<option value="events">Event Marketing</option>
					<option value="content">Content Marketing</option>
					<option value="multi">Multi-Channel Package</option>
					<option value="other">Something Else</option>
				</select>
			</div>
		</div>

		<div class="flxlm-form__row">
			<div class="flxlm-form__field">
				<label for="flxlm-message">Anything else we should know?</label>
				<textarea id="flxlm-message" name="message" rows="5"></textarea>
			</div>
		</div>

		<div class="flxlm-form__row">
			<button type="submit" class="btn btn--primary">Request My Free Review</button>
		</div>
	</form>
	<?php
}

/**
 * Handle form submission (admin-post fallback).
 */
function flxlm_handle_contact_submit() {
	if ( ! isset( $_POST['flxlm_form_nonce'] ) || ! wp_verify_nonce( $_POST['flxlm_form_nonce'], 'flxlm_contact_form' ) ) {
		wp_die( 'Security check failed.', 'Error', array( 'response' => 403 ) );
	}

	$data = array(
		'first_name'    => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
		'last_name'     => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
		'email'         => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
		'business_name' => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
		'interest'      => sanitize_text_field( wp_unslash( $_POST['interest'] ?? '' ) ),
		'holding_back'  => sanitize_textarea_field( wp_unslash( $_POST['holding_back'] ?? '' ) ),
		'business_type' => sanitize_text_field( wp_unslash( $_POST['business_type'] ?? '' ) ),
		'annual_spend'  => sanitize_text_field( wp_unslash( $_POST['annual_spend'] ?? '' ) ),
		'message'       => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
		'source_page'   => sanitize_text_field( wp_unslash( $_POST['source_page'] ?? '' ) ),
	);

	// Only accept a spend answer from the list we offered.
	if ( ! in_array( $data['annual_spend'], flxlm_form_spend_options(), true ) ) {
		$data['annual_spend'] = '';
	}

	// The browser enforces the required fields; check again here for anyone who bypasses it.
	if ( '' === $data['holding_back'] || '' === $data['first_name'] || '' === $data['last_name'] || ! is_email( $data['email'] ) ) {
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/contact/' );
		wp_safe_redirect( add_query_arg( 'contact', 'missing', remove_query_arg( 'contact', $back ) ) . '#review' );
		exit;
	}

	// Send notification email.
	$to = get_option( 'admin_email' );
	$subject = 'New Free Marketing Review Request: ' . $data['first_name'] . ' ' . $data['last_name'];
	$body  = "Holding the business back: {$data['holding_back']}\n";
	$body .= "Kind of business: {$data['business_type']}\n";
	$body .= "Annual marketing spend: {$data['annual_spend']}\n\n";
	$body .= "Name: {$data['first_name']} {$data['last_name']}\n";
	$body .= "Email: {$data['email']}\n";
	$body .= "Phone: {$data['phone']}\n";
	$body .= "Business: {$data['business_name']}\n";
	$body .= "Interest: {$data['interest']}\n";
	$body .= "Source: {$data['source_page']}\n\n";
	$body .= "Message:\n{$data['message']}\n";

	wp_mail( $to, $subject, $body );

	// Allow plugins/integrations to hook in.
	do_action( 'flxlm_contact_submitted', $data );

	// Redirect back to contact page with success flag.
	$redirect = add_query_arg( 'contact', 'success', wp_get_referer() ? wp_get_referer() : home_url( '/contact/' ) );
	wp_safe_redirect( $redirect . '#review' );
	exit;
}
add_action( 'admin_post_flxlm_contact_submit', 'flxlm_handle_contact_submit' );
add_action( 'admin_post_nopriv_flxlm_contact_submit', 'flxlm_handle_contact_submit' );
