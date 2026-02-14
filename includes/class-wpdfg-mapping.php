<?php
/**
 * PDF-to-page mapping CRUD operations.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDFG_Mapping {

	/**
	 * Get the mappings table name.
	 *
	 * @return string
	 */
	private static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpdfg_mappings';
	}

	/**
	 * Get the product page ID mapped to a PDF attachment.
	 *
	 * @param int $pdf_id Attachment post ID.
	 * @return int|null Page ID or null if not mapped.
	 */
	public static function get_page_for_pdf( $pdf_id ) {
		global $wpdb;

		$pdf_id = absint( $pdf_id );
		$table  = self::table_name();

		$page_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT page_id FROM {$table} WHERE pdf_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pdf_id
			)
		);

		return $page_id ? absint( $page_id ) : null;
	}

	/**
	 * Get all PDF attachment IDs mapped to a given page.
	 *
	 * @param int $page_id Post/page ID.
	 * @return int[] Array of attachment IDs.
	 */
	public static function get_pdfs_for_page( $page_id ) {
		global $wpdb;

		$page_id = absint( $page_id );
		$table   = self::table_name();

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pdf_id FROM {$table} WHERE page_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$page_id
			)
		);

		return array_map( 'absint', $results );
	}

	/**
	 * Create a new mapping.
	 *
	 * @param int $pdf_id  Attachment post ID (must be a PDF).
	 * @param int $page_id Post/page ID.
	 * @return int|WP_Error The mapping ID on success, WP_Error on failure.
	 */
	public static function create( $pdf_id, $page_id ) {
		global $wpdb;

		$pdf_id  = absint( $pdf_id );
		$page_id = absint( $page_id );

		if ( ! $pdf_id || ! $page_id ) {
			return new WP_Error( 'invalid_ids', __( 'Invalid PDF or page ID.', 'wp-pdf-guard' ) );
		}

		// Verify PDF attachment exists and is a PDF.
		$pdf_post = get_post( $pdf_id );
		if ( ! $pdf_post || 'attachment' !== $pdf_post->post_type ) {
			return new WP_Error( 'invalid_pdf', __( 'The specified attachment does not exist.', 'wp-pdf-guard' ) );
		}

		$mime_type = get_post_mime_type( $pdf_id );
		if ( 'application/pdf' !== $mime_type ) {
			return new WP_Error( 'not_pdf', __( 'The specified attachment is not a PDF.', 'wp-pdf-guard' ) );
		}

		// Verify page exists.
		$page_post = get_post( $page_id );
		if ( ! $page_post ) {
			return new WP_Error( 'invalid_page', __( 'The specified page does not exist.', 'wp-pdf-guard' ) );
		}

		$table  = self::table_name();
		$result = $wpdb->insert(
			$table,
			array(
				'pdf_id'     => $pdf_id,
				'page_id'    => $page_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			// Check for duplicate.
			if ( strpos( $wpdb->last_error, 'Duplicate' ) !== false ) {
				return new WP_Error( 'duplicate', __( 'This mapping already exists.', 'wp-pdf-guard' ) );
			}
			return new WP_Error( 'db_error', __( 'Failed to create mapping.', 'wp-pdf-guard' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete a mapping by ID.
	 *
	 * @param int $mapping_id The mapping row ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $mapping_id ) {
		global $wpdb;

		$mapping_id = absint( $mapping_id );
		$table      = self::table_name();

		$result = $wpdb->delete(
			$table,
			array( 'id' => $mapping_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * List all mappings with pagination.
	 *
	 * @param int $per_page Items per page.
	 * @param int $page     Current page number.
	 * @return array {
	 *     @type array  $items Array of mapping objects.
	 *     @type int    $total Total number of mappings.
	 * }
	 */
	public static function list_all( $per_page = 20, $page = 1 ) {
		global $wpdb;

		$table    = self::table_name();
		$per_page = absint( $per_page );
		$page     = max( 1, absint( $page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}
}
