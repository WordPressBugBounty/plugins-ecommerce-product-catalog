/*!
impleCode Cart Frontend
(c) 2026 https://implecode.com
 */

var ic_cart_auto_refresh = 0;
var ic_cart_success_refresh_interval = 0;

document.addEventListener(
	'visibilitychange',
	function () {
		if (document.visibilityState === 'hidden') {
			ic_cart_auto_refresh = 1;
		} else if (ic_cart_auto_refresh === 1) {
			ic_cart_auto_refresh = 0;
			if (ic_cart_has_refreshable_container() && ic_cart_has_cached_markup()) {
				ic_cart_refresh_active_cart();
			}
		}
	}
);

jQuery( document ).ready(
	function () {
		var ic_edit_qty_timeout;
		var dec_sep = (typeof ic_cart_ajax_object !== 'undefined') ? ic_cart_ajax_object.dec_sep : '.';
		var th_sep  = (typeof ic_cart_ajax_object !== 'undefined') ? ic_cart_ajax_object.th_sep : '';

		if (ic_cart_has_refreshable_container() && ic_cart_has_cached_markup()) {
			ic_cart_refresh_active_cart();
		}

		jQuery( 'body' ).on(
			'click',
			'.login-before-checkout, .ic-overlay',
			function (event) {
				if (typeof digital_customer_toggle_popup_login === 'function') {
					event.preventDefault();
					digital_customer_toggle_popup_login();
				}
			}
		);

		jQuery( 'body' ).on(
			'change',
			'#shopping-cart-container .edit-product-quantity, #quote-cart-container .edit-product-quantity, #quote-cart-submit-container .edit-product-quantity',
			function () {
				var swicher = jQuery( this );

				clearTimeout( ic_edit_qty_timeout );
				ic_edit_qty_timeout = setTimeout(
					function () {
						if (swicher.closest( '#quote-cart-container, #quote-cart-submit-container' ).length) {
							ic_cart_handle_quote_quantity_change( swicher );
						} else {
							ic_cart_handle_shopping_quantity_change( swicher );
						}
					},
					400
				);
			}
		);

		jQuery( 'body' ).on(
			'click',
			'.to_cart_submit, .trigger-backorder-alerts, .only-available-submit, .trigger-backorder-second-message, .template-steps .ic-next-step',
			function () {
				clearTimeout( ic_edit_qty_timeout );
			}
		);

		jQuery( document ).on(
			'click',
			'.delete_product',
			function () {
				jQuery( this ).parent().find( '.edit-product-quantity' ).val( 0 ).trigger( 'change' );
			}
		);

		jQuery( document ).on(
			'keydown',
			'#shopping-cart-container .edit-product-quantity, #quote-cart-container .edit-product-quantity, #quote-cart-submit-container .edit-product-quantity',
			function (event) {
				if (event.keyCode == 13) {
					event.preventDefault();
					jQuery( this ).trigger( 'change' );

					return false;
				}
			}
		);

		jQuery( '#shopping_cart_form' ).on(
			'submit',
			function () {
				var form = form2string( jQuery( '#shopping_cart_form :input:visible' ) );
				setCookie( 'shopping_form_fields', form );
			}
		);

		var cookie_fields = getCookie( 'shopping_form_fields' );
		if (cookie_fields != null) {
			cookie2form( cookie_fields );
		}

		jQuery( 'div.product-price.price-add-to-cart' ).click(
			function () {
				if (jQuery( this ).children( 'span' ).length == 0) {
					jQuery( this ).closest( '.archive-listing' ).find( '.reg_add' ).submit();
				}
			}
		);

		fix_cart_widget_width();
		check_if_cart_empty( 'fast', true );
		update_all_cart_price_effects( dec_sep, th_sep );

		jQuery( '#shopping-cart-submit-container .ic-form form select, #shopping-cart-submit-container .ic-form form input:radio, #shopping-cart-submit-container .ic-form form input:checkbox[data-price_effect], #quote-cart-submit-container .ic-form form select, #quote-cart-submit-container .ic-form form input:radio, #quote-cart-submit-container .ic-form form input:checkbox[data-price_effect]' ).change(
			function () {
				update_all_cart_price_effects( dec_sep, th_sep );
			}
		);

		if (jQuery( '#shopping-cart-submit-container' ).length && ! jQuery( '.ic-form .ic_tax_limiter' ).length) {
			jQuery( '#shopping-cart-submit-container .ic-form form select' ).change(
				function () {
					if (jQuery( this ).data( 'tax_field' )) {
						var tax_effect = jQuery( this ).find( ':selected' ).data( 'tax_effect' );
						if (tax_effect !== undefined) {
							add_tax_to_order( tax_effect );
						} else {
							remove_tax_from_order();
						}
					}
				}
			);
			jQuery( '#shopping-cart-submit-container .ic-form form select' ).trigger( 'change' );
		}

		jQuery( document ).on(
			'click',
			'.restore-ic-cart',
			function (event) {
				event.preventDefault();
				var data = {
					'action': 'restore_shopping_cart',
					'front': 1
				};

				jQuery.post(
					ic_cart_get_ajax_url(),
					data,
					function (response) {
						response = JSON.parse( response );
						var cart_container = jQuery( '.restore-ic-cart' ).parent( '.product-shopping-cart' );
						cart_container.html( response[0] );
						if (jQuery( '#shopping-cart-container' ).length) {
							jQuery( '#shopping-cart-container' ).replaceWith( response[1] );
						}
						ic_update_cart();
						ic_cart_refresh_cart_button();
					}
				);
			}
		);

		if (jQuery.ic && typeof jQuery.ic.addAction === 'function') {
			jQuery.ic.addAction(
				'ic_cart_added',
				function () {
					ic_update_cart();
					ic_cart_refresh_cart_button();
				}
			);
			jQuery.ic.addAction(
				'ic_cart_qties_updated',
				function () {
					ic_update_cart();
				}
			);
		}

		if (jQuery( '.ic-thank-you-success' ).length) {
			ic_update_cart();
			ic_cart_start_success_page_refresh();
		}

		ic_cart_restore_cached_add_to_cart_markup();
	}
);

function ic_cart_get_ajax_url() {
	if (typeof product_object !== 'undefined' && product_object.ajaxurl) {
		return product_object.ajaxurl;
	}
	if (typeof ic_cart_ajax_object !== 'undefined' && ic_cart_ajax_object.ajax_url) {
		return ic_cart_ajax_object.ajax_url;
	}

	return '';
}

function ic_cart_has_refreshable_container() {
	return jQuery( '#shopping-cart-container, #shopping-cart-submit-container, #quote-cart-container, #quote-cart-submit-container' ).length > 0;
}

function ic_cart_is_quote_context() {
	return jQuery( '#quote-cart-container, #quote-cart-submit-container, .quote_cart_button_container, input[name="quote_cart_content"], form.add-to-quote-cart' ).length > 0;
}

function ic_cart_current_cart_name() {
	if (ic_cart_is_quote_context()) {
		return 'quote_cart_content';
	}

	return 'cart_content';
}

function ic_cart_handle_quote_quantity_change(swicher) {
	var current_quantity = Number( swicher.val() );
	var container        = swicher.closest( '#quote-cart-container, #quote-cart-submit-container' );
	var form;
	var raw_request_parameter = '0';

	if (current_quantity === 0) {
		var current_row = swicher.parent().parent();
		current_row.hide(
			'slow',
			function () {
				current_row.remove();
			}
		);
		check_if_cart_empty( 'slow' );
	}

	if (container.is( '#quote-cart-submit-container' )) {
		form                  = container.find( 'form' ).first();
		raw_request_parameter = '1';
	} else {
		form = container.find( '> form' );
	}

	if ( ! form.length) {
		return;
	}

	container.css( 'opacity', '0.5' );
	var data = form.serialize();
	data     = data + '&action=get_quote_cart_products&raw=' + raw_request_parameter + '&front=1';

	jQuery.post(
		ic_cart_get_ajax_url(),
		data,
		function (response) {
			if (container.is( '#quote-cart-submit-container' )) {
				if (typeof ic_cart_apply_updated_products_table === 'function') {
					ic_cart_apply_updated_products_table( response );
					ic_cart_after_updated_products_table( response );
				}
			} else {
				form.replaceWith( response );
			}
			ic_update_cart();
			container.css( 'opacity', '1' );
			if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
				jQuery.ic.doAction( 'ic_cart_qties_updated' );
			}
		}
	);
}

function ic_cart_handle_shopping_quantity_change(swicher) {
	var current_quantity = Number( swicher.val() );
	var row_count        = jQuery( '#shopping-cart-container .cart-products tr' ).length;

	if (current_quantity === 0) {
		var current_row = swicher.parent().parent();
		row_count       = row_count - 1;
		current_row.hide(
			'slow',
			function () {
				current_row.remove();
			}
		);
		check_if_cart_empty( 'slow' );
	}

	ic_update_cart_button_qty();
	ic_hide_products_table();

	var new_current_quantity = Number( swicher.val() );
	if (new_current_quantity === current_quantity) {
		var data = jQuery( '#shopping-cart-container form' ).serialize();
		data     = data + '&action=shopping_cart_products&raw=0&front=1';
		jQuery.post(
			ic_cart_get_ajax_url(),
			data,
			function (response) {
				if (typeof ic_cart_apply_updated_products_table === 'function') {
					ic_cart_apply_updated_products_table( response );
					ic_cart_after_updated_products_table( response );
				} else {
					jQuery( '.before-cart-products' ).remove();
					jQuery( 'table.cart-products' ).remove();
					jQuery( "#shopping-cart-container form input[name='cart_content']" ).remove();
					jQuery( '#shopping-cart-container form .form-buttons' ).last().before( response );
					jQuery( '#shopping-cart-container form .form-buttons .to_cart_submit' ).show();
				}
				ic_show_products_table();
				ic_update_cart();
				if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
					jQuery.ic.doAction( 'ic_cart_qties_updated' );
				}
			}
		);
	}
}

function ic_cart_success_page_container() {
	return jQuery( '.ic-thank-you-success' ).first();
}

function ic_cart_stop_success_page_refresh() {
	if (ic_cart_success_refresh_interval) {
		clearInterval( ic_cart_success_refresh_interval );
		ic_cart_success_refresh_interval = 0;
	}
}

function ic_cart_refresh_success_page() {
	var container = ic_cart_success_page_container();
	if (container.length === 0 || ! container.data( 'icSuccessRefresh' ) || ! ic_cart_get_ajax_url()) {
		ic_cart_stop_success_page_refresh();
		return;
	}
	if (container.hasClass( 'ic-success-page-refreshing' )) {
		return;
	}

	container.addClass( 'ic-success-page-refreshing' );
	jQuery.post(
		ic_cart_get_ajax_url(),
		{
			'action': 'ic_refresh_success_page',
			'current_url': window.location.href,
			'ic_nonce': product_object.refresh_success_nonce
		},
		function (response) {
			var current_container   = ic_cart_success_page_container();
			var refreshed_container = jQuery();

			if (response && response.success && response.data && response.data.html) {
				refreshed_container = jQuery( response.data.html );
				if (refreshed_container.length) {
					current_container.replaceWith( refreshed_container );
					current_container = refreshed_container.first();
				}
			}

			current_container.removeClass( 'ic-success-page-refreshing' );
			if ( ! response || ! response.success || ! response.data || response.data.keep_refreshing === false || ! current_container.data( 'icSuccessRefresh' )) {
				ic_cart_stop_success_page_refresh();
			}
		}
	).fail(
		function () {
			ic_cart_success_page_container().removeClass( 'ic-success-page-refreshing' );
		}
	);
}

function ic_cart_start_success_page_refresh() {
	var container        = ic_cart_success_page_container();
	var refresh_interval = parseInt( container.data( 'icSuccessRefreshInterval' ), 10 );

	if (container.length === 0 || ! container.data( 'icSuccessRefresh' )) {
		return;
	}
	if (Number.isNaN( refresh_interval ) || refresh_interval <= 0) {
		refresh_interval = 10000;
	}

	ic_cart_stop_success_page_refresh();
	ic_cart_success_refresh_interval = setInterval(
		function () {
			if (document.hidden) {
				return;
			}
			ic_cart_refresh_success_page();
		},
		refresh_interval
	);
}

function ic_cart_has_session_cookie() {
	return document.cookie.indexOf( 'wordpress_ic_session_' ) !== -1 || document.cookie.indexOf( 'PHPSESSID=' ) !== -1 || document.cookie.indexOf( '_wp_session=' ) !== -1;
}

function ic_cart_has_cached_markup() {
	return jQuery( '.ic-cached, .ic-pre-cached, .ic_cache' ).length > 0;
}

function ic_cart_refresh_active_cart() {
	var container = jQuery( '#quote-cart-container, #shopping-cart-container' ).first();
	var data      = {
		raw: 0
	};

	if (container.length === 0) {
		container = jQuery( '#shopping-cart-submit-container, #quote-cart-submit-container' ).first();
		data.raw  = 1;
	}
	if (container.length === 0 || ic_disabled_body()) {
		return;
	}

	if (container.is( '#quote-cart-container' ) || container.is( '#quote-cart-submit-container' )) {
		data.action = 'get_quote_cart_products';
	} else {
		data.action = 'shopping_cart_products';
		data.front  = 1;
	}

	ic_disable_body();
	jQuery.post(
		ic_cart_get_ajax_url(),
		data,
		function (response) {
			if (container.is( '#quote-cart-container' )) {
				container.find( '> form' ).replaceWith( response );
			} else if (container.is( '#shopping-cart-container' )) {
				if (typeof ic_cart_apply_updated_products_table === 'function') {
					ic_cart_apply_updated_products_table( response );
					ic_cart_after_updated_products_table( response );
				} else {
					jQuery( '.before-cart-products' ).remove();
					jQuery( 'table.cart-products' ).remove();
					jQuery( "#shopping-cart-container form input[name='cart_content']" ).remove();
					container.find( 'form .form-buttons' ).last().before( response );
					container.find( 'form .form-buttons .to_cart_submit' ).show();
				}
			} else if (typeof ic_cart_apply_updated_products_table === 'function') {
				ic_cart_apply_updated_products_table( response );
				ic_cart_after_updated_products_table( response );
			}
			ic_enable_body();
		}
	).fail(
		function () {
			ic_enable_body();
		}
	);
}

function ic_cart_refresh_cart_button() {
	jQuery( '.quote_cart_button_container' ).each(
		function () {
			var container = jQuery( this );
			jQuery.post(
				ic_cart_get_ajax_url(),
				{
					'action': 'get_quote_cart_button'
				},
				function (response) {
					var current_cart_value  = container.find( 'input[name="quote_cart_content"]' ).first().val();
					var response_cart_value = jQuery( '<div>' + response + '</div>' ).find( 'input[name="quote_cart_content"]' ).first().val();
					if (current_cart_value && ! response_cart_value) {
						return;
					}
					container.find( '#quote_cart_widget' ).remove();
					container.find( '#shopping_cart_widget' ).remove();
					container.append( response );
				}
			);
		}
	);

	var cart_button = jQuery( '.cart-widget-container.check-if-empty' );
	if (cart_button.length) {
		var label = cart_button.find( '.shopping-cart-widget' ).data( 'label' );
		jQuery.post(
			ic_cart_get_ajax_url(),
			{
				'action': 'ic_get_cart_button',
				'label': label
			},
			function (response) {
				if (response && ! (response.indexOf( 'empty-cart' ) >= 0)) {
					cart_button.find( '#shopping_cart_widget' ).replaceWith( response );
					if (typeof ic_cart_show === 'function') {
						ic_cart_show();
					}
				}
			}
		);
	}

	if (typeof ic_sitewide_icons_refresh === 'function') {
		ic_sitewide_icons_refresh();
	}
}

function ic_cart_restore_cached_add_to_cart_markup() {
	if ( ! ic_cart_is_quote_context() || ! ic_cart_has_session_cookie() || ! ic_cart_has_cached_markup() || ! jQuery( '.add_to_cart_form_container' ).length || jQuery( '.variations-container' ).length || jQuery( '.add_to_cart_added_popup' ).length) {
		return;
	}

	var saved_cart_content = localStorage.getItem( 'ic_cart_content' );
	if ( ! saved_cart_content) {
		return;
	}

	try {
		saved_cart_content = jQuery.parseJSON( saved_cart_content );
	} catch (error) {
		return;
	}

	if ( ! saved_cart_content || ! saved_cart_content.added_button_html) {
		return;
	}

	jQuery( '.add_to_cart_form_container' ).each(
		function () {
			var form_container = jQuery( this );
			var product_id     = form_container.find( '[name=current_product]' ).val();

			jQuery.each(
				saved_cart_content,
				function (key) {
					if (key.indexOf( product_id ) >= 0) {
						form_container.find( 'form' ).replaceWith( saved_cart_content.added_button_html );
						form_container.find( '.cart_info' ).hide();
					}
				}
			);
		}
	);
}

function ic_update_cart() {
	jQuery.post(
		ic_cart_get_ajax_url(),
		{
			'action': 'ic_get_cart_content',
			'cart': ic_cart_current_cart_name()
		},
		function (cart_content) {
			if (cart_content) {
				localStorage.setItem( 'ic_cart_content', cart_content );
			} else {
				localStorage.removeItem( 'ic_cart_content' );
			}
		}
	);
}

if (typeof ic_quote_cart_success_page_container !== 'function') {
	function ic_quote_cart_success_page_container() {
		return ic_cart_success_page_container();
	}
}

if (typeof ic_quote_cart_stop_success_page_refresh !== 'function') {
	function ic_quote_cart_stop_success_page_refresh() {
		ic_cart_stop_success_page_refresh();
	}
}

if (typeof ic_quote_cart_refresh_success_page !== 'function') {
	function ic_quote_cart_refresh_success_page() {
		ic_cart_refresh_success_page();
	}
}

if (typeof ic_quote_cart_start_success_page_refresh !== 'function') {
	function ic_quote_cart_start_success_page_refresh() {
		ic_cart_start_success_page_refresh();
	}
}

if (typeof ic_quote_cart_has_session_cookie !== 'function') {
	function ic_quote_cart_has_session_cookie() {
		return ic_cart_has_session_cookie();
	}
}

if (typeof ic_quote_cart_has_cached_markup !== 'function') {
	function ic_quote_cart_has_cached_markup() {
		return ic_cart_has_cached_markup();
	}
}

if (typeof ic_quote_cart_refresh !== 'function') {
	function ic_quote_cart_refresh() {
		ic_cart_refresh_active_cart();
	}
}

if (typeof number_format !== 'function') {
	function number_format(number, decimals, dec_point, thousands_sep) {
		number         = (number + '').replace( /[^0-9+\-Ee.]/g, '' );
		var n          = ! isFinite( +number ) ? 0 : +number,
			prec       = ! isFinite( +decimals ) ? 0 : Math.abs( decimals ),
			sep        = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
			dec        = (typeof dec_point === 'undefined') ? '.' : dec_point,
			s          = '',
			toFixedFix = function (number_input, precision_input) {
				var k = Math.pow( 10, precision_input );
				return '' + Math.round( number_input * k ) / k;
			};
		s = (prec ? toFixedFix( n, prec ) : '' + Math.round( n )).split( '.' );
		if (s[0].length > 3) {
			s[0] = s[0].replace( /\B(?=(?:\d{3})+(?!\d))/g, sep );
		}
		if ((s[1] || '').length < prec) {
			s[1]  = s[1] || '';
			s[1] += new Array( prec - s[1].length + 1 ).join( '0' );
		}
		return s.join( dec );
	}
}

function fix_cart_widget_width() {
	var cart_product_search = jQuery( '#shopping_cart_widget_container #product_search_form' );
	if (cart_product_search.length) {
		var cart_width      = jQuery( '.product-shopping-cart' ).outerWidth();
		var container_width = cart_product_search.parent( 'div' ).outerWidth();
		var temp_width      = container_width - cart_width;
		jQuery( '#shopping_cart_widget_container .product-search-box' ).outerWidth( temp_width );
		jQuery( '#shopping_cart_widget_container .ic-search-container' ).show();
		return;
	}

	var cart_button_width = jQuery( '.product-shopping-cart input' ).outerWidth();
	if (cart_button_width) {
		var temp_button_width = cart_button_width - 29;
		jQuery( '#shopping_cart_widget_container .product_widget_search' ).outerWidth( cart_button_width );
		jQuery( '#shopping_cart_widget_container .product-search-box' ).outerWidth( temp_button_width );
	}
}

if (typeof ic_number_format !== 'function') {
	function ic_number_format(number, decimals, dec_point, thousands_sep) {
		number         = (number + '').replace( /[^0-9+\-Ee.]/g, '' );
		var n          = ! isFinite( +number ) ? 0 : +number,
			prec       = ! isFinite( +decimals ) ? 0 : Math.abs( decimals ),
			sep        = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
			dec        = (typeof dec_point === 'undefined') ? '.' : dec_point,
			s          = '',
			toFixedFix = function (number_input, precision_input) {
				var k = Math.pow( 10, precision_input );
				return '' + Math.round( number_input * k ) / k;
			};
		s = (prec ? toFixedFix( n, prec ) : '' + Math.round( n )).split( '.' );
		if (s[0].length > 3) {
			s[0] = s[0].replace( /\B(?=(?:\d{3})+(?!\d))/g, sep );
		}
		if ((s[1] || '').length < prec) {
			s[1]  = s[1] || '';
			s[1] += new Array( prec - s[1].length + 1 ).join( '0' );
		}
		return s.join( dec );
	}
}

function getCookie(name) {
	var re    = new RegExp( name + '=([^;]+)' );
	var value = re.exec( document.cookie );
	return (value != null) ? unescape( value[1] ) : null;
}

function form2string(form) {
	return JSON.stringify( form.serializeArray() );
}

function cookie2form(cookie) {
	var fields = JSON.parse( cookie );
	for (var i = 0; i < fields.length; i++) {
		var name  = fields[i].name;
		var value = fields[i].value;
		if (name != 'cart_content' && name != 'quote_cart_content' && name != 'captcha_code') {
			if (name == 'cart_person') {
				jQuery( ':radio[value="' + value + '"]' ).attr( 'checked', true );
			} else {
				jQuery( '[name="' + name + '"]' ).val( value );
			}
		}
	}
}

var today  = new Date();
var expiry = new Date( today.getTime() + 30 * 24 * 3600 * 1000 );

function setCookie(name, value) {
	document.cookie = name + '=' + escape( value ) + '; path=/; expires=' + expiry.toGMTString();
}

function check_if_cart_empty(how, ajax) {
	if (jQuery( '.cart-products' ).length === 0) {
		return false;
	}
	if (ajax !== undefined && jQuery( '.ic_cache' ).length && jQuery( '#shopping-cart-container' ).length) {
		ic_hide_products_table();
		jQuery.when( update_checkout_products_table_html() ).then(
			function () {
				check_if_cart_empty( how );
			}
		);
		return false;
	}

	var allow = 0;
	jQuery( 'input.edit-product-quantity' ).each(
		function () {
			if (jQuery( this ).val() != 0) {
				allow = 1;
			}
		}
	);

	if (allow != 1) {
		jQuery( '.to_cart_submit' ).hide( how );
		jQuery( '.save_new_quantities' ).hide( how );
		jQuery( '.no-products' ).show( how );
		if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
			jQuery.ic.doAction( 'ic_cart_empty' );
		}
	} else {
		jQuery( '.no-products' ).hide( how );
		jQuery( '.to_cart_submit' ).show( how );
	}
}

if (typeof ic_roundto !== 'function') {
	function ic_roundto(number, increments) {
		increments = increments * 100;
		if (increments != 0) {
			increments = 1 / increments;
			number     = Math.round( number * increments * 100 ) / 100 / increments;
		}
		return number;
	}
}

function limitText(limitField, limitNum) {
	if (limitField.value.length > limitNum) {
		limitField.value = 999;
		jQuery( limitField ).trigger( 'change' );
	}
}

if (typeof decode_number_format !== 'function') {
	function decode_number_format(number, dec_sep, th_sep) {
		if (number === undefined || number === null) {
			return 0;
		}
		var out = number.replace( th_sep, '' );
		if (dec_sep != '') {
			out = out.replace( dec_sep, '.' );
		}
		return parseFloat( out );
	}
}

if (typeof price_format !== 'function') {
	function ic_price_format(price) {
		return jQuery.post(
			ic_cart_get_ajax_url(),
			{
				'action': 'ic_price_format',
				'price': price
			}
		);
	}
}

function add_tax_to_order(tax_rate) {
	if (tax_rate === undefined) {
		tax_rate = 1;
	}
	if ((! jQuery( '.ic-form .vat_rate' ).length && ! jQuery( '.ic-form .order-checkout-tax' ).length) || (jQuery( '.ic-form' ).data( 'tax_type' ) !== undefined && jQuery( '.ic-form' ).data( 'tax_type' ) !== tax_rate)) {
		update_checkout_products_table(
			{
				'action': 'shopping_cart_products',
				'raw': 1,
				'tax_rate': tax_rate
			}
		);
		jQuery( '.ic-form' ).attr( 'data-tax_type', tax_rate );
		jQuery( '.ic-form' ).data( 'tax_type', tax_rate );
	}
}

function remove_tax_from_order() {
	if (jQuery( '.ic-form .vat_rate' ).length || jQuery( '.ic-form .order-checkout-tax' ).length) {
		update_checkout_products_table(
			{
				'action': 'shopping_cart_products',
				'raw': 1,
				'front': 1
			}
		);
		jQuery( '.ic-form' ).attr( 'data-tax_type', '' );
		jQuery( '.ic-form' ).data( 'tax_type', '' );
	}
}

function update_checkout_products_table(data) {
	if (data.raw !== 1) {
		jQuery( '.to_cart_submit' ).prop( 'disabled', true );
	}

	if (jQuery( '#shopping-cart-submit-container' ).length && typeof ic_cart_update_products_table === 'function') {
		return ic_cart_update_products_table( data );
	}

	return jQuery.post(
		ic_cart_get_ajax_url(),
		data,
		function (response) {
			jQuery.when( apply_updated_products_table( response, data ) ).then( after_updated_products_table( response ) );
		}
	);
}

function apply_updated_products_table(response, data) {
	var responseMarkup = jQuery( '<div></div>' ).html( response );
	var updatedTable = responseMarkup.find( '.ic-cart-products-table' ).first();
	var updatedActions = responseMarkup.find( '#shopping-cart-actions' ).first();
	var currentTable = jQuery( '#shopping-cart-container .ic-cart-products-table' ).first();
	var currentActions = jQuery( '#shopping-cart-container form #shopping-cart-actions' ).first();

	if (data.raw === 1) {
		jQuery( '#shopping-cart-submit-container .ic-form > form' ).prepend( response );
		return;
	}
	if (currentTable.length && updatedTable.length) {
		currentTable.replaceWith( updatedTable );
	}
	if (currentActions.length && updatedActions.length) {
		currentActions.replaceWith( updatedActions );
	}
	jQuery( '.to_cart_submit' ).prop( 'disabled', false );
	jQuery( '.to_cart_submit' ).show();
}

function after_updated_products_table(response) {
	if (jQuery.ic && typeof jQuery.ic.doAction === 'function') {
		jQuery.when( jQuery.ic.doAction( 'ic_checkout_table_updated', response ) ).then(
			function () {
				if (typeof update_all_cart_price_effects === 'function' && typeof ic_cart_ajax_object !== 'undefined') {
					update_all_cart_price_effects( ic_cart_ajax_object.dec_sep, ic_cart_ajax_object.th_sep );
				}
			}
		);
	}
}

function update_checkout_products_table_html(inputData) {
	var data;
	if ( ! inputData) {
		data = {
			'action': 'shopping_cart_products',
			'front': 1,
			'raw': 0
		};
		if (jQuery( '.cart-products.raw' ).length) {
			data.raw = 1;
		}
	} else {
		data = inputData + '&action=shopping_cart_products&raw=0&front=1';
	}
	return jQuery.when( update_checkout_products_table( data ) ).then(
		function () {
			ic_show_products_table();
		}
	);
}

function ic_hide_products_table() {
	ic_disable_container( jQuery( '#shopping-cart-container' ) );
}

function ic_show_products_table() {
	ic_enable_container( jQuery( '#shopping-cart-container' ) );
}

function ic_update_cart_button_qty() {
	var product_count = 0;
	jQuery( 'input.edit-product-quantity' ).each(
		function () {
			var value = jQuery( this ).val();
			if (value > 0) {
				product_count++;
			}
		}
	);
	var cart_button = jQuery( '.product-shopping-cart .button .cart_button_text' );
	if (cart_button.length > 0) {
		var current_text  = cart_button.first().text();
		var selected_word = current_text.split( ' ' );
		selected_word.splice( 0, 1 );
		selected_word = selected_word.join( ' ' );
		var new_text = product_count + ' ' + selected_word;
		if (current_text !== new_text) {
			cart_button.each(
				function () {
					jQuery( this ).text( new_text );
				}
			);
		}
	}
}
