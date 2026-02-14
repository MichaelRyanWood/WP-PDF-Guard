<?php
/**
 * Frontend functionality: shortcode, auto-inject, assets.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDFG_Public {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_shortcode( 'pdf_guard_download', array( $this, 'shortcode' ) );
		add_action( 'template_redirect', array( $this, 'auto_inject_cookies' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Shortcode: [pdf_guard_download id="123"]
	 *
	 * Sets the access cookie and renders View/Download links.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'label' => '',
			),
			$atts,
			'pdf_guard_download'
		);

		$attachment_id = absint( $atts['id'] );

		if ( ! $attachment_id ) {
			return '';
		}

		// Verify it's a valid PDF attachment.
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
			return '';
		}

		// Set the access cookie.
		WPDFG_Token::set_cookie( $attachment_id );

		$title = ! empty( $atts['label'] ) ? $atts['label'] : get_the_title( $attachment_id );
		$title = esc_html( $title );

		$view_url     = home_url( '/wpdfg-serve/' . $attachment_id . '/?wpdfg_action=view' );
		$download_url = home_url( '/wpdfg-serve/' . $attachment_id . '/?wpdfg_action=download' );

		$output  = '<div class="wpdfg-download-links">';
		$output .= '<span class="wpdfg-pdf-title">' . $title . '</span>';
		$output .= ' &mdash; ';
		$output .= '<a href="' . esc_url( $view_url ) . '" class="wpdfg-link wpdfg-view" target="_blank" rel="noopener">';
		$output .= esc_html__( 'View PDF', 'wp-pdf-guard' );
		$output .= '</a>';
		$output .= ' | ';
		$output .= '<a href="' . esc_url( $download_url ) . '" class="wpdfg-link wpdfg-download">';
		$output .= esc_html__( 'Download PDF', 'wp-pdf-guard' );
		$output .= '</a>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Auto-inject cookies on product pages with mapped PDFs.
	 */
	public function auto_inject_cookies() {
		if ( ! get_option( 'wpdfg_auto_inject', 1 ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$page_id = get_queried_object_id();
		if ( ! $page_id ) {
			return;
		}

		$pdf_ids = WPDFG_Mapping::get_pdfs_for_page( $page_id );

		if ( empty( $pdf_ids ) ) {
			return;
		}

		// Set DONOTCACHEPAGE to prevent caching of cookie-setting pages.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// Send no-store header.
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

		// Set cookies for all mapped PDFs.
		foreach ( $pdf_ids as $pdf_id ) {
			WPDFG_Token::set_cookie( $pdf_id );
		}
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'wpdfg-public',
			WPDFG_PLUGIN_URL . 'public/css/wpdfg-public.css',
			array(),
			WPDFG_VERSION
		);
	}
}
