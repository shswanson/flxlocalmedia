<?php
/**
 * The applications list screen.
 *
 * Built on the native WordPress list table rather than a custom screen. That is
 * not laziness: because stages are post statuses, the "All (24) | New (5) |
 * Screening (3) | ..." row across the top, the counts, the filtering, the
 * search box and the bulk-action machinery all already exist and already work
 * the way everyone at the company has used WordPress for years. A bespoke
 * screen would have to re-earn all of that and would be one more thing to
 * learn.
 *
 * The only real additions are the columns that matter (applicant, job, stage,
 * source, whether a resume is attached) and one-click stage moves as row
 * actions, so advancing a candidate is a single click from the list with no
 * typing and no intermediate screen.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Columns on the applications list.
 *
 * @param array $columns Default columns.
 * @return array
 */
function flxlm_ats_columns( $columns ) {
	return array(
		'cb'              => $columns['cb'] ?? '',
		'title'           => 'Applicant',
		'flxlm_job'       => 'Job',
		'flxlm_stage'     => 'Stage',
		'flxlm_source'    => 'Heard about us',
		'flxlm_resume'    => 'Resume',
		'flxlm_submitted' => 'Applied',
	);
}
add_filter( 'manage_flxlm_application_posts_columns', 'flxlm_ats_columns' );

/**
 * Render a column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Application ID.
 */
function flxlm_ats_render_column( $column, $post_id ) {
	switch ( $column ) {
		case 'flxlm_job':
			$job_id = (int) get_post_meta( $post_id, '_flxlm_job_id', true );
			$title  = flxlm_ats_job_title( $post_id );
			if ( $job_id && get_post( $job_id ) ) {
				printf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_post_link( $job_id ) ),
					esc_html( $title )
				);
			} else {
				echo esc_html( $title );
			}

			// Which site took the application in. Worth seeing at a glance while
			// the cross-site relay is new.
			$site = get_post_meta( $post_id, '_flxlm_source_site', true );
			if ( 'fldn' === $site ) {
				echo '<br /><span style="color:#666;font-size:.85em">via Finger Lakes Daily News</span>';
			}
			break;

		case 'flxlm_stage':
			$stage = get_post_status( $post_id );
			echo '<strong>' . esc_html( flxlm_ats_stage_label( $stage ) ) . '</strong>';
			if ( flxlm_ats_was_interviewed( $post_id ) && 'flxlm_interviewed' !== $stage ) {
				// Surfacing the historical fact matters: someone interviewed and
				// then rejected still counts as an interviewee on the EEO report,
				// and seeing that here is how anyone would ever notice.
				echo '<br /><span style="color:#666;font-size:.85em">interviewed</span>';
			}
			break;

		case 'flxlm_source':
			echo esc_html( flxlm_ats_source_label( get_post_meta( $post_id, '_flxlm_source', true ) ) );
			break;

		case 'flxlm_resume':
			if ( get_post_meta( $post_id, '_flxlm_resume_file', true ) ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener">Open</a>',
					esc_url( flxlm_ats_admin_resume_url( $post_id ) )
				);
			} else {
				echo '<span style="color:#999">—</span>';
			}
			break;

		case 'flxlm_submitted':
			$at = get_post_meta( $post_id, '_flxlm_submitted_at', true );
			echo esc_html( $at ? mysql2date( 'M j, Y', $at ) : '—' );
			break;
	}
}
add_action( 'manage_flxlm_application_posts_custom_column', 'flxlm_ats_render_column', 10, 2 );

/**
 * Sort by application date by default, newest first.
 *
 * @param WP_Query $query Query.
 */
function flxlm_ats_default_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'flxlm_application' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'flxlm_ats_default_order' );

/**
 * One-click stage moves, as row actions.
 *
 * Only the next sensible step and the rejection are offered, rather than all
 * seven stages. A row with seven links is a menu; a row with two is a decision.
 *
 * @param array   $actions Existing row actions.
 * @param WP_Post $post    Application.
 * @return array
 */
function flxlm_ats_row_actions( $actions, $post ) {
	if ( 'flxlm_application' !== $post->post_type ) {
		return $actions;
	}

	// The native "Trash" link must not appear: applications are retained, not
	// deleted. See inc/retention.php.
	unset( $actions['trash'], $actions['inline hide-if-no-js'] );

	if ( ! current_user_can( 'flxlm_manage_applications' ) ) {
		return $actions;
	}

	$order = array_keys( flxlm_ats_stages() );
	$index = array_search( $post->post_status, $order, true );
	$next  = '';

	if ( false !== $index && isset( $order[ $index + 1 ] ) ) {
		$candidate = $order[ $index + 1 ];
		// Never auto-offer the rejection as the "next" step.
		if ( 'flxlm_rejected' !== $candidate ) {
			$next = $candidate;
		}
	}

	$new_actions = array();

	if ( $next ) {
		$new_actions['flxlm_advance'] = sprintf(
			'<a href="%s">Move to %s</a>',
			esc_url( flxlm_ats_admin_stage_url( $post->ID, $next ) ),
			esc_html( flxlm_ats_stage_label( $next ) )
		);
	}

	if ( 'flxlm_rejected' !== $post->post_status ) {
		$new_actions['flxlm_reject'] = sprintf(
			'<a href="%s" style="color:#b32d2e">Not selected</a>',
			esc_url( flxlm_ats_admin_stage_url( $post->ID, 'flxlm_rejected' ) )
		);
	}

	return array_merge( $new_actions, $actions );
}
add_filter( 'post_row_actions', 'flxlm_ats_row_actions', 10, 2 );

/**
 * Nonced URL for an admin-side stage move.
 *
 * @param int    $application_id Application ID.
 * @param string $stage          Target stage.
 * @return string
 */
function flxlm_ats_admin_stage_url( $application_id, $stage ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'      => 'flxlm_ats_stage',
				'application' => (int) $application_id,
				'stage'       => $stage,
			),
			admin_url( 'admin-post.php' )
		),
		'flxlm_ats_stage_' . (int) $application_id . '_' . $stage
	);
}

/**
 * Perform an admin-side stage move.
 */
function flxlm_ats_handle_admin_stage() {
	$application_id = isset( $_GET['application'] ) ? (int) $_GET['application'] : 0;
	$stage          = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';

	check_admin_referer( 'flxlm_ats_stage_' . $application_id . '_' . $stage );

	if ( ! current_user_can( 'flxlm_manage_applications' ) ) {
		wp_die( 'You do not have permission to move applications.', 'Not allowed', array( 'response' => 403 ) );
	}

	$result = flxlm_ats_set_stage( $application_id, $stage );

	$back = wp_get_referer();
	$back = $back ? $back : admin_url( 'edit.php?post_type=flxlm_application' );

	wp_safe_redirect(
		add_query_arg(
			'flxlm_ats_moved',
			is_wp_error( $result ) ? 'error' : rawurlencode( $stage ),
			$back
		)
	);
	exit;
}
add_action( 'admin_post_flxlm_ats_stage', 'flxlm_ats_handle_admin_stage' );

/**
 * Confirm a move at the top of the list.
 */
function flxlm_ats_moved_notice() {
	if ( empty( $_GET['flxlm_ats_moved'] ) ) {
		return;
	}

	$moved = sanitize_key( wp_unslash( $_GET['flxlm_ats_moved'] ) );

	if ( 'error' === $moved ) {
		echo '<div class="notice notice-error is-dismissible"><p>That application could not be moved.</p></div>';
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>Moved to %s.</p></div>',
		esc_html( flxlm_ats_stage_label( $moved ) )
	);
}
add_action( 'admin_notices', 'flxlm_ats_moved_notice' );
