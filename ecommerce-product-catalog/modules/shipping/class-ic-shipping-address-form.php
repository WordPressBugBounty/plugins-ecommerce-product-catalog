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
		wp_register_script(
			'ic-shipping-address-form',
			AL_PLUGIN_BASE_PATH . 'modules/shipping/js/shipping-address-form.js' . ic_filemtime( AL_BASE_PATH . '/modules/shipping/js/shipping-address-form.js' ),
			array(
				'jquery',
			),
			IC_EPC_VERSION,
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
			wp_register_script(
				'ic-shipping-address-form',
				AL_PLUGIN_BASE_PATH . 'modules/shipping/js/shipping-address-form.js' . ic_filemtime( AL_BASE_PATH . '/modules/shipping/js/shipping-address-form.js' ),
				array(
					'jquery',
				),
				IC_EPC_VERSION,
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
		return "<script>(function($){function updateGroups(form){if(!form.length){return;}form.find('.ic-shipping-address-group').each(function(){var group=$(this),fieldName=group.data('ic-shipping-field'),selectedField,selectedValue='';if(!fieldName){return;}selectedField=form.find('input[type=\"radio\"][name=\"'+fieldName+'\"]:checked').first();if(!selectedField.length){selectedField=form.find('input[type=\"hidden\"][name=\"'+fieldName+'\"]').first();}selectedValue=selectedField.val()||'';group.find('.ic-shipping-address-option').each(function(){var option=$(this),isActive=option.data('ic-shipping-option-value')===selectedValue,fields=option.find('input, select, textarea, button');option.toggle(isActive);option.toggleClass('active',isActive);fields.prop('disabled',!isActive);fields.filter('select').trigger('chosen:updated');});});}$(function(){var script=$(document.currentScript),previous=script.prev(),group=previous.is('.ic-shipping-address-group')?previous:previous.find('.ic-shipping-address-group').first(),form=group.closest('form');updateGroups(form);if(form.length){form.off('change.icShippingAddressInline','input[type=\"radio\"][name=\"shipping\"], input[type=\"radio\"][name^=\"shipping_\"]').on('change.icShippingAddressInline','input[type=\"radio\"][name=\"shipping\"], input[type=\"radio\"][name^=\"shipping_\"]',function(){updateGroups(form);});}});})(jQuery);</script>";
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

			$option_slug  = sanitize_title( $option['name'] );
			$field_slot   = self::option_slot( $option );
			$field_json   = ic_get_shipping_address_form_fields( $field_slot, $pre_name );
			$is_active    = $option_slug === $selected_value;
			$field_prefix = self::field_prefix( $pre_name, $group_index, $option_slug );
			$field_values = self::field_values( $field_prefix );
			$fields_html  = formbuilder_raw_fields( $field_json, 2, $field_prefix, $field_values );
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
			$group_forms .= '<div class="shipping-address-form-fields table" data-pre_name="' . esc_attr( $field_prefix ) . '">';
			$group_forms .= $fields_html;
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
			if ( null !== $this->posted_scalar_value( $field_name ) ) {
				$selected = $this->posted_scalar_value( $field_name );
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
			if ( empty( $context['prefix'] ) || isset( $contexts[ $context['prefix'] ] ) ) {
				continue;
			}
			$contexts[ $context['prefix'] ] = $context;
		}

		return array_values( $contexts );
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

					$contexts[ $prefix ] = array(
						'group_index'     => 0,
						'field_name'      => '',
						'selected'        => '',
						'prefix'          => $prefix,
						'shipping_option' => array(),
						'option'          => array(),
						'fields'          => $fields,
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
		$fields      = json_decode( $fields_json );
		if ( empty( $fields->fields ) || ! is_array( $fields->fields ) ) {
			return null;
		}

		$fields->ic_fields_json = $fields_json;

		return $fields;
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The parent order save handler verifies the order nonce before firing update_digital_order.
		if ( empty( $_POST['ic_admin_shipping_address_slots'] ) || ! is_array( $_POST['ic_admin_shipping_address_slots'] ) ) {
			return array();
		}

		$contexts = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The parent order save handler verifies the order nonce before firing update_digital_order.
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The parent order save handler verifies the order nonce before firing update_digital_order.
		if ( ! isset( $_POST[ $field_id ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The parent order save handler verifies the order nonce before firing update_digital_order.
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

		$payment_details = ic_get_order_payment_details( $order_id );
		$summary         = '';
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

			$summary .= $rows . '<br>';
		}

		$payment_details['shipping_address'] = preg_replace( '/(?:<br>)+$/', '', $summary );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The parent order save handler verifies the order nonce before firing update_digital_order.
		if ( ! isset( $_POST['ic_admin_shipping_address_summary'] ) ) {
			return;
		}

		$payment_details = ic_get_order_payment_details( $order_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The parent order save handler verifies the order nonce before firing update_digital_order.
		$summary = sanitize_textarea_field( wp_unslash( $_POST['ic_admin_shipping_address_summary'] ) );

		$payment_details['shipping_address'] = nl2br( esc_html( $summary ) );
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
	 * Returns one posted scalar value.
	 *
	 * @param string $field_id Field identifier.
	 *
	 * @return string|null
	 */
	protected function posted_scalar_value( $field_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shipping address fields are processed during the parent checkout form submission.
		if ( ! isset( $_POST[ $field_id ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shipping address fields are processed during the parent checkout form submission.
		return sanitize_text_field( wp_unslash( $_POST[ $field_id ] ) );
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
			if ( ! empty( $shipping_address ) ) {
				$payment_details['shipping_address'] = $shipping_address;

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
		if ( ! is_array( $payment_details ) || ! empty( $payment_details['shipping_address'] ) ) {
			return $payment_details;
		}

		foreach ( $this->request_pre_names( $this->submitted_form_name() ) as $pre_name ) {
			$shipping_address = $this->render_selected_shipping_address( $pre_name );
			if ( empty( $shipping_address ) ) {
				continue;
			}

			$payment_details['shipping_address'] = $shipping_address;

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
			$payment_details = ic_get_order_payment_details( intval( $pre_name ) );
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

				if ( is_array( $field_value ) ) {
					$field_value = implode( ', ', $field_value );
				}

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

new IC_Shipping_Address_Form();
