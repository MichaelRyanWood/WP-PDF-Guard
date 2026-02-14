<?php
/**
 * Request interceptor for PDF file access.
 *
 * Registers rewrite rules and handles PDF serving with token validation.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDFG_Interceptor {

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
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'parse_request', array( $this, 'handle_request' ) );
	}

	/**
	 * Register custom rewrite rules for serving PDFs.
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^wpdfg-serve/([0-9]+)/?$',
			'index.php?wpdfg_serve_id=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query variables.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'wpdfg_resolve_path';
		$vars[] = 'wpdfg_serve_id';
		$vars[] = 'wpdfg_action';
		return $vars;
	}

	/**
	 * Handle incoming requests for PDF files.
	 *
	 * @param WP $wp The WordPress environment instance.
	 */
	public function handle_request( $wp ) {
		// Handle .htaccess rewritten direct PDF URLs.
		if ( ! empty( $wp->query_vars['wpdfg_resolve_path'] ) ) {
			$this->handle_resolve_path( $wp->query_vars['wpdfg_resolve_path'] );
			return;
		}

		// Handle /wpdfg-serve/{id} URLs (from shortcode links).
		if ( ! empty( $wp->query_vars['wpdfg_serve_id'] ) ) {
			$action = isset( $wp->query_vars['wpdfg_action'] ) ? $wp->query_vars['wpdfg_action'] : 'view';
			$this->handle_serve( absint( $wp->query_vars['wpdfg_serve_id'] ), $action );
			return;
		}
	}

	/**
	 * Handle a resolve-path request (direct PDF URL intercepted by .htaccess).
	 *
	 * @param string $path The requested PDF path relative to site root.
	 */
	private function handle_resolve_path( $path ) {
		// Sanitize and validate the path.
		$path = sanitize_text_field( wp_unslash( $path ) );

		// Must end in .pdf.
		if ( ! preg_match( '/\.pdf$/i', $path ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Access denied.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
		}

		// Resolve the path to a real file and validate it's within uploads.
		$upload_dir = wp_upload_dir();
		$basedir    = realpath( $upload_dir['basedir'] );

		// Build the absolute path from the relative uploads path.
		$relative_path = preg_replace( '#^/wp-content/uploads/#', '', $path );
		$absolute_path = realpath( $basedir . '/' . $relative_path );

		// Path traversal protection.
		if ( false === $absolute_path || strpos( $absolute_path, $basedir ) !== 0 ) {
			status_header( 403 );
			wp_die( esc_html__( 'Access denied.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
		}

		// Resolve path to attachment ID (with transient caching).
		$url           = $upload_dir['baseurl'] . '/' . $relative_path;
		$transient_key = 'wpdfg_aid_' . md5( $url );
		$attachment_id = get_transient( $transient_key );

		if ( false === $attachment_id ) {
			$attachment_id = attachment_url_to_postid( $url );
			if ( $attachment_id ) {
				set_transient( $transient_key, $attachment_id, HOUR_IN_SECONDS );
			}
		}

		if ( ! $attachment_id ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not found.', 'wp-pdf-guard' ), '', array( 'response' => 404 ) );
		}

		// Verify it's a PDF attachment.
		if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Access denied.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
		}

		// Check token.
		if ( WPDFG_Token::validate( $attachment_id ) ) {
			$this->serve_file( $absolute_path, 'view' );
			return;
		}

		// No valid token — redirect to mapped page.
		$page_id       = WPDFG_Mapping::get_page_for_pdf( $attachment_id );
		$block_all     = get_option( 'wpdfg_block_all_pdfs', 0 );

		if ( ! $page_id ) {
			if ( ! $block_all ) {
				// Unmapped PDF — allow direct access when "Block All" is off.
				$this->serve_file( $absolute_path, 'view' );
				return;
			}
			status_header( 403 );
			wp_die( esc_html__( 'Access denied. This PDF is not available for direct access.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
		}

		$redirect_url = get_permalink( $page_id );
		if ( $redirect_url ) {
			wp_safe_redirect( $redirect_url, 302 );
			exit;
		}

		status_header( 403 );
		wp_die( esc_html__( 'Access denied.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
	}

	/**
	 * Handle a serve request (from shortcode links: /wpdfg-serve/{id}).
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $action        'view' or 'download'.
	 */
	private function handle_serve( $attachment_id, $action ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not found.', 'wp-pdf-guard' ), '', array( 'response' => 404 ) );
		}

		// Verify it's a PDF.
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Access denied.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
		}

		// Validate token.
		if ( ! WPDFG_Token::validate( $attachment_id ) ) {
			$page_id = WPDFG_Mapping::get_page_for_pdf( $attachment_id );
			if ( $page_id ) {
				wp_safe_redirect( get_permalink( $page_id ), 302 );
				exit;
			}
			status_header( 403 );
			wp_die( esc_html__( 'Access denied. Please visit the product page first.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
		}

		// Get the file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not found.', 'wp-pdf-guard' ), '', array( 'response' => 404 ) );
		}

		// Validate the file is within uploads directory.
		$upload_dir = wp_upload_dir();
		$basedir    = realpath( $upload_dir['basedir'] );
		$real_path  = realpath( $file_path );

		if ( false === $real_path || strpos( $real_path, $basedir ) !== 0 ) {
			status_header( 403 );
			wp_die( esc_html__( 'Access denied.', 'wp-pdf-guard' ), '', array( 'response' => 403 ) );
		}

		$action = in_array( $action, array( 'view', 'download' ), true ) ? $action : 'view';
		$this->serve_file( $real_path, $action );
	}

	/**
	 * Serve a PDF file with appropriate headers.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @param string $action    'view' for inline, 'download' for attachment.
	 */
	private function serve_file( $file_path, $action ) {
		$filename    = basename( $file_path );
		$disposition = ( 'download' === $action ) ? 'attachment' : 'inline';

		// Prevent browser from caching the PDF response.
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'X-Content-Type-Options: nosniff' );

		// Clean output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $file_path );
		exit;
	}
}
