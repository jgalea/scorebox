<?php
/**
 * Post meta registration and helpers.
 *
 * All review data lives in a single _scorebox_review post meta key as JSON.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the _scorebox_review meta key for the REST API and block editor.
 */
function scorebox_register_meta() {
	$post_types = apply_filters( 'scorebox_post_types', array( 'post', 'page' ) );

	foreach ( $post_types as $post_type ) {
		register_post_meta(
			$post_type,
			'_scorebox_review',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'scorebox_sanitize_meta',
			)
		);
	}
}
add_action( 'init', 'scorebox_register_meta' );

/**
 * Sanitize the _scorebox_review meta value.
 *
 * Accepts a JSON string, validates its structure, and returns sanitized JSON.
 *
 * @param string $value Raw meta value.
 * @return string Sanitized JSON string.
 */
function scorebox_sanitize_meta( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	$data = json_decode( $value, true );
	if ( ! is_array( $data ) ) {
		return '';
	}

	$clean = scorebox_sanitize_data( $data );
	return wp_json_encode( $clean );
}

/**
 * Sanitize review data array.
 *
 * @param array $data Raw review data.
 * @return array Sanitized review data.
 */
function scorebox_sanitize_data( $data ) {
	$defaults = scorebox_get_defaults();

	$clean = array();

	// Style: default, card, hero, split, minimal.
	$valid_styles = array( 'default', 'card', 'hero', 'split', 'minimal' );
	$clean['style'] = '';
	if ( isset( $data['style'] ) && in_array( $data['style'], $valid_styles, true ) ) {
		$clean['style'] = $data['style'];
	}

	// Rating type: star, percentage, point.
	$valid_rating_types = array( 'star', 'percentage', 'point' );
	$clean['rating_type'] = 'star';
	if ( isset( $data['rating_type'] ) && in_array( $data['rating_type'], $valid_rating_types, true ) ) {
		$clean['rating_type'] = $data['rating_type'];
	}

	// Rating: depends on type.
	// star: 0-5, half increments. percentage: 0-100, integers. point: 0-10, half increments.
	$clean['rating'] = 0;
	if ( isset( $data['rating'] ) ) {
		$rating = floatval( $data['rating'] );
		switch ( $clean['rating_type'] ) {
			case 'percentage':
				$rating = round( $rating );
				$clean['rating'] = max( 0, min( 100, $rating ) );
				break;
			case 'point':
				$rating = round( $rating * 2 ) / 2; // Snap to half.
				$clean['rating'] = max( 0, min( 10, $rating ) );
				break;
			case 'star':
			default:
				$rating = round( $rating * 2 ) / 2; // Snap to half-star.
				$clean['rating'] = max( 0, min( 5, $rating ) );
				break;
		}
	}

	// Position: bottom, top, both, manual.
	$valid_positions = array( 'bottom', 'top', 'both', 'manual' );
	$clean['position'] = isset( $data['position'] ) && in_array( $data['position'], $valid_positions, true )
		? $data['position']
		: '';

	// Heading.
	$clean['heading'] = '';
	if ( isset( $data['heading'] ) ) {
		$clean['heading'] = sanitize_text_field( $data['heading'] );
	}

	// Summary.
	$clean['summary'] = '';
	if ( isset( $data['summary'] ) ) {
		$clean['summary'] = sanitize_textarea_field( $data['summary'] );
	}

	// Pros.
	$clean['pros'] = array();
	if ( isset( $data['pros'] ) && is_array( $data['pros'] ) ) {
		foreach ( $data['pros'] as $pro ) {
			$sanitized = sanitize_text_field( $pro );
			if ( '' !== $sanitized ) {
				$clean['pros'][] = $sanitized;
			}
		}
	}

	// Cons.
	$clean['cons'] = array();
	if ( isset( $data['cons'] ) && is_array( $data['cons'] ) ) {
		foreach ( $data['cons'] as $con ) {
			$sanitized = sanitize_text_field( $con );
			if ( '' !== $sanitized ) {
				$clean['cons'][] = $sanitized;
			}
		}
	}

	// Schema type.
	$valid_types = array( 'Product', 'SoftwareApplication', 'Thing' );
	$clean['schema_type'] = $defaults['schema_type'];
	if ( isset( $data['schema_type'] ) && in_array( $data['schema_type'], $valid_types, true ) ) {
		$clean['schema_type'] = $data['schema_type'];
	}

	// Product name (for schema).
	$clean['product_name'] = '';
	if ( isset( $data['product_name'] ) ) {
		$clean['product_name'] = sanitize_text_field( $data['product_name'] );
	}

	// Price and currency (for Product schema offers).
	$clean['price'] = '';
	if ( isset( $data['price'] ) ) {
		$clean['price'] = sanitize_text_field( $data['price'] );
	}

	$clean['currency'] = 'USD';
	if ( isset( $data['currency'] ) ) {
		$clean['currency'] = sanitize_text_field( $data['currency'] );
	}

	// CTA button.
	$clean['cta_text'] = '';
	if ( isset( $data['cta_text'] ) ) {
		$clean['cta_text'] = sanitize_text_field( $data['cta_text'] );
	}

	$clean['cta_url'] = '';
	if ( isset( $data['cta_url'] ) ) {
		$clean['cta_url'] = esc_url_raw( $data['cta_url'] );
	}

	// Author name override.
	$clean['author_name'] = '';
	if ( isset( $data['author_name'] ) ) {
		$clean['author_name'] = sanitize_text_field( $data['author_name'] );
	}

	// Multi-criteria ratings.
	$clean['use_criteria'] = false;
	if ( isset( $data['use_criteria'] ) ) {
		$clean['use_criteria'] = (bool) $data['use_criteria'];
	}

	$clean['criteria'] = array();
	if ( isset( $data['criteria'] ) && is_array( $data['criteria'] ) ) {
		foreach ( $data['criteria'] as $criterion ) {
			if ( ! is_array( $criterion ) ) {
				continue;
			}
			$label = isset( $criterion['label'] ) ? sanitize_text_field( $criterion['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$c_rating = isset( $criterion['rating'] ) ? floatval( $criterion['rating'] ) : 0;
			$c_rating = round( $c_rating * 2 ) / 2; // Snap to half-star.
			$c_rating = max( 0, min( 5, $c_rating ) );
			$clean['criteria'][] = array(
				'label'  => $label,
				'rating' => $c_rating,
			);
		}
	}

	// Allow add-ons to add their own sanitized keys.
	return apply_filters( 'scorebox_sanitize_review_data', $clean, $data );
}

/**
 * Get the review data for a post.
 *
 * @param int $post_id Post ID.
 * @return array|null Review data array or null if no review.
 */
function scorebox_get_review( $post_id ) {
	$raw = get_post_meta( $post_id, '_scorebox_review', true );

	if ( empty( $raw ) ) {
		return null;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return null;
	}

	return $data;
}

/**
 * Save review data for a post.
 *
 * @param int   $post_id Post ID.
 * @param array $data    Review data array.
 * @return bool True on success.
 */
function scorebox_save_review( $post_id, $data ) {
	$clean = scorebox_sanitize_data( $data );
	$result = update_post_meta( $post_id, '_scorebox_review', wp_json_encode( $clean ) );

	// Store a normalized 0-5 rating in its own meta key so the admin Rating column
	// can sort numerically. The JSON blob above is unsortable.
	$rating_type   = ! empty( $clean['rating_type'] ) ? $clean['rating_type'] : 'star';
	$rating_sort   = scorebox_normalize_to_star_scale( $clean['rating'], $rating_type );
	update_post_meta( $post_id, '_scorebox_rating_sort', $rating_sort );

	return $result;
}

/**
 * Get default review values from settings.
 *
 * @return array Default values.
 */
function scorebox_get_defaults() {
	$options = get_option( 'scorebox_settings', array() );

	return array(
		'rating'       => 0,
		'rating_type'  => 'star',
		'style'        => '',
		'position'     => '',
		'heading'      => '',
		'summary'      => '',
		'pros'         => array(),
		'cons'         => array(),
		'schema_type'  => isset( $options['default_schema_type'] ) ? $options['default_schema_type'] : 'Product',
		'product_name' => '',
		'price'        => '',
		'currency'     => 'USD',
		'cta_text'     => '',
		'cta_url'      => '',
		'author_name'  => isset( $options['default_author_name'] ) ? $options['default_author_name'] : '',
		'use_criteria' => false,
		'criteria'     => array(),
	);
}

/**
 * Normalize a rating to the 0-5 star scale for schema output.
 *
 * @param float  $rating      The rating value.
 * @param string $rating_type The rating type (star, percentage, point).
 * @return float Rating on 0-5 scale.
 */
function scorebox_normalize_to_star_scale( $rating, $rating_type ) {
	$value = floatval( $rating );

	switch ( $rating_type ) {
		case 'percentage':
			$value = $value / 20; // 100 -> 5.
			break;
		case 'point':
			$value = $value / 2; // 10 -> 5.
			break;
		case 'star':
		default:
			// Already 0-5.
			break;
	}

	$value = max( 0, min( 5, $value ) );
	$value = round( $value * 2 ) / 2;

	return $value;
}
