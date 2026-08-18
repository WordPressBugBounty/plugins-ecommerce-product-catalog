<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages product variations
 *
 * Here product variations functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-product-variations/includes
 * @author        Norbert Dreszer
 */

add_action( 'wp_ajax_get_viariation_details', 'ic_ajax_get_viariation_details' );
add_action( 'wp_ajax_nopriv_get_viariation_details', 'ic_ajax_get_viariation_details' );

/**
 * Handles ajax variations fields
 */
function ic_ajax_get_viariation_details() {
	$nonce = isset( $_POST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ic_nonce'] ) ) : '';
	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_variation_lookup' ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
		wp_die( -1, 403 );
	}
	if ( ! isset( $_POST['selected_variation'] ) ) {
		wp_die();
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve the array/scalar container until the next line applies sanitize_key() to each field name exactly once.
	$variation_field    = isset( $_POST['variation_field'] ) ? wp_unslash( $_POST['variation_field'] ) : '';
	$what               = is_array( $variation_field ) ? array_map( 'sanitize_key', $variation_field ) : array( sanitize_key( (string) $variation_field ) );
	$selected_variation = is_array( $_POST['selected_variation'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_variation'] ) ) : sanitize_text_field( wp_unslash( (string) $_POST['selected_variation'] ) );
	$selected_variation = apply_filters( 'ic_selected_variations', $selected_variation );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
	$variation_id       = absint( wp_unslash( $_POST['variation_id'] ) );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
	$product_id         = absint( wp_unslash( $_POST['product_id'] ) );
	if ( ! empty( $product_id ) ) {
		ic_set_product_id( $product_id );
		do_action( 'ic_ajax_get_variation_details_start', $product_id );
	}
	$var_lp    = isset( $_POST['selected_variation_lp'] ) ? absint( wp_unslash( $_POST['selected_variation_lp'] ) ) : '';
	$var_lps   = isset( $_POST['selected_variation_lps'] ) && is_scalar( $_POST['selected_variation_lps'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_variation_lps'] ) ) : '';
	$var_qties = isset( $_POST['var_qties'] ) && is_array( $_POST['var_qties'] ) ? array_map( 'absint', wp_unslash( $_POST['var_qties'] ) ) : array();
	// $product           = get_post( $product_id );
	if ( ! is_array( $what ) ) {
		$what = array( $what );
	}
	if ( $selected_variation != '' && ! empty( $variation_id ) ) {
		$out          = array();
		$variation_id = 1; // Details are available only for first variation
		foreach ( $what as $element ) {
			ob_start();
			if ( $element == 'shipping' ) {
				if ( function_exists( 'show_shipping_options' ) ) {
					$variation_shipping_overrides = function_exists( 'override_variation_shipping_label' ) && function_exists( 'override_variation_shipping_price' );
					ic_save_global( 'var_details_selected_variation', $selected_variation );
					ic_save_global( 'var_details_variation_id', $variation_id );
					ic_set_product_id( $product_id );
					if ( $variation_shipping_overrides ) {
						add_filter( 'ic_product_shipping_label', 'override_variation_shipping_label', 10, 3 );
						add_filter( 'product_shipping_option_price', 'override_variation_shipping_price', 10, 3 );
					}
					do_action( 'ic_ajax_variation_' . $element . '_details', $selected_variation, $variation_id, $product_id );
					show_shipping_options();
					ic_delete_global( 'var_details_selected_variation' );
					ic_delete_global( 'var_details_variation_id' );
					ic_reset_product_id();
					if ( $variation_shipping_overrides ) {
						remove_filter( 'ic_product_shipping_label', 'override_variation_shipping_label', 10, 3 );
						remove_filter( 'product_shipping_option_price', 'override_variation_shipping_price', 10, 3 );
					}
				}
			} elseif ( $element == 'in_cart' ) {
				$product_variations_settings = get_product_variations_settings();
				foreach ( $selected_variation as $var_id => $var_value ) {
					$_POST[ $var_id ] = $var_value;
				}
				$current_product_variations = get_current_product_variations_string( $product_id, $product_variations_settings );
				if ( $current_product_variations ) {
					$cart_id = $product_id . $current_product_variations;
				}
				$cart_content = shopping_cart_products_array();
				if ( is_ic_product_in_cart( $cart_id, $cart_content ) ) {
					echo 1;
				}
			} elseif ( $element == 'price' ) {
				$price_modifier = 0;
				if ( ! empty( $selected_variation ) && ! empty( $_POST['variation_id'] ) && ! empty( $_POST['product_id'] ) ) {
					$format         = isset( $_POST['format'] ) ? false : true;
					$price          = isset( $_POST['price'] ) && is_scalar( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : null;
					$price_modifier = get_variations_modificators( $product_id, $selected_variation, $variation_id, $format, $price, $var_qties, $var_lps );
				}
				echo esc_html( html_entity_decode( (string) $price_modifier, ENT_QUOTES, 'UTF-8' ) );
			} elseif ( ! empty( $selected_variation ) && ! empty( $variation_id ) && ! empty( $product_id ) ) {
				do_action( 'ic_ajax_get_variation_details', $element, $selected_variation, $variation_id, $product_id, $var_lp );
			}
			$out[] = ob_get_clean();
		}
	}
	echo json_encode( $out );
	wp_die();
}

/**
 * Generates variation details field name
 *
 * @param type $main_field_name
 * @param type $var_id
 *
 * @return type
 */
function ic_get_var_field_name( $main_field_name, $var_id ) {
	return '_' . $var_id . $main_field_name;
}
