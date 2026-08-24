<?php
/**
 * Shared shipping-address checkout runtime.
 *
 * @package AL_Product_Catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shared runtime for shipping-address collection per shipping option.
 */
class IC_Shipping_Address_Form {
	/**
	 * Validation errors collected for the current form submission.
	 *
	 * @var array
	 */
	protected static $validation_errors = array();

	/**
	 * Registers the runtime hooks.
	 */
	public function __construct() {
		add_action( 'register_catalog_styles', array( $this, 'register_scripts' ) );
		add_action( 'enqueue_catalog_scripts', array( $this, 'enqueue_scripts' ) );

		add_filter( 'ic_formbuilder_filled_fields', array( $this, 'merge_selected_fields' ) );
		add_filter( 'ic_formbuilder_error', array( $this, 'validate_selected_forms' ) );
		add_filter( 'ic_formbuilder_error_output', array( $this, 'add_validation_messages' ) );
		add_filter( 'ic_save_order_payment_details', array( $this, 'save_shipping_address_summary' ) );
		add_filter( 'payment_order_details', array( $this, 'add_shipping_address_to_payment_details' ), 30 );
		add_filter( 'ic_payment_details', array( $this, 'add_shipping_address_to_payment_details' ), 100 );
		add_filter( 'ic_notification_shipping_address', array( $this, 'notification_shipping_address' ), 10, 2 );
		add_action( 'digital_order_delivery_details', array( $this, 'admin_shipping_address_fields' ), 12, 2 );
		add_action( 'update_digital_order', array( $this, 'save_admin_shipping_address_fields' ), 20, 2 );
	}

	/**
	 * Registers the shipping-address toggle script.
	 *
	 * @return void
	 */
	public function register_scripts() {
		$script_path = __DIR__ . '/js/shipping-address-form.js';
		wp_register_script(
			'ic-shipping-address-form',
			plugins_url( 'js/shipping-address-form.js', __FILE__ ),
			array(
				'jquery',
			),
			ic_filemtime( $script_path ),
			true
		);
	}

	/**
	 * Enqueues the shipping-address toggle script with catalog frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'ic-shipping-address-form' );
	}

	/**
	 * Ensures the frontend toggle script is available whenever a shipping form is rendered.
	 *
	 * @return void
	 */
	protected static function ensure_script_enqueued() {
		if ( ! wp_script_is( 'ic-shipping-address-form', 'registered' ) ) {
			$script_path = __DIR__ . '/js/shipping-address-form.js';
			wp_register_script(
				'ic-shipping-address-form',
				plugins_url( 'js/shipping-address-form.js', __FILE__ ),
				array(
					'jquery',
				),
				ic_filemtime( $script_path ),
				true
			);
		}

		wp_enqueue_script( 'ic-shipping-address-form' );
	}

	/**
	 * Returns inline fallback JS that initializes the current form block locally.
	 *
	 * @return string
	 */
	protected static function inline_init_script() {
		return "<script>(function($){function markRequiredFields(fields){fields.each(function(){var field=$(this);if(field.is('[required]')||field.prop('required')){field.data('ic-shipping-address-required',true);}});}function updateRequiredFields(fields,required){fields.each(function(){var field=$(this);if(field.data('ic-shipping-address-required')){if(required){field.attr('required','required');field.prop('required',true);}else{field.removeAttr('required');field.prop('required',false);}}});}function updateSameAsBilling(option){var toggle=option.find('.ic-shipping-address-same-as-billing-toggle').first(),container=option.find('.shipping-address-form-fields-container').first();if(!toggle.length||!container.length){return;}var formFields=container.find('input, select, textarea'),fields=formFields.add(container.find('button')),same=toggle.is(':checked');markRequiredFields(formFields);container.toggle(!same);fields.prop('disabled',same);updateRequiredFields(formFields,!same);fields.filter('select').trigger('chosen:updated');}function updateGroups(form){if(!form.length){return;}form.find('.ic-shipping-address-group').each(function(){var group=$(this),fieldName=group.data('ic-shipping-field'),selectedField,selectedValue='';if(!fieldName){return;}selectedField=form.find('input[type=\"radio\"][name=\"'+fieldName+'\"]:checked').first();if(!selectedField.length){selectedField=form.find('input[type=\"hidden\"][name=\"'+fieldName+'\"]').first();}selectedValue=selectedField.val()||'';group.find('.ic-shipping-address-option').each(function(){var option=$(this),isActive=option.data('ic-shipping-option-value')===selectedValue,fields=option.find('input, select, textarea, button');option.toggle(isActive);option.toggleClass('active',isActive);fields.prop('disabled',!isActive);if(isActive){updateSameAsBilling(option);}else{option.find('.shipping-address-form-fields-container').show();}fields.filter('select').trigger('chosen:updated');});});}$(function(){var script=$(document.currentScript),previous=script.prev(),group=previous.is('.ic-shipping-address-group')?previous:previous.find('.ic-shipping-address-group').first(),form=group.closest('form');updateGroups(form);if(form.length){form.off('change.icShippingAddressInline','input[type=\"radio\"][name=\"shipping\"], input[type=\"radio\"][name^=\"shipping_\"]').on('change.icShippingAddressInline','input[type=\"radio\"][name=\"shipping\"], input[type=\"radio\"][name^=\"shipping_\"]',function(){updateGroups(form);});form.off('change.icShippingSameAsBillingInline','.ic-shipping-address-same-as-billing-toggle').on('change.icShippingSameAsBillingInline','.ic-shipping-address-same-as-billing-toggle',function(){updateSameAsBilling($(this).closest('.ic-shipping-address-option'));});}});})(jQuery);</script>";
	}

	/**
	 * Returns true when the toggle script should be enqueued.
	 *
	 * @return bool
	 */
	protected function should_enqueue() {
		if ( function_exists( 'is_ic_shopping_page' ) && is_ic_shopping_page() ) {
			return true;
		}
		if ( function_exists( 'is_ic_quote_cart_page' ) && is_ic_quote_cart_page() ) {
			return true;
		}
		if ( function_exists( 'is_ic_shopping_order' ) && is_ic_shopping_order() ) {
			return true;
		}
		if ( function_exists( 'is_ic_quote_order' ) && is_ic_quote_order() ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns checkout HTML for one shipping option group.
	 *
	 * @param array  $shipping_option Shipping option group.
	 * @param int    $group_index Shipping group index.
	 * @param string $field_name Shipping radio field name.
	 * @param string $selected_value Selected shipping option slug.
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return string
	 */
	public static function group_html( $shipping_option, $group_index, $field_name, $selected_value, $pre_name ) {
		if ( ! function_exists( 'formbuilder_raw_fields' ) || empty( $shipping_option['options'] ) || ! is_array( $shipping_option['options'] ) ) {
			return '';
		}

		$group_forms = '';
		foreach ( $shipping_option['options'] as $option ) {
			if ( ! self::option_has_form( $option, $pre_name ) ) {
				continue;
			}

			$option_slug          = sanitize_title( $option['name'] );
			$field_slot           = self::option_slot( $option );
			$field_json           = ic_get_shipping_address_form_fields( $field_slot, $pre_name );
			$is_active            = $option_slug === $selected_value;
			$field_prefix         = self::field_prefix( $pre_name, $group_index, $option_slug );
			$field_values         = self::field_values( $field_prefix );
			$slot_field_id        = self::shipping_slot_field_id( $field_prefix );
			$same_as_billing_id   = self::same_as_billing_field_id( $field_prefix );
			$show_same_as_billing = self::same_as_billing_available( $field_slot, $pre_name );
			$is_same_as_billing   = $show_same_as_billing && self::field_value_is_checked( isset( $field_values[ $same_as_billing_id ] ) ? $field_values[ $same_as_billing_id ] : null );
			$fields_html          = formbuilder_raw_fields( $field_json, 2, $field_prefix, $field_values );
			if ( ! $is_active ) {
				$fields_html = str_replace(
					array(
						'<input ',
						'<select ',
						'<textarea ',
						'<button ',
					),
					array(
						'<input disabled ',
						'<select disabled ',
						'<textarea disabled ',
						'<button disabled ',
					),
					$fields_html
				);
			}

			$group_forms .= '<div class="ic-shipping-address-option' . ( $is_active ? ' active' : '' ) . '" data-ic-shipping-option-value="' . esc_attr( $option_slug ) . '"' . ( $is_active ? '' : ' style="display:none;"' ) . '>';
			$group_forms .= '<div class="shipping-address-form-row">';
			$group_forms .= '<div class="shipping-address-form-title"><strong>' . esc_html__( 'Shipping Address', 'ecommerce-product-catalog' ) . '</strong></div>';
			$group_forms .= '<input class="ic-shipping-address-slot" type="hidden" name="' . esc_attr( $slot_field_id ) . '" value="' . intval( $field_slot ) . '"' . ( $is_active ? '' : ' disabled' ) . ' />';
			if ( $show_same_as_billing ) {
				$group_forms .= '<div class="ic-shipping-address-same-as-billing"><label><input class="ic-shipping-address-same-as-billing-toggle" type="checkbox" name="' . esc_attr( $same_as_billing_id ) . '" value="1" ' . checked( $is_same_as_billing, true, false ) . ( $is_active ? '' : ' disabled' ) . ' /> <span class="ic-shipping-address-same-as-billing-label">' . esc_html__( 'Shipping address is same as billing address', 'ecommerce-product-catalog' ) . '</span></label></div>';
			}
			$group_forms .= '<div class="shipping-address-form-fields-container"' . ( $is_same_as_billing ? ' style="display:none;"' : '' ) . '>';
			$group_forms .= '<div class="shipping-address-form-fields table" data-pre_name="' . esc_attr( $field_prefix ) . '">';
			$group_forms .= $fields_html;
			$group_forms .= '</div>';
			$group_forms .= '</div>';
			$group_forms .= '</div>';
			$group_forms .= '</div>';
		}

		if ( '' === $group_forms ) {
			return '';
		}

		self::ensure_script_enqueued();

		return '<div class="shipping-address-group-row"><div class="ic-shipping-address-group-field"><div class="ic-shipping-address-group" data-ic-shipping-field="' . esc_attr( $field_name ) . '">' . $group_forms . '</div></div></div>' . self::inline_init_script();
	}

	/**
	 * Returns true when one shipping option has a visible address form.
	 *
	 * @param array  $option Shipping option data.
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return bool
	 */
	public static function option_has_form( $option, $pre_name = '' ) {
		$field_slot      = self::option_slot( $option );
		$collect_address = ! empty( $field_slot ) ? ic_shipping_collect_address( $field_slot ) : false;
		if ( empty( $field_slot ) || ! $collect_address ) {
			return false;
		}

		$fields   = ic_get_shipping_address_form_fields( $field_slot, $pre_name );
		$has_form = ! empty( $fields );

		return $has_form;
	}

	/**
	 * Returns the option slot number.
	 *
	 * @param array $option Shipping option data.
	 *
	 * @return int
	 */
	protected static function option_slot( $option ) {
		return ! empty( $option['slot'] ) ? intval( $option['slot'] ) : 0;
	}

	/**
	 * Returns the submitted form prefix.
	 *
	 * @return string
	 */
	protected function submitted_form_name() {
		if ( function_exists( 'get_submitted_form_name' ) ) {
			return get_submitted_form_name();
		}

		global $submitted_form_name;

		return ! empty( $submitted_form_name ) ? $submitted_form_name : '';
	}

	/**
	 * Returns true when shipping-address processing should run for a prefix.
	 *
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return bool
	 */
	protected function is_supported_pre_name( $pre_name ) {
		return 'cart_' === $pre_name || 'quote_cart_' === $pre_name || 'order_form_' === $pre_name;
	}

	/**
	 * Returns supported checkout prefixes relevant to the current request.
	 *
	 * @param string $pre_name Preferred checkout prefix.
	 *
	 * @return array
	 */
	protected function request_pre_names( $pre_name = '' ) {
		$pre_names = array();
		if ( $this->is_supported_pre_name( $pre_name ) ) {
			$pre_names[] = $pre_name;
		}

		$posted = self::posted_request_data();
		foreach ( array( 'quote_cart_', 'cart_', 'order_form_' ) as $supported_pre_name ) {
			if ( in_array( $supported_pre_name, $pre_names, true ) ) {
				continue;
			}

			if ( isset( $posted[ $supported_pre_name . 'submit' ] ) || $this->has_posted_shipping_address_fields( $supported_pre_name, $posted ) ) {
				$pre_names[] = $supported_pre_name;
			}
		}

		return $pre_names;
	}

	/**
	 * Checks whether the request includes shipping-address fields for a checkout prefix.
	 *
	 * @param string $pre_name Checkout prefix.
	 * @param array  $posted Posted request data.
	 *
	 * @return bool
	 */
	protected function has_posted_shipping_address_fields( $pre_name, $posted ) {
		$field_prefix = $pre_name . 'shipping_address_';
		foreach ( array_keys( $posted ) as $field_name ) {
			if ( 0 === strpos( (string) $field_name, $field_prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns true when a saved shipping-address prefix belongs to a supported checkout form.
	 *
	 * @param string $prefix Saved shipping-address formbuilder prefix.
	 *
	 * @return bool
	 */
	protected function is_supported_shipping_address_prefix( $prefix ) {
		foreach ( array( 'cart_', 'quote_cart_', 'order_form_' ) as $pre_name ) {
			if ( 0 === strpos( $prefix, $pre_name . 'shipping_address_' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the selected shipping form contexts for the current request.
	 *
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return array
	 */
	protected function selected_form_contexts( $pre_name ) {
		if ( ! function_exists( 'get_current_checkout_shipping_options' ) ) {
			return array();
		}

		$shipping_options = get_current_checkout_shipping_options( $pre_name );
		if ( empty( $shipping_options ) || ! is_array( $shipping_options ) ) {
			return array();
		}

		$contexts     = array();
		$shipping_num = count( $shipping_options );
		$group_index  = 0;

		foreach ( $shipping_options as $shipping_option ) {
			if ( empty( $shipping_option['options'] ) || ! is_array( $shipping_option['options'] ) ) {
				continue;
			}

			$field_name = $shipping_num > 1 ? 'shipping_' . $group_index : 'shipping';
			$selected   = '';
			// Read the shipping radio through posted_shipping_option_slug(), never through a
			// sanitize_text_field() based reader. $option_slug below and the rendered radio value are both
			// sanitize_title() slugs, and sanitize_title() PERCENT-ENCODES non-ASCII option names, while
			// sanitize_text_field() strips every %xx octet. That made the comparison at the bottom of this
			// loop impossible to satisfy for any non-Latin option name, so no context was returned and
			// validate_selected_forms() never validated the required shipping-address fields.
			if ( null !== $this->posted_shipping_option_slug( $field_name ) ) {
				$selected = $this->posted_shipping_option_slug( $field_name );
			}
			if ( '' === $selected ) {
				$first_option = reset( $shipping_option['options'] );
				if ( ! empty( $first_option['name'] ) ) {
					$selected = sanitize_title( $first_option['name'] );
				}
			}

			foreach ( $shipping_option['options'] as $option ) {
				$option_slug = sanitize_title( $option['name'] );
				if ( $option_slug !== $selected || ! self::option_has_form( $option, $pre_name ) ) {
					continue;
				}

				$contexts[] = array(
					'group_index'     => $group_index,
					'field_name'      => $field_name,
					'selected'        => $selected,
					'prefix'          => self::field_prefix( $pre_name, $group_index, $option_slug ),
					'slot'            => self::option_slot( $option ),
					'shipping_option' => $shipping_option,
					'option'          => $option,
					'fields'          => json_decode( ic_get_shipping_address_form_fields( self::option_slot( $option ), $pre_name ) ),
				);
				break;
			}

			++$group_index;
		}

		return $contexts;
	}

	/**
	 * Returns shipping-address contexts from selected shipping options and direct POST fallback.
	 *
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return array
	 */
	protected function request_shipping_address_contexts( $pre_name ) {
		$contexts = array();
		foreach ( $this->selected_form_contexts( $pre_name ) as $context ) {
			if ( empty( $context['prefix'] ) ) {
				continue;
			}
			$contexts[ $context['prefix'] ] = $context;
		}

		foreach ( $this->posted_shipping_address_contexts( $pre_name ) as $context ) {
			if ( empty( $context['prefix'] ) ) {
				continue;
			}
			$contexts[ $context['prefix'] ] = $context;
		}

		return array_values( $contexts );
	}

	/**
	 * Returns true when one reconstructed prefix is a nested derivative of another.
	 *
	 * @param string $prefix Candidate shipping-address field prefix.
	 * @param array  $contexts Existing contexts keyed by prefix.
	 *
	 * @return bool
	 */
	protected function context_prefix_is_nested( $prefix, $contexts ) {
		if ( empty( $prefix ) || empty( $contexts ) || ! is_array( $contexts ) ) {
			return false;
		}

		foreach ( array_keys( $contexts ) as $existing_prefix ) {
			$existing_prefix = (string) $existing_prefix;
			if ( '' === $existing_prefix || $existing_prefix === $prefix ) {
				continue;
			}
			if ( 0 === strpos( $prefix, $existing_prefix ) || 0 === strpos( $existing_prefix, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reconstructs shipping-address contexts directly from submitted field names.
	 *
	 * This covers checkout submissions where the store/location context that
	 * produced the form is not available when the order is saved.
	 *
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return array
	 */
	protected function posted_shipping_address_contexts( $pre_name ) {
		if ( ! $this->is_supported_pre_name( $pre_name ) || ! function_exists( 'ic_formbuilder_field_cid' ) ) {
			return array();
		}

		$posted       = self::posted_request_data();
		$field_prefix = $pre_name . 'shipping_address_';
		$contexts     = array();
		foreach ( $this->admin_shipping_address_slots( $pre_name ) as $slot ) {
			$fields = $this->admin_shipping_address_fields_for_slot( $slot, $pre_name );
			if ( empty( $fields->fields ) || ! is_array( $fields->fields ) ) {
				continue;
			}

			$field_definitions = $fields->fields;
			usort(
				$field_definitions,
				static function ( $first_field, $second_field ) {
					return strlen( (string) $second_field->cid ) > strlen( (string) $first_field->cid ) ? 1 : ( strlen( (string) $second_field->cid ) < strlen( (string) $first_field->cid ) ? -1 : 0 );
				}
			);

			foreach ( array_keys( $posted ) as $posted_field_name ) {
				$posted_field_name = (string) $posted_field_name;
				if ( 0 !== strpos( $posted_field_name, $field_prefix ) ) {
					continue;
				}

				if ( substr( $posted_field_name, -strlen( 'shipping_slot' ) ) === 'shipping_slot' ) {
					$prefix = substr( $posted_field_name, 0, -strlen( 'shipping_slot' ) );
					if ( ! $this->is_supported_shipping_address_prefix( $prefix ) ) {
						continue;
					}
					if ( $this->context_prefix_is_nested( $prefix, $contexts ) ) {
						continue;
					}

					$posted_slot = intval( $this->posted_field_value( $posted_field_name ) );
					if ( $posted_slot < 1 ) {
						continue;
					}

					$posted_slot_fields = $this->best_admin_shipping_address_fields_for_prefix( $prefix, $posted_slot, $pre_name, $posted );
					if ( empty( $posted_slot_fields ) ) {
						continue;
					}

					$contexts[ $prefix ] = array(
						'group_index'     => 0,
						'field_name'      => '',
						'selected'        => '',
						'prefix'          => $prefix,
						'slot'            => $posted_slot,
						'shipping_option' => array(),
						'option'          => array(),
						'fields'          => $posted_slot_fields,
					);
					continue;
				}

				foreach ( $field_definitions as $field ) {
					if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
						continue;
					}

					$field_cid = (string) $field->cid;
					if ( substr( $posted_field_name, -strlen( $field_cid ) ) !== $field_cid ) {
						continue;
					}

					$prefix = substr( $posted_field_name, 0, -strlen( $field_cid ) );
					if ( ! $this->is_supported_shipping_address_prefix( $prefix ) ) {
						continue;
					}
					if ( isset( $contexts[ $prefix ] ) ) {
						break;
					}
					if ( $this->context_prefix_is_nested( $prefix, $contexts ) ) {
						break;
					}

					$matched_fields = $this->best_admin_shipping_address_fields_for_prefix( $prefix, $slot, $pre_name, $posted );
					if ( empty( $matched_fields ) ) {
						break;
					}

					$contexts[ $prefix ] = array(
						'group_index'     => 0,
						'field_name'      => '',
						'selected'        => '',
						'prefix'          => $prefix,
						'slot'            => $slot,
						'shipping_option' => array(),
						'option'          => array(),
						'fields'          => $matched_fields,
					);
					break;
				}
			}
		}

		return array_values( $contexts );
	}

	/**
	 * Returns the unique field prefix for one shipping option form.
	 *
	 * @param string $pre_name Checkout prefix.
	 * @param int    $group_index Shipping group index.
	 * @param string $option_slug Shipping option slug.
	 *
	 * @return string
	 */
	protected static function field_prefix( $pre_name, $group_index, $option_slug ) {
		return $pre_name . 'shipping_address_' . intval( $group_index ) . '_' . sanitize_key( $option_slug ) . '_';
	}

	/**
	 * Returns the same-as-billing field identifier for one shipping option form.
	 *
	 * @param string $prefix Shipping-address field prefix.
	 *
	 * @return string
	 */
	protected static function same_as_billing_field_id( $prefix ) {
		return $prefix . 'same_as_billing';
	}

	/**
	 * Returns the hidden shipping-slot field identifier for one shipping option form.
	 *
	 * @param string $prefix Shipping-address field prefix.
	 *
	 * @return string
	 */
	protected static function shipping_slot_field_id( $prefix ) {
		return $prefix . 'shipping_slot';
	}

	/**
	 * Returns true when the value represents a checked checkbox state.
	 *
	 * @param mixed $field_value Field value.
	 *
	 * @return bool
	 */
	protected static function field_value_is_checked( $field_value ) {
		if ( is_array( $field_value ) ) {
			return ! empty( array_filter( $field_value ) );
		}

		return ! empty( $field_value );
	}

	/**
	 * Returns true when the same-as-billing checkbox should be shown.
	 *
	 * @param int    $field_slot Shipping slot number.
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return bool
	 */
	public static function same_as_billing_available( $field_slot, $pre_name = '' ) {
		return (bool) apply_filters( 'ic_shipping_same_as_billing_available', true, intval( $field_slot ), $pre_name );
	}

	/**
	 * Returns likely shipping-address form slots for admin field reconstruction.
	 *
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return array
	 */
	protected function admin_shipping_address_slots( $pre_name = '' ) {
		$slots = array();
		if ( function_exists( 'get_shipping_options_number' ) ) {
			$shipping_options_number = max( 1, intval( get_shipping_options_number() ) );
			for ( $slot = 1; $slot <= $shipping_options_number; $slot++ ) {
				$slots[] = $slot;
			}
		}
		if ( function_exists( 'get_default_shipping_address_collection' ) ) {
			foreach ( get_default_shipping_address_collection() as $slot => $collect_address ) {
				if ( ! empty( $collect_address ) ) {
					$slots[] = intval( $slot );
				}
			}
		}

		if ( empty( $slots ) ) {
			$slots = range( 1, 10 );
		}

		$slots = array_values( array_unique( array_filter( array_map( 'intval', $slots ) ) ) );

		return apply_filters( 'ic_admin_shipping_address_slots', $slots, $pre_name );
	}

	/**
	 * Returns decoded fields for one shipping-address slot.
	 *
	 * @param int    $slot Shipping option slot.
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return object|null
	 */
	protected function admin_shipping_address_fields_for_slot( $slot, $pre_name ) {
		if ( ! function_exists( 'ic_get_shipping_address_form_fields' ) ) {
			return null;
		}

		$fields_json = ic_get_shipping_address_form_fields( $slot, $pre_name );
		return $this->decoded_shipping_address_fields( $fields_json );
	}

	/**
	 * Returns decoded shipping-address fields for one JSON payload.
	 *
	 * @param string $fields_json Shipping-address formbuilder JSON.
	 *
	 * @return object|null
	 */
	protected function decoded_shipping_address_fields( $fields_json ) {
		$fields = json_decode( $fields_json );
		if ( empty( $fields->fields ) || ! is_array( $fields->fields ) ) {
			return null;
		}

		$fields->ic_fields_json = (string) $fields_json;

		return $fields;
	}

	/**
	 * Returns additional shipping-address field candidates for a slot.
	 *
	 * @param int    $slot Shipping option slot.
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return array
	 */
	protected function additional_admin_shipping_address_field_sets_for_slot( $slot, $pre_name ) {
		if ( 'quote_cart_' !== $pre_name || ! function_exists( 'ic_get_quote_cart_shipping_address_forms' ) ) {
			return array();
		}

		$field_sets = array();
		$slot       = intval( $slot );
		foreach ( ic_get_quote_cart_shipping_address_forms() as $key => $form ) {
			if ( is_array( $form ) ) {
				if ( empty( $form[ $slot ] ) ) {
					continue;
				}
				$fields = $this->decoded_shipping_address_fields( $form[ $slot ] );
				if ( empty( $fields ) ) {
					continue;
				}
				$field_sets[ 'location_' . intval( $key ) . '_' . $slot ] = $fields;
			} elseif ( intval( $key ) === $slot ) {
				$fields = $this->decoded_shipping_address_fields( $form );
				if ( empty( $fields ) ) {
					continue;
				}
				$field_sets[ 'slot_' . $slot ] = $fields;
			}
		}

		return $field_sets;
	}

	/**
	 * Returns all candidate field sets for a slot.
	 *
	 * @param int    $slot Shipping option slot.
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return array
	 */
	protected function candidate_admin_shipping_address_field_sets_for_slot( $slot, $pre_name ) {
		$field_sets = array();
		$fields     = $this->admin_shipping_address_fields_for_slot( $slot, $pre_name );
		if ( ! empty( $fields ) ) {
			$field_sets[ 'runtime_' . intval( $slot ) ] = $fields;
		}

		foreach ( $this->additional_admin_shipping_address_field_sets_for_slot( $slot, $pre_name ) as $key => $additional_fields ) {
			if ( empty( $additional_fields ) ) {
				continue;
			}
			$field_sets[ $key ] = $additional_fields;
		}

		return $field_sets;
	}

	/**
	 * Returns the best matching field set for one posted shipping-address prefix.
	 *
	 * @param string $prefix Shipping-address field prefix.
	 * @param int    $slot Shipping option slot.
	 * @param string $pre_name Checkout prefix.
	 * @param array  $posted Posted request data.
	 *
	 * @return object|null
	 */
	protected function best_admin_shipping_address_fields_for_prefix( $prefix, $slot, $pre_name, $posted ) {
		$candidates = $this->candidate_admin_shipping_address_field_sets_for_slot( $slot, $pre_name );
		if ( empty( $candidates ) ) {
			return null;
		}

		$best_candidate = null;
		$best_score     = -1;
		foreach ( $candidates as $candidate ) {
			if ( empty( $candidate->fields ) || ! is_array( $candidate->fields ) ) {
				continue;
			}

			$score = 0;
			foreach ( $candidate->fields as $field ) {
				if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
					continue;
				}

				$field_id = ic_formbuilder_field_cid( $field, $prefix );
				if ( array_key_exists( $field_id, $posted ) ) {
					++$score;
				}
			}

			if ( $score > $best_score ) {
				$best_candidate = $candidate;
				$best_score     = $score;
			}
		}

		return $best_candidate;
	}

	/**
	 * Returns all configured field sets that can match saved admin shipping-address fields.
	 *
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return array
	 */
	protected function admin_shipping_address_field_sets( $pre_name ) {
		$field_sets = array();
		foreach ( $this->admin_shipping_address_slots( $pre_name ) as $slot ) {
			$fields = $this->admin_shipping_address_fields_for_slot( $slot, $pre_name );
			if ( empty( $fields ) ) {
				continue;
			}

			$field_sets[ $slot ] = $fields;
		}

		return $field_sets;
	}

	/**
	 * Detects saved shipping-address form prefixes and their most likely field set.
	 *
	 * @param array $payment_details Saved payment details.
	 *
	 * @return array
	 */
	protected function saved_admin_shipping_address_contexts( $payment_details ) {
		if ( empty( $payment_details ) || ! is_array( $payment_details ) ) {
			return array();
		}

		$prefix_scores = array();
		foreach ( array( 'cart_', 'quote_cart_', 'order_form_' ) as $pre_name ) {
			$field_sets = $this->admin_shipping_address_field_sets( $pre_name );
			if ( empty( $field_sets ) ) {
				continue;
			}

			foreach ( array_keys( $payment_details ) as $field_id ) {
				if ( 0 !== strpos( $field_id, $pre_name . 'shipping_address_' ) ) {
					continue;
				}

				foreach ( $field_sets as $slot => $fields ) {
					foreach ( $fields->fields as $field ) {
						if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
							continue;
						}

						$cid_length = strlen( $field->cid );
						if ( substr( $field_id, -$cid_length ) !== $field->cid ) {
							continue;
						}

						$prefix = substr( $field_id, 0, -$cid_length );
						if ( ! $this->is_supported_shipping_address_prefix( $prefix ) ) {
							continue;
						}

						if ( empty( $prefix_scores[ $prefix ][ $slot ] ) ) {
							$prefix_scores[ $prefix ][ $slot ] = 0;
						}
						++$prefix_scores[ $prefix ][ $slot ];
					}
				}
			}
		}

		if ( empty( $prefix_scores ) ) {
			return array();
		}

		$contexts = array();
		foreach ( $prefix_scores as $prefix => $slot_scores ) {
			arsort( $slot_scores );
			$slot     = intval( key( $slot_scores ) );
			$pre_name = $this->pre_name_from_shipping_address_prefix( $prefix );
			$fields   = $this->admin_shipping_address_fields_for_slot( $slot, $pre_name );
			if ( empty( $fields ) ) {
				continue;
			}

			$contexts[] = array(
				'prefix'      => $prefix,
				'pre_name'    => $pre_name,
				'slot'        => $slot,
				'fields_json' => $fields->ic_fields_json,
				'fields'      => $fields,
			);
		}

		return $contexts;
	}

	/**
	 * Returns the checkout prefix for a saved shipping-address field prefix.
	 *
	 * @param string $prefix Saved shipping-address formbuilder prefix.
	 *
	 * @return string
	 */
	protected function pre_name_from_shipping_address_prefix( $prefix ) {
		foreach ( array( 'cart_', 'quote_cart_', 'order_form_' ) as $pre_name ) {
			if ( 0 === strpos( $prefix, $pre_name . 'shipping_address_' ) ) {
				return $pre_name;
			}
		}

		return '';
	}

	/**
	 * Renders saved shipping-address fields on the admin edit order screen.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $payment_details Saved payment details.
	 *
	 * @return void
	 */
	public function admin_shipping_address_fields( $order_id, $payment_details = array() ) {
		if ( empty( $order_id ) || ! is_numeric( $order_id ) || ! function_exists( 'formbuilder_raw_fields' ) ) {
			return;
		}
		if ( ! is_array( $payment_details ) ) {
			$payment_details = array();
		}

		$contexts = $this->saved_admin_shipping_address_contexts( $payment_details );
		if ( empty( $contexts ) ) {
			if ( empty( $payment_details['shipping_address'] ) ) {
				return;
			}
			$this->admin_shipping_address_summary_field( $payment_details['shipping_address'] );

			return;
		}
		?>
		<tr class="ic-admin-shipping-address">
			<td colspan="2">
				<div class="ic-admin-shipping-address-fields">
					<h2><?php esc_html_e( 'Shipping Address', 'ecommerce-product-catalog' ); ?></h2>
					<?php foreach ( $contexts as $context ) { ?>
						<input type="hidden" name="ic_admin_shipping_address_slots[<?php echo esc_attr( $context['prefix'] ); ?>]" value="<?php echo esc_attr( $context['slot'] ); ?>"/>
						<div class="shipping-address-form-fields" data-pre_name="<?php echo esc_attr( $context['prefix'] ); ?>">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formbuilder returns prepared admin field HTML.
							echo formbuilder_raw_fields( $context['fields_json'], 2, $context['prefix'], $payment_details, array(), false, false );
							?>
						</div>
					<?php } ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders a fallback editable summary when old orders only have summary HTML.
	 *
	 * @param string $shipping_address Saved shipping-address summary.
	 *
	 * @return void
	 */
	protected function admin_shipping_address_summary_field( $shipping_address ) {
		$shipping_address = trim( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), "\n", $shipping_address ) ) );
		?>
		<tr class="ic-admin-shipping-address">
			<td><?php esc_html_e( 'Shipping Address', 'ecommerce-product-catalog' ); ?></td>
			<td>
				<textarea name="ic_admin_shipping_address_summary" rows="5"><?php echo esc_textarea( $shipping_address ); ?></textarea>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns admin-posted shipping-address field contexts.
	 *
	 * @return array
	 */
	protected function posted_admin_shipping_address_contexts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reached only via the update_digital_order hook, whose handler runs current_user_can( 'edit_digital_order' ) and an order-nonce check first. NOTE: that upstream nonce check only rejects a nonce that is present and invalid, so it is the capability check that is load-bearing here; every value read below is sanitized.
		if ( empty( $_POST['ic_admin_shipping_address_slots'] ) || ! is_array( $_POST['ic_admin_shipping_address_slots'] ) ) {
			return array();
		}

		$contexts = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reached only via the update_digital_order hook, whose handler runs current_user_can( 'edit_digital_order' ) and an order-nonce check first. NOTE: that upstream nonce check only rejects a nonce that is present and invalid, so it is the capability check that is load-bearing here; every value read below is sanitized.
		$posted_slots = wp_unslash( $_POST['ic_admin_shipping_address_slots'] );
		foreach ( $posted_slots as $prefix => $slot ) {
			$prefix = sanitize_key( $prefix );
			$slot   = intval( $slot );
			if ( empty( $slot ) || ! $this->is_supported_shipping_address_prefix( $prefix ) ) {
				continue;
			}

			$pre_name = $this->pre_name_from_shipping_address_prefix( $prefix );
			$fields   = $this->admin_shipping_address_fields_for_slot( $slot, $pre_name );
			if ( empty( $fields ) ) {
				continue;
			}

			$contexts[] = array(
				'prefix'   => $prefix,
				'pre_name' => $pre_name,
				'slot'     => $slot,
				'fields'   => $fields,
			);
		}

		return $contexts;
	}

	/**
	 * Returns a posted admin field value.
	 *
	 * @param string $field_id Field ID.
	 *
	 * @return mixed|null
	 */
	protected function posted_admin_field_value( $field_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reached only via the update_digital_order hook, whose handler runs current_user_can( 'edit_digital_order' ) and an order-nonce check first. NOTE: that upstream nonce check only rejects a nonce that is present and invalid, so it is the capability check that is load-bearing here; every value read below is sanitized.
		if ( ! isset( $_POST[ $field_id ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reached only via the update_digital_order hook, whose handler runs current_user_can( 'edit_digital_order' ) and an order-nonce check first. NOTE: that upstream nonce check only rejects a nonce that is present and invalid, so it is the capability check that is load-bearing here; every value read below is sanitized.
		$field_value = wp_unslash( $_POST[ $field_id ] );
		if ( is_array( $field_value ) ) {
			return array_map( 'sanitize_text_field', $field_value );
		}

		return sanitize_text_field( $field_value );
	}

	/**
	 * Persists admin edits to saved shipping-address fields and summary.
	 *
	 * @param int     $order_id Order ID.
	 * @param WP_Post $post Order post object.
	 *
	 * @return void
	 */
	public function save_admin_shipping_address_fields( $order_id, $post = null ) {
		unset( $post );
		if ( empty( $order_id ) || ! is_numeric( $order_id ) || ! function_exists( 'ic_get_order_payment_details' ) || ! function_exists( 'ic_formbuilder_field_cid' ) || ! function_exists( 'ic_message_row' ) ) {
			return;
		}

		$contexts = $this->posted_admin_shipping_address_contexts();
		if ( empty( $contexts ) ) {
			$this->save_admin_shipping_address_summary( $order_id );

			return;
		}

		$payment_details  = ic_get_order_payment_details( $order_id );
		$summary          = '';
		$contexts_payload = array();
		foreach ( $contexts as $context ) {
			$rows = '';
			foreach ( $context['fields']->fields as $field ) {
				if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
					continue;
				}

				$field_id    = ic_formbuilder_field_cid( $field, $context['prefix'] );
				$field_value = $this->posted_admin_field_value( $field_id );
				if ( null === $field_value ) {
					$field_value = '';
				}

				$payment_details[ $field_id ] = $field_value;
				$row_value                    = is_array( $field_value ) ? implode( ', ', array_filter( $field_value ) ) : $field_value;
				if ( '' === trim( (string) $row_value ) ) {
					continue;
				}

				$rows .= ic_message_row( $field, $row_value, $field_id, '<br>', false );
			}

			if ( '' === $rows ) {
				continue;
			}

			$summary           .= $rows . '<br>';
			$contexts_payload[] = $this->build_admin_context_payload( $context );
		}

		$payment_details['shipping_address'] = preg_replace( '/(?:<br>)+$/', '', $summary );
		if ( ! empty( $contexts_payload ) ) {
			$payment_details[ self::payment_details_contexts_key() ] = $contexts_payload;
		} else {
			unset( $payment_details[ self::payment_details_contexts_key() ] );
		}
		update_post_meta( $order_id, '_payment_details', $payment_details );
	}

	/**
	 * Persists the fallback summary textarea for old orders.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	protected function save_admin_shipping_address_summary( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reached only via the update_digital_order hook, whose handler runs current_user_can( 'edit_digital_order' ) and an order-nonce check first. NOTE: that upstream nonce check only rejects a nonce that is present and invalid, so it is the capability check that is load-bearing here; every value read below is sanitized.
		if ( ! isset( $_POST['ic_admin_shipping_address_summary'] ) ) {
			return;
		}

		$payment_details = ic_get_order_payment_details( $order_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reached only via the update_digital_order hook, whose handler runs current_user_can( 'edit_digital_order' ) and an order-nonce check first. NOTE: that upstream nonce check only rejects a nonce that is present and invalid, so it is the capability check that is load-bearing here; every value read below is sanitized.
		$summary = sanitize_textarea_field( wp_unslash( $_POST['ic_admin_shipping_address_summary'] ) );

		$payment_details['shipping_address'] = nl2br( esc_html( $summary ) );
		unset( $payment_details[ self::payment_details_contexts_key() ] );
		update_post_meta( $order_id, '_payment_details', $payment_details );
	}

	/**
	 * Returns raw posted request data for formbuilder repopulation.
	 *
	 * @return array
	 */
	protected static function posted_request_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shipping address fields are processed during the parent checkout form submission.
		return is_array( $_POST ) ? wp_unslash( $_POST ) : array();
	}

	/**
	 * Returns request values with saved formbuilder session values as fallback.
	 *
	 * @param string $pre_name Formbuilder prefix.
	 *
	 * @return array
	 */
	protected static function field_values( $pre_name ) {
		$field_values = array();
		if ( function_exists( 'get_product_catalog_session' ) ) {
			$session = get_product_catalog_session();
			if ( ! empty( $session['formbuilder_fields'][ $pre_name ] ) && is_array( $session['formbuilder_fields'][ $pre_name ] ) ) {
				$field_values = $session['formbuilder_fields'][ $pre_name ];
			}
		}

		return array_merge( $field_values, self::posted_request_data() );
	}

	/**
	 * Returns one posted shipping-option slug.
	 *
	 * Deliberately separate from the generic readers (posted_field_value() and posted_request_data()),
	 * which serve the free-text address fields (names, streets, notes) where sanitize_text_field() is
	 * correct and slugifying the value would mangle it. Only the shipping radios carry a
	 * sanitize_title() slug, and sanitize_title() percent-encodes non-ASCII option names, so
	 * sanitize_text_field() -- which strips every %xx octet -- destroys them. sanitize_title() is
	 * idempotent on an already-slugged value, so it is the only normalizer that can agree with the
	 * sanitize_title()-built option slugs this value is compared against.
	 *
	 * @param string $field_id Shipping radio field name (shipping or shipping_N).
	 *
	 * @return string|null
	 */
	protected function posted_shipping_option_slug( $field_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shipping address fields are processed during the parent checkout form submission.
		if ( ! isset( $_POST[ $field_id ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shipping address fields are processed during the parent checkout form submission.
		return sanitize_title( wp_unslash( $_POST[ $field_id ] ) );
	}

	/**
	 * Returns one posted field value.
	 *
	 * @param string $field_id Field identifier.
	 *
	 * @return mixed|null
	 */
	protected function posted_field_value( $field_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shipping address fields are processed during the parent checkout form submission.
		if ( ! isset( $_POST[ $field_id ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shipping address fields are processed during the parent checkout form submission.
		$field_value = wp_unslash( $_POST[ $field_id ] );

		if ( is_array( $field_value ) ) {
			return array_map( 'sanitize_text_field', $field_value );
		}

		return sanitize_text_field( $field_value );
	}

	/**
	 * Returns true when the request selected "same as billing" for one context.
	 *
	 * @param array $context Shipping-address context.
	 *
	 * @return bool
	 */
	protected function is_same_as_billing_context( $context ) {
		if ( empty( $context['prefix'] ) ) {
			return false;
		}

		$field_id    = self::same_as_billing_field_id( $context['prefix'] );
		$field_value = $this->posted_field_value( $field_id );
		if ( null === $field_value ) {
			$field_values = self::field_values( $context['prefix'] );
			$field_value  = isset( $field_values[ $field_id ] ) ? $field_values[ $field_id ] : null;
		}

		return self::field_value_is_checked( $field_value );
	}

	/**
	 * Returns the summary used when shipping matches billing.
	 *
	 * @return string
	 */
	protected static function same_as_billing_summary() {
		return esc_html__( 'Shipping address is same as billing address', 'ecommerce-product-catalog' );
	}

	/**
	 * Returns the payment-details key used for structured shipping contexts.
	 *
	 * @return string
	 */
	protected static function payment_details_contexts_key() {
		return '_shipping_address_contexts';
	}

	/**
	 * Returns structured shipping contexts saved in payment details.
	 *
	 * @param array $payment_details Saved payment details.
	 *
	 * @return array
	 */
	public function payment_details_contexts( $payment_details ) {
		if ( empty( $payment_details ) || ! is_array( $payment_details ) ) {
			return array();
		}

		$contexts_key = self::payment_details_contexts_key();
		if ( ! empty( $payment_details[ $contexts_key ] ) && is_array( $payment_details[ $contexts_key ] ) ) {
			$contexts = $this->normalize_payment_details_contexts( $payment_details[ $contexts_key ] );
			if ( ! empty( $contexts ) ) {
				return $contexts;
			}
		}

		$contexts = $this->build_saved_payment_details_contexts( $payment_details );
		if ( ! empty( $contexts ) ) {
			return $contexts;
		}

		if ( ! empty( $payment_details['shipping_address'] ) && trim( wp_strip_all_tags( $payment_details['shipping_address'] ) ) === self::same_as_billing_summary() ) {
			return array(
				array(
					'slot'            => 0,
					'pre_name'        => '',
					'prefix'          => '',
					'same_as_billing' => true,
					'fields_json'     => '',
					'values'          => array(),
				),
			);
		}

		return array();
	}

	/**
	 * Normalizes structured shipping contexts loaded from saved payment details.
	 *
	 * @param array $contexts Raw contexts.
	 *
	 * @return array
	 */
	protected function normalize_payment_details_contexts( $contexts ) {
		$normalized = array();
		foreach ( $contexts as $context ) {
			if ( ! is_array( $context ) ) {
				continue;
			}

			$fields_json = '';
			if ( isset( $context['fields_json'] ) && is_string( $context['fields_json'] ) ) {
				$decoded_fields = json_decode( $context['fields_json'] );
				$fields_json    = ! empty( $decoded_fields ) ? wp_json_encode( $decoded_fields ) : sanitize_text_field( $context['fields_json'] );
			}

			$normalized[] = array(
				'slot'            => ! empty( $context['slot'] ) ? intval( $context['slot'] ) : 0,
				'pre_name'        => ! empty( $context['pre_name'] ) ? sanitize_key( $context['pre_name'] ) : '',
				'prefix'          => ! empty( $context['prefix'] ) ? sanitize_key( $context['prefix'] ) : '',
				'same_as_billing' => ! empty( $context['same_as_billing'] ),
				'fields_json'     => $fields_json,
				'values'          => $this->sanitize_context_values( isset( $context['values'] ) ? $context['values'] : array() ),
			);
		}

		return $normalized;
	}

	/**
	 * Sanitizes structured field values for one shipping context.
	 *
	 * @param array $values Raw field values.
	 *
	 * @return array
	 */
	protected function sanitize_context_values( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $values as $cid => $value ) {
			$cid = sanitize_key( $cid );
			if ( '' === $cid ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $cid ] = array_map( 'sanitize_text_field', $value );
			} else {
				$sanitized[ $cid ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Builds structured contexts from saved legacy payment details.
	 *
	 * @param array $payment_details Saved payment details.
	 *
	 * @return array
	 */
	protected function build_saved_payment_details_contexts( $payment_details ) {
		$contexts = array();
		foreach ( $this->saved_admin_shipping_address_contexts( $payment_details ) as $context ) {
			$payload = $this->build_context_payload_from_saved_payment_details( $context, $payment_details );
			if ( empty( $payload ) ) {
				continue;
			}

			$contexts[] = $payload;
		}

		return $contexts;
	}

	/**
	 * Builds a structured context payload from saved raw payment-detail fields.
	 *
	 * @param array $context         Shipping-address context.
	 * @param array $payment_details Saved payment details.
	 *
	 * @return array
	 */
	protected function build_context_payload_from_saved_payment_details( $context, $payment_details ) {
		if ( empty( $context['prefix'] ) || empty( $context['fields']->fields ) || ! is_array( $context['fields']->fields ) ) {
			return array();
		}

		$payload = array(
			'slot'            => ! empty( $context['slot'] ) ? intval( $context['slot'] ) : 0,
			'pre_name'        => ! empty( $context['pre_name'] ) ? $context['pre_name'] : $this->pre_name_from_shipping_address_prefix( $context['prefix'] ),
			'prefix'          => $context['prefix'],
			'same_as_billing' => false,
			'fields_json'     => ! empty( $context['fields_json'] ) ? (string) $context['fields_json'] : '',
			'values'          => array(),
		);

		foreach ( $context['fields']->fields as $field ) {
			if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
				continue;
			}

			$field_id = ic_formbuilder_field_cid( $field, $context['prefix'] );
			if ( ! array_key_exists( $field_id, $payment_details ) ) {
				continue;
			}

			$payload['values'][ $field->cid ] = $payment_details[ $field_id ];
		}

		return $payload;
	}

	/**
	 * Builds a structured context payload from the current checkout request.
	 *
	 * @param array $context Shipping-address context.
	 *
	 * @return array
	 */
	protected function build_request_context_payload( $context ) {
		if ( empty( $context['prefix'] ) ) {
			return array();
		}

		$payload = array(
			'slot'            => ! empty( $context['slot'] ) ? intval( $context['slot'] ) : 0,
			'pre_name'        => $this->pre_name_from_shipping_address_prefix( $context['prefix'] ),
			'prefix'          => $context['prefix'],
			'same_as_billing' => $this->is_same_as_billing_context( $context ),
			'fields_json'     => ! empty( $context['fields']->ic_fields_json ) ? $context['fields']->ic_fields_json : '',
			'values'          => array(),
		);

		if ( empty( $context['fields']->fields ) || ! is_array( $context['fields']->fields ) ) {
			return $payload;
		}

		foreach ( $context['fields']->fields as $field ) {
			if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
				continue;
			}

			$field_id    = ic_formbuilder_field_cid( $field, $context['prefix'] );
			$field_value = $this->posted_field_value( $field_id );
			if ( null === $field_value ) {
				$field_values = self::field_values( $context['prefix'] );
				$field_value  = isset( $field_values[ $field_id ] ) ? $field_values[ $field_id ] : null;
			}
			if ( null === $field_value ) {
				continue;
			}

			$payload['values'][ $field->cid ] = $field_value;
		}

		return $payload;
	}

	/**
	 * Builds a structured context payload from admin-posted shipping-address fields.
	 *
	 * @param array $context Shipping-address context.
	 *
	 * @return array
	 */
	protected function build_admin_context_payload( $context ) {
		if ( empty( $context['prefix'] ) ) {
			return array();
		}

		$payload = array(
			'slot'            => ! empty( $context['slot'] ) ? intval( $context['slot'] ) : 0,
			'pre_name'        => ! empty( $context['pre_name'] ) ? $context['pre_name'] : $this->pre_name_from_shipping_address_prefix( $context['prefix'] ),
			'prefix'          => $context['prefix'],
			'same_as_billing' => false,
			'fields_json'     => ! empty( $context['fields']->ic_fields_json ) ? $context['fields']->ic_fields_json : '',
			'values'          => array(),
		);

		if ( empty( $context['fields']->fields ) || ! is_array( $context['fields']->fields ) ) {
			return $payload;
		}

		foreach ( $context['fields']->fields as $field ) {
			if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
				continue;
			}

			$field_id    = ic_formbuilder_field_cid( $field, $context['prefix'] );
			$field_value = $this->posted_admin_field_value( $field_id );
			if ( null === $field_value ) {
				continue;
			}

			$payload['values'][ $field->cid ] = $field_value;
		}

		return $payload;
	}

	/**
	 * Adds selected shipping-address fields to the saved form values.
	 *
	 * @param array $filled_fields Existing filled fields.
	 *
	 * @return array
	 */
	public function merge_selected_fields( $filled_fields ) {
		$pre_names = $this->request_pre_names( $this->submitted_form_name() );
		if ( empty( $pre_names ) ) {
			return $filled_fields;
		}

		foreach ( $pre_names as $pre_name ) {
			foreach ( $this->request_shipping_address_contexts( $pre_name ) as $context ) {
				if ( $this->is_same_as_billing_context( $context ) ) {
					continue;
				}

				if ( empty( $context['fields']->fields ) || ! is_array( $context['fields']->fields ) ) {
					continue;
				}

				foreach ( $context['fields']->fields as $field ) {
					if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
						continue;
					}

					$field_id    = ic_formbuilder_field_cid( $field, $context['prefix'] );
					$field_value = $this->posted_field_value( $field_id );
					if ( null === $field_value ) {
						continue;
					}

					$filled_fields[ $field_id ] = $field_value;
				}
			}
		}

		return $filled_fields;
	}

	/**
	 * Validates required selected shipping-address fields.
	 *
	 * @param bool $error Existing form error state.
	 *
	 * @return bool
	 */
	public function validate_selected_forms( $error ) {
		self::$validation_errors = array();

		$pre_names = $this->request_pre_names( $this->submitted_form_name() );
		if ( empty( $pre_names ) ) {
			return $error;
		}

		foreach ( $pre_names as $pre_name ) {
			foreach ( $this->request_shipping_address_contexts( $pre_name ) as $context ) {
				if ( $this->is_same_as_billing_context( $context ) ) {
					continue;
				}

				if ( empty( $context['fields']->fields ) || ! is_array( $context['fields']->fields ) ) {
					continue;
				}

				foreach ( $context['fields']->fields as $field ) {
					if ( empty( $field->required ) || empty( $field->cid ) || 'section_break' === $field->field_type ) {
						continue;
					}

					$field_id    = ic_formbuilder_field_cid( $field, $context['prefix'] );
					$field_value = $this->posted_field_value( $field_id );
					if ( null === $field_value ) {
						$field_value = '';
					}
					$is_empty = is_array( $field_value ) ? empty( array_filter( $field_value ) ) : '' === trim( (string) $field_value );

					if ( $is_empty ) {
						self::$validation_errors[ $field_id ] = wp_strip_all_tags( $field->label );
						$error                                = true;
					}
				}
			}
		}

		return $error;
	}

	/**
	 * Adds validation messages for selected shipping-address fields.
	 *
	 * @param array $error_output Existing validation errors.
	 *
	 * @return array
	 */
	public function add_validation_messages( $error_output ) {
		if ( empty( self::$validation_errors ) ) {
			return $error_output;
		}

		return array_merge( $error_output, self::$validation_errors );
	}

	/**
	 * Saves the rendered shipping-address summary in order payment details.
	 *
	 * @param array $payment_details Payment details.
	 *
	 * @return array
	 */
	public function save_shipping_address_summary( $payment_details ) {
		foreach ( $this->request_pre_names( $this->submitted_form_name() ) as $pre_name ) {
			$shipping_address = $this->render_selected_shipping_address( $pre_name );
			$contexts         = array();
			foreach ( $this->request_shipping_address_contexts( $pre_name ) as $context ) {
				$payload = $this->build_request_context_payload( $context );
				if ( empty( $payload ) ) {
					continue;
				}

				$contexts[] = $payload;
			}

			if ( ! empty( $shipping_address ) || ! empty( $contexts ) ) {
				$payment_details['shipping_address'] = $shipping_address;
				if ( ! empty( $contexts ) ) {
					$payment_details[ self::payment_details_contexts_key() ] = $contexts;
				}

				return $payment_details;
			}
		}

		return $payment_details;
	}

	/**
	 * Adds the posted shipping-address summary to gateway payment details.
	 *
	 * @param array $payment_details Payment details.
	 *
	 * @return array
	 */
	public function add_shipping_address_to_payment_details( $payment_details ) {
		if ( ! is_array( $payment_details ) ) {
			return $payment_details;
		}

		foreach ( $this->request_pre_names( $this->submitted_form_name() ) as $pre_name ) {
			$shipping_address = $this->render_selected_shipping_address( $pre_name );
			$contexts         = array();
			foreach ( $this->request_shipping_address_contexts( $pre_name ) as $context ) {
				$payload = $this->build_request_context_payload( $context );
				if ( empty( $payload ) ) {
					continue;
				}

				$contexts[] = $payload;
			}

			if ( empty( $shipping_address ) && empty( $contexts ) ) {
				continue;
			}

			if ( empty( $payment_details['shipping_address'] ) ) {
				$payment_details['shipping_address'] = $shipping_address;
			}
			if ( empty( $payment_details[ self::payment_details_contexts_key() ] ) && ! empty( $contexts ) ) {
				$payment_details[ self::payment_details_contexts_key() ] = $contexts;
			}

			return $payment_details;
		}

		return $payment_details;
	}

	/**
	 * Returns the notification shipping-address HTML.
	 *
	 * @param string     $content Existing content.
	 * @param string|int $pre_name Checkout prefix or order ID.
	 *
	 * @return string
	 */
	public function notification_shipping_address( $content, $pre_name ) {
		if ( empty( $pre_name ) ) {
			$pre_name = $this->submitted_form_name();
		}

		if ( is_numeric( $pre_name ) && function_exists( 'ic_get_order_payment_details' ) ) {
			$payment_details  = ic_get_order_payment_details( intval( $pre_name ) );
			$shipping_address = $this->render_saved_notification_shipping_address( $payment_details );
			if ( '' !== $shipping_address ) {
				return $shipping_address;
			}
			if ( ! empty( $payment_details['shipping_address'] ) ) {
				return $payment_details['shipping_address'];
			}

			return $content;
		}

		if ( ! $this->is_supported_pre_name( $pre_name ) ) {
			return $content;
		}

		$shipping_address = $this->render_selected_shipping_address( $pre_name );
		if ( ! empty( $shipping_address ) ) {
			return $shipping_address;
		}

		return $content;
	}

	/**
	 * Returns notification shipping-address HTML rendered from saved structured contexts.
	 *
	 * @param array $payment_details Saved payment details.
	 *
	 * @return string
	 */
	protected function render_saved_notification_shipping_address( $payment_details ) {
		if ( empty( $payment_details ) || ! is_array( $payment_details ) ) {
			return '';
		}

		$contexts = $this->payment_details_contexts( $payment_details );
		if ( empty( $contexts ) ) {
			return '';
		}

		$output = '';
		foreach ( $contexts as $context ) {
			$rows = $this->render_saved_notification_context_rows( $context, $payment_details );
			if ( '' === $rows ) {
				continue;
			}

			$output .= $rows . '<br>';
		}

		return preg_replace( '/(?:<br>)+$/', '', $output );
	}

	/**
	 * Renders one saved structured shipping-address context for notifications.
	 *
	 * @param array $context         Saved shipping-address context.
	 * @param array $payment_details Saved payment details.
	 *
	 * @return string
	 */
	protected function render_saved_notification_context_rows( $context, $payment_details ) {
		$fields = $this->context_fields( $context );
		if ( empty( $fields ) ) {
			return '';
		}

		$rows = '';
		foreach ( $fields as $field ) {
			if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
				continue;
			}

			$field_value = $this->saved_notification_context_field_value( $context, $field, $payment_details );
			if ( null === $field_value ) {
				continue;
			}
			$field_value = $this->normalize_notification_field_value( $field_value, $field );
			if ( '' === trim( (string) $field_value ) ) {
				continue;
			}

			$rows .= ic_message_row( $field, $field_value, $field->cid, '<br>', false );
		}

		return $rows;
	}

	/**
	 * Returns the decoded field definitions for a saved shipping-address context.
	 *
	 * @param array $context Saved shipping-address context.
	 *
	 * @return array
	 */
	protected function context_fields( $context ) {
		if ( empty( $context['fields_json'] ) || ! is_string( $context['fields_json'] ) ) {
			return array();
		}

		$fields = json_decode( $context['fields_json'] );
		if ( empty( $fields->fields ) || ! is_array( $fields->fields ) ) {
			return array();
		}

		return $fields->fields;
	}

	/**
	 * Returns one saved notification field value.
	 *
	 * @param array  $context         Saved shipping-address context.
	 * @param object $field           Shipping-address field definition.
	 * @param array  $payment_details Saved payment details.
	 *
	 * @return array|string|null
	 */
	protected function saved_notification_context_field_value( $context, $field, $payment_details ) {
		if ( ! empty( $context['same_as_billing'] ) ) {
			return $this->same_as_billing_notification_field_value( $field, $payment_details );
		}

		if ( empty( $context['values'] ) || ! is_array( $context['values'] ) ) {
			return null;
		}

		return array_key_exists( $field->cid, $context['values'] ) ? $context['values'][ $field->cid ] : null;
	}

	/**
	 * Returns a billing/payment-detail value for a same-as-billing notification field.
	 *
	 * @param object $field           Shipping-address field definition.
	 * @param array  $payment_details Saved payment details.
	 *
	 * @return string|null
	 */
	protected function same_as_billing_notification_field_value( $field, $payment_details ) {
		if ( function_exists( 'ic_qc_sc_same_as_billing_notification_field_value' ) ) {
			return ic_qc_sc_same_as_billing_notification_field_value( $field, $payment_details );
		}

		return null;
	}

	/**
	 * Normalizes one notification field value using the shared formbuilder formatter.
	 *
	 * @param mixed       $field_value Raw field value.
	 * @param object      $field       Shipping-address field definition.
	 * @param string|null $field_id    Optional concrete field identifier.
	 *
	 * @return string
	 */
	protected function normalize_notification_field_value( $field_value, $field, $field_id = null ) {
		$field_id   = null !== $field_id ? (string) $field_id : ( ! empty( $field->cid ) ? (string) $field->cid : '' );
		$field_type = ! empty( $field->field_type ) ? (string) $field->field_type : '';

		if ( is_array( $field_value ) ) {
			$normalized_values = array();
			foreach ( $field_value as $single_value ) {
				if ( is_array( $single_value ) ) {
					$single_value = implode( ', ', $single_value );
				}

				$single_value = $this->normalize_notification_scalar_value( $single_value, $field_id, $field_type );
				if ( '' === trim( (string) $single_value ) ) {
					continue;
				}

				$normalized_values[] = $single_value;
			}

			return implode( ', ', $normalized_values );
		}

		return (string) $this->normalize_notification_scalar_value( $field_value, $field_id, $field_type );
	}

	/**
	 * Normalizes one scalar notification field value.
	 *
	 * @param mixed  $field_value Raw scalar value.
	 * @param string $field_id    Concrete field identifier.
	 * @param string $field_type  Formbuilder field type.
	 *
	 * @return mixed
	 */
	protected function normalize_notification_scalar_value( $field_value, $field_id, $field_type ) {
		return apply_filters( 'ic_formbuilder_message_field_value', $field_value, $field_id, $field_type );
	}

	/**
	 * Returns rendered selected shipping-address summary HTML.
	 *
	 * @param string $pre_name Checkout prefix.
	 *
	 * @return string
	 */
	protected function render_selected_shipping_address( $pre_name ) {
		$contexts = $this->request_shipping_address_contexts( $pre_name );
		if ( empty( $contexts ) ) {
			return '';
		}

		$output   = '';
		$multiple = count( $contexts ) > 1;

		foreach ( $contexts as $context ) {
			if ( $this->is_same_as_billing_context( $context ) ) {
				if ( $multiple && ! empty( $context['shipping_option']['product_names'] ) ) {
					$output .= '<strong>' . esc_html( $context['shipping_option']['product_names'] ) . '</strong><br>';
				}
				$output .= self::same_as_billing_summary();
				$output .= '<br>';
				continue;
			}

			if ( empty( $context['fields']->fields ) || ! is_array( $context['fields']->fields ) ) {
				continue;
			}

			$rows = '';
			foreach ( $context['fields']->fields as $field ) {
				if ( empty( $field->cid ) || 'section_break' === $field->field_type ) {
					continue;
				}

				$field_id    = ic_formbuilder_field_cid( $field, $context['prefix'] );
				$field_value = $this->posted_field_value( $field_id );
				if ( null === $field_value ) {
					continue;
				}

				$field_value = $this->normalize_notification_field_value( $field_value, $field, $field_id );
				if ( '' === trim( $field_value ) ) {
					continue;
				}

				$rows .= ic_message_row( $field, $field_value, $field_id, '<br>', false );
			}

			if ( '' === $rows ) {
				continue;
			}

			if ( $multiple && ! empty( $context['shipping_option']['product_names'] ) ) {
				$output .= '<strong>' . esc_html( $context['shipping_option']['product_names'] ) . '</strong><br>';
			}
			$output .= $rows;
			$output .= '<br>';
		}

		return preg_replace( '/(?:<br>)+$/', '', $output );
	}
}

$GLOBALS['ic_shipping_address_form'] = new IC_Shipping_Address_Form();

if ( ! function_exists( 'ic_get_saved_shipping_address_contexts' ) ) {
	/**
	 * Returns structured shipping-address contexts saved with an order.
	 *
	 * @param array $payment_details Saved payment details.
	 *
	 * @return array
	 */
	function ic_get_saved_shipping_address_contexts( $payment_details ) {
		if ( empty( $GLOBALS['ic_shipping_address_form'] ) || ! $GLOBALS['ic_shipping_address_form'] instanceof IC_Shipping_Address_Form ) {
			return array();
		}

		return $GLOBALS['ic_shipping_address_form']->payment_details_contexts( $payment_details );
	}
}
