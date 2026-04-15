<?php
/**
 * Migration tools for ScoreBox.
 *
 * Supports migration from WP Review Pro (free + pro).
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

// Menu registration is handled by admin/settings.php.

// =========================================================================
// AJAX handlers
// =========================================================================

/**
 * Handle single post migration AJAX request.
 */
function scorebox_ajax_migrate() {
	check_ajax_referer( 'scorebox_migrate', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'scorebox' ) ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$source  = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'wp_review_pro';

	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'scorebox' ) ) );
	}

	$result = scorebox_migrate_post( $post_id, $source );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s: post title */
				__( 'Migrated: %s', 'scorebox' ),
				get_the_title( $post_id )
			),
			'post_id' => $post_id,
		)
	);
}
add_action( 'wp_ajax_scorebox_migrate', 'scorebox_ajax_migrate' );

/**
 * Handle bulk migration AJAX request.
 */
function scorebox_ajax_migrate_all() {
	check_ajax_referer( 'scorebox_migrate', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'scorebox' ) ) );
	}

	$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'wp_review_pro';

	$sources = scorebox_get_migration_sources();
	if ( ! isset( $sources[ $source ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Unknown source.', 'scorebox' ) ) );
	}
	$posts = call_user_func( $sources[ $source ]['detect'] );

	$migrated = 0;
	$errors   = array();

	foreach ( $posts as $post ) {
		$result = scorebox_migrate_post( $post->ID, $source );
		if ( is_wp_error( $result ) ) {
			$errors[] = sprintf( '%s: %s', get_the_title( $post->ID ), $result->get_error_message() );
		} else {
			$migrated++;
		}
	}

	wp_send_json_success(
		array(
			'migrated' => $migrated,
			'errors'   => $errors,
			'message'  => sprintf(
				/* translators: %d: number of posts migrated */
				__( 'Migration complete. %d posts migrated.', 'scorebox' ),
				$migrated
			),
		)
	);
}
add_action( 'wp_ajax_scorebox_migrate_all', 'scorebox_ajax_migrate_all' );

// =========================================================================
// Migration: WP Review Pro
// =========================================================================

/**
 * Migrate a single post from the given source.
 *
 * @param int    $post_id Post ID.
 * @param string $source  Migration source key.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function scorebox_migrate_post( $post_id, $source = 'wp_review_pro' ) {
	$sources = scorebox_get_migration_sources();
	if ( ! isset( $sources[ $source ] ) || ! is_callable( $sources[ $source ]['migrate'] ) ) {
		return new WP_Error( 'invalid_source', __( 'Unknown migration source.', 'scorebox' ) );
	}
	return call_user_func( $sources[ $source ]['migrate'], $post_id );
}

/**
 * Migrate a single post from WP Review Pro.
 *
 * @param int $post_id Post ID.
 * @return true|WP_Error
 */
function scorebox_migrate_post_wp_review_pro( $post_id ) {
	$wp_review_type = get_post_meta( $post_id, 'wp_review_type', true );

	if ( empty( $wp_review_type ) || 'none' === $wp_review_type ) {
		return new WP_Error( 'no_review', __( 'No WP Review Pro data found.', 'scorebox' ) );
	}

	// Already migrated?
	$existing = get_post_meta( $post_id, '_scorebox_review', true );
	if ( ! empty( $existing ) ) {
		$existing_data = json_decode( $existing, true );
		if ( is_array( $existing_data ) && ! empty( $existing_data['rating'] ) ) {
			return new WP_Error( 'already_migrated', __( 'Already has ScoreBox data.', 'scorebox' ) );
		}
	}

	// Read WP Review Pro meta.
	$rating      = get_post_meta( $post_id, 'wp_review_total', true );
	$heading     = get_post_meta( $post_id, 'wp_review_heading', true );
	$desc        = get_post_meta( $post_id, 'wp_review_desc', true );
	$pros_html   = get_post_meta( $post_id, 'wp_review_pros', true );
	$cons_html   = get_post_meta( $post_id, 'wp_review_cons', true );
	$product     = get_post_meta( $post_id, 'wp_review_product', true );
	$schema      = get_post_meta( $post_id, 'wp_review_schema', true );
	$price       = get_post_meta( $post_id, 'wp_review_price', true );
	$currency    = get_post_meta( $post_id, 'wp_review_currency', true );
	$url         = get_post_meta( $post_id, 'wp_review_url', true );
	$btn_txt     = get_post_meta( $post_id, 'wp_review_btn_txt', true );
	$author_name = get_post_meta( $post_id, 'wp_review_author', true );

	// Normalize the rating based on review type.
	$normalized_rating = scorebox_normalize_wp_review_rating( $rating, $wp_review_type );

	// Parse pros/cons from HTML.
	$pros = scorebox_parse_list_html( $pros_html );
	$cons = scorebox_parse_list_html( $cons_html );

	// Map schema type.
	$schema_type = scorebox_map_schema_type( $schema );

	// Migrate multi-criteria ratings from wp_review_item.
	$criteria     = array();
	$use_criteria = false;
	$review_items = get_post_meta( $post_id, 'wp_review_item', true );
	if ( ! empty( $review_items ) && is_array( $review_items ) ) {
		foreach ( $review_items as $item ) {
			$label    = '';
			$c_rating = 0;

			if ( ! empty( $item['wp_review_item_title'] ) ) {
				$label = $item['wp_review_item_title'];
			}

			if ( ! empty( $item['wp_review_item_star'] ) ) {
				$c_rating = floatval( $item['wp_review_item_star'] );
			}

			// Normalize criterion rating based on review type (same scale as overall).
			$c_rating = scorebox_normalize_wp_review_rating( $c_rating, $wp_review_type );

			if ( ! empty( $label ) ) {
				$criteria[] = array(
					'label'  => sanitize_text_field( $label ),
					'rating' => $c_rating,
				);
			}
		}
		if ( ! empty( $criteria ) ) {
			$use_criteria = true;
		}
	}

	$data = array(
		'rating'       => $normalized_rating,
		'rating_type'  => 'star', // Always convert to star scale.
		'position'     => '',
		'heading'      => $heading ?: '',
		'summary'      => $desc ?: '',
		'pros'         => $pros,
		'cons'         => $cons,
		'schema_type'  => $schema_type,
		'product_name' => $product ?: '',
		'price'        => $price ?: '',
		'currency'     => $currency ?: 'USD',
		'cta_text'     => $btn_txt ?: '',
		'cta_url'      => $url ?: '',
		'author_name'  => $author_name ?: '',
		'use_criteria' => $use_criteria,
		'criteria'     => $criteria,
	);

	scorebox_save_review( $post_id, $data );

	return true;
}

// =========================================================================
// Shared helpers
// =========================================================================

/**
 * Normalize a WP Review Pro rating to 0-5 scale.
 *
 * @param string $rating      Raw rating value.
 * @param string $review_type WP Review Pro review type.
 * @return float Normalized rating (0-5, half-star increments).
 */
function scorebox_normalize_wp_review_rating( $rating, $review_type ) {
	$value = floatval( $rating );

	switch ( $review_type ) {
		case 'point':
			$value = $value / 2;
			break;
		case 'percentage':
			$value = $value / 20;
			break;
		case 'star':
		default:
			break;
	}

	$value = max( 0, min( 5, $value ) );
	$value = round( $value * 2 ) / 2;

	return $value;
}

/**
 * Parse HTML list items from WP Review Pro pros/cons HTML.
 *
 * @param string $html Raw HTML string.
 * @return array Array of clean text strings.
 */
function scorebox_parse_list_html( $html ) {
	if ( empty( $html ) ) {
		return array();
	}

	$items = array();

	if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/si', $html, $matches ) ) {
		foreach ( $matches[1] as $match ) {
			$text = wp_strip_all_tags( $match );
			$text = trim( $text );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}
	}

	if ( empty( $items ) ) {
		$stripped = wp_strip_all_tags( $html );
		$lines   = preg_split( '/[\r\n]+/', $stripped );
		foreach ( $lines as $line ) {
			$text = trim( $line );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}
	}

	return $items;
}

/**
 * Map WP Review Pro schema type to ScoreBox schema type.
 *
 * @param string $schema WP Review Pro schema value.
 * @return string ScoreBox schema type.
 */
function scorebox_map_schema_type( $schema ) {
	$map = array(
		'Product'             => 'Product',
		'SoftwareApplication' => 'SoftwareApplication',
		'Book'                => 'Thing',
		'Movie'               => 'Thing',
		'MusicRecording'      => 'Thing',
		'Game'                => 'SoftwareApplication',
		'Restaurant'          => 'Thing',
		'TVSeries'            => 'Thing',
		'WebSite'             => 'Thing',
		'Place'               => 'Thing',
		'Organization'        => 'Thing',
		'Course'              => 'Thing',
		'CreativeWork'        => 'Thing',
		'Event'               => 'Thing',
		'HowTo'              => 'Thing',
		'LocalBusiness'       => 'Thing',
		'MediaObject'         => 'Thing',
		'Recipe'              => 'Thing',
		'Store'               => 'Thing',
		'Thing'               => 'Thing',
	);

	if ( isset( $map[ $schema ] ) ) {
		return $map[ $schema ];
	}

	return 'Product';
}

// =========================================================================
// Migration row data callbacks
// =========================================================================

/**
 * Return display row data for a WP Review Pro post.
 *
 * @param WP_Post $post Post object.
 * @return array
 */
function scorebox_migration_row_wp_review_pro( $post ) {
	$review_type = get_post_meta( $post->ID, 'wp_review_type', true );
	$rating      = get_post_meta( $post->ID, 'wp_review_total', true );
	$schema      = get_post_meta( $post->ID, 'wp_review_schema', true );
	$pros_html   = get_post_meta( $post->ID, 'wp_review_pros', true );
	$cons_html   = get_post_meta( $post->ID, 'wp_review_cons', true );
	$normalized  = scorebox_normalize_wp_review_rating( $rating, $review_type );

	return array(
		'type'   => $review_type,
		'rating' => $rating . ( (string) $rating !== (string) $normalized ? ' &rarr; ' . $normalized : '' ),
		'schema' => $schema ?: '-',
		'pros'   => count( scorebox_parse_list_html( $pros_html ) ),
		'cons'   => count( scorebox_parse_list_html( $cons_html ) ),
	);
}

// =========================================================================
// Queries for migratable posts
// =========================================================================

/**
 * Get all posts with WP Review Pro data that haven't been migrated yet.
 *
 * @return WP_Post[] Array of post objects.
 */
function scorebox_get_migratable_posts() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND pm.meta_value != %s
			AND pm.meta_value != %s
			AND p.post_status IN (%s, %s, %s, %s)
			ORDER BY p.post_date DESC",
			'wp_review_type',
			'',
			'none',
			'publish',
			'draft',
			'pending',
			'private'
		)
	);

	if ( empty( $post_ids ) ) {
		return array();
	}

	$posts = array();
	foreach ( $post_ids as $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( $post ) {
			$posts[] = $post;
		}
	}

	return $posts;
}

// =========================================================================
// Enqueue and render
// =========================================================================

/**
 * Enqueue migration page scripts.
 *
 * @param string $hook_suffix Current admin page.
 */
function scorebox_migration_enqueue( $hook_suffix ) {
	if ( 'scorebox_page_scorebox-migration' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'scorebox-admin',
		SCOREBOX_URL . 'assets/css/admin.css',
		array(),
		SCOREBOX_VERSION
	);

	wp_enqueue_script(
		'scorebox-migration',
		SCOREBOX_URL . 'assets/js/migration.js',
		array(),
		SCOREBOX_VERSION,
		true
	);

	wp_localize_script(
		'scorebox-migration',
		'scoreboxMigration',
		array(
			'nonce'   => wp_create_nonce( 'scorebox_migrate' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => array(
				'migrating'  => __( 'Migrating...', 'scorebox' ),
				'migrated'   => __( 'Migrated', 'scorebox' ),
				'error'      => __( 'Error', 'scorebox' ),
				'confirmAll' => __( 'Migrate all posts? This cannot be undone.', 'scorebox' ),
				'retry'      => __( 'Retry Migration', 'scorebox' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'scorebox_migration_enqueue' );

/**
 * Render the migration page.
 */
function scorebox_migration_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	include SCOREBOX_DIR . 'admin/views/migration-page.php';
}
