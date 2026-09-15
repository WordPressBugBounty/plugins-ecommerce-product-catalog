jQuery(document).ready(function () {
    /* globals digital_order_edit_script_trans */
    jQuery('#country-selector').chosen({width: '180px'});
    jQuery('#add-digital-product').click(function () {
        var count = parseInt(jQuery('.manual_products_count').val(), 10) + 1;
        jQuery('.manual_products_count').val(count);
        jQuery('#order_product_list tr:last').after('<tr><td>' + digital_order_edit_script_trans.digital_products_dropdown_action + '<input custom="' + count + '" style="display:none" type="text" id="manual_order_product_name_' + count + '" name="manual_order_product_name_' + count + '" value=""></td><td><input custom="' + count + '" type="number" min="1" step="1" class="product-quantity number_box" id="manual_order_product_quantity_' + count + '" name="manual_order_product_quantity_' + count + '" value="" /></td><td><input custom="' + count + '" type="text" id="manual_order_product_price_' + count + '" class="product-price number_box" name="manual_order_product_price_' + count + '" value="" /></td>' + digital_order_edit_script_trans.digital_order_manual_products_td_script_action + '<td><input readonly type="text" class="number_box" id="manual_order_product_summary_' + count + '" name="manual_order_product_summary_' + count + '" value="" /></td></tr>');
        jQuery('#new_manual_order_product_id').attr('name', 'manual_order_product_id_' + count);
        jQuery('#new_manual_order_product_id').attr('custom', count);
        jQuery('#manual_order_product_quantity_' + count).val(1);
        jQuery('#new_manual_order_product_id option').each(function () {
            var product_id = jQuery(this).val();
            jQuery(this).attr('name', 'manual_order_product_id_' + count + '[' + product_id + ']');
        });
        jQuery('#new_manual_order_product_id').attr('id', 'manual_order_product_id_' + count);
        if (window.icSelector) {
            /* The injected row may carry a shared AJAX selector; enhance it. */
            window.icSelector.init(document);
        }
        disable_product_choose();
    });
    jQuery('#add-custom-digital-product').click(function () {
        var count = parseInt(jQuery('.manual_products_count').val(), 10) + 1;
        jQuery('.manual_products_count').val(count);
        jQuery('#order_product_list tr:last').after('<tr><td><input name="manual_order_product_name_' + count + '" value="" ></td><td><input custom="' + count + '" type="number" min="1" step="1" class="product-quantity number_box" id="manual_order_product_quantity_' + count + '" name="manual_order_product_quantity_' + count + '" value="" /></td><td><input custom="' + count + '" type="number" min="0.01" step="0.01" id="manual_order_product_price_' + count + '" class="product-price number_box" name="manual_order_product_price_' + count + '" value="" /></td>' + digital_order_edit_script_trans.digital_order_custom_manual_products_td_script_action + '<td><input  type="text" readonly class="number_box" id="manual_order_product_summary_' + count + '" name="manual_order_product_summary_' + count + '" value="" /></td></tr>');
        jQuery('#manual_order_product_id_' + count).hide();
        jQuery('#manual_order_product_name_' + count).show();
    });
    jQuery('#order_product_list').on('change', '.digital_products_dropdown', function () {
		load_product_price(jQuery(this));
    });
    jQuery('#order_product_list').on('change', '.product-quantity', function () {
        update_row(jQuery(this));
    });
    jQuery('#order_product_list').on('change', '.product-price', function () {
		invalidate_price_request(jQuery(this));
		clear_price_error(jQuery(this));
        update_row(jQuery(this));
    });
    jQuery('.ic-payment-data-form h2').on('click', function () {
        var container = jQuery(this).parent('.ic-payment-data-form');
        if (container.hasClass('open')) {
            container.find('.form_row').hide('slow');
            container.removeClass('open');
        } else {
            container.find('.form_row').show('slow');
            container.addClass('open');
        }
    });
    var max_row_width = 0;
    jQuery('.ic-order-checkout-data .table-row').each(function () {
        var width = jQuery(this).width();
        if (width > max_row_width) {
            max_row_width = width;
        }
    }).promise().done(function () {
        jQuery('.ic-checkout-form-data div h2').css('width', max_row_width);
        max_row_width = max_row_width + 24;
        jQuery('.ic-order-checkout-data .ic-payment-data-form').css('width', max_row_width);
    });
    jQuery('.ic-checkout-form-data div h2').on('click', function () {
        var container = jQuery(this).closest('.section-break');
        ic_toggle_section_containers(container, 1);
    });
    if (jQuery('.ic-order-checkout-data .table-row').length > 7) {
        jQuery('.ic-checkout-form-data div h2').trigger('click');
    }

    function update_row(field, price) {
        var field_number = field.attr('custom');
        var pre_name = '#manual_order_';
        var field_name = field.attr('name');
        if (field_name === 'order_product_id_' + field_number || field_name === 'order_product_quantity_' + field_number || field_name === 'order_product_price_' + field_number) {
            pre_name = '#order_';
        }
        if (price !== undefined) {
            jQuery(pre_name + 'product_price_' + field_number).val(price);
        } else {
            price = jQuery(pre_name + 'product_price_' + field_number).val();
        }
        var quantity = jQuery(pre_name + 'product_quantity_' + field_number).val();
        var sum = Math.round(quantity * price * 100) / 100;
        sum = sum.toFixed(2);
        jQuery(pre_name + 'product_summary_' + field_number).val(sum);
    }

	function row_price_field(field) {
		var field_number = field.attr('custom');
		var pre_name = '#manual_order_';
		if (field.attr('name') === 'order_product_id_' + field_number) {
			pre_name = '#order_';
		}
		return jQuery(pre_name + 'product_price_' + field_number);
	}

	function invalidate_price_request(price_field) {
		var request = price_field.data('ic-price-request');
		if (request && request.xhr) {
			request.xhr.abort();
		}
		price_field.data('ic-price-sequence', (price_field.data('ic-price-sequence') || 0) + 1);
		price_field.removeData('ic-price-request').removeAttr('aria-busy');
	}

	function clear_price_error(price_field) {
		price_field.removeAttr('aria-invalid title placeholder');
	}

	function show_price_error(field, price_field, message) {
		price_field.val('').removeAttr('aria-busy').attr({
			'aria-invalid': 'true',
			'title': message,
			'placeholder': message
		});
		update_row(field, 0);
	}

	function load_product_price(field) {
		var config = digital_order_edit_script_trans.product_price || {};
		var product_id = String(field.val() || '');
		var price_field = row_price_field(field);
		var previous = price_field.data('ic-price-request');
		if (previous && previous.xhr) {
			previous.xhr.abort();
		}
		var sequence = (price_field.data('ic-price-sequence') || 0) + 1;
		price_field.data('ic-price-sequence', sequence).removeData('ic-price-request');
		clear_price_error(price_field);

		if (!product_id || product_id === 'noid') {
			price_field.removeAttr('aria-busy');
			update_row(field, 0);
			return;
		}

		var original_price = String(price_field.val() || '');
		price_field.attr({'aria-busy': 'true', 'placeholder': config.loading || ''});
		var xhr = jQuery.ajax({
			url: config.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: config.action,
				nonce: config.nonce,
				order_id: config.order_id,
				product_id: product_id
			}
		});
		price_field.data('ic-price-request', {xhr: xhr, sequence: sequence, product_id: product_id});

		xhr.done(function (response) {
			var current = price_field.data('ic-price-request');
			if (!current || current.sequence !== sequence || String(field.val() || '') !== product_id || !response || !response.success || !response.data || String(response.data.product_id) !== product_id || String(price_field.val() || '') !== original_price) {
				return;
			}
			price_field.removeData('ic-price-request').removeAttr('aria-busy');
			clear_price_error(price_field);
			update_row(field, response.data.price === '' ? 0 : String(response.data.price));
		}).fail(function (request, status) {
			var current = price_field.data('ic-price-request');
			if (status === 'abort' || !current || current.sequence !== sequence || String(field.val() || '') !== product_id || String(price_field.val() || '') !== original_price) {
				return;
			}
			price_field.removeData('ic-price-request');
			show_price_error(field, price_field, config.error || '');
		});
	}
});

function ic_toggle_section_containers(container, toggle_container) {
    if (!container.hasClass('section-break') || toggle_container === 1) {
        if (container.hasClass('open') || !container.hasClass('closed')) {
            if (container.hasClass('section-break')) {
                var width = container.find('h2').width();
                container.find('h2').css('width', width);
            }
            if (!container.hasClass('section-break')) {
                container.hide('slow');
            }
            container.removeClass('open');
            container.addClass('closed');
        } else {
            if (!container.hasClass('section-break')) {
                container.show('slow');
            }
            container.addClass('open');
            container.removeClass('closed');
        }
        var next_container = container.next('.form_row');
        if (next_container.length > 0) {
            ic_toggle_section_containers(next_container, 0);
        }
    }
    return true;
}

function disable_product_choose() {
    jQuery('.digital_products_dropdown option').removeAttr('disabled', 'disabled');
    jQuery('.digital_products_dropdown').each(function () {
        var name = 'id_' + jQuery(this).val();
        jQuery('.digital_products_dropdown .' + name).attr('disabled', 'disabled');
    });
    jQuery(':selected').removeAttr('disabled', 'disabled');
}
