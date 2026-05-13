<?php
/**
 * Careers CPT — `flxlm_job` post type, /careers/ permalink, admin meta box.
 *
 * Mirrors the schema of `fldn-careers` (job_location, job_type, job_email,
 * job_responsibilities, job_qualifications, job_offer) so postings can be
 * drafted with the same fields on both sites.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Job CPT.
 */
function flxlm_register_job_cpt() {
	$labels = array(
		'name'               => 'Careers',
		'singular_name'      => 'Job',
		'menu_name'          => 'Careers',
		'add_new'            => 'Add New',
		'add_new_item'       => 'Add New Job',
		'edit_item'          => 'Edit Job',
		'new_item'           => 'New Job',
		'view_item'          => 'View Job',
		'search_items'       => 'Search Jobs',
		'not_found'          => 'No jobs found',
		'not_found_in_trash' => 'No jobs found in trash',
		'all_items'          => 'All Jobs',
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'careers', 'with_front' => false ),
		'capability_type'    => 'post',
		'has_archive'        => 'careers',
		'hierarchical'       => false,
		'menu_position'      => 22,
		'menu_icon'          => 'dashicons-businessperson',
		'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
	);

	register_post_type( 'flxlm_job', $args );
}
add_action( 'init', 'flxlm_register_job_cpt' );

/**
 * Use the classic editor for jobs — meta box authoring is easier than blocks.
 */
add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
	if ( 'flxlm_job' === $post_type ) {
		return false;
	}
	return $use;
}, 10, 2 );

/**
 * Add the meta box.
 */
function flxlm_add_job_meta_box() {
	add_meta_box(
		'flxlm_job_details',
		'Job Details',
		'flxlm_render_job_meta_box',
		'flxlm_job',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'flxlm_add_job_meta_box' );

/**
 * Render the meta box.
 */
function flxlm_render_job_meta_box( $post ) {
	wp_nonce_field( 'flxlm_job_meta', 'flxlm_job_nonce' );

	$single_fields = array(
		array( 'key' => 'job_location', 'label' => 'Location (e.g. Geneva, NY)' ),
		array( 'key' => 'job_type',     'label' => 'Type (e.g. Full-Time / Part-Time / Contract)' ),
		array( 'key' => 'job_email',    'label' => 'Application Email (comma-separated for multiple)' ),
	);

	$multi_fields = array(
		array( 'key' => 'job_responsibilities', 'label' => 'What You\'ll Do — HTML (paragraph or &lt;ul&gt;)' ),
		array( 'key' => 'job_qualifications',   'label' => 'What We\'re Looking For — HTML (&lt;ul&gt;)' ),
		array( 'key' => 'job_offer',            'label' => 'What We Offer — HTML (&lt;ul&gt;, optional closer &lt;p&gt;)' ),
	);

	echo '<table class="form-table">';

	foreach ( $single_fields as $field ) {
		$value = get_post_meta( $post->ID, $field['key'], true );
		echo '<tr>';
		echo '<th><label for="flxlm_' . esc_attr( $field['key'] ) . '">' . esc_html( $field['label'] ) . '</label></th>';
		echo '<td><input type="text" id="flxlm_' . esc_attr( $field['key'] ) . '" name="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '" class="widefat" /></td>';
		echo '</tr>';
	}

	foreach ( $multi_fields as $field ) {
		$value = get_post_meta( $post->ID, $field['key'], true );
		echo '<tr>';
		echo '<th><label for="flxlm_' . esc_attr( $field['key'] ) . '">' . wp_kses( $field['label'], array() ) . '</label></th>';
		echo '<td><textarea id="flxlm_' . esc_attr( $field['key'] ) . '" name="' . esc_attr( $field['key'] ) . '" rows="6" class="widefat code">' . esc_textarea( $value ) . '</textarea></td>';
		echo '</tr>';
	}

	echo '</table>';
}

/**
 * Save the meta box.
 */
function flxlm_save_job_meta( $post_id ) {
	if ( ! isset( $_POST['flxlm_job_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['flxlm_job_nonce'] ), 'flxlm_job_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$single_fields = array( 'job_location', 'job_type', 'job_email' );
	foreach ( $single_fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
		}
	}

	$multi_fields = array( 'job_responsibilities', 'job_qualifications', 'job_offer' );
	foreach ( $multi_fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, wp_kses_post( wp_unslash( $_POST[ $field ] ) ) );
		}
	}
}
add_action( 'save_post_flxlm_job', 'flxlm_save_job_meta' );
