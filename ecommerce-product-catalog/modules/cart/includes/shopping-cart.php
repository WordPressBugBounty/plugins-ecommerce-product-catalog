<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Adds EPC-owned shopping cart AJAX nonces to the catalog product object.
 *
 * @param array $nonces Existing nonce map.
 * @return array
 */
function ic_epc_shopping_cart_product_object_nonces( $nonces ) {
	$nonces['shopping_cart_products_nonce'] = wp_create_nonce( 'shopping_cart_products' );
	$nonces['cart_price_nonce']             = wp_create_nonce( 'ic_price_format' );

	return $nonces;
}
add_filter( 'ic_catalog_product_object_nonces', 'ic_epc_shopping_cart_product_object_nonces' );

/**
 * Manages shopping cart
 *
 * Here shopping cart functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-shopping-cart/includes
 * @author        Norbert Dreszer
 */
if ( ! function_exists( 'shopping_cart_products' ) ) {

	function shopping_cart_products( $raw = 1 ) {
		$settings = get_shopping_cart_settings();

		return ic_cart_products( $raw, true, 'cart_content', $settings );
	}

}

if ( ! function_exists( 'shopping_cart_products_array' ) ) {

	function shopping_cart_products_array( $cart_content = null ) {
		return ic_cart_products_array( $cart_content, 'cart_content' );
	}

}

/**
 * Returns current cart product total net
 *
 * @return type
 * @global type $ic_shopping_cart_totals
 */
function shopping_cart_products_total() {
	global $ic_shopping_cart_totals;
	if ( isset( $ic_shopping_cart_totals['total'] ) ) {
		return $ic_shopping_cart_totals['total'];
	} else {
		$products  = shopping_cart_products_array();
		$total_net = 0;
		foreach ( $products as $product_id => $p_quantity ) {
			$cart_id    = $product_id;
			$product_id = cart_id_to_product_id( $cart_id );
			// $product_price     = apply_filters( 'shopping_cart_product_price', product_price( $product_id, 1 ), $cart_id, $p_quantity );
			$product_price = get_shopping_cart_product_price( $product_id, $cart_id, $p_quantity );
			$product_total = floatval( $product_price ) * $p_quantity;
			$total_net    += $product_total;
		}

		return $total_net;
	}
}

if ( ! function_exists( 'ic_ajax_price_format' ) ) {
	add_action( 'wp_ajax_nopriv_ic_price_format', 'ic_ajax_price_format' );
	add_action( 'wp_ajax_ic_price_format', 'ic_ajax_price_format' );

	/**
	 * Manages ajax price format
	 */
	function ic_ajax_price_format() {
		$nonce = isset( $_REQUEST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ic_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_price_format' ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$price = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public nopriv AJAX formatting endpoint: it echoes a formatted number for the visitor's own cart and writes nothing; the value is sanitized on this line.
		if ( isset( $_POST['price'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public nopriv AJAX formatting endpoint: it echoes a formatted number for the visitor's own cart and writes nothing; the value is sanitized on this line.
			$price = price_format( raw_price_format( sanitize_text_field( wp_unslash( $_POST['price'] ) ) ) );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: price_format() output, which emits the currency markup. The underlying value came from $_POST but was already run through sanitize_text_field( wp_unslash() ) and raw_price_format() above; it is the formatter's tags that must survive here.
		echo $price;
		wp_die();
	}

}

add_action( 'wp_ajax_shopping_cart_products', 'ic_ajax_shopping_cart_products' );
add_action( 'wp_ajax_nopriv_shopping_cart_products', 'ic_ajax_shopping_cart_products' );

/**
 * Handles ajax shopping cart products table
 */
function ic_ajax_shopping_cart_products() {
	ic_verify_public_cart_nonce( 'shopping_cart_products' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public nopriv AJAX endpoint that renders the visitor's own cart table and writes nothing; the flag is cast with intval() on this line.
	$raw = isset( $_POST['raw'] ) ? intval( $_POST['raw'] ) : 1;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by shopping_cart_products().
	echo shopping_cart_products( $raw );
	wp_die();
}

add_shortcode( 'cart_button', 'ic_shopping_cart_button' );

/**
 * Returns shopping cart button
 */
function ic_shopping_cart_button( $show_empty = true, $label = '' ) {
	$cart_content = ic_shopping_cart_content( true );
	$how_many     = ic_get_cart_items_count( $cart_content );
	$attr         = '';
	if ( ! empty( $label ) ) {
		$attr = 'data-label="' . esc_attr( $label ) . '"';
	}
	if ( $how_many || $show_empty ) {
		$content   = '<div id="shopping_cart_widget" class="shopping-cart-widget" ' . $attr . '>';
		$content  .= '<div class="product-shopping-cart">';
		$user_cart = ic_get_customer_cart();
		if ( empty( $how_many ) && ! empty( $user_cart ) ) {
			$content .= '<a href="#" class="restore-ic-cart button ' . design_schemes( 'box', 0 ) . '"><span class="cart_button_text">' . __( 'Restore Previous Cart', 'ecommerce-product-catalog' ) . '</span></a>';
		} else {
			$attr = '';
			if ( empty( $label ) ) {
				$label = $how_many . ' ' . __( 'selected', 'ecommerce-product-catalog' );
			}
			$content .= '<a href="' . ic_shopping_cart_page_url() . '" class="button ' . design_schemes( 'box', 0 ) . '"><span class="cart_button_text">' . wp_kses_post( $label ) . '</span></a>';
		}
		$content .= '</div>';
	} else {
		$content = '<div id="shopping_cart_widget" class="empty-cart shopping-cart-widget" ' . $attr . '>';
	}
	$content .= '</div>';

	return $content;
}
