<?php
/**
 * Uninstall handler.
 *
 * Cleans up plugin data when the plugin is deleted via the WordPress admin.
 * Does NOT run on deactivation — only on full deletion.
 *
 * @package ScoreBox
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options.
delete_option( 'scorebox_settings' );

// Optionally remove all review meta from posts.
// Uncomment the following if you want full cleanup on uninstall.
// global $wpdb;
// $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_scorebox_review' ) );
