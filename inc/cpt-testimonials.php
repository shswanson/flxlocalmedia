<?php
/**
 * Testimonial CPT + meta fields + admin meta box.
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Testimonial CPT.
 */
function flxlm_register_testimonial_cpt() {
	$labels = array(
		'name'               => 'Testimonials',
		'singular_name'      => 'Testimonial',
		'add_new'            => 'Add New',
		'add_new_item'       => 'Add New Testimonial',
		'edit_item'          => 'Edit Testimonial',
		'new_item'           => 'New Testimonial',
		'view_item'          => 'View Testimonial',
		'search_items'       => 'Search Testimonials',
		'not_found'          => 'No testimonials found',
		'not_found_in_trash' => 'No testimonials found in Trash',
		'menu_name'          => 'Testimonials',
	);

	$args = array(
		'labels'       => $labels,
		'public'       => true,
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => 'testimonials', 'with_front' => false ),
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'menu_icon'    => 'dashicons-format-video',
		'show_in_rest' => true,
	);

	register_post_type( 'flxlm_testimonial', $args );
}
add_action( 'init', 'flxlm_register_testimonial_cpt' );

/**
 * Register Service taxonomy.
 */
function flxlm_register_service_taxonomy() {
	$labels = array(
		'name'          => 'Services',
		'singular_name' => 'Service',
		'search_items'  => 'Search Services',
		'all_items'     => 'All Services',
		'edit_item'     => 'Edit Service',
		'update_item'   => 'Update Service',
		'add_new_item'  => 'Add New Service',
		'new_item_name' => 'New Service Name',
		'menu_name'     => 'Services',
	);

	register_taxonomy( 'flxlm_service', 'flxlm_testimonial', array(
		'labels'       => $labels,
		'hierarchical' => true,
		'public'       => true,
		'rewrite'      => array( 'slug' => 'service' ),
		'show_in_rest' => true,
	) );
}
add_action( 'init', 'flxlm_register_service_taxonomy' );

/**
 * Register post meta fields.
 */
function flxlm_register_testimonial_meta() {
	$string_fields = array(
		'person_name',
		'person_title',
		'business_name',
		'industry',
		'location',
		'company_size',
		'products_used',
		'quote_short',
		'quote_full',
		'video_4k',
		'video_1080p',
		'video_720p',
		'poster_webp',
		'poster_jpg',
		'hero_object_position',
		'captions_vtt',
		'transcript_full',
	);

	foreach ( $string_fields as $field ) {
		register_post_meta( 'flxlm_testimonial', $field, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}

	register_post_meta( 'flxlm_testimonial', 'is_featured', array(
		'type'              => 'boolean',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'rest_sanitize_boolean',
		'default'           => false,
	) );
}
add_action( 'init', 'flxlm_register_testimonial_meta' );

/**
 * Add meta box.
 */
function flxlm_add_testimonial_meta_box() {
	add_meta_box(
		'flxlm_testimonial_details',
		'Testimonial Details',
		'flxlm_render_testimonial_meta_box',
		'flxlm_testimonial',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'flxlm_add_testimonial_meta_box' );

/**
 * Render meta box.
 */
function flxlm_render_testimonial_meta_box( $post ) {
	wp_nonce_field( 'flxlm_testimonial_meta', 'flxlm_testimonial_nonce' );

	$fields = array(
		array( 'key' => 'person_name',            'label' => 'Person Name' ),
		array( 'key' => 'person_title',           'label' => 'Person Title' ),
		array( 'key' => 'business_name',          'label' => 'Business Name' ),
		array( 'key' => 'industry',               'label' => 'Industry' ),
		array( 'key' => 'location',               'label' => 'Location' ),
		array( 'key' => 'company_size',           'label' => 'Company Size' ),
		array( 'key' => 'products_used',          'label' => 'Products Used (e.g. Radio, Digital, FLX Finest)' ),
		array( 'key' => 'quote_short',            'label' => 'Short Quote (one line, for cards)' ),
		array( 'key' => 'quote_full',             'label' => 'Full Quote (for hero/detail)' ),
		array( 'key' => 'video_4k',               'label' => 'Video URL (4K)' ),
		array( 'key' => 'video_1080p',            'label' => 'Video URL (1080p)' ),
		array( 'key' => 'video_720p',             'label' => 'Video URL (720p)' ),
		array( 'key' => 'poster_webp',            'label' => 'Poster Image (WebP)' ),
		array( 'key' => 'poster_jpg',             'label' => 'Poster Image (JPG)' ),
		array( 'key' => 'hero_object_position',   'label' => 'Hero Image Position (e.g. 47% 38%)' ),
		array( 'key' => 'captions_vtt',           'label' => 'Captions (WebVTT URL)' ),
	);

	echo '<table class="form-table">';

	foreach ( $fields as $field ) {
		$value = get_post_meta( $post->ID, $field['key'], true );
		echo '<tr>';
		echo '<th><label for="flxlm_' . esc_attr( $field['key'] ) . '">' . esc_html( $field['label'] ) . '</label></th>';
		echo '<td><input type="text" id="flxlm_' . esc_attr( $field['key'] ) . '" name="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '" class="widefat" /></td>';
		echo '</tr>';
	}

	// Transcript (textarea).
	$transcript = get_post_meta( $post->ID, 'transcript_full', true );
	echo '<tr>';
	echo '<th><label for="flxlm_transcript_full">Full Transcript</label></th>';
	echo '<td><textarea id="flxlm_transcript_full" name="transcript_full" rows="8" class="widefat">' . esc_textarea( $transcript ) . '</textarea></td>';
	echo '</tr>';

	// Featured checkbox.
	$is_featured = get_post_meta( $post->ID, 'is_featured', true );
	echo '<tr>';
	echo '<th><label for="flxlm_is_featured">Featured</label></th>';
	echo '<td><label><input type="checkbox" id="flxlm_is_featured" name="is_featured" value="1" ' . checked( $is_featured, true, false ) . ' /> Show in hero sections</label></td>';
	echo '</tr>';

	echo '</table>';
}

/**
 * Save meta box data.
 */
function flxlm_save_testimonial_meta( $post_id ) {
	if ( ! isset( $_POST['flxlm_testimonial_nonce'] ) || ! wp_verify_nonce( $_POST['flxlm_testimonial_nonce'], 'flxlm_testimonial_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$string_fields = array(
		'person_name', 'person_title', 'business_name', 'industry',
		'location', 'company_size', 'products_used',
		'quote_short', 'quote_full',
		'video_4k', 'video_1080p', 'video_720p',
		'poster_webp', 'poster_jpg', 'hero_object_position', 'captions_vtt',
	);

	foreach ( $string_fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
		}
	}

	if ( isset( $_POST['transcript_full'] ) ) {
		update_post_meta( $post_id, 'transcript_full', sanitize_textarea_field( $_POST['transcript_full'] ) );
	}

	update_post_meta( $post_id, 'is_featured', ! empty( $_POST['is_featured'] ) );
}
add_action( 'save_post_flxlm_testimonial', 'flxlm_save_testimonial_meta' );
