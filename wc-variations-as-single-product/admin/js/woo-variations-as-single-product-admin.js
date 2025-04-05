jQuery(document).ready(function ($) {
	'use strict';

	let totalProducts = 0;
    let processedProducts = 0;

	//Handle settings form submission
	$('.woocommerce-tab-wvasp #mainform').on('submit', function (e) {
		e.preventDefault();

		// Show modal
		createModal();

		var formData = $(this).serialize();
		//console.log(formData);
		formData += '&action=wvasp_save_settings&nonce=' + wvasp_ajax.nonce;

		$.ajax({
			url: wvasp_ajax.ajax_url,
			type: 'POST',
			data: formData,
			dataType: 'json',
			beforeSend: function () {
				$('.woocommerce-save-button[type="submit"]').prop('disabled', true).text('Saving...');
			},
			success: function (response) {
				if (response.success) {
					totalProducts = response.data.varible_product_count;
					batch_update_product_variations();
				} else {
					//alert(response.data.message); // Show error message
				}
			},
			error: function () {
				alert( wvasp_ajax.i18n.error_setting );
			},
			complete: function () {
				// Re-enable button
				$('.woocommerce-save-button[type="submit"]').removeClass('is-busy');
				$('.woocommerce-save-button[type="submit"]').prop('disabled', false).text('Save changes');
				//$('.woocommerce-save-button[type="submit"]').text('Save changes');
			}
		});
	});

	function createModal() {
		const modalHtml = `
            <div id="wvasp-batch-modal" class="wvasp-modal active">
				<div class="wvasp-modal-overlay"></div>
				<div class="wvasp-modal-content">
					<div class="wvasp-modal-header">
						<h2>${wvasp_ajax.i18n.updating}</h2>
						<span class="wvasp-modal-close">&times;</span> <!-- Close button -->
					</div>
					<div class="wvasp-modal-body">
						<div class="wvasp-progress-container">
							<div class="wvasp-progress-bar">
								<div class="wvasp-progress-percentage">0%</div>
								<div class="wvasp-progress-bar-fill" style="width: 0%"></div>
							</div>
							<p class="wvasp-progress-text"></p>
							<p class="wvasp-error-text"></p>
						</div>
					</div>
				</div>
			</div>
        `;

		// Remove existing modal if any
		$('#wvasp-batch-modal').remove();
		$('body').append(modalHtml);
	}

	// Close modal
	$(document).on("click", ".wvasp-modal-close", function () {
		$("#wvasp-batch-modal").removeClass("active");
	});

	// Batch update product variations
	function batch_update_product_variations(offset = 0) {
		$.ajax({
			url: wvasp_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'wvasp_batch_update_product_variations',
				offset: offset,
				nonce: wvasp_ajax.nonce
			},
			dataType: 'json',
			success: function (response) {
				//console.log(response);
				if (response.success) {
					processedProducts += response.data.processed;
					let progress = (processedProducts / totalProducts) * 100;
					$('.wvasp-progress-bar-fill').css('width', progress + '%');
					$('.wvasp-progress-percentage').text(progress.toFixed(0) + '%');
                    if(progress > 55) {
                        $('.wvasp-progress-percentage').addClass('half-finished');
                    }
					//$('.wvasp-progress-text').text(processedProducts + ' of ' + totalProducts + ' products updated');
					$('.wvasp-progress-text').text(
						wvasp_ajax.i18n.progress_text.replace('%1$s', processedProducts).replace('%2$s', totalProducts)
					);

					if (processedProducts < totalProducts) {
						batch_update_product_variations(processedProducts); // Recursive call
					} else {
						$('.wvasp-progress-text').text(wvasp_ajax.i18n.completed);
						//setTimeout(() => { $('#batch-update-modal').fadeOut(); }, 2000);

						// reset processedProducts
						processedProducts = 0;
					}
				} else {
					alert(wvasp_ajax.i18n.error_update);
				}
			},
			error: function () {
				alert(wvasp_ajax.i18n.error_update);
			},
			complete: function () {}
		});
	}

});
