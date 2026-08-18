<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages quote cart functions folder
 *
 * Here includes folder files defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */
add_action( 'wp', 'ic_shopping_cart_redirect_empty_cart' );

/**
 * Redirect checkout to cart if cart empty
 */
function ic_shopping_cart_redirect_empty_cart() {
	if ( function_exists( 'ic_cart_should_redirect_empty_checkout' ) && ic_cart_should_redirect_empty_checkout( true ) ) {
		wp_safe_redirect( ic_shopping_cart_page_url() );
		exit;
		exit;
	}
}

add_action( 'init', 'ic_add_recent_new_product_in_cart', 5 );

/**
 * Add recently added product to session
 */
function ic_add_recent_new_product_in_cart() {
	if ( ! is_admin() ) {
		$shopping_cart_settings = get_shopping_cart_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public add-to-cart post that only records the product in the current visitor's own cart session; the id is cast with intval() below.
		if ( $shopping_cart_settings['cart_redirect'] != 1 && ! empty( $_POST['current_product'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public add-to-cart post scoped to the visitor's own session; the id is cast with intval() on this line.
			ic_cart_store_recent_product( intval( $_POST['current_product'] ) );
		}
	}
}
