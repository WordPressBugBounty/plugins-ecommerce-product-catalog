/**
 * Created by Advert Lab on 09-Mar-15.
 */

jQuery(document).ready(function () {
    /* global product_object, ic_customer_panel */
    var ajaxNonce = '';
    var restNonce = '';
    if (typeof ic_customer_panel !== 'undefined') {
        ajaxNonce = ic_customer_panel.ajax_nonce || '';
        restNonce = ic_customer_panel.rest_nonce || ic_customer_panel.nonce || '';
    }
    jQuery('#customer_panel_tabs').tabs();
    var password = jQuery('#customer_panel_tabs-password');
    password.find('.new-password-button button').click(function () {
        password.find('.spinner img').show();
        var first = password.find('#new_password_1').val();
        var second = password.find('#new_password_2').val();
        if (first !== '' && second !== '' && first !== second) {
            password.find('.spinner img').hide();
            password.find('.password-reset-result').html('<div class="al-box warning">The passwords do not match!</div>');
        } else if (first === '' || second === '') {
            password.find('.spinner img').hide();
            password.find('.password-reset-result').html('<div class="al-box warning">Please fill both fields.</div>');
        } else {
            var data = {
                'action': 'customer_panel_password_reset',
                'new_password': first,
                'repeat_new_password': second,
                'nonce': ajaxNonce
            };
            jQuery.post(product_object.ajaxurl, data, function (result) {
                password.find('.spinner img').hide();
                password.find('.password-reset-result').html(result);
                if (jQuery('span.time').length > 0) {
                    var time = jQuery('span.time').text();
                    setInterval(function () {
                        time--;
                        jQuery('span.time').html(time);
                        if (time === 0) {
                            location.reload();
                        }
                    }, 1000);
                }
            });
        }
    });

    var customer_data = jQuery('#customer_panel_tabs-customer-data');

    customer_data.on('click', '.ic-save-customer-data button', function (e) {
        e.preventDefault();
        const clicked = jQuery(this);
        const form = clicked.closest('form');
        const spinner = clicked.next('.spinner');
        clicked.attr('disabled', true);
        if (clicked.closest('.ic-api-response').length === 0) {
            customer_data.find('.ic-api-response').remove();
        } else {
            clicked.closest('.ic-api-response').css('opacity', '0.5');
        }
        if (spinner.length > 0) {
            spinner.css('display', 'inline-block');
        }
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            clicked.attr('disabled', false);
            if (spinner.length > 0) {
                spinner.css('display', 'none');
            }
            return;
        }

        wp.apiFetch({
            path: 'implecode/v1/customer/' + ic_customer_panel.customer_id,
            method: 'POST',
            data: {data: form.serialize()},
            nonce: restNonce
        }).then((response) => {
            clicked.attr('disabled', false);
            if (clicked.closest('.ic-api-response').length === 0) {
                customer_data.find('.ic-api-response').remove();
                if (spinner.length > 0) {
                    spinner.css('display', 'none');
                    spinner.after(response);
                } else {
                    if (clicked.closest('.ic-question-buttons').length > 0) {
                        clicked.closest('.ic-question-buttons').replaceWith(response);
                    } else {
                        clicked.after(response);
                    }
                }
            } else {
                clicked.closest('.ic-api-response').replaceWith(response);
            }

        });
    });

    jQuery('#customer_orders_table .table-row .table-cell span.dashicons').click(function () {
        var row = jQuery(this).closest('.table-row');
        var order_id = row.data('order_id');
        if (order_id !== '') {
            var order_name_span = jQuery(this).next('.order-name');
            var old_order_name = order_name_span.text();
            order_name_span.html('<input class="order-name-edit" type="text" value=""><span class="dashicons dashicons-yes"></span><span class="dashicons dashicons-no"></span>');
        }
        jQuery('#customer_orders_table').unbind('click');
        jQuery('#customer_orders_table').on('click', '.order-name .dashicons-no', function () {
            order_name_span.html(old_order_name);
        });
        jQuery('#customer_orders_table').on('click', '.order-name .dashicons-yes', function () {
            var order_name_edit = jQuery(order_name_span).find('.order-name-edit');
            var new_order_name = order_name_edit.val();
            var data = {
                'action': 'change_order_name',
                'order_id': order_id,
                'new_name': new_order_name,
                'nonce': ajaxNonce
            };
            order_name_edit.attr('disabled', true);
            jQuery.post(product_object.ajaxurl, data, function (response) {
                if (response === 'done') {
                    order_name_span.html(new_order_name);
                } else {
                    order_name_edit.attr('disabled', false);
                }
            });
        });
    });
});
