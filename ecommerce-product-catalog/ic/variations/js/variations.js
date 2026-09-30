/*!
 impleCode Shopping Cart v1.0.0 - 2014-08-27
 Adds appropriate scripts to admin settings
 (c) 2014 Norbert Dreszer - http://implecode.com
 */

jQuery(document).ready(function () {
    /* globals ic_variations, product_object */
    jQuery.ic.addAction('ic_cart_variation_after_update', function (swicher, product_id) {
        ic_variations_exluded_front(swicher, 1);
    });
    jQuery.ic.addAction('ic_cart_add_form_before_submit', function (add_cart_form) {
        add_cart_form.find('.variation_select').attr('disabled', true);
    });
    jQuery.ic.addAction('ic_cart_add_form_after_response', function (add_cart_form) {
        add_cart_form.find('.variation_select').attr('disabled', false);
    });
    jQuery(document).on('click', '.delete_variation', function () {
        var var_id = jQuery(this).data('var_num');
        var var_lp = jQuery(this).data('var_lp');
        var qty_field = jQuery(this).next('.ic-current-quantity');
        var min_qty = parseInt(qty_field.attr('min'));
        var current_qty = parseInt(qty_field.val());
        if (current_qty !== min_qty) {
            qty_field.val(min_qty).trigger('change');
        }
        if (var_lp !== undefined) {
            var variation_selector = jQuery(this).closest('tr').find('.td-name .variation_select[data-var_num="' + var_id + '"][data-var_lp="' + var_lp + '"]');
            if (variation_selector.is(':checkbox') || variation_selector.is(':radio')) {
                variation_selector.attr('checked', false);
            } else {
                variation_selector.val('');
            }
        } else {
            jQuery(this).closest('tr').find('.td-name .variation_select[data-var_num="' + var_id + '"]').val("");
        }
        jQuery(this).closest('.td-qty').find('.edit-product-quantity').trigger('change');
    });
    jQuery(document).on('change', '.cart-products .ic-current-quantity', function () {
        var qty_field = jQuery(this);
        var qty = parseInt(qty_field.val());
        if (qty === 0) {
            qty_field.prevAll('.delete_variation').first().trigger('click');
        } else {
            var var_id = jQuery(this).data('var_num');
            var var_lp = jQuery(this).data('var_lp');
            var variation_selector;
            if (var_lp !== undefined) {
                variation_selector = jQuery(this).closest('tr').find('.td-name .variation_select[data-var_num="' + var_id + '"][data-var_lp="' + var_lp + '"]');
            } else {
                variation_selector = jQuery(this).closest('tr').find('.td-name .variation_select[data-var_num="' + var_id + '"]');
            }
            if (variation_selector.is(':checkbox')) {
                variation_selector.attr('checked', true);
            }

            jQuery(this).closest('.td-qty').find('.edit-product-quantity').trigger('change');
        }
    });
    jQuery('.product-entry #product_details .variations-container .ic-current-quantity').on('change', function (event, forced) {
//jQuery( ".product-entry #product_details .variation_select" ).trigger( "change" );
        var event_trigger_args = [];
        if (forced === undefined) {
            event_trigger_args = ['force'];
        }
        var var_qty_field = jQuery(this);
        var var_qty_name = var_qty_field.attr('name').replace('[]', '');
        var all_this_fields = jQuery(this).closest('.variations-container').find('input[name="' + var_qty_name + '[]"]');
        var var_qty = 0;
        if (all_this_fields.length > 1) {
            all_this_fields.each(function () {
                var_qty += jQuery(this).val() + ic_variations.checkbox_separator;
            });
        } else {
            var_qty = parseInt(var_qty_field.val());
        }
        var save_field = jQuery('form.add-to-shopping-cart input[name="' + var_qty_name + '"]');
        var prev_qty = save_field.val();
        if (prev_qty === var_qty) {
            return;
        }
        save_field.val(var_qty);
        var variation_field = ic_find_aty_var_field(var_qty_field);
        if (variation_field.length > 1) {
            if (!Number.isInteger(var_qty)) {
                var_qty = var_qty.split(ic_variations.checkbox_separator);
            }
            if (!Number.isInteger(prev_qty)) {
                if (prev_qty !== undefined) {
                    prev_qty = prev_qty.split(ic_variations.checkbox_separator);
                }
            }
            var key = 0;
            variation_field.each(function () {
                var this_qty = parseInt(var_qty[key]);
                var this_prev_qty = parseInt(prev_qty[key]);
                var this_variation_field = jQuery(this);
                var this_min_qty = var_qty_field.attr('min');
                if (this_qty === 0) {
                    if (this_variation_field.length && this_variation_field.is(':checked')) {
                        this_variation_field.attr('checked', false);
                        this_variation_field.trigger('change', event_trigger_args);
                    }
                } else if (this_qty > this_min_qty) {
                    this_variation_field.attr('checked', true);
                    this_variation_field.trigger('change', event_trigger_args);
                }
                key++;
            });
        } else {

            prev_qty = parseInt(prev_qty);
            if (var_qty === 0) {
                if (variation_field.length) {
                    var current = '';
                    if ((variation_field.is(':checkbox') || variation_field.is(':radio')) && variation_field.is(':checked')) {
                        current = variation_field.val();
                    } else {
                        current = variation_field.val();
                    }
                    if (current !== '') {
                        if ((variation_field.is(':checkbox') || variation_field.is(':radio'))) {
                            variation_field.attr('checked', false);
                        } else {
                            variation_field.val('');
                        }
                        variation_field.trigger('change', event_trigger_args);
                    }
                }
            } else if (prev_qty !== var_qty) {
                variation_field.trigger('change', event_trigger_args);
            }
        }
    });
    jQuery('.variable-product .variations-container').each(function () {
        var initial_shipping = jQuery(this).closest('.variable-product').find('.shipping-table-container').first();
        jQuery(this).data('base-shipping', initial_shipping.length ? initial_shipping.clone() : '');
    });
    jQuery('.variation_select').on('change', function (event, force) {
        var dropdown = jQuery(this);
        var variation = dropdown.attr('name');
        var value = '';
        if (dropdown.is('input:radio')) {
            if (dropdown.is(':checked')) {
                value = dropdown.val();
            }
        } else if (dropdown.is('input:checkbox')) {
            var container = dropdown.closest('.variation-checkbox-container');
            var checkbox_value = '';
            if (dropdown.is(':checked')) {
                checkbox_value = dropdown.val();
            }
            container.find('.variation_select').each(function () {
                if (jQuery(this).is(':checked')) {
                    value += jQuery(this).val();
                }
            });
        } else {
            value = dropdown.val();
        }
        var selected_variations_field = jQuery('form.add-to-shopping-cart input[name="' + variation.replace('[]', '') + '"]');
        // var selected_variations_lp_field = jQuery( "form.add-to-shopping-cart input[name=" + variation.replace( "[]", "" ).replace( "_variation_", "_variation_lp_" ) + "]" );
        var current_value = selected_variations_field.val();
        if (current_value === value && force === undefined) {
// return;
        }

        selected_variations_field.val(value);
        if (value !== '') {
            dropdown.closest('.variations-container').find('.ic-variation-selected').removeClass('ic-variation-selected');
            dropdown.closest('.ic-radio-variation').addClass('ic-variation-selected');
        }
        var var_lp = ic_get_selected_lp(dropdown);
        // selected_variations_lp_field.val( var_lp );
        var force_update = 0;
        jQuery('.variations-container .ic-current-quantity').each(function () {
            var min = parseInt(jQuery(this).attr('min'));
            if (min === 0) {
                force_update = 1;
                return false;
            }
        });
        var dropdown_container = dropdown.closest('.variations-container');
        if (dropdown_container.length === 0) {
            dropdown_container = dropdown.closest('div');
        }
        var button_container = dropdown_container.nextAll('.add_to_cart_form_container').first();
        if (button_container.length === 0) {
            button_container = dropdown_container.parent('div').find('.add_to_cart_form_container').first();
        }
        if (button_container.length === 0) {
            button_container = dropdown_container.closest('.product-variation').parent('div').find('.add_to_cart_form_container').first();
            if (button_container.length === 1) {
                dropdown_container = dropdown_container.closest('.product-variation').parent('div');
            }
        }
        if (button_container.length === 0) {
            button_container = dropdown_container.closest('.product-variation').parent('td').find('.add_to_cart_form_container').first();
            if (button_container.length === 1) {
                dropdown_container = dropdown_container.closest('.product-variation').parent('td');
            }
        }
        var all_selected = 0;
        var var_num = 0;
        var var_qty_field;
        if (value !== '' || force_update) {
            var product_id = dropdown.data('product_id');
            if (!product_id) {
                product_id = jQuery('input[name="current_product"]').val();
            }

            jQuery('form input[name="' + variation.replace('[]', '') + '"]').val(value);
            var variation_id = variation.substr(0, variation.indexOf('_'));
            //var var_qty_field = dropdown.prevAll( ".ic-current-quantity" ).first();
            var_qty_field = ic_find_var_qty_field(dropdown);

            if (var_qty_field.length) {
                var min_qty = var_qty_field.attr('min');
                var var_qty = parseInt(var_qty_field.val());
                var check_value = value;
                if (checkbox_value !== undefined) {
                    check_value = checkbox_value;
                }

                if (check_value !== '' && var_qty === 0) {
                    var_qty = 1;
                    var_qty_field.val(var_qty);
                    var_qty_field.trigger('change', ['forced']);
                } else if (check_value === '' && var_qty !== min_qty) {
                    var_qty = min_qty;
                    var_qty_field.val(var_qty);
                    var_qty_field.trigger('change', ['forced']);
                }
            }
            var values = {};
            var var_lps = {};
            var var_qties = {};
            all_selected = 1;
            var_num = dropdown.data('var_num');
            /*
             jQuery( '.product-entry #product_details .variation_select:radio:checked' ).each( function ( ) {
             var this_dropdown = jQuery( this );
             var this_value = this_dropdown.val( );
             var this_name = this_dropdown.attr( "name" ).replace( "[]", "" );
             if ( this_value === '' ) {
             this_value = 'not_selected';
             all_selected = 0;
             }
             values[this_name] = this_value;
             var var_qty_field = this_dropdown.prevAll( ".ic-current-quantity" ).first( );
             if ( var_qty_field.length ) {
             var var_qty = parseInt( var_qty_field.val( ) );
             var_qties[this_name] = var_qty;
             }
             } );
             *
             */
            var all_var_selectors = dropdown_container.find('.variation_select');
            all_var_selectors.each(function () {
                var this_dropdown = jQuery(this);
                var this_name = this_dropdown.attr('name').replace('[]', '');
                if ((this_dropdown.is(':radio')/* || this_dropdown.is( ":checkbox" )*/) && !this_dropdown.is(':checked')) {
                    if (all_selected === 1) {
                        all_selected = 0;
                        var this_radio_selectors = dropdown_container.find('.variation_select[name="' + this_name + '"]');
                        this_radio_selectors.each(function () {
                            if (jQuery(this).is(':checked')) {
                                all_selected = 1;
                            }
                        });
                    }
                    return true;
                }
                if (var_num === 1) {
                    this_dropdown.find('option:disabled').each(function () {
                        jQuery(this).prop('disabled', false);
                        jQuery(this).show();
                    });
                }
                var this_value = '';
                if (this_dropdown.is(':checkbox') || this_dropdown.is(':radio')) {
                    if (this_dropdown.is(':checked')) {
                        this_value = this_dropdown.val();
                    }
                } else {
                    this_value = this_dropdown.val();
                }
                if (this_value === '' && !this_dropdown.is(':checkbox')) {
                    this_value = 'not_selected';
                    all_selected = 0;
                }
                if (this_dropdown.is(':checkbox')) {
                    if (jQuery.isEmptyObject(values[this_name])) {
                        values[this_name] = this_value;
                    } else {
                        values[this_name] += this_value;
                    }
                } else {
                    values[this_name] = this_value;
                }
                var this_var_lp = ic_get_selected_lp(this_dropdown);
                if (this_dropdown.is(':checkbox')) {
                    if (jQuery.isEmptyObject(var_lps[this_name])) {
                        var_lps[this_name] = this_var_lp;
                    } else {
                        var_lps[this_name] += this_var_lp;
                    }
                } else {
                    var_lps[this_name] = this_var_lp;
                }

                //var var_qty_field = this_dropdown.prevAll( ".ic-current-quantity" ).first( );
                var var_qty_field = ic_find_var_qty_field(this_dropdown);
                if (var_qty_field.length) {
                    var var_qty = parseInt(var_qty_field.val());
                    if (this_dropdown.is(':checkbox')) {
                        var_qty += ic_variations.checkbox_separator;
                        if (jQuery.isEmptyObject(var_qties[this_name])) {
                            var_qties[this_name] = var_qty;
                        } else {
                            var_qties[this_name] += var_qty;
                        }
                    } else {
                        var_qties[this_name] = var_qty;
                    }
                }
            });
        }

        if (var_num === 1) {
            all_selected = ic_variations_exluded_front(dropdown, all_selected);
        }
        if (force_update) {
            all_selected = 1;
        }
        if (all_selected === 1) {
            var single_price_effect_container = dropdown.closest('.variable-single-price-effect');
            var variable_price_effect_container = dropdown.closest('.variable-price-effect');
            single_price_effect_container.find('.price-container').fadeTo("slow", 0);
            single_price_effect_container.find('.shipping-table-container').fadeTo("slow", 0);
            single_price_effect_container.find('.sku-table').fadeTo("slow", 0);
            var spinner = dropdown.next('.ic_spinner');
            if (spinner.length < 1) {
                spinner = dropdown.next('label').next('.ic_spinner');
            }
            spinner.show().css('display', 'inline-block');
            var fields = jQuery.ic.applyFilters('ic_variation_details_fields', ['shipping', 'in_cart', 'price', 'sku', 'size', 'weight', 'image']);
            jQuery.when(ic_get_variation_details(fields, values, product_id, variation_id, var_lp, var_qties, var_lps)).done(function (out) {
                if (out === '') {
                    spinner.hide();
                    return;
                }
                out = JSON.parse(out);
                if (out[2] !== '' && out[2] !== undefined) {
                    variable_price_effect_container.find('td.price-value').html(out[2]);
                    //jQuery('.variable-price-effect td.price-value').first().text(out[2]);
                }
                //jQuery( ".variable-price-effect .price-container" ).fadeIn( );
                if (out[3] !== '') {
                    jQuery('.variable-product td.sku-value').first().text(out[3]);
                    //jQuery( ".variable-price-effect .sku-table" ).fadeIn( );
                }
                if (out[4] !== '') {
                    jQuery('.variable-product td.size-value').first().text(out[4]);
                    jQuery('.variable-price-effect .size-table').fadeIn();
                }
                if (out[5] !== '') {
                    jQuery('.variable-product td.weight-value').first().text(out[5]);
                    jQuery('.variable-price-effect .weight-table').fadeIn();
                }
                var product_image = jQuery('.variable-product .product-image img');
                if (jQuery('.variable-product .product-image a.a-product-image').length) {
                    product_image = jQuery('.variable-product .product-image a.a-product-image');
                }
                if (out[6] !== '') {
                    if (!jQuery('.main-image-to-restore').length) {
                        product_image.first().clone().appendTo('.variable-product #product_details').hide().addClass('main-image-to-restore');
                    }
                    product_image.first().replaceWith(out[6]);
                } else if (jQuery('.main-image-to-restore').length) {
                    product_image.first().replaceWith(jQuery('.main-image-to-restore').clone().removeClass('main-image-to-restore').show());
                    jQuery('.main-image-to-restore').remove();
                }
                if (out[1] === '1') {
                    button_container.find('.cart_info').hide();
                    button_container.find('form.add-to-shopping-cart button').hide();
                    button_container.find('form.add-to-shopping-cart input[type="image"]').hide();
                    button_container.find('form.add-to-shopping-cart input[name="current_quantity"]').hide();
                    button_container.find('form.add-to-shopping-cart .cart-added-info').show().css('display', 'block');
                } else {
                    button_container.find('.cart_info').show();
                    button_container.find('form.add-to-shopping-cart button').fadeIn();
                    button_container.find('form.add-to-shopping-cart input[type="image"]').fadeIn();
                    button_container.find('form.add-to-shopping-cart input[name="current_quantity"]').fadeIn();
                    button_container.find('form.add-to-shopping-cart .cart-added-info').hide();
                }

                if (jQuery('.variable-product .shipping-table-container').length) {
                    jQuery('.variable-product .shipping-table-container').replaceWith(out[0]);
                } else {
                    jQuery('.variable-product #product_details').append(out[0]);
                }
                jQuery('.variable-product .variations-container').data('shipping-modified', 1);
//jQuery( ".variable-price-effect .shipping-table-container" ).show( );
                jQuery('.variable-price-effect .price-container, .variable-price-effect .shipping-table-container, .variable-price-effect .sku-table').fadeTo('slow', 1);
                jQuery.ic.doAction('ic_update_variation_details', [out, product_id]);
                spinner.hide();
            });
        } else if (jQuery('article.al_product').hasClass('variable-single-price-effect')) {
            if (jQuery('.price-container').is(':visible')) {
                jQuery('.price-container').fadeTo('fast', 0).css('display', 'none');
            }
            jQuery('.shipping-table-container').fadeTo('fast', 0);
            jQuery('.sku-table').fadeTo('fast', 0);
        } else if (jQuery('article.al_product').hasClass('variable-single-price-effect')) {
            if (jQuery('.price-container').is(':visible')) {
                jQuery('.price-container').fadeTo('fast', 0).css('display', 'none');
            }
            jQuery('.shipping-table-container').fadeTo('fast', 0);
            jQuery('.sku-table').fadeTo('fast', 0);
        } else {
            jQuery('.variable-price-effect td.price-value').first().text(jQuery('.variable-price-effect .variations-container').data('base-price'));
            var base_container = jQuery('.variable-product .variations-container').first();
            var base_shipping = base_container.data('base-shipping');
            if (base_container.data('shipping-modified')) {
                if (base_shipping) {
                    if (jQuery('.variable-product .shipping-table-container').length) {
                        jQuery('.variable-product .shipping-table-container').replaceWith(base_shipping.clone());
                    } else {
                        jQuery('.variable-product #product_details').append(base_shipping.clone());
                    }
                } else {
                    jQuery('.variable-product .shipping-table-container').remove();
                }
                base_container.removeData('shipping-modified');
            }
            button_container.find('.cart_info').show();
            button_container.find('form.add-to-shopping-cart button').fadeIn();
            button_container.find('form.add-to-shopping-cart input[type="image"]').fadeIn();
            button_container.find('form.add-to-shopping-cart input[name="current_quantity"]').fadeIn();
            button_container.find('form.add-to-shopping-cart .cart-added-info').hide();
        }
        if (value === '') {
            var_qty_field = dropdown.prevAll('.ic-current-quantity').first();
            if (var_qty_field.length) {
                var_qty = var_qty_field.attr('min');
                var_qty_field.val(var_qty);
                var_qty_field.trigger('change');
            }
        }
        /*
         console.log( var_lp );
         var data = {
         'action': 'modify_variations_price',
         'selected_variation': values,
         'product_id': product_id,
         'variation_id': variation_id,
         'selected_variation_lp': var_lp,
         //'selected_variation_lps': var_lps,
         };
         jQuery( ".product-entry #product_details .variation_select" ).trigger( "variation_select", data );
         * *
         */
    });
    jQuery('.product-entry .variation_select').each(function () {
        jQuery(this).trigger('change');
    });
    jQuery(document).on('change', '.cart-products .variation_select', function () {
        var swicher = jQuery(this);
        //var swicher_product_id = swicher.closest( ".variations-container" ).data( "product_id" );
        // var swicher_var_id = swicher.data( "var_num" );
        var swicher_val = swicher.val();
        //var switcher_selected_lp = ic_get_selected_lp( swicher );
        //var selected_lp_name = swicher_var_id + '_variation_lp_' + swicher_product_id;
        //var selected_lp_name = ic_var_get_selected_lp_input_name( swicher );
        //jQuery( 'input[name="' + selected_lp_name + '"]' ).val( switcher_selected_lp );
        var var_qty_field = ic_find_var_qty_field(swicher);
        if (var_qty_field.length) {
            var min = parseInt(var_qty_field.attr('min'));
            var qty = parseInt(var_qty_field.val());
            if (swicher_val === '') {
                var_qty_field.val(min);
            } else if (qty === min && min < 1) {
                var_qty_field.val(1);
            }
        }
        var tr = swicher.closest('tr');
        var all_swichers = tr.find('.variation_select');
        var all_selected = true;
        all_swichers.each(function () {
            var this_switcher = jQuery(this);
            var var_qty_field = ic_find_var_qty_field(this_switcher);
            var var_value = this_switcher.val();
            if (var_qty_field.length) {
                var min = parseInt(var_qty_field.attr("min"));
                if (min === 0) {
                    all_selected = true;
                    return false;
                }
            }

            if (var_value === '') {
                all_selected = false;
                return false;
            }
        });
        if (all_selected) {
            ic_variations_update_shopping_row_variation(swicher);
        }
    });
    jQuery('.product-list').on('change', '.table-listing-element .variation_select', function () {
        ic_variations_update_archive_variation_price(jQuery(this));
    });
});

function ic_variations_get_ajax_url() {
    if (typeof product_object !== 'undefined' && product_object.ajaxurl) {
        return product_object.ajaxurl;
    }
    if (typeof ic_cart_ajax_object !== 'undefined' && ic_cart_ajax_object.ajax_url) {
        return ic_cart_ajax_object.ajax_url;
    }

    return '';
}

function ic_variations_format_price(value) {
    if (typeof ic_cart_ajax_object === 'undefined') {
        return value;
    }

    if (typeof ic_number_format === 'function') {
        return ic_number_format(value, 2, ic_cart_ajax_object.dec_sep, ic_cart_ajax_object.th_sep);
    }

    return value;
}

function ic_variations_update_shopping_row_variation(swicher) {
    var product_id = swicher.closest('tr').find('.product_id').val();
    var values = {};
    var row = swicher.closest('tr');
    var quantity = row.find('.edit-product-quantity').val();

    if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
        jQuery.ic.doAction('ic_cart_variation_before_update', swicher, product_id);
    }

    row.find('.variation_select').each(function () {
        var this_value = jQuery(this).val();
        var this_name = jQuery(this).attr('name');
        if (this_value === '') {
            this_value = 'not_selected';
        }
        values[this_name] = this_value;
    });

    if (!product_id) {
        row.find('.edit-product-quantity').trigger('change');
        if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
            jQuery.ic.doAction('ic_cart_variation_after_update', swicher, product_id);
        }
        return;
    }

    if (typeof ic_hide_products_table === 'function') {
        ic_hide_products_table();
    }
    jQuery.post(
        ic_variations_get_ajax_url(),
        {
            'action': 'get_shopping_cart_product_price',
            'selected_variation': values,
            'product_id': product_id,
            'quantity': quantity,
            'ic_nonce': product_object.variation_nonce
        },
        function (response) {
            row.find('.td-price').text(ic_variations_format_price(response));
            if (typeof ic_show_products_table === 'function') {
                ic_show_products_table();
            }
            row.find('.edit-product-quantity').trigger('change');
            if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
                jQuery.ic.doAction('ic_cart_variation_after_update', swicher, product_id);
            }
        }
    );
}

function ic_variations_update_archive_variation_price(swicher) {
    var variation = swicher.attr('name');
    var value = swicher.val();
    var current_row = swicher.parents('.table-listing-element');
    var product_id = current_row.find('input[name="current_product"]').val();
    var variation_id = variation.substr(0, variation.indexOf('_'));

    current_row.find('form.add-to-shopping-cart input[name="' + variation + '"]').val(value);

    var values = {};
    var var_lps = {};
    var var_qties = {};
    var mod_prices = false;

    current_row.find('.variation_select').each(function () {
        var this_var_num = jQuery(this).data('var_num');
        var this_value = jQuery(this).val();
        var this_lp = jQuery(this).find(':selected').data('var_lp');
        var this_name = jQuery(this).attr('name');

        if (this_value === '') {
            this_value = 'not_selected';
        }
        values[this_name] = this_value;
        var_lps[this_name] = this_lp;

        var var_qty_field = jQuery(this).closest('tr').find('.delete_variation[data-var_num=' + this_var_num + ']').next('.ic-current-quantity');
        if (var_qty_field.length) {
            var_qties[this_name] = parseInt(var_qty_field.val(), 10);
        }
        if (jQuery(this).attr('data-mod-prices')) {
            mod_prices = true;
        }
    });

    if (!mod_prices) {
        return;
    }

    jQuery.post(
        ic_variations_get_ajax_url(),
        {
            'action': 'modify_variations_price',
            'selected_variation': values,
            'product_id': product_id,
            'variation_id': variation_id,
            'var_qties': var_qties,
            'selected_variation_lps': var_lps,
            'ic_nonce': product_object.variation_nonce
        },
        function (response) {
            var price_container = current_row.find('div.product-price span');
            if (price_container.length === 0) {
                price_container = current_row.find('div.product-price');
            }
            price_container.text(response);
        }
    );
}

function ic_find_var_qty_field(var_selector) {
    var this_var_num = var_selector.data('var_num');
    var var_qty_field = var_selector.closest('tr').find('.delete_variation[data-var_num=' + this_var_num + ']').next('.ic-current-quantity');
    if (var_qty_field.length < 1) {
        var_qty_field = var_selector.closest('.variations-container').find('input.ic-current-quantity[data-var_num=' + this_var_num + ']');
    }
    if (var_qty_field.length > 1) {
        this_var_num = var_selector.data('var_lp');
        if (this_var_num !== undefined) {
            this_var_num = parseInt(this_var_num);
            var new_qty_field = var_qty_field;
            var_qty_field.each(function () {
                var this_qty_field = jQuery(this);
                var this_qty_var_num = this_qty_field.data('var_lp');
                if (this_qty_var_num !== undefined) {
                    this_qty_var_num = parseInt(this_qty_var_num);
                }
                if (this_qty_var_num === this_var_num) {
                    new_qty_field = this_qty_field;
                    return false;
                }
            });
            return new_qty_field;
        }
    }
    return var_qty_field;
}

function ic_find_aty_var_field(var_qty_field) {
    var variation_field = var_qty_field.next('.variation_select');
    var qty_container;
    if (variation_field.length < 1) {
        qty_container = var_qty_field.closest('div.variation-qty-container');
        if (qty_container.length) {
            variation_field = qty_container.next('.variation_select');
        }
    }
    if (variation_field.length < 1) {
        qty_container = var_qty_field.closest('div.variation-qty-container');
        if (qty_container.length) {
            variation_field = qty_container.next('div.variation-dropdown-container-inside').find('.variation_select');
        }
    }
    if (variation_field.length < 1) {
        var checkbox_qty_container = var_qty_field.closest('div.variation-checkbox-qty-container');
        if (checkbox_qty_container.length) {
            variation_field = checkbox_qty_container.next('div.variation-checkbox-container-inside').find('.variation_select');
        }
    }
    if (variation_field.length < 1) {
        var radio_qty_container = var_qty_field.closest('div.variation-qty-container');
        if (radio_qty_container.length) {
            variation_field = radio_qty_container.next('div.variation-radio-container-inside').find('.variation_select:checked');
        }
    }
    return variation_field;
}

function ic_get_variation_details(what, values, product_id, variation_id, var_lp, var_qties, var_lps) {
    var data = {
        'action': 'get_viariation_details',
        'selected_variation': values,
        'selected_variation_lp': var_lp,
        //'selected_variation_lps': var_lps,
        'product_id': product_id,
        'variation_id': variation_id,
        'variation_field': what,
        'var_qties': var_qties,
        'ic_nonce': product_object.variation_nonce
    };
    return jQuery.post(product_object.ajaxurl, data);
}

function ic_variations_exluded_front(dropdown, all_selected) {
    var dropdown_container = dropdown.closest(".variations-container");
    var excluded = ic_variation_get_excluded(dropdown);
    if (excluded !== undefined && excluded !== '') {
        jQuery.each(excluded, function (var_num, lp_arr) {
            var select = dropdown_container.find('select[data-var_num=' + var_num + ']');
            jQuery.each(lp_arr, function (key, var_lp) {
                var disabled_option = select.find('option[data-var_lp=' + var_lp + ']');
                disabled_option.prop('disabled', true);
                if (ic_variations.disable_hidden === '1') {
                    disabled_option.hide();
                }
                if (disabled_option.is(':selected')) {
                    disabled_option.prop('selected', false);
                    select.trigger('change');
                    if (all_selected === 1) {
                        all_selected = 0;
                    }
                }
            });
        });
    }
    return all_selected;
}

function ic_variation_get_excluded(selector) {
    var excluded = '';
    if (selector.is(':radio:checked')) {
        excluded = selector.data('excluded');
    } else if (selector.is(':checkbox')) {
        var checkboxes = selector.closest('.variation-checkbox-container-inside').find('input:checked');
        excluded = checkboxes.data('excluded');
    } else {
        excluded = selector.find('option:selected').data('excluded');
    }
    return excluded;
}

function ic_get_selected_lp(selector) {
    var var_lp = '';
    if (selector.is(':radio') || selector.is(':checkbox')) {
        var selector_name = selector.attr('name');
        var this_selectors = selector.closest('.variations-container').find('.variation_select[name="' + selector_name + '"]');
        if (selector.is(':checkbox')) {
            var_lp = [];
        }
        this_selectors.each(function () {
            if (jQuery(this).is(':checked')) {
                var this_var_lp = jQuery(this).data('var_lp');
                if (selector.is(':radio')) {
                    var_lp = this_var_lp;
                } else {
                    var_lp.push(this_var_lp);
                }
            }
        });
    } else {
        var_lp = selector.find('option:selected').data('var_lp');
    }
    return var_lp;
}

function ic_var_get_selected_lp_input_name(selector) {
    var name = selector.attr('name');
    return name.replace('[]', '').replace('_variation_', '_variation_lp_');
}
