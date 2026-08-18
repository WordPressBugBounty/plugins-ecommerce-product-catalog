<?php
/**
 * Shared customer saved-cart helpers.
 *
 * @package ic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_customers_save_cart_products' ) ) {
	/**
	 * Saves cart products for the provided or current customer.
	 *
	 * @param array    $cart_products Cart products payload.
	 * @param int|null $customer_id   Customer identifier.
	 *
	 * @return int
	 */
	function ic_customers_save_cart_products( $cart_products, $customer_id = null ) {
		if ( empty( $cart_products ) || ! is_array( $cart_products ) ) {
			return 0;
		}

		if ( ! is_int( $customer_id ) || ! get_userdata( $customer_id ) ) {
			$customer_id = get_current_user_id();
		}
		if ( empty( $customer_id ) ) {
			return 0;
		}

		update_user_meta( $customer_id, '_customer_cart', $cart_products );

		return (int) $customer_id;
	}
}

if ( ! function_exists( 'ic_customers_saved_cart_products' ) ) {
	/**
	 * Returns the saved cart payload for the provided or current customer.
	 *
	 * @param int|null $customer_id Customer identifier.
	 *
	 * @return mixed
	 */
	function ic_customers_saved_cart_products( $customer_id = null ) {
		if ( empty( $customer_id ) ) {
			$customer_id = function_exists( 'ic_cart_customer_id' ) ? ic_cart_customer_id() : get_current_user_id();
		}

		$user_cart = '';
		if ( ! empty( $customer_id ) ) {
			$user_cart = get_user_meta( $customer_id, '_customer_cart', true );
		}

		return apply_filters( 'ic_customer_cart', $user_cart );
	}
}

if ( ! function_exists( 'ic_customers_saved_cart_restore_state' ) ) {
	/**
	 * Builds shared restore-cart state from a saved cart payload.
	 *
	 * @param mixed $saved_cart Saved cart payload.
	 *
	 * @return array
	 */
	function ic_customers_saved_cart_restore_state( $saved_cart ) {
		$cart_content = ic_encode_string_cart( $saved_cart );
		$how_many     = ic_get_cart_items_count( $cart_content );

		return array(
			'cart_content' => $cart_content,
			'how_many'     => $how_many,
		);
	}
}
