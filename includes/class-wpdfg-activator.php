<?php
/**
 * Plugin activation handler.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDFG_Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate() {
		self::create_table();
		self::set_default_options();
		self::inject_htaccess_rules();
		flush_rewrite_rules();
	}

	/**
	 * Create the mappings table via dbDelta.
	 */
	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'wpdfg_mappings';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			pdf_id BIGINT UNSIGNED NOT NULL,
			page_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY pdf_unique (pdf_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wpdfg_db_version', WPDFG_VERSION );
	}

	/**
	 * Set default option values.
	 */
	private static function set_default_options() {
		add_option( 'wpdfg_token_duration', 600 );
		add_option( 'wpdfg_block_all_pdfs', 0 );
	}

	/**
	 * Inject .htaccess rewrite rules for PDF interception.
	 */
	public static function inject_htaccess_rules() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$htaccess_file = get_home_path() . '.htaccess';

		if ( ! file_exists( $htaccess_file ) ) {
			return;
		}

		$rules = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_URI} ^/wp-content/uploads/.*\\.pdf$ [NC]',
			'RewriteCond %{REQUEST_FILENAME} -f',
			'RewriteRule ^wp-content/uploads/(.+\\.pdf)$ /index.php?wpdfg_resolve_path=/wp-content/uploads/$1 [L,QSA]',
			'</IfModule>',
		);

		insert_with_markers( $htaccess_file, 'WP-PDF-Guard', $rules );
	}
}
