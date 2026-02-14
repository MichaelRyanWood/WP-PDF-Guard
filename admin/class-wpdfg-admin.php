<?php
/**
 * Admin functionality: settings, mapping management, AJAX handlers.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDFG_Admin {

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
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'nginx_notice' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpdfg_save_mapping', array( $this, 'ajax_save_mapping' ) );
		add_action( 'wp_ajax_wpdfg_delete_mapping', array( $this, 'ajax_delete_mapping' ) );
		add_action( 'wp_ajax_wpdfg_search_pdfs', array( $this, 'ajax_search_pdfs' ) );
		add_action( 'wp_ajax_wpdfg_search_pages', array( $this, 'ajax_search_pages' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_menu_pages() {
		add_menu_page(
			__( 'PDF Guard', 'wp-pdf-guard' ),
			__( 'PDF Guard', 'wp-pdf-guard' ),
			'manage_options',
			'wpdfg-mappings',
			array( $this, 'render_mapping_page' ),
			'dashicons-shield',
			80
		);

		add_submenu_page(
			'wpdfg-mappings',
			__( 'PDF Mappings', 'wp-pdf-guard' ),
			__( 'Mappings', 'wp-pdf-guard' ),
			'manage_options',
			'wpdfg-mappings',
			array( $this, 'render_mapping_page' )
		);

		add_submenu_page(
			'wpdfg-mappings',
			__( 'PDF Guard Settings', 'wp-pdf-guard' ),
			__( 'Settings', 'wp-pdf-guard' ),
			'manage_options',
			'wpdfg-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'wpdfg_settings', 'wpdfg_token_duration', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 600,
		) );

		register_setting( 'wpdfg_settings', 'wpdfg_auto_inject', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1,
		) );

		register_setting( 'wpdfg_settings', 'wpdfg_block_all_pdfs', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );

		add_settings_section(
			'wpdfg_general',
			__( 'General Settings', 'wp-pdf-guard' ),
			'__return_false',
			'wpdfg-settings'
		);

		add_settings_field(
			'wpdfg_token_duration',
			__( 'Token Duration (seconds)', 'wp-pdf-guard' ),
			array( $this, 'render_token_duration_field' ),
			'wpdfg-settings',
			'wpdfg_general'
		);

		add_settings_field(
			'wpdfg_auto_inject',
			__( 'Auto-inject Cookies', 'wp-pdf-guard' ),
			array( $this, 'render_auto_inject_field' ),
			'wpdfg-settings',
			'wpdfg_general'
		);

		add_settings_field(
			'wpdfg_block_all_pdfs',
			__( 'Block All PDFs', 'wp-pdf-guard' ),
			array( $this, 'render_block_all_pdfs_field' ),
			'wpdfg-settings',
			'wpdfg_general'
		);
	}

	/**
	 * Render token duration settings field.
	 */
	public function render_token_duration_field() {
		$value = get_option( 'wpdfg_token_duration', 600 );
		printf(
			'<input type="number" name="wpdfg_token_duration" value="%d" min="10" max="86400" class="small-text" /> <p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'How long (in seconds) a user can access the PDF after visiting the product page. Default: 600 (10 minutes).', 'wp-pdf-guard' )
		);
	}

	/**
	 * Render auto-inject settings field.
	 */
	public function render_auto_inject_field() {
		$value = get_option( 'wpdfg_auto_inject', 1 );
		printf(
			'<label><input type="checkbox" name="wpdfg_auto_inject" value="1" %s /> %s</label><p class="description">%s</p>',
			checked( $value, 1, false ),
			esc_html__( 'Enabled', 'wp-pdf-guard' ),
			esc_html__( 'Automatically set access cookies when a user visits a product page with mapped PDFs.', 'wp-pdf-guard' )
		);
	}

	/**
	 * Render block-all-PDFs settings field.
	 */
	public function render_block_all_pdfs_field() {
		$value = get_option( 'wpdfg_block_all_pdfs', 0 );
		printf(
			'<label><input type="checkbox" name="wpdfg_block_all_pdfs" value="1" %s /> %s</label><p class="description">%s</p>',
			checked( $value, 1, false ),
			esc_html__( 'Enabled', 'wp-pdf-guard' ),
			esc_html__( 'Block direct access to ALL PDFs, even those not explicitly mapped. When off, only mapped PDFs are protected.', 'wp-pdf-guard' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'wpdfg' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'wpdfg-admin',
			WPDFG_PLUGIN_URL . 'admin/css/wpdfg-admin.css',
			array(),
			WPDFG_VERSION
		);

		wp_enqueue_script(
			'wpdfg-admin',
			WPDFG_PLUGIN_URL . 'admin/js/wpdfg-admin.js',
			array( 'jquery', 'jquery-ui-autocomplete' ),
			WPDFG_VERSION,
			true
		);

		wp_localize_script( 'wpdfg-admin', 'wpdfg', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpdfg_admin' ),
			'strings'  => array(
				'confirm_delete' => __( 'Are you sure you want to delete this mapping?', 'wp-pdf-guard' ),
				'saving'         => __( 'Saving...', 'wp-pdf-guard' ),
				'deleting'       => __( 'Deleting...', 'wp-pdf-guard' ),
				'error'          => __( 'An error occurred. Please try again.', 'wp-pdf-guard' ),
			),
		) );
	}

	/**
	 * Render the mappings management page.
	 */
	public function render_mapping_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-pdf-guard' ) );
		}
		require_once WPDFG_PLUGIN_DIR . 'admin/partials/mapping-page.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-pdf-guard' ) );
		}
		require_once WPDFG_PLUGIN_DIR . 'admin/partials/settings-page.php';
	}

	/**
	 * Show admin notice for Nginx users.
	 */
	public function nginx_notice() {
		global $is_nginx;

		if ( ! $is_nginx ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'wpdfg' ) === false ) {
			return;
		}

		$dismissed = get_option( 'wpdfg_nginx_notice_dismissed' );
		if ( $dismissed ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'WP-PDF-Guard — Nginx Configuration Required', 'wp-pdf-guard' ); ?></strong>
			</p>
			<p><?php esc_html_e( 'Your server uses Nginx. Please add the following rules to your Nginx configuration:', 'wp-pdf-guard' ); ?></p>
			<pre style="background:#f0f0f0;padding:10px;overflow-x:auto;">location ~* /wp-content/uploads/.*\.pdf$ {
    rewrite ^/wp-content/uploads/(.+\.pdf)$ /index.php?wpdfg_resolve_path=/wp-content/uploads/$1 last;
}</pre>
		</div>
		<?php
	}

	/**
	 * AJAX: Save a new mapping.
	 */
	public function ajax_save_mapping() {
		check_ajax_referer( 'wpdfg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-pdf-guard' ) ), 403 );
		}

		$pdf_id  = isset( $_POST['pdf_id'] ) ? absint( $_POST['pdf_id'] ) : 0;
		$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;

		if ( ! $pdf_id || ! $page_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select both a PDF and a page.', 'wp-pdf-guard' ) ) );
		}

		$result = WPDFG_Mapping::create( $pdf_id, $page_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'Mapping created successfully.', 'wp-pdf-guard' ),
			'mapping_id' => $result,
		) );
	}

	/**
	 * AJAX: Delete a mapping.
	 */
	public function ajax_delete_mapping() {
		check_ajax_referer( 'wpdfg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-pdf-guard' ) ), 403 );
		}

		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( $_POST['mapping_id'] ) : 0;

		if ( ! $mapping_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid mapping ID.', 'wp-pdf-guard' ) ) );
		}

		$result = WPDFG_Mapping::delete( $mapping_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Mapping deleted.', 'wp-pdf-guard' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete mapping.', 'wp-pdf-guard' ) ) );
		}
	}

	/**
	 * AJAX: Search PDF attachments for autocomplete.
	 */
	public function ajax_search_pdfs() {
		check_ajax_referer( 'wpdfg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-pdf-guard' ) ), 403 );
		}

		$search = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		// Exclude PDFs that are already mapped.
		global $wpdb;
		$table      = $wpdb->prefix . 'wpdfg_mappings';
		$mapped_ids = $wpdb->get_col( "SELECT pdf_id FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$mapped_ids = array_map( 'absint', $mapped_ids );

		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'application/pdf',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
			's'              => $search,
		);

		if ( ! empty( $mapped_ids ) ) {
			$args['post__not_in'] = $mapped_ids;
		}

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'    => $post->ID,
				'label' => $post->post_title . ' (ID: ' . $post->ID . ')',
				'value' => $post->post_title,
			);
		}

		wp_send_json( $results );
	}

	/**
	 * AJAX: Search pages/posts for autocomplete.
	 */
	public function ajax_search_pages() {
		check_ajax_referer( 'wpdfg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-pdf-guard' ) ), 403 );
		}

		$search = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $search,
		);

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'    => $post->ID,
				'label' => $post->post_title . ' (' . $post->post_type . ', ID: ' . $post->ID . ')',
				'value' => $post->post_title,
			);
		}

		wp_send_json( $results );
	}
}
