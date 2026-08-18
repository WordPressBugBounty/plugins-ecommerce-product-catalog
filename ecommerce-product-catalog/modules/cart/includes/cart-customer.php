<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once AL_BASE_PATH . '/ic/customers/saved-cart.php';
require_once AL_BASE_PATH . '/ic/customers/admin-access.php';

/**
 * Manages shopping customer
 *
 * Here shopping customer is defined and managed.
 *
 * @version     1.0.0
 * @package     implecode-shopping-cart/includes
 * @author      Norbert Dreszer
 */
class ic_cart_customer {

	function __construct() {
		add_filter( 'ic_catalog_product_object_nonces', array( $this, 'product_object_nonces' ) );
		add_action( 'existing_customer_buy', array( __CLASS__, 'save_cart' ), 10, 1 );
		add_action( 'ic_formbuilder_before_mail', array( __CLASS__, 'save_cart' ), 10, 0 );

		add_filter( 'ic_formbuilder_default_value', array( __CLASS__, 'checkout_defaults' ), 9, 2 );

		add_action( 'init', array( __CLASS__, 'remove_admin_bar' ) );

		add_action( 'wp_ajax_nopriv_restore_shopping_cart', array( __CLASS__, 'ajax_restore_cart' ) );
		add_action( 'wp_ajax_restore_shopping_cart', array( __CLASS__, 'ajax_restore_cart' ) );

		add_filter( 'ic_no_products_text', array( __CLASS__, 'restore_cart_button' ) );
	}

	/**
	 * Adds the saved-cart restore nonce to the catalog product object.
	 *
	 * @param array $nonces Existing nonce map.
	 * @return array
	 */
	public function product_object_nonces( $nonces ) {
		$nonces['restore_shopping_cart_nonce'] = wp_create_nonce( 'restore_shopping_cart' );

		return $nonces;
	}

	static function save_cart( $customer_id = null ) {
		$cart_products = shopping_cart_products_array();
		ic_customers_save_cart_products( $cart_products, $customer_id );
	}

	static function checkout_defaults( $field_value, $field_name ) {
		$customer_field_value = ic_cart_customer_data( $field_name );
		if ( ! empty( $customer_field_value ) ) {
			$field_value = $customer_field_value;
		}
		return $field_value;
	}

	static function remove_admin_bar() {
		ic_customers_restrict_admin_access( is_ic_cart_customer() && ! current_user_can( 'manage_product_settings' ) );
	}

	/**
	 * Handles ajax user cart restore
	 */
	static function ajax_restore_cart() {
		ic_verify_public_cart_nonce( 'restore_shopping_cart' );
		$restore_state = ic_customers_saved_cart_restore_state( ic_get_customer_cart() );
		$cart_content  = $restore_state['cart_content'];
		$how_many      = $restore_state['how_many'];
		ic_cart_save( $cart_content, 'cart_content' );
		/**
		 * Fires when a customer restores a previously saved cart.
		 *
		 * Carries no identity: statistics consumers resolve the session
		 * themselves, exactly as they do for the other shared cart hooks.
		 *
		 * @param array $payload Cart key and restored cart size.
		 */
		if ( ! empty( $cart_content ) && $how_many > 0 ) {
			do_action(
				'ic_cart_recovered',
				array(
					'cart'      => 'cart_content',
					'cart_size' => is_array( $cart_content ) ? count( $cart_content ) : 0,
				)
			);
		}
		$array[0] = '<a href="' . ic_shopping_cart_page_url() . '"><button class="button ' . design_schemes( 'box', 0 ) . '"><span class="cart_button_text">' . $how_many . ' ' . __( 'selected', 'ecommerce-product-catalog' ) . '</span></button></a>';
		$array[1] = do_shortcode( '[shopping_cart]' );
		$array[2] = shopping_cart_products( 1 );
		echo json_encode( $array );
		wp_die();
	}

	/**
	 *
	 * @param string $text
	 * @return string
	 */
	static function restore_cart_button( $text ) {
		$user_cart = ic_get_customer_cart();
		if ( ! empty( $user_cart ) ) {
			$text .= ' <a href="#" class="restore-ic-cart"><button class="button ' . design_schemes( 'box', 0 ) . '"><span class="cart_button_text">' . __( 'Restore Previous Cart', 'ecommerce-product-catalog' ) . '</span></button></a>';
		}
		return $text;
	}
}

$ic_cart_customer = new ic_cart_customer();

function ic_cart_customer_data( $field_name ) {
	$customer_id = ic_cart_customer_id();
	if ( ! empty( $customer_id ) ) {
		$customer = get_user_meta( $customer_id, '_customer_data', true );
		if ( is_array( $customer ) && isset( $customer[ $field_name ] ) ) {
			return $customer[ $field_name ];
		}
	}
}

/**
 * Returns customer id
 *
 * @param type $email
 * @return type
 */
function ic_cart_customer_id( $email = null ) {
	$customer_id = get_current_user_id();
	if ( empty( $customer_id ) && ! empty( $email ) ) {
		$customer_id = username_exists( $email );
	}
	return $customer_id;
}

/**
 * Returns customer cart
 *
 * @return type
 */
function ic_get_customer_cart() {
	return ic_customers_saved_cart_products();
}
