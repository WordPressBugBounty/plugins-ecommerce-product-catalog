<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Calculates and returns the variation price modifiers based on selected values.
 *
 * @param int|null          $cart_id Cart ID to retrieve product information. If null, the current product ID is used.
 * @param string|array|null $selected Selected variation options for the product. Defaults to null.
 * @param int|null          $variation_id The variation ID to process when $selected is not an array.
 * @param bool              $format Whether to format the final result, intended for front-end display. Default is true.
 * @param float|null        $price_value Base price of the product. If null, it will retrieve the product price.
 * @param array|null        $var_qties Quantity of selected variations. Defaults to null.
 * @param array             $var_lps Additional variation LP values for price modification. Default is an empty array.
 *
 * @return array|string|float Returns an array of calculated modifiers, a formatted price string, or the raw modified price depending on the arguments.
 */
function get_variations_modificators(
	$cart_id = null,
	$selected = null,
	$variation_id = null,
	$format = true,
	$price_value = null,
	$var_qties = null,
	$var_lps = array()
) {
	$product_id = empty( $cart_id ) ? get_the_ID() : cart_id_to_product_id( $cart_id );
	if ( empty( $cart_id ) ) {
		$cart_id = $product_id;
	} else {
		$var_lps = apply_filters( 'ic_variation_lps_for_price_mod', get_variation_lp_from_cart_id( $cart_id ) );
	}
	if ( empty( $var_lps ) ) {
		$var_lps = $selected;
	}
	$variation_prices = get_product_variations_prices( $product_id );
	$variation_values = get_product_variations_values( $product_id );
	$variation_mod    = get_product_variations_price_mod_type( $product_id );
	// Variation prices and the base price are raw meta strings ('' for a blank textarea
	// line). On PHP 8 an empty string reaching * or / is a fatal TypeError, so every value
	// feeding the arithmetic below is coerced to float where it is read.
	$price_value = floatval( isset( $price_value ) ? $price_value : product_price( $product_id ) );
	$var_qties   = apply_filters( 'ic_selected_variation_quantities', $var_qties );
	if ( empty( $selected ) ) {
		$modificators = array();
		foreach ( $variation_prices as $i => $prices ) {
			$i = $i + 1;
			if ( ! empty( $prices ) ) {
				if ( ! is_array( $prices ) ) {
					$prices = explode( "\r\n", $prices );
				}
				$prices   = array_map( 'floatval', $prices );
				$var_name = $i . '_variation_' . $product_id;
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
				$selected = isset( $_POST[ $var_name ] ) ? ic_sanitize( wp_unslash( $_POST[ $var_name ] ) ) : '';
				if ( $selected == '' ) {
					$selected = get_variation_lp_from_cart_id( $product_id, $i );
				}

				if ( $selected & ! empty( $selected ) ) {
					$selected = apply_filters( 'ic_selected_variations', $selected );
					/*
						if ( !empty( $var_lps ) ) {
						$var_lp = isset( $var_lps[ $var_name ] ) ? $var_lps[ $var_name ] : $var_lps[ $var_name ];
						}
						if ( empty( $var_lp ) ) {
						$var_lp = array();
						foreach ( $variation_values[ $i ] as $z => $value ) {
						if ( is_array( $selected ) && in_array( $value, $selected ) ) {
						$var_lp[] = $z;
						} else {
						if ( $value == $selected ) {
						$var_lp[] = $z;
						break;
						}
						}
						}
						}
					 *
					 */
					if ( ! is_array( $selected ) ) {
						$selected = array( $selected );
					}
					$price = 0;
					foreach ( $selected as $lp ) {
						$this_price = $prices[ $lp ];
						if ( ! empty( $var_qties[ $var_name ] ) ) {
							if ( is_array( $var_qties[ $var_name ] ) && isset( $var_qties[ $var_name ][ $lp ] ) ) {
								$var_qty = intval( $var_qties[ $var_name ][ $lp ] );
							} else {
								$var_qty = intval( $var_qties[ $var_name ] );
							}
							$this_price = $this_price * $var_qty;
						}
						$price += $this_price;
					}
					$modificators[] = $price;
				}
			}
		}

		return $modificators;
	} elseif ( is_array( $selected ) ) {
		$selected    = apply_filters( 'ic_selected_variations', $selected );
		$price_mod   = 0;
		$calc_prices = array();
		foreach ( $selected as $var_name => $var_lp ) {
			$var_id = current( explode( '_', $var_name ) ) - 1;
			$found  = false;
			if ( ! isset( $variation_values[ $var_id ] ) || ! isset( $variation_prices[ $var_id ] ) ) {
				continue;
			}
			if ( ! is_array( $variation_values[ $var_id ] ) ) {
				$values = explode( "\r\n", $variation_values[ $var_id ] );
			} else {
				$values = $variation_values[ $var_id ];
			}
			if ( ! is_array( $variation_prices[ $var_id ] ) ) {
				$prices = explode( "\r\n", $variation_prices[ $var_id ] );
			} else {
				$prices = $variation_prices[ $var_id ];
			}
			$prices = array_map( 'floatval', $prices );

			/*
				if ( !empty( $var_lps ) ) {
				$var_lp = isset( $var_lps[ $var_name ] ) ? $var_lps[ $var_name ] : $var_lps[ $var_name ];
				}
				error_log( $var_lp );
				if ( empty( $var_lp ) ) {
				$var_lp = array();
				foreach ( $values as $z => $value ) {
				if ( is_array( $var_selected ) && in_array( $value, $var_selected ) ) {
				$var_lp[] = $z;
				} else {
				if ( $value == $var_selected ) {
				$var_lp[] = $z;
				break;
				}
				}
				}
				}
			 *
			 */
			if ( ! empty( $var_lp ) || is_numeric( $var_lp ) ) {
				if ( ! is_array( $var_lp ) ) {
					$var_lp = array( $var_lp );
				}
				$this_price = 0;
				$i          = $var_id + 1;
				foreach ( $var_lp as $lp ) {
					if ( ! empty( $prices[ $lp ] ) ) {
						$this_price += $prices[ $lp ];
					}
					$calc_prices[ $i ][ $lp ] = isset( $prices[ $lp ] ) ? $prices[ $lp ] : 0;
				}
				if ( ! empty( $this_price ) ) {
					$this_price = apply_filters( 'ic_variation_mod_calculated', $this_price, $cart_id, $i, $var_qties, $calc_prices );
					if ( $variation_mod[ $var_id ] === '%' ) {
						$price_mod = $price_mod + ( $price_value * ( 1 + ( $this_price / 100 ) ) - $price_value );
					} elseif ( $variation_mod[ $var_id ] === '=' ) {
						$product_variations_settings = get_product_variations_settings();
						if ( $var_id === 0 && empty( $product_variations_settings['quantity'] ) ) {
							$price_mod = $price_mod + ( $this_price - $price_value );
						} else {
							$price_mod = $price_mod + $this_price;
						}
						// $price_mod = $price_mod + $this_price;
					} else {
						$price_mod = $price_mod + $this_price;
					}
				}
			}
		}
		if ( ! empty( $price_mod ) ) {
			$price_mod = apply_filters( 'ic_variation_mod', $price_mod, $product_id );
		}
		$modified_price = apply_filters( 'ic_variation_price', $price_value + $price_mod, $product_id );
		if ( $format ) {
			return price_format( $modified_price );
		} else {
			return $modified_price;
		}
	} elseif ( $selected != 'not_selected' && ! is_array( $selected ) ) {
		$variation_id = $variation_id - 1;
		$found        = false;
		if ( ! is_array( $variation_values[ $variation_id ] ) ) {
			$values = explode( "\r\n", $variation_values[ $variation_id ] );
		} else {
			$values = $variation_values[ $variation_id ];
		}
		if ( ! is_array( $variation_prices[ $variation_id ] ) ) {
			$prices = explode( "\r\n", $variation_prices[ $variation_id ] );
		} else {
			$prices = $variation_prices[ $variation_id ];
		}
		$prices   = array_map( 'floatval', $prices );
		$selected = apply_filters( 'ic_selected_variations', $selected );
		foreach ( $values as $z => $value ) {
			if ( $z == $selected ) {
				$found = true;
				break;
			}
		}
		if ( $found ) {
			$prices[ $z ] = empty( $prices[ $z ] ) ? 0 : $prices[ $z ];
			if ( ! empty( $prices[ $z ] ) ) {
				if ( $variation_mod[ $variation_id ] === '%' ) {
					$price_mod = $price_value * ( $prices[ $z ] / 100 );
				} elseif ( $variation_mod[ $variation_id ] === '=' ) {
					// $price_mod = $prices[ $z ] - $price_value;
					$price_mod = $prices[ $z ];
				} else {
					$price_mod = $prices[ $z ];
				}
				$price_mod   = apply_filters( 'ic_variation_mod', $price_mod, $product_id );
				$price_value = $price_value + $price_mod;
			}
			$modified_price = apply_filters( 'ic_variation_price', $price_value, $product_id );
			if ( $format ) {
				return price_format( $modified_price );
			} else {
				return $modified_price;
			}
		}
	} else {
		$modified_price = apply_filters( 'ic_variation_price', $price_value, $product_id );
		if ( $format ) {
			return price_format( $modified_price );
		} else {
			return $modified_price;
		}
	}
}

function ic_get_variation_lp( $product_id, $variation_value, $var_id ) {
	if ( empty( $product_id ) ) {
		return false;
	}
	$variation_values = get_product_variations_values( $product_id );
	$var_key          = $var_id - 1;
	if ( empty( $variation_values[ $var_key ] ) ) {
		return false;
	}
	$lp = array_search( $variation_value, $variation_values[ $var_key ] );

	return $lp;
}

function ic_get_var_id( $product_id, $label ) {
	$labels = get_product_variations_labels( $product_id );
	$key    = array_search( $label, $labels );
	$var_id = false;
	if ( $key !== false ) {
		$var_id = $key + 1;
	}

	return $var_id;
}

/**
 * Returns variation modified shipping price array
 *
 * @param int          $product_id Product ID (Cart ID will be transformed to Product ID)
 * @param string|array $selected Selected product variations
 * @param int          $variation_id Required if $selected is set and is not array
 * @param boolean      $format If output formatted price for front-end
 * @param array        $shipping_price_a Shipping prices array (it will get it if not provided)
 *
 * @return type
 */
function get_variations_shipping_modificators(
	$product_id = null,
	$selected = null,
	$variation_id = null,
	$format = true,
	$shipping_price_a = null
) {
	$product_id         = empty( $product_id ) ? get_the_ID() : cart_id_to_product_id( $product_id );
	$variation_shipping = get_product_variations_shipping( $product_id );
	$variation_values   = get_product_variations_values( $product_id );
	$variation_mod      = get_product_variations_price_mod_type( $product_id );
	$shipping_price_a   = isset( $shipping_price_a ) ? $shipping_price_a : get_shipping_options( $product_id );
	if ( ! is_array( $shipping_price_a ) ) {
		return 0;
	}
	if ( empty( $selected ) ) {
		$modificators = array();
		foreach ( $variation_shipping as $i => $prices ) {
			$i = $i + 1;
			if ( ! empty( $prices ) ) {
				if ( ! is_array( $prices ) ) {
					$prices = explode( "\r\n", $prices );
				}
				$prices = array_map( 'floatval', $prices );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
				$selected = isset( $_POST[ $i . '_variation_' . $product_id ] ) ? ic_sanitize( wp_unslash( $_POST[ $i . '_variation_' . $product_id ] ) ) : '';
				if ( $selected == '' ) {
					$selected = get_variation_lp_from_cart_id( $product_id, $i );
				}
				if ( $selected && ! empty( $selected ) ) {
					foreach ( $variation_values[ $i ] as $z => $value ) {
						if ( $z == $selected ) {
							break;
						}
					}
					$modificators[] = $prices[ $z ];

					return $modificators;
				}
			}
		}
	} elseif ( is_array( $selected ) ) {
		$price_mod = 0;
		foreach ( $selected as $var_id => $var_selected ) {
			$var_id = current( explode( '_', $var_id ) ) - 1;
			$found  = false;
			if ( ! is_array( $variation_values[ $var_id ] ) ) {
				$values = explode( "\r\n", $variation_values[ $var_id ] );
			} else {
				$values = $variation_values[ $var_id ];
			}
			if ( ! is_array( $variation_shipping[ $var_id ] ) ) {
				$prices = explode( "\r\n", $variation_shipping[ $var_id ] );
			} else {
				$prices = $variation_shipping[ $var_id ];
			}
			if ( ! empty( $var_selected ) ) {
				foreach ( $values as $z => $value ) {
					if ( $z == $var_selected ) {
						$found = true;
						break;
					}
				}
				if ( $found ) {
					$prices[ $z ] = empty( $prices[ $z ] ) ? 0 : $prices[ $z ];
					$price_mod    = get_shipping_price_mod( $shipping_price_a, $prices[ $z ], $variation_mod[ $var_id ], $price_mod );
				}
			}
		}
		$modified_shipping = variation_modify_shipping( $shipping_price_a, $price_mod, $format );

		return $modified_shipping;
	} elseif ( $selected != 'not_selected' && ! is_array( $selected ) ) {
		$variation_id = $variation_id - 1;
		$found        = false;
		if ( ! is_array( $variation_values[ $variation_id ] ) ) {
			$values = explode( "\r\n", $variation_values[ $variation_id ] );
		} else {
			$values = $variation_values[ $variation_id ];
		}
		if ( ! is_array( $variation_shipping[ $variation_id ] ) ) {
			$prices = explode( "\r\n", $variation_shipping[ $variation_id ] );
		} else {
			$prices = $variation_shipping[ $variation_id ];
		}
		foreach ( $values as $z => $value ) {
			if ( $z == $selected ) {
				$found = true;
				break;
			}
		}
		if ( $found ) {
			$prices[ $z ]      = empty( $prices[ $z ] ) ? 0 : $prices[ $z ];
			$price_mod         = get_shipping_price_mod( $shipping_price_a, $prices[ $z ], $variation_mod[ $variation_id ], $price_mod );
			$modified_shipping = variation_modify_shipping( $shipping_price_a, $price_mod, $format );

			return $modified_shipping;
		}
	} else {
		$modified_shipping = variation_modify_shipping( $shipping_price_a, 0, $format );

		return $modified_shipping;
	}

	return $shipping_price_a;
}

/**
 * Returns variation shipping price modificator
 *
 * @param array  $shipping_price_a Product shipping price array
 * @param float  $variation_shipping Selected variation shipping effect value
 * @param string $variation_mod Variation modificator type
 *
 * @return int
 */
function get_shipping_price_mod( $shipping_price_a, $variation_shipping, $variation_mod, $price_mod = 0 ) {
	if ( empty( $variation_shipping ) ) {
		return 0;
	}
	$variation_shipping = floatval( $variation_shipping );
	if ( $variation_mod === '%' ) {
		$price_mod = ! is_array( $price_mod ) ? array() : $price_mod;
		foreach ( $shipping_price_a as $i => $shipping_price ) {
			$shipping_price  = floatval( $shipping_price );
			$price_mod[ $i ] = isset( $price_mod[ $i ] ) ? $price_mod[ $i ] : 0;
			$price_mod[ $i ] = $price_mod[ $i ] + ( $shipping_price * ( 1 + ( $variation_shipping / 100 ) ) - $shipping_price );
		}
	} elseif ( $variation_mod === '=' ) {
		// $price_mod = $variation_shipping;
		$price_mod = $price_mod + $variation_shipping;
	} else {
		$price_mod = $price_mod + $variation_shipping;
	}

	return $price_mod;
}

/**
 * Returns variation modified shipping array
 *
 * @param array       $shipping_a
 * @param array|float $price_mod Modification array or value
 * @param boolean     $format
 *
 * @return array
 */
function variation_modify_shipping( $shipping_a, $price_mod, $format ) {
	if ( empty( $price_mod ) ) {
		return $shipping_a;
	}
	if ( is_array( $price_mod ) ) {
		foreach ( $shipping_a as $i => $shipping_price ) {
			$modified_shipping[ $i ] = $shipping_price + $price_mod[ $i ];
			if ( $format ) {
				$modified_shipping[ $i ] = function_exists( 'ic_shipping_price_format' ) ? ic_shipping_price_format( $modified_shipping[ $i ] ) : price_format( $modified_shipping[ $i ] );
			}
		}
	} else {
		foreach ( $shipping_a as $i => $shipping_price ) {
			$modified_shipping[ $i ] = floatval( $shipping_price ) + $price_mod;
			if ( $format ) {
				$modified_shipping[ $i ] = function_exists( 'ic_shipping_price_format' ) ? ic_shipping_price_format( $modified_shipping[ $i ] ) : price_format( $modified_shipping[ $i ] );
			}
		}
	}

	return $modified_shipping;
}

/**
 * Encodes array to json
 *
 * @param type $var_mod
 *
 * @return type
 */
function encode_variation_mod( $var_mod ) {
	return htmlspecialchars( json_encode( $var_mod ) );
}

/**
 * Returns product variations values array
 *
 * @param int     $cart_id
 * @param boolean $show_empty
 *
 * @return array
 */
function get_product_variations_values( $cart_id, $show_empty = true ) {
	$product_id       = cart_id_to_product_id( $cart_id );
	$cache_key        = $product_id . '_product_variations_values_' . intval( $show_empty );
	$variation_values = ic_get_global( $cache_key );
	if ( $variation_values === false ) {
		$variation_values = apply_filters( 'ic_get_product_variations_values', $variation_values, $product_id );
		if ( $variation_values === false ) {
			$product_variations_settings = get_product_variations_settings();
			$variation_values            = array();
			for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
				$values = get_post_meta( $product_id, $i . '_variation_values', true );
				if ( ! is_array( $values ) ) {
					$values = explode( "\r\n", $values );
				}
				$values          = apply_filters( 'ic_variation_values', $values, $i, $cart_id );
				$filtered_values = array_filter(
					$values,
					function ( $value ) {
						return ! empty( $value ) || is_numeric( $value );
					}
				);
				if ( $show_empty || ! empty( $filtered_values ) ) {
					$variation_values[] = $values;
				}
				unset( $values );
			}
		}
		ic_save_global( $cache_key, $variation_values );
	}

	return $variation_values;
}

/**
 * Returns product variations price modificators array
 *
 * @param int     $product_id
 * @param boolean $array Will make array from each textarea value if true
 *
 * @return array
 */
function get_product_variations_prices( $cart_id ) {
	$product_id       = cart_id_to_product_id( $cart_id );
	$variation_prices = ic_get_global( $product_id . '_product_variations_prices' );
	if ( $variation_prices === false ) {
		$variation_prices = apply_filters( 'ic_get_product_variations_prices', $variation_prices, $product_id );
		if ( $variation_prices === false ) {
			$product_variations_settings = get_product_variations_settings();
			$variation_prices            = array();
			for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
				$prices_meta = get_post_meta( $product_id, $i . '_variation_prices', true );
				if ( ! is_array( $prices_meta ) ) {
					$prices_meta = explode( "\r\n", $prices_meta );
				}
				$variation_prices[] = $prices_meta;
			}
		}
		ic_save_global( $product_id . '_product_variations_prices', $variation_prices );
	}

	return apply_filters( 'ic_variations_prices', $variation_prices, $product_id );
}

/**
 * Returns product variations shipping price modificators array
 *
 * @param int     $product_id
 * @param boolean $array Will make array from each textarea value if true
 *
 * @return array
 */
function get_product_variations_shipping( $cart_id ) {
	$product_id         = cart_id_to_product_id( $cart_id );
	$variation_shipping = ic_get_global( $product_id . '_product_variations_shipping' );
	if ( $variation_shipping === false ) {
		$variation_shipping = apply_filters( 'ic_get_product_variations_shipping', $variation_shipping, $product_id );
		if ( $variation_shipping === false ) {
			$product_variations_settings = get_product_variations_settings();
			$variation_shipping          = array();
			for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
				$variation_shipping[] = get_post_meta( $product_id, $i . '_variation_shipping', true );
			}
		}
		ic_save_global( $product_id . '_product_variations_shipping', $variation_shipping );
	}

	return $variation_shipping;
}

/**
 * Returns product variations labels array
 *
 * @param int $product_id
 *
 * @return array
 */
function get_product_variations_labels( $cart_id ) {
	$product_id = cart_id_to_product_id( $cart_id );
	$labels     = ic_get_global( $product_id . '_product_variations_labels' );
	if ( $labels === false ) {
		$labels                      = apply_filters( 'ic_get_product_variations_labels', array(), $product_id );
		$product_variations_settings = get_product_variations_settings();
		for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
			$labels [] = get_product_variation_label( $product_id, $i );
		}
		ic_save_global( $product_id . '_product_variations_labels', $labels );
	}

	return $labels;
}

function get_product_variation_label( $product_id, $i ) {
	$label = get_post_meta( $product_id, $i . '_variation_label', true );

	return apply_filters( 'get_product_variation_label', $label, $i, $product_id );
}

/**
 * Returns variations price modificators array
 *
 * @param int $product_id
 *
 * @return array
 */
function get_product_variations_price_mod_type( $cart_id ) {
	$product_id = cart_id_to_product_id( $cart_id );
	$mod_types  = apply_filters( 'ic_get_product_variations_price_mod_type', false, $product_id );
	if ( $mod_types === false ) {
		$product_variations_settings = get_product_variations_settings();
		$mod_types                   = array();
		for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
			$mod_type    = ic_variation_mod_type( $cart_id, $i );
			$mod_types[] = apply_filters( 'ic_variation_mod_type', $mod_type, $cart_id, $i );
		}
	}

	return $mod_types;
}

function ic_variation_mod_type( $cart_id, $var_id ) {
	$product_id = cart_id_to_product_id( $cart_id );
	$mod_type   = get_post_meta( $product_id, $var_id . '_variation_mod', true );
	if ( empty( $mod_type ) ) {
		$product_variations_settings                   = get_product_variations_settings();
		$product_variations_settings['mod'][ $var_id ] = isset( $product_variations_settings['mod'][ $var_id ] ) ? $product_variations_settings['mod'][ $var_id ] : '+';
		$mod_type                                      = $product_variations_settings['mod'][ $var_id ];
	}

	return $mod_type;
}

function get_cart_id_without_variations( $cart_id ) {
	if ( strpos( $cart_id, '_' ) == ! false ) {
		$cart_id = explode( '_', $cart_id );

		return $cart_id[0];
	} else {
		return $cart_id;
	}
}

/**
 * Returns exact variation value or array of selected variations if $i not provided
 *
 * @param type $cart_id
 * @param type $i
 *
 * @return boolean
 */
function get_variation_value_from_cart_id( $cart_id, $i = null ) {
	if ( strpos( $cart_id, '_' ) == ! false ) {
		$var_lps          = apply_filters( 'ic_variation_lps_for_value_from_cart_id', get_variation_lp_from_cart_id( $cart_id, $i, false ) );
		$variation_values = get_product_variations_values( $cart_id );
		if ( is_array( $var_lps ) && $i === null ) {
			foreach ( $var_lps as $var_id => $var_lp ) {
				if ( is_array( $var_lp ) ) {
					$var_values[ $var_id ] = array();
					foreach ( $var_lp as $this_var_lp ) {
						$var_values[ $var_id ][] = $variation_values[ $var_id - 1 ][ $this_var_lp ];
					}
				} else {
					$var_values[ $var_id ] = isset( $variation_values[ $var_id - 1 ][ $var_lp ] ) ? $variation_values[ $var_id - 1 ][ $var_lp ] : apply_filters( 'ic_variation_empty_value_from_cart_id', '', $var_lp, $variation_values[ $var_id - 1 ] );
				}
			}
		} elseif ( is_array( $var_lps ) && $i !== null ) {
			$var_values[ $i ] = array();
			foreach ( $var_lps as $var_id => $var_lp ) {
				$var_values[ $i ][] = isset( $variation_values[ $i - 1 ][ $var_lp ] ) ? $variation_values[ $i - 1 ][ $var_lp ] : apply_filters( 'ic_variation_empty_value_from_cart_id', '', $var_lp, $variation_values[ $i - 1 ] );
			}
		} elseif ( ! empty( $var_lps ) || is_numeric( $var_lps ) ) {
			$var_values[ $i ] = isset( $variation_values[ $i - 1 ][ $var_lps ] ) ? $variation_values[ $i - 1 ][ $var_lps ] : apply_filters( 'ic_variation_empty_value_from_cart_id', '', $var_lps, $variation_values[ $i - 1 ] );
			if ( $var_values[ $i ] === '' ) {
				$variation_type = ic_cat_get_variation_type( cart_id_to_product_id( $cart_id ), $i );
				if ( $variation_type === 'custom' ) {
					$var_values[ $i ] = $var_lps;
				}
			}
		}

		if ( $i == null ) {
			$product_id = cart_id_to_product_id( $cart_id );
			foreach ( $var_values as $z => $value ) {
				$var_array[ $z . '_variation_' . $product_id ] = $value;
			}

			return $var_array;
		} else {
			return isset( $var_values[ $i ] ) ? $var_values[ $i ] : '';
		}
	} else {
		return false;
	}
}

/**
 * Returns exact variation lp or array of selected variations if $i not provided
 *
 * @param type $cart_id
 * @param type $i
 *
 * @return boolean
 */
function get_variation_lp_from_cart_id( $cart_id, $i = null, $full_index = true ) {
	if ( strpos( $cart_id, '_' ) == ! false ) {
		$var_lps = explode( '_', $cart_id );
		if ( $i == null ) {
			unset( $var_lps[0] );
			$product_id = cart_id_to_product_id( $cart_id );
			foreach ( $var_lps as $z => $value ) {
				if ( $full_index ) {
					$index = $z . '_variation_' . $product_id;
				} else {
					$index = $z;
				}
				$var_lps_array[ $index ] = $value;
			}

			return apply_filters( 'ic_variation_lp_from_cart_id', $var_lps_array, $i );
		} else {
			return isset( $var_lps[ $i ] ) ? apply_filters( 'ic_variation_lp_from_cart_id', $var_lps[ $i ], $i ) : '';
		}
	} else {
		return false;
	}
}

function unescape_variation_cart_value( $encoded ) {
	// $unescaped = preg_replace_callback('/(?<!\\\\)\\\\u(\w{4})/', function ($matches) {
	// return html_entity_decode(' & #x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
	// }, $encoded);
	$unescaped = mb_decode_numericentity( $encoded, array( 0x80, 0xffff, 0, 0xffff ), 'UTF-8' );

	return $unescaped;
}

add_filter( 'payment_order_details', 'add_variations_to_payment_summary', 11 );

function add_variations_to_payment_summary( $order ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
	if ( true || isset( $_POST['cart_submit'] ) ) {
		$product_variations_settings = get_product_variations_settings();
		$cart_content                = ic_shopping_cart_content( true );
		$products_array              = shopping_cart_products_array( $cart_content );
		$a                           = 0;
		foreach ( $products_array as $cart_id => $p_quantity ) {
			$product_id = cart_id_to_product_id( $cart_id );
			for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
				$variation_label = get_product_variation_label( $product_id, $i );
				$variation_value = get_variation_value_from_cart_id( $cart_id, $i );
				if ( $variation_value ) {
					$order['variations'][ $a ][ $variation_label ] = apply_filters( 'ic_variation_value_in_payment_summary', $variation_value, $cart_id, $i );
				}
			}
			$a += 1;
		}
	}

	return $order;
}

function get_current_product_variations_string( $current_product_id, $product_variations_settings ) {
	$current_product_variations = false;
	if ( $current_product_id != '' && $product_variations_settings['count'] > 0 ) {
		for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			if ( isset( $_POST[ $i . '_variation_' . $current_product_id ] ) && $_POST[ $i . '_variation_' . $current_product_id ] != '' ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
				$posted_variation            = wp_unslash( $_POST[ $i . '_variation_' . $current_product_id ] );
				$post_sanitized              = is_array( $posted_variation ) ? implode( array_map( 'sanitize_text_field', $posted_variation ) ) : sanitize_text_field( $posted_variation );
				$current_product_variations  = isset( $current_product_variations ) ? $current_product_variations : '';
				$current_product_variations .= '_' . $post_sanitized;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			} elseif ( isset( $_POST[ $i . '_variation_' . $current_product_id ] ) ) {
				$current_product_variations  = isset( $current_product_variations ) ? $current_product_variations : '';
				$current_product_variations .= '_';
			} else {
				$current_product_variations  = isset( $current_product_variations ) ? $current_product_variations : '';
				$current_product_variations .= '_';
			}
		}
	}

	return $current_product_variations;
}

function create_variation_id( $cart_content, $cart_id, $variations_string ) {
	$variation_id = $cart_id;
	$product_id   = cart_id_to_product_id( $cart_id );
	if ( is_array( $cart_content ) && ! empty( $product_id ) ) {
		/*
			$count = 0;
			foreach ( $cart_content as $this_cart_id => $qty ) {
			$this_product_id = cart_id_to_product_id( $this_cart_id );

			if ( $this_product_id == $product_id && $cart_id !== $this_cart_id ) {
			$count++;
			}
			}
		 *
		 */
		$max_var_id = 0;
		$count      = 0;
		foreach ( $cart_content as $this_cart_id => $qty ) {
			$this_product_id = cart_id_to_product_id( $this_cart_id );
			if ( $this_product_id == $product_id && $cart_id !== $this_cart_id ) {
				$var_id = ic_var_id_from_cart_id( $this_cart_id );
				++$count;
				if ( ic_string_contains( $var_id, '::' ) ) {
					$get_id = explode( '::', $var_id );
					if ( ! empty( $get_id[0] ) && $get_id[0] > $max_var_id ) {
						$max_var_id = $get_id[0];
					}
				}
			}
		}

		if ( ! empty( $count ) && $count > 0 ) {
			if ( ! empty( $max_var_id ) ) {
				$this_var_id = $max_var_id + 1;
			} else {
				$this_var_id = $count;
			}
			$variation_id = $this_var_id . '::' . $product_id;
		}
	}
	/*
		$string_cart_content = implode( ',', array_keys( $cart_content ) );
		if ( !empty( $cart_content ) && $product_id != '' && $variations_string && !ic_string_contains( $string_cart_content, $product_id . $variations_string . ',' ) && (ic_string_contains( $string_cart_content, $product_id . '_' ) || ic_string_contains( $string_cart_content, '::' . $product_id )) ) {
		$count             = 1;
		$lastPos       = 0;
		$same_products     = array();
		//$unique_products = implode( ',', array_unique( $cart_content ) );
		while ( ($lastPos      = strpos( $string_cart_content, '::' . $product_id, $lastPos )) !== false ) {
		$same_products[] = $lastPos;
		$lastPos       = $lastPos + strlen( '::' . $product_id );
		}
		$count             = count( $same_products ) + 1;
		$variation_id  = $count . '::' . $product_id;
		}
	 *
	 */

	/*
		else if ($cart_content != '' && $product_id != '' && $variations_string && strpos($cart_content,$product_id.$variations_string) !== false) {
		$count = get_variation_id($product_id.$variations_string, $cart_content);
		if (!empty($count)) {
		$variation_id = $count.'::'.$product_id;
		}
		} */

	return $variation_id;
}

add_action( 'wp', 'ic_validate_variations_submit' );

function ic_validate_variations_submit() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
	if ( is_ic_variations_checkout() && ! is_ic_product_page() && ! isset( $_POST['current_product'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		foreach ( $_POST as $key => $param ) {
			$key_temp = explode( '_', $key );
			if ( isset( $key_temp[1] ) && $key_temp[1] == 'variation' && $param == '' ) {
				$checkout_url = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
				wp_safe_redirect( add_query_arg( 'no_variations', $key, $checkout_url ) );
				exit;
				exit();
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( empty( $_POST ) && is_ic_shopping_order() && ! ic_cart_all_variations_selected() ) {
			$checkout_url = esc_url_raw( ic_shopping_cart_page_url() );
			if ( empty( $checkout_url ) ) {
				return;
			}
			wp_safe_redirect( add_query_arg( 'no_variations', '1', $checkout_url ) );
			exit;
			exit();
		}
	}
}

/**
 * Delete variations id from cart id
 *
 * @param type $cart_id
 *
 * @return type
 */
function get_cart_id_without_variation_id( $cart_id ) {
	if ( strpos( $cart_id, '::' ) !== false ) {
		$cart_id = explode( '::', $cart_id );
		$cart_id = $cart_id[1];
	}

	return $cart_id;
}

function ic_var_id_from_cart_id( $cart_id ) {
	if ( ic_string_contains( $cart_id, '::' ) && ic_string_contains( $cart_id, '_' ) ) {
		$temp    = explode( '_', $cart_id );
		$cart_id = $temp[0];
	} else {
		$cart_id = cart_id_to_product_id( $cart_id );
	}

	return $cart_id;
}

add_filter( 'post_class', 'add_variations_product_class', 10, 1 );

/**
 * Adds variable product class
 *
 * @param string $product_class
 * @param type   $class
 * @param type   $product_id
 *
 * @return string
 */
function add_variations_product_class( $product_class ) {
	$product_id = ic_get_product_id();
	if ( function_exists( 'is_ic_product' ) && is_ic_product( $product_id ) && ic_has_product_variations( $product_id ) ) {
		$product_class[] = 'variable-product';
		if ( is_ic_any_variation_price_effect( $product_id ) ) {
			$product_class[] = 'variable-price-effect';
			$settings        = get_product_variations_settings();
			if ( is_ic_multi_variation_price_effect( $product_id ) ) {
				$product_class[] = 'variable-multi-price-effect';
			} elseif ( ! ic_has_multiple_product_variations( $product_id ) && $settings['info'] === 'full-price' ) {
				$product_class[] = 'variable-single-price-effect';
			}
		}
	}

	return $product_class;
}

/*
	function get_variation_id( $cart_id, $cart_content ) {
	$cart_content = explode( ',', $cart_content );
	foreach ( $cart_content as $item ) {
	if ( strpos( $item, $cart_id ) !== FALSE ) {
	if ( strpos( $item, '::' ) !== FALSE ) {
	$item = explode( '::', $item );
	return $item[ 0 ];
	break;
	} else {
	return '';
	break;
	}
	}
	}
	}
 */

add_action( 'ic_cat_shopping_variation_buttons', 'ic_cat_variation_custom_field_button', 10, 2 );

function ic_cat_variation_custom_field_button( $product_id, $var_id ) {
	if ( ! ic_is_variations_qc_sc_enabled() ) {
		return;
	}
	$variation_type  = ic_cat_get_variation_type( $product_id, $var_id );
	$available_types = ic_variation_available_types();
	?>
	<div class="variation-type-select-container">
		<?php
		implecode_settings_radio( '', $var_id . '_variation_type', $variation_type, $available_types );
		?>
	</div>
	<?php
	/*
		$class = "";
		if ( $variation_type === "custom" ) {
		$class = "active";
		}
		?>
		<div class="button button-small custom_field_variation <?php echo $class ?>" data-type_name="custom">
		<?php _e( 'Custom Field', 'implecode-shopping-cart' ) ?>
		</div>
		<input name="<?php echo $var_id ?>_variation_type" class="variation-type-selector" value="<?php echo $variation_type ?>" type="hidden">
		<?php
	 *
	 */
}

add_filter( 'ic_cat_variation_tr_class', 'ic_cat_get_variation_type_class', 10, 3 );

function ic_cat_get_variation_type_class( $class, $product_id, $var_id ) {
	$class .= ' type-' . ic_cat_get_variation_type( $product_id, $var_id );

	return $class;
}

add_filter( 'ic_cat_variation_tr_attr', 'ic_cat_get_variation_type_attr', 10, 3 );

function ic_cat_get_variation_type_attr( $attr, $product_id, $var_id ) {
	$attr .= ' data-type="' . ic_cat_get_variation_type( $product_id, $var_id ) . '"';

	return $attr;
}

function ic_cat_get_variation_type( $product_id, $var_id ) {
	$type = get_post_meta( $product_id, $var_id . '_variation_type', true );
	if ( empty( $type ) ) {
		$type = 'dropdown';
	}

	return apply_filters( 'ic_variation_type', $type );
}

function ic_variation_available_types() {
	if ( ! ic_is_variations_qc_sc_enabled() ) {
		return array(
			'dropdown' => __( 'Dropdown', 'ecommerce-product-catalog' ),
		);
	}
	$types = array(
		'dropdown' => __( 'Dropdown', 'ecommerce-product-catalog' ),
		'radio'    => __( 'Radio', 'ecommerce-product-catalog' ),
		'checkbox' => __( 'Checkbox', 'ecommerce-product-catalog' ),
		'custom'   => __( 'Custom', 'ecommerce-product-catalog' ),
		// 'table'        => __( 'Table', 'implecode-shopping-cart' )
	);

	return $types;
}
