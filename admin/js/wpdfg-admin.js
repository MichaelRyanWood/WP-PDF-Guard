/**
 * WP-PDF-Guard Admin JavaScript
 */
(function ($) {
	'use strict';

	$(function () {
		// PDF autocomplete.
		$('#wpdfg-pdf-search').autocomplete({
			source: function (request, response) {
				$.getJSON(wpdfg.ajax_url, {
					action: 'wpdfg_search_pdfs',
					nonce: wpdfg.nonce,
					term: request.term
				}, response);
			},
			minLength: 2,
			select: function (event, ui) {
				$('#wpdfg-pdf-id').val(ui.item.id);
			}
		});

		// Page autocomplete.
		$('#wpdfg-page-search').autocomplete({
			source: function (request, response) {
				$.getJSON(wpdfg.ajax_url, {
					action: 'wpdfg_search_pages',
					nonce: wpdfg.nonce,
					term: request.term
				}, response);
			},
			minLength: 2,
			select: function (event, ui) {
				$('#wpdfg-page-id').val(ui.item.id);
			}
		});

		// Save mapping.
		$('#wpdfg-save-mapping').on('click', function () {
			var $btn = $(this);
			var $status = $('#wpdfg-save-status');
			var pdfId = $('#wpdfg-pdf-id').val();
			var pageId = $('#wpdfg-page-id').val();

			if (!pdfId || !pageId) {
				$status.text(wpdfg.strings.error).removeClass('success').addClass('error');
				return;
			}

			$btn.prop('disabled', true);
			$status.text(wpdfg.strings.saving).removeClass('success error');

			$.post(wpdfg.ajax_url, {
				action: 'wpdfg_save_mapping',
				nonce: wpdfg.nonce,
				pdf_id: pdfId,
				page_id: pageId
			}, function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					$status.text(response.data.message).removeClass('error').addClass('success');
					// Reload to show updated table.
					setTimeout(function () {
						location.reload();
					}, 1000);
				} else {
					$status.text(response.data.message).removeClass('success').addClass('error');
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				$status.text(wpdfg.strings.error).removeClass('success').addClass('error');
			});
		});

		// Delete mapping.
		$(document).on('click', '.wpdfg-delete-mapping', function () {
			if (!confirm(wpdfg.strings.confirm_delete)) {
				return;
			}

			var $btn = $(this);
			var mappingId = $btn.data('id');
			var $row = $btn.closest('tr');

			$btn.prop('disabled', true).text(wpdfg.strings.deleting);

			$.post(wpdfg.ajax_url, {
				action: 'wpdfg_delete_mapping',
				nonce: wpdfg.nonce,
				mapping_id: mappingId
			}, function (response) {
				if (response.success) {
					$row.fadeOut(300, function () {
						$(this).remove();
					});
				} else {
					$btn.prop('disabled', false).text('Delete');
					alert(response.data.message);
				}
			}).fail(function () {
				$btn.prop('disabled', false).text('Delete');
				alert(wpdfg.strings.error);
			});
		});
	});
})(jQuery);
