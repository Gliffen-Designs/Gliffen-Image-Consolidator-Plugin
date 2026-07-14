/**
 * Gliffen Image Consolidator Admin JavaScript
 */

jQuery(function($) {
	const $sizesList = $('#gic-sizes-list');

	/**
	 * Get all sizes that are being replaced (consolidated away)
	 */
	function getDisabledSizes() {
		const disabled = new Set();
		$sizesList.find('.gic-replacement-select').each(function() {
			const $row = $(this).closest('.gic-size-row');
			const sizeName = $row.data('size-name');
			const replacement = $(this).val();
			
			// If this size has a replacement selected, it's being disabled
			if (replacement) {
				disabled.add(sizeName);
			}
		});
		return disabled;
	}

	/**
	 * Update dropdown options to hide sizes that are being replaced
	 */
	function updateDropdownOptions() {
		const disabledSizes = getDisabledSizes();

		// Update each dropdown
		$sizesList.find('.gic-replacement-select').each(function() {
			const $select = $(this);
			const currentSize = $select.closest('.gic-size-row').data('size-name');
			const currentSelection = $select.val();

			$select.find('option').each(function() {
				const $option = $(this);
				const optionValue = $option.val();

				// Always show empty option
				if (!optionValue) {
					$option.show();
					return;
				}

				// Show current selection
				if (optionValue === currentSelection) {
					$option.show();
					return;
				}

				// Hide if this size is being replaced/disabled elsewhere
				if (disabledSizes.has(optionValue)) {
					$option.hide();
				} else {
					$option.show();
				}
			});
		});
	}

	/**
	 * Handle replacement selection changes
	 */
	$sizesList.on('change', '.gic-replacement-select', function() {
		updateDropdownOptions();
	});

	/**
	 * Handle move to cleanup button
	 */
	$('#gic-move-to-cleanup').on('click', function(e) {
		e.preventDefault();
		const $button = $(this);
		
		$button.prop('disabled', true).text('Processing...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'gic_move_to_cleanup',
				nonce: gicAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					alert('Successfully moved ' + response.data.files_moved + ' files to pending cleanup.');
					location.reload();
				} else {
					alert('Error: ' + (response.data.message || 'Unknown error'));
				}
			},
			error: function(err) {
				alert('AJAX Error: ' + err.status + ' ' + err.statusText);
			},
			complete: function() {
				$button.prop('disabled', false).text('Move to Pending Cleanup');
			}
		});
	});

	/**
	 * Handle delete cleanup files button
	 */
	$('#gic-delete-cleanup').on('click', function(e) {
		e.preventDefault();
		const $button = $(this);
		
		$button.prop('disabled', true).text('Deleting...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'gic_delete_cleanup',
				nonce: gicAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					alert('Successfully deleted ' + response.data.files_deleted + ' files.');
					location.reload();
				} else {
					alert('Error: ' + (response.data.message || 'Unknown error'));
				}
			},
			error: function(err) {
				alert('AJAX Error: ' + err.status + ' ' + err.statusText);
			},
			complete: function() {
				$button.prop('disabled', false).text('Permanently Delete');
			}
		});
	});

	/**
	 * Handle cleanup metadata button
	 */
	$('#gic-cleanup-metadata').on('click', function(e) {
		e.preventDefault();
		const $button = $(this);
		
		$button.prop('disabled', true).text('Cleaning metadata...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'gic_cleanup_metadata',
				nonce: gicAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					alert('Successfully cleaned metadata for ' + response.data.metadata_cleaned + ' attachments.');
					location.reload();
				} else {
					alert('Error: ' + (response.data.message || 'Unknown error'));
				}
			},
			error: function(err) {
				alert('AJAX Error: ' + err.status + ' ' + err.statusText);
			},
			complete: function() {
				$button.prop('disabled', false).text('Clean Metadata');
			}
		});
	});

	// Initialize on page load
	updateDropdownOptions();
});

