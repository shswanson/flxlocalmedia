<?php
/**
 * The application record.
 *
 * One post per application. The post title is the applicant's name and the job
 * they applied for, which is what makes the native admin list readable without
 * building a custom screen.
 *
 * THIS POST TYPE IS NOT PUBLIC, AND EVERY FLAG BELOW IS DELIBERATE.
 *
 *   'public'              => false  No front-end URL exists for an application.
 *   'publicly_queryable'  => false  ?post_type=flxlm_application returns nothing.
 *   'show_in_rest'        => false  THE IMPORTANT ONE. WordPress exposes post
 *                                   types over /wp-json/wp/v2/ when this is
 *                                   true. An applicant's name, email, phone and
 *                                   cover letter must never be one unauthenticated
 *                                   GET away. This also keeps the block editor
 *                                   off the type, which is fine: nobody authors
 *                                   an application by hand except through the
 *                                   manual-entry screen, which has its own form.
 *   'exclude_from_search' => true   Never in site search results.
 *   'has_archive'         => false  No /applications/ archive page.
 *   'rewrite'             => false  No permalink structure at all.
 *
 * Applications are also deliberately NOT attached to the uploads media library
 * (see inc/storage.php). A resume is not an attachment post.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the application post type.
 */
function flxlm_ats_register_post_type() {
	$labels = array(
		'name'               => 'Applications',
		'singular_name'      => 'Application',
		'menu_name'          => 'Applications',
		'all_items'          => 'All Applications',
		'add_new'            => 'Add Applicant',
		'add_new_item'       => 'Add Applicant',
		'edit_item'          => 'Application',
		'view_item'          => 'View Application',
		'search_items'       => 'Search Applications',
		'not_found'          => 'No applications yet.',
		'not_found_in_trash' => 'Nothing here.',
	);

	register_post_type(
		'flxlm_application',
		array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'hierarchical'        => false,
			'menu_position'       => 23,
			'menu_icon'           => 'dashicons-id-alt',
			'supports'            => array( 'title' ),
			'capability_type'     => array( 'flxlm_application', 'flxlm_applications' ),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'read'                   => 'flxlm_view_applications',
				'edit_posts'             => 'flxlm_view_applications',
				'edit_published_posts'   => 'flxlm_manage_applications',
				'edit_others_posts'      => 'flxlm_manage_applications',
				'publish_posts'          => 'flxlm_manage_applications',
				'read_private_posts'     => 'flxlm_view_applications',
				// Deletion is intentionally pinned to a capability nobody holds.
				// See inc/retention.php for why applications are never deleted.
				'delete_posts'           => 'flxlm_delete_applications',
				'delete_private_posts'   => 'flxlm_delete_applications',
				'delete_published_posts' => 'flxlm_delete_applications',
				'delete_others_posts'    => 'flxlm_delete_applications',
			),
		)
	);
}
add_action( 'init', 'flxlm_ats_register_post_type' );

/**
 * The meta keys an application carries.
 *
 * Kept in one place so the intake, the admin screens and the EEO report cannot
 * drift apart on a field name. Everything is prefixed with an underscore so it
 * stays out of any custom-fields UI.
 *
 * @return string[]
 */
function flxlm_ats_meta_keys() {
	return array(
		'_flxlm_first_name',
		'_flxlm_last_name',
		'_flxlm_email',
		'_flxlm_phone',
		'_flxlm_job_id',        // Local flxlm_job post ID, when the posting is on this site.
		'_flxlm_job_title',     // Denormalised on purpose: see below.
		'_flxlm_source',        // Recruitment source key. Drives the EEO report.
		'_flxlm_source_site',   // 'flxlocalmedia' or 'fldn' — which site took it in.
		'_flxlm_message',       // Cover note / why they want the job.
		'_flxlm_links',         // Portfolio, audio reel, writing samples.
		'_flxlm_salary_expectation',
		'_flxlm_resume_file',   // Basename only. Never a full path, never a URL.
		'_flxlm_resume_name',   // Original filename, for display.
		'_flxlm_interviewed_at',
		'_flxlm_hired_at',
		'_flxlm_stage_history',
		'_flxlm_entered_by',    // 'web', 'fldn-relay', or 'manual'.
		'_flxlm_submitted_at',
	);
}

/**
 * Read an application into a plain array.
 *
 * @param int $application_id Application ID.
 * @return array|null
 */
function flxlm_ats_get_application( $application_id ) {
	$post = get_post( (int) $application_id );
	if ( ! $post || 'flxlm_application' !== $post->post_type ) {
		return null;
	}

	$out = array(
		'id'    => $post->ID,
		'stage' => $post->post_status,
		'title' => $post->post_title,
	);

	foreach ( flxlm_ats_meta_keys() as $key ) {
		$out[ ltrim( str_replace( '_flxlm_', '', $key ), '_' ) ] = get_post_meta( $post->ID, $key, true );
	}

	return $out;
}

/**
 * The job title an application was filed against.
 *
 * job_title is stored on the application rather than being looked up from the
 * job post every time, and that duplication is on purpose. The EEO report has
 * to say which vacancy each applicant applied for, and postings get retitled,
 * unpublished and deleted as roles are filled. If the report read the title
 * live, deleting last year's posting would silently rewrite last year's report.
 * The stored copy is the historical fact. The job ID is kept alongside it for
 * linking while the posting still exists.
 *
 * @param int $application_id Application ID.
 * @return string
 */
function flxlm_ats_job_title( $application_id ) {
	$stored = get_post_meta( (int) $application_id, '_flxlm_job_title', true );
	if ( $stored ) {
		return (string) $stored;
	}

	$job_id = (int) get_post_meta( (int) $application_id, '_flxlm_job_id', true );
	if ( $job_id && get_post( $job_id ) ) {
		return get_the_title( $job_id );
	}

	return 'General application';
}

/**
 * The applicant's display name.
 *
 * @param int $application_id Application ID.
 * @return string
 */
function flxlm_ats_applicant_name( $application_id ) {
	$first = get_post_meta( (int) $application_id, '_flxlm_first_name', true );
	$last  = get_post_meta( (int) $application_id, '_flxlm_last_name', true );
	$name  = trim( $first . ' ' . $last );
	return $name ? $name : 'Applicant';
}
