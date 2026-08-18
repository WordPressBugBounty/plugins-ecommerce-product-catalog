<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages shopping form
 *
 * Here shopping form are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-shopping-cart/includes
 * @author        Norbert Dreszer
 */
class ic_cart_checkout_form {

	function __construct() {
		global $ic_cart_checkout_form_email;
		$ic_cart_checkout_form_email = new ic_cart_checkout_form_email();
		add_filter( 'ic_formbuilder_form_beginning', array( __CLASS__, 'add_checkout_products' ), 5, 2 );
		add_filter( 'ic_formbuilder_form_beginning', array( __CLASS__, 'add_checkout_hidden_fields' ), 10, 2 );

		add_filter( 'payment_order_details', array( __CLASS__, 'payment_details' ) );
	}

	/**
	 * Adds ordered products to checkout form
	 *
	 * @param string $content
	 * @param string $pre_name
	 *
	 * @return string
	 */
	static function add_checkout_products( $content, $pre_name ) {
		if ( $pre_name == 'cart_' ) {
			$content .= shopping_cart_products();
		}

		return $content;
	}

	/**
	 * Adds shopping cart checkout hidden fields
	 *
	 * @param string $content
	 * @param string $pre_name
	 *
	 * @return string
	 */
	static function add_checkout_hidden_fields( $content, $pre_name ) {
		if ( $pre_name == 'cart_' ) {
			$product_group = function_exists( 'ic_cart_product_group_from_cart_content' ) ? ic_cart_product_group_from_cart_content( null, 'cart_content' ) : 'al_product';
			if ( empty( $product_group ) ) {
				$product_group = 'al_product';
			}
			$cart_type     = '<input hidden type="hidden" name="cart_type" value="order">';
			$product_group = '<input hidden type="hidden" name="product_group" value="' . esc_attr( $product_group ) . '">';
			$content      .= apply_filters( 'cart_mail_hidden_fields', $cart_type . $product_group );
		}

		return $content;
	}

	static function payment_details( $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Short-circuited by the leading true; the presence check is never evaluated and nothing is written from it.
		if ( true || isset( $_POST['cart_submit'] ) ) {
			$shopping_cart_settings = get_shopping_cart_settings();
			$cart_content           = ic_shopping_cart_content( true );
			$products_array         = shopping_cart_products_array( $cart_content );
			$total_net              = 0;
			$total_gross            = 0;
			$total_tax              = 0;
			$taxed                  = function_exists( 'is_ic_order_taxed' ) ? is_ic_order_taxed() : false;
			// $currency_settings         = get_currency_settings();
			$tax_rate = function_exists( 'get_cart_tax_rate' ) ? get_cart_tax_rate() : 0;
			foreach ( $products_array as $cart_id => $p_quantity ) {
				$product_id            = cart_id_to_product_id( $cart_id );
				$order['product'][]    = apply_filters( 'cart_payment_product_name', get_product_name( $product_id ), $cart_id, $product_id );
				$order['product_id'][] = $product_id;
				$order['quantity'][]   = $p_quantity;
				$order['cart_id'][]    = $cart_id;
				// $product_price             = apply_filters( 'shopping_cart_product_price', product_price( $product_id, 1 ), $cart_id, $p_quantity );
				$product_net_price = get_shopping_cart_product_price( $product_id, $cart_id, $p_quantity );
				$product_net_price = floatval( $product_net_price );

				if ( $taxed ) {
					if ( is_ic_tax_included() ) {
						$tax_rate_c    = $tax_rate['tax_rate'] / 100;
						$taxed_product = $product_net_price;
						// $product_net_price     = ic_roundto( $product_net_price / (1 + $tax_rate_c), $tax_rate[ 'tax_rate_round' ] );
						$product_net_price = $product_net_price / ( 1 + $tax_rate_c );
						$product_tax       = $taxed_product - $product_net_price;
						if ( function_exists( 'ic_tax_round_per_item' ) && ic_tax_round_per_item() ) {
							$product_tax = ic_round_tax( $product_tax );
						}
					} else {
						$product_tax = ic_get_price_tax( $product_net_price, $product_id );
					}
					$order['tax'][] = $product_tax;
				}
				$order['sum'][]    = ic_payment_number_format( $product_net_price );
				$product_total_net = $product_net_price * $p_quantity;
				$taxed_total       = $product_total_net;
				if ( $taxed ) {
					if ( ! is_ic_tax_included() ) {
						$taxed_product = $product_net_price + $product_tax;
						$taxed_total   = $taxed_product * $p_quantity;
						// $taxed_product     = ic_roundto( $product_net_price + $product_tax, $tax_rate[ 'tax_rate_round' ] );
					}
					$order['product_gross'][]       = ic_payment_number_format( $taxed_product );
					$order['product_total_net'][]   = ic_payment_number_format( $product_total_net );
					$order['product_total_gross'][] = ic_payment_number_format( $taxed_total );
					$order['product_total'][]       = ic_payment_number_format( $taxed_total );
					$order['product_tax_rate'][]    = ic_get_tax_rate( $product_id );
				} else {
					$order['product_total_net'][] = ic_payment_number_format( $product_total_net );
					$order['product_total'][]     = ic_payment_number_format( $product_total_net );
				}
				$total_net   += $product_total_net;
				$total_gross += $taxed_total;
				if ( $taxed ) {
					$total_tax += $product_tax * $p_quantity;
				}
			}
			if ( function_exists( 'ic_tax_round_per_item' ) && ! ic_tax_round_per_item() ) {
				$total_tax = ic_round_tax( $total_tax );
			}
			if ( is_ic_tax_included() && ! empty( $total_gross ) ) {
				$order_total = $total_gross;
			} else {
				$order_total = $total_net + $total_tax;
			}
			$order['total_net']    = ic_payment_number_format( $order_total - $total_tax );
			$order['taxed']        = ic_payment_number_format( $order_total );
			$order['tax']          = ic_payment_number_format( $total_tax );
			$order['currency']     = get_product_currency_code();
			$order['success_page'] = ic_get_permalink( $shopping_cart_settings['thank_you_page'] );
			$handling              = apply_filters( 'shopping_cart_order_handling', 0, 'cart_' );
			$order['handling']     = 0;
			if ( ! empty( $handling ) ) {
				$order['handling'] = ic_payment_number_format( $handling );
			}
			$order['total_taxed'] = ic_payment_number_format( $order['taxed'] + $order['handling'] );
		}

		return $order;
	}
}

$ic_cart_checkout_form = new ic_cart_checkout_form();
