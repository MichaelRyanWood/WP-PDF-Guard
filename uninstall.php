<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom table.
$table_name = $wpdb->prefix . 'wpdfg_mappings';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Delete options.
delete_option( 'wpdfg_token_duration' );
delete_option( 'wpdfg_block_all_pdfs' );
delete_option( 'wpdfg_db_version' );

// Remove .htaccess rules.
if ( function_exists( 'insert_with_markers' ) ) {
	$htaccess_file = get_home_path() . '.htaccess';
	if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
		insert_with_markers( $htaccess_file, 'WP-PDF-Guard', array() );
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
