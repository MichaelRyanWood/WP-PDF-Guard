<?php
/**
 * Settings page template.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'WP-PDF-Guard Settings', 'wp-pdf-guard' ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'wpdfg_settings' );
		do_settings_sections( 'wpdfg-settings' );
		submit_button();
		?>
	</form>
</div>
