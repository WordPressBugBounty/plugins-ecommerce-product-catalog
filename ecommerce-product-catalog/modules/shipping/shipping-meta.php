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
add_action( 'add_product_metaboxes', 'ic_shipping_metabox' );

/**
 * Renders the product shipping table.
 *
 * @param array $args Table arguments.
 */
function ic_render_product_shipping_table( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'shipping_count'          => get_shipping_options_number(),
			'table_class'             => 'sort-settings shipping',
			'currency'                => '',
			'values'                  => array(),
			'label_name_pattern'      => '_shipping-label%d',
			'cost_name_pattern'       => '_shipping%d',
			'label_disabled'          => false,
			'price_disabled'          => false,
			'price_placeholders'      => array(),
			'label_cell_class'        => 'shipping-label-column',
			'price_cell_class'        => '',
			'label_input_class'       => 'shipping-label',
			'price_input_class'       => 'shipping-value',
			'price_input_id'          => 'admin-number-field',
			'show_dragger'            => true,
			'separate_currency_cell'  => false,
		)
	);

	$shipping_count = intval( $args['shipping_count'] );
	if ( $shipping_count < 1 ) {
		return;
	}

	$label_disabled = ! empty( $args['label_disabled'] ) ? ' disabled="disabled"' : '';
	$price_disabled = ! empty( $args['price_disabled'] ) ? ' disabled="disabled"' : '';
	$label_cell     = '' !== $args['label_cell_class'] ? ' class="' . esc_attr( $args['label_cell_class'] ) . '"' : '';
	$price_cell     = '' !== $args['price_cell_class'] ? ' class="' . esc_attr( $args['price_cell_class'] ) . '"' : '';
	$price_input_id = '' !== $args['price_input_id'] ? ' id="' . esc_attr( $args['price_input_id'] ) . '"' : '';

	echo '<table class="' . esc_attr( $args['table_class'] ) . '"><tbody>';
	for ( $i = 1; $i <= $shipping_count; $i++ ) {
		$label             = ic_get_shipping_table_value( $args['values'], 'label', $i, '' );
		$cost              = ic_get_shipping_table_value( $args['values'], 'cost', $i, '' );
		$price_placeholder = ic_get_shipping_table_value( $args['price_placeholders'], 'value', $i, '' );
		$placeholder_attr  = '' !== $price_placeholder ? ' placeholder="' . esc_attr( $price_placeholder ) . '"' : '';

		echo '<tr>';
		if ( ! empty( $args['show_dragger'] ) ) {
			echo '<td class="dragger"></td>';
		}
		echo '<td' . $label_cell . '><input class="' . esc_attr( $args['label_input_class'] ) . '" type="text" name="' . esc_attr( ic_get_shipping_table_field_name( $args['label_name_pattern'], $i ) ) . '" value="' . esc_attr( $label ) . '"' . $label_disabled . ' /></td>';
		echo '<td' . $price_cell . '><input' . $price_input_id . ' class="' . esc_attr( $args['price_input_class'] ) . '" type="number" min="0" step="0.01" name="' . esc_attr( ic_get_shipping_table_field_name( $args['cost_name_pattern'], $i ) ) . '" value="' . esc_attr( $cost ) . '"' . $placeholder_attr . $price_disabled . ' />';
		if ( ! empty( $args['separate_currency_cell'] ) ) {
			echo '</td><td>' . esc_html( $args['currency'] ) . '</td>';
		} else {
			echo esc_html( $args['currency'] ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
}

/**
 * Adds attributes meatbox
 *
 * @param array $names
 */
function ic_shipping_metabox( $names ) {
	$names['singular'] = ic_ucfirst( $names['singular'] );
	if ( is_plural_form_active() ) {
		$labels['shipping'] = sprintf( __( '%s Shipping', 'ecommerce-product-catalog' ), $names['singular'] );
	} else {
		$labels['shipping'] = __( 'Shipping', 'ecommerce-product-catalog' );
	}
	$sh_num = get_shipping_options_number();
	if ( $sh_num > 0 ) {
		add_meta_box( 'al_product_shipping', $labels['shipping'], 'al_product_shipping', 'al_product', apply_filters( 'product_shipping_box_column', 'side' ), apply_filters( 'product_shipping_box_priority', 'default' ) );
	}
}

/**
 * Shows shipping meta box content
 *
 * @global type $post
 */
function al_product_shipping() {
	global $post;
	echo '<input type="hidden" name="shippingmeta_noncename" id="shippingmeta_noncename" value="' .
		wp_create_nonce( AL_BASE_PATH . 'shipping_meta' ) . '" />';
	$currency      = '';
	$shipping_mode = get_shipping_source_mode();
	if ( function_exists( 'product_currency' ) ) {
		$currency = product_currency();
	}
	if ( $shipping_mode === 'price_per_product' ) {
		echo '<p>' . esc_html__( 'Shipping option names are defined globally. You can override only the shipping prices for this product.', 'ecommerce-product-catalog' ) . '</p>';
	} elseif ( $shipping_mode === 'global' ) {
		echo '<p>' . esc_html__( 'Shipping option names and prices are defined globally. Per-product shipping overrides are disabled in this mode.', 'ecommerce-product-catalog' ) . '</p>';
	}
	$shipping_option       = get_default_shipping_costs();
	$shipping_label_option = get_default_shipping_labels();
	$shipping_values       = array(
		'label' => array(),
		'cost'  => array(),
	);
	$price_placeholders    = array(
		'value' => array(),
	);
	for ( $i = 1; $i <= get_shipping_options_number(); $i++ ) {
		$shipping_option_field = get_post_meta( $post->ID, '_shipping' . $i, true );
		$shipping_label_field  = get_post_meta( $post->ID, '_shipping-label' . $i, true );
		$shipping              = '';
		$shipping_placeholder  = '';
		if ( $shipping_option_field !== null && $shipping_option_field !== '' ) {
			$shipping = floatval( $shipping_option_field );
		} elseif ( isset( $shipping_option[ $i ] ) ) {
			if ( is_ic_new_product_screen() || ! ic_product_shipping_prices_per_product() ) {
				$shipping = floatval( $shipping_option[ $i ] );
			} elseif ( $shipping_mode === 'price_per_product' ) {
				$shipping_placeholder = floatval( $shipping_option[ $i ] );
			}
		}
		if ( ic_product_shipping_labels_per_product() && ! empty( $shipping_label_field ) ) {
			$shipping_label = $shipping_label_field;
		} else {
			$shipping_label = isset( $shipping_label_option[ $i ] ) ? $shipping_label_option[ $i ] : '';
		}
		$shipping_values['label'][ $i ]    = $shipping_label;
		$shipping_values['cost'][ $i ]     = $shipping;
		$price_placeholders['value'][ $i ] = $shipping_placeholder;
	}
	ic_render_product_shipping_table(
		array(
			'currency'           => $currency,
			'values'             => $shipping_values,
			'price_placeholders' => $price_placeholders,
			'label_name_pattern' => ic_product_shipping_labels_per_product() ? '_shipping-label%d' : '',
			'cost_name_pattern'  => ic_product_shipping_prices_per_product() ? '_shipping%d' : '',
			'label_disabled'     => ! ic_product_shipping_labels_per_product(),
			'price_disabled'     => ! ic_product_shipping_prices_per_product(),
		)
	);
	do_action( 'product_shipping_metabox', $post->ID );
}

add_filter( 'product_meta_save', 'ic_save_product_shipping', 1, 2 );

/**
 * Saves product attributes
 *
 * @param type $product_meta
 *
 * @return type
 */
function ic_save_product_shipping( $product_meta, $post ) {
	$max_shipping = get_shipping_options_number();
	for ( $i = 1; $i <= $max_shipping; $i++ ) {
		if ( isset( $_POST[ '_shipping' . $i ] ) ) {
			$product_meta[ '_shipping' . $i ] = $_POST[ '_shipping' . $i ];
		}
		if ( isset( $_POST[ '_shipping-label' . $i ] ) ) {
			$product_meta[ '_shipping-label' . $i ] = ! empty( $_POST[ '_shipping-label' . $i ] ) ? $_POST[ '_shipping-label' . $i ] : '';
		}
	}

	return $product_meta;
}
