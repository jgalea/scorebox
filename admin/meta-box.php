<?php
/**
 * Classic editor meta box fallback.
 *
 * Provides the same review fields as the block editor for users on classic editor.
 * The meta box only loads when the block editor is NOT active for the current post type.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the meta box on post types that support reviews.
 */
function scorebox_add_meta_box() {
	// Don't add meta box if block editor is active — the block handles it.
	if ( scorebox_is_block_editor() ) {
		return;
	}

	$post_types = apply_filters( 'scorebox_post_types', array( 'post', 'page' ) );

	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'scorebox_meta_box',
			__( 'ScoreBox', 'scorebox' ),
			'scorebox_meta_box_render',
			$post_type,
			'normal',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'scorebox_add_meta_box' );

/**
 * Check if the block editor is being used for the current screen.
 *
 * @return bool
 */
function scorebox_is_block_editor() {
	$screen = get_current_screen();
	if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		return true;
	}
	return false;
}

/**
 * Render the meta box contents.
 *
 * @param WP_Post $post Current post object.
 */
function scorebox_meta_box_render( $post ) {
	wp_nonce_field( 'scorebox_meta_box', 'scorebox_meta_box_nonce' );

	$review  = scorebox_get_review( $post->ID );
	$defaults = scorebox_get_defaults();

	// Merge with defaults.
	$data = wp_parse_args( $review ? $review : array(), $defaults );

	include SCOREBOX_DIR . 'admin/views/meta-box.php';
}

/**
 * Save meta box data.
 *
 * @param int $post_id Post ID.
 */
function scorebox_save_meta_box( $post_id ) {
	// Verify nonce.
	if ( ! isset( $_POST['scorebox_meta_box_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scorebox_meta_box_nonce'] ) ), 'scorebox_meta_box' ) ) {
		return;
	}

	// Check autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Only process if our fields are present.
	if ( ! isset( $_POST['scorebox_review_rating'] ) ) {
		return;
	}

	$data = array(
		'rating'       => isset( $_POST['scorebox_review_rating'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_rating'] ) ) : 0,
		'rating_type'  => isset( $_POST['scorebox_review_rating_type'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_rating_type'] ) ) : 'star',
		'style'        => isset( $_POST['scorebox_review_style'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_style'] ) ) : '',
		'position'     => isset( $_POST['scorebox_review_position'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_position'] ) ) : '',
		'heading'      => isset( $_POST['scorebox_review_heading'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_heading'] ) ) : '',
		'summary'      => isset( $_POST['scorebox_review_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['scorebox_review_summary'] ) ) : '',
		'schema_type'  => isset( $_POST['scorebox_review_schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_schema_type'] ) ) : 'Product',
		'product_name' => isset( $_POST['scorebox_review_product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_product_name'] ) ) : '',
		'price'        => isset( $_POST['scorebox_review_price'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_price'] ) ) : '',
		'currency'     => isset( $_POST['scorebox_review_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_currency'] ) ) : 'USD',
		'cta_text'     => isset( $_POST['scorebox_review_cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_cta_text'] ) ) : '',
		'cta_url'      => isset( $_POST['scorebox_review_cta_url'] ) ? esc_url_raw( wp_unslash( $_POST['scorebox_review_cta_url'] ) ) : '',
		'author_name'  => isset( $_POST['scorebox_review_author_name'] ) ? sanitize_text_field( wp_unslash( $_POST['scorebox_review_author_name'] ) ) : '',
	);

	// Parse pros from repeater input array.
	$data['pros']    = array();
	$raw_pros        = isset( $_POST['scorebox_review_pros'] ) && is_array( $_POST['scorebox_review_pros'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['scorebox_review_pros'] ) )
		: array();
	foreach ( $raw_pros as $pro_item ) {
		$trimmed = trim( $pro_item );
		if ( '' !== $trimmed ) {
			$data['pros'][] = $trimmed;
		}
	}

	// Parse cons from repeater input array.
	$data['cons'] = array();
	$raw_cons     = isset( $_POST['scorebox_review_cons'] ) && is_array( $_POST['scorebox_review_cons'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['scorebox_review_cons'] ) )
		: array();
	foreach ( $raw_cons as $con_item ) {
		$trimmed = trim( $con_item );
		if ( '' !== $trimmed ) {
			$data['cons'][] = $trimmed;
		}
	}

	// Parse criteria (Pro feature): paired label + rating arrays.
	$data['use_criteria'] = ! empty( $_POST['scorebox_review_use_criteria'] );
	$data['criteria']     = array();
	$raw_labels           = isset( $_POST['scorebox_review_criteria_labels'] ) && is_array( $_POST['scorebox_review_criteria_labels'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['scorebox_review_criteria_labels'] ) )
		: array();
	$raw_ratings          = isset( $_POST['scorebox_review_criteria_ratings'] ) && is_array( $_POST['scorebox_review_criteria_ratings'] )
		? array_map( 'floatval', wp_unslash( $_POST['scorebox_review_criteria_ratings'] ) )
		: array();
	foreach ( $raw_labels as $i => $label ) {
		$label_clean = trim( $label );
		if ( '' === $label_clean ) {
			continue;
		}
		$rating_val         = isset( $raw_ratings[ $i ] ) ? (float) $raw_ratings[ $i ] : 0;
		$data['criteria'][] = array(
			'label'  => $label_clean,
			'rating' => max( 0, min( 5, $rating_val ) ),
		);
	}

	// When multi-criteria is active, the overall rating is the average of the criteria.
	if ( $data['use_criteria'] && ! empty( $data['criteria'] ) ) {
		$sum = 0;
		foreach ( $data['criteria'] as $c ) {
			$sum += $c['rating'];
		}
		$avg            = $sum / count( $data['criteria'] );
		$data['rating'] = round( $avg * 2 ) / 2;
	}

	$data = apply_filters( 'scorebox_meta_box_save_data', $data, $post_id );

	// Only save if there's actually review data (rating > 0 or something filled in).
	$has_data = ( floatval( $data['rating'] ) > 0 )
		|| ! empty( $data['summary'] )
		|| ! empty( $data['pros'] )
		|| ! empty( $data['cons'] );

	if ( $has_data ) {
		scorebox_save_review( $post_id, $data );
	} else {
		// Clear review data if everything is empty.
		delete_post_meta( $post_id, '_scorebox_review' );
	}
}
add_action( 'save_post', 'scorebox_save_meta_box' );

/**
 * Enqueue admin styles for the meta box.
 *
 * @param string $hook_suffix Current admin page.
 */
function scorebox_meta_box_enqueue( $hook_suffix ) {
	if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
		return;
	}

	// Only on classic editor.
	if ( scorebox_is_block_editor() ) {
		return;
	}

	wp_enqueue_style(
		'scorebox-meta-box',
		SCOREBOX_URL . 'assets/css/meta-box.css',
		array(),
		SCOREBOX_VERSION
	);

	wp_enqueue_script(
		'scorebox-meta-box',
		SCOREBOX_URL . 'assets/js/meta-box.js',
		array(),
		SCOREBOX_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'scorebox_meta_box_enqueue' );
