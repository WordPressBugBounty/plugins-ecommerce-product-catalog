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
        var product_id = jQuery(this).val();
        var price = jQuery('#product_price_' + product_id).val();
        if (!price) {
            price = 0;
        }
        update_row(jQuery(this), price);
        /*
        var field_number = jQuery(this).attr('custom');
        var pre_name = '#manual_order_';
        if (jQuery(this).attr('name') === 'order_product_id_' + field_number) {
            pre_name = '#order_';
        }
        jQuery(pre_name + 'product_price_' + field_number).val(price);
        var quantity = jQuery(pre_name + 'product_quantity_' + field_number).val();
        var sum = Math.round(quantity * price * 100) / 100;
        sum = sum.toFixed(2);
        jQuery(pre_name + "product_summary_" + field_number).val(sum);

         */
    });
    jQuery('#order_product_list').on('change', '.product-quantity', function () {
        update_row(jQuery(this));
    });
    jQuery('#order_product_list').on('change', '.product-price', function () {
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