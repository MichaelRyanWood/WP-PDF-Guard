<?php
/**
 * Plugin deactivation handler.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDFG_Deactivator {

	/**
	 * Run deactivation routines.
	 */
	public static function deactivate() {
		self::remove_htaccess_rules();
		flush_rewrite_rules();
	}

	/**
	 * Remove .htaccess rewrite rules.
	 */
	private static function remove_htaccess_rules() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$htaccess_file = get_home_path() . '.htaccess';

		if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
			insert_with_markers( $htaccess_file, 'WP-PDF-Guard', array() );
		}
	}
}
