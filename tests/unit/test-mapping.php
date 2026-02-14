<?php
/**
 * Unit tests for WPDFG_Mapping.
 *
 * @package WP_PDF_Guard
 */

class Test_WPDFG_Mapping extends WP_UnitTestCase {

	private $pdf_id;
	private $page_id;

	public function setUp(): void {
		parent::setUp();

		// Ensure mappings table exists.
		require_once WPDFG_PLUGIN_DIR . 'includes/class-wpdfg-activator.php';
		WPDFG_Activator::activate();

		// Create a PDF attachment.
		$this->pdf_id = self::factory()->attachment->create( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'Test PDF',
			'file'           => 'test-document.pdf',
		) );

		// Create a page.
		$this->page_id = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Test Product Page',
		) );
	}

	/**
	 * Valid PDF + page IDs creates a mapping row.
	 */
	public function test_create_mapping_success() {
		$result = WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * Same pair can't be added twice.
	 */
	public function test_create_mapping_duplicate_rejected() {
		WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
		$result = WPDFG_Mapping::create( $this->pdf_id, $this->page_id );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'duplicate', $result->get_error_code() );
	}

	/**
	 * Non-PDF attachment ID fails.
	 */
	public function test_create_mapping_invalid_pdf_rejected() {
		$image_id = self::factory()->attachment->create( array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Test Image',
		) );

		$result = WPDFG_Mapping::create( $image_id, $this->page_id );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'not_pdf', $result->get_error_code() );
	}

	/**
	 * IDs that don't exist fail.
	 */
	public function test_create_mapping_nonexistent_ids_rejected() {
		$result = WPDFG_Mapping::create( 999999, $this->page_id );
		$this->assertInstanceOf( 'WP_Error', $result );

		$result = WPDFG_Mapping::create( $this->pdf_id, 999999 );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Correct lookup for mapped PDF.
	 */
	public function test_get_page_for_pdf_returns_correct_page() {
		WPDFG_Mapping::create( $this->pdf_id, $this->page_id );

		$page = WPDFG_Mapping::get_page_for_pdf( $this->pdf_id );
		$this->assertEquals( $this->page_id, $page );
	}

	/**
	 * Unmapped PDF returns null.
	 */
	public function test_get_page_for_pdf_returns_null_for_unmapped() {
		$this->assertNull( WPDFG_Mapping::get_page_for_pdf( 999999 ) );
	}

	/**
	 * Multiple PDFs per page returned.
	 */
	public function test_get_pdfs_for_page_returns_all_mapped() {
		$pdf2 = self::factory()->attachment->create( array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => 'Test PDF 2',
			'file'           => 'test-document-2.pdf',
		) );

		WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
		WPDFG_Mapping::create( $pdf2, $this->page_id );

		$pdfs = WPDFG_Mapping::get_pdfs_for_page( $this->page_id );

		$this->assertCount( 2, $pdfs );
		$this->assertContains( $this->pdf_id, $pdfs );
		$this->assertContains( $pdf2, $pdfs );
	}

	/**
	 * Deletion works.
	 */
	public function test_delete_mapping_removes_row() {
		$mapping_id = WPDFG_Mapping::create( $this->pdf_id, $this->page_id );
		$this->assertTrue( WPDFG_Mapping::delete( $mapping_id ) );
		$this->assertNull( WPDFG_Mapping::get_page_for_pdf( $this->pdf_id ) );
	}

	/**
	 * Pagination returns correct subset.
	 */
	public function test_list_all_with_pagination() {
		// Create 3 mappings.
		for ( $i = 0; $i < 3; $i++ ) {
			$pdf = self::factory()->attachment->create( array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => "PDF $i",
				'file'           => "doc-$i.pdf",
			) );
			WPDFG_Mapping::create( $pdf, $this->page_id );
		}

		$result = WPDFG_Mapping::list_all( 2, 1 );
		$this->assertCount( 2, $result['items'] );
		$this->assertEquals( 3, $result['total'] );

		$result = WPDFG_Mapping::list_all( 2, 2 );
		$this->assertCount( 1, $result['items'] );
	}
}
