/*!
impleCode Shopping Cart v1.0.0 - 2014-08-27
Adds appropriate scripts to admin settings
(c) 2014 Norbert Dreszer - http://implecode.com
 */

jQuery( document ).ready(
	function () {
		/* global product_object */
		if ( typeof product_object !== 'undefined' && ! window.ic_cart_nonce_prefilter ) {
			window.ic_cart_nonce_prefilter = true;
			jQuery.ajaxPrefilter( function ( options, original_options ) {
				if ( ! product_object.ajaxurl || options.url !== product_object.ajaxurl || ! original_options.data ) {
					return;
				}
				var action_match = String( original_options.data ).match( /(?:^|&)action=([^&]+)/ );
				var action       = action_match ? decodeURIComponent( action_match[1] ) : ( original_options.data.action || '' );
				var nonce_map    = {
					ic_add_to_cart: 'cart_add_nonce', ic_get_cart_content: 'cart_content_nonce',
					ic_get_cart_button: 'cart_button_nonce', ic_get_sitewide_icons: 'cart_icons_nonce',
					ic_cart_update_products_table: 'cart_update_nonce', ic_cart_update_shipping_selection: 'cart_shipping_nonce',
					ic_quote_cart_update_products_table: 'quote_cart_update_nonce', ic_quote_cart_update_shipping_selection: 'quote_cart_shipping_nonce',
					ic_price_format: 'cart_price_nonce', ic_cart_fast_checkout_submit: 'cart_fast_checkout_nonce',
					ic_shopping_add_cart_get: 'cart_add_button_nonce', ic_get_variation_selectors: 'variation_selectors_nonce',
					get_viariation_details: 'variation_nonce', modify_variations_price: 'variation_nonce',
					get_shopping_cart_product_price: 'variation_nonce', modify_variations_shipping: 'variation_nonce',
					shopping_cart_products: 'shopping_cart_products_nonce', restore_shopping_cart: 'restore_shopping_cart_nonce',
					ic_refresh_success_page: 'refresh_success_nonce', ic_state_dropdown: 'formbuilder_state_nonce',
					ic_address_lines: 'formbuilder_address_nonce', ic_formbuilder_save_field: 'formbuilder_save_nonce',
					ic_premium_state_dropdown: 'premium_state_nonce', ic_premium_address_lines: 'premium_address_nonce'
				};
				var nonce_key = nonce_map[ action ];
				if ( nonce_key && product_object[ nonce_key ] ) {
					options.data = typeof options.data === 'string' ? options.data + '&ic_nonce=' + encodeURIComponent( product_object[ nonce_key ] ) : jQuery.extend( {}, options.data, { ic_nonce: product_object[ nonce_key ] } );
				}
			} );
		}
		var cart_button = jQuery( '.cart-widget-container.check-if-empty' );
		if (cart_button.length) {
			var label = cart_button.find( ".shopping-cart-widget" ).data( "label" );
			var data  = {
				'action': 'ic_get_cart_button',
				'label': label
			};

			jQuery.post(
				product_object.ajaxurl,
				data,
				function (response) {
					if (response && ! (response.indexOf( "empty-cart" ) >= 0)) {
						cart_button.find( "#shopping_cart_widget" ).replaceWith( response );
						ic_cart_show();
					}
				}
			);
		}
		ic_sitewide_icons_refresh();
		jQuery.ic.addAction( 'ic_cart_qties_updated', ic_sitewide_icons_refresh );
		jQuery.ic.addAction( 'ic_cart_added', ic_sitewide_icons_refresh );
		var cart_ajax_submit_on = true;
		jQuery( 'body' ).on(
			'submit',
			'.add_to_cart_form_container form.reg_add',
			function (e) {
				if (jQuery( this ).attr( 'action' ) !== '' || cart_ajax_submit_on === false) {
					return true;
				}
				e.preventDefault();
				var add_cart_form  = jQuery( this );
				var form_data      = add_cart_form.serialize();
				var cart_widget    = 0;
				var cart_container = 0;
				var cart_icon      = 0;
				if (jQuery( "#shopping_cart_widget" ).length) {
					cart_widget = 1;
				}
				if (jQuery( "table.cart-products" ).length) {
					cart_container = 1;
				}
				if (jQuery( '.ic-bar-icon .dashicons-cart' ).length) {
					cart_icon = 1;
				}
				label    = cart_button.find( ".shopping-cart-widget" ).data( "label" );
				var data = {
					'action': 'ic_add_to_cart',
					'add_cart_data': form_data,
					'cart_widget': cart_widget,
					'cart_container': cart_container,
					'cart_icon': cart_icon,
					'label': label
				};
				// jQuery( "#product_details" ).css( "opacity", "0.5" );
				add_cart_form.css( "opacity", "0.5" );
				var add_cart_button = add_cart_form.find( ".button" );
				add_cart_button.attr( "disabled", true );
				add_cart_button.after( '<div class="ic_spinner" style="display: inline-block"></div>' );
				if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
					jQuery.ic.doAction( 'ic_cart_add_form_before_submit', add_cart_form );
				}
				jQuery.post(
					product_object.ajaxurl,
					data,
					function (response) {
						jQuery.ic.doAction( "ic_cart_added", response );
						// response = jQuery.parseJSON( response );
						// response = JSON.parse(response);
						try {
							response = JSON.parse( response );
						} catch (e) {
							cart_ajax_submit_on = false;
							add_cart_form.submit();
							return false;
						}
						if ( ! response) {
							cart_ajax_submit_on = false;
							add_cart_form.submit();
							return false;
						}
						if (jQuery( '.add_to_cart_added_popup' ).length) {
							jQuery( '.add_to_cart_added_popup' ).show();
							jQuery( '.ic-overlay' ).first().show();
						} else {
							if (add_cart_form.find( ".cart-added-info" ).length) {
								add_cart_form.find( ".cart-added-info" ).fadeIn().css( "display", "inline-block" );
							} else if (response["cart-added-info"]) {
								jQuery( response["cart-added-info"] ).hide().appendTo( add_cart_form ).fadeIn();
							}
							add_cart_button.hide();
							add_cart_form.find( "input" ).hide();
							add_cart_form.next( ".cart_info" ).hide();
						}
						// jQuery( "#product_details" ).css( "opacity", "1" );
						add_cart_form.css( "opacity", "1" );
						add_cart_button.attr( "disabled", false );
						if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
							jQuery.ic.doAction( 'ic_cart_add_form_after_response', add_cart_form, response );
						}
						add_cart_form.find( ".ic_spinner" ).remove();
						if (response["cart-widget"]) {
							jQuery( ".ic-cart-button-container" ).replaceWith( response["cart-widget"] );
							ic_cart_show();
						}
						if (response["cart-container"]) {
							jQuery( "table.cart-products" ).replaceWith( response["cart-container"] );
						}
						if (response['icons-bar']) {
							jQuery( '#ic-catalog-menu-bar' ).replaceWith( response['icons-bar'] );
						}
						jQuery( ".to_cart_submit" ).show();
					}
				);
			}
		);
	}
);

function ic_cart_show() {
	var cart_button = jQuery( '.cart-widget-container.check-if-empty' );
	cart_button.removeClass( "ic_hidden" );
	var content = jQuery( ".cart-hide-container" ).html();
	jQuery( ".cart-hide-container" ).after( content );
	jQuery( ".cart-hide-container" ).remove();
}

function ic_sitewide_icons_refresh() {
	var cart_icon = jQuery( '.ic-bar-icon .dashicons-cart' );
	if (cart_icon.length) {
		var icons_data = {
			'action': 'ic_get_sitewide_icons',
		};

		jQuery.post(
			product_object.ajaxurl,
			icons_data,
			function (response) {
				if (response) {
					jQuery( '#ic-catalog-menu-bar' ).replaceWith( response );
				}
			}
		);
	}
}

function ic_cart_update_products_table(data) {
	var container = ic_cart_get_checkout_products_container();
	var request;

	if ( ! container.length) {
		return;
	}

	ic_disable_container( container );
	request = jQuery.post(
		product_object.ajaxurl,
		data,
		function (response) {
			jQuery.when( ic_cart_apply_updated_products_table( response ) ).then( ic_cart_after_updated_products_table( response ) );
		}
	).always(
		function () {
			ic_enable_container( container );
		}
	);

	return request;
}

function ic_cart_apply_updated_products_table(response) {
	var form               = ic_cart_get_checkout_form();
	var responseMarkup     = jQuery( '<div></div>' ).html( response );
	var updatedShoppingCartActions = responseMarkup.filter( '#shopping-cart-actions' ).first();
	var updatedTable       = responseMarkup.filter( '.ic-cart-products-table' ).first();
	var updatedFormButtons = responseMarkup.filter( '.form-buttons' ).last();
	var updatedButtonBlock = responseMarkup.filter( '.button-container' ).last();
	var currentShoppingCartActions;
	var currentTable;

	if ( ! form.length) {
		return;
	}

	currentTable = form.find( '.ic-cart-products-table' ).first();
	currentShoppingCartActions = form.find( '#shopping-cart-actions' ).first();
	if ( ! updatedShoppingCartActions.length) {
		updatedShoppingCartActions = responseMarkup.find( '#shopping-cart-actions' ).first();
	}
	if ( ! updatedTable.length) {
		updatedTable = responseMarkup.find( '.ic-cart-products-table' ).first();
	}
	if ( ! updatedFormButtons.length) {
		updatedFormButtons = responseMarkup.find( '.form-buttons' ).last();
	}
	if ( ! updatedButtonBlock.length) {
		updatedButtonBlock = responseMarkup.find( '.button-container' ).last();
	}

	if ( ! updatedTable.length) {
		return;
	}

	if (currentTable.length) {
		currentTable.replaceWith( updatedTable );
	} else if (currentShoppingCartActions.length) {
		currentShoppingCartActions.before( updatedTable );
	} else if (form.find( ".form-buttons:last-child" ).length) {
		form.find( ".form-buttons:last-child" ).before( updatedTable );
	} else if (form.find( ".button-container:last-child" ).length) {
		form.find( ".button-container:last-child" ).before( updatedTable );
	} else {
		form.prepend( updatedTable );
	}

	if (currentShoppingCartActions.length && updatedShoppingCartActions.length) {
		currentShoppingCartActions.replaceWith( updatedShoppingCartActions );
	} else if (form.find( '.form-buttons:last-child' ).length && updatedFormButtons.length) {
		form.find( '.form-buttons:last-child' ).replaceWith( updatedFormButtons );
	} else if (form.find( '.button-container:last-child' ).length && updatedButtonBlock.length) {
		form.find( '.button-container:last-child' ).replaceWith( updatedButtonBlock );
	}

	ic_cart_replace_updated_shipping_sections( form, responseMarkup );
	ic_cart_sync_selected_shipping_summary_price( form );
}

function ic_cart_after_updated_products_table(response) {
	if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
		jQuery.ic.doAction( "ic_checkout_table_updated", response );
	}
	if (typeof update_all_cart_price_effects === 'function' && typeof ic_cart_ajax_object !== 'undefined') {
		update_all_cart_price_effects( ic_cart_ajax_object.dec_sep, ic_cart_ajax_object.th_sep );
	}
}

function ic_cart_get_checkout_products_container() {
	if (jQuery( "#shopping-cart-submit-container" ).length) {
		return jQuery( "#shopping-cart-submit-container" );
	}
	if (jQuery( "#shopping-cart-container" ).length) {
		return jQuery( "#shopping-cart-container" );
	}
	if (jQuery( "#quote-cart-submit-container" ).length) {
		return jQuery( "#quote-cart-submit-container" );
	}
	if (jQuery( "#quote-cart-container" ).length) {
		return jQuery( "#quote-cart-container" );
	}

	return jQuery();
}

if (typeof ic_get_checkout_container !== 'function') {
	function ic_get_checkout_container() {
		if (jQuery( "#shopping-cart-submit-container" ).length) {
			return jQuery( "#shopping-cart-submit-container" );
		} else if (jQuery( "#quote-cart-submit-container" ).length) {
			return jQuery( "#quote-cart-submit-container" );
		} else {
			return null;
		}
	}
}

function ic_cart_get_checkout_form() {
	var container = ic_cart_get_checkout_products_container();

	if ( ! container.length) {
		return jQuery();
	}
	if (container.is( "#quote-cart-submit-container" ) || container.is( "#shopping-cart-submit-container" )) {
		return container.find( ".ic-form > form" ).first();
	}

	return container.find( "> form" ).first();
}

function ic_cart_shipping_is_included_in_total(checkout_container) {
	return checkout_container.find( ".ic-cart-shipping-summary-row" ).length > 0;
}

function ic_cart_is_shipping_price_effect_field(field) {
	var fieldName = field.attr( "name" ) || "";

	return field.is( "[data-ic_shipping_pre_name]" ) || /^shipping(?:_\d+)?$/.test( fieldName );
}

function ic_cart_find_response_fragments(responseMarkup, selector) {
	return responseMarkup.filter( selector ).add( responseMarkup.find( selector ) );
}

function ic_cart_capture_shipping_section_state(section) {
	var savedState = {};

	section.find( 'input[name], select[name], textarea[name]' ).each(
		function () {
			var field = jQuery( this );
			var name  = field.attr( 'name' );

			if ( ! name || ic_cart_is_shipping_price_effect_field( field )) {
				return;
			}

			if (field.is( ':checkbox' )) {
				if (savedState[ name ] === undefined) {
					savedState[ name ] = [];
				}
				if (field.is( ':checked' )) {
					savedState[ name ].push( field.val() );
				}
			} else if (field.is( ':radio' )) {
				if (savedState[ name ] === undefined) {
					savedState[ name ] = '';
				}
				if (field.is( ':checked' )) {
					savedState[ name ] = field.val();
				}
			} else {
				savedState[ name ] = field.val();
			}
		}
	);

	return savedState;
}

function ic_cart_restore_shipping_section_state(section, savedState) {
	jQuery.each(
		savedState,
		function (name, value) {
			var fields = section.find( 'input[name], select[name], textarea[name]' ).filter(
				function () {
					return jQuery( this ).attr( 'name' ) === name;
				}
			);
			var field;

			if ( ! fields.length) {
				return;
			}

			field = fields.first();
			if (field.is( ':checkbox' )) {
				fields.each(
					function () {
						var checkbox = jQuery( this );
						checkbox.prop( 'checked', jQuery.inArray( checkbox.val(), value ) !== -1 );
					}
				);
			} else if (field.is( ':radio' )) {
				fields.each(
					function () {
						var radio = jQuery( this );
						radio.prop( 'checked', radio.val() === value );
					}
				);
			} else {
				fields.val( value );
				fields.filter( 'select' ).trigger( 'chosen:updated' );
			}
		}
	);
}

function ic_cart_replace_updated_shipping_sections(form, responseMarkup) {
	var updatedSections = ic_cart_find_response_fragments( responseMarkup, '.shipping-options-section[data-ic_shipping_pre_name]' );

	if ( ! updatedSections.length) {
		return;
	}

	updatedSections.each(
		function () {
			var updatedSection = jQuery( this );
			var preName        = updatedSection.data( 'ic_shipping_pre_name' );
			var currentSection;
			var savedState;

			if ( ! preName) {
				return;
			}

			currentSection = form.find( '.shipping-options-section[data-ic_shipping_pre_name="' + preName + '"]' ).first();
			if ( ! currentSection.length) {
				return;
			}

			savedState = ic_cart_capture_shipping_section_state( currentSection );
			currentSection.replaceWith( updatedSection );
			ic_cart_restore_shipping_section_state( updatedSection, savedState );

			if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
				jQuery.ic.doAction( 'ic_checkout_shipping_section_updated', updatedSection, savedState );
			}
		}
	);
}

function ic_cart_sync_selected_shipping_summary_price(form) {
	var shippingTotalCell = form.find( '.ic-cart-shipping-summary-row .shipping-total[data-ic_odoo_shipping_total]' ).first();
	var selectedShipping  = form.find( 'input[data-ic_shipping_pre_name]:checked' ).first();
	var selectedLabel;
	var selectedPrice;
	var rawPrice;

	if ( ! shippingTotalCell.length || ! selectedShipping.length) {
		return;
	}

	rawPrice = shippingTotalCell.attr( 'data-ic_odoo_shipping_total' );

	if (typeof rawPrice === 'undefined' || rawPrice === '') {
		return;
	}

	selectedShipping.attr( 'data-price_effect', rawPrice );
	selectedLabel = form.find( 'label[for="' + selectedShipping.attr( 'id' ) + '"]' ).first();

	if ( ! selectedLabel.length) {
		return;
	}

	selectedPrice = selectedLabel.find( 'span' ).last();

	if (selectedPrice.length) {
		selectedPrice.html( '(' + shippingTotalCell.html() + ')' );
	}
}

if (typeof update_all_cart_price_effects !== 'function') {
	var ic_counted_final_price = 0;
	function update_all_cart_price_effects(dec_sep, th_sep) {
		var checkout_container = ic_get_checkout_container();
		var shipping_included  = false;
		if (checkout_container && checkout_container.length) {
			var form          = checkout_container.find( ".ic-form" );
			var effect_total  = parseFloat( 0 );
			shipping_included = ic_cart_shipping_is_included_in_total( checkout_container );
			form.find( "input[data-price_effect]:checked, option[data-price_effect]:checked, input[type=hidden][data-price_effect]" ).each(
				function () {
					var field = jQuery( this );
					var price_effect;
					if (shipping_included && ic_cart_is_shipping_price_effect_field( field )) {
						return;
					}
					price_effect = field.attr( 'data-price_effect' );
					if (price_effect !== '' && price_effect !== undefined) {
						effect_total += parseFloat( price_effect );
					}
				}
			);
			var effect_counted = jQuery( "input[name=ic_price_effect]" ).val();
			jQuery( "input[name=ic_price_effect]" ).val( effect_total );
			var effect_total_vat   = 0;
			var effect_total_gross = effect_total + effect_total_vat;
			var final_price;
			if (checkout_container.find( "input[name='total_with_tax']" ).length) {
				final_price = checkout_container.find( "input[name='total_with_tax']" ).val();
			} else {
				final_price = checkout_container.find( "input[name='total_net']" ).val();
			}

			if (final_price != ic_counted_final_price || effect_counted != effect_total) {
				ic_counted_final_price = final_price;
				final_price            = decode_number_format( final_price, dec_sep, th_sep );
				if (final_price) {
					final_price = parseFloat( final_price ) + parseFloat( effect_total_gross );
				} else {
					final_price = parseFloat( effect_total_gross );
				}
				checkout_container.find( ".button-container .new-price" ).remove();
				checkout_container.find( ".button-container .ic_cart_total" ).remove();
				var total;
				if (dec_sep != '') {
					total = ic_number_format( final_price, 2, dec_sep, th_sep );
				} else {
					total = ic_number_format( final_price, 0 );
				}
				checkout_container.find( ".button-container" ).prepend( "<input type='hidden' class='ic_cart_total' name='ic_cart_total' value='" + total + "'>" );
				checkout_container.find( ".button-container" ).prepend( "<div style='font-size: 18px; padding: 10px 0;' class='new-price'>" + ic_cart_ajax_object.total_label + ": " + total + "</div>" );
				ic_price_format( total ).done(
					function (formatted) {
						checkout_container.find( ".button-container .new-price" ).remove();
						checkout_container.find( ".button-container" ).prepend( "<div style='font-size: 18px; padding: 10px 0;' class='new-price'>" + ic_cart_ajax_object.total_label + ": " + formatted + "</div>" );
					}
				);
			}
		}
	}
}
