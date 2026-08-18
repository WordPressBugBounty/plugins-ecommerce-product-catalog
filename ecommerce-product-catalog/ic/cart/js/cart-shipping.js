jQuery( document ).ready(
	function () {
		ic_cart_capture_shipping_step_selection();
		ic_cart_toggle_all_delivery_location_steps();

		jQuery( 'body' ).on(
			'change',
			'input[type="radio"][data-ic_shipping_pre_name]',
			function () {
				var input = jQuery( this );
				var form = input.closest( 'form' );
				var preName = input.data( 'ic_shipping_pre_name' );
				var shippingStep = input.closest( '.ic-checkout-step-shipping' );
				var shippingFields;

				if ( input.closest( '.template-steps' ).length ) {
					ic_cart_toggle_delivery_location_step( shippingStep, form );
					return;
				}

				if ( ! form.length || ! preName ) {
					return;
				}

				shippingFields = ic_cart_selected_shipping_fields( form ).serialize();

				ic_cart_update_products_table(
					{
						'action': 'ic_quote_cart_update_products_table',
						'pre_name': preName,
						'shipping_data': shippingFields,
						'raw': ic_cart_shipping_refresh_raw_mode( form )
					}
				);
			}
		);

		jQuery( 'body' ).on(
			'click',
			'.template-steps .ic-checkout-step-shipping .ic-next-step',
			function () {
				var button = jQuery( this );
				var currentStep = button.closest( '.ic-checkout-step-shipping' );
				var form = button.closest( 'form' );
				var shippingInput = currentStep.find( 'input[type="radio"][data-ic_shipping_pre_name]' ).first();
				var preName = shippingInput.data( 'ic_shipping_pre_name' );
				var shippingFields;
				var currentSelection;
				var refreshRequest;

				if ( ! form.length || ! preName || ! currentStep.length ) {
					return;
				}

				shippingFields = ic_cart_selected_shipping_fields( form ).serialize();
				currentSelection = ic_cart_selected_shipping_step_value( currentStep, form );
				if ( currentSelection === ic_cart_saved_shipping_step_value( currentStep ) ) {
					return;
				}

				window.setTimeout(
					function () {
						if ( ! ic_cart_shipping_step_fields_are_valid( currentStep ) ) {
							return;
						}

						shippingFields = ic_cart_selected_shipping_fields( form ).serialize();
						refreshRequest = ic_cart_update_products_table(
							{
								'action': 'ic_quote_cart_update_products_table',
								'pre_name': preName,
								'shipping_data': shippingFields,
								'raw': ic_cart_shipping_refresh_raw_mode( form )
							}
						);
						if ( refreshRequest && typeof refreshRequest.done === 'function' ) {
							refreshRequest.done(
								function () {
									ic_cart_save_shipping_step_value( currentStep, currentSelection );
								}
							);
						}
					},
					0
				);
			}
		);

		if ( jQuery.ic && typeof jQuery.ic.addAction === 'function' ) {
			jQuery.ic.addAction(
				'ic_checkout_table_updated',
				function () {
					ic_cart_capture_shipping_step_selection();
					ic_cart_toggle_all_delivery_location_steps();
				}
			);
		}
	}
);

function ic_cart_selected_shipping_fields(form) {
	return form.find( 'input[name="shipping"]:checked, input[name^="shipping_"]:checked, input[type="hidden"][name="shipping"][data-ic_shipping_pre_name], input[type="hidden"][name^="shipping_"][data-ic_shipping_pre_name]' );
}

function ic_cart_selected_shipping_step_validation_fields(shippingStep) {
	var addressOption = shippingStep.find( '.ic-shipping-address-option.active' ).first();

	if ( ! addressOption.length ) {
		return jQuery();
	}

	return addressOption.find( '.shipping-address-form-fields-container input:not([type=hidden]):enabled,.shipping-address-form-fields-container select:enabled,.shipping-address-form-fields-container textarea:enabled' );
}

function ic_cart_shipping_step_fields_are_valid(shippingStep) {
	var fields = ic_cart_selected_shipping_step_validation_fields( shippingStep );
	var valid = true;

	fields.each(
		function () {
			if ( this.checkValidity() ) {
				return true;
			}

			valid = false;
			return false;
		}
	);

	return valid;
}

function ic_cart_selected_shipping_step_inputs(shippingStep, form) {
	if ( shippingStep.length ) {
		return shippingStep.find( 'input[name="shipping"]:checked, input[name^="shipping_"]:checked, input[type="hidden"][name="shipping"][data-ic_shipping_pre_name], input[type="hidden"][name^="shipping_"][data-ic_shipping_pre_name]' );
	}

	return ic_cart_selected_shipping_fields( form );
}

function ic_cart_shipping_step_requires_delivery_location(shippingStep, form) {
	var selectedShipping = ic_cart_selected_shipping_step_inputs( shippingStep, form );
	var requiresDeliveryLocation = false;

	selectedShipping.each(
		function () {
			if ( jQuery( this ).attr( 'data-ic_shipping_collect_address' ) !== '1' ) {
				requiresDeliveryLocation = true;
				return false;
			}

			return true;
		}
	);

	return requiresDeliveryLocation;
}

function ic_cart_toggle_delivery_location_step(shippingStep, form) {
	var deliveryLocationStep;
	var shouldShow;
	var wasDisabled;

	if ( ! shippingStep.length || ! form.length ) {
		return;
	}

	deliveryLocationStep = form.find( '.ic-checkout-step-delivery-location' ).first();
	if ( ! deliveryLocationStep.length ) {
		return;
	}

	shouldShow = ic_cart_shipping_step_requires_delivery_location( shippingStep, form );
	wasDisabled = deliveryLocationStep.hasClass( 'ic-checkout-step-disabled' );
	deliveryLocationStep.toggleClass( 'ic-checkout-step-disabled', ! shouldShow );
	if ( ! shouldShow ) {
		deliveryLocationStep.css( 'min-height', '' );
	}
	if ( wasDisabled !== ! shouldShow && jQuery.ic && typeof jQuery.ic.doAction === 'function' ) {
		jQuery.ic.doAction( 'ic_checkout_steps_visibility_changed', deliveryLocationStep, shouldShow );
	}
}

function ic_cart_capture_shipping_step_selection() {
	jQuery( '.template-steps .ic-checkout-step-shipping' ).each(
		function () {
			var shippingStep = jQuery( this );
			var form = shippingStep.closest( 'form' );

			if ( ! form.length ) {
				return;
			}

			ic_cart_save_shipping_step_value( shippingStep, ic_cart_selected_shipping_step_value( shippingStep, form ) );
			ic_cart_toggle_delivery_location_step( shippingStep, form );
		}
	);
}

function ic_cart_toggle_all_delivery_location_steps() {
	jQuery( '.template-steps .ic-checkout-step-shipping' ).each(
		function () {
			var shippingStep = jQuery( this );
			var form = shippingStep.closest( 'form' );

			if ( ! form.length ) {
				return;
			}

			ic_cart_toggle_delivery_location_step( shippingStep, form );
		}
	);
}

function ic_cart_selected_shipping_step_value(shippingStep, form) {
	if ( ! shippingStep.length || ! form.length ) {
		return '';
	}

	return shippingStep.find( 'input[name="shipping"]:checked, input[name^="shipping_"]:checked, input[type="hidden"][name="shipping"][data-ic_shipping_pre_name], input[type="hidden"][name^="shipping_"][data-ic_shipping_pre_name]' ).serialize() || ic_cart_selected_shipping_fields( form ).serialize();
}

function ic_cart_saved_shipping_step_value(shippingStep) {
	return shippingStep.data( 'icCartShippingSelection' ) || '';
}

function ic_cart_save_shipping_step_value(shippingStep, shippingValue) {
	shippingStep.data( 'icCartShippingSelection', shippingValue || '' );
}

function ic_cart_shipping_refresh_raw_mode(form) {
	if ( ! form.length ) {
		return '0';
	}
	if (form.closest( '#quote-cart-submit-container, #shopping-cart-submit-container' ).length) {
		return '1';
	}

	return '0';
}
