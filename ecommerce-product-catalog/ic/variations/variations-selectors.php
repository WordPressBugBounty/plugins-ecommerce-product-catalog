<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages framework folder
 *
 * Here framework folder files defined and managed.
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog-pro/framework
 * @author        Norbert Dreszer
 */
class ic_cart_variations_selectors {

	public $product_id;
	public $cart_id;
	public $checkbox_separator;
	public $checkbox_separator_visible;
	public $separate_qty;

	function __construct( $product_id = null, $cart_id = null ) {
		$product_variations_settings      = get_product_variations_settings();
		$this->product_id                 = $product_id;
		$this->cart_id                    = $cart_id;
		$this->checkbox_separator         = '>>>';
		$this->checkbox_separator_visible = ', ';
		if ( ! empty( $product_variations_settings['quantity'] ) ) {
			$this->separate_qty = 1;
		} else {
			$this->separate_qty = 0;
		}
		add_filter( 'ic_variations_script_localize', array( $this, 'script_localize' ) );
		add_filter( 'ic_selected_variations', array( $this, 'checkbox_selected' ) );
		add_filter( 'ic_selected_variation_quantities', array( $this, 'checkbox_selected' ) );
		add_filter( 'ic_cart_summary_variation', array( $this, 'checkbox_summary_info' ), 10, 7 );
		// add_filter( 'ic_catalog_notification_variation_multiple', array( $this, 'notification_info' ), 10, 4 );
		add_filter( 'ic_catalog_notification_variation', array( $this, 'notification_info' ), 11, 4 );
		add_filter( 'ic_selected_variation_compare', array( $this, 'explode_checkbox_value' ) );
		// add_filter( 'ic_variation_lp_from_cart_id', array( $this, 'explode_checkbox_value' ) );
		// add_filter( 'ic_variation_lps_for_value_from_cart_id', array( $this, 'explode_checkbox_lp' ) );
		add_filter( 'ic_variation_lps_for_price_mod', array( $this, 'explode_checkbox_value' ) );
		add_filter( 'ic_variations_excluded_check_lp', array( $this, 'explode_checkbox_value' ) );
		add_filter( 'ic_variation_empty_value_from_cart_id', array( $this, 'handle_empty_value_from_cart_id' ), 10, 3 );
		add_filter( 'ic_orders_export_var_value', array( $this, 'checkbox_visible_value' ) );
		add_filter( 'ic_variation_value_in_admin_order', array( $this, 'checkbox_visible_value' ) );
		add_filter( 'ic_variations_default_qty_html', array( $this, 'checkbox_default_qty' ), 10, 3 );
	}

	function checkbox_default_qty( $qty, $product_id, $var_id ) {
		$type = ic_cat_get_variation_type( $product_id, $var_id );
		if ( $type !== 'checkbox' ) {
			return $qty;
		}
		if ( ic_string_contains( $qty, $this->checkbox_separator ) ) {
			return $qty;
		}
		$variation_values = get_product_variations_values( $product_id, true );
		$i                = $var_id - 1;
		if ( ! empty( $variation_values[ $i ] ) && is_array( $variation_values[ $i ] ) ) {
			$new_qty = '';
			foreach ( $variation_values[ $i ] as $var_value ) {
				$new_qty .= $qty . $this->checkbox_separator;
			}
			$qty = $new_qty;
		}

		return $qty;
	}

	function notification_info( $html, $variations, $label, $equal_sign ) {
		if ( ! ic_string_contains( $variations, $this->checkbox_separator ) ) {
			return $html;
		}

		// $variations = rtrim( $variations, $this->checkbox_separator );
		if ( ! empty( $this->separate_qty ) ) {
			$values           = $this->explode_checkbox_value( $variations );
			$new_summary_info = '';
			foreach ( $values as $value ) {
				if ( empty( $value ) ) {
					continue;
				}
				if ( ! empty( $new_summary_info ) ) {
					$new_summary_info .= '<br>';
				}
				if ( ! ic_string_contains( $value, $equal_sign ) ) {
					$new_summary_info .= $label . $equal_sign;
				}
				$new_summary_info .= $value;
			}
			$html = $new_summary_info;
		} else {
			$html = $this->checkbox_visible_value( $html );
		}

		return $html;
	}

	function checkbox_summary_info(
		$summary_info,
		$i,
		$cart_id,
		$cart_id_non_var,
		$variation_value,
		$variation_label,
		$equal_sign
	) {
		if ( ! ic_string_contains( $variation_value, $this->checkbox_separator ) ) {
			return $summary_info;
		}
		if ( ! empty( $this->separate_qty ) ) {
			$new_summary_info = '<input name="' . $i . '_variation_' . $cart_id_non_var . '" type="hidden" hidden value="' . sanitize_title( $variation_value ) . '" />';
			$values           = $this->explode_checkbox_value( $variation_value );
			foreach ( $values as $value ) {
				$new_summary_info .= '<span class="chosen_variation">' . $variation_label . $equal_sign . $value . '</span>';
			}
			$summary_info = $new_summary_info;
		} else {
			$summary_info = $this->checkbox_visible_value( str_replace( $this->checkbox_separator . '</', '</', $summary_info ) );
		}

		return $summary_info;
	}

	function checkbox_visible_value( $value ) {
		$value = str_replace( $this->checkbox_separator, $this->checkbox_separator_visible, $value );

		return $value;
	}

	function checkbox_selected( $selected ) {
		if ( is_array( $selected ) ) {
			$selected = array_map( array( $this, 'explode_checkbox_value' ), $selected );
		} else {
			$selected = $this->explode_checkbox_value( $selected );
		}

		return $selected;
	}

	function handle_empty_value_from_cart_id( $value, $var_lps, $variation_values ) {
		if ( ic_string_contains( $var_lps, $this->checkbox_separator ) ) {
			$checkbox_value = $this->explode_checkbox_value( $var_lps );
			if ( is_array( $checkbox_value ) ) {
				$value = '';
				foreach ( $checkbox_value as $var_lp ) {
					if ( ! empty( $value ) ) {
						$value .= $this->checkbox_separator;
					}
					$value .= $variation_values[ $var_lp ];
				}
			}
		}

		return $value;
	}

	function explode_checkbox_value( $value, $force = false ) {
		if ( is_array( $value ) ) {
			$return = array();
			foreach ( $value as $key => $this_value ) {
				$return[ $key ] = $this->explode_checkbox_value( $this_value, $force );
			}

			return $return;
		}
		if ( $force || ic_string_contains( $value, $this->checkbox_separator ) ) {
			$value = ic_filter_empty_array_values(
				explode( $this->checkbox_separator, $value ),
				array(
					$this,
					'sanitize_checkbox_value',
				)
			);
		}

		return $value;
	}

	function sanitize_checkbox_value( $value ) {
		if ( $value === '' ) {
			return false;
		}

		return true;
	}

	function script_localize( $array ) {
		$array['checkbox_separator'] = $this->checkbox_separator;

		return $array;
	}

	function selected( $product_id, $i ) {
		if ( function_exists( 'ic_get_recently_added_product_variation' ) ) {
			$recently_added = ic_get_recently_added_product_variation();
		}
		$selected = isset( $recently_added[ $i . '_variation_' . $product_id ] ) ? $recently_added[ $i . '_variation_' . $product_id ] : '';
		if ( $selected == '' ) {
			$selected = get_variation_lp_from_cart_id( $product_id, $i );
		}

		if ( $selected == '' ) {
			$type = ic_cat_get_variation_type( $product_id, $i );
			if ( $type === 'radio' ) {
				$selected = 0;
			}
		}

		return $selected;
	}

	function selectors() {
		$product_id = $this->product_id;
		$cart_id    = $this->cart_id;
		if ( empty( $cart_id ) ) {
			$cart_id = $product_id;
		}
		$variation_values = get_product_variations_values( $product_id, true );
		$return           = '';
		if ( ! empty( $variation_values ) ) {
			$selectors = '';
			ic_save_global( 'inside_variation_selectors', 1 );
			$prev_type   = '';
			$mixed_types = false;
			foreach ( $variation_values as $i => $values ) {
				$filtered_values = $values;
				if ( is_array( $values ) ) {
					$filtered_values = ic_filter_empty_array_values( $values );
				}
				$var_id = $i + 1;
				$type   = ic_cat_get_variation_type( $product_id, $var_id );
				if ( empty( $filtered_values ) && $type !== 'custom' ) {
					continue;
				}
				if ( ! empty( $prev_type ) && $prev_type !== $type ) {
					$mixed_types = true;
				}
				$selectors .= $this->selector( $product_id, $var_id, $values, $cart_id );
				$prev_type  = $type;
			}
			if ( ! empty( $selectors ) ) {
				$class = 'variations-container';
				if ( $mixed_types ) {
					$class .= ' mixed-variation-types';
				}
				$return  = '<div class="' . $class . '" data-product_id="' . $product_id . '" data-base-price="' . esc_attr( price_format( product_price( $product_id ) ) ) . '">';
				$return .= apply_filters( 'ic_variations_selectors', $selectors, $product_id );
				$return .= '</div>';
			}
			ic_delete_global( 'inside_variation_selectors' );
		}

		return $return;
	}

	function selector( $product_id, $var_id, $var_values, $cart_id = null, $before = null, $after = null ) {
		$type = ic_cat_get_variation_type( $product_id, $var_id );
		if ( $type !== 'custom' ) {
			$var_values = ic_filter_empty_array_values( $var_values );
		}
		$return = '';
		if ( $type === 'table' && is_ic_cart() ) {
			$type = 'dropdown';
		}
		$before = apply_filters( 'ic_variations_selector_before', $before, $product_id, $var_id, $cart_id, $type );
		$after  = apply_filters( 'ic_variations_selector_after', $after, $product_id, $var_id, $cart_id, $type );
		if ( $type === 'dropdown' && ! empty( $var_values ) ) {
			$return .= $this->dropdown( $cart_id, $var_id, $before, $after );
		} elseif ( $type === 'radio' && ! empty( $var_values ) ) {
			$return .= $this->radio( $cart_id, $var_id, $before, $after );
		} elseif ( $type === 'checkbox' && ! empty( $var_values ) ) {
			$return .= $this->checkbox( $cart_id, $var_id, $before, $after );
		} elseif ( $type === 'custom' ) {
			$return .= $before . $this->custom_field( $cart_id, $var_id ) . $after;
		} elseif ( $type === 'table' ) {
			$return .= $before . $this->table( $cart_id, $var_id, $var_values ) . $after;
		} else {
			$return .= $this->dropdown( $cart_id, $var_id, $before, $after );
		}

		// $return .= '<input type="hidden" name="' . esc_attr( $var_id ) . '_variation_lp_' . esc_attr( get_cart_id_without_variations( $cart_id ) ) . '" value="' . get_variation_lp_from_cart_id( $cart_id, $var_id ) . '">';
		return $return;
	}

	function table( $product_id, $var_id, $var_values ) {
		$product_id = empty( $product_id ) ? get_the_ID() : $product_id;
		if ( ! empty( $var_values ) && is_array( $var_values ) ) {
			$table  = '';
			$return = '';
			global $ic_table_variation_headers;
			$ic_table_variation_headers = array();
			$var_name                   = $var_id . '_variation_' . $product_id;
			$var_values                 = apply_filters( 'ic_catalog_var_table_values', $var_values, $product_id );
			foreach ( $var_values as $var_lp => $value ) {
				// $headers = apply_filters( 'ic_variation_table_headers', array(), $product_id, $var_id, $var_lp );
				$details = apply_filters( 'ic_variation_table', '', $product_id, $var_id, $var_lp );
				if ( ! empty( $details ) ) {
					if ( ! isset( $ic_table_variation_headers['button'] ) ) {
						$ic_table_variation_headers['button'] = apply_filters( 'ic_table_variations_button_header', '' );
					}
					$args[ $var_name ] = $var_lp;
					$cart_button       = shopping_cart_button( 0, 0, $args );
					$details          .= '<td class="variation-add">' . apply_filters( 'ic_variation_table_button', $cart_button, $product_id, $args ) . '</td>';
					$table            .= '<tr>' . $details . '</tr>';
				}
			}
			if ( ! empty( $table ) ) {
				$return = '<table class="ic-variation-table" data-product_id="' . $product_id . '">';
				if ( ! empty( $ic_table_variation_headers ) && is_array( $ic_table_variation_headers ) ) {
					$return .= '<tr>';
					foreach ( $ic_table_variation_headers as $name => $header ) {
						$return .= '<th class="var-table-head-' . $name . '">' . $header . '</th>';
					}
					$return .= '</tr>';
				}
				$return .= $table;
				$return .= '</table>';
			}

			return $return;
		}
	}

	function dropdown( $product_id, $variation, $before = null, $after = null ) {
		$product_id = empty( $product_id ) ? get_the_ID() : $product_id;
		if ( function_exists( 'product_price' ) ) {
			$product_price = product_price( cart_id_to_product_id( $product_id ) );
		}
		$variation_values   = get_product_variations_values( $product_id, true );
		$variation_prices   = get_product_variations_prices( $product_id );
		$variation_shipping = get_product_variations_shipping( $product_id );
		$variation_mod_type = get_product_variations_price_mod_type( $product_id );
		$variation_labels   = get_product_variations_labels( $product_id );
		$return             = '';
		$i                  = $variation;
		$var_id             = $i - 1;
		if ( ! empty( $variation_labels[ $var_id ] ) ) {
			if ( ! empty( $variation_values[ $var_id ] ) ) {
				if ( ! is_array( $variation_values[ $var_id ] ) ) {
					$values = explode( "\r\n", $variation_values[ $var_id ] );
				} else {
					$values = $variation_values[ $var_id ];
				}

				$selected    = $this->selected( $product_id, $i );
				$attributes  = $this->selector_attributes( $var_id, $i, $variation_mod_type, $variation_prices, $variation_shipping );
				$is_required = apply_filters( 'ic_variation_is_required', true, $var_id, $product_id );
				if ( $is_required ) {
					$required = ' required="required"';
				} else {
					$required = '';
				}
				$return      .= '<div class="variation-dropdown-container">';
				$return      .= '<p class="dropdown-main-label">' . $variation_labels[ $var_id ] . ':</p>';
				$return      .= $before;
				$return      .= '<div class="variation-dropdown-container-inside">';
				$return      .= '<select' . $this->required( $product_id, $i ) . ' class="variation_select" ' . $attributes . ' data-product_id="' . esc_attr( $product_id ) . '" name="' . esc_attr( $i ) . '_variation_' . esc_attr( get_cart_id_without_variations( $product_id ) ) . '">';
				$return      .= '<option value="">' . __( 'Select', 'ecommerce-product-catalog' ) . ' ' . $variation_labels[ $var_id ] . '</option>';
				$mod          = '';
				$var_prices_a = $variation_prices[ $var_id ];
				foreach ( $values as $key => $value ) {
					if ( ! empty( $value ) ) {
						/*
						if ( !empty( $var_prices_a[ $key ] ) ) {
							$mod = $variation_mod_type[ $i - 1 ] == '+' ? ' (' . price_format( $product_price + $var_prices_a[ $key ] ) . ')' : ' (' . price_format( $product_price * (1 + $var_prices_a[ $key ] / 100) ) . ')';
							} else {
							$mod = ' (' . price_format( $product_price ) . ')';
							}
							$mod = str_replace( '()', '', $mod );
						 *
						 */
						if ( isset( $variation_mod_type[ $var_id ] ) && isset( $var_prices_a[ $key ] ) ) {
							$mod = $this->get_price_effect_dropdown_info( $variation_mod_type[ $var_id ], $var_prices_a[ $key ], $product_price, $product_id );
						}
						// $process_value     = ic_filter_variation_value( $value );
						$attributes = apply_filters( 'ic_variation_dropdown_option_attributes', '', $product_id, $i, $key );
						$return    .= '<option data-var_lp="' . esc_attr( $key ) . '" value="' . esc_attr( $key ) . '" ' . selected( $key, $selected, 0 ) . ' ' . $attributes . '>' . $value . $mod . '</option>';
					}
				}
				$return .= '</select><div class="ic_spinner"></div>';
				$return .= '<br />';
				$return .= '</div>';
				$return .= $after;
				$return .= '</div>';
				// $return    .= '<br>';
			}
		}

		return $return;
	}

	function radio( $product_id, $variation, $before = null, $after = null ) {
		$product_id         = empty( $product_id ) ? get_the_ID() : $product_id;
		$product_price      = product_price( cart_id_to_product_id( $product_id ) );
		$variation_values   = get_product_variations_values( $product_id, true );
		$variation_prices   = get_product_variations_prices( $product_id );
		$variation_shipping = get_product_variations_shipping( $product_id );
		$variation_mod_type = get_product_variations_price_mod_type( $product_id );
		$variation_labels   = get_product_variations_labels( $product_id );
		$return             = '';
		$i                  = $variation;
		$var_id             = $i - 1;
		if ( ! empty( $variation_labels[ $var_id ] ) ) {
			if ( ! empty( $variation_values[ $var_id ] ) ) {
				if ( ! is_array( $variation_values[ $var_id ] ) ) {
					$values = explode( "\r\n", $variation_values[ $var_id ] );
				} else {
					$values = $variation_values[ $var_id ];
				}
				$selected     = $this->selected( $product_id, $i );
				$attributes   = $this->selector_attributes( $var_id, $i, $variation_mod_type, $variation_prices, $variation_shipping );
				$mod          = '';
				$var_prices_a = $variation_prices[ $var_id ];
				$radio_name   = $i . '_variation_' . get_cart_id_without_variations( $product_id );
				$return      .= '<div class="variation-radio-container">';
				// if ( !is_ic_shopping_cart() && !is_ic_ajax( 'shopping_cart_products' ) ) {
				$return .= '<p class="radio-button-main-label">' . $variation_labels[ $var_id ] . ':</p>';
				// }
				$return .= $before;
				$return .= '<div class="variation-radio-container-inside">';
				foreach ( $values as $key => $value ) {
					if ( ! empty( $value ) ) {
						/*
						if ( !empty( $var_prices_a[ $key ] ) ) {
							$mod = $variation_mod_type[ $i - 1 ] == '+' ? ' (' . price_format( $product_price + $var_prices_a[ $key ] ) . ')' : ' (' . price_format( $product_price * (1 + $var_prices_a[ $key ] / 100) ) . ')';
							} else {
							$mod = ' (' . price_format( $product_price ) . ')';
							}
							$mod = str_replace( '()', '', $mod );
						 *
						 */
						if ( isset( $variation_mod_type[ $var_id ] ) && isset( $var_prices_a[ $key ] ) ) {
							$mod = $this->get_price_effect_dropdown_info( $variation_mod_type[ $var_id ], $var_prices_a[ $key ], $product_price, $product_id );
						}
						// $process_value     = ic_filter_variation_value( $value );
						$attributes        = apply_filters( 'ic_variation_dropdown_option_attributes', '', $product_id, $i, $key );
						$id                = 'variation_' . sanitize_title( $product_id ) . sanitize_title( $value );
						$variation_checked = checked( $key, $selected, 0 );
						$container_class   = 'ic-radio-variation';
						if ( ! empty( $variation_checked ) ) {
							$container_class .= ' ic-variation-selected';
						}
						$return .= '<div class="' . $container_class . '">';
						$return .= '<input' . $this->required( $product_id, $i ) . ' id="' . esc_attr( $id ) . '" type="radio" class="variation_select" data-product_id="' . esc_attr( $product_id ) . '" name="' . esc_attr( $radio_name ) . '" data-var_num="' . esc_attr( $i ) . '" data-var_lp="' . esc_attr( $key ) . '" value="' . esc_attr( $key ) . '" ' . $variation_checked . ' ' . $attributes . '><label for="' . esc_attr( $id ) . '">' . $value . $mod . '</label>';
						$return .= '<div class="ic_spinner"></div>';
						$return .= '</div>';
					}
				}
				$return .= '</div>';
				$return .= $after;
				$return .= '</div>';
				$return .= '<br>';
			}
		}

		return $return;
	}

	function checkbox( $product_id, $variation, $before = null, $after = null ) {
		$product_id         = empty( $product_id ) ? get_the_ID() : $product_id;
		$product_price      = product_price( cart_id_to_product_id( $product_id ) );
		$variation_values   = get_product_variations_values( $product_id, true );
		$variation_prices   = get_product_variations_prices( $product_id );
		$variation_shipping = get_product_variations_shipping( $product_id );
		$variation_mod_type = get_product_variations_price_mod_type( $product_id );
		$variation_labels   = get_product_variations_labels( $product_id );
		$return             = '';
		$i                  = $variation;
		$var_id             = $i - 1;
		$var_count          = count( ic_filter_empty_array_values( $variation_values ) );
		if ( ! empty( $variation_labels[ $var_id ] ) ) {
			if ( ! empty( $variation_values[ $var_id ] ) ) {
				if ( ! is_array( $variation_values[ $var_id ] ) ) {
					$values = explode( "\r\n", $variation_values[ $var_id ] );
				} else {
					$values = $variation_values[ $var_id ];
				}
				$selected = $this->explode_checkbox_value( $this->selected( $product_id, $i ), true );
				// $selected        = $this->selected( $product_id, $i );
				$attributes   = $this->selector_attributes( $var_id, $i, $variation_mod_type, $variation_prices, $variation_shipping );
				$mod          = '';
				$var_prices_a = $variation_prices[ $var_id ];
				$radio_name   = $i . '_variation_' . get_cart_id_without_variations( $product_id );
				$return      .= '<div class="variation-checkbox-container">';
				if ( ! is_ic_shopping_cart() || ( is_ic_shopping_cart() && $var_count > 1 ) ) {
					$return .= '<p class="checkbox-main-label">' . $variation_labels[ $var_id ] . ':</p>';
				}
				$return .= $before;
				$return .= '<div class="variation-checkbox-container-inside">';
				foreach ( $values as $key => $value ) {
					if ( ! empty( $value ) ) {
						/*
						if ( !empty( $var_prices_a[ $key ] ) ) {
							$mod = $variation_mod_type[ $i - 1 ] == '+' ? ' (' . price_format( $product_price + $var_prices_a[ $key ] ) . ')' : ' (' . price_format( $product_price * (1 + $var_prices_a[ $key ] / 100) ) . ')';
							} else {
							$mod = ' (' . price_format( $product_price ) . ')';
							}
							$mod = str_replace( '()', '', $mod );
						 *
						 */
						if ( isset( $variation_mod_type[ $var_id ] ) && isset( $var_prices_a[ $key ] ) ) {
							$mod = $this->get_price_effect_dropdown_info( $variation_mod_type[ $var_id ], $var_prices_a[ $key ], $product_price, $product_id, true );
						}
						// $process_value     = ic_filter_variation_value( $value );
						$attributes = apply_filters( 'ic_variation_dropdown_option_attributes', '', $product_id, $i, $key );
						$id         = 'variation_' . sanitize_title( $product_id ) . sanitize_title( $value );
						$return    .= apply_filters( 'ic_variation_checkbox', '<input id="' . esc_attr( $id ) . '" type="checkbox" class="variation_select" data-product_id="' . esc_attr( $product_id ) . '" name="' . esc_attr( $radio_name ) . '[]" data-var_num="' . esc_attr( $i ) . '" data-var_lp="' . esc_attr( $key ) . '" value="' . esc_attr( $key . $this->checkbox_separator ) . '" ' . checked( in_array( $key, $selected ), true, 0 ) . ' ' . $attributes . '><label for="' . esc_attr( $id ) . '">' . $value . $mod . '</label>', $product_id, $i, $key, $mod, $value );
						$return    .= '<div class="ic_spinner"></div>';
						$return    .= '<br />';
					}
				}
				$return .= '</div>';
				$return .= $after;
				$return .= '</div>';
				$return .= '<br>';
			}
		}

		return $return;
	}

	function custom_field( $product_id, $variation ) {
		$product_id         = empty( $product_id ) ? get_the_ID() : $product_id;
		$product_price      = product_price( cart_id_to_product_id( $product_id ) );
		$variation_values   = get_product_variations_values( $product_id, true );
		$variation_prices   = get_product_variations_prices( $product_id );
		$variation_shipping = get_product_variations_shipping( $product_id );
		$variation_mod_type = get_product_variations_price_mod_type( $product_id );
		$variation_labels   = get_product_variations_labels( $product_id );
		$return             = '';
		$i                  = $variation;
		$var_id             = $i - 1;
		if ( ! empty( $variation_labels[ $var_id ] ) ) {
			$selected = $this->selected( $product_id, $i );
			$return  .= '<div class="variation-select-container">';
			// $return    .= '<label for="' . $i . '_variation_type">' . $variation_labels[ $i - 1 ] . '</label> ';
			$attributes = $this->selector_attributes( $var_id, $i, $variation_mod_type, $variation_prices, $variation_shipping );
			$return    .= '<input id="' . esc_attr( $i ) . '_variation_type" placeholder="' . esc_attr( $variation_labels[ $var_id ] ) . '" type="text" class="variation_select" ' . esc_attr( $attributes ) . ' data-product_id="' . esc_attr( $product_id ) . '" name="' . esc_attr( $i ) . '_variation_' . esc_attr( get_cart_id_without_variations( $product_id ) ) . '" value="' . esc_attr( $selected ) . '">';
			$return    .= '<div class="ic_spinner"></div><br>';
			$return    .= '</div>';
		}

		return $return;
	}

	function required( $product_id, $var_id ) {
		$is_required = apply_filters( 'ic_variation_is_required', true, $var_id, $product_id );
		if ( $is_required ) {
			$required = ' required="required"';
		} else {
			$required = '';
		}

		return $required;
	}

	function selector_attributes( $var_id, $var_num, $variation_mod_type, $variation_prices, $variation_shipping ) {
		$attributes = 'data-var_num="' . $var_num . '"';
		if ( ! empty( $variation_mod_type[ $var_id ] ) ) {
			$attributes .= ' data-mod-type="' . $variation_mod_type[ $var_id ] . '"';
		}
		if ( ! empty( $variation_prices[ $var_id ] ) && is_array( $variation_prices[ $var_id ] ) ) {
			$filtered_prices = ic_filter_empty_array_values( $variation_prices[ $var_id ] );
			if ( ! empty( $filtered_prices ) ) {
				$attributes .= ' data-mod-prices="' . encode_variation_mod( $variation_prices[ $var_id ] ) . '"';
			}
		}
		if ( ! empty( $variation_shipping[ $var_id ] ) && is_array( $variation_shipping[ $var_id ] ) ) {
			$filtered_shipping = ic_filter_empty_array_values( $variation_shipping[ $var_id ] );
			if ( ! empty( $filtered_shipping ) ) {
				$attributes .= ' data-mod-shipping="' . encode_variation_mod( $variation_shipping[ $var_id ] ) . '"';
			}
		}

		return $attributes;
	}

	/**
	 * Defines price effect dropdown info
	 *
	 * @param type $mod_type
	 * @param type $price_effect
	 * @param type $product_price
	 * @param type $product_id
	 *
	 * @return string
	 */
	function get_price_effect_dropdown_info(
		$mod_type,
		$price_effect,
		$product_price,
		$product_id,
		$force_price_effect = false
	) {
		$cache_meta = 'variation_price_effect_info_' . $mod_type . $price_effect . $product_price . $product_id;
		$mod        = ic_get_global( $cache_meta );
		if ( $mod === false ) {
			$mod = apply_filters( 'ic_get_price_effect_dropdown_info', $mod, $mod_type, $price_effect, $product_price, $product_id );
			if ( $mod === false ) {
				$settings = get_product_variations_settings();
				if ( ! empty( $price_effect ) && $settings['info'] != 'no-info' && ic_is_variations_price_effect_active() ) {
					if ( ic_has_multiple_product_variations( $product_id ) || $settings['info'] == 'price-effect' || $force_price_effect ) {
						if ( $mod_type == '=' ) {
							$mod = ' (' . price_format( $price_effect ) . ')';
						} else {
							$sign = $price_effect < 0 ? '-' : '+';
							$mod  = $mod_type == '+' ? ' (' . $sign . ' ' . price_format( abs( $price_effect ) ) . ')' : ' (' . $sign . ' ' . abs( $price_effect ) . '%)';
						}
					} else {
						if ( empty( $product_price ) ) {
							$product_price = 0;
						}
						if ( $mod_type === '+' ) {
							$modified_price = $product_price + $price_effect;
						} elseif ( $mod_type === '%' ) {
							$modified_price = $product_price * ( 1 + $price_effect / 100 );
						} else {
							$modified_price = $price_effect;
						}
						$mod = ' (' . price_format( $modified_price ) . ')';
					}
				} elseif ( ! $force_price_effect && is_ic_any_variation_price_effect( $product_id ) && ! ic_has_multiple_product_variations( $product_id ) && $settings['info'] != 'price-effect' && $settings['info'] != 'no-info' && ic_is_variations_price_effect_active() ) {
					$mod = ' (' . price_format( $product_price ) . ')';
					$mod = str_replace( '()', '', $mod );
				} else {
					$mod = '';
				}
			}
			ic_save_global( $cache_meta, $mod );
		}

		return $mod;
	}

	function first_variation_value( $product_id, $var_id ) {

		$variation_values = get_product_variations_values( $product_id, true );
		if ( ! empty( $variation_values[ $var_id ] ) && is_array( $variation_values[ $var_id ] ) ) {
			$filtered_values = ic_filter_empty_array_values( $variation_values[ $var_id ] );
			if ( isset( $filtered_values[0] ) ) {
				return $filtered_values[0];
			}
		}

		return '';
	}
}

$ic_cart_variations_selectors = new ic_cart_variations_selectors();
