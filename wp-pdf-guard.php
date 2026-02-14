<?php
/**
 * Plugin Name:       WP-PDF-Guard
 * Plugin URI:        https://github.com/your-username/wp-pdf-guard
 * Description:       Protects PDF downloads by requiring users to visit a product page (with ads) before accessing the file. Uses stateless HMAC-SHA256 signed cookies.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-pdf-guard
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPDFG_VERSION', '1.0.0' );
define( 'WPDFG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPDFG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPDFG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activation hook.
 */
function wpdfg_activate() {
	require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
	WPDFG_Activator::activate();
}
register_activation_hook( __FILE__, 'wpdfg_activate' );

/**
 * Deactivation hook.
 */
function wpdfg_deactivate() {
	require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-deactivator.php';
	WPDFG_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'wpdfg_deactivate' );

/**
 * Load plugin classes and initialize.
 */
function wpdfg_init() {
	require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-token.php';
	require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-mapping.php';
	require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-interceptor.php';

	WPDFG_Interceptor::instance()->init();

	if ( is_admin() ) {
		require_once WPDFG_PLUGIN_DIR . 'admin/class-wpdfg-admin.php';
		WPDFG_Admin::instance()->init();
	} else {
		require_once WPDFG_PLUGIN_DIR . 'public/class-wpdfg-public.php';
		WPDFG_Public::instance()->init();
	}
}
add_action( 'plugins_loaded', 'wpdfg_init' );

/**
 * Load plugin textdomain.
 */
function wpdfg_load_textdomain() {
	load_plugin_textdomain( 'wp-pdf-guard', false, dirname( WPDFG_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'init', 'wpdfg_load_textdomain' );
