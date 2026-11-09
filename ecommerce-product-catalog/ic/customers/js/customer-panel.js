/**
 * Created by Advert Lab on 09-Mar-15.
 */

window.icInitializeCustomerPanel = function () {
    var panelRoot = jQuery('#customer_panel');
    if (!panelRoot.length || panelRoot.data('ic-panel-initialized')) { return; }
    panelRoot.data('ic-panel-initialized', true);
    /* global product_object, ic_customer_panel */
    var ajaxNonce = '';
    var restNonce = '';
    if (typeof ic_customer_panel !== 'undefined') {
        ajaxNonce = ic_customer_panel.ajax_nonce || '';
        restNonce = ic_customer_panel.rest_nonce || ic_customer_panel.nonce || '';
    }
    var panelTabs = jQuery('#customer_panel_tabs');
    var initialTab = parseInt(panelTabs.attr('data-initial-tab'), 10);
    panelTabs.tabs({active: isNaN(initialTab) ? 0 : initialTab});
    panelTabs.removeClass('ic-tabs-preinit').children('.ic-tab-initial').removeClass('ic-tab-initial');
    function initializeBillingCountry() {
        if (!jQuery.fn.chosen) { return; }
        jQuery('.ic-billing-country').each(function () {
            var select = jQuery(this);
            if (select.data('chosen')) { select.trigger('chosen:updated'); }
            else if (select.is(':visible')) { select.chosen({disable_search_threshold: 0, width: '100%', no_results_text: 'Please make sure that you search in English. No results for'}); }
        });
    }
    initializeBillingCountry();
    jQuery('#customer_panel_tabs').on('tabsactivate', initializeBillingCountry);
    jQuery('.ic-customer-email-prompt').prop('hidden', false);
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

    customer_data.on('input change', '.ic-contact-form input[type="email"]', function () {
        var form = jQuery(this).closest('form');
        var changed = jQuery(this).val().toLowerCase() !== String(form.data('email')).toLowerCase();
        form.find('.ic-current-password').prop('hidden', !changed);
        form.find('[name="current_password"]').prop('required', changed);
        if (!changed) { form.find('[name="current_password"]').val(''); }
    });

    function saveCustomerForm(form, clicked, billing) {
        var scope = billing ? form.find('.ic-billing-response') : form.find('.ic-save-customer-data').first();
        if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
        var payload = form.serialize();
        form.find('[name="current_password"]').val('');
        clicked.prop('disabled', true);
        form.find('.spinner').show();
        wp.apiFetch({
            path: 'implecode/v1/customer/' + ic_customer_panel.customer_id + (billing ? '/billing' : ''),
            method: 'POST', data: {data: payload}, nonce: restNonce
        }).then(function (response) {
            if (billing) { scope.html(response); }
            else if (clicked.closest('.ic-api-response').length) { clicked.closest('.ic-api-response').replaceWith(response); }
            else { scope.find('.ic-api-response').remove(); scope.append(response); }
        }).catch(function (error) {
            var message = jQuery('<div class="al-box error" role="alert">').text(error.message || 'Please try again.');
            if (billing) { scope.empty().append(message); }
            else { scope.find('.ic-api-response').remove(); scope.append(jQuery('<div class="ic-api-response">').append(message)); }
        }).finally(function () { clicked.prop('disabled', false); form.find('.spinner').hide(); });
    }
    customer_data.on('submit', '.ic-billing-form', function (e) {
        e.preventDefault(); saveCustomerForm(jQuery(this), jQuery(this).find('button'), true);
    });
    customer_data.on('click', '.ic-save-customer-data button', function (e) {
        e.preventDefault(); saveCustomerForm(jQuery(this).closest('form'), jQuery(this), false);
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
 };

jQuery(document).ready(window.icInitializeCustomerPanel);
jQuery(document).on('ic:license-panel-updated', window.icInitializeCustomerPanel);
