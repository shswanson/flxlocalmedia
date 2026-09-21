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

	if ( ! flxlm_ats_is_stage( $stage ) ) {
		flxlm_ats_simple_page( 'Link not valid', 'That link does not name a stage we recognise.' );
	}

	$verified = flxlm_ats_verify_token( $application_id, $stage, $token );
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

	// The POST is the only thing that changes anything.
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		$result = flxlm_ats_set_stage( $application_id, $stage, 'signed-link' );

		if ( is_wp_error( $result ) ) {
			flxlm_ats_simple_page( 'Could not update', $result->get_error_message() );
		}

		flxlm_ats_simple_page(
			'Done',
			esc_html( $name ) . ' is now at <strong>' . esc_html( $label ) . '</strong> for '
				. esc_html( $job ) . '.',
			true
		);
	}

	// The GET just asks.
	$already = flxlm_ats_stage_label( $application['stage'] );

	ob_start();
	?>
	<p>
		<strong><?php echo esc_html( $name ); ?></strong><br />
		<?php echo esc_html( $job ); ?><br />
		<span class="flxlm-ats-current">Currently: <?php echo esc_html( $already ); ?></span>
	</p>

	<p>Move this applicant to <strong><?php echo esc_html( $label ); ?></strong>?</p>

	<form method="post">
		<input type="hidden" name="flxlm_ats" value="confirm" />
		<input type="hidden" name="application" value="<?php echo esc_attr( $application_id ); ?>" />
		<input type="hidden" name="stage" value="<?php echo esc_attr( $stage ); ?>" />
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />
		<button type="submit" class="flxlm-ats-btn">Yes, move to <?php echo esc_html( $label ); ?></button>
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
 * @return void Exits.
 */
function flxlm_ats_simple_page( $title, $body, $success = false, $escape = true ) {
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	status_header( 200 );

	$accent = $success ? '#1e7e34' : '#512DA8';
	?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $title ); ?> — FLX Local Media</title>
	<style>
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif;
			background:#f4f4f6;margin:0;padding:2rem 1rem;color:#222;line-height:1.55}
		.card{max-width:34rem;margin:3rem auto;background:#fff;border-radius:10px;
			padding:2rem;box-shadow:0 2px 14px rgba(0,0,0,.08);border-top:4px solid <?php echo esc_attr( $accent ); ?>}
		h1{margin:0 0 1rem;font-size:1.4rem}
		.flxlm-ats-btn{display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:#fff;border:0;
			border-radius:6px;padding:.85rem 1.5rem;font-size:1rem;cursor:pointer;margin-top:.5rem}
		.flxlm-ats-current{color:#666;font-size:.9rem}
		.flxlm-ats-secondary{margin-top:1.5rem;font-size:.9rem}
		a{color:<?php echo esc_attr( $accent ); ?>}
	</style>
</head>
<body>
	<div class="card">
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php echo $escape ? esc_html( $body ) : wp_kses_post( $body ); ?>
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

	$served = flxlm_ats_serve_resume( $application_id );
	if ( is_wp_error( $served ) ) {
		wp_die( esc_html( $served->get_error_message() ), 'Resume unavailable', array( 'response' => 404 ) );
	}
	exit;
}
