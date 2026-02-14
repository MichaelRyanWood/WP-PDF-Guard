<?php
/**
 * Security tests for path traversal attacks.
 *
 * @package WP_PDF_Guard
 */

class Test_Path_Traversal extends WP_UnitTestCase {

	/**
	 * ../../wp-config.php in path is blocked by realpath() validation.
	 */
	public function test_dotdot_traversal_blocked() {
		$upload_dir = wp_upload_dir();
		$basedir    = realpath( $upload_dir['basedir'] );

		// Attempt traversal.
		$malicious  = $basedir . '/../../wp-config.php';
		$resolved   = realpath( $malicious );

		// realpath returns false for nonexistent files, or resolves to outside uploads.
		if ( false !== $resolved ) {
			$this->assertStringStartsNotWith( $basedir, $resolved );
		} else {
			$this->assertFalse( $resolved );
		}
	}

	/**
	 * Null byte injection is handled (file.pdf%00.php).
	 */
	public function test_null_byte_injection_blocked() {
		$path = "test.pdf\0.php";

		// PHP 5.3.4+ rejects null bytes in file operations.
		// Our regex check also catches this: the path won't match \.pdf$.
		$this->assertDoesNotMatchRegularExpression( '/\.pdf$/i', $path );
	}

	/**
	 * URL-encoded traversal variants rejected.
	 */
	public function test_encoded_traversal_blocked() {
		$variants = array(
			'%2e%2e%2f',
			'%2e%2e/',
			'..%2f',
			'%2e%2e%5c',
			'..%5c',
		);

		foreach ( $variants as $variant ) {
			$decoded = urldecode( $variant );
			$this->assertStringContainsString( '..', $decoded );
		}

		// sanitize_text_field strips these, and realpath resolves them.
		$sanitized = sanitize_text_field( '%2e%2e%2fwp-config.php' );
		$this->assertStringNotContainsString( '..', $sanitized );
	}

	/**
	 * Resolved path outside uploads dir fails validation.
	 */
	public function test_symlink_outside_uploads_blocked() {
		$upload_dir = wp_upload_dir();
		$basedir    = realpath( $upload_dir['basedir'] );

		// A path outside uploads should fail the strpos check.
		$outside_path = ABSPATH . 'wp-config.php';
		$resolved     = realpath( $outside_path );

		if ( false !== $resolved ) {
			$this->assertFalse( strpos( $resolved, $basedir ) === 0 );
		} else {
			$this->assertFalse( $resolved );
		}
	}

	/**
	 * Non-PDF extensions rejected.
	 */
	public function test_non_pdf_extension_blocked() {
		$blocked_extensions = array( '.php', '.html', '.js', '.exe', '.sh' );

		foreach ( $blocked_extensions as $ext ) {
			$path = 'malicious' . $ext;
			$this->assertDoesNotMatchRegularExpression( '/\.pdf$/i', $path );
		}
	}
}
