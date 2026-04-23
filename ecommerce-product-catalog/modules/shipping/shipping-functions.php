<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages product attributes
 *
 * Here all product attributes are defined and managed.
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog/includes
 * @author        impleCode
 */
function get_shipping_options_number() {
	return get_option( 'product_shipping_options_number', 1 );
}

/**
 * Returns the cache schema for checkout shipping payloads.
 *
 * Bump this value whenever checkout shipping option array metadata changes.
 *
 * @return string
 */
function ic_shipping_checkout_cache_schema() {
	return apply_filters( 'ic_shipping_checkout_cache_schema', 'shipping_address_slots_v2' );
}

/**
 * Returns the persisted shipping cache version.
 *
 * @return string
 */
function ic_shipping_checkout_cache_version() {
	return (string) get_option( 'ic_shipping_checkout_cache_version', ic_shipping_checkout_cache_schema() );
}

/**
 * Bumps the persisted shipping cache version.
 *
 * @return void
 */
function ic_bump_shipping_checkout_cache_version() {
	update_option( 'ic_shipping_checkout_cache_version', ic_shipping_checkout_cache_schema() . '-' . microtime( true ), false );
}

/**
 * Returns true when a meta key affects checkout shipping option payloads.
 *
 * @param string $meta_key Meta key being changed.
 *
 * @return bool
 */
function ic_is_shipping_checkout_cache_meta_key( $meta_key ) {
	$meta_key = (string) $meta_key;

	return (bool) preg_match( '/^_shipping(?:-label)?\d+$/', $meta_key );
}

/**
 * Bumps the checkout shipping cache version after shipping-related product meta changes.
 *
 * @param int    $meta_id Meta row ID.
 * @param int    $object_id Object ID.
 * @param string $meta_key Meta key.
 *
 * @return void
 */
function ic_maybe_bump_shipping_checkout_cache_version_for_meta( $meta_id, $object_id, $meta_key ) {
	if ( 'al_product' !== get_post_type( $object_id ) || ! ic_is_shipping_checkout_cache_meta_key( $meta_key ) ) {
		return;
	}

	ic_bump_shipping_checkout_cache_version();
}

add_action( 'update_option_product_shipping_options_number', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'update_option_display_shipping', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'update_option_product_shipping_cost', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'update_option_product_shipping_label', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'update_option_product_shipping_note', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'update_option_product_shipping_address_collection', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'update_option_general_shipping_settings', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'update_option_ic_store_locations_shipping_defaults', 'ic_bump_shipping_checkout_cache_version' );
add_action( 'added_post_meta', 'ic_maybe_bump_shipping_checkout_cache_version_for_meta', 10, 3 );
add_action( 'updated_post_meta', 'ic_maybe_bump_shipping_checkout_cache_version_for_meta', 10, 3 );
add_action( 'deleted_post_meta', 'ic_maybe_bump_shipping_checkout_cache_version_for_meta', 10, 3 );

/**
 * Returns true when a cached checkout shipping payload has the current shape.
 *
 * @param array $checkout_shipping Checkout shipping options.
 *
 * @return bool
 */
function ic_shipping_checkout_options_cache_valid( $checkout_shipping ) {
	if ( empty( $checkout_shipping ) || ! is_array( $checkout_shipping ) ) {
		return false;
	}

	foreach ( $checkout_shipping as $shipping_group ) {
		if ( empty( $shipping_group['options'] ) || ! is_array( $shipping_group['options'] ) ) {
			return false;
		}

		foreach ( $shipping_group['options'] as $option ) {
			if ( ! is_array( $option ) ) {
				return false;
			}
			if ( ! array_key_exists( 'name', $option ) || ! array_key_exists( 'price', $option ) || empty( $option['slot'] ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Returns available shipping source modes.
 *
 * @return array
 */
function ic_get_shipping_source_modes() {
	return array(
		'option_price_per_product' => __( 'Shipping options and prices set per product', 'ecommerce-product-catalog' ),
		'price_per_product'        => __( 'Global shipping options with prices set per product', 'ecommerce-product-catalog' ),
		'global'                   => __( 'Global shipping options and prices for all products', 'ecommerce-product-catalog' ),
	);
}

/**
 * Returns the current shipping source mode.
 *
 * @return string
 */
function get_shipping_source_mode() {
	$shipping_settings = get_general_shipping_settings();
	$mode              = isset( $shipping_settings['source_mode'] ) ? sanitize_key( $shipping_settings['source_mode'] ) : 'option_price_per_product';
	$allowed_modes     = array_keys( ic_get_shipping_source_modes() );

	if ( ! in_array( $mode, $allowed_modes, true ) ) {
		$mode = 'option_price_per_product';
	}

	return apply_filters( 'ic_shipping_source_mode', $mode, $shipping_settings );
}

/**
 * Returns true when shipping labels can be changed per product.
 *
 * @return bool
 */
function ic_product_shipping_labels_per_product() {
	return get_shipping_source_mode() === 'option_price_per_product';
}

/**
 * Returns true when shipping prices can be changed per product.
 *
 * @return bool
 */
function ic_product_shipping_prices_per_product() {
	return get_shipping_source_mode() !== 'global';
}

/**
 * Checks if a shipping value should be treated as saved.
 *
 * @param mixed $value
 *
 * @return bool
 */
function ic_has_shipping_value( $value ) {
	return $value !== '' && $value !== null;
}

/**
 * Returns the default shipping price for a slot.
 *
 * @param int $i
 *
 * @return string
 */
function ic_get_default_shipping_cost( $i ) {
	$shipping_costs = get_default_shipping_costs();

	return isset( $shipping_costs[ $i ] ) ? $shipping_costs[ $i ] : '';
}

/**
 * Returns the default shipping label for a slot.
 *
 * @param int $i
 *
 * @return string
 */
function ic_get_default_shipping_label( $i ) {
	$shipping_labels = get_default_shipping_labels();

	return isset( $shipping_labels[ $i ] ) ? $shipping_labels[ $i ] : '';
}

/**
 * Returns the default shipping note for a slot.
 *
 * @param int $i
 *
 * @return string
 */
function ic_get_default_shipping_note( $i ) {
	$shipping_notes = get_default_shipping_notes();

	return isset( $shipping_notes[ $i ] ) ? $shipping_notes[ $i ] : '';
}

/**
 * Returns true when shipping-address collection can be configured.
 *
 * @return bool
 */
function ic_shipping_address_collection_available() {
	return function_exists( 'shopping_cart_products' );
}

/**
 * Returns enabled shipping-address collection slots.
 *
 * @return array
 */
function get_default_shipping_address_collection() {
	$shipping_address_collection = get_option( 'product_shipping_address_collection' );
	if ( empty( $shipping_address_collection ) || ! is_array( $shipping_address_collection ) ) {
		$shipping_address_collection = array();
	}

	return $shipping_address_collection;
}

/**
 * Returns true when a shipping slot should collect a shipping address.
 *
 * @param int $i Shipping slot number.
 *
 * @return bool
 */
function ic_shipping_collect_address( $i ) {
	$shipping_address_collection = get_default_shipping_address_collection();

	return apply_filters( 'ic_shipping_collect_address', ! empty( $shipping_address_collection[ intval( $i ) ] ), intval( $i ) );
}

/**
 * Returns the default shipping-address form fields JSON.
 *
 * @return string
 */
function ic_default_shipping_address_form_fields() {
	$default  = '{"fields":[';
	$default .= '{"label":"' . __( 'Full Name', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":true,"field_options":{"size":"medium"},"cid":"name"}';
	$default .= ',{"label":"' . __( 'Company', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"company"}';
	$default .= ',{"label":"' . __( 'Address', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":true,"field_options":{"size":"medium"},"cid":"address"}';
	$default .= ',{"label":"' . __( 'Postal Code', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":true,"field_options":{"size":"medium"},"cid":"postal"}';
	$default .= ',{"label":"' . __( 'City', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":true,"field_options":{"size":"medium"},"cid":"city"}';
	$default .= ',{"label":"' . __( 'Country', 'ecommerce-product-catalog' ) . ':","field_type":"dropdown_country","required":true,"field_options":{"size":"medium"},"cid":"country"}';
	$default .= ',{"label":"' . __( 'State', 'ecommerce-product-catalog' ) . ':","field_type":"dropdown_state","required":false,"field_options":{"size":"medium"},"cid":"state"}';
	$default .= ',{"label":"' . __( 'Phone', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"phone"}';
	$default .= ',{"label":"' . __( 'Email', 'ecommerce-product-catalog' ) . ':","field_type":"email","required":false,"field_options":{"size":"medium"},"cid":"email"}';
	$default .= ',{"label":"' . __( 'Comment', 'ecommerce-product-catalog' ) . ':","field_type":"paragraph","required":false,"field_options":{"size":"medium"},"cid":"comment"}';
	$default .= ']}';

	return str_replace( ',]', ']', $default );
}

/**
 * Returns the configured shipping-address form fields JSON for one slot.
 *
 * @param int    $shipping_slot Shipping slot number.
 * @param string $pre_name Checkout form prefix.
 *
 * @return string
 */
function ic_get_shipping_address_form_fields( $shipping_slot, $pre_name = '' ) {
	$default_fields = ic_default_shipping_address_form_fields();

	return apply_filters( 'ic_shipping_address_form_fields', $default_fields, intval( $shipping_slot ), $pre_name );
}

/**
 * Returns edit-button HTML for one shipping-address form slot.
 *
 * @param int $shipping_slot Shipping slot number.
 *
 * @return string
 */
function ic_get_shipping_address_form_editor_button( $shipping_slot ) {
	return apply_filters( 'ic_shipping_address_form_editor_button', '', intval( $shipping_slot ) );
}

/**
 * Returns a configured shipping table value for a slot.
 *
 * @param array  $values Value map grouped by type.
 * @param string $type Value type.
 * @param int    $index Shipping option index.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function ic_get_shipping_table_value( $values, $type, $index, $default = '' ) {
	$index = intval( $index );
	if ( ! is_array( $values ) || empty( $type ) ) {
		return $default;
	}
	if ( ! empty( $values[ $type ] ) && is_array( $values[ $type ] ) && array_key_exists( $index, $values[ $type ] ) ) {
		return $values[ $type ][ $index ];
	}

	return $default;
}

/**
 * Returns the rendered field name for a shipping table input.
 *
 * @param string $pattern Field name pattern.
 * @param int    $index Shipping option index.
 *
 * @return string
 */
function ic_get_shipping_table_field_name( $pattern, $index ) {
	if ( '' === $pattern ) {
		return '';
	}

	return sprintf( $pattern, intval( $index ) );
}

/**
 * Returns product shipping values array
 *
 * @param type $product_id
 *
 * @return type
 */
function get_shipping_options( $product_id ) {
	$shipping_options   = get_shipping_options_number();
	$shipping_values    = array();
	$any_shipping_value = false;
	for ( $i = 1; $i <= $shipping_options; $i++ ) {
		$sh_val   = get_shipping_option( $i, $product_id );
		$test_val = '';
		if ( ! empty( $sh_val ) || is_numeric( $sh_val ) ) {
			$test_val = ic_shipping_price_format( $sh_val );
		}
		if ( ! empty( $test_val ) ) {
			$any_shipping_value = true;
		}
		$shipping_values[ $i ] = $sh_val;
	}
	if ( ! $any_shipping_value ) {
		$shipping_values = 'none';
	}

	return apply_filters( 'product_shipping_values', $shipping_values, $product_id );
}

/**
 * Returns product shipping labels array
 *
 * @param type $product_id
 *
 * @return type
 */
function get_shipping_labels( $product_id ) {
	$shipping_values = get_shipping_options( $product_id );
	$shipping_labels = array();
	if ( is_array( $shipping_values ) ) {
		foreach ( $shipping_values as $i => $shipping_value ) {
			$shipping_value = ic_shipping_price_format( $shipping_value );
			if ( ! empty( $shipping_value ) ) {
				$shipping_labels[ $i ] = get_shipping_label( $i, $product_id );
			}
		}
	}

	return apply_filters( 'product_shipping_labels', $shipping_labels );
}

/**
 * Returns specific shipping option
 *
 * @param type $i
 * @param type $product_id
 *
 * @return type
 */
function get_shipping_option( $i = 1, $product_id = null ) {
	if ( empty( $product_id ) ) {
		$product_id = function_exists( 'ic_get_product_id' ) ? ic_get_product_id() : get_the_ID();
	}
	$mode   = get_shipping_source_mode();
	$option = '';
	if ( $mode !== 'global' ) {
		$option = get_post_meta( $product_id, '_shipping' . $i, true );
	}
	if ( $mode === 'global' || ( $mode === 'price_per_product' && ! ic_has_shipping_value( $option ) ) ) {
		$option = ic_get_default_shipping_cost( $i );
	}

	return apply_filters( 'product_shipping_option_price', $option, $product_id, $i );
}

function get_shipping_label( $i = 1, $product_id = null ) {
	if ( empty( $product_id ) ) {
		$product_id = function_exists( 'ic_get_product_id' ) ? ic_get_product_id() : get_the_ID();
	}
	if ( ic_product_shipping_labels_per_product() ) {
		$label = get_post_meta( $product_id, '_shipping-label' . $i, true );
	} else {
		$label = ic_get_default_shipping_label( $i );
	}
	$label = empty( $label ) ? ic_get_default_shipping_label( $i ) : $label;

	return apply_filters( 'ic_product_shipping_label', $label, $product_id, $i );
}

/**
 * Returns the configured checkout note for a shipping slot.
 *
 * @param int $i
 * @param int $product_id
 *
 * @return string
 */
function get_shipping_note( $i = 1, $product_id = null ) {
	if ( empty( $product_id ) ) {
		$product_id = function_exists( 'ic_get_product_id' ) ? ic_get_product_id() : get_the_ID();
	}

	$note = ic_get_default_shipping_note( $i );

	return apply_filters( 'ic_product_shipping_note', $note, $product_id, $i );
}

add_action( 'product_details', 'show_shipping_options', 9, 0 );

/**
 * Shows shipping table
 *
 * @param object $post
 * @param array  $single_names
 */
function show_shipping_options( $product_id = false ) {
	ic_show_template_file( 'product-page/product-shipping.php', AL_BASE_TEMPLATES_PATH, $product_id );
}

/**
 * Returns shipping options table
 *
 * @param int   $product_id
 * @param array $v_single_names
 *
 * @return string
 */
function get_shipping_options_table( $product_id ) {
	ob_start();
	show_shipping_options( $product_id );

	return ob_get_clean();
}

/**
 * Generated a formatted shipping price
 *
 * @param $price
 * @param int  $clear
 * @param int  $format
 * @param int  $raw
 * @param bool $free_label
 *
 * @return float|string
 */
function ic_shipping_price_format( $price, $clear = 0, $format = 1, $raw = 0, $free_label = true ) {
	if ( $price === null || $price === '' ) {
		return '';
	} elseif ( empty( $price ) && $free_label ) {
		$single_names = get_single_names();

		return $single_names['free_shipping'];
	}

	return function_exists( 'price_format' ) ? price_format( $price, $clear, $format, $raw, $free_label ) : number_format( $price, 2 );
}
