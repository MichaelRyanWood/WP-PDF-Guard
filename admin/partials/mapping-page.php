<?php
/**
 * Mapping management page template.
 *
 * @package WP_PDF_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$per_page     = 20;
$data         = WPDFG_Mapping::list_all( $per_page, $current_page );
$mappings     = $data['items'];
$total        = $data['total'];
$total_pages  = ceil( $total / $per_page );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'PDF Guard — Mappings', 'wp-pdf-guard' ); ?></h1>

	<div class="wpdfg-add-mapping-form" id="wpdfg-add-mapping">
		<h2><?php esc_html_e( 'Add New Mapping', 'wp-pdf-guard' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="wpdfg-pdf-search"><?php esc_html_e( 'PDF Attachment', 'wp-pdf-guard' ); ?></label>
				</th>
				<td>
					<input type="text" id="wpdfg-pdf-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search for a PDF...', 'wp-pdf-guard' ); ?>" autocomplete="off" />
					<input type="hidden" id="wpdfg-pdf-id" value="" />
					<p class="description"><?php esc_html_e( 'Start typing to search for PDF attachments.', 'wp-pdf-guard' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpdfg-page-search"><?php esc_html_e( 'Product Page', 'wp-pdf-guard' ); ?></label>
				</th>
				<td>
					<input type="text" id="wpdfg-page-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search for a page...', 'wp-pdf-guard' ); ?>" autocomplete="off" />
					<input type="hidden" id="wpdfg-page-id" value="" />
					<p class="description"><?php esc_html_e( 'The page users will be redirected to before accessing the PDF.', 'wp-pdf-guard' ); ?></p>
				</td>
			</tr>
		</table>
		<p>
			<button type="button" id="wpdfg-save-mapping" class="button button-primary">
				<?php esc_html_e( 'Add Mapping', 'wp-pdf-guard' ); ?>
			</button>
			<span id="wpdfg-save-status" class="wpdfg-status"></span>
		</p>
	</div>

	<hr />

	<h2><?php esc_html_e( 'Existing Mappings', 'wp-pdf-guard' ); ?></h2>

	<?php if ( empty( $mappings ) ) : ?>
		<p><?php esc_html_e( 'No mappings found. Add one above to start protecting your PDFs.', 'wp-pdf-guard' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" id="wpdfg-mappings-table">
			<thead>
				<tr>
					<th scope="col" class="column-id"><?php esc_html_e( 'ID', 'wp-pdf-guard' ); ?></th>
					<th scope="col" class="column-pdf"><?php esc_html_e( 'PDF', 'wp-pdf-guard' ); ?></th>
					<th scope="col" class="column-page"><?php esc_html_e( 'Product Page', 'wp-pdf-guard' ); ?></th>
					<th scope="col" class="column-shortcode"><?php esc_html_e( 'Shortcode', 'wp-pdf-guard' ); ?></th>
					<th scope="col" class="column-created"><?php esc_html_e( 'Created', 'wp-pdf-guard' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'wp-pdf-guard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $mappings as $mapping ) : ?>
					<?php
					$pdf_title  = get_the_title( $mapping->pdf_id );
					$page_title = get_the_title( $mapping->page_id );
					$page_url   = get_permalink( $mapping->page_id );
					?>
					<tr id="wpdfg-mapping-<?php echo esc_attr( $mapping->id ); ?>">
						<td class="column-id"><?php echo esc_html( $mapping->id ); ?></td>
						<td class="column-pdf">
							<?php echo esc_html( $pdf_title ? $pdf_title : '#' . $mapping->pdf_id ); ?>
							<br /><small><?php echo esc_html( 'ID: ' . $mapping->pdf_id ); ?></small>
						</td>
						<td class="column-page">
							<?php if ( $page_url ) : ?>
								<a href="<?php echo esc_url( $page_url ); ?>" target="_blank">
									<?php echo esc_html( $page_title ? $page_title : '#' . $mapping->page_id ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $page_title ? $page_title : '#' . $mapping->page_id ); ?>
							<?php endif; ?>
							<br /><small><?php echo esc_html( 'ID: ' . $mapping->page_id ); ?></small>
						</td>
						<td class="column-shortcode">
							<code>[pdf_guard_download id="<?php echo esc_attr( $mapping->pdf_id ); ?>"]</code>
						</td>
						<td class="column-created"><?php echo esc_html( $mapping->created_at ); ?></td>
						<td class="column-actions">
							<button type="button" class="button button-small wpdfg-delete-mapping" data-id="<?php echo esc_attr( $mapping->id ); ?>">
								<?php esc_html_e( 'Delete', 'wp-pdf-guard' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $current_page,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						) )
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
