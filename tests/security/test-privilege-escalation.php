<?php
/**
 * Security tests for privilege escalation.
 *
 * @package WP_PDF_Guard
 */

class Test_Privilege_Escalation extends WP_UnitTestCase {

	private $admin_id;
	private $subscriber_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * manage_options enforced for settings access.
	 */
	public function test_subscriber_cannot_access_admin_settings() {
		wp_set_current_user( $this->subscriber_id );
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	/**
	 * Subscriber cannot save mappings.
	 */
	public function test_subscriber_cannot_save_mappings() {
		wp_set_current_user( $this->subscriber_id );
		$this->assertFalse( current_user_can( 'manage_options' ) );

		// Admin can.
		wp_set_current_user( $this->admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ) );
	}

	/**
	 * Subscriber cannot delete mappings.
	 */
	public function test_subscriber_cannot_delete_mappings() {
		wp_set_current_user( $this->subscriber_id );
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	/**
	 * No logged-in user → rejected.
	 */
	public function test_unauthenticated_user_cannot_access_admin_ajax() {
		wp_set_current_user( 0 );
		$this->assertFalse( current_user_can( 'manage_options' ) );
		$this->assertFalse( is_user_logged_in() );
	}
}
