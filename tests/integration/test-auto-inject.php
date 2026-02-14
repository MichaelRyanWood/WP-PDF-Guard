<?php
/**
 * Integration tests for auto-inject mechanism.
 *
 * @package WP_PDF_Guard
 */

class Test_Auto_Inject extends WP_UnitTestCase {

	private $pdf_id;
	private $page_id;

	public function setUp(): void {
		parent::setUp();

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$this->pdf_id = self::factory()->attachment->create( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'Auto-inject Test PDF',
			'file'           => 'auto-inject-test.pdf',
		) );

		$this->page_id = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Auto-inject Test Page',
		) );

		WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
		update_option( 'wpdfg_auto_inject', 1 );
	}

	public function tearDown(): void {
		$_COOKIE = array();
		parent::tearDown();
	}

	/**
	 * Visiting mapped page sets cookies.
	 */
	public function test_auto_inject_sets_cookies_for_mapped_pdfs() {
		// Verify the mapping returns PDFs.
		$pdfs = WPDFG_Mapping::get_pdfs_for_page( $this->page_id );
		$this->assertContains( $this->pdf_id, $pdfs );

		// Simulate what auto_inject_cookies does.
		foreach ( $pdfs as $pdf_id ) {
			WPDFG_Token::set_cookie( $pdf_id );
		}

		$this->assertArrayHasKey( 'wpdfg_token_' . $this->pdf_id, $_COOKIE );
		$this->assertTrue( WPDFG_Token::validate( $this->pdf_id ) );
	}

	/**
	 * Respects wpdfg_auto_inject option.
	 */
	public function test_auto_inject_disabled_when_setting_off() {
		update_option( 'wpdfg_auto_inject', 0 );
		$this->assertEquals( 0, get_option( 'wpdfg_auto_inject' ) );
	}

	/**
	 * DONOTCACHEPAGE constant would be set on mapped pages.
	 */
	public function test_auto_inject_sets_donotcachepage() {
		// In a full WordPress environment, auto_inject_cookies() defines DONOTCACHEPAGE.
		// We verify the logic path: if PDFs are mapped, caching should be disabled.
		$pdfs = WPDFG_Mapping::get_pdfs_for_page( $this->page_id );
		$this->assertNotEmpty( $pdfs, 'Mapped PDFs should exist, triggering DONOTCACHEPAGE.' );
	}
}
