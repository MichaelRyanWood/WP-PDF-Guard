<?php
/**
 * Security tests for CSRF protection.
 *
 * @package WP_PDF_Guard
 */

class Test_CSRF extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Nonce from different action fails verification.
	 */
	public function test_save_mapping_wrong_nonce_rejected() {
		$wrong_nonce = wp_create_nonce( 'wrong_action' );
		$this->assertFalse( wp_verify_nonce( $wrong_nonce, 'wpdfg_admin' ) );
	}

	/**
	 * Nonce from different action fails for delete.
	 */
	public function test_delete_mapping_wrong_nonce_rejected() {
		$wrong_nonce = wp_create_nonce( 'some_other_action' );
		$this->assertFalse( wp_verify_nonce( $wrong_nonce, 'wpdfg_admin' ) );
	}

	/**
	 * Wrong nonce fails for settings update.
	 */
	public function test_settings_update_wrong_nonce_rejected() {
		$wrong_nonce = wp_create_nonce( 'not_wpdfg_settings' );
		$this->assertFalse( wp_verify_nonce( $wrong_nonce, 'wpdfg_settings-options' ) );
	}

	/**
	 * Correct nonce verifies successfully.
	 */
	public function test_correct_nonce_accepted() {
		$nonce = wp_create_nonce( 'wpdfg_admin' );
		$this->assertNotFalse( wp_verify_nonce( $nonce, 'wpdfg_admin' ) );
	}

	/**
	 * Old/expired nonce fails (simulated by verifying a random string).
	 */
	public function test_expired_nonce_rejected() {
		$fake_expired = 'expired_nonce_' . time();
		$this->assertFalse( wp_verify_nonce( $fake_expired, 'wpdfg_admin' ) );
	}
}
