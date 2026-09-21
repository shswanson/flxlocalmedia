<?php
/**
 * The single-application screen.
 *
 * Everything about an applicant on one screen, with the stage buttons at the
 * top where a decision can be made without scrolling. The WordPress editor is
 * suppressed for this post type: an application is a record, not a document,
 * and nothing about it should look editable by hand.
 *
 * @package flxlm-ats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the detail meta box and hide the pieces that do not apply.
 */
function flxlm_ats_add_detail_box() {
	add_meta_box(
		'flxlm_ats_detail',
		'Application',
		'flxlm_ats_render_detail_box',
		'flxlm_application',
		'normal',
		'high'
	);

	// The publish box offers Draft/Pending/Publish, which are meaningless here
	// and would let someone knock a record out of the stage ladder entirely.
	remove_meta_box( 'submitdiv', 'flxlm_application', 'side' );

	add_meta_box(
		'flxlm_ats_stagebox',
		'Stage',
		'flxlm_ats_render_stage_box',
		'flxlm_application',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_flxlm_application', 'flxlm_ats_add_detail_box' );

/**
 * The stage control.
 *
 * Every stage is a single button. No dropdown, no Save step: one click moves
 * the candidate and reloads. Requiring someone to pick from a select and then
 * find Update is exactly the kind of two-step that stops getting done.
 *
 * @param WP_Post $post Application.
 */
function flxlm_ats_render_stage_box( $post ) {
	$current = $post->post_status;
	$can     = current_user_can( 'flxlm_manage_applications' );

	echo '<p style="margin-top:0">Currently <strong>' . esc_html( flxlm_ats_stage_label( $current ) ) . '</strong>.</p>';

	if ( flxlm_ats_was_interviewed( $post->ID ) ) {
		$when = get_post_meta( $post->ID, '_flxlm_interviewed_at', true );
		echo '<p style="color:#1e7e34;font-size:.9em">Interviewed '
			. esc_html( mysql2date( 'M j, Y', $when ) )
			. '. This is recorded for the FCC EEO report and cannot be undone.</p>';
	}

	if ( ! $can ) {
		echo '<p style="color:#666">You can read this application but not move it.</p>';
		return;
	}

	echo '<div style="display:flex;flex-direction:column;gap:.4rem">';
	foreach ( flxlm_ats_stages() as $key => $stage ) {
		if ( $key === $current ) {
			continue;
		}

		$is_reject = ( 'flxlm_rejected' === $key );
		printf(
			'<a href="%s" class="button %s"%s>%s</a>',
			esc_url( flxlm_ats_admin_stage_url( $post->ID, $key ) ),
			$is_reject ? '' : 'button-primary',
			$is_reject ? ' style="color:#b32d2e"' : '',
			esc_html( 'Move to ' . $stage['label'] )
		);
	}
	echo '</div>';
}

/**
 * The application itself.
 *
 * @param WP_Post $post Application.
 */
function flxlm_ats_render_detail_box( $post ) {
	$id = $post->ID;

	$rows = array(
		'Name'            => flxlm_ats_applicant_name( $id ),
		'Email'           => get_post_meta( $id, '_flxlm_email', true ),
		'Phone'           => get_post_meta( $id, '_flxlm_phone', true ),
		'Applied for'     => flxlm_ats_job_title( $id ),
		'Heard about us'  => flxlm_ats_source_label( get_post_meta( $id, '_flxlm_source', true ) ),
		'Pay they want'   => get_post_meta( $id, '_flxlm_salary_expectation', true ),
		'Applied'         => mysql2date( 'F j, Y g:ia', get_post_meta( $id, '_flxlm_submitted_at', true ) ),
		'Came in via'     => flxlm_ats_entered_by_label( get_post_meta( $id, '_flxlm_entered_by', true ) ),
	);

	echo '<table class="form-table"><tbody>';
	foreach ( $rows as $label => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}
		echo '<tr><th style="width:11rem">' . esc_html( $label ) . '</th><td>';
		if ( 'Email' === $label ) {
			printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $value ) );
		} else {
			echo esc_html( $value );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	if ( get_post_meta( $id, '_flxlm_resume_file', true ) ) {
		printf(
			'<p><a href="%s" class="button button-primary" target="_blank" rel="noopener">Open resume (%s)</a></p>',
			esc_url( flxlm_ats_admin_resume_url( $id ) ),
			esc_html( get_post_meta( $id, '_flxlm_resume_name', true ) )
		);
	} else {
		echo '<p style="color:#666">No resume on file.</p>';
	}

	$links = (string) get_post_meta( $id, '_flxlm_links', true );
	if ( $links ) {
		echo '<h3>Their work</h3><p style="white-space:pre-line">'
			. wp_kses_post( make_clickable( esc_html( $links ) ) ) . '</p>';
	}

	$message = (string) get_post_meta( $id, '_flxlm_message', true );
	if ( $message ) {
		echo '<h3>What they said</h3><p style="white-space:pre-line">' . esc_html( $message ) . '</p>';
	}

	$history = get_post_meta( $id, '_flxlm_stage_history', true );
	if ( is_array( $history ) && $history ) {
		echo '<h3>History</h3><ul style="margin:0;color:#555">';
		foreach ( array_reverse( $history ) as $entry ) {
			printf(
				'<li>%s → <strong>%s</strong> · %s · %s</li>',
				esc_html( flxlm_ats_stage_label( $entry['from'] ) ),
				esc_html( flxlm_ats_stage_label( $entry['to'] ) ),
				esc_html( mysql2date( 'M j, Y g:ia', $entry['at'] ) ),
				esc_html( $entry['by'] )
			);
		}
		echo '</ul>';
	}
}

/**
 * Human label for how an application arrived.
 *
 * @param string $key Stored value.
 * @return string
 */
function flxlm_ats_entered_by_label( $key ) {
	$map = array(
		'web'        => 'The form on flxlocalmedia.com',
		'fldn-relay' => 'The form on Finger Lakes Daily News',
		'manual'     => 'Entered by hand',
	);
	return $map[ $key ] ?? (string) $key;
}

/**
 * Register the hidden admin route that streams a resume.
 *
 * Registered with a null parent so it has a URL but no menu item.
 */
function flxlm_ats_register_hidden_screens() {
	add_submenu_page(
		'',
		'Resume',
		'Resume',
		'flxlm_view_applications',
		'flxlm-ats-resume',
		'flxlm_ats_admin_resume_screen'
	);
}
add_action( 'admin_menu', 'flxlm_ats_register_hidden_screens' );
