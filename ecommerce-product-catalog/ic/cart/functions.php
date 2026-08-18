<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages shopping cart
 *
 * Here shopping cart functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */

/**
 * Normalizes the cart session bucket before cart access.
 *
 * @param array|null $session Current session data.
 *
 * @return array
 */
function ic_cart_normalize_session( $session = null ) {
	if ( ! is_array( $session ) ) {
		$session = array();
	}
	if ( ! isset( $session['ic_cart'] ) || ! is_array( $session['ic_cart'] ) ) {
		$session['ic_cart'] = array();
		set_product_catalog_session( $session );
	}

	return $session;
}

/**
 * Returns given cart content
 *
 * @param type $cart
 *
 * @return string
 */
function ic_cart_get( $cart = 'cart_content' ) {
	$session = ic_cart_normalize_session( get_product_catalog_session() );
	if ( isset( $session['ic_cart'][ $cart ] ) ) {
		return $session['ic_cart'][ $cart ];
	}

	return '';
}

/**
 * Save cart individual product array
 *
 * @param int       $_product_id
 * @param array|int $cart_product Product array or quantity
 * @param string    $cart
 *
 * @return array
 */
function ic_cart_insert( $_product_id, $qty = 1, $cart = 'cart_content' ) {
	$product_id = strval( $_product_id );
	if ( ! empty( $product_id ) ) {
		$cart_content                = ic_decode_json_cart( ic_cart_get( $cart ) );
		$product_id                  = apply_filters( 'ic_cart_insert_id', $product_id, $cart_content, $cart );
		$cart_content[ $product_id ] = apply_filters( 'ic_cart_insert', $qty, $product_id, $cart );

		return ic_cart_save( $cart_content, $cart );
	}

	return false;
}

/**
 * Saves new cart content
 *
 * @param type $new_cart
 * @param type $cart
 *
 * @return type
 */
function ic_cart_save( $new_cart, $cart = 'cart_content' ) {
	global $cart_just_created;
	$cart_just_created = false;
	// $cart_just_created is NOT a usable "new cart" signal: this file assigns it the
	// boolean false while ic-cart.php assigns it an array keyed by cart. Read the
	// session directly, before the save overwrites it.
	$existed          = is_ic_cart_initialized( $cart );
	$session          = ic_cart_normalize_session( get_product_catalog_session() );
	$cart_size_before = ic_cart_stats_size( $session['ic_cart'][ $cart ] ?? '' );
	if ( is_array( $new_cart ) ) {
		$new_cart = ic_encode_string_cart( $new_cart );
	}
	$new_cart                    = apply_filters( 'ic_cart_save', $new_cart );
	$session['ic_cart'][ $cart ] = $new_cart;
	set_product_catalog_session( $session );
	$cart_size = ic_cart_stats_size( $session['ic_cart'][ $cart ] );
	if ( ( ! $existed || 0 === $cart_size_before ) && $cart_size > 0 ) {
		do_action(
			'ic_cart_started',
			array(
				'cart'      => $cart,
				'cart_size' => $cart_size,
			)
		);
	}

	return $new_cart;
}

/**
 * Clears selected cart
 *
 * @param type $cart
 */
function ic_cart_clear( $cart = 'cart_content' ) {
	global $cart_just_created;
	$cart_just_created = false;
	$session           = ic_cart_normalize_session( apply_filters( 'ic_cart_clear', get_product_catalog_session() ) );
	if ( isset( $session['ic_cart'][ $cart ] ) ) {
		unset( $session['ic_cart'][ $cart ] );
	}
	if ( isset( $session['recent_cart_added_variation'] ) ) {
		unset( $session['recent_cart_added_variation'] );
	}
	if ( isset( $session['recent_cart_added'] ) ) {
		unset( $session['recent_cart_added'] );
	}
	if ( isset( $session['trans_id'] ) ) {
		unset( $session['trans_id'] );
	}
	set_product_catalog_session( $session );
	do_action( 'ic_cart_updated', array() );
	do_action(
		'ic_cart_event',
		array(
			'cart'      => $cart,
			'cart_size' => 0,
			'stage'     => 'clear',
		)
	);
}

/**
 * Checks if cart is already initialized
 *
 * @return boolean
 */
function is_ic_cart_initialized( $cart = 'cart_content' ) {
	$session = ic_cart_normalize_session( get_product_catalog_session() );
	if ( isset( $session['ic_cart'][ $cart ] ) ) {
		return true;
	}

	return false;
}

if ( ! function_exists( 'ic_cart_stats_size' ) ) {
	/**
	 * Counts the distinct products a cart payload carries.
	 *
	 * Sizes the statistics payloads emitted by this file. No session hash and no
	 * identity of any kind crosses that boundary: session hashing is
	 * provider-scoped and is resolved by the consumer.
	 *
	 * @param string|array $cart_content Encoded or decoded cart content.
	 *
	 * @return int
	 */
	function ic_cart_stats_size( $cart_content ) {
		if ( is_array( $cart_content ) ) {
			return count( $cart_content );
		}
		if ( ! is_string( $cart_content ) || '' === $cart_content || ! function_exists( 'ic_decode_json_cart' ) ) {
			return 0;
		}
		$decoded = ic_decode_json_cart( $cart_content );

		return is_array( $decoded ) ? count( $decoded ) : 0;
	}
}

if ( ! function_exists( 'ic_get_cart_items_count' ) ) {
	function ic_get_cart_items_count( $cart_content = null ) {
		if ( $cart_content === null ) {
			$cart_content = ic_shopping_cart_content( true );
		}
		$decoded_cart_content = ic_decode_json_cart( $cart_content, true );

		return $decoded_cart_content['count'];
	}
}

if ( ! function_exists( 'ic_filter_empty_array_values' ) ) {
	function ic_filter_empty_array_values( $values ) {
		if ( is_array( $values ) ) {
			if ( empty( $values ) ) {
				return array();
			}
			$values = array_filter( $values, 'ic_filter_empty_array_values' );
		} elseif ( empty( $values ) && ! is_numeric( $values ) ) {
			return false;
		} else {
			return true;
		}

		return $values;
	}
}

if ( ! function_exists( 'ic_cart_sanitize_parsed_data' ) ) {
	/**
	 * Recursively sanitizes values parsed out of a serialized cart payload.
	 *
	 * Both entry points into the cart parse the same `add_cart_data` payload with `parse_str()`
	 * and merge the result into `$_POST`: `ic_cart::content()` in cart/ic-cart.php and
	 * `ic_cart_ajax::add_to_cart()` in cart/ic-cart-ajax.php, which is a public `nopriv`
	 * endpoint. They must agree about sanitizing, so both call this one helper rather than
	 * keeping private copies that can drift apart.
	 *
	 * `parse_str()` legitimately produces arrays here: cart/buttons-fields.php emits one
	 * `<input name="additional_product[]">` per configured additional product, so
	 * `$params['additional_product']` arrives as an array of product ids. A flat
	 * `array_map( 'sanitize_text_field', $params )` applies a SCALAR sanitizer to that array,
	 * and WP core `_sanitize_text_fields()` short-circuits arrays with `return '';` -- silently,
	 * with no PHP notice. The resulting empty string then failed the
	 * `! empty( $additional_product )` guard in `ic_cart::content()`, so every configured
	 * additional product was dropped from the cart without any error.
	 *
	 * The walk is therefore recursive: only leaf values reach `sanitize_text_field()`, and array
	 * keys and nesting are preserved exactly as `parse_str()` produced them.
	 *
	 * `ic_sanitize()` is deliberately NOT used -- it is defined in
	 * ecommerce-product-catalog/functions/functions.php only and is not guaranteed to exist in
	 * every plugin that ships this framework file.
	 *
	 * This is NOT interchangeable with `ic_cart_shipping::sanitize_parsed_data()` in
	 * cart/cart-shipping.php. That one is field-scoped on purpose: it normalizes `shipping` and
	 * `shipping_N` keys with `sanitize_title()` so the posted value can still match the
	 * `sanitize_title()`-built option whitelist. Merging the two would reintroduce that bug.
	 *
	 * @param mixed $data Parsed request data.
	 *
	 * @return mixed
	 */
	function ic_cart_sanitize_parsed_data( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				$sanitized[ $key ] = ic_cart_sanitize_parsed_data( $value );
			}

			return $sanitized;
		}

		return sanitize_text_field( $data );
	}
}

if ( ! function_exists( 'ic_cart_should_redirect_empty_checkout' ) ) {
	/**
	 * Checks whether the current checkout request should be redirected back to the cart.
	 *
	 * @param bool $require_not_cart_page Whether the current page must also not be the cart page.
	 *
	 * @return bool
	 */
	function ic_cart_should_redirect_empty_checkout( $require_not_cart_page = false ) {
		if ( current_user_can( 'manage_product_settings' ) ) {
			return false;
		}
		if ( ! is_ic_shopping_order() || is_ic_ajax() ) {
			return false;
		}
		if ( $require_not_cart_page && is_ic_shopping_cart() ) {
			return false;
		}

		$cart = shopping_cart_products_array();

		return empty( $cart );
	}
}

if ( ! function_exists( 'ic_cart_store_recent_product' ) ) {
	/**
	 * Stores the most recently added product and its selected variations in the session.
	 *
	 * @param int   $product_id Product identifier.
	 * @param array $args       Optional behavior flags.
	 *
	 * @return bool
	 */
	function ic_cart_store_recent_product( $product_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'variation_value_check'  => 'not_empty',
				'variation_session_hook' => '',
			)
		);

		$product_id = intval( $product_id );
		if ( empty( $product_id ) ) {
			return false;
		}

		$session                      = get_product_catalog_session();
		$session['recent_cart_added'] = $product_id;
		$variation_values             = get_product_variations_values( $product_id, true );

		foreach ( $variation_values as $i => $variation ) {
			++$i;
			$field_name = $i . '_variation_' . $product_id;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			$has_value  = isset( $_POST[ $field_name ] );
			if ( 'not_empty' === $args['variation_value_check'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$has_value = ! empty( $_POST[ $field_name ] );
			}
			if ( ! $has_value ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			$session['recent_cart_added_variation'][ $field_name ] = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );

			if ( ! empty( $args['variation_session_hook'] ) ) {
				$session = apply_filters( $args['variation_session_hook'], $session, $i, $product_id );
			}
		}

		set_product_catalog_session( $session );

		return true;
	}
}

if ( ! function_exists( 'ic_cart_recent_session_value' ) ) {
	/**
	 * Returns a recently stored cart session value and clears it from the session bucket.
	 *
	 * @param string $session_key Session key name.
	 * @param string $global_key  Runtime cache key.
	 *
	 * @return mixed|string
	 */
	function ic_cart_recent_session_value( $session_key, $global_key ) {
		if ( is_admin() ) {
			return '';
		}

		$recently_added = ic_get_global( $global_key );
		if ( $recently_added ) {
			return $recently_added;
		}

		$session = get_product_catalog_session();
		if ( isset( $session[ $session_key ] ) ) {
			$recently_added = $session[ $session_key ];
			unset( $session[ $session_key ] );
			set_product_catalog_session( $session );
			ic_save_global( $global_key, $recently_added );

			return $recently_added;
		}

		return '';
	}
}

if ( ! function_exists( 'ic_get_recently_added_product' ) ) {
	/**
	 * Returns the most recently added product identifier.
	 *
	 * @return mixed|string
	 */
	function ic_get_recently_added_product() {
		return ic_cart_recent_session_value( 'recent_cart_added', 'recent_cart_added' );
	}
}

if ( ! function_exists( 'ic_get_recently_added_product_variation' ) ) {
	/**
	 * Returns the most recently selected variation payload.
	 *
	 * @return mixed|string
	 */
	function ic_get_recently_added_product_variation() {
		return ic_cart_recent_session_value( 'recent_cart_added_variation', 'recently_added_product_variation' );
	}
}

if ( ! function_exists( 'ic_payment_number_format' ) ) {
	/**
	 * Formats payment numbers to a fixed two-decimal string.
	 *
	 * @param mixed $number Number to format.
	 *
	 * @return string|int
	 */
	function ic_payment_number_format( $number ) {
		if ( is_numeric( $number ) ) {
			return number_format( floatval( $number ), 2, '.', '' );
		}

		return 0;
	}
}

if ( ! function_exists( 'ic_cart_normalize_back_url' ) ) {
	/**
	 * Normalizes a back-link URL for comparisons.
	 *
	 * @param string $url URL to normalize.
	 *
	 * @return string
	 */
	function ic_cart_normalize_back_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) && ! empty( $parts['path'] ) ) {
			$url = home_url( $parts['path'] );
			if ( ! empty( $parts['query'] ) ) {
				$url .= '?' . $parts['query'];
			}
		}

		return untrailingslashit( $url );
	}
}

if ( ! function_exists( 'ic_cart_get_main_listing_back_url' ) ) {
	/**
	 * Returns the main catalog listing URL when enabled.
	 *
	 * @return string
	 */
	function ic_cart_get_main_listing_back_url() {
		if ( function_exists( 'is_ic_product_listing_enabled' ) && ! is_ic_product_listing_enabled() ) {
			return '';
		}
		if ( function_exists( 'product_listing_url' ) ) {
			return ic_cart_normalize_back_url( product_listing_url() );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_cart_get_disallowed_back_urls' ) ) {
	/**
	 * Returns cart-related URLs that should never be used as continue-shopping targets.
	 *
	 * @return array
	 */
	function ic_cart_get_disallowed_back_urls() {
		$disallowed = array();

		if ( function_exists( 'ic_shopping_cart_page_url' ) ) {
			$disallowed[] = ic_shopping_cart_page_url();
		}
		if ( function_exists( 'ic_shopping_submit_page_url' ) ) {
			$disallowed[] = ic_shopping_submit_page_url();
		}
		if ( function_exists( 'quote_cart_page_url' ) ) {
			$disallowed[] = quote_cart_page_url();
		}
		if ( function_exists( 'quote_submit_page_url' ) ) {
			$disallowed[] = quote_submit_page_url();
		}
		if ( function_exists( 'ic_shopping_thank_you_page_id' ) ) {
			$thank_you_page_id = ic_shopping_thank_you_page_id();
			if ( ! empty( $thank_you_page_id ) ) {
				$disallowed[] = ic_get_permalink( $thank_you_page_id );
			}
		}
		if ( function_exists( 'get_quote_cart_settings' ) ) {
			$quote_settings = get_quote_cart_settings();
			if ( ! empty( $quote_settings['thank_you_page'] ) ) {
				$disallowed[] = ic_get_permalink( $quote_settings['thank_you_page'] );
			}
		}

		$current_url = '';
		if ( function_exists( 'ic_current_page_url' ) ) {
			$current_url = ic_current_page_url();
		}
		if ( ! empty( $current_url ) ) {
			$disallowed[] = $current_url;
		}

		return array_filter( array_map( 'ic_cart_normalize_back_url', $disallowed ) );
	}
}

if ( ! function_exists( 'ic_cart_is_safe_previous_url' ) ) {
	/**
	 * Checks whether a previous URL is same-site and not a cart/admin loop target.
	 *
	 * @param string $url URL to validate.
	 *
	 * @return bool
	 */
	function ic_cart_is_safe_previous_url( $url ) {
		$url = ic_cart_normalize_back_url( $url );
		if ( '' === $url ) {
			return false;
		}

		$parts      = wp_parse_url( $url );
		$site_parts = wp_parse_url( home_url( '/' ) );
		if ( empty( $parts['host'] ) || empty( $site_parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $site_parts['host'] ) ) {
			return false;
		}

		if ( ic_string_contains( $url, admin_url() ) ) {
			return false;
		}

		return ! in_array( $url, ic_cart_get_disallowed_back_urls(), true );
	}
}

if ( ! function_exists( 'ic_cart_shared_textdomain' ) ) {
	/**
	 * Returns the shared cart textdomain.
	 *
	 * @return string
	 */
	function ic_cart_shared_textdomain() {
		return apply_filters( 'ic_cart_shared_textdomain', 'ecommerce-product-catalog' );
	}
}

if ( ! function_exists( 'ic_shopping_cart_page_url' ) ) {
	/**
	 * Returns the shopping-cart page URL.
	 *
	 * @return string
	 */
	function ic_shopping_cart_page_url() {
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( empty( $shopping_cart_settings['shopping_cart_page'] ) ) {
			return '';
		}
		$status = get_post_status( $shopping_cart_settings['shopping_cart_page'] );
		if ( $status && 'trash' !== $status ) {
			return ic_get_permalink( $shopping_cart_settings['shopping_cart_page'] );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_shopping_cart_page_id' ) ) {
	/**
	 * Returns the shopping-cart page ID.
	 *
	 * @return int|string
	 */
	function ic_shopping_cart_page_id() {
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( empty( $shopping_cart_settings['shopping_cart_page'] ) ) {
			return '';
		}
		$status = get_post_status( $shopping_cart_settings['shopping_cart_page'] );
		if ( $status && 'trash' !== $status ) {
			return intval( $shopping_cart_settings['shopping_cart_page'] );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_shopping_submit_page_url' ) ) {
	/**
	 * Returns the checkout page URL.
	 *
	 * @return string
	 */
	function ic_shopping_submit_page_url() {
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( empty( $shopping_cart_settings['cart_submit_page'] ) ) {
			return '';
		}
		$status = get_post_status( $shopping_cart_settings['cart_submit_page'] );
		if ( $status && 'trash' !== $status ) {
			return ic_get_permalink( $shopping_cart_settings['cart_submit_page'] );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_shopping_submit_page_id' ) ) {
	/**
	 * Returns the checkout page ID.
	 *
	 * @return int|string
	 */
	function ic_shopping_submit_page_id() {
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( empty( $shopping_cart_settings['cart_submit_page'] ) ) {
			return '';
		}
		$status = get_post_status( $shopping_cart_settings['cart_submit_page'] );
		if ( $status && 'trash' !== $status ) {
			return intval( $shopping_cart_settings['cart_submit_page'] );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_shopping_thank_you_page_id' ) ) {
	/**
	 * Returns the thank-you page ID.
	 *
	 * @return int|string
	 */
	function ic_shopping_thank_you_page_id() {
		$shopping_cart_settings = get_shopping_cart_settings();
		if ( empty( $shopping_cart_settings['thank_you_page'] ) ) {
			return '';
		}
		$status = get_post_status( $shopping_cart_settings['thank_you_page'] );
		if ( $status && 'trash' !== $status ) {
			return intval( $shopping_cart_settings['thank_you_page'] );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_shopping_cart_content' ) ) {
	/**
	 * Returns the current shopping-cart content.
	 *
	 * @return string
	 */
	function ic_shopping_cart_content() {
		return ic_cart_content( true, 'cart_content' );
	}
}

if ( ! function_exists( 'ic_clear_cart_without_payment' ) ) {
	add_action( 'init', 'ic_clear_cart_without_payment' );

	/**
	 * Clears the cart automatically when no payment gateways are active.
	 *
	 * @return void
	 */
	function ic_clear_cart_without_payment() {
		$normal_payments = array();
		if ( function_exists( 'ic_get_order_payments' ) ) {
			$normal_payments = ic_get_order_payments();
		}
		if ( empty( $normal_payments ) ) {
			add_action( 'ic_formbuilder_after_mail', 'ic_clear_shopping_cart', 99, 5 );
		}
	}
}

if ( ! function_exists( 'ic_clear_shopping_cart' ) ) {
	add_action( 'gateway_payment_verification_successful', 'ic_clear_shopping_cart' );
	add_action( 'ic_payment_pre_verified', 'ic_clear_shopping_cart' );
	add_action( 'wp_logout', 'ic_clear_shopping_cart' );
	add_action( 'success_page', 'ic_clear_shopping_cart', 99 );

	/**
	 * Clears the shopping cart after checkout completion.
	 *
	 * @param string|null $message        Optional form-builder message.
	 * @param string|null $customer_email Optional customer email.
	 * @param mixed|null  $redirect       Optional redirect payload.
	 * @param mixed|null  $contact        Optional contact payload.
	 * @param string|null $pre_name       Optional form prefix.
	 *
	 * @return void
	 */
	function ic_clear_shopping_cart( $message = null, $customer_email = null, $redirect = null, $contact = null, $pre_name = null ) {
		if ( 'success_page' === current_filter() && ! is_ic_shopping_thank_you() ) {
			ic_cart_clear( 'quote_cart_content' );
			$_POST['quote_cart_content'] = '';

			return;
		}
		if ( 'ic_formbuilder_after_mail' === current_filter() && ! empty( $pre_name ) && 'quote_cart_' === $pre_name ) {
			do_action(
				'ic_cart_converted',
				array(
					'cart'      => 'quote_cart_content',
					'cart_size' => ic_cart_stats_size( ic_cart_get( 'quote_cart_content' ) ),
				)
			);
			ic_cart_clear( 'quote_cart_content' );
			$_POST['quote_cart_content'] = '';

			return;
		}

		// This function is also registered on wp_logout, which is not a conversion.
		// Emit only for the checkout-completion filters, and emit before the clear so
		// the payload can still read the cart size.
		if (
			in_array(
				current_filter(),
				array( 'gateway_payment_verification_successful', 'ic_payment_pre_verified', 'success_page', 'ic_formbuilder_after_mail' ),
				true
			) && ic_cart_stats_size( ic_cart_get( 'cart_content' ) ) > 0
		) {
			do_action(
				'ic_cart_converted',
				array(
					'cart'      => 'cart_content',
					'cart_size' => ic_cart_stats_size( ic_cart_get( 'cart_content' ) ),
				)
			);
		}
		ic_cart_clear( 'cart_content' );
		$_POST['cart_content'] = '';
	}
}

add_action( 'wp', 'ic_shopping_form_output_buffer' );

if ( ! function_exists( 'ic_shopping_form_output_buffer' ) ) {
	/**
	 * Starts output buffering on the checkout page.
	 *
	 * @return void
	 */
	function ic_shopping_form_output_buffer() {
		if ( is_ic_shopping_order() ) {
			ob_start();
		}
	}
}

add_action( 'wp', 'ic_shopping_cart_add_redirect' );

if ( ! function_exists( 'ic_shopping_cart_add_redirect' ) ) {
	/**
	 * Redirects after add-to-cart to avoid back-button resubmits.
	 *
	 * @return void
	 */
	function ic_shopping_cart_add_redirect() {
		$shopping_cart_settings = get_shopping_cart_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( empty( $shopping_cart_settings['cart_redirect'] ) && isset( $_POST['current_product'] ) && ! is_ic_ajax() ) {
			ic_shopping_cart_content( true );
			if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$location = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
				wp_safe_redirect( $location );
				exit;
			}
		}
	}
}

if ( ! function_exists( 'ic_country_selector' ) ) {
	/**
	 * Returns the checkout country selector field.
	 *
	 * @param string      $name     Field name.
	 * @param string|null $required Required attribute.
	 * @param string|null $selected Selected country code.
	 * @param int|string  $echo     Whether to echo the markup.
	 *
	 * @return string
	 */
	function ic_country_selector( $name, $required = null, $selected = null, $echo = 1 ) {
		$placeholder = __( 'Choose your country...', 'ecommerce-product-catalog' );
		$return      = '<select ' . $required . ' data-placeholder="' . esc_attr( $placeholder ) . '" id="country-selector" name="' . esc_attr( $name ) . '" class="country-selector">';
		$return     .= '<option value=""></option>';
		if ( function_exists( 'implecode_qc_supported_countries' ) ) {
			$supported_countries = implecode_qc_supported_countries();
		} else {
			$supported_countries = implecode_supported_countries();
		}
		foreach ( $supported_countries as $code => $country ) {
			$return .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $country ) . '</option>';
		}
		$return .= '</select>';

		return echo_ic_setting( $return, $echo );
	}
}

if ( ! function_exists( 'ic_roundto' ) ) {
	/**
	 * Rounds a value to a configured increment.
	 *
	 * @param float|int $number     Number to round.
	 * @param float|int $increments Increment value.
	 *
	 * @return float
	 */
	function ic_roundto( $number, $increments ) {
		$increments = $increments * 100;
		if ( 0 == $increments ) {
			return round( $number, 2 );
		}

		$increments = 1 / $increments;

		return round( $number * $increments, 2 ) / $increments;
	}
}

if ( ! function_exists( 'ic_get_price_tax' ) ) {
	/**
	 * Calculates the product tax amount for a net price.
	 *
	 * @param float|int $price      Net price.
	 * @param int|null  $product_id Optional product identifier.
	 *
	 * @return float|int
	 */
	function ic_get_price_tax( $price, $product_id = null ) {
		if ( ! function_exists( 'get_cart_tax_rate' ) ) {
			return 0;
		}
		$tax_rate = get_cart_tax_rate();
		if ( ! empty( $product_id ) ) {
			$tax_rate_c = ic_get_tax_rate( $product_id ) / 100;
		} else {
			$tax_rate_c = $tax_rate['tax_rate'] / 100;
		}
		$tax = $price * $tax_rate_c;
		if ( function_exists( 'ic_tax_round_per_item' ) && ic_tax_round_per_item() ) {
			$tax = ic_round_tax( $tax );
		}

		return $tax;
	}
}

add_action( 'wp', 'ic_exclude_cart_pages_caching' );

if ( ! function_exists( 'ic_exclude_cart_pages_caching' ) ) {
	/**
	 * Excludes cart and checkout pages from page caching.
	 *
	 * @return void
	 */
	function ic_exclude_cart_pages_caching() {
		if ( is_ic_shopping_cart() || is_ic_shopping_order() ) {
			global $cache_stop;
			$cache_stop = true;
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', 1 );
			}
		}
	}
}

if ( ! function_exists( 'ic_cart_get_previous_referrer_url' ) ) {
	/**
	 * Returns the current request referrer when it is safe to use.
	 *
	 * @return string
	 */
	function ic_cart_get_previous_referrer_url() {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		$previous_url = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		if ( ic_cart_is_safe_previous_url( $previous_url ) ) {
			return ic_cart_normalize_back_url( $previous_url );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_cart_get_stored_catalog_back_url' ) ) {
	/**
	 * Returns the last stored catalog page URL for continue-shopping back links.
	 *
	 * @return string
	 */
	function ic_cart_get_stored_catalog_back_url() {
		$session = get_product_catalog_session();
		if ( empty( $session['ic_catalog_back_url'] ) ) {
			return '';
		}
		if ( ic_cart_is_safe_previous_url( $session['ic_catalog_back_url'] ) ) {
			return ic_cart_normalize_back_url( $session['ic_catalog_back_url'] );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_cart_store_catalog_back_url' ) ) {
	/**
	 * Stores the current catalog page as the preferred continue-shopping target.
	 *
	 * @return void
	 */
	function ic_cart_store_catalog_back_url() {
		if ( is_admin() || is_ic_ajax() ) {
			return;
		}
		if ( ! function_exists( 'is_ic_product_listing' ) || ! function_exists( 'is_ic_taxonomy_page' ) || ! function_exists( 'is_ic_product_search' ) ) {
			return;
		}
		if ( ! is_ic_product_listing() && ! is_ic_taxonomy_page() && ! is_ic_product_search() ) {
			return;
		}

		$current_url = '';
		if ( function_exists( 'ic_current_page_url' ) ) {
			$current_url = ic_current_page_url();
		}
		$current_url = ic_cart_normalize_back_url( $current_url );
		if ( '' === $current_url ) {
			return;
		}

		$session                        = get_product_catalog_session();
		$session['ic_catalog_back_url'] = $current_url;
		set_product_catalog_session( $session );
	}

	add_action( 'wp', 'ic_cart_store_catalog_back_url', 20 );
}

if ( ! function_exists( 'ic_cart_use_previous_page_target' ) ) {
	/**
	 * Determines whether the current cart context should prefer the previous catalog page.
	 *
	 * @return bool
	 */
	function ic_cart_use_previous_page_target() {
		if ( function_exists( 'is_ic_quote_cart_page' ) && is_ic_quote_cart_page() ) {
			return true;
		}
		if ( function_exists( 'is_ic_quote_cart' ) && function_exists( 'is_ic_quote_order' ) && ( is_ic_quote_cart() || is_ic_quote_order() ) ) {
			return true;
		}
		if ( function_exists( 'get_shopping_cart_settings' ) ) {
			$shopping_cart_settings = get_shopping_cart_settings();
			if ( ! empty( $shopping_cart_settings['contnue_shopping_target'] ) && 'prev' === $shopping_cart_settings['contnue_shopping_target'] ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'ic_cart_resolve_shopping_back_url' ) ) {
	/**
	 * Resolves the unified continue-shopping/back URL.
	 *
	 * @return string
	 */
	function ic_cart_resolve_shopping_back_url() {
		$listing_url      = ic_cart_get_main_listing_back_url();
		$previous_url     = ic_cart_get_previous_referrer_url();
		$catalog_back_url = ic_cart_get_stored_catalog_back_url();
		$prefer_previous  = ic_cart_use_previous_page_target();

		if ( $prefer_previous ) {
			if ( '' !== $previous_url && '' !== $catalog_back_url && $previous_url === $catalog_back_url ) {
				$url = $previous_url;
			} elseif ( '' !== $catalog_back_url ) {
				$url = $catalog_back_url;
			} elseif ( '' !== $listing_url ) {
				$url = $listing_url;
			} elseif ( '' !== $previous_url ) {
				$url = $previous_url;
			} else {
				$url = home_url( '/' );
			}
		} elseif ( '' !== $listing_url ) {
			$url = $listing_url;
		} elseif ( '' !== $previous_url ) {
			$url = $previous_url;
		} else {
			$url = home_url( '/' );
		}

		return $url;
	}
}

if ( ! function_exists( 'ic_get_shopping_back_url' ) ) {
	/**
	 * Returns the unified continue-shopping/back URL.
	 *
	 * @return string
	 */
	function ic_get_shopping_back_url() {
		return apply_filters( 'continue_shopping_url', ic_cart_resolve_shopping_back_url() );
	}
}
