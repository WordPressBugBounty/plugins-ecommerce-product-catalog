/*!
impleCode FormBuilder v1.1.0
Adds appropriate scripts to front-end form
(c) 2021 Norbert Dreszer - https://implecode.com
 */

jQuery( document ).ready(
	function () {
		/* globals product_object,implecode,ajaxurl,ic_catalog */
		var ic_ajaxurl = '';
		var nonce      = '';
		if (typeof product_object !== 'undefined') {
			ic_ajaxurl = product_object.ajaxurl;
			nonce      = product_object.nonce;
		} else if (typeof ajaxurl !== 'undefined') {
			ic_ajaxurl = ajaxurl;
		}
		if (nonce === '' && typeof ic_catalog !== 'undefined') {
			nonce = ic_catalog.nonce;
		}
		var state_container = jQuery( '.ic-form .dropdown_state, .ic-order-checkout-data .dropdown_state, .shipping-address-form-fields .dropdown_state' );

		function icFormbuilderFormScope(country_container) {
			return country_container.closest( '.shipping-address-form-fields, .ic-form, .ic-order-checkout-data, .ic-checkout-form-data, form' );
		}

		function icFormbuilderStateContainer(country_selector, country_container) {
			var country_name         = country_selector.attr( 'name' ) || '';
			var form_scope           = icFormbuilderFormScope( country_container );
			var this_state_container = jQuery();

			if (country_name && form_scope.length) {
				this_state_container = form_scope.find( '.dropdown_state select' ).filter(
					function () {
						return jQuery( this ).attr( 'name' ) === country_name.replace( /country$/, 'state' );
					}
				).first().closest( '.dropdown_state' );
			}

			if (!this_state_container.length) {
				this_state_container = country_container.nextAll( '.dropdown_state' ).first();
			}

			if (!this_state_container.length) {
				this_state_container = form_scope.find( '.dropdown_state' ).first();
			}

			return this_state_container;
		}

		function icFormbuilderRealOptions(options) {
			var real_options = [];

			jQuery( options ).each(
				function (key, value) {
					if (value.value !== '') {
						real_options.push( value );
					}
				}
			);

			return real_options;
		}

		function icFormbuilderShowStateContainer(state_container) {
			state_container.show().css( 'visibility', 'visible' ).attr( 'aria-hidden', 'false' );
		}

		function icFormbuilderHideStateContainer(state_container) {
			state_container.show().css( 'visibility', 'hidden' ).attr( 'aria-hidden', 'true' );
		}

		if (state_container.length) {
			jQuery( '.ic-form .dropdown_country select, .ic-order-checkout-data .dropdown_country select, .shipping-address-form-fields .dropdown_country select' ).on(
				'change',
				function () {
					var country_selector     = jQuery( this );
					var country_container    = country_selector.closest( '.dropdown_country' );
					var this_state_container = icFormbuilderStateContainer( country_selector, country_container );
					var this_state_select    = this_state_container.find( 'select' );
					if (!this_state_select.length) {
						return;
					}
					var country_code = country_selector.val();
					if (country_code) {
						var data = {
							'action': 'ic_state_dropdown',
							'country_code': country_code,
							'state_code': this_state_select.val(),
							'nonce': nonce
						};
						implecode.disable_container( this_state_container );
						jQuery.post(
							ic_ajaxurl,
							data,
							function (response) {
								var state_updated = false;
								if (response) {
									var options = [];
									try {
										options = JSON.parse( response );
									} catch (e) {
										const json_regex      = /\[.*?\]/g;
										const parsed_response = response.match( json_regex );
										options               = JSON.parse( parsed_response );
									}
									if (!Array.isArray( options )) {
										options = [];
									}
									this_state_select.find( 'option' ).remove();
									this_state_select.append( '<option value=""></option>' );
									var real_options = icFormbuilderRealOptions( options );
									jQuery( options ).each(
										function (key, value) {
											var selected = '';
											if (value.checked || (real_options.length === 1 && value.value === real_options[0].value)) {
												selected = ' selected';
											}
											this_state_select.append( '<option' + selected + ' value="' + value.value + '">' + value.label + '</option>' );
										}
									);
									if (real_options.length > 0 && country_container.is( ':visible' )) {
										icFormbuilderShowStateContainer( this_state_container );
									} else {
										icFormbuilderHideStateContainer( this_state_container );
									}
									if (this_state_container.find( '.chosen-container' ).length) {
										this_state_select.trigger( 'chosen:updated' );
									} else if (typeof this_state_select.chosen === 'function') {
										var chosen_width = '224px';
										if (this_state_container.hasClass( 'size-medium' )) {
											chosen_width = '400px';
										}
										this_state_select.chosen( {width: chosen_width} );
									}
									if (this_state_select.hasClass( 'required' )) {
										this_state_select.attr( 'required', real_options.length > 0 );
									}
									state_updated = true;
								} else {
									icFormbuilderHideStateContainer( this_state_container );
									if (this_state_select.hasClass( 'required' )) {
										this_state_select.attr( 'required', false );
									}
								}
								implecode.enable_container( this_state_container );
								if (state_updated) {
									this_state_select.trigger( 'change' );
								}
							}
						);
					} else {
						icFormbuilderHideStateContainer( this_state_container );
						if (this_state_select.hasClass( 'required' )) {
							this_state_select.attr( 'required', false );
						}
					}
				}
			);
			jQuery( '.ic-form .dropdown_country select, .ic-order-checkout-data .dropdown_country select, .shipping-address-form-fields .dropdown_country select' ).trigger( 'change' );
		}
	}
);
