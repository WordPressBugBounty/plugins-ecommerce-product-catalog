<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages quote cart includes folder
 *
 * Here includes folder files defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */

/**
 * Adds product added info near shopping cart button
 *
 * @param string $button
 *
 * @return string
 */
function ic_cart_added_info_button( $button, $product_id = null ) {
	$added_info = ic_product_cart_added_info( $product_id );
	if ( ! empty( $added_info ) ) {
		if ( empty( $product_id ) ) {
			$product_id = get_the_ID();
		}
		if ( ! ic_has_product_variations( $product_id ) ) {
			add_filter( 'ic_product_page_quantity_field', 'ic_clear_variable' );
			add_filter( 'shopping_cart_button_info', 'ic_clear_variable' );
			$button = $added_info;
		} else {
			$button .= $added_info;
		}
	} else {
		remove_filter( 'ic_product_page_quantity_field', 'ic_clear_variable' );
		remove_filter( 'shopping_cart_button_info', 'ic_clear_variable' );
	}

	return $button;
}

/**
 * Returns cart added info
 *
 * @return string
 */
function ic_product_cart_added_info( $product_id ) {
	$cart_page      = ic_shopping_cart_page_url();
	$recently_added = false;
	$show           = false;
	$class          = '';
	$cart_content   = shopping_cart_products_array();
	if ( empty( $product_id ) ) {
		$product_id = get_the_ID();
	}
	if ( is_ic_product_in_cart( $product_id, $cart_content, false ) ) {
		$show = true;
		if ( ic_has_product_variations( $product_id ) && empty( $recently_added ) ) {
			$class = 'ic-hidden';
		}
	}
	$show = apply_filters( 'ic_show_product_cart_added_info', $show );
	if ( ! empty( $cart_page ) && ( ! empty( $recently_added ) || $show ) ) {
		return ic_product_cart_added_info_html( $class, $cart_page );
	}

	return '';
}
function ic_product_cart_added_info_html( $class = '', $cart_page = '' ) {

	$settings = get_shopping_cart_settings();
		/* translators: Placeholders are substituted with runtime values. */
	$info = isset( $settings['cart_added_info'] ) ? $settings['cart_added_info'] : sprintf( __( 'Added! %1$sSee your cart%2$s.', 'ecommerce-product-catalog' ), '[cart_url_start]', '[cart_url_end]' );
	if ( ! empty( $info ) ) {
		return '<span class="al-box success cart-added-info ' . $class . '">' . str_replace(
			array(
				'[cart_url_start]',
				'[cart_url_end]',
			),
			array( '<a href="' . $cart_page . '">', '</a>' ),
			$info
		) . '</span>';
	}
}
