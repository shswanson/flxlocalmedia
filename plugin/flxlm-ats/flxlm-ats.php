<?php
/**
 * Plugin Name: FLX Local Media ATS
 * Plugin URI: https://www.flxlocalmedia.com
 * Description: Lightweight applicant tracking for the FLX Local Media career center. Takes in applications from flxlocalmedia.com, fingerlakesdailynews.com and email, tracks them through a stage ladder with interviewer feedback and team comments, and produces the FCC EEO Public File Report numbers as a byproduct.
 * Version: 1.3.1
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

define( 'FLXLM_ATS_VERSION', '1.3.1' ); // v1.3.1, 2026-09-30: AI resume summary (inc/ai-summary.php), staff-only and advisory, off unless FLXLM_ATS_EMAIL_INTAKE_SECRET is defined. No data migration. Earlier note: // v1.1, 2026-09-28: contact editing, the recruitment-source change control, clickable mailto:/tel: links, external (non-staff) interviewers, the thumbs recommendation UI, and a visual pass on every signed page. No data migration — inc/upgrade.php still only runs the 1.2.0 stage-ladder migration.
define( 'FLXLM_ATS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXLM_ATS_URL', plugin_dir_url( __FILE__ ) );

require_once FLXLM_ATS_DIR . 'inc/resume-store.php';
require_once FLXLM_ATS_DIR . 'inc/sources.php';
require_once FLXLM_ATS_DIR . 'inc/notes.php'; // The append-only notes table: comments, feedback, decisions, system events. Loaded early — stages.php, interviewers.php and email-intake.php all write to it.
require_once FLXLM_ATS_DIR . 'inc/stages.php';
require_once FLXLM_ATS_DIR . 'inc/upgrade.php'; // The 1.2.0 data migration off the retired flxlm_screening/flxlm_manager stages.
require_once FLXLM_ATS_DIR . 'inc/post-type.php';
require_once FLXLM_ATS_DIR . 'inc/storage.php';
require_once FLXLM_ATS_DIR . 'inc/tokens.php';
require_once FLXLM_ATS_DIR . 'inc/intake.php';
require_once FLXLM_ATS_DIR . 'inc/email-intake.php'; // jobs@flxlocalmedia.com, via the Air's read-only intake job — off unless FLXLM_ATS_EMAIL_INTAKE_SECRET is defined.
require_once FLXLM_ATS_DIR . 'inc/ai-summary.php'; // Fact-only resume summary from the Air's ai-summary job, same signing secret as email intake. Loaded before hub-bridge.php's audit helpers are CALLED (not defined), so order is not load-bearing.
require_once FLXLM_ATS_DIR . 'inc/form.php';
require_once FLXLM_ATS_DIR . 'inc/rest-intake.php';
require_once FLXLM_ATS_DIR . 'inc/hiring-manager.php'; // job_hiring_manager meta box on flxlm_job; inc/notify.php and inc/digest.php both read it.
require_once FLXLM_ATS_DIR . 'inc/comments.php'; // Business manager address(es) option + team-comment notifications.
require_once FLXLM_ATS_DIR . 'inc/notify.php';
require_once FLXLM_ATS_DIR . 'inc/interviewers.php';
require_once FLXLM_ATS_DIR . 'inc/contact.php'; // Contact-detail edit and the recruitment-source change control (v1.1, 2026-09-28).
require_once FLXLM_ATS_DIR . 'inc/digest.php'; // Weekly stuck-candidate email + `wp flxlm-ats digest`.
require_once FLXLM_ATS_DIR . 'inc/admin-list.php';
require_once FLXLM_ATS_DIR . 'inc/admin-detail.php';
require_once FLXLM_ATS_DIR . 'inc/admin-manual-entry.php';
require_once FLXLM_ATS_DIR . 'inc/confirm-page.php';
require_once FLXLM_ATS_DIR . 'inc/retention.php';
require_once FLXLM_ATS_DIR . 'inc/eeo-report.php';
require_once FLXLM_ATS_DIR . 'inc/hub-bridge.php'; // hub.flxlocalmedia.com bridge, fldn#1912 — off unless FLXLM_ATS_HUB_BRIDGE_ENABLED is defined true.

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
		flxlm_ats_install_notes_table();
		flxlm_ats_run_1_2_0_migration();
		update_option( 'flxlm_ats_migration_1_2_0_done', gmdate( 'Y-m-d H:i:s' ) );
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

	flxlm_ats_ensure_hiring_team_role();
}

/**
 * The hiring_team role, in code.
 *
 * Reused from the paused feat/hub-postings branch (commit 5530c58): it
 * existed only in the live database there (created by hand), which meant a
 * fresh install or a restore onto a new host would silently lose who can see
 * applicants. Codified here so the code is the record, trimmed to only the
 * v1 caps this plugin actually grants (the posting-specific
 * flxlm_edit_postings/flxlm_publish_postings caps stay on that branch — see
 * this plugin's structured report for why v1 does not touch them).
 *
 * This role exists so job_hiring_manager (inc/hiring-manager.php) and the
 * hub bridge's per-user capability check can be assigned to a station or
 * department manager who has no business holding the Editor role's much
 * wider posting/publishing rights. Idempotent: creates the role if missing,
 * otherwise only ADDS caps it lacks — it never removes a cap, so anything
 * granted by hand on the live site stays.
 */
function flxlm_ats_ensure_hiring_team_role() {
	$caps = array( 'read', 'flxlm_view_applications', 'flxlm_manage_applications' );

	$role = get_role( 'hiring_team' );
	if ( ! $role ) {
		add_role( 'hiring_team', 'Hiring Team', array_fill_keys( $caps, true ) );
		return;
	}

	foreach ( $caps as $cap ) {
		if ( ! $role->has_cap( $cap ) ) {
			$role->add_cap( $cap );
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
		flxlm_ats_maybe_install_notes_table();
		update_option( 'flxlm_ats_version', FLXLM_ATS_VERSION );
	}
);
