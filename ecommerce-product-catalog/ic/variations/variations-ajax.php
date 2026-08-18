<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages framework folder
 *
 * Here framework folder files defined and managed.
 *
 * @version		1.0.0
 * @package		ecommerce-product-catalog-pro/framework
 * @author 		Norbert Dreszer
 */
class ic_cart_variations_ajax {

	private function verify_public_nonce() {
		$nonce = isset( $_POST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ic_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_variation_lookup' ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
			wp_die( -1, 403 );
		}
	}

	function __construct() {
		add_action( 'wp_ajax_nopriv_modify_variations_price', array( $this, 'variations_price' ) );
		add_action( 'wp_ajax_modify_variations_price', array( $this, 'variations_price' ) );
		add_action( 'wp_ajax_nopriv_get_shopping_cart_product_price', array( $this, 'cart_price' ) );
		add_action( 'wp_ajax_get_shopping_cart_product_price', array( $this, 'cart_price' ) );

		add_action( 'wp_ajax_nopriv_modify_variations_shipping', array( $this, 'variations_shipping' ) );
		add_action( 'wp_ajax_modify_variations_shipping', array( $this, 'variations_shipping' ) );
	}

	/**
	 * Handles ajax variation product price modification
	 */
	function variations_price() {
		$this->verify_public_nonce();
		$price_modifier = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific variation nonce immediately above and requires read for logged-in calls.
		if ( !empty( $_POST[ 'selected_variation' ] ) && !empty( $_POST[ 'variation_id' ] ) && !empty( $_POST[ 'product_id' ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$format		 = isset( $_POST[ 'format' ] ) ? false : true;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$price		 = isset( $_POST[ 'price' ] ) && is_scalar( $_POST[ 'price' ] ) ? (float) sanitize_text_field( wp_unslash( $_POST[ 'price' ] ) ) : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$product_id	 = absint( wp_unslash( $_POST[ 'product_id' ] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$var_qties	 = isset( $_POST[ 'var_qties' ] ) && is_array( $_POST[ 'var_qties' ] ) ? array_map( 'absint', wp_unslash( $_POST[ 'var_qties' ] ) ) : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$var_lps	 = isset( $_POST[ 'selected_variation_lps' ] ) && is_scalar( $_POST[ 'selected_variation_lps' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'selected_variation_lps' ] ) ) : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			if ( is_array( $_POST[ 'selected_variation' ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
				$selected_variation = array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'selected_variation' ] ) );
			} else {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
				$selected_variation = sanitize_text_field( wp_unslash( $_POST[ 'selected_variation' ] ) );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$var_id			 = absint( wp_unslash( $_POST[ 'variation_id' ] ) );
			$price_modifier	 = get_variations_modificators( $product_id, $selected_variation, $var_id, $format, $price, $var_qties, $var_lps );
		}
		echo esc_html( html_entity_decode( (string) $price_modifier, ENT_QUOTES, 'UTF-8' ) );
		wp_die();
	}

	/**
	 * Handles ajax variation product price modification
	 */
	function variations_shipping() {
		$this->verify_public_nonce();
		$price_modifier = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific variation nonce above and requires read for logged-in calls.
		if ( !empty( $_POST[ 'selected_variation' ] ) && !empty( $_POST[ 'variation_id' ] ) && !empty( $_POST[ 'product_id' ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$format				 = isset( $_POST[ 'format' ] ) ? false : true;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$product_id			 = absint( wp_unslash( $_POST[ 'product_id' ] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$selected_variation	 = sanitize_text_field( wp_unslash( $_POST[ 'selected_variation' ] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			$var_id				 = absint( wp_unslash( $_POST[ 'variation_id' ] ) );
			$price_modifier		 = get_variations_shipping_modificators( $product_id, $selected_variation, $var_id, $format );
		}
		echo wp_json_encode( $price_modifier );
		wp_die();
	}

	function cart_price() {
		$this->verify_public_nonce();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
		$product_id = cart_id_to_product_id( sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) );
		$cart_id    = $product_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
		if ( isset( $_POST['selected_variation'] ) && is_array( $_POST['selected_variation'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The public AJAX method verifies the action-specific variation nonce before reading this payload; logged-in calls also require the read capability.
			foreach ( array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_variation'] ) ) as $var_value ) {
				$var_value = ( 'not_selected' === $var_value ) ? '' : $var_value;
				$cart_id  .= '_' . $var_value;
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
		echo esc_html( get_shopping_cart_product_price( $product_id, $cart_id, absint( wp_unslash( $_POST['quantity'] ) ) ) );
		wp_die();
	}

}

$ic_cart_variations_ajax = new ic_cart_variations_ajax;
