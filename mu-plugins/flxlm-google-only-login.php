<?php
/**
 * Plugin Name: FLXLM — Google-Only Login
 * Description: Disables username/password sign-in for humans on flxlocalmedia.com. Staff sign in with their FLX Google account via Nextend Social Login. Application passwords (REST automations) are deliberately left working.
 * Version:     1.0.0
 * Author:      FLX Local Media
 *
 * Ported from fldn-google-only-login.php, which does the same job on the news
 * site. Same shape on purpose: two sites that behave differently at the login
 * screen is how someone gets locked out of one of them.
 *
 * BREAK-GLASS RECOVERY (if Google sign-in ever fails):
 *   Option A — re-enable password login without touching this file:
 *       add to wp-config.php:  define( 'FLXLM_ALLOW_PASSWORD_LOGIN', true );
 *   Option B — remove the block entirely:
 *       ssh flxlm.tempurl.host
 *       mv site/public_html/wp-content/mu-plugins/flxlm-google-only-login.php ~/flxlm-google-only-login.php.off
 *
 * WHY THIS SHIPPED ONLY AFTER A REAL GOOGLE SIGN-IN WAS PROVEN:
 * Turning off password login before confirming the replacement works is how you
 * lock every administrator out of a live site at once. The Google round trip was
 * completed against this exact client ID first, landing in wp-admin as `scott`.
 *
 * @package flxlm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Break-glass escape hatch.
 *
 * @return bool True when password sign-in has been deliberately re-enabled.
 */
function flxlm_password_login_allowed() {
	return defined( 'FLXLM_ALLOW_PASSWORD_LOGIN' ) && FLXLM_ALLOW_PASSWORD_LOGIN;
}

/**
 * Remove BOTH core password authentication paths.
 *
 * WordPress accepts a password against a username AND against an email address,
 * via two separate callbacks. Removing only the first leaves email sign-in fully
 * open while appearing to work, which is the classic mistake.
 *
 * wp_authenticate_application_password is intentionally NOT removed: automations
 * authenticate that way and must keep working.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( flxlm_password_login_allowed() ) {
			return;
		}
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
	},
	5
);

/**
 * Belt and braces: reject any credential-bearing attempt that still gets through.
 *
 * Runs late and passes through untouched when something else has already
 * produced a valid user, which is how application-password auth survives.
 *
 * @param null|WP_User|WP_Error $user     Result of prior authentication.
 * @param string                $username Submitted username.
 * @param string                $password Submitted password.
 * @return null|WP_User|WP_Error
 */
add_filter(
	'authenticate',
	function ( $user, $username, $password ) {
		if ( flxlm_password_login_allowed() || $user instanceof WP_User ) {
			return $user;
		}

		// Credentials were actually submitted: refuse, with a clear explanation.
		if ( ! empty( $username ) && ! empty( $password ) ) {
			return new WP_Error(
				'flxlm_password_login_disabled',
				wp_kses(
					'<strong>Password sign-in is turned off.</strong><br>Please use the <em>Continue with Google</em> button and sign in with your FLX Google account.',
					array(
						'strong' => array(),
						'br'     => array(),
						'em'     => array(),
					)
				)
			);
		}

		/*
		 * No credentials, so this is just someone loading wp-login.php.
		 * wp-login.php calls wp_signon() with empty args even on a plain GET;
		 * with the core password callbacks removed nothing claims the request,
		 * so wp_authenticate() falls through to its generic "Invalid username"
		 * error and every visitor would see a red error box on arrival.
		 * Returning a silent WP_Error with no message suppresses that.
		 */
		return new WP_Error( 'flxlm_no_credentials', '' );
	},
	100,
	3
);

/**
 * Hide the username and password fields, and style the Google button to spec.
 *
 * The fields are hidden rather than removed: other plugins and core JS expect
 * them to exist in the form, and removing them outright breaks the page in ways
 * that are tedious to chase.
 */
add_action(
	'login_head',
	function () {
		if ( flxlm_password_login_allowed() ) {
			return;
		}
		?>
		<style>
			#loginform > p:not(.nsl-container):not(.forgetmenot),
			#loginform .user-pass-wrap,
			#loginform .forgetmenot,
			#loginform #wp-submit,
			#loginform .submit,
			#nav { display: none !important; }

			#loginform .nsl-container { margin: 0 !important; padding: 0 !important; }

			/* Google's own sign-in button spec: 40px tall, 1px #dadce0, 4px radius. */
			#loginform .nsl-button-google {
				display: flex !important;
				align-items: center;
				justify-content: center;
				gap: 12px;
				box-sizing: border-box;
				width: 100% !important;
				height: 40px !important;
				background-color: #fff !important;
				border: 1px solid #dadce0 !important;
				border-radius: 4px !important;
				box-shadow: none !important;
			}
			#loginform .nsl-button-google:hover {
				background-color: #f8f9fa !important;
				border-color: #d2e3fc !important;
			}
			#loginform .nsl-button-google .nsl-button-label-container {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
				font-size: 14px !important;
				font-weight: 500 !important;
				color: #3c4043 !important;
			}
			#loginform .nsl-button-google .nsl-button-label-container b { font-weight: 500 !important; }
		</style>
		<?php
	}
);
