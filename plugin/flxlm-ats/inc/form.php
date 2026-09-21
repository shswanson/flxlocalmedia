<?php
/**
 * The apply form, and the handler for submissions made on this site.
 *
 * This replaces the mailto: link that was the entire "How to Apply" section on
 * every job posting. A mailto: costs nothing to operate and converts badly:
 * it requires a working mail client, it gives the applicant no confirmation,
 * and it produces no record anyone can count.
 *
 * FIELD COUNT IS THE CONVERSION LEVER, so the required set is deliberately
 * five: name, email, resume, and how they heard about us. Everything else is
 * optional. For on-air and reporter roles the optional "links" field matters
 * more than a cover letter, because an audio reel or a set of clips is the real
 * portfolio in this industry.
 *
 * WHY THERE IS NO NONCE REQUIREMENT
 *
 * This form is rendered on pages that sit behind Cloudflare. A WordPress nonce
 * baked into a cached page is a hazard: every visitor gets the same token, and
 * once the page has been cached longer than the nonce lifetime, every genuine
 * applicant's submission is rejected with a security error they cannot act on.
 * Silently losing applications is the worst outcome this project can produce,
 * so correctness here beats ceremony.
 *
 * That is an acceptable trade because cross-site request forgery is not a
 * meaningful threat model for this endpoint. CSRF matters when an attacker can
 * make a logged-in victim perform a privileged action; there is no victim and
 * no privilege here, only an anonymous stranger submitting a job application,
 * which they can already do by visiting the page. The threat that IS real is
 * automated spam, and that is handled by the three checks below, which a nonce
 * would not have caught anyway:
 *
 *   honeypot     a field a human never sees and never fills
 *   dwell time   a form returned faster than a person can type is not a person
 *   rate limit   see flxlm_ats_check_rate_limit()
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/** Fastest a human could plausibly complete the form, in seconds. */
const FLXLM_ATS_MIN_DWELL = 4;

/**
 * Render the apply form.
 *
 * Markup and class names deliberately mirror the theme's existing contact form
 * (.flxlm-form and friends) so it inherits the site's styling rather than
 * needing its own visual language.
 *
 * @param int $job_id Job posting ID, 0 for a general application.
 * @return string HTML.
 */
function flxlm_ats_render_form( $job_id = 0 ) {
	$job_id = (int) $job_id;
	$sent   = isset( $_GET['applied'] ) ? sanitize_key( wp_unslash( $_GET['applied'] ) ) : '';
	$error  = isset( $_GET['apply_error'] ) ? sanitize_text_field( wp_unslash( $_GET['apply_error'] ) ) : '';

	ob_start();

	if ( 'success' === $sent ) {
		?>
		<div class="flxlm-ats-notice flxlm-ats-notice--success" role="status">
			<h3>Application received</h3>
			<p>Thanks. We have your application and a confirmation is on its way to your email. Someone here will be in touch.</p>
		</div>
		<?php
		return ob_get_clean();
	}
	?>

	<div class="flxlm-ats-apply" id="apply">
		<h2>Apply for this job</h2>

		<?php if ( $error ) : ?>
			<div class="flxlm-ats-notice flxlm-ats-notice--error" role="alert">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
		<?php endif; ?>

		<form class="flxlm-form flxlm-ats-form"
			method="post"
			enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

			<input type="hidden" name="action" value="flxlm_ats_apply" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>" />
			<input type="hidden" name="rendered_at" value="<?php echo esc_attr( time() ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ? get_permalink() : home_url( '/careers/' ) ); ?>" />

			<?php
			// The honeypot. Hidden from people by CSS and from screen readers by
			// aria-hidden, and explicitly removed from the tab order. Bots fill
			// every field they find; humans never see this one.
			?>
			<div class="flxlm-ats-hp" aria-hidden="true">
				<label for="flxlm-ats-website">Website</label>
				<input type="text" id="flxlm-ats-website" name="website" tabindex="-1" autocomplete="off" />
			</div>

			<div class="flxlm-form__row flxlm-form__row--two">
				<div class="flxlm-form__field">
					<label for="flxlm-ats-first">First Name <span aria-hidden="true">*</span></label>
					<input type="text" id="flxlm-ats-first" name="first_name" autocomplete="given-name" required />
				</div>
				<div class="flxlm-form__field">
					<label for="flxlm-ats-last">Last Name <span aria-hidden="true">*</span></label>
					<input type="text" id="flxlm-ats-last" name="last_name" autocomplete="family-name" required />
				</div>
			</div>

			<div class="flxlm-form__row flxlm-form__row--two">
				<div class="flxlm-form__field">
					<label for="flxlm-ats-email">Email <span aria-hidden="true">*</span></label>
					<input type="email" id="flxlm-ats-email" name="email" autocomplete="email" required />
				</div>
				<div class="flxlm-form__field">
					<label for="flxlm-ats-phone">Phone</label>
					<input type="tel" id="flxlm-ats-phone" name="phone" autocomplete="tel" />
				</div>
			</div>

			<div class="flxlm-form__row">
				<div class="flxlm-form__field">
					<label for="flxlm-ats-resume">Resume <span aria-hidden="true">*</span></label>
					<input type="file" id="flxlm-ats-resume" name="resume"
						accept=".pdf,.doc,.docx,.odt,.rtf,.txt" required />
					<p class="flxlm-form__hint">
						PDF or Word document, up to <?php echo esc_html( flxlm_ats_max_upload_label() ); ?>.
					</p>
				</div>
			</div>

			<div class="flxlm-form__row">
				<div class="flxlm-form__field">
					<label for="flxlm-ats-source">How did you hear about this job? <span aria-hidden="true">*</span></label>
					<select id="flxlm-ats-source" name="source" required>
						<option value="">Choose one...</option>
						<?php foreach ( flxlm_ats_sources() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="flxlm-form__row">
				<div class="flxlm-form__field">
					<label for="flxlm-ats-links">Links to your work</label>
					<textarea id="flxlm-ats-links" name="links" rows="3"
						placeholder="An audio reel, clips you have written, a portfolio, LinkedIn — one per line."></textarea>
					<p class="flxlm-form__hint">Optional, but for on-air and reporting roles this is the part we look at first.</p>
				</div>
			</div>

			<div class="flxlm-form__row">
				<div class="flxlm-form__field">
					<label for="flxlm-ats-message">Anything you want us to know</label>
					<textarea id="flxlm-ats-message" name="message" rows="5"></textarea>
				</div>
			</div>

			<?php
			// Salary EXPECTATION only. New York prohibits asking an applicant
			// for their current or previous pay, so no such field exists here.
			?>
			<div class="flxlm-form__row">
				<div class="flxlm-form__field">
					<label for="flxlm-ats-salary">Pay you are looking for</label>
					<input type="text" id="flxlm-ats-salary" name="salary_expectation"
						placeholder="Optional — a number or a range is fine" />
				</div>
			</div>

			<div class="flxlm-form__row">
				<button type="submit" class="btn btn--primary">Submit Application</button>
			</div>

			<?php
			/*
			 * Catch an over-size resume in the browser, before the upload.
			 *
			 * The server-side catch exists and works, but it costs the applicant
			 * a full upload of a file that was always going to be refused, over
			 * whatever connection they are on. Telling them the moment they pick
			 * the file is the difference between a small correction and giving up.
			 *
			 * Progressive enhancement: with JavaScript off the form still works
			 * and the server still explains the problem.
			 */
			?>
			<script>
			(function () {
				var form = document.currentScript.closest('form');
				if (!form) { return; }
				var input = form.querySelector('input[type="file"][name="resume"]');
				if (!input) { return; }

				var maxBytes = <?php echo (int) flxlm_ats_max_upload_bytes(); ?>;
				var maxLabel = <?php echo wp_json_encode( flxlm_ats_max_upload_label() ); ?>;

				function check() {
					if (!input.files || !input.files.length) { return true; }
					if (input.files[0].size <= maxBytes) {
						input.setCustomValidity('');
						return true;
					}
					input.setCustomValidity(
						'That file is ' + (input.files[0].size / 1048576).toFixed(1) +
						' MB. Please choose one under ' + maxLabel + '.'
					);
					input.reportValidity();
					return false;
				}

				input.addEventListener('change', check);
				form.addEventListener('submit', function (e) {
					if (!check()) { e.preventDefault(); }
				});
			})();
			</script>

			<p class="flxlm-ats-eeo-note">
				<em>FLX Local Media is an equal opportunity employer. Your application is kept private and is
				seen only by the people hiring for this role.</em>
			</p>
		</form>
	</div>

	<?php
	return ob_get_clean();
}

/**
 * Handle a submission made on this site.
 */
function flxlm_ats_handle_apply() {
	/*
	 * Before anything else: did PHP throw this request away?
	 *
	 * An upload bigger than post_max_size does not arrive truncated, it does not
	 * arrive at all. $_POST and $_FILES are both empty, which includes the
	 * redirect_to field, so even knowing where to send the applicant back to
	 * takes a fallback. Without this branch the applicant sees a bare page, no
	 * error and no confirmation, and nothing was recorded. That is the silent
	 * loss this whole system exists to stop, arriving through the one door
	 * nobody watches.
	 */
	if ( flxlm_ats_post_was_discarded() ) {
		$referer = wp_get_referer();
		flxlm_ats_apply_fail(
			'Your resume was larger than this site accepts, so the form could not be sent. '
				. 'Please attach a file under ' . flxlm_ats_max_upload_label()
				. ' and try again, or email it to us and we will add it for you.',
			$referer ? $referer : home_url( '/careers/' )
		);
	}

	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/careers/' );

	// --- Bot checks first: they are free, and they run before the file. ---

	if ( ! empty( $_POST['website'] ) ) {
		// A filled honeypot. Redirect to the success page rather than showing an
		// error: a bot that is told it failed simply adapts, whereas one that
		// believes it succeeded goes away.
		wp_safe_redirect( add_query_arg( 'applied', 'success', $redirect ) . '#apply' );
		exit;
	}

	$rendered_at = isset( $_POST['rendered_at'] ) ? (int) $_POST['rendered_at'] : 0;
	if ( $rendered_at && ( time() - $rendered_at ) < FLXLM_ATS_MIN_DWELL ) {
		wp_safe_redirect( add_query_arg( 'applied', 'success', $redirect ) . '#apply' );
		exit;
	}

	$limited = flxlm_ats_check_rate_limit();
	if ( is_wp_error( $limited ) ) {
		flxlm_ats_apply_fail( $limited->get_error_message(), $redirect );
	}

	// --- Now the real work. ---

	$fields = flxlm_ats_validate_fields( wp_unslash( $_POST ) );
	if ( is_wp_error( $fields ) ) {
		flxlm_ats_apply_fail( $fields->get_error_message(), $redirect );
	}

	$resume = flxlm_ats_store_resume( isset( $_FILES['resume'] ) ? $_FILES['resume'] : null );
	if ( is_wp_error( $resume ) ) {
		flxlm_ats_apply_fail( $resume->get_error_message(), $redirect );
	}

	$job = flxlm_ats_resolve_job( isset( $_POST['job_id'] ) ? (int) $_POST['job_id'] : 0 );

	$application_id = flxlm_ats_create_application(
		array(
			'fields'      => $fields,
			'job_id'      => $job['id'],
			'job_title'   => $job['title'],
			'resume'      => $resume,
			'entered_by'  => 'web',
			'source_site' => 'flxlocalmedia',
		)
	);

	if ( is_wp_error( $application_id ) ) {
		flxlm_ats_apply_fail( 'Something went wrong saving your application. Please try again.', $redirect );
	}

	wp_safe_redirect( add_query_arg( 'applied', 'success', $redirect ) . '#apply' );
	exit;
}
add_action( 'admin_post_nopriv_flxlm_ats_apply', 'flxlm_ats_handle_apply' );
add_action( 'admin_post_flxlm_ats_apply', 'flxlm_ats_handle_apply' );

/*
 * The over-limit request never reaches the handler above.
 *
 * When PHP discards a POST for exceeding post_max_size it discards the action
 * field along with everything else, so admin-post.php has no action to dispatch
 * on and falls through to its bare hook. Without catching that, an applicant
 * whose resume is slightly too large gets a raw PHP warning and nothing else.
 *
 * These two hooks are the only place that request can still be caught.
 */
add_action( 'admin_post_nopriv', 'flxlm_ats_catch_discarded_post' );
add_action( 'admin_post', 'flxlm_ats_catch_discarded_post' );

/**
 * Turn a discarded over-size POST into an explanation.
 */
function flxlm_ats_catch_discarded_post() {
	if ( ! flxlm_ats_post_was_discarded() ) {
		return;
	}

	$referer = wp_get_referer();
	flxlm_ats_apply_fail(
		'Your resume was larger than this site accepts, so the form could not be sent. '
			. 'Please attach a file under ' . flxlm_ats_max_upload_label()
			. ' and try again, or email it to us and we will add it for you.',
		$referer ? $referer : home_url( '/careers/' )
	);
}

/**
 * Bounce back to the form with a message the applicant can act on.
 *
 * @param string $message  Human-readable problem.
 * @param string $redirect Where the form lives.
 * @return void Exits.
 */
function flxlm_ats_apply_fail( $message, $redirect ) {
	wp_safe_redirect(
		add_query_arg( 'apply_error', rawurlencode( $message ), $redirect ) . '#apply'
	);
	exit;
}

/**
 * Minimal styles for the parts the theme has no opinion about.
 */
function flxlm_ats_enqueue_styles() {
	if ( is_admin() ) {
		return;
	}

	wp_register_style( 'flxlm-ats', false, array(), FLXLM_ATS_VERSION );
	wp_enqueue_style( 'flxlm-ats' );

	$css = '
		.flxlm-ats-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
		.flxlm-ats-apply{margin:2.5rem 0;padding:2rem;background:#f7f7f8;border-radius:8px}
		.flxlm-ats-apply h2{margin-top:0}
		.flxlm-ats-notice{padding:1rem 1.25rem;border-radius:6px;margin-bottom:1.25rem}
		.flxlm-ats-notice--success{background:#e7f6ec;border-left:4px solid #1e7e34}
		.flxlm-ats-notice--error{background:#fdecea;border-left:4px solid #c62828}
		.flxlm-ats-notice h3{margin:0 0 .35rem}
		.flxlm-ats-notice p{margin:0}
		.flxlm-form__hint{font-size:.875rem;color:#555;margin:.35rem 0 0}
		.flxlm-ats-eeo-note{font-size:.8125rem;color:#555;margin-top:1rem}
		.flxlm-ats-apply-cta{margin:1.5rem 0}
	';

	wp_add_inline_style( 'flxlm-ats', $css );
}
add_action( 'wp_enqueue_scripts', 'flxlm_ats_enqueue_styles' );

/**
 * Shortcode so a posting can place the form anywhere in its body copy.
 *
 * [flxlm_apply] renders the form for the current posting.
 */
add_shortcode(
	'flxlm_apply',
	function ( $atts ) {
		$atts   = shortcode_atts( array( 'job' => 0 ), $atts, 'flxlm_apply' );
		$job_id = (int) $atts['job'];
		if ( ! $job_id && is_singular( 'flxlm_job' ) ) {
			$job_id = get_the_ID();
		}
		return flxlm_ats_render_form( $job_id );
	}
);
