<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages paypal settings
 *
 * Here all paypal settings are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-paypal-getaway/includes
 * @author        Norbert Dreszer
 */
if ( ! function_exists( 'validate_payment_data' ) ) {

	function validate_payment_data( $character_set, $data ) {
		return ic_validate_payment_data( $character_set, $data );
	}

}

if ( ! function_exists( 'ic_validate_payment_data' ) ) {

	function ic_validate_payment_data( $character_set, $data ) {
		if ( ! is_array( $data ) && ! empty( $data ) ) {
			$charset = get_option( 'blog_charset' );
			ic_error_log( 'validate payment' );
			ic_error_log( $data );
			$data = urldecode( $data );
			ic_error_log( $data );

			if ( $character_set ) {
				$data = iconv( $character_set, $charset, $data );
			}
			ic_error_log( $data );
			$data = htmlspecialchars( $data, ENT_COMPAT, $charset );
			ic_error_log( $data );

		}

		return $data;
	}

}


/**
 * Verifies if payment amount is the same or more than expected amount
 *
 * @param array|int $item_id
 * @param float $payment_amount
 * @param string $payment_currency
 *
 * @return boolean
 */
function ic_verify_payment_price( $item_id, $payment_amount, $payment_currency, $order_products, $trans_id ) {
	$expected_currency = apply_filters( 'ic_expected_currency', get_product_currency_code(), $trans_id );
	$expected_total    = 0;
	if ( ! is_array( $item_id ) ) {
		$product_price  = product_price( $item_id, 'unfiltered' );
		$expected_total = floatval( apply_filters( 'expected_order_amout', $product_price, $payment_amount, $item_id, $trans_id, $order_products ) );
	} else if ( isset( $item_id[0]['product_id'] ) ) {
		foreach ( $item_id as $product ) {
			$product_price   = product_price( $product['product_id'], 'unfiltered' );
			$expected_amount = apply_filters( 'expected_order_amout', $product_price, $payment_amount, $product['product_id'], $trans_id, $order_products );
			$expected_total  += floatval( $expected_amount );
		}
	} else {
		foreach ( $item_id as $product_id ) {
			$product_price   = product_price( $product_id, 'unfiltered' );
			$expected_amount = apply_filters( 'expected_order_amout', $product_price, $payment_amount, $product_id, $trans_id, $order_products );
			$expected_total  += floatval( $expected_amount );
		}
	}
	$expected_total = apply_filters( 'expected_total_amount', $expected_total, $trans_id );
	if ( $expected_total - $payment_amount > 0.01 || strval( $expected_currency ) !== strval( $payment_currency ) ) {
		ic_send_error_message( 'error', 'expected ' . floatval( $expected_total ) . 'paid ' . floatval( $payment_amount ) . 'curr exp ' . $expected_currency . 'paid curr ' . $payment_currency );

		return false;
	}

	return true;
}

add_filter( 'ic_expected_currency', 'ic_expected_currency', 99, 2 );

function ic_expected_currency( $currency, $trans_id ) {
	$order_id = ic_order_exists( $trans_id );
	if ( empty( $order_id ) ) {
		return $currency;
	}
	$current_order_currency = ic_order_currency( $order_id );
	if ( ! empty( $current_order_currency ) ) {
		return $current_order_currency;
	}

	return $currency;
}

add_filter( 'expected_order_amout', 'ic_expected_order_amount', 99, 4 );

function ic_expected_order_amount( $product_price, $payment_amount, $product_id, $trans_id = null ) {
	$order_id = ic_order_exists( $trans_id );
	if ( empty( $order_id ) ) {
		return $product_price;
	}
	$order_product_price = ic_get_order_product_price( $order_id, $product_id );
	if ( ! empty( $order_product_price ) ) {
		return $order_product_price;
	}

	return $product_price;
}


function ic_digital_order_status( $trans_id, $order_id = null ) {
	// Never leave $payment_details undefined: the order may not exist at all.
	$payment_details = array();
	if ( empty( $order_id ) ) {
		$order_id = ic_order_exists( $trans_id );
	}
	if ( ! empty( $order_id ) ) {
		$payment_details = get_post_meta( $order_id, '_payment_details', true );
		// Missing meta returns an empty string; writing an offset on it is a PHP 8 TypeError.
		if ( ! is_array( $payment_details ) ) {
			$payment_details = array();
		}
	}
	$payment_details['status'] = isset( $payment_details['status'] ) ? $payment_details['status'] : 'pending';

	return $payment_details['status'];
}

function ic_create_digital_order() {
	$order_id = wp_insert_post( array( 'post_type' => 'al_digital_orders', 'post_status' => 'publish' ), true );
	if ( empty( $order_id ) || is_wp_error( $order_id ) ) {
		$order_id = 0;
	}

	return $order_id;
}

function ic_order_exists( $trans_id ) {
	if ( ! is_scalar( $trans_id ) || '' === $trans_id ) {
		return;
	}
	$trans_id = sanitize_text_field( (string) $trans_id );
	if ( '' === $trans_id ) {
		return;
	}
	$order_id = ic_get_global( 'trans_id_to_order_id_' . $trans_id );
	if ( $order_id !== false ) {
		return $order_id;
	}
	global $wpdb;
	$querystr = $wpdb->prepare(
		"SELECT wposts.ID
FROM {$wpdb->posts} AS wposts
INNER JOIN {$wpdb->postmeta} AS wpostmeta
ON wpostmeta.post_id = wposts.ID
AND wpostmeta.meta_key = %s
AND wpostmeta.meta_value = %s
AND wposts.post_type = %s
AND wposts.post_status = %s",
		'_order_id',
		$trans_id,
		'al_digital_orders',
		'publish'
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- This bounded custom-table query uses framework-owned identifiers and prepared values; no equivalent cached WordPress API preserves its semantics.
	$order_id = (int) $wpdb->get_var( $querystr );
	if ( ! empty( $order_id ) ) {
		ic_save_global( 'trans_id_to_order_id_' . $trans_id, $order_id );

		return $order_id;
	}
	if ( is_numeric( $trans_id ) ) {
		$check_trans_id = get_post_meta( $trans_id, '_order_id', true );
		if ( ! empty( $check_trans_id ) ) {
			return $trans_id;
		}
	}
}

function ic_update_digital_order_status(
	$payment_details, $order_products, $order_summary, $trans_id,
	$just_status = false
) {
	if ( ! is_scalar( $trans_id ) ) {
		$trans_id = '';
	} else {
		$trans_id = sanitize_text_field( (string) $trans_id );
	}
	$order_id     = ic_order_exists( $trans_id );
	$just_created = false;
	if ( empty( $order_id ) && ! empty( $order_products ) ) {
		$just_created = true;
		$order_id     = ic_create_digital_order();
		if ( empty( $order_id ) ) {
			return 0;
		}
		if ( empty( $trans_id ) ) {
			$trans_id = ic_generate_new_trans_id();
		}
		$session             = get_product_catalog_session();
		$session['trans_id'] = $trans_id;
		set_product_catalog_session( $session );
		do_action( 'ic_new_order_created', $payment_details, $order_products, $order_summary, $trans_id, $order_id );
	}
	if ( empty( $order_id ) || is_wp_error( $order_id ) ) {
		return $order_id;
	}
	$prev_payment_details = get_post_meta( $order_id, '_payment_details', true );
	if ( isset( $prev_payment_details['shipping_email'] ) && is_email( $prev_payment_details['shipping_email'] ) ) {
		$payment_details['shipping_email'] = $prev_payment_details['shipping_email'];
	}
	if ( $just_status && ! empty( $payment_details['status'] ) && ! $just_created ) {
		$current_status = isset( $prev_payment_details['status'] ) ? $prev_payment_details['status'] : '';
		if ( ic_should_auto_promote_shipped_status( $payment_details['status'], $current_status, $order_id ) ) {
			$completion_payment_details           = $payment_details;
			$completion_payment_details['status'] = ic_get_order_completion_status();
			ic_update_digital_order_status(
				$completion_payment_details,
				$order_products,
				$order_summary,
				! empty( $trans_id ) ? $trans_id : $order_id,
				true
			);
			$prev_payment_details = get_post_meta( $order_id, '_payment_details', true );
			$current_status       = isset( $prev_payment_details['status'] ) ? $prev_payment_details['status'] : '';
			if ( ! ic_is_order_completion_status( $current_status ) ) {
				return $order_id;
			}
		}
		$prev_should_be_taxed = ic_should_digital_order_be_taxed( $order_id );
		if ( ! empty( $order_summary['price'] ) ) {
			$payment_details['payment_price'] = $order_summary['price'];
		}
		$prev_payment_details = get_post_meta( $order_id, '_payment_details', true );
		$new_payment_details  = array_merge( $prev_payment_details, $payment_details );
		//$prev_payment_details[ 'status' ]	 = $payment_details[ 'status' ];
		update_post_meta( $order_id, '_payment_details', $new_payment_details );
		if ( ! empty( $order_summary ) ) {
			$prev_order_summary = get_post_meta( $order_id, '_order_summary', true );
			if ( floatval( $prev_order_summary['price'] ) !== floatval( $order_summary['price'] ) ) {
				if ( ! empty( $prev_order_summary['email'] ) ) {
					unset( $order_summary['email'] );
				}
				if ( ! empty( $prev_order_summary['tax'] ) ) {
					$order_summary['total_net'] = $prev_order_summary['price'] - $prev_order_summary['tax'];
				}
				$new_order_summary = array_merge( $prev_order_summary, $order_summary );
				update_post_meta( $order_id, '_order_summary', $new_order_summary );
			}
			if ( $payment_details['status'] !== 'pending' && empty( $order_summary['tax'] ) && empty( $new_order_summary['tax'] ) && ! $prev_should_be_taxed && ic_should_digital_order_be_taxed( $order_id ) ) {
				$payment_details['status']     = 'pending';
				$new_payment_details['status'] = 'pending';
				update_post_meta( $order_id, '_payment_details', $new_payment_details );
			}
		}
	} else {
		if ( ! empty( $payment_details ) ) {
			update_post_meta( $order_id, '_payment_details', $payment_details );
		}
		if ( ! empty( $order_products ) ) {
			update_post_meta( $order_id, '_order_products', $order_products );
		}
		if ( ! empty( $order_summary ) ) {
			update_post_meta( $order_id, '_order_summary', $order_summary );
		}
		if ( ! empty( $trans_id ) ) {
			update_post_meta( $order_id, '_order_id', $trans_id );
		}
	}
	$triggered = get_post_meta( $order_id, '_order_completed_triggered', true );
	if ( empty( $triggered ) && ic_is_order_completion_status( $payment_details['status'] ) ) {

		$payment_details = ic_get_order_payment_details( $order_id );

		if ( empty( $order_products ) ) {
			$order_products = get_post_meta( $order_id, '_order_products', true );
		}
		$session = get_product_catalog_session();
		if ( isset( $session['trans_id'] ) ) {
			unset( $session['trans_id'] );
			set_product_catalog_session( $session );
		}
		do_action( 'order_completed', $order_id, $payment_details, $order_products );
		if ( ! current_user_can( 'edit_digital_orders' ) ) {
			do_action( 'auto_order_completed', $order_id, $payment_details, $order_products );
		}
		update_post_meta( $order_id, '_order_completed_triggered', 1 );
	}

	return $order_id;
}

/**
 * Returns currently processing order customer ID
 *
 * @return type
 * @global type $verify_payment
 */
function ic_get_active_order_customer_id() {
	global $verify_payment;
	$customer_id = '';
	if ( isset( $verify_payment['payment_details']['shipping_email'] ) && is_email( $verify_payment['payment_details']['shipping_email'] ) ) {
		$customer_id = ic_get_customer_id_from_email( $verify_payment['payment_details']['shipping_email'] );
	}

	return apply_filters( 'ic_order_customer_id', $customer_id );
}
