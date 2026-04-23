<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages shopping cart
 *
 * Here shopping cart functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */
class ic_cart_shipping {
	function __construct() {
		if ( is_ic_shipping_enabled() ) {
			// Register before settings pages are instantiated on init so the cached
			// shipping settings page includes the checkout shipping section.
			add_action( 'init', array( $this, 'hooks' ), -1 );
		}
	}

	function hooks() {
		add_filter( 'ic_epc_shipping_settings_page_sections', array( $this, 'settings_section' ), 10, 2 );

		add_filter( 'ic_checkout_products_table_end', array( $this, 'shipping_total_table_row' ), 10, 3 );
		add_filter( 'ic_formbuilder_before_button', array( $this, 'checkout_options_html' ), 7, 2 );

		add_filter( 'checkout_shipping_options', array( $this, 'add_current' ), 10, 2 );
		add_filter( 'shopping_cart_order_handling', array( $this, 'selected_cost' ), 10, 2 );
		add_filter( 'ic_formbuilder_user_email', array( $this, 'shipping_address' ), 10, 2 );
		add_filter( 'ic_formbuilder_admin_email', array( $this, 'shipping_address' ), 10, 2 );
		add_filter( 'ic_payment_complete_user_message', array( $this, 'shipping_address' ), 10, 2 );
		add_filter( 'ic_payment_complete_admin_message', array( $this, 'shipping_address' ), 10, 2 );
	}

	/**
	 * Adds checkout shipping settings to the shipping settings page sections.
	 *
	 * @param array $sections Existing shipping settings sections.
	 * @param array $page_settings Shipping page settings.
	 *
	 * @return array
	 */
	function settings_section( $sections, $page_settings ) {
		if ( empty( $page_settings['shipping_count'] ) ) {
			return $sections;
		}

		$sections[] = array(
			'title'       => __( 'Checkout Shipping Cost', 'ecommerce-product-catalog' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'table_args'  => array(
				'settings' => array(
					'rows' => array(
						array(
							'type'    => 'radio',
							'label'   => __( 'Shipping Cost Mode', 'ecommerce-product-catalog' ),
							'name'    => 'general_shipping_settings[cart_shipping][mode]',
							'value'   => $this->settings()['mode'],
							'options' => array(
								'highest'    => __( 'Highest cost of all products in cart', 'ecommerce-product-catalog' ),
								'individual' => __( 'Sum of each product shipping cost', 'ecommerce-product-catalog' ),
								'none'       => __( 'Shipping cost calculation disabled', 'ecommerce-product-catalog' ),
							),
						),
					),
				),
			),
		);

		return $sections;
	}

	/**
	 * Returns cart shipping settings
	 *
	 * @return type
	 */
	function settings() {
		$shipping_settings                          = get_general_shipping_settings();
		$shipping_settings['cart_shipping']['mode'] = isset( $shipping_settings['cart_shipping']['mode'] ) ? $shipping_settings['cart_shipping']['mode'] : 'highest';

		return $shipping_settings['cart_shipping'];
	}

	/**
	 * Checks if indivicual cart shipping cost is enabled
	 *
	 * @return boolean
	 */
	function individual() {
		$settings = $this->settings();
		if ( $settings['mode'] == 'individual' ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns current checkout shipping options array
	 *
	 * @param string $pre_name
	 *
	 * @return array
	 */
	function selected_options( $pre_name ) {
		$products_array = ic_cart_products_array( null, $pre_name . 'content' );
		$cache_key      = $this->selected_options_cache_key( $pre_name, $products_array );
		if ( $checkout_shipping = ic_get_cache( $cache_key ) ) {
			return $checkout_shipping;
		}
		$base_checkout_shipping = $this->get_cached_selected_options_base( $cache_key, $pre_name );
		if ( null === $base_checkout_shipping ) {
			$product_count   = count( $products_array );
			$shipping_labels = $this->remove_empty_labels( $this->cart_products_shipping_labels( $products_array ) );
			$not_unique      = $this->cart_get_most_appearing_shipping( $shipping_labels );
			if ( empty( $not_unique ) ) {
				$base_checkout_shipping = $this->front_labels( $shipping_labels );
			} else {
				$max             = max( $not_unique );
				$current_options = $this->array_get_max_value_keys( $not_unique );
				if ( ! empty( $current_options ) && $max >= $product_count ) {
					$base_checkout_shipping = $this->labels_to_options( $shipping_labels, $current_options );
				} else {
					$base_checkout_shipping = $this->labels_to_options( $shipping_labels, $current_options );
				}
			}
			$this->cache_selected_options_base( $cache_key, $pre_name, $base_checkout_shipping );
		}
		$shipping_labels   = $this->remove_empty_labels( $this->cart_products_shipping_labels( $products_array ) );
		$checkout_shipping = apply_filters( 'current_checkout_shipping_options', $base_checkout_shipping, $shipping_labels, $products_array );
		ic_save_cache( $cache_key, $checkout_shipping );

		return $checkout_shipping;
	}

	function selected_options_cache_key( $pre_name, $products_array ) {
		return 'current_checkout_shipping_options_' . sanitize_key( $pre_name ) . '_' . md5(
			wp_json_encode(
				array(
					'products'         => $products_array,
					'shipping_mode'    => $this->settings()['mode'],
					'shipping_count'   => intval( get_shipping_options_number() ),
					'source_mode'      => function_exists( 'get_shipping_source_mode' ) ? get_shipping_source_mode() : '',
					'schema_version'   => function_exists( 'ic_shipping_checkout_cache_schema' ) ? ic_shipping_checkout_cache_schema() : 'shipping_address_slots_v2',
					'cache_version'    => function_exists( 'ic_shipping_checkout_cache_version' ) ? ic_shipping_checkout_cache_version() : '',
				)
			)
		);
	}

	function get_cached_selected_options_base( $cache_key, $pre_name ) {
		$session_cache = ic_session_cache();
		$cached = $session_cache->get_group( 'ic_cart_shipping_options_cache', sanitize_key( $pre_name ) );
		if ( empty( $cached ) || ! is_array( $cached ) ) {
			return null;
		}
		if ( empty( $cached['key'] ) || $cached['key'] !== $cache_key || ! isset( $cached['data'] ) || ! is_array( $cached['data'] ) ) {
			return null;
		}
		if ( function_exists( 'ic_shipping_checkout_options_cache_valid' ) && ! ic_shipping_checkout_options_cache_valid( $cached['data'] ) ) {
			return null;
		}

		return $cached['data'];
	}

	function cache_selected_options_base( $cache_key, $pre_name, $checkout_shipping ) {
		$session_cache = ic_session_cache();
		$session_cache->set_group(
			'ic_cart_shipping_options_cache',
			sanitize_key( $pre_name ),
			array(
			'key'  => $cache_key,
			'data' => $checkout_shipping,
			)
		);
	}

	/**
	 * Returns the saved shipping-selection session key for one cart prefix.
	 *
	 * @param string $pre_name Prefix for the checkout/cart fields.
	 *
	 * @return string
	 */
	function selection_session_key( $pre_name ) {
		return sanitize_key( $pre_name . 'selected_shipping_options' );
	}

	/**
	 * Persists selected shipping values in the catalog session.
	 *
	 * @param array  $selected_values Selected shipping values keyed by field name.
	 * @param string $pre_name Prefix for the checkout/cart fields.
	 *
	 * @return void
	 */
	function remember_selected_values( $selected_values, $pre_name ) {
		$session_cache = ic_session_cache();
		$key           = $this->selection_session_key( $pre_name );
		if ( empty( $selected_values ) ) {
			$session_cache->delete( $key );
		} else {
			$session_cache->set( $key, $selected_values );
		}
	}

	/**
	 * Returns normalized selected shipping values from the provided request data.
	 *
	 * @param array  $shipping_options Current checkout shipping options.
	 * @param string $pre_name Prefix for the checkout/cart fields.
	 * @param array  $request_data Request data to inspect.
	 *
	 * @return array
	 */
	function selected_values_from_request( $shipping_options, $pre_name, $request_data = array() ) {
		$session_cache   = ic_session_cache();
		$session         = $session_cache->all();
		$key             = $this->selection_session_key( $pre_name );
		$saved_values    = ! empty( $session[ $key ] ) && is_array( $session[ $key ] ) ? $session[ $key ] : array();
		$selected_values = array();
		$shipping_num    = count( $shipping_options );
		$a               = 0;

		foreach ( $shipping_options as $shipping_option ) {
			if ( ! is_array( $shipping_option ) || empty( $shipping_option['options'] ) || ! is_array( $shipping_option['options'] ) ) {
				continue;
			}

			$name      = $shipping_num > 1 ? 'shipping_' . $a : 'shipping';
			$available = array();
			foreach ( $shipping_option['options'] as $option ) {
				if ( empty( $option['name'] ) ) {
					continue;
				}
				$available[] = sanitize_title( $option['name'] );
			}

			$selected = '';
			if ( isset( $request_data[ $name ] ) ) {
				$selected = sanitize_text_field( wp_unslash( $request_data[ $name ] ) );
			} elseif ( ! empty( $saved_values[ $name ] ) ) {
				$selected = sanitize_text_field( $saved_values[ $name ] );
			}

			if ( ! in_array( $selected, $available, true ) && ! empty( $available[0] ) ) {
				$selected = $available[0];
			}

			if ( ! empty( $selected ) ) {
				$selected_values[ $name ] = $selected;
			}
			$a += 1;
		}

		$this->remember_selected_values( $selected_values, $pre_name );

		return $selected_values;
	}

	/**
	 * Returns selected shipping values using POST first and saved session state second.
	 *
	 * @param array  $shipping_options Current checkout shipping options.
	 * @param string $pre_name Prefix for the checkout/cart fields.
	 *
	 * @return array
	 */
	function selected_values( $shipping_options, $pre_name ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Checkout shipping radios are handled by the surrounding form flow.
		$selected_values = $this->selected_values_from_request( $shipping_options, $pre_name, $_POST );
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended

		return $selected_values;
	}

	function remove_empty_labels( $shipping_labels ) {
		foreach ( $shipping_labels as $cart_id => $labels ) {
			foreach ( $labels['labels'] as $key => $in_labels ) {
				if ( $labels['prices'][ $key ] === '' ) {
					unset( $shipping_labels[ $cart_id ]['labels'][ $key ] );
					unset( $shipping_labels[ $cart_id ]['prices'][ $key ] );
				}
			}
		}

		return $shipping_labels;
	}

	/**
	 * Transforms shipping labels directly to checkout shipping array
	 *
	 * @param array $shipping_labels
	 *
	 * @return array
	 */
	function front_labels( $shipping_labels ) {
		$shipping = $shipping_labels;
		if ( ! isset( $shipping_labels[1] ) ) {
			$shipping = array();
			$i        = 0;
			foreach ( $shipping_labels as $cart_id => $ship ) {
				$product_id   = cart_id_to_product_id( $cart_id );
				$product_name = apply_filters( 'cart_email_product_name', get_product_name( $product_id ), $product_id, $cart_id );
				foreach ( $ship['labels'] as $i => $name ) {
					if ( ! empty( $name ) ) {
						$shipping[ $cart_id ]['product_ids']      = $product_id;
						$shipping[ $cart_id ]['product_names']    = $product_name;
						$shipping[ $cart_id ]['options'][ $name ] = array(
							'name'  => $name,
							'price' => $ship['prices'][ $i ],
							'note'  => get_shipping_note( $i, $product_id ),
							'slot'  => intval( $i ),
						);
					}
				}
			}
		}

		return $shipping;
	}

	/**
	 * Transforms cart products array to product shipping labels array
	 *
	 * @param type $products_array
	 *
	 * @return type
	 */
	function cart_products_shipping_labels( $products_array ) {
		$product_shipping = array();
		foreach ( $products_array as $cart_id => $qty ) {
			$product_id = cart_id_to_product_id( $cart_id );
			unset( $qty );

			$cached_shipping = $this->get_cached_product_shipping_data( $cart_id );
			if ( ! empty( $cached_shipping['labels'] ) && is_array( $cached_shipping['labels'] ) && ! empty( $cached_shipping['prices'] ) && is_array( $cached_shipping['prices'] ) ) {
				$product_shipping[ $cart_id ] = $cached_shipping;
				continue;
			}

			$selected = get_variation_value_from_cart_id( $cart_id );
			$labels   = apply_filters( 'checkout_shipping_labels', get_shipping_labels( $product_id ), $product_id, $cart_id );
			if ( empty( $labels ) ) {
				continue;
			}

			$prices = apply_filters( 'checkout_shipping_prices', get_variations_shipping_modificators( $product_id, $selected, null, false ), $labels, $product_id, $cart_id );
			if ( ! empty( $prices ) ) {
				$product_shipping[ $cart_id ] = array(
					'labels' => $labels,
					'prices' => $prices,
				);
				$this->cache_product_shipping_data( $cart_id, $product_shipping[ $cart_id ] );
			}
		}

		return $product_shipping;
	}

	function get_cached_product_shipping_data( $cart_id ) {
		$session_cache = ic_session_cache();
		$cached = $session_cache->get_group( 'ic_cart_shipping_product_cache', $this->product_shipping_cache_key( $cart_id ), array() );
		if ( empty( $cached ) || ! is_array( $cached ) ) {
			return array();
		}

		return $cached;
	}

	function cache_product_shipping_data( $cart_id, $shipping_data ) {
		$session_cache = ic_session_cache();
		if ( empty( $shipping_data['labels'] ) || empty( $shipping_data['prices'] ) ) {
			return;
		}
		$session_cache->set_group( 'ic_cart_shipping_product_cache', $this->product_shipping_cache_key( $cart_id ), $shipping_data );
	}

	function product_shipping_cache_key( $cart_id ) {
		return md5(
			wp_json_encode(
				array(
					'cart_id'        => (string) $cart_id,
					'shipping_mode'  => $this->settings()['mode'],
					'shipping_count' => intval( get_shipping_options_number() ),
					'source_mode'    => function_exists( 'get_shipping_source_mode' ) ? get_shipping_source_mode() : '',
					'schema_version' => function_exists( 'ic_shipping_checkout_cache_schema' ) ? ic_shipping_checkout_cache_schema() : 'shipping_address_slots_v2',
					'cache_version'  => function_exists( 'ic_shipping_checkout_cache_version' ) ? ic_shipping_checkout_cache_version() : '',
				)
			)
		);
	}

	/**
	 * Transforms shipping labels to current shipping options with multiple groups
	 *
	 * @param array $shipping_labels
	 * @param array $current_options
	 *
	 * @return array
	 */
	function labels_to_options( $shipping_labels, $current_options ) {
		$option_added  = array();
		$product_added = array();
		if ( ! is_array( $current_options[0] ) ) {
			$current_options = array( 0 => $current_options );
		}
		$price = array();
		foreach ( $current_options as $x => $options ) {
			foreach ( $shipping_labels as $cart_id => $option ) {
				$product_id   = cart_id_to_product_id( $cart_id );
				$product_name = apply_filters( 'cart_email_product_name', get_product_name( $product_id ), $product_id, $cart_id );
				foreach ( $option['labels'] as $i => $label ) {
					if ( array_search( $label, $options ) !== false ) {
						unset( $shipping_labels[ $cart_id ] );
						if ( array_search( $product_name, $product_added ) === false ) {
							$product_added[] = $product_name;
							if ( ! empty( $shipping_options[ $x ]['product_names'] ) ) {
								$shipping_options[ $x ]['product_names'] .= ', ' . $product_name;
							} else {
								$shipping_options[ $x ]['product_names'] = $product_name;
							}
						}
						if ( array_search( $label, $option_added ) === false ) {
							$option_added[]                                      = $label;
							$shipping_options[ $x ]['options'][ $label ]['name'] = $label;
							$shipping_options[ $x ]['options'][ $label ]['note'] = get_shipping_note( $i, $product_id );
							$shipping_options[ $x ]['options'][ $label ]['slot'] = intval( $i );
						} elseif ( empty( $shipping_options[ $x ]['options'][ $label ]['note'] ) ) {
							$shipping_options[ $x ]['options'][ $label ]['note'] = get_shipping_note( $i, $product_id );
						} elseif ( empty( $shipping_options[ $x ]['options'][ $label ]['slot'] ) ) {
							$shipping_options[ $x ]['options'][ $label ]['slot'] = intval( $i );
						}
						$price[ $label ] = isset( $price[ $label ] ) ? $price[ $label ] : 0;
						if ( $this->individual() ) {
							$price[ $label ] += apply_filters( 'individual_cart_shipping_addition', $option['prices'][ $i ], $label );
						} else {
							$price[ $label ] = $option['prices'][ $i ] > $price[ $label ] ? $option['prices'][ $i ] : $price[ $label ];
						}
						$shipping_options[ $x ]['options'][ $label ]['price'] = $price[ $label ];
					}
				}
			}
		}
		if ( ! empty( $shipping_labels ) ) {
			$not_unique = $this->cart_get_most_appearing_shipping( $shipping_labels );
			$next_key   = max( array_keys( $shipping_options ) ) + 1;
			if ( ! empty( $not_unique ) ) {
				$current_options               = $this->array_get_max_value_keys( $not_unique );
				$shipping_options[ $next_key ] = $this->labels_to_options( $shipping_labels, $current_options );
			} else {
				$shippings = $this->front_labels( $shipping_labels );
				foreach ( $shippings as $shipping ) {
					$shipping_options[ $next_key ] = $shipping;
					$next_key                     += 1;
				}
			}
		}

		return $shipping_options;
	}

	/**
	 * Returns most appearing shipping label from given products
	 *
	 * @param type $products_array
	 *
	 * @return type
	 */
	function cart_get_most_appearing_shipping( $products_array ) {
		$shipping_labels_a = array();
		foreach ( $products_array as $cart_id => $shipping_labels ) {
			$shipping_labels_a = array_merge( $shipping_labels_a, $shipping_labels['labels'] );
		}
		$not_unique = array_count_values( $this->array_not_unique( array_filter( $shipping_labels_a ) ) );

		return $not_unique;
	}

	/**
	 * Returns most appearing same values keys
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	function array_get_max_value_keys( $array ) {
		$same_options = array();
		if ( ! empty( $array ) ) {
			$max = max( $array );
			foreach ( $array as $key => $value ) {
				if ( $value >= $max ) {
					$same_options[] = $key;
				}
			}
		}

		return $same_options;
	}

	/**
	 * Returns duplicate values from array
	 *
	 * @param type $raw_array
	 *
	 * @return type
	 */
	function array_not_unique( $raw_array ) {
		$dupes = array();
		natcasesort( $raw_array );
		reset( $raw_array );

		$old_key   = null;
		$old_value = null;
		foreach ( $raw_array as $key => $value ) {
			if ( $value === null ) {
				continue;
			}
			if ( $old_value !== null && strcasecmp( $old_value, $value ) === 0 ) {
				$dupes[ $old_key ] = $old_value;
				$dupes[ $key ]     = $value;
			}
			$old_value = $value;
			$old_key   = $key;
		}

		return $dupes;
	}

	/**
	 * Adds shipping options to checkout form
	 *
	 * @param string $content
	 * @param string $pre_name
	 *
	 * @return string
	 */
	function checkout_options_html( $content, $pre_name ) {
		if ( $pre_name == 'order_form_' || $pre_name == 'cart_' ) {
			$shipping_options = apply_filters( 'checkout_shipping_options', '', $pre_name );
			if ( ! empty( $shipping_options ) ) {
				$content .= '<div class="form_section shipping-options-section" data-ic_shipping_pre_name="' . esc_attr( $pre_name ) . '">';
				$content .= '<div class="order_form_row row section_break"><h5 class="section-break"><strong>' . __( 'SHIPPING', 'ecommerce-product-catalog' ) . '</strong></h5></div>';
				$content .= $shipping_options;
				$content .= '</div>';
			}
		}

		return $content;
	}

	/**
	 * Adds custom payment options to checkout form
	 *
	 * @param type $content
	 * @param type $pre_name
	 *
	 * @return string
	 */
	function add_current( $content = '', $pre_name = 'cart_' ) {
		$settings = $this->settings();
		if ( $settings['mode'] == 'none' ) {
			return $content;
		}
		$shipping               = '';
		$shipping_options       = $this->selected_options( $pre_name );
		$selected_values        = $this->selected_values( $shipping_options, $pre_name );
		$first_shipping_options = reset( $shipping_options );
		if ( is_array( $shipping_options ) && ( count( $shipping_options ) > 1 || ( ( is_array( $first_shipping_options ) && is_array( $first_shipping_options['options'] ) ) && count( $first_shipping_options['options'] ) > 1 ) ) ) {
			$a            = 0;
			$shipping_num = count( $shipping_options );
			$name         = 'shipping';
			foreach ( $shipping_options as $key => $shipping_option ) {
				unset( $selected );
				if ( is_array( $shipping_option ) && isset( $shipping_option['options'] ) ) {

					$shipping .= '<div class="order_form_row row shipping-options">';
					$shipping .= '<div class="label">';
					if ( isset( $shipping_option['product_names'] ) && $shipping_num > 1 ) {
						$shipping .= $shipping_option['product_names'];
					}
					$shipping .= '</div>';
					$shipping .= '<div class="field">';
					if ( $shipping_num > 1 ) {
						$name = 'shipping_' . $a;
					}
					$selected     = isset( $selected_values[ $name ] ) ? $selected_values[ $name ] : '';
					$option_index = 0;
					foreach ( $shipping_option['options'] as $option ) {
						if ( $option['price'] === '' ) {
							continue;
						}
						$esc_name  = sanitize_title( $option['name'] );
						$input_id  = sanitize_html_class( $name . '-' . $esc_name . '-' . $option_index );
						$shipping .= '<div class="shipping-option-choice"><input id="' . esc_attr( $input_id ) . '" type="radio" value="' . esc_attr( $esc_name ) . '" data-price_effect="' . esc_attr( $option['price'] ) . '" data-ic_shipping_pre_name="' . esc_attr( $pre_name ) . '" name="' . esc_attr( $name ) . '" ' . checked( $esc_name, $selected, 0 ) . ' /> ';
						$shipping .= '<label for="' . esc_attr( $input_id ) . '"><span>' . esc_html( $option['name'] ) . '</span> <span>(' . ic_shipping_price_format( $option['price'] ) . ')</span></label>';
						if ( ! empty( $option['note'] ) ) {
							$shipping .= '<div class="shipping-option-note">' . esc_html( $option['note'] ) . '</div>';
						}
						$shipping .= '</div>';
						++$option_index;
					}
					if ( class_exists( 'IC_Shipping_Address_Form' ) ) {
						$shipping .= IC_Shipping_Address_Form::group_html( $shipping_option, $a, $name, $selected, $pre_name );
					}
					$shipping .= '</div>';
					$shipping .= '</div>';
					$a        += 1;
				}
			}
		} elseif ( is_array( $first_shipping_options ) && ! empty( $first_shipping_options['options'] ) && count( $first_shipping_options['options'] ) === 1 ) {
			$first_option = reset( $first_shipping_options['options'] );
			$has_shipping_form = class_exists( 'IC_Shipping_Address_Form' ) && IC_Shipping_Address_Form::option_has_form( $first_option, $pre_name );
			if ( ! empty( $first_option['note'] ) || $has_shipping_form ) {
				$input_id  = sanitize_html_class( 'shipping-' . sanitize_title( $first_option['name'] ) . '-single' );
				$shipping .= '<div class="order_form_row row shipping-options">';
				$shipping .= '<div class="label"></div>';
				$shipping .= '<div class="field">';
				$shipping .= '<div class="shipping-option-choice shipping-option-choice-single">';
				$shipping .= '<input id="' . esc_attr( $input_id ) . '" type="radio" value="' . esc_attr( sanitize_title( $first_option['name'] ) ) . '" data-price_effect="' . esc_attr( $first_option['price'] ) . '" data-ic_shipping_pre_name="' . esc_attr( $pre_name ) . '" name="shipping" checked style="display:none;" />';
				$shipping .= '<label for="' . esc_attr( $input_id ) . '"><span>' . esc_html( $first_option['name'] ) . '</span> <span>(' . ic_shipping_price_format( $first_option['price'] ) . ')</span></label>';
				if ( ! empty( $first_option['note'] ) ) {
					$shipping .= '<div class="shipping-option-note shipping-option-note-visible">' . esc_html( $first_option['note'] ) . '</div>';
				}
				$shipping .= '</div>';
				if ( class_exists( 'IC_Shipping_Address_Form' ) ) {
					$shipping .= IC_Shipping_Address_Form::group_html( $first_shipping_options, 0, 'shipping', sanitize_title( $first_option['name'] ), $pre_name );
				}
				$shipping .= '</div>';
				$shipping .= '</div>';
			}
		}

		return $content . $shipping;
	}

	/**
	 * Adds selected shipping cost to order handling
	 *
	 * @param type $handling
	 *
	 * @return type
	 */
	function selected_cost( $handling, $pre_name ) {
		$shipping_options = $this->selected_options( $pre_name );
		$selected_values  = $this->selected_values( $shipping_options, $pre_name );
		$shipping_num     = count( $shipping_options );
		$a                = 0;
		foreach ( $shipping_options as $shipping_option ) {
			if ( is_array( $shipping_option ) && isset( $shipping_option['options'] ) ) {
				$name     = $shipping_num > 1 ? 'shipping_' . $a : 'shipping';
				$selected = isset( $selected_values[ $name ] ) ? $selected_values[ $name ] : '';
				foreach ( $shipping_option['options'] as $option ) {
					$esc_name = sanitize_title( $option['name'] );
					if ( $selected == $esc_name ) {
						$handling += $option['price'];
						break;
					}
				}
				$a += 1;
			}
		}

		return $handling;
	}

	/**
	 * Returns selected order shipping labels
	 *
	 * @param type $pre_name
	 *
	 * @return type
	 */
	function order_labels( $pre_name ) {
		$labels           = '';
		$shipping_options = $this->selected_options( $pre_name );
		$selected_values  = $this->selected_values( $shipping_options, $pre_name );
		$shipping_num     = count( $shipping_options );
		$a                = 0;
		foreach ( $shipping_options as $shipping_option ) {
			if ( is_array( $shipping_option ) && isset( $shipping_option['options'] ) ) {
				$name = $shipping_num > 1 ? 'shipping_' . $a : 'shipping';
				if ( ! empty( $selected_values[ $name ] ) ) {
					if ( ! empty( $labels ) ) {
						$labels .= ', ';
					}
					$labels .= sanitize_text_field( $selected_values[ $name ] );
				}
				$a += 1;
			}
		}

		return $labels;
	}

	function shipping_total_table_row( $products_table, $price, $cart ) {
		$settings = $this->settings();
		if ( $settings['mode'] == 'none' ) {
			return $products_table;
		}
		if ( $price ) {
			$shipping_options = $this->selected_options( 'cart_' );
			$first_option     = reset( $shipping_options );
			if ( is_array( $shipping_options ) && ( is_array( $first_option ) && is_array( $first_option['options'] ) ) && ( count( $shipping_options ) <= 1 && count( $first_option['options'] ) <= 1 ) ) {
				$products_table        .= '<tr class="order-checkout-shipping">';
				$def_colspan            = 3;
				$shopping_cart_settings = get_shopping_cart_settings();
				if ( $shopping_cart_settings['cart_page_template'] == 'no_qty' ) {
					$def_colspan = 1;
				}
				if ( function_exists( 'is_ic_sku_enabled' ) && is_ic_sku_enabled() ) {
					++$def_colspan;
				}
				$products_table .= '<td style="text-align:right" colspan="' . apply_filters( 'ic_cart_checkout_table_colspan', $def_colspan ) . '">';
				$products_table .= __( 'Shipping', 'ecommerce-product-catalog' );
				$products_table .= '</td>';
				$first_option    = reset( $first_option['options'] );
				$products_table .= '<td style="text-align:right">';
				if ( ! empty( $first_option['price'] ) ) {
					$products_table .= '+';
				}
				$products_table .= ic_shipping_price_format( $first_option['price'], 1, 0, 0 );
				$products_table .= '<input type="radio" style="display: none;" value="' . sanitize_title( $first_option['name'] ) . '" data-price_effect="' . $first_option['price'] . '" data-ic_shipping_pre_name="cart_" name="shipping" checked />';
				$products_table .= '</td>';
				$products_table .= '</tr>';
			}
		}

		return $products_table;
	}

	function shipping_address( $message, $pre_name ) {
		if ( ! ic_string_contains( $message, '[shipping_address]' ) ) {
			return $message;
		}
		$shipping_address = apply_filters( 'ic_notification_shipping_address', '', $pre_name );
		if ( ! empty( $shipping_address ) ) {
			$p                = ic_email_paragraph( 'margin-bottom:0px;font-weight:bold;' );
			$ep               = ic_email_paragraph_end();
			$shipping_address = $p . __( 'Delivery:', 'ecommerce-product-catalog' ) . $ep . $shipping_address;
		}

		return str_replace( '[shipping_address]', $shipping_address, $message );
	}
}

global $ic_cart_shipping;
$ic_cart_shipping = new ic_cart_shipping();

/**
 * Adds selected shipping cost to order handling
 *
 * @param type $handling
 *
 * @return type
 */
function ic_count_shipping_cost_payment( $handling, $pre_name ) {
	global $ic_cart_shipping;

	return $ic_cart_shipping->selected_cost( $handling, $pre_name );
}

if ( ! function_exists( 'get_current_checkout_shipping_options' ) ) {

	/**
	 * Returns current checkout shipping options array
	 *
	 * @param string $pre_name
	 *
	 * @return array
	 */
	function get_current_checkout_shipping_options( $pre_name ) {
		global $ic_cart_shipping;

		return $ic_cart_shipping->selected_options( $pre_name );
	}

}

/**
 * Returns selected order shipping labels
 *
 * @param type $pre_name
 *
 * @return type
 */
function ic_get_order_shipping_labels( $pre_name ) {
	global $ic_cart_shipping;

	return $ic_cart_shipping->order_labels( $pre_name );
}
