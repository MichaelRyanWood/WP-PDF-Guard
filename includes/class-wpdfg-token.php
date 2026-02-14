<?php
/**
 * HMAC-SHA256 token generation and validation.
 *
 * Token format: base64( attachment_id | expiration | hmac )
 * HMAC covers: attachment_id + expiration + client IP
 * Signed with key derived from WordPress AUTH_KEY + AUTH_SALT.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDFG_Token {

	/**
	 * Separator used in token payload.
	 */
	const SEPARATOR = '|';

	/**
	 * Generate a signed token for a given attachment ID.
	 *
	 * @param int $attachment_id The PDF attachment ID.
	 * @return string Base64-encoded token.
	 */
	public static function generate( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$duration      = absint( get_option( 'wpdfg_token_duration', 600 ) );
		$expiration    = time() + $duration;

		$hmac = self::compute_hmac( $attachment_id, $expiration );

		$payload = $attachment_id . self::SEPARATOR . $expiration . self::SEPARATOR . $hmac;

		return base64_encode( $payload );
	}

	/**
	 * Set a signed cookie for a given attachment ID.
	 *
	 * @param int $attachment_id The PDF attachment ID.
	 */
	public static function set_cookie( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$token         = self::generate( $attachment_id );
		$duration      = absint( get_option( 'wpdfg_token_duration', 600 ) );
		$cookie_name   = 'wpdfg_token_' . $attachment_id;

		$secure = is_ssl();

		setcookie(
			$cookie_name,
			$token,
			array(
				'expires'  => time() + $duration,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly'  => true,
				'samesite' => 'Lax',
			)
		);

		// Make the cookie available in the current request too.
		$_COOKIE[ $cookie_name ] = $token;
	}

	/**
	 * Validate a token for a given attachment ID.
	 *
	 * @param int $attachment_id The PDF attachment ID.
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$cookie_name   = 'wpdfg_token_' . $attachment_id;

		if ( empty( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}

		$token = $_COOKIE[ $cookie_name ];

		// Reject oversized tokens (max reasonable size ~256 bytes).
		if ( strlen( $token ) > 512 ) {
			return false;
		}

		$decoded = base64_decode( $token, true );
		if ( false === $decoded ) {
			return false;
		}

		$parts = explode( self::SEPARATOR, $decoded );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		list( $token_id, $token_expiration, $token_hmac ) = $parts;

		$token_id         = absint( $token_id );
		$token_expiration = absint( $token_expiration );

		// Check attachment ID matches.
		if ( $token_id !== $attachment_id ) {
			return false;
		}

		// Check expiration.
		if ( time() > $token_expiration ) {
			return false;
		}

		// Recompute HMAC and compare using timing-safe function.
		$expected_hmac = self::compute_hmac( $token_id, $token_expiration );

		return hash_equals( $expected_hmac, $token_hmac );
	}

	/**
	 * Compute HMAC-SHA256 for a token payload.
	 *
	 * @param int $attachment_id The PDF attachment ID.
	 * @param int $expiration    The expiration timestamp.
	 * @return string Hex-encoded HMAC.
	 */
	private static function compute_hmac( $attachment_id, $expiration ) {
		$data = $attachment_id . self::SEPARATOR . $expiration . self::SEPARATOR . self::get_client_ip();
		$key  = self::get_signing_key();

		return hash_hmac( 'sha256', $data, $key );
	}

	/**
	 * Get the signing key derived from WordPress salts.
	 *
	 * @return string Signing key.
	 */
	public static function get_signing_key() {
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpdfg-fallback-key';
		$auth_salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'wpdfg-fallback-salt';

		return hash( 'sha256', 'wpdfg_token_key' . $auth_key . $auth_salt );
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string Client IP.
	 */
	private static function get_client_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '0.0.0.0';
	}
}
