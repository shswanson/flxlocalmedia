<?php
/**
 * Plugin Name: FLX Local Media ATS
 * Plugin URI: https://www.flxlocalmedia.com
 * Description: Lightweight applicant tracking for the FLX Local Media career center. Takes in applications from flxlocalmedia.com and fingerlakesdailynews.com, tracks them through a short stage ladder, and produces the FCC EEO Public File Report numbers as a byproduct.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: TOTIB Media
 * Author URI: https://totib.com
 * License: Proprietary
 * Text Domain: flxlm-ats
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS A PLUGIN AND NOT THEME CODE
 *
 * The job postings on this site live in the theme (inc/cpt-jobs.php). The
 * APPLICATIONS must not. Applicant records are this company's FCC EEO
 * compliance evidence under 47 CFR 73.2080(c)(5), which requires retaining them
 * until final action on the next license renewal. A theme is a thing you swap.
 * Compliance evidence is not. This also has to survive the move off WPMU DEV
 * hosting, so it is written to be self-contained: its own post type, its own
 * storage, and no dependency on the flxlocalmedia theme being active.
 *
 * WHERE RESUMES LIVE
 *
 * In the database, not on disk. inc/resume-store.php explains why at length;
 * the short version is that this host runs nginx (so nothing inside the
 * docroot can be made private) and runs the web server as www-data, which
 * cannot write anywhere outside the docroot. The database is the only store
 * here that is private, needs no filesystem permissions, and survives the move
 * off this platform unchanged.
 *
 * @package flxlm-ats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLXLM_ATS_VERSION', '1.0.0' );
define( 'FLXLM_ATS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXLM_ATS_URL', plugin_dir_url( __FILE__ ) );

require_once FLXLM_ATS_DIR . 'inc/resume-store.php';
require_once FLXLM_ATS_DIR . 'inc/sources.php';
require_once FLXLM_ATS_DIR . 'inc/stages.php';
require_once FLXLM_ATS_DIR . 'inc/post-type.php';
require_once FLXLM_ATS_DIR . 'inc/storage.php';
require_once FLXLM_ATS_DIR . 'inc/tokens.php';
require_once FLXLM_ATS_DIR . 'inc/intake.php';
require_once FLXLM_ATS_DIR . 'inc/form.php';
require_once FLXLM_ATS_DIR . 'inc/rest-intake.php';
require_once FLXLM_ATS_DIR . 'inc/notify.php';
require_once FLXLM_ATS_DIR . 'inc/admin-list.php';
require_once FLXLM_ATS_DIR . 'inc/admin-detail.php';
require_once FLXLM_ATS_DIR . 'inc/admin-manual-entry.php';
require_once FLXLM_ATS_DIR . 'inc/confirm-page.php';
require_once FLXLM_ATS_DIR . 'inc/retention.php';
require_once FLXLM_ATS_DIR . 'inc/eeo-report.php';

/**
 * Activation: register everything once, prepare private storage, grant caps.
 */
register_activation_hook(
	__FILE__,
	function () {
		flxlm_ats_register_post_type();
		flxlm_ats_register_stages();
		flxlm_ats_grant_caps();
		flxlm_ats_install_resume_table();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

/**
 * Give administrators and editors the ATS capabilities.
 *
 * Deliberately narrow. Reading an application means reading someone's resume,
 * so it is its own capability rather than riding on edit_posts: a contributor
 * who can write a news post has no business reading applicants' home addresses.
 */
function flxlm_ats_grant_caps() {
	$caps = array( 'flxlm_view_applications', 'flxlm_manage_applications' );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( $caps as $cap ) {
			$admin->add_cap( $cap );
		}
		// Only administrators produce the EEO report; it is a filing, not a task.
		$admin->add_cap( 'flxlm_view_eeo_report' );
	}

	// Editors act as hiring managers: they can read and advance, not file EEO.
	$editor = get_role( 'editor' );
	if ( $editor ) {
		foreach ( $caps as $cap ) {
			$editor->add_cap( $cap );
		}
	}
}

/**
 * Run the capability grant on upgrade too, so an existing install picks up
 * capabilities added in a later version without needing a deactivate/reactivate.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'flxlm_ats_version' ) === FLXLM_ATS_VERSION ) {
			return;
		}
		flxlm_ats_grant_caps();
		flxlm_ats_maybe_install_resume_table();
		update_option( 'flxlm_ats_version', FLXLM_ATS_VERSION );
	}
);
