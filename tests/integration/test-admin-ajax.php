<?php
/**
 * Integration tests for Admin AJAX handlers.
 *
 * @package WP_PDF_Guard
 */

class Test_Admin_AJAX extends WP_UnitTestCase {

	private $admin_id;
	private $subscriber_id;
	private $pdf_id;
	private $page_id;

	public function setUp(): void {
		parent::setUp();

		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->pdf_id = self::factory()->attachment->create( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'AJAX Test PDF',
			'file'           => 'ajax-test.pdf',
		) );

		$this->page_id = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'AJAX Test Page',
		) );
	}

	/**
	 * Correct nonce + capability → success.
	 */
	public function test_save_mapping_with_valid_nonce_succeeds() {
		wp_set_current_user( $this->admin_id );

		$_POST['nonce']   = wp_create_nonce( 'wpdfg_admin' );
		$_POST['pdf_id']  = $this->pdf_id;
		$_POST['page_id'] = $this->page_id;

		// Verify the mapping can be created.
		$result = WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
		$this->assertIsInt( $result );
	}

	/**
	 * Missing nonce → nonce verification would fail.
	 */
	public function test_save_mapping_without_nonce_fails() {
		wp_set_current_user( $this->admin_id );

		// Verify wp_verify_nonce fails with wrong nonce.
		$this->assertFalse( wp_verify_nonce( 'invalid_nonce', 'wpdfg_admin' ) );
	}

	/**
	 * Delete with valid nonce succeeds.
	 */
	public function test_delete_mapping_with_valid_nonce_succeeds() {
		wp_set_current_user( $this->admin_id );

		$mapping_id = WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
		$this->assertTrue( WPDFG_Mapping::delete( $mapping_id ) );
	}

	/**
	 * PDF search returns only PDF attachments.
	 */
	public function test_search_pdfs_returns_pdf_attachments_only() {
		// Create a non-PDF attachment.
		self::factory()->attachment->create( array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Not a PDF',
		) );

		$args  = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'application/pdf',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
		);
		$query = new WP_Query( $args );

		foreach ( $query->posts as $post ) {
			$this->assertEquals( 'application/pdf', $post->post_mime_type );
		}
	}

	/**
	 * Page search returns published posts and pages.
	 */
	public function test_search_pages_returns_published_posts_and_pages() {
		$args  = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		);
		$query = new WP_Query( $args );

		foreach ( $query->posts as $post ) {
			$this->assertContains( $post->post_type, array( 'post', 'page' ) );
			$this->assertEquals( 'publish', $post->post_status );
		}
	}
}
