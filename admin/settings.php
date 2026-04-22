<?php
/**
 * Admin menu, settings registration, and post list integration.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// Top-level admin menu
// =========================================================================

function scorebox_admin_menu() {
	// Main menu page — shows the dashboard/overview.
	add_menu_page(
		__( 'ScoreBox', 'scorebox' ),
		__( 'ScoreBox', 'scorebox' ),
		'manage_options',
		'scorebox',
		'scorebox_dashboard_page',
		'dashicons-star-filled',
		30
	);

	// Settings submenu.
	add_submenu_page(
		'scorebox',
		__( 'Settings', 'scorebox' ),
		__( 'Settings', 'scorebox' ),
		'manage_options',
		'scorebox-settings',
		'scorebox_settings_page'
	);

	// Migration submenu.
	add_submenu_page(
		'scorebox',
		__( 'Migration', 'scorebox' ),
		__( 'Migration', 'scorebox' ),
		'manage_options',
		'scorebox-migration',
		'scorebox_migration_page'
	);

	// Rename the auto-created first submenu from "ScoreBox" to "Dashboard".
	global $submenu;
	if ( isset( $submenu['scorebox'] ) ) {
		$submenu['scorebox'][0][0] = __( 'Dashboard', 'scorebox' );
	}
}
add_action( 'admin_menu', 'scorebox_admin_menu' );

// =========================================================================
// Settings registration
// =========================================================================

function scorebox_register_settings() {
	register_setting(
		'scorebox_settings_group',
		'scorebox_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'scorebox_sanitize_settings',
			'default'           => array(),
		)
	);

	// General section.
	add_settings_section(
		'scorebox_general',
		'',
		'__return_null',
		'scorebox-settings'
	);

	add_settings_field(
		'default_schema_type',
		__( 'Default Schema Type', 'scorebox' ),
		'scorebox_field_schema_type',
		'scorebox-settings',
		'scorebox_general'
	);

	add_settings_field(
		'default_author_name',
		__( 'Default Author Name', 'scorebox' ),
		'scorebox_field_author_name',
		'scorebox-settings',
		'scorebox_general'
	);

	add_settings_field(
		'default_position',
		__( 'Default Review Position', 'scorebox' ),
		'scorebox_field_position',
		'scorebox-settings',
		'scorebox_general'
	);

	// Appearance section.
	add_settings_section(
		'scorebox_appearance',
		'',
		'__return_null',
		'scorebox-settings-appearance'
	);

	add_settings_field(
		'default_style',
		__( 'Review Box Style', 'scorebox' ),
		'scorebox_field_default_style',
		'scorebox-settings-appearance',
		'scorebox_appearance'
	);

	add_settings_field(
		'accent_color',
		__( 'Star / Accent Color', 'scorebox' ),
		'scorebox_field_accent_color',
		'scorebox-settings-appearance',
		'scorebox_appearance'
	);

	add_settings_field(
		'bg_color',
		__( 'Background Color', 'scorebox' ),
		'scorebox_field_bg_color',
		'scorebox-settings-appearance',
		'scorebox_appearance'
	);

	add_settings_field(
		'border_color',
		__( 'Border Color', 'scorebox' ),
		'scorebox_field_border_color',
		'scorebox-settings-appearance',
		'scorebox_appearance'
	);
}
add_action( 'admin_init', 'scorebox_register_settings' );

// =========================================================================
// Sanitize
// =========================================================================

function scorebox_sanitize_settings( $input ) {
	$output = array();

	$valid_types = array_keys( scorebox_get_schema_types() );
	$output['default_schema_type'] = 'Product';
	if ( isset( $input['default_schema_type'] ) && in_array( $input['default_schema_type'], $valid_types, true ) ) {
		$output['default_schema_type'] = $input['default_schema_type'];
	}

	$output['default_author_name'] = '';
	if ( isset( $input['default_author_name'] ) ) {
		$output['default_author_name'] = sanitize_text_field( $input['default_author_name'] );
	}

	$valid_positions = array( 'bottom', 'top', 'both', 'manual' );
	$output['default_position'] = 'manual';
	if ( isset( $input['default_position'] ) && in_array( $input['default_position'], $valid_positions, true ) ) {
		$output['default_position'] = $input['default_position'];
	}

	$valid_styles = array( 'default', 'card', 'hero', 'split', 'minimal' );
	$output['default_style'] = 'default';
	if ( isset( $input['default_style'] ) && in_array( $input['default_style'], $valid_styles, true ) ) {
		$output['default_style'] = $input['default_style'];
	}

	$output['accent_color'] = '#1e73be';
	if ( isset( $input['accent_color'] ) ) {
		$output['accent_color'] = sanitize_hex_color( $input['accent_color'] ) ?: '#1e73be';
	}

	$output['bg_color'] = '#fff';
	if ( isset( $input['bg_color'] ) ) {
		$output['bg_color'] = sanitize_hex_color( $input['bg_color'] ) ?: '#fff';
	}

	$output['border_color'] = '#e7e7e7';
	if ( isset( $input['border_color'] ) ) {
		$output['border_color'] = sanitize_hex_color( $input['border_color'] ) ?: '#e7e7e7';
	}

	$output['post_types'] = array( 'post' );
	if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
		$output['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
	}

	return $output;
}

// =========================================================================
// Page render callbacks
// =========================================================================

function scorebox_dashboard_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	include SCOREBOX_DIR . 'admin/views/dashboard-page.php';
}

function scorebox_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	include SCOREBOX_DIR . 'admin/views/settings-page.php';
}

// =========================================================================
// Field callbacks
// =========================================================================

function scorebox_field_schema_type() {
	$options = get_option( 'scorebox_settings', array() );
	$current = isset( $options['default_schema_type'] ) ? $options['default_schema_type'] : 'Product';
	?>
	<select name="scorebox_settings[default_schema_type]" id="scorebox_default_schema_type">
		<?php foreach ( scorebox_get_schema_types() as $type_value => $type_label ) : ?>
			<option value="<?php echo esc_attr( $type_value ); ?>" <?php selected( $current, $type_value ); ?>><?php echo esc_html( $type_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<p class="description"><?php esc_html_e( 'Default schema.org type for new reviews. Can be overridden per post.', 'scorebox' ); ?></p>
	<?php
}

function scorebox_field_author_name() {
	$options = get_option( 'scorebox_settings', array() );
	$value   = isset( $options['default_author_name'] ) ? $options['default_author_name'] : '';
	?>
	<input type="text" name="scorebox_settings[default_author_name]" id="scorebox_default_author_name"
		value="<?php echo esc_attr( $value ); ?>" class="regular-text"
		placeholder="<?php esc_attr_e( 'e.g., John Smith', 'scorebox' ); ?>">
	<p class="description"><?php esc_html_e( 'Used in JSON-LD schema as review author. Falls back to post author if empty.', 'scorebox' ); ?></p>
	<?php
}

function scorebox_field_position() {
	$options = get_option( 'scorebox_settings', array() );
	$current = isset( $options['default_position'] ) ? $options['default_position'] : 'manual';
	?>
	<select name="scorebox_settings[default_position]" id="scorebox_default_position">
		<option value="manual" <?php selected( $current, 'manual' ); ?>><?php esc_html_e( 'Manual (block/shortcode only)', 'scorebox' ); ?></option>
		<option value="bottom" <?php selected( $current, 'bottom' ); ?>><?php esc_html_e( 'After content', 'scorebox' ); ?></option>
		<option value="top" <?php selected( $current, 'top' ); ?>><?php esc_html_e( 'Before content', 'scorebox' ); ?></option>
		<option value="both" <?php selected( $current, 'both' ); ?>><?php esc_html_e( 'Before and after content', 'scorebox' ); ?></option>
	</select>
	<p class="description"><?php esc_html_e( 'Where to auto-insert the review box. Can be overridden per post.', 'scorebox' ); ?></p>
	<?php
}

function scorebox_field_accent_color() {
	$options = get_option( 'scorebox_settings', array() );
	$value   = isset( $options['accent_color'] ) ? $options['accent_color'] : '#1e73be';
	?>
	<input type="text" name="scorebox_settings[accent_color]" id="scorebox_accent_color"
		value="<?php echo esc_attr( $value ); ?>" class="scorebox-color-picker" data-default-color="#1e73be">
	<?php
}

function scorebox_field_bg_color() {
	$options = get_option( 'scorebox_settings', array() );
	$value   = isset( $options['bg_color'] ) ? $options['bg_color'] : '#fff';
	?>
	<input type="text" name="scorebox_settings[bg_color]" id="scorebox_bg_color"
		value="<?php echo esc_attr( $value ); ?>" class="scorebox-color-picker" data-default-color="#ffffff">
	<?php
}

function scorebox_field_border_color() {
	$options = get_option( 'scorebox_settings', array() );
	$value   = isset( $options['border_color'] ) ? $options['border_color'] : '#e7e7e7';
	?>
	<input type="text" name="scorebox_settings[border_color]" id="scorebox_border_color"
		value="<?php echo esc_attr( $value ); ?>" class="scorebox-color-picker" data-default-color="#e7e7e7">
	<?php
}

function scorebox_field_default_style() {
	$options = get_option( 'scorebox_settings', array() );
	$current = isset( $options['default_style'] ) ? $options['default_style'] : 'default';
	$styles  = scorebox_get_styles();
	?>
	<select name="scorebox_settings[default_style]" id="scorebox_default_style">
		<?php foreach ( $styles as $value => $config ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $config['label'] ); ?></option>
		<?php endforeach; ?>
	</select>
	<p class="description"><?php esc_html_e( 'Default style for new reviews.', 'scorebox' ); ?></p>
	<?php
}

// =========================================================================
// Appearance preview — shared sample + AJAX refresh
// =========================================================================

/**
 * Sample review used for the appearance preview.
 */
function scorebox_get_preview_review() {
	$review = array(
		'rating'       => 4.5,
		'rating_type'  => 'star',
		'heading'      => __( 'Our Verdict', 'scorebox' ),
		'summary'      => __( 'An excellent product that delivers on its promises. Great value for the price.', 'scorebox' ),
		'pros'         => array(
			__( 'Easy to use', 'scorebox' ),
			__( 'Great performance', 'scorebox' ),
			__( 'Good value', 'scorebox' ),
		),
		'cons'         => array(
			__( 'Limited options', 'scorebox' ),
			__( 'Could be cheaper', 'scorebox' ),
		),
		'cta_text'     => __( 'Visit Website', 'scorebox' ),
		'cta_url'      => '#',
		'product_name' => __( 'Example Product', 'scorebox' ),
		'style'        => '',
		'position'     => '',
		'schema_type'  => 'Product',
		'price'        => '',
		'currency'     => 'USD',
		'author_name'  => '',
		'use_criteria' => false,
		'criteria'     => array(),
	);

	/**
	 * Filter the sample review used on the Appearance preview. Pro add-ons
	 * hook in to enable criteria, visitor ratings, etc. on the preview.
	 *
	 * @param array $review Sample review data.
	 */
	return apply_filters( 'scorebox_preview_review', $review );
}

/**
 * AJAX: render the appearance preview with the provided style + colors,
 * without persisting any settings.
 */
function scorebox_ajax_render_preview() {
	check_ajax_referer( 'scorebox_preview', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$style        = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : 'default';
	$accent_color = isset( $_POST['accent_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['accent_color'] ) ) : '';
	$bg_color     = isset( $_POST['bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bg_color'] ) ) : '';
	$border_color = isset( $_POST['border_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['border_color'] ) ) : '';

	$override = function( $value ) use ( $accent_color, $bg_color, $border_color ) {
		$value = is_array( $value ) ? $value : array();
		if ( $accent_color ) {
			$value['accent_color'] = $accent_color;
		}
		if ( $bg_color ) {
			$value['bg_color'] = $bg_color;
		}
		if ( $border_color ) {
			$value['border_color'] = $border_color;
		}
		return $value;
	};
	add_filter( 'option_scorebox_settings', $override );

	$review          = scorebox_get_preview_review();
	$review['style'] = $style;

	$html = scorebox_render_box( $review, 0 );

	remove_filter( 'option_scorebox_settings', $override );

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_scorebox_render_preview', 'scorebox_ajax_render_preview' );

// =========================================================================
// Post list table — Rating column
// =========================================================================

function scorebox_add_post_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['scorebox_rating'] = __( 'Rating', 'scorebox' );
		}
	}
	return $new;
}

function scorebox_render_post_column( $column, $post_id ) {
	if ( 'scorebox_rating' !== $column ) {
		return;
	}

	$review = scorebox_get_review( $post_id );
	if ( ! $review || empty( $review['rating'] ) ) {
		echo '<span class="scorebox-col-empty">&mdash;</span>';
		return;
	}

	$rating      = $review['rating'];
	$rating_type = ! empty( $review['rating_type'] ) ? $review['rating_type'] : 'star';

	switch ( $rating_type ) {
		case 'percentage':
			$display = round( $rating ) . '%';
			break;
		case 'point':
			$display = $rating . '/10';
			break;
		default:
			$display = $rating . '/5';
			break;
	}

	$stars_html = '';
	if ( 'star' === $rating_type ) {
		$full = floor( $rating );
		$half = ( $rating - $full ) >= 0.5 ? 1 : 0;
		$stars_html .= str_repeat( '<span class="scorebox-col-star scorebox-col-star--full"></span>', $full );
		if ( $half ) {
			$stars_html .= '<span class="scorebox-col-star scorebox-col-star--half"></span>';
		}
		$stars_html .= str_repeat( '<span class="scorebox-col-star scorebox-col-star--empty"></span>', 5 - $full - $half );
	}

	printf(
		'<span class="scorebox-col-rating" title="%s">%s<strong>%s</strong></span>',
		esc_attr( $display ),
		$stars_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static HTML.
		esc_html( $display )
	);
}

function scorebox_sortable_columns( $columns ) {
	$columns['scorebox_rating'] = 'scorebox_rating';
	return $columns;
}

function scorebox_column_orderby( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'scorebox_rating' !== $query->get( 'orderby' ) ) {
		return;
	}

	// Sort by the numeric _scorebox_rating_sort companion meta (0-5 normalized).
	// Posts without a review are excluded — this is the expected behavior when the
	// user explicitly asked to sort by rating.
	$query->set( 'meta_key', '_scorebox_rating_sort' );
	$query->set( 'orderby', 'meta_value_num' );
}

/**
 * One-shot backfill: populate _scorebox_rating_sort for existing reviews that
 * were saved before this companion meta was introduced.
 */
function scorebox_maybe_backfill_rating_sort() {
	if ( get_option( 'scorebox_rating_sort_backfilled' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT pm.post_id
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = pm.post_id AND pm2.meta_key = %s
			WHERE pm.meta_key = %s AND pm.meta_value != %s AND pm2.meta_id IS NULL",
			'_scorebox_rating_sort',
			'_scorebox_review',
			''
		)
	);

	foreach ( $post_ids as $pid ) {
		$review = scorebox_get_review( (int) $pid );
		if ( ! $review ) {
			continue;
		}
		$rating_type = ! empty( $review['rating_type'] ) ? $review['rating_type'] : 'star';
		$sort_val    = scorebox_normalize_to_star_scale( $review['rating'], $rating_type );
		update_post_meta( (int) $pid, '_scorebox_rating_sort', $sort_val );
	}

	update_option( 'scorebox_rating_sort_backfilled', 1 );
}
add_action( 'admin_init', 'scorebox_maybe_backfill_rating_sort' );

function scorebox_register_post_columns() {
	$options    = get_option( 'scorebox_settings', array() );
	$post_types = apply_filters( 'scorebox_post_types', isset( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' ) );

	foreach ( $post_types as $post_type ) {
		add_filter( "manage_{$post_type}_posts_columns", 'scorebox_add_post_columns' );
		add_action( "manage_{$post_type}_posts_custom_column", 'scorebox_render_post_column', 10, 2 );
		add_filter( "manage_edit-{$post_type}_sortable_columns", 'scorebox_sortable_columns' );
	}
}
add_action( 'admin_init', 'scorebox_register_post_columns' );
add_action( 'pre_get_posts', 'scorebox_column_orderby' );

// =========================================================================
// Admin assets
// =========================================================================

function scorebox_admin_enqueue( $hook_suffix ) {
	// Admin CSS on all ScoreBox pages.
	$scorebox_pages = array(
		'toplevel_page_scorebox',
		'scorebox_page_scorebox-settings',
		'scorebox_page_scorebox-migration',
	);

	if ( in_array( $hook_suffix, $scorebox_pages, true ) ) {
		wp_enqueue_style(
			'scorebox-admin',
			SCOREBOX_URL . 'assets/css/admin.css',
			array(),
			SCOREBOX_VERSION
		);
	}

	// Color picker + frontend preview styles on settings page.
	if ( 'scorebox_page_scorebox-settings' === $hook_suffix ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'scorebox-frontend',
			SCOREBOX_URL . 'assets/css/review-box.css',
			array(),
			SCOREBOX_VERSION
		);
		wp_enqueue_script(
			'scorebox-admin-settings',
			SCOREBOX_URL . 'assets/js/admin-settings.js',
			array( 'wp-color-picker', 'jquery' ),
			SCOREBOX_VERSION,
			true
		);
		wp_localize_script(
			'scorebox-admin-settings',
			'scoreboxPreview',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'scorebox_preview' ),
			)
		);
	}

	// Post list column styles.
	if ( 'edit.php' === $hook_suffix ) {
		wp_enqueue_style(
			'scorebox-admin-columns',
			SCOREBOX_URL . 'assets/css/admin-columns.css',
			array(),
			SCOREBOX_VERSION
		);
	}
}
add_action( 'admin_enqueue_scripts', 'scorebox_admin_enqueue' );

// =========================================================================
// Plugin action links
// =========================================================================

function scorebox_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=scorebox-settings' ),
		__( 'Settings', 'scorebox' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . SCOREBOX_BASENAME, 'scorebox_plugin_action_links' );
