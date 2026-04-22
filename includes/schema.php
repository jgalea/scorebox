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
 * Get all supported JSON-LD schema types.
 *
 * Returns an associative array of [ value => human label ]. Types without
 * dedicated field handlers in scorebox_build_schema() fall through to a
 * generic Review structure (name, review, url, image) which is still valid
 * JSON-LD for Google.
 *
 * @return array<string, string>
 */
function scorebox_get_schema_types() {
	$types = array(
		'Product'             => __( 'Product', 'scorebox' ),
		'SoftwareApplication' => __( 'Software Application', 'scorebox' ),
		'Book'                => __( 'Book', 'scorebox' ),
		'Movie'               => __( 'Movie', 'scorebox' ),
		'TVSeries'            => __( 'TV Series', 'scorebox' ),
		'VideoGame'           => __( 'Video Game', 'scorebox' ),
		'MusicAlbum'          => __( 'Music Album', 'scorebox' ),
		'Recipe'              => __( 'Recipe', 'scorebox' ),
		'Course'              => __( 'Course', 'scorebox' ),
		'Event'               => __( 'Event', 'scorebox' ),
		'LocalBusiness'       => __( 'Local Business', 'scorebox' ),
		'Restaurant'          => __( 'Restaurant', 'scorebox' ),
		'CreativeWork'        => __( 'Creative Work', 'scorebox' ),
		'Thing'               => __( 'Thing (generic)', 'scorebox' ),
	);

	return apply_filters( 'scorebox_schema_types', $types );
}

/**
 * Get optional per-type fields that users can fill in to enrich the JSON-LD
 * output for a given schema type.
 *
 * Each type maps to an array of [ field_key => [ label, type ] ] where type
 * is one of: text, textarea, date, number. Fields that are omitted or empty
 * are simply not emitted in the schema.
 *
 * @return array<string, array<string, array{label: string, type: string}>>
 */
function scorebox_get_type_fields() {
	$fields = array(
		'Book'          => array(
			'author' => array( 'label' => __( 'Author', 'scorebox' ),           'type' => 'text' ),
			'isbn'   => array( 'label' => __( 'ISBN', 'scorebox' ),             'type' => 'text' ),
		),
		'Movie'         => array(
			'director' => array( 'label' => __( 'Director', 'scorebox' ),       'type' => 'text' ),
			'duration' => array( 'label' => __( 'Duration (e.g. PT2H15M)', 'scorebox' ), 'type' => 'text' ),
		),
		'TVSeries'      => array(
			'actor'           => array( 'label' => __( 'Lead Actor', 'scorebox' ),        'type' => 'text' ),
			'numberOfSeasons' => array( 'label' => __( 'Number of Seasons', 'scorebox' ), 'type' => 'number' ),
		),
		'VideoGame'     => array(
			'gamePlatform' => array( 'label' => __( 'Platform(s)', 'scorebox' ),  'type' => 'text' ),
		),
		'MusicAlbum'    => array(
			'byArtist' => array( 'label' => __( 'Artist', 'scorebox' ),          'type' => 'text' ),
		),
		'Recipe'        => array(
			'recipeIngredient' => array( 'label' => __( 'Ingredients (one per line)', 'scorebox' ), 'type' => 'textarea' ),
			'prepTime'         => array( 'label' => __( 'Prep Time (e.g. PT30M)', 'scorebox' ),    'type' => 'text' ),
			'cookTime'         => array( 'label' => __( 'Cook Time (e.g. PT1H)', 'scorebox' ),     'type' => 'text' ),
			'recipeYield'      => array( 'label' => __( 'Yield (e.g. 4 servings)', 'scorebox' ),   'type' => 'text' ),
		),
		'Course'        => array(
			'provider' => array( 'label' => __( 'Provider', 'scorebox' ),        'type' => 'text' ),
		),
		'Event'         => array(
			'startDate' => array( 'label' => __( 'Start Date', 'scorebox' ),     'type' => 'date' ),
			'location'  => array( 'label' => __( 'Location', 'scorebox' ),       'type' => 'text' ),
		),
		'LocalBusiness' => array(
			'address'    => array( 'label' => __( 'Address', 'scorebox' ),       'type' => 'textarea' ),
			'telephone'  => array( 'label' => __( 'Telephone', 'scorebox' ),     'type' => 'text' ),
			'priceRange' => array( 'label' => __( 'Price Range (e.g. $$)', 'scorebox' ), 'type' => 'text' ),
		),
		'Restaurant'    => array(
			'address'       => array( 'label' => __( 'Address', 'scorebox' ),    'type' => 'textarea' ),
			'telephone'     => array( 'label' => __( 'Telephone', 'scorebox' ),  'type' => 'text' ),
			'servesCuisine' => array( 'label' => __( 'Cuisine', 'scorebox' ),    'type' => 'text' ),
			'priceRange'    => array( 'label' => __( 'Price Range (e.g. $$)', 'scorebox' ), 'type' => 'text' ),
		),
		'CreativeWork'  => array(
			'creator' => array( 'label' => __( 'Creator', 'scorebox' ),          'type' => 'text' ),
		),
	);

	return apply_filters( 'scorebox_type_fields', $fields );
}

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
	$valid_types = array_keys( scorebox_get_schema_types() );
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

	$review_name = get_the_title( $post_id );

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => $schema_type,
		'name'            => $product_name,
		'review'          => array(
			'@type'        => 'Review',
			'name'         => $review_name,
			'itemReviewed' => array(
				'@type' => $schema_type,
				'name'  => $product_name,
			),
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
		'aggregateRating' => array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) $schema_rating,
			'reviewCount' => '1',
			'bestRating'  => '5',
			'worstRating' => '1',
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
	}

	// Merge user-provided per-type fields (e.g. Book author, Recipe ingredients).
	$schema = scorebox_schema_apply_type_fields( $schema, $schema_type, $review );

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
 * @param array $schema Schema array.
 * @param array $review Review data.
 * @return array Modified schema.
 */
function scorebox_schema_product( $schema, $review ) {
	if ( ! empty( $review['price'] ) ) {
		$schema['offers'] = array(
			'@type'         => 'Offer',
			'url'           => $schema['url'],
			'price'         => $review['price'],
			'priceCurrency' => ! empty( $review['currency'] ) ? $review['currency'] : 'USD',
			'availability'  => 'https://schema.org/InStock',
		);
	}

	if ( ! empty( $review['product_name'] ) ) {
		$schema['brand'] = array(
			'@type' => 'Brand',
			'name'  => $review['product_name'],
		);
	}

	return $schema;
}

/**
 * Merge user-supplied per-type fields into the schema.
 *
 * Reads $review['type_fields'][ $schema_type ] and emits each non-empty field
 * as a top-level property on the schema object. Multi-line textarea values
 * (e.g. Recipe ingredients) become arrays, one item per non-empty line.
 *
 * @param array  $schema      Current schema array.
 * @param string $schema_type Schema type slug.
 * @param array  $review      Review data.
 * @return array Schema with type fields merged in.
 */
function scorebox_schema_apply_type_fields( $schema, $schema_type, $review ) {
	if ( empty( $review['type_fields'][ $schema_type ] ) || ! is_array( $review['type_fields'][ $schema_type ] ) ) {
		return $schema;
	}

	$registry = scorebox_get_type_fields();
	if ( empty( $registry[ $schema_type ] ) ) {
		return $schema;
	}

	foreach ( $review['type_fields'][ $schema_type ] as $field_key => $value ) {
		if ( ! isset( $registry[ $schema_type ][ $field_key ] ) ) {
			continue;
		}
		if ( '' === $value || null === $value ) {
			continue;
		}

		$field_type = isset( $registry[ $schema_type ][ $field_key ]['type'] ) ? $registry[ $schema_type ][ $field_key ]['type'] : 'text';

		if ( 'textarea' === $field_type ) {
			$lines = array_filter(
				array_map( 'trim', preg_split( "/\r\n|\r|\n/", (string) $value ) ),
				function ( $l ) {
					return '' !== $l;
				}
			);
			if ( ! empty( $lines ) ) {
				$schema[ $field_key ] = count( $lines ) === 1 ? array_values( $lines )[0] : array_values( $lines );
			}
		} elseif ( 'number' === $field_type ) {
			$schema[ $field_key ] = is_numeric( $value ) ? 0 + $value : $value;
		} else {
			$schema[ $field_key ] = $value;
		}
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

	if ( ! empty( $review['price'] ) ) {
		$schema['offers'] = array(
			'@type'         => 'Offer',
			'url'           => $schema['url'],
			'price'         => $review['price'],
			'priceCurrency' => ! empty( $review['currency'] ) ? $review['currency'] : 'USD',
		);
	}

	return $schema;
}
