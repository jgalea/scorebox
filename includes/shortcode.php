<?php
/**
 * Shortcode support for ScoreBox.
 *
 * [scorebox] — renders the review box for the current post.
 * [scorebox id="123"] — renders the review box for a specific post.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the [scorebox] shortcode.
 */
function scorebox_register_shortcode() {
	add_shortcode( 'scorebox', 'scorebox_shortcode_handler' );
}
add_action( 'init', 'scorebox_register_shortcode' );

/**
 * Handle the [scorebox] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string Rendered review box HTML or empty string.
 */
function scorebox_shortcode_handler( $atts ) {
	$atts = shortcode_atts(
		array(
			'id' => 0,
		),
		$atts,
		'scorebox'
	);

	$post_id = absint( $atts['id'] );

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	if ( ! $post_id ) {
		return '';
	}

	$review = scorebox_get_review( $post_id );
	if ( ! $review || empty( $review['rating'] ) ) {
		return '';
	}

	// Enqueue frontend styles when shortcode is used.
	wp_enqueue_style(
		'scorebox-frontend',
		SCOREBOX_URL . 'assets/css/review-box.css',
		array(),
		SCOREBOX_VERSION
	);

	return scorebox_render_box( $review, $post_id );
}
