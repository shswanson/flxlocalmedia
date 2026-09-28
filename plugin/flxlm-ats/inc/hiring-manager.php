<?php
/**
 * The hiring manager assignment on a job posting.
 *
 * job_hiring_manager (a WP user ID) drives who gets told about a new
 * application (inc/notify.php) and owns moving candidates through the early
 * stages (see inc/stages.php's ladder). It is set here, in a meta box on the
 * flxlm_job edit screen, even though flxlm_job itself is registered by the
 * flxlocalmedia theme (inc/cpt-jobs.php), not this plugin.
 *
 * THAT SPLIT IS DELIBERATE. The Careers CPT is presentation: title, body,
 * location, pay, how the posting reads on the public site. Who reviews
 * applicants for it is an ATS concern, no different from _flxlm_job_id on an
 * application meaning something only in this plugin's world. Bolting it onto
 * the theme would mean the applicant-tracking logic starts depending on the
 * theme being the active one, and inc/post-type.php already explains at
 * length why an application must never depend on any particular theme. The
 * plugin hooking a meta box onto a post type the theme registers is the same
 * pattern flxlm_ats_hub_get_vacancies() and inc/notify.php already use to
 * read that post type's own job_email meta — extending it, not duplicating it.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the meta box, only where the flxlm_job post type actually exists
 * (a site running this plugin without the flxlocalmedia theme, or a QA
 * install with a stand-in CPT, still needs this to no-op cleanly rather than
 * fatal on an unregistered post type).
 */
function flxlm_ats_add_hiring_manager_box() {
	if ( ! post_type_exists( 'flxlm_job' ) ) {
		return;
	}

	add_meta_box(
		'flxlm_ats_hiring_manager',
		'Hiring Manager',
		'flxlm_ats_render_hiring_manager_box',
		'flxlm_job',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'flxlm_ats_add_hiring_manager_box' );

/**
 * Users eligible to be a hiring manager: anyone who can view applications.
 * Not everyone who CAN see applications should necessarily be assigned as a
 * manager, but no one who cannot see them should ever be — this is the
 * capability that already draws that exact line (inc/post-type.php).
 *
 * @return WP_User[]
 */
function flxlm_ats_hiring_manager_candidates() {
	return get_users(
		array(
			'capability' => 'flxlm_view_applications',
			'orderby'    => 'display_name',
			'order'      => 'ASC',
		)
	);
}

/**
 * Render the dropdown.
 *
 * @param WP_Post $post Job posting.
 */
function flxlm_ats_render_hiring_manager_box( $post ) {
	wp_nonce_field( 'flxlm_ats_hiring_manager', 'flxlm_ats_hiring_manager_nonce' );

	$current = (int) get_post_meta( $post->ID, 'job_hiring_manager', true );
	$users   = flxlm_ats_hiring_manager_candidates();

	echo '<p class="description">New applications for this job email this person first. If nobody is set, we fall back to the posting\'s Application Email, then the business manager.</p>';

	echo '<select name="job_hiring_manager" style="width:100%">';
	echo '<option value="0">' . esc_html__( 'No one assigned yet', 'flxlm-ats' ) . '</option>';
	foreach ( $users as $user ) {
		printf(
			'<option value="%d" %s>%s</option>',
			esc_attr( $user->ID ),
			selected( $current, $user->ID, false ),
			esc_html( $user->display_name . ' (' . $user->user_email . ')' )
		);
	}
	echo '</select>';
}

/**
 * Save the assignment.
 *
 * @param int $post_id Job posting ID.
 */
function flxlm_ats_save_hiring_manager( $post_id ) {
	if ( ! isset( $_POST['flxlm_ats_hiring_manager_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flxlm_ats_hiring_manager_nonce'] ) ), 'flxlm_ats_hiring_manager' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['job_hiring_manager'] ) ) {
		return;
	}

	$user_id = (int) $_POST['job_hiring_manager'];

	if ( $user_id > 0 && user_can( $user_id, 'flxlm_view_applications' ) ) {
		update_post_meta( $post_id, 'job_hiring_manager', $user_id );
	} else {
		delete_post_meta( $post_id, 'job_hiring_manager' );
	}
}
add_action( 'save_post_flxlm_job', 'flxlm_ats_save_hiring_manager' );

/**
 * The job's assigned hiring manager, as a WP_User, or null.
 *
 * @param int $job_id Job posting ID.
 * @return WP_User|null
 */
function flxlm_ats_job_hiring_manager( $job_id ) {
	$user_id = (int) get_post_meta( (int) $job_id, 'job_hiring_manager', true );
	if ( ! $user_id ) {
		return null;
	}
	$user = get_userdata( $user_id );
	return $user ? $user : null;
}
