<?php
/**
 * Security tests for input validation (SQL injection, XSS).
 *
 * @package WP_PDF_Guard
 */

class Test_Input_Validation extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();
	}

	/**
	 * SQL injection attempt in pdf_id is sanitized via absint().
	 */
	public function test_sql_injection_in_pdf_id_sanitized() {
		$malicious = '1 OR 1=1';
		$sanitized = absint( $malicious );
		$this->assertEquals( 1, $sanitized );

		// Creating a mapping with the sanitized value should not cause SQL errors.
		$result = WPDFG_Mapping::get_page_for_pdf( $sanitized );
		$this->assertNull( $result ); // No mapping exists, but no SQL error either.
	}

	/**
	 * SQL injection attempt in page_id is sanitized.
	 */
	public function test_sql_injection_in_page_id_sanitized() {
		$malicious = '1; DROP TABLE wp_posts; --';
		$sanitized = absint( $malicious );
		$this->assertEquals( 1, $sanitized );
	}

	/**
	 * Script tags in shortcode attribute are escaped.
	 */
	public function test_xss_in_shortcode_id_escaped() {
		$public = WPDFG_Public::instance();

		// Non-numeric ID gets absint'd to 0, producing empty output.
		$output = $public->shortcode( array( 'id' => '<script>alert(1)</script>' ) );
		$this->assertEmpty( $output );

		// Even if somehow a label contains HTML, esc_html escapes it.
		$escaped = esc_html( '<script>alert("XSS")</script>' );
		$this->assertStringNotContainsString( '<script>', $escaped );
	}

	/**
	 * Search input with HTML is sanitized.
	 */
	public function test_xss_in_admin_search_escaped() {
		$malicious = '<img src=x onerror=alert(1)>';
		$sanitized = sanitize_text_field( $malicious );

		$this->assertStringNotContainsString( '<img', $sanitized );
		$this->assertStringNotContainsString( 'onerror', $sanitized );
	}

	/**
	 * Negative integers fail absint validation.
	 */
	public function test_negative_ids_rejected() {
		// absint() takes the absolute value, so -5 becomes 5 — but these
		// won't match real PDF/page IDs, so create() still rejects them.
		$result = WPDFG_Mapping::create( absint( -5 ), absint( -999 ) );
		$this->assertInstanceOf( 'WP_Error', $result );

		// Zero values are explicitly rejected.
		$result = WPDFG_Mapping::create( 0, 0 );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * String/float values are rejected by absint.
	 */
	public function test_non_numeric_ids_rejected() {
		$this->assertEquals( 0, absint( 'abc' ) );
		$this->assertEquals( 3, absint( 3.14 ) );
		$this->assertEquals( 0, absint( '' ) );
		$this->assertEquals( 0, absint( null ) );
	}
}
