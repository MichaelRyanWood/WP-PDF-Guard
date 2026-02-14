<?php
/**
 * Security tests for token abuse scenarios.
 *
 * @package WP_PDF_Guard
 */

class Test_Token_Abuse extends WP_UnitTestCase {

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
	 * Crafted base64 with wrong HMAC fails.
	 */
	public function test_forged_token_with_valid_format_rejected() {
		$attachment_id = 100;
		$expiration    = time() + 600;
		$fake_hmac     = hash( 'sha256', 'totally-fake-data' );

		$payload = $attachment_id . '|' . $expiration . '|' . $fake_hmac;
		$token   = base64_encode( $payload );

		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $token;

		$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Captured valid token fails after TTL.
	 */
	public function test_token_replay_after_expiry_rejected() {
		$attachment_id = 200;
		$key           = WPDFG_Token::get_signing_key();
		$expiration    = time() - 1; // Already expired.
		$ip            = '127.0.0.1';

		$hmac    = hash_hmac( 'sha256', $attachment_id . '|' . $expiration . '|' . $ip, $key );
		$payload = $attachment_id . '|' . $expiration . '|' . $hmac;
		$token   = base64_encode( $payload );

		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $token;

		$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Cross-PDF token reuse fails.
	 */
	public function test_token_for_pdf_a_rejected_for_pdf_b() {
		$token_a = WPDFG_Token::generate( 300 );

		$_COOKIE['wpdfg_token_301'] = $token_a;

		$this->assertFalse( WPDFG_Token::validate( 301 ) );
	}

	/**
	 * Extending expiry breaks HMAC.
	 */
	public function test_token_with_manipulated_expiry_rejected() {
		$attachment_id = 400;
		$token         = WPDFG_Token::generate( $attachment_id );
		$decoded       = base64_decode( $token, true );
		$parts         = explode( '|', $decoded );

		// Extend by 1 year.
		$parts[1] = (string) ( (int) $parts[1] + 365 * 86400 );
		$tampered = base64_encode( implode( '|', $parts ) );

		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $tampered;

		$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * Token from IP-A fails from IP-B.
	 */
	public function test_token_with_different_ip_rejected() {
		$attachment_id = 500;

		// Generate token with IP 10.0.0.1.
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$token                  = WPDFG_Token::generate( $attachment_id );

		// Validate from a different IP.
		$_SERVER['REMOTE_ADDR']                     = '10.0.0.2';
		$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $token;

		$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
	}

	/**
	 * 1000 random tokens all fail (statistical assertion).
	 */
	public function test_brute_force_token_infeasible() {
		$attachment_id = 600;

		for ( $i = 0; $i < 1000; $i++ ) {
			$random_token = base64_encode( random_bytes( 64 ) );

			$_COOKIE[ 'wpdfg_token_' . $attachment_id ] = $random_token;

			$this->assertFalse( WPDFG_Token::validate( $attachment_id ) );
		}
	}

	/**
	 * Empty token rejected.
	 */
	public function test_empty_token_rejected() {
		$_COOKIE['wpdfg_token_700'] = '';
		$this->assertFalse( WPDFG_Token::validate( 700 ) );
	}

	/**
	 * Malformed base64 rejected.
	 */
	public function test_malformed_base64_rejected() {
		$_COOKIE['wpdfg_token_800'] = '!!!not-base64!!!';
		$this->assertFalse( WPDFG_Token::validate( 800 ) );
	}

	/**
	 * Oversized cookie value handled gracefully.
	 */
	public function test_extremely_long_token_rejected() {
		$_COOKIE['wpdfg_token_900'] = str_repeat( 'A', 10000 );
		$this->assertFalse( WPDFG_Token::validate( 900 ) );
	}
}
