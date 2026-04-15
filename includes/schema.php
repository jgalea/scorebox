<?php
/**
 * JSON-LD structured data output.
 *
 * Outputs a single <script type="application/ld+json"> tag in wp_head.
 * Supports Product, SoftwareApplication, and Thing schema types.
 * Normalizes all rating types to a 0-5 scale for schema ratingValue.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output JSON-LD schema on singular posts/pages that have review data.
 */
function scorebox_output_schema() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = get_the_ID();
	$review  = scorebox_get_review( $post_id );

	if ( ! $review || empty( $review['rating'] ) ) {
		return;
	}

	$schema = scorebox_build_schema( $post_id, $review );
	if ( empty( $schema ) ) {
		return;
	}

	// Single script tag output — no duplicates.
	$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	// Prevent breaking out of the script tag (standard JSON-LD safety encoding).
	$json = str_replace( '</', '<\/', $json );
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded with script-tag-safe escaping.
	);
}
add_action( 'wp_head', 'scorebox_output_schema', 1 );

/**
 * Build the JSON-LD schema array for a review.
 *
 * @param int   $post_id Post ID.
 * @param array $review  Review data.
 * @return array Schema array.
 */
function scorebox_build_schema( $post_id, $review ) {
	$options     = get_option( 'scorebox_settings', array() );
	$valid_types = array( 'Product', 'SoftwareApplication', 'Thing' );
	$schema_type = ! empty( $review['schema_type'] ) && in_array( $review['schema_type'], $valid_types, true )
		? $review['schema_type']
		: 'Product';
	$post        = get_post( $post_id );

	// Determine author name: review-level override > settings default > post author display name.
	$author_name = '';
	if ( ! empty( $review['author_name'] ) ) {
		$author_name = $review['author_name'];
	} elseif ( ! empty( $options['default_author_name'] ) ) {
		$author_name = $options['default_author_name'];
	} else {
		$author = get_userdata( $post->post_author );
		$author_name = $author ? $author->display_name : '';
	}

	// Product name: review-level override > post title.
	$product_name = ! empty( $review['product_name'] ) ? $review['product_name'] : get_the_title( $post_id );

	// Normalize rating to 0-5 scale for schema output.
	$rating_type     = ! empty( $review['rating_type'] ) ? $review['rating_type'] : 'star';
	$schema_rating   = scorebox_normalize_to_star_scale( $review['rating'], $rating_type );

	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => $schema_type,
		'name'     => $product_name,
		'review'   => array(
			'@type'        => 'Review',
			'author'       => array(
				'@type' => 'Person',
				'name'  => $author_name,
			),
			'reviewRating' => array(
				'@type'       => 'Rating',
				'ratingValue' => (string) $schema_rating,
				'bestRating'  => '5',
				'worstRating' => '1',
			),
		),
	);

	// Add review body. Append criteria breakdown if present.
	$review_body = '';
	if ( ! empty( $review['summary'] ) ) {
		$review_body = $review['summary'];
	}
	if ( ! empty( $review['use_criteria'] ) && ! empty( $review['criteria'] ) ) {
		$criteria_parts = array();
		foreach ( $review['criteria'] as $criterion ) {
			$criteria_parts[] = $criterion['label'] . ': ' . $criterion['rating'] . '/5';
		}
		$criteria_text = implode( ', ', $criteria_parts );
		$review_body   = $review_body ? $review_body . ' ' . $criteria_text : $criteria_text;
	}
	if ( ! empty( $review_body ) ) {
		$schema['review']['reviewBody'] = $review_body;
	}

	// Add datePublished.
	$schema['review']['datePublished'] = get_the_date( 'c', $post_id );

	// Add description from post excerpt or summary.
	if ( ! empty( $review['summary'] ) ) {
		$schema['description'] = $review['summary'];
	} elseif ( has_excerpt( $post_id ) ) {
		$schema['description'] = get_the_excerpt( $post_id );
	}

	// Add URL.
	$schema['url'] = get_permalink( $post_id );

	// Add image if post has a featured image.
	if ( has_post_thumbnail( $post_id ) ) {
		$schema['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
	}

	// Type-specific additions.
	switch ( $schema_type ) {
		case 'Product':
			$schema = scorebox_schema_product( $schema, $review );
			break;

		case 'SoftwareApplication':
			$schema = scorebox_schema_software( $schema, $review );
			break;

		case 'Thing':
			// Thing has no additional required fields beyond what we've set.
			break;
	}

	/**
	 * Filter the complete JSON-LD schema array before output.
	 *
	 * @param array $schema  Schema array.
	 * @param int   $post_id Post ID.
	 * @param array $review  Review data.
	 */
	return apply_filters( 'scorebox_schema', $schema, $post_id, $review );
}

/**
 * Add Product-specific schema fields.
 *
 * Always includes offers to fix GSC "Missing field offers" error.
 *
 * @param array $schema Schema array.
 * @param array $review Review data.
 * @return array Modified schema.
 */
function scorebox_schema_product( $schema, $review ) {
	// Offers is required for Product schema — always include it.
	$offer = array(
		'@type' => 'Offer',
		'url'   => $schema['url'],
	);

	if ( ! empty( $review['price'] ) ) {
		$offer['price']         = $review['price'];
		$offer['priceCurrency'] = ! empty( $review['currency'] ) ? $review['currency'] : 'USD';
		$offer['availability']  = 'https://schema.org/InStock';
	} else {
		// No price specified — use 0 to satisfy GSC requirements.
		$offer['price']         = '0';
		$offer['priceCurrency'] = ! empty( $review['currency'] ) ? $review['currency'] : 'USD';
		$offer['availability']  = 'https://schema.org/InStock';
	}

	$schema['offers'] = $offer;

	// Add brand if we can derive one.
	if ( ! empty( $review['product_name'] ) ) {
		$schema['brand'] = array(
			'@type' => 'Brand',
			'name'  => $review['product_name'],
		);
	}

	return $schema;
}

/**
 * Add SoftwareApplication-specific schema fields.
 *
 * @param array $schema Schema array.
 * @param array $review Review data.
 * @return array Modified schema.
 */
function scorebox_schema_software( $schema, $review ) {
	$schema['applicationCategory'] = 'WebApplication';

	// Offers for software.
	$offer = array(
		'@type' => 'Offer',
		'url'   => $schema['url'],
	);

	if ( ! empty( $review['price'] ) ) {
		$offer['price']         = $review['price'];
		$offer['priceCurrency'] = ! empty( $review['currency'] ) ? $review['currency'] : 'USD';
	} else {
		$offer['price']         = '0';
		$offer['priceCurrency'] = ! empty( $review['currency'] ) ? $review['currency'] : 'USD';
	}

	$schema['offers'] = $offer;

	return $schema;
}
