<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Adds the cart-content AJAX nonce to the catalog product object.
 *
 * @param array $nonces Existing nonce map.
 * @return array
 */
function ic_cart_cache_product_object_nonces( $nonces ) {
	$nonces['cart_content_nonce'] = wp_create_nonce( 'ic_get_cart_content' );

	return $nonces;
}
add_filter( 'ic_catalog_product_object_nonces', 'ic_cart_cache_product_object_nonces' );

/**
 * Manages shopping cart
 *
 * Here shopping cart functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */
add_action( 'wp_ajax_nopriv_ic_get_cart_content', 'ic_ajax_cart_content' );
add_action( 'wp_ajax_ic_get_cart_content', 'ic_ajax_cart_content' );

function ic_ajax_cart_content() {
	$nonce = isset( $_REQUEST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ic_nonce'] ) ) : '';
	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_get_cart_content' ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	$cart = null;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public cart cache endpoint.
	if ( ! empty( $_POST['cart'] ) ) {
		$cart = sanitize_text_field( wp_unslash( $_POST['cart'] ) );
	}
	$cart_array = ic_decode_json_cart( ic_cart_get( $cart ) );
	if ( ! empty( $cart_array ) ) {
		$cart_name = empty( $cart ) ? 'cart_content' : $cart;
		$cart_page = '';
		if ( 'quote_cart_content' === $cart_name && function_exists( 'quote_cart_page_url' ) ) {
			$cart_page = quote_cart_page_url();
			if ( function_exists( 'ic_quote_cart_added_info_html' ) ) {
				$cart_array['added_button_html'] = ic_quote_cart_added_info_html( '', $cart_page );
			}
		} elseif ( function_exists( 'ic_shopping_cart_page_url' ) ) {
			$cart_page = ic_shopping_cart_page_url();
			if ( function_exists( 'ic_product_cart_added_info_html' ) ) {
				$cart_array['added_button_html'] = ic_product_cart_added_info_html( '', $cart_page );
			}
		}
		$cart_array['cart_page'] = $cart_page;
		$cart_array['cart_name'] = $cart_name;
		echo wp_json_encode( $cart_array );
	}
	wp_die();
}

//add_action( 'wp', 'ic_exclude_quote_pages_caching' );

/**
 * Exclude caching for checkout pages
 */
function ic_exclude_quote_pages_caching() {
	if ( is_ic_catalog_page() ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', 1 );
		}
	}
}
