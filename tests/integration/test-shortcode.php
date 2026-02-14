<?php
/**
 * Integration tests for the [pdf_guard_download] shortcode.
 *
 * @package WP_PDF_Guard
 */

class Test_Shortcode extends WP_UnitTestCase {

	private $pdf_id;

	public function setUp(): void {
		parent::setUp();

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$this->pdf_id = self::factory()->attachment->create( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'Shortcode Test PDF',
			'file'           => 'shortcode-test.pdf',
		) );
	}

	public function tearDown(): void {
		$_COOKIE = array();
		parent::tearDown();
	}

	/**
	 * HTML output contains View and Download links.
	 */
	public function test_shortcode_renders_view_and_download_links() {
		$public = WPDFG_Public::instance();
		$output = $public->shortcode( array( 'id' => $this->pdf_id ) );

		$this->assertStringContainsString( 'wpdfg-view', $output );
		$this->assertStringContainsString( 'wpdfg-download', $output );
		$this->assertStringContainsString( 'wpdfg_action=view', $output );
		$this->assertStringContainsString( 'wpdfg_action=download', $output );
	}

	/**
	 * Cookie is set in response when shortcode runs.
	 */
	public function test_shortcode_sets_cookie_for_attachment() {
		$public = WPDFG_Public::instance();
		$public->shortcode( array( 'id' => $this->pdf_id ) );

		$this->assertArrayHasKey( 'wpdfg_token_' . $this->pdf_id, $_COOKIE );
	}

	/**
	 * Invalid ID renders nothing.
	 */
	public function test_shortcode_with_invalid_id_renders_nothing() {
		$public = WPDFG_Public::instance();

		$this->assertEmpty( $public->shortcode( array( 'id' => 0 ) ) );
		$this->assertEmpty( $public->shortcode( array( 'id' => 999999 ) ) );
	}

	/**
	 * Output is escaped — no raw HTML injection possible.
	 */
	public function test_shortcode_escapes_output() {
		$public = WPDFG_Public::instance();
		$output = $public->shortcode( array( 'id' => $this->pdf_id ) );

		// Output should not contain unescaped script tags.
		$this->assertStringNotContainsString( '<script>', $output );

		// All URLs should be escaped.
		$this->assertDoesNotMatchRegularExpression( '/href=["\'][^"\']*javascript:/i', $output );
	}
}
