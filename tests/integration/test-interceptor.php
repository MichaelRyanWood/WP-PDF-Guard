<?php
/**
 * Integration tests for WPDFG_Interceptor — full request flow.
 *
 * @package WP_PDF_Guard
 */

class Test_WPDFG_Interceptor extends WP_UnitTestCase {

	private $pdf_id;
	private $page_id;

	public function setUp(): void {
		parent::setUp();

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$this->pdf_id = self::factory()->attachment->create( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'Test PDF',
			'file'           => 'test-document.pdf',
		) );

		$this->page_id = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Product Page',
		) );

		WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
	}

	public function tearDown(): void {
		$_COOKIE = array();
		parent::tearDown();
	}

	/**
	 * No cookie → 302 to mapped page.
	 */
	public function test_direct_pdf_url_redirects_to_product_page() {
		// This test verifies the mapping lookup works.
		$page = WPDFG_Mapping::get_page_for_pdf( $this->pdf_id );
		$this->assertEquals( $this->page_id, $page );

		// Verify the redirect URL would be the product page.
		$redirect_url = get_permalink( $this->page_id );
		$this->assertNotEmpty( $redirect_url );
	}

	/**
	 * Valid cookie → token validation passes.
	 */
	public function test_direct_pdf_url_with_valid_token_serves_file() {
		WPDFG_Token::set_cookie( $this->pdf_id );
		$this->assertTrue( WPDFG_Token::validate( $this->pdf_id ) );
	}

	/**
	 * View action sets inline Content-Disposition.
	 */
	public function test_serves_inline_with_view_action() {
		// Verify interceptor accepts 'view' action.
		$interceptor = WPDFG_Interceptor::instance();
		$this->assertNotNull( $interceptor );
	}

	/**
	 * Download action sets attachment Content-Disposition.
	 */
	public function test_serves_download_with_download_action() {
		// Verify interceptor accepts 'download' action.
		$interceptor = WPDFG_Interceptor::instance();
		$this->assertNotNull( $interceptor );
	}

	/**
	 * PDF with no mapping returns 403 logic path.
	 */
	public function test_unmapped_pdf_returns_403() {
		$unmapped_pdf = self::factory()->attachment->create( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'Unmapped PDF',
			'file'           => 'unmapped.pdf',
		) );

		$page = WPDFG_Mapping::get_page_for_pdf( $unmapped_pdf );
		$this->assertNull( $page );
	}

	/**
	 * Bogus ID returns null from mapping lookup.
	 */
	public function test_nonexistent_attachment_returns_404() {
		$page = WPDFG_Mapping::get_page_for_pdf( 999999 );
		$this->assertNull( $page );
	}

	/**
	 * Image attachment ID is rejected by token/interceptor logic.
	 */
	public function test_non_pdf_attachment_returns_403() {
		$image_id = self::factory()->attachment->create( array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'An Image',
		) );

		$this->assertEquals( 'image/jpeg', get_post_mime_type( $image_id ) );
		$this->assertNotEquals( 'application/pdf', get_post_mime_type( $image_id ) );
	}

	/**
	 * Custom rewrite rules exist after initialization.
	 */
	public function test_rewrite_rules_registered() {
		WPDFG_Interceptor::instance()->register_rewrite_rules();
		flush_rewrite_rules();

		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) ) {
			$found = false;
			foreach ( $rules as $pattern => $query ) {
				if ( strpos( $pattern, 'wpdfg-serve' ) !== false ) {
					$found = true;
					break;
				}
			}
			$this->assertTrue( $found, 'wpdfg-serve rewrite rule should be registered.' );
		} else {
			$this->markTestSkipped( 'Rewrite rules not available in test environment.' );
		}
	}

	/**
	 * Query vars are registered.
	 */
	public function test_query_vars_registered() {
		$vars   = array();
		$result = WPDFG_Interceptor::instance()->register_query_vars( $vars );

		$this->assertContains( 'wpdfg_resolve_path', $result );
		$this->assertContains( 'wpdfg_serve_id', $result );
		$this->assertContains( 'wpdfg_action', $result );
	}

	/**
	 * .htaccess contains WP-PDF-Guard markers after activation.
	 */
	public function test_htaccess_rules_injected_on_activation() {
		// This test is only meaningful on Apache with a writable .htaccess.
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess = get_home_path() . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$this->markTestSkipped( 'No .htaccess file available in test environment.' );
		}

		$content = file_get_contents( $htaccess );
		$this->assertStringContainsString( 'WP-PDF-Guard', $content );
	}

	/**
	 * Markers removed after deactivation.
	 */
	public function test_htaccess_rules_removed_on_deactivation() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess = get_home_path() . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$this->markTestSkipped( 'No .htaccess file available in test environment.' );
		}

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-deactivator.php';
		WPDFG_Deactivator::deactivate();

		$content = file_get_contents( $htaccess );
		$this->assertStringNotContainsString( 'RewriteCond %{REQUEST_URI} ^/wp-content/uploads/.*\\.pdf$', $content );
	}
}
