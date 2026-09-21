<?php
/**
 * Signed links for hiring managers.
 *
 * The people who decide on candidates are station and department managers. Most
 * of them live in email, not in wp-admin, and several do not have a login on
 * this site at all. If reviewing a candidate requires someone to find a
 * password, the review does not happen and the applications rot, which is the
 * exact failure this whole project exists to end.
 *
 * So the notification email carries signed links that work without a login: one
 * to read the resume, and one per decision. The signature is an HMAC over the
 * application, the action and an expiry, keyed on a secret unique to this site.
 *
 * TWO RULES MAKE THIS SAFE.
 *
 * 1. NOTHING MUTATES ON GET. A decision link in an email does not perform the
 *    decision. It opens a page with one button, and the change happens only
 *    when that button is POSTed. This is not ceremony: corporate mail security
 *    (Microsoft Defender Safe Links, Google Workspace link checking, antivirus
 *    link prefetchers) automatically fetches every URL in an incoming message
 *    before a human ever opens it. A bare GET "Not Selected" link would be
 *    fired by the scanner, silently rejecting candidates nobody looked at, and
 *    would corrupt the EEO record while it did so.
 *
 * 2. LINKS EXPIRE. Email lives forever in inboxes, gets forwarded, and ends up
 *    in backups. A link that works for a fortnight and then stops limits how
 *    long a leaked message stays dangerous. Resume links are shorter-lived than
 *    decision links because they expose more.
 *
 * The secret is generated once and stored in an option. It is never committed
 * and never leaves this site.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

const FLXLM_ATS_SECRET_OPTION = 'flxlm_ats_link_secret';

/** How long a decision link stays valid. */
const FLXLM_ATS_ACTION_TTL = 14 * DAY_IN_SECONDS;

/** How long a resume view link stays valid. Shorter: it exposes more. */
const FLXLM_ATS_RESUME_TTL = 3 * DAY_IN_SECONDS;

/**
 * The HMAC key for signed links.
 *
 * Generated on first use from the CSPRNG. Deliberately not derived from
 * AUTH_SALT: rotating WordPress salts (a routine security response) would
 * otherwise invalidate every outstanding manager link at once, and losing the
 * ability to action pending candidates because of an unrelated password reset
 * is a bad failure mode.
 *
 * @return string
 */
function flxlm_ats_link_secret() {
	$secret = get_option( FLXLM_ATS_SECRET_OPTION );
	if ( $secret ) {
		return $secret;
	}

	$secret = wp_generate_password( 64, true, true );
	// autoload=no: this is read on demand, not on every page load.
	add_option( FLXLM_ATS_SECRET_OPTION, $secret, '', 'no' );

	return $secret;
}

/**
 * Build a signed token.
 *
 * @param int    $application_id Application ID.
 * @param string $action         'view' or a stage key.
 * @param int    $ttl            Seconds until expiry.
 * @return string token in the form <expiry>.<hmac>
 */
function flxlm_ats_make_token( $application_id, $action, $ttl ) {
	$expires = time() + (int) $ttl;
	$payload = (int) $application_id . '|' . $action . '|' . $expires;
	$hmac    = hash_hmac( 'sha256', $payload, flxlm_ats_link_secret() );

	return $expires . '.' . $hmac;
}

/**
 * Verify a signed token.
 *
 * Uses hash_equals so a comparison cannot be timed, and checks expiry after the
 * signature so an attacker learns nothing from the difference.
 *
 * @param int    $application_id Application ID.
 * @param string $action         Action the token must authorise.
 * @param string $token          Token from the URL.
 * @return true|WP_Error
 */
function flxlm_ats_verify_token( $application_id, $action, $token ) {
	$token = (string) $token;
	if ( false === strpos( $token, '.' ) ) {
		return new WP_Error( 'flxlm_ats_bad_token', 'That link is not valid.' );
	}

	list( $expires, $hmac ) = explode( '.', $token, 2 );
	$expires                = (int) $expires;

	$payload  = (int) $application_id . '|' . $action . '|' . $expires;
	$expected = hash_hmac( 'sha256', $payload, flxlm_ats_link_secret() );

	if ( ! hash_equals( $expected, $hmac ) ) {
		return new WP_Error( 'flxlm_ats_bad_token', 'That link is not valid.' );
	}

	if ( $expires < time() ) {
		return new WP_Error(
			'flxlm_ats_expired_token',
			'That link has expired. Please open the application in the admin instead.'
		);
	}

	return true;
}

/**
 * URL of the confirmation page for a decision.
 *
 * Note this is the page that ASKS. It never performs the change.
 *
 * @param int    $application_id Application ID.
 * @param string $stage          Target stage key.
 * @return string
 */
function flxlm_ats_action_url( $application_id, $stage ) {
	return add_query_arg(
		array(
			'flxlm_ats'   => 'confirm',
			'application' => (int) $application_id,
			'stage'       => rawurlencode( $stage ),
			'token'       => rawurlencode( flxlm_ats_make_token( $application_id, $stage, FLXLM_ATS_ACTION_TTL ) ),
		),
		home_url( '/' )
	);
}

/**
 * URL that shows a resume to someone holding a valid signed link.
 *
 * @param int $application_id Application ID.
 * @return string
 */
function flxlm_ats_resume_url( $application_id ) {
	return add_query_arg(
		array(
			'flxlm_ats'   => 'resume',
			'application' => (int) $application_id,
			'token'       => rawurlencode( flxlm_ats_make_token( $application_id, 'view', FLXLM_ATS_RESUME_TTL ) ),
		),
		home_url( '/' )
	);
}

/**
 * The admin-side resume link, for someone already logged in.
 *
 * @param int $application_id Application ID.
 * @return string
 */
function flxlm_ats_admin_resume_url( $application_id ) {
	return wp_nonce_url(
		admin_url( 'admin.php?page=flxlm-ats-resume&application=' . (int) $application_id ),
		'flxlm_ats_resume_' . (int) $application_id
	);
}
