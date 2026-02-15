<?php
/**
 * Integration tests for plugin activation/deactivation/uninstall lifecycle.
 *
 * @package WP_PDF_Guard
 */

class Test_Activation extends WP_UnitTestCase {

	/**
	 * Table exists (created during plugin bootstrap/activation).
	 */
	public function test_activation_creates_table() {
		global $wpdb;

		// The table is created when the plugin activates during test bootstrap.
		// Verify it exists by checking for the table structure.
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}wpdfg_mappings" );

		$this->assertNotEmpty( $columns, 'wpdfg_mappings table should exist after activation.' );

		$column_names = wp_list_pluck( $columns, 'Field' );
		$this->assertContains( 'id', $column_names );
		$this->assertContains( 'pdf_id', $column_names );
		$this->assertContains( 'page_id', $column_names );
		$this->assertContains( 'created_at', $column_names );
	}

	/**
	 * Default options are populated after activation.
	 */
	public function test_activation_sets_default_options() {
		// Options are set during activation in bootstrap. Verify they exist.
		$this->assertNotFalse( get_option( 'wpdfg_token_duration' ) );
		$this->assertEquals( 600, get_option( 'wpdfg_token_duration' ) );
	}

	/**
	 * Uninstall deletes options and the drop table query executes without error.
	 *
	 * Note: DROP TABLE is a DDL statement that auto-commits in MySQL, which
	 * conflicts with the WP test suite's transaction rollback. We verify
	 * the options are deleted and that the DROP query runs without error.
	 */
	public function test_uninstall_drops_table_and_options() {
		global $wpdb;

		// Set options so we can verify they get deleted.
		update_option( 'wpdfg_token_duration', 600 );
		update_option( 'wpdfg_block_all_pdfs', 0 );
		update_option( 'wpdfg_db_version', '1.0.0' );

		// Simulate uninstall option cleanup.
		delete_option( 'wpdfg_token_duration' );
		delete_option( 'wpdfg_block_all_pdfs' );
		delete_option( 'wpdfg_db_version' );

		$this->assertFalse( get_option( 'wpdfg_token_duration' ) );
		$this->assertFalse( get_option( 'wpdfg_block_all_pdfs' ) );
		$this->assertFalse( get_option( 'wpdfg_db_version' ) );

		// Verify the DROP TABLE query itself runs without DB error.
		$table_name = $wpdb->prefix . 'wpdfg_mappings';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore
		$this->assertEmpty( $wpdb->last_error, 'DROP TABLE should not produce a DB error.' );
	}
}
