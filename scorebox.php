<?php
/**
 * Plugin Name: ScoreBox
 * Plugin URI:  https://jeangalea.com/scorebox/
 * Description: Lightweight review boxes with structured data (JSON-LD) for WordPress. Star ratings, percentage, points, pros/cons, and schema markup that Google understands.
 * Version:     1.2.0
 * Author:      Jean Galea
 * Author URI:  https://jeangalea.com/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: scorebox
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

define( 'SCOREBOX_VERSION', '1.2.0' );
define( 'SCOREBOX_FILE', __FILE__ );
define( 'SCOREBOX_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCOREBOX_URL', plugin_dir_url( __FILE__ ) );
define( 'SCOREBOX_BASENAME', plugin_basename( __FILE__ ) );

// Core includes.
require_once SCOREBOX_DIR . 'includes/meta.php';
require_once SCOREBOX_DIR . 'includes/hooks.php';
require_once SCOREBOX_DIR . 'includes/schema.php';
require_once SCOREBOX_DIR . 'includes/rest-api.php';
require_once SCOREBOX_DIR . 'includes/block.php';
require_once SCOREBOX_DIR . 'includes/render.php';
require_once SCOREBOX_DIR . 'includes/shortcode.php';

// Admin-only includes.
if ( is_admin() ) {
	require_once SCOREBOX_DIR . 'admin/settings.php';
	require_once SCOREBOX_DIR . 'admin/meta-box.php';
	require_once SCOREBOX_DIR . 'admin/migration.php';
}
