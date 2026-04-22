<?php
/**
 * Extension point registry for ScoreBox.
 *
 * Provides filterable registries for rating types, styles, migration sources,
 * and editor configuration. Pro add-ons extend these via WordPress filters.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get all registered rating types.
 *
 * Returns an associative array keyed by type slug. Each entry contains:
 *   label  (string)   Human-readable label.
 *   max    (float)    Maximum rating value.
 *   step   (float)    Minimum increment.
 *   render (callable) Function name that renders the display HTML.
 *   format (string)   sprintf-compatible format string for the value label.
 *
 * @return array<string, array>
 */
function scorebox_get_rating_types() {
	$types = array(
		'star' => array(
			'label'  => __( 'Star (0-5)', 'scorebox' ),
			'max'    => 5,
			'step'   => 0.5,
			'render' => 'scorebox_render_stars_display',
			'format' => '%s/5',
		),
	);

	return apply_filters( 'scorebox_rating_types', $types );
}

/**
 * Get all registered box styles.
 *
 * Returns an associative array keyed by style slug. Each entry contains:
 *   label (string) Human-readable label.
 *
 * @return array<string, array>
 */
function scorebox_get_styles() {
	$styles = array(
		'default' => array(
			'label' => __( 'Default (Classic)', 'scorebox' ),
		),
	);

	return apply_filters( 'scorebox_styles', $styles );
}

/**
 * Get all registered migration sources.
 *
 * Returns an associative array keyed by source slug. Each entry contains:
 *   label    (string)   Human-readable label.
 *   detect   (callable) Function name that returns an array of WP_Post objects to migrate.
 *   migrate  (callable) Function name that migrates a single post by ID.
 *   columns  (array)    Column definitions for the migration table (label => heading).
 *   row_data (callable) Function name that returns per-row display data for a given post ID.
 *
 * @return array<string, array>
 */
/**
 * Count posts migratable across every registered source.
 *
 * @return int
 */
function scorebox_count_all_migratable() {
	$total = 0;
	foreach ( scorebox_get_migration_sources() as $source ) {
		if ( isset( $source['detect'] ) && is_callable( $source['detect'] ) ) {
			$posts  = call_user_func( $source['detect'] );
			$total += is_array( $posts ) ? count( $posts ) : 0;
		}
	}
	return $total;
}

function scorebox_get_migration_sources() {
	$sources = array(
		'wp_review_pro' => array(
			'label'    => __( 'WP Review Pro', 'scorebox' ),
			'detect'   => 'scorebox_get_migratable_posts',
			'migrate'  => 'scorebox_migrate_post_wp_review_pro',
			'columns'  => array( 'type', 'rating', 'schema', 'pros', 'cons' ),
			'row_data' => 'scorebox_migration_row_wp_review_pro',
		),
	);

	return apply_filters( 'scorebox_migration_sources', $sources );
}

/**
 * Build the editor configuration array for the block editor JS.
 *
 * Transforms the registered rating types and styles into the shape expected
 * by the block editor script (window.scoreboxConfig).
 *
 * @return array {
 *   @type array $ratingTypes  Flat array of { value, label, max, step, format }.
 *   @type array $styles       Flat array of { value, label }.
 *   @type array $features     Feature flags: criteria (bool).
 * }
 */
function scorebox_get_editor_config() {
	$types = scorebox_get_rating_types();
	$styles = scorebox_get_styles();

	$rating_types_config = array();
	foreach ( $types as $slug => $type ) {
		$rating_types_config[] = array(
			'value'  => $slug,
			'label'  => $type['label'],
			'max'    => $type['max'],
			'step'   => $type['step'],
			'format' => $type['format'],
		);
	}

	$styles_config = array();
	foreach ( $styles as $slug => $style ) {
		$styles_config[] = array(
			'value' => $slug,
			'label' => $style['label'],
		);
	}

	$schema_types_config = array();
	foreach ( scorebox_get_schema_types() as $slug => $label ) {
		$schema_types_config[] = array(
			'value' => $slug,
			'label' => $label,
		);
	}

	$type_fields_config = array();
	foreach ( scorebox_get_type_fields() as $type_key => $fields ) {
		$list = array();
		foreach ( $fields as $field_key => $def ) {
			$list[] = array(
				'key'   => $field_key,
				'label' => isset( $def['label'] ) ? $def['label'] : $field_key,
				'type'  => isset( $def['type'] ) ? $def['type'] : 'text',
			);
		}
		$type_fields_config[ $type_key ] = $list;
	}

	$config = array(
		'ratingTypes' => $rating_types_config,
		'styles'      => $styles_config,
		'schemaTypes' => $schema_types_config,
		'typeFields'  => $type_fields_config,
		'features'    => array(
			'criteria' => false,
		),
	);

	return apply_filters( 'scorebox_editor_config', $config );
}
