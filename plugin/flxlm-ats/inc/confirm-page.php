<?php
/**
 * The login-free pages a hiring manager reaches from their email.
 *
 * Two routes, both driven by a signed token (see inc/tokens.php):
 *
 *   ?flxlm_ats=resume    shows the resume
 *   ?flxlm_ats=confirm   asks "are you sure", and only then performs the move
 *
 * THE CONFIRMATION STEP IS NOT POLITENESS, IT IS CORRECTNESS.
 *
 * Mail security products fetch the URLs inside incoming messages before anyone
 * reads them. Microsoft Defender Safe Links, Google Workspace link checking and
 * ordinary antivirus link prefetchers all do this. If clicking "Not Selected"
 * in an email performed the rejection, the scanner would perform it first, on
 * every candidate, the instant the mail arrived. The manager would then open a
 * message whose decisions had already been made for them, and the EEO record
 * would show interviews and rejections that never happened.
 *
 * So the GET is inert. It renders a page with one button. The change happens on
 * the POST from that button, which no scanner will ever submit.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dispatch the signed-link routes.
 */
function flxlm_ats_handle_signed_routes() {
	/*
	 * $_REQUEST, not $_GET.
	 *
	 * The link in the email is a GET carrying these in the query string, but the
	 * confirmation button posts them in the body. Reading only $_GET would make
	 * the POST work purely by accident, because a form with no action attribute
	 * happens to inherit the current query string. Anything that later rewrites
	 * the URL, strips the query, or moves the form would break the confirm step
	 * silently: the manager would click Confirm, get the ordinary home page, and
	 * the applicant would never be moved.
	 */
	$route = isset( $_REQUEST['flxlm_ats'] ) ? sanitize_key( wp_unslash( $_REQUEST['flxlm_ats'] ) ) : '';
	if ( '' === $route ) {
		return;
	}

	$application_id = isset( $_REQUEST['application'] ) ? (int) $_REQUEST['application'] : 0;
	$token          = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';

	if ( 'resume' === $route ) {
		flxlm_ats_route_resume( $application_id, $token );
		return;
	}

	if ( 'confirm' === $route ) {
		flxlm_ats_route_confirm( $application_id, $token );
	}
}
add_action( 'template_redirect', 'flxlm_ats_handle_signed_routes' );

/**
 * Serve a resume to a holder of a valid signed link.
 *
 * @param int    $application_id Application ID.
 * @param string $token          Signed token.
 * @return void Exits.
 */
function flxlm_ats_route_resume( $application_id, $token ) {
	// A logged-in person with the capability does not need a token.
	$authorised = current_user_can( 'flxlm_view_applications' );

	if ( ! $authorised ) {
		$verified = flxlm_ats_verify_token( $application_id, 'view', $token );
		if ( is_wp_error( $verified ) ) {
			flxlm_ats_simple_page( 'Link not valid', $verified->get_error_message() );
		}
		$authorised = true;
	}

	$served = flxlm_ats_serve_resume( $application_id );

	// serve_resume() exits on success, so reaching here means it failed.
	if ( is_wp_error( $served ) ) {
		flxlm_ats_simple_page( 'Resume unavailable', $served->get_error_message() );
	}

	exit;
}

/**
 * The confirm-then-act page for a stage decision.
 *
 * @param int    $application_id Application ID.
 * @param string $token          Signed token.
 * @return void Exits.
 */
function flxlm_ats_route_confirm( $application_id, $token ) {
	$stage = isset( $_REQUEST['stage'] ) ? sanitize_key( wp_unslash( $_REQUEST['stage'] ) ) : '';

	if ( ! flxlm_ats_is_movable_stage( $stage ) ) {
		flxlm_ats_simple_page( 'Link not valid', 'That link does not name a stage we recognise.' );
	}

	$verified  = flxlm_ats_verify_token( $application_id, $stage, $token );
	$nonce_key = 'flxlm_ats_confirm_' . $application_id . '_' . $stage;

	// A logged-in manager without a valid token (a stale or forwarded link) may
	// still reach the GET "are you sure" page: rendering it changes nothing.
	if ( is_wp_error( $verified ) && ! current_user_can( 'flxlm_manage_applications' ) ) {
		flxlm_ats_simple_page( 'Link not valid', $verified->get_error_message() );
	}

	$application = flxlm_ats_get_application( $application_id );
	if ( ! $application ) {
		flxlm_ats_simple_page( 'Not found', 'That application no longer exists.' );
	}

	$name  = flxlm_ats_applicant_name( $application_id );
	$job   = flxlm_ats_job_title( $application_id );
	$label = flxlm_ats_stage_label( $stage );

	// Not hired is the one email button whose target requires data (a close
	// reason — see inc/stages.php's required fields). Phone screen, the other
	// email button, requires nothing, so this branch only ever adds the
	// dropdown when it is actually needed.
	$needs_reason = in_array( 'close_reason', flxlm_ats_stage_required_fields( $stage ), true );

	// The POST is the only thing that changes anything.
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		/*
		 * A valid signed token proves this POST came from the button on our own
		 * confirm page, reached via the email link: that is the whole trust
		 * model for a logged-out request and it is CSRF-proof on its own,
		 * because a forged page cannot produce a valid HMAC.
		 *
		 * When the token does NOT verify, the earlier check already required
		 * flxlm_manage_applications to get this far. That capability alone is
		 * not enough to authorise the write: any page a logged-in manager's
		 * browser visits can silently POST here using nothing but their
		 * ordinary session cookie. So this path additionally requires a
		 * WordPress nonce, proving the POST originated from a form THIS SITE
		 * rendered for THIS user in THIS session, the same way
		 * inc/admin-list.php's flxlm_ats_handle_admin_stage() protects its
		 * equivalent action. Never skip both checks at once.
		 */
		if ( is_wp_error( $verified ) ) {
			$nonce = isset( $_POST['_flxlm_ats_nonce'] ) ? wp_unslash( $_POST['_flxlm_ats_nonce'] ) : '';
			if ( ! current_user_can( 'flxlm_manage_applications' ) || ! wp_verify_nonce( $nonce, $nonce_key ) ) {
				flxlm_ats_simple_page( 'Link not valid', $verified->get_error_message() );
			}
		}

		$args = array( 'author_email' => is_user_logged_in() ? wp_get_current_user()->user_email : '' );
		if ( $needs_reason ) {
			$args['close_reason'] = isset( $_POST['close_reason'] ) ? sanitize_key( wp_unslash( $_POST['close_reason'] ) ) : '';
		}

		$result = flxlm_ats_set_stage( $application_id, $stage, 'signed-link', $args );

		if ( is_wp_error( $result ) ) {
			if ( 'flxlm_ats_missing_fields' === $result->get_error_code() ) {
				flxlm_ats_render_confirm_form( $application_id, $stage, $token, $name, $job, $label, $needs_reason, 'Please choose a reason before continuing.' );
				exit;
			}
			flxlm_ats_simple_page( 'Could not update', $result->get_error_message() );
		}

		flxlm_ats_simple_page(
			'Done',
			esc_html( $name ) . ' is now at <strong>' . esc_html( $label ) . '</strong> for '
				. esc_html( $job ) . '.',
			true,
			false
		);
	}

	// The GET just asks.
	flxlm_ats_render_confirm_form( $application_id, $stage, $token, $name, $job, $label, $needs_reason );
}

/**
 * Render the "are you sure" form for a signed stage-move link.
 *
 * @param int    $application_id Application ID.
 * @param string $stage          Target stage.
 * @param string $token          Signed token, re-emitted in the form.
 * @param string $name           Applicant display name.
 * @param string $job            Job title.
 * @param string $label          Target stage label.
 * @param bool   $needs_reason   Whether to show the close-reason dropdown.
 * @param string $error          Optional validation error to show.
 */
function flxlm_ats_render_confirm_form( $application_id, $stage, $token, $name, $job, $label, $needs_reason, $error = '' ) {
	$already = flxlm_ats_stage_label( get_post_status( $application_id ) );
	$chips   = flxlm_ats_chip_html( 'Currently: ' . $already, '#8a8f98' );

	ob_start();
	?>
	<?php echo flxlm_ats_candidate_header_html( $name, $job, $chips ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from already-escaped values, see helper. ?>

	<?php if ( $error ) : ?>
		<p class="flxlm-ats-alert flxlm-ats-alert--error"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>

	<p>Move this applicant to <strong><?php echo esc_html( $label ); ?></strong>?</p>

	<form method="post" class="flxlm-ats-form">
		<input type="hidden" name="flxlm_ats" value="confirm" />
		<input type="hidden" name="application" value="<?php echo esc_attr( $application_id ); ?>" />
		<input type="hidden" name="stage" value="<?php echo esc_attr( $stage ); ?>" />
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />
		<?php wp_nonce_field( 'flxlm_ats_confirm_' . $application_id . '_' . $stage, '_flxlm_ats_nonce', false ); ?>

		<?php if ( $needs_reason ) : ?>
			<p class="flxlm-ats-fieldlabel">Reason</p>
			<select name="close_reason" required class="flxlm-ats-select">
				<option value="">Choose one...</option>
				<?php foreach ( flxlm_ats_close_reasons() as $key => $reason_label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $reason_label ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>

		<p style="margin-top:1.25rem"><button type="submit" class="flxlm-ats-btn">Yes, move to <?php echo esc_html( $label ); ?></button></p>
	</form>

	<?php if ( get_post_meta( $application_id, '_flxlm_resume_file', true ) ) : ?>
		<p class="flxlm-ats-secondary">
			<a href="<?php echo esc_url( flxlm_ats_resume_url( $application_id ) ); ?>">Read the resume first</a>
		</p>
	<?php endif; ?>
	<?php

	flxlm_ats_simple_page( 'Confirm', ob_get_clean(), false, false );
}

/**
 * A candidate header: initials avatar, name as a large title, job as
 * secondary text. The one recurring block every signed page in this plugin
 * (the feedback page, the confirm page) opens with, so a person clicking in
 * from email always lands on the same visual shape wp-admin and the hub
 * both also use, per the v1.1 design direction: "one glance should answer
 * where is this person."
 *
 * @param string $name  Candidate display name.
 * @param string $job   Job title.
 * @param string $chips Optional extra HTML (already-built, e.g. a stage
 *                        badge) rendered under the job line. Trusted markup;
 *                        every current caller builds it from static markup
 *                        plus values it already escaped.
 * @return string HTML.
 */
function flxlm_ats_candidate_header_html( $name, $job, $chips = '' ) {
	$initials = flxlm_ats_initials( $name );
	ob_start();
	?>
	<div class="flxlm-ats-header">
		<div class="flxlm-ats-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
		<div class="flxlm-ats-header__text">
			<h1 class="flxlm-ats-title"><?php echo esc_html( $name ); ?></h1>
			<p class="flxlm-ats-subtitle"><?php echo esc_html( $job ); ?></p>
			<?php if ( $chips ) : ?>
				<p class="flxlm-ats-chips"><?php echo $chips; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see param doc. ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Up to two initials from a display name, for the avatar circle.
 *
 * @param string $name Display name.
 * @return string 1-2 uppercase letters, or '?' for an empty name.
 */
function flxlm_ats_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$parts = array_filter( $parts );
	if ( ! $parts ) {
		return '?';
	}
	$first = mb_substr( reset( $parts ), 0, 1 );
	$last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';
	return mb_strtoupper( $first . $last );
}

/**
 * A small capsule badge — the same visual language as a stage or source chip
 * anywhere on a signed page.
 *
 * @param string $text  Label.
 * @param string $color Accent color (hex).
 * @return string HTML.
 */
function flxlm_ats_chip_html( $text, $color = '#1E3A5F' ) {
	return sprintf(
		'<span class="flxlm-ats-chip" style="--flxlm-chip-color:%s">%s</span>',
		esc_attr( $color ),
		esc_html( $text )
	);
}

/**
 * Render a small standalone page and stop.
 *
 * Deliberately not a theme template: these pages are reached from email by
 * people who are not logged in, and they must render identically regardless of
 * which theme is active or whether the theme is mid-deploy.
 *
 * @param string $title    Page heading.
 * @param string $body     HTML body.
 * @param bool   $success  Style as a success.
 * @param bool   $escape   Escape the body (false when the caller built HTML).
 *                          $escape=false is NOT a place to run untrusted or
 *                          user-supplied text through: every current caller
 *                          builds this string itself, from static markup plus
 *                          values it already ran through esc_html()/esc_attr()/
 *                          esc_textarea() at the point each was interpolated.
 *                          wp_kses_post() used to run over it here too, on the
 *                          theory that a second pass was "extra safe" — but
 *                          wp_kses_post()'s allowed-tag list is scoped to post
 *                          CONTENT and does not include <form>, <input>,
 *                          <select> or <option>, so it silently stripped the
 *                          confirm-page and feedback forms down to a bare,
 *                          unwrapped submit button with no fields: the
 *                          click-to-decide flow this whole file exists for
 *                          could not actually be submitted. Trust the caller
 *                          instead of re-sanitizing markup it already built
 *                          safely.
 * @return void Exits.
 */
function flxlm_ats_simple_page( $title, $body, $success = false, $escape = true ) {
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	status_header( 200 );

	$accent = $success ? '#1e7e34' : '#1E3A5F';
	// The page-level title above the card is only shown when the caller has
	// not already opened the body with its own candidate header
	// (flxlm_ats_candidate_header_html()) — a plain h1 duplicating the avatar
	// block's own <h1> would be two titles stacked on top of each other.
	$show_page_title = ( false === strpos( (string) $body, 'flxlm-ats-header' ) );
	?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $title ); ?>: FLX Local Media</title>
	<style>
		:root{
			--flxlm-navy:#1E3A5F; --flxlm-navy2:#16304e; --flxlm-accent:<?php echo esc_attr( $accent ); ?>;
			--flxlm-ink:#22262b; --flxlm-muted:#5a5a5a; --flxlm-line:#e6e1d8; --flxlm-cream:#faf6f0;
			--flxlm-radius:14px;
		}
		*{box-sizing:border-box}
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
			background:var(--flxlm-cream);margin:0;padding:2rem 1rem;color:var(--flxlm-ink);line-height:1.55;
			-webkit-font-smoothing:antialiased}
		.card{max-width:34rem;margin:2.5rem auto;background:#fff;border-radius:var(--flxlm-radius);
			padding:1.75rem 1.75rem 2rem;box-shadow:0 1px 2px rgba(20,20,30,.04),0 8px 24px rgba(20,20,30,.07);
			border-top:4px solid var(--flxlm-accent)}
		@media (max-width:460px){ .card{padding:1.25rem 1.1rem 1.6rem;margin:1.25rem auto} body{padding:1rem .75rem} }
		h1{margin:0 0 1rem;font-size:1.3rem;letter-spacing:-.01em}
		.flxlm-ats-btn{display:inline-block;background:var(--flxlm-accent);color:#fff;border:0;
			border-radius:10px;padding:.85rem 1.5rem;font-size:1rem;font-weight:600;cursor:pointer;margin-top:.5rem;
			box-shadow:0 1px 2px rgba(20,20,30,.12);transition:filter .1s}
		.flxlm-ats-btn:hover{filter:brightness(.94)}
		.flxlm-ats-btn:active{transform:translateY(1px)}
		.flxlm-ats-current{color:var(--flxlm-muted);font-size:.9rem}
		.flxlm-ats-secondary{margin-top:1.5rem;font-size:.9rem;padding-top:1rem;border-top:1px solid var(--flxlm-line)}
		a{color:var(--flxlm-accent)}

		/* Candidate header: avatar + name + job, the block every signed page opens with. */
		.flxlm-ats-header{display:flex;align-items:flex-start;gap:.9rem;margin-bottom:1.4rem;
			padding-bottom:1.2rem;border-bottom:1px solid var(--flxlm-line)}
		.flxlm-ats-avatar{flex:none;width:3rem;height:3rem;border-radius:999px;background:var(--flxlm-navy);
			color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.05rem;
			letter-spacing:.02em}
		.flxlm-ats-header__text{min-width:0}
		.flxlm-ats-title{margin:0;font-size:1.3rem;font-weight:700;letter-spacing:-.01em;line-height:1.2}
		.flxlm-ats-subtitle{margin:.15rem 0 0;color:var(--flxlm-muted);font-size:.95rem}
		.flxlm-ats-chips{margin:.5rem 0 0;display:flex;gap:.4rem;flex-wrap:wrap}
		.flxlm-ats-chip{display:inline-block;background:color-mix(in srgb, var(--flxlm-chip-color,#1E3A5F) 12%, #fff);
			color:var(--flxlm-chip-color,#1E3A5F);border:1px solid color-mix(in srgb, var(--flxlm-chip-color,#1E3A5F) 30%, #fff);
			border-radius:999px;padding:.15rem .65rem;font-size:.78rem;font-weight:600}

		/* Alerts (errors, informational notices). */
		.flxlm-ats-alert{border-radius:10px;padding:.7rem .9rem;font-size:.92rem;margin:0 0 1rem}
		.flxlm-ats-alert--error{background:#fdecec;border:1px solid #f3c8c6;color:#8a2c25}
		.flxlm-ats-alert--note{background:#fdf6ea;border:1px solid #ecd9ad;color:#6b5620}

		/* Form fields, shared by the confirm page and the feedback page. */
		.flxlm-ats-fieldlabel{margin:1.1rem 0 .4rem;font-weight:600;font-size:.92rem}
		.flxlm-ats-fieldlabel__hint{color:var(--flxlm-muted);font-weight:400}
		.flxlm-ats-textarea,.flxlm-ats-input,.flxlm-ats-select{width:100%;font:inherit;padding:.6rem .7rem;
			border:1.5px solid var(--flxlm-line);border-radius:10px;background:#fff;color:var(--flxlm-ink)}
		.flxlm-ats-textarea:focus,.flxlm-ats-input:focus,.flxlm-ats-select:focus{outline:2px solid var(--flxlm-accent);
			outline-offset:1px;border-color:var(--flxlm-accent)}
	</style>
</head>
<body>
	<div class="card">
		<?php if ( $show_page_title ) : ?><h1><?php echo esc_html( $title ); ?></h1><?php endif; ?>
		<?php echo $escape ? esc_html( $body ) : $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see the $escape param doc above: every $escape=false caller builds this from static markup plus values it already escaped itself. ?>
	</div>
</body>
</html>
	<?php
	exit;
}

/**
 * The admin-side resume viewer, for someone logged in.
 */
function flxlm_ats_admin_resume_screen() {
	if ( ! current_user_can( 'flxlm_view_applications' ) ) {
		wp_die( 'You do not have permission to view applications.', 'Not allowed', array( 'response' => 403 ) );
	}

	$application_id = isset( $_GET['application'] ) ? (int) $_GET['application'] : 0;

	check_admin_referer( 'flxlm_ats_resume_' . $application_id );

	$mode = ( isset( $_GET['mode'] ) && 'download' === $_GET['mode'] ) ? 'download' : 'inline';

	$served = flxlm_ats_serve_resume( $application_id, $mode );
	if ( is_wp_error( $served ) ) {
		wp_die( esc_html( $served->get_error_message() ), 'Resume unavailable', array( 'response' => 404 ) );
	}
	exit;
}
