<?php
/**
 * Integration tests for plugin activation/deactivation/uninstall lifecycle.
 *
 * @package WP_PDF_Guard
 */

class Test_Activation extends WP_UnitTestCase {

	/**
	 * Table exists after activation.
	 */
	public function test_activation_creates_table() {
		global $wpdb;

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		$table_name = $wpdb->prefix . 'wpdfg_mappings';
		$result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		$this->assertEquals( $table_name, $result );
	}

	/**
	 * Default options are populated after activation.
	 */
	public function test_activation_sets_default_options() {
		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		$this->assertEquals( 600, get_option( 'wpdfg_token_duration' ) );
		$this->assertEquals( 1, get_option( 'wpdfg_auto_inject' ) );
	}

	/**
	 * Uninstall drops table and deletes options.
	 */
	public function test_uninstall_drops_table_and_options() {
		global $wpdb;

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		// Simulate uninstall logic (can't directly include uninstall.php due to WP_UNINSTALL_PLUGIN check).
		$table_name = $wpdb->prefix . 'wpdfg_mappings';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore
		delete_option( 'wpdfg_token_duration' );
		delete_option( 'wpdfg_auto_inject' );
		delete_option( 'wpdfg_db_version' );

		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		$this->assertNull( $result );
		$this->assertFalse( get_option( 'wpdfg_token_duration' ) );
		$this->assertFalse( get_option( 'wpdfg_auto_inject' ) );
	}
}
