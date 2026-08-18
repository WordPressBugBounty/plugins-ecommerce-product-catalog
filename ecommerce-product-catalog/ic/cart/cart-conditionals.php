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
function is_ic_json_cart( $cart_content ) {
	if ( ! empty( $cart_content ) && is_string( $cart_content ) && isset( $cart_content[0] ) && $cart_content[0] === '{' ) {
		return true;
	}

	return false;
}

/**
 * Checks if provided cart id is already in cart
 *
 * @param type $cart_content
 * @param type $cart_id
 *
 * @return boolean
 */
function is_ic_product_in_cart( $cart_id, $cart_content = null, $strict = true ) {
	if ( empty( $cart_content ) ) {
		$cart_content = shopping_cart_products_array();
	}

	foreach ( $cart_content as $cart_content_cart_id => $quantity ) {
		if ( ic_string_contains( $cart_content_cart_id, '::' ) && ! ic_string_contains( $cart_id, '::' ) ) {
			$ex_cart_id           = explode( '::', $cart_content_cart_id );
			$cart_content_cart_id = $ex_cart_id[1];
		}
		if ( $cart_content_cart_id == $cart_id || trim( $cart_content_cart_id, '_' ) == $cart_id ) {

			return true;
		} else if ( ! $strict ) {
			$cart_content_product_id = cart_id_to_product_id( $cart_content_cart_id );
			if ( $cart_content_product_id == $cart_id ) {
				return true;
			}
		}
	}

	return apply_filters( 'is_ic_product_in_cart', false, $cart_id );
}

function is_ic_cart() {
	if ( function_exists( 'is_ic_quote_cart' ) ) {
		if ( is_ic_quote_cart() || is_ic_ajax( 'quote_cart_products' ) || is_ic_ajax( 'get_quote_cart_products' ) ) {
			return true;
		}
	} else if ( function_exists( 'is_ic_shopping_cart' ) ) {
		if ( is_ic_shopping_cart() || is_ic_ajax( 'shopping_cart_products' ) ) {
			return true;
		}
	}

	return false;
}

function is_ic_cart_submit() {
	if ( function_exists( 'is_ic_quote_order' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a boolean request-shape check; the owning cart or quote handler verifies its action nonce before processing.
		if ( is_ic_quote_order() || ( ( is_ic_ajax( 'quote_cart_products' ) || is_ic_ajax( 'get_quote_cart_products' ) ) && empty( $_POST['raw'] ) ) ) {
			return true;
		}
	} else if ( function_exists( 'is_ic_shopping_order' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a boolean request-shape check; the owning cart or quote handler verifies its action nonce before processing.
		if ( is_ic_shopping_order() || ( is_ic_ajax( 'shopping_cart_products' ) && empty( $_POST['raw'] ) ) ) {
			return true;
		}
	}

	return false;
}

if ( ! function_exists( 'is_ic_tax_per_product' ) ) {
	function is_ic_tax_per_product() {
		if ( ! function_exists( 'get_cart_tax_rate' ) ) {
			return false;
		}
		$tax_rate = get_cart_tax_rate();
		if ( ! empty( $tax_rate['tax_different_rates'] ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_tax_included' ) ) {
	function is_ic_tax_included() {
		if ( ! function_exists( 'get_cart_tax_rate' ) ) {
			return false;
		}
		$tax_rate = get_cart_tax_rate();
		if ( ! empty( $tax_rate['tax_included'] ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_cart_sku_enabled' ) ) {
	function is_ic_cart_sku_enabled() {
		$enabled = false;
		if ( function_exists( 'is_ic_sku_enabled' ) && is_ic_sku_enabled() ) {
			$enabled = true;
		}

		return apply_filters( 'is_ic_cart_sku_enabled', $enabled );
	}
}

if ( ! function_exists( 'is_ic_shopping_page' ) ) {
	/**
	 * Checks whether the current request is any shopping-cart page.
	 *
	 * @return bool
	 */
	function is_ic_shopping_page() {
		return is_ic_shopping_cart() || is_ic_shopping_order() || is_ic_shopping_thank_you();
	}
}

if ( ! function_exists( 'is_ic_inside_shopping_page' ) ) {
	/**
	 * Checks whether the current runtime is inside shopping/cart output.
	 *
	 * @return bool
	 */
	function is_ic_inside_shopping_page() {
		return is_ic_inside_shopping_cart() || is_ic_inside_checkout() || is_ic_inside_thank_you();
	}
}

if ( ! function_exists( 'is_ic_shopping_cart' ) ) {
	/**
	 * Checks whether the current request is the shopping-cart page.
	 *
	 * @return bool
	 */
	function is_ic_shopping_cart() {
		if ( is_ic_ajax( 'shopping_cart_products' ) ) {
			return true;
		}
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( ! empty( $shopping_cart_settings['shopping_cart_page'] ) && 'noid' !== $shopping_cart_settings['shopping_cart_page'] && is_ic_page( $shopping_cart_settings['shopping_cart_page'] ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_shopping_order' ) ) {
	/**
	 * Checks whether the current request is the checkout page.
	 *
	 * @return bool
	 */
	function is_ic_shopping_order() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a boolean request-shape check; the owning cart or quote handler verifies its action nonce before processing.
		if ( is_ic_ajax( 'shopping_cart_products' ) && empty( $_POST['raw'] ) ) {
			return true;
		}
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( ! empty( $shopping_cart_settings['cart_submit_page'] ) && 'noid' !== $shopping_cart_settings['cart_submit_page'] && is_ic_page( $shopping_cart_settings['cart_submit_page'] ) ) {
			return apply_filters( 'is_ic_shopping_order', true );
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_shopping_thank_you' ) ) {
	/**
	 * Checks whether the current request is the thank-you page.
	 *
	 * @return bool
	 */
	function is_ic_shopping_thank_you() {
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( ! empty( $shopping_cart_settings['thank_you_page'] ) && 'noid' !== $shopping_cart_settings['thank_you_page'] && is_ic_page( $shopping_cart_settings['thank_you_page'] ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'is_ic_min_qty_enabled' ) ) {
	/**
	 * Checks whether the minimum-quantity option is enabled.
	 *
	 * @return bool
	 */
	function is_ic_min_qty_enabled() {
		$shopping_cart_settings = get_shopping_cart_settings();

		return ! empty( $shopping_cart_settings['qty_box'] );
	}
}

if ( ! function_exists( 'is_ic_max_qty_enabled' ) ) {
	/**
	 * Checks whether the maximum-quantity option is enabled.
	 *
	 * @return bool
	 */
	function is_ic_max_qty_enabled() {
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( ! empty( $shopping_cart_settings['max_qty_box'] ) ) {
			return true;
		}

		return apply_filters( 'ic_max_qty_enabled', false );
	}
}

if ( ! function_exists( 'is_ic_shopping_cart_empty' ) ) {
	/**
	 * Checks whether the current shopping cart is empty.
	 *
	 * @return bool
	 */
	function is_ic_shopping_cart_empty() {
		$cart_content = ic_shopping_cart_content( true );
		$how_many     = ic_get_cart_items_count( $cart_content );

		return empty( $how_many );
	}
}

if ( ! function_exists( 'ic_product_exists' ) ) {
	/**
	 * Checks whether the product post still exists.
	 *
	 * @param int $product_id Product identifier.
	 *
	 * @return bool
	 */
	function ic_product_exists( $product_id ) {
		return false !== get_post_status( $product_id );
	}
}

if ( ! function_exists( 'ic_cart_product_is_available' ) ) {
	/**
	 * Checks whether a product may still be shown as a cart line item.
	 *
	 * Deliberately narrow: only a trashed, auto-draft or deleted post disqualifies a product. It does NOT
	 * test ic_visible_product_status(), because catalogs routinely hold tens of thousands of legitimately
	 * `private` products that customers order every day -- gating on visibility would empty real carts.
	 *
	 * is_ic_product() cannot be used for this: it only answers whether the ID is a catalog post type, and
	 * it returns true for a trashed product, whose permalink degrades to ?post_type=al_product&p=ID.
	 *
	 * @param int $product_id Product identifier.
	 *
	 * @return bool
	 */
	function ic_cart_product_is_available( $product_id ) {
		$status    = get_post_status( $product_id );
		$available = ! in_array( $status, array( false, 'trash', 'auto-draft' ), true );

		return (bool) apply_filters( 'ic_cart_product_is_available', $available, $product_id, $status );
	}
}

if ( ! function_exists( 'is_ic_any_payment_gateway_active' ) ) {
	/**
	 * Checks whether any payment gateways are configured.
	 *
	 * @return bool
	 */
	function is_ic_any_payment_gateway_active() {
		$custom_payments = function_exists( 'get_shopping_cart_custom_payments' ) ? get_shopping_cart_custom_payments() : array();
		$normal_payments = function_exists( 'ic_get_order_payments' ) ? ic_get_order_payments() : array();

		return ! empty( $custom_payments ) || ! empty( $normal_payments );
	}
}

if ( ! function_exists( 'is_ic_order_taxed' ) ) {
	/**
	 * Checks whether cart tax is enabled.
	 *
	 * @return bool
	 */
	function is_ic_order_taxed() {
		if ( ! function_exists( 'get_cart_tax_rate' ) ) {
			return false;
		}
		$tax_rate = get_cart_tax_rate();

		return ! empty( $tax_rate['tax_rate'] );
	}
}

if ( ! function_exists( 'is_ic_cart_customer' ) ) {
	/**
	 * Checks whether the current user is a cart customer.
	 *
	 * @return bool
	 */
	function is_ic_cart_customer() {
		$customer_id = ic_cart_customer_id();
		if ( empty( $customer_id ) ) {
			return false;
		}
		$ic_roles = get_ic_roles( $customer_id );

		return current_user_can( 'customer' ) || in_array( 'customer', $ic_roles, true ) || in_array( 'cart_customer', $ic_roles, true ) || current_user_can( 'cart_customer' ) || in_array( 'digital_customer', $ic_roles, true ) || current_user_can( 'digital_customer' );
	}
}

if ( ! function_exists( 'is_ic_add_to_cart_shown' ) ) {
	/**
	 * Checks whether the add-to-cart button should be shown.
	 *
	 * @param int $product_id Product identifier.
	 *
	 * @return bool
	 */
	function is_ic_add_to_cart_shown( $product_id ) {
		if ( product_price( $product_id ) !== '' ) {
			return true;
		}

		return apply_filters( 'is_ic_add_to_cart_shown', false, $product_id );
	}
}

if ( ! function_exists( 'is_ic_inside_shopping_cart' ) ) {
	/**
	 * Checks whether the runtime is inside the cart output.
	 *
	 * @return bool
	 */
	function is_ic_inside_shopping_cart() {
		if ( is_ic_inside_cart_products() ) {
			return true;
		}

		return (bool) ic_get_global( 'inside_shopping_cart' );
	}
}

if ( ! function_exists( 'is_ic_inside_checkout' ) ) {
	/**
	 * Checks whether the runtime is inside checkout output.
	 *
	 * @return bool
	 */
	function is_ic_inside_checkout() {
		if ( is_ic_inside_cart_products() ) {
			return true;
		}

		return (bool) ic_get_global( 'inside_checkout' );
	}
}

if ( ! function_exists( 'is_ic_inside_cart_products' ) ) {
	/**
	 * Checks whether the runtime is inside the cart products table.
	 *
	 * @return bool
	 */
	function is_ic_inside_cart_products() {
		return (bool) ic_get_global( 'inside_cart_products' );
	}
}

if ( ! function_exists( 'is_ic_inside_thank_you' ) ) {
	/**
	 * Checks whether the runtime is inside the thank-you output.
	 *
	 * @return bool
	 */
	function is_ic_inside_thank_you() {
		return (bool) ic_get_global( 'inside_thank_you' );
	}
}
