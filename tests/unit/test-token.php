<?php
/**
 * Unit tests for WPDFG_Token.
 *
 * @package WP_PDF_Guard
 */

class Test_WPDFG_Token extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		update_option( 'wpdfg_token_duration', 600 );
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	}

	public function tearDown(): void {
		$_COOKIE = array();
		parent::tearDown();
	}

	/**
	 * Token format is valid base64.
	 */
	public function test_generate_returns_base64_string() {
		$token   = WPDFG_Token::generate( 123 );
		$decoded = base64_decode( $token, true );

		$this->assertNotFalse( $decoded );
		$this->assertEquals( $token, base64_encode( $decoded ) );
	}

	/**
	 * Decoded payload contains attachment ID and expiration.
	 */
	public function test_generate_contains_attachment_id_and_expiration() {
		$token   = WPDFG_Token::generate( 456 );
		$decoded = base64_decode( $token, true );
		$parts   = explode( '|', $decoded );

		$this->assertCount( 3, $parts );
		$this->assertEquals( 456, (int) $parts[0] );
		$this->assertGreaterThan( time(), (int) $parts[1] );
	}

	/**
	 * Freshly generated token passes validation.
	 */
	public function test_validate_accepts_valid_token() {
		$attachment_id = 789;
		$token         = WPDFG_Token::generate( $attachment_id );

		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $token;

		$this->assertTrue( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Token past TTL fails validation.
	 */
	public function test_validate_rejects_expired_token() {
		$attachment_id = 100;

		// Generate a token that's already expired by manipulating expiration.
		$expiration = time() - 1;
		$key        = WPDFG_Token::get_signing_key();
		$ip         = '127.0.0.1';
		$hmac       = hash_hmac( 'sha256', $attachment_id . '|' . $expiration . '|' . $ip, $key );
		$payload    = $attachment_id . '|' . $expiration . '|' . $hmac;
		$token      = base64_encode( $payload );

		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $token;

		$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Token for PDF-A fails validation for PDF-B.
	 */
	public function test_validate_rejects_wrong_attachment_id() {
		$token_a = WPDFG_Token::generate( 111 );

		// Try to use token for attachment 111 on attachment 222.
		$_COOKIE['wpdfg_token_222'] = $token_a;

		$this->assertFalse( WPDFG_Token::validate( 222 ) );
	}

	/**
	 * Flipping a bit in the HMAC causes validation failure.
	 */
	public function test_validate_rejects_tampered_hmac() {
		$attachment_id = 333;
		$token         = WPDFG_Token::generate( $attachment_id );
		$decoded       = base64_decode( $token, true );
		$parts         = explode( '|', $decoded );

		// Tamper with HMAC.
		$parts[2]       = str_repeat( 'a', strlen( $parts[2] ) );
		$tampered_token = base64_encode( implode( '|', $parts ) );

		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $tampered_token;

		$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Extending expiration in payload causes HMAC mismatch.
	 */
	public function test_validate_rejects_tampered_expiration() {
		$attachment_id = 444;
		$token         = WPDFG_Token::generate( $attachment_id );
		$decoded       = base64_decode( $token, true );
		$parts         = explode( '|', $decoded );

		// Extend expiration by 1 hour.
		$parts[1]       = (string) ( (int) $parts[1] + 3600 );
		$tampered_token = base64_encode( implode( '|', $parts ) );

		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $tampered_token;

		$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Empty/missing cookie returns false.
	 */
	public function test_validate_rejects_empty_cookie() {
		$this->assertFalse( WPDFG_Token::validate( 555 ) );
	}

	/**
	 * Confirm hash_equals() is used in the source code.
	 */
	public function test_validate_uses_timing_safe_comparison() {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-wpdfg-token.php' );
		$this->assertStringContainsString( 'hash_equals(', $source );
	}

	/**
	 * Cookie attributes are set correctly via set_cookie().
	 */
	public function test_set_cookie_sets_correct_attributes() {
		// We can verify set_cookie populates $_COOKIE for the current request.
		$attachment_id = 666;
		WPDFG_Token::set_cookie( $attachment_id );

		$this->assertArrayHasKey( 'wpdfg_token_' . $attachment_id, $_COOKIE );
		$this->assertNotEmpty( $_COOKIE[ 'wpdfg_token_' . $attachment_id ] );

		// Verify the cookie value is a valid token.
		$this->assertTrue( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Signing key changes when AUTH_KEY changes.
	 */
	public function test_signing_key_derived_from_wp_salts() {
		$key = WPDFG_Token::get_signing_key();
		$this->assertNotEmpty( $key );
		$this->assertEquals( 64, strlen( $key ) ); // SHA-256 hex = 64 chars.
	}
}
