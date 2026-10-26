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
if ( ! function_exists( 'ic_verify_public_cart_nonce' ) ) {
	/**
	 * Verifies a public cart nonce and authorizes logged-in callers.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	function ic_verify_public_cart_nonce( $action ) {
		$nonce = isset( $_REQUEST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ic_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
	}
}

class ic_cart_ajax {
	/**
	 * Verifies a public cart request and authorizes logged-in callers.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function verify_public_request( $action ) {
		ic_verify_public_cart_nonce( $action );
	}

	function __construct() {
		add_filter( 'ic_catalog_product_object_nonces', array( $this, 'product_object_nonces' ) );
		add_action( 'wp_ajax_nopriv_ic_add_to_cart', array( $this, 'add_to_cart' ) );
		add_action( 'wp_ajax_ic_add_to_cart', array( $this, 'add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_ic_cart_update_products_table', array( $this, 'update_products_table' ) );
		add_action( 'wp_ajax_ic_cart_update_products_table', array( $this, 'update_products_table' ) );
		// Back-compat alias of `ic_cart_update_products_table`. No in-tree caller; kept because sites and
		// custom scripts still post to this action name. Do not remove.
		add_action( 'wp_ajax_nopriv_ic_cart_update_shipping_selection', array( $this, 'update_products_table' ) );
		add_action( 'wp_ajax_ic_cart_update_shipping_selection', array( $this, 'update_products_table' ) );
		add_action( 'wp_ajax_nopriv_ic_quote_cart_update_products_table', array( $this, 'update_products_table' ) );
		add_action( 'wp_ajax_ic_quote_cart_update_products_table', array( $this, 'update_products_table' ) );
		// Back-compat alias of `ic_quote_cart_update_products_table`. No in-tree caller; kept because sites
		// and custom scripts still post to this action name. Do not remove.
		add_action( 'wp_ajax_nopriv_ic_quote_cart_update_shipping_selection', array( $this, 'update_products_table' ) );
		add_action( 'wp_ajax_ic_quote_cart_update_shipping_selection', array( $this, 'update_products_table' ) );
		add_action( 'wp_ajax_nopriv_ic_get_sitewide_icons', array( $this, 'get_icons' ) );
		add_action( 'wp_ajax_ic_get_sitewide_icons', array( $this, 'get_icons' ) );
		add_action( 'wp_ajax_nopriv_ic_get_cart_button', array( $this, 'get_cart_button' ) );
		add_action( 'wp_ajax_ic_get_cart_button', array( $this, 'get_cart_button' ) );
		add_filter( 'ic_pre_cached_parts', array( $this, 'get_cart_button_precached' ), 10, 2 );
		add_action( 'register_catalog_styles', array( $this, 'register_styles' ) );
		add_action( 'enqueue_catalog_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Adds shared cart AJAX nonces to the catalog product object.
	 *
	 * @param array $nonces Existing nonce map.
	 * @return array
	 */
	public function product_object_nonces( $nonces ) {
		$actions = array(
			'cart_add_nonce'            => 'ic_add_to_cart',
			'cart_button_nonce'         => 'ic_get_cart_button',
			'cart_icons_nonce'          => 'ic_get_sitewide_icons',
			'cart_update_nonce'         => 'ic_cart_update_products_table',
			'cart_shipping_nonce'       => 'ic_cart_update_shipping_selection',
			'quote_cart_update_nonce'   => 'ic_quote_cart_update_products_table',
			'quote_cart_shipping_nonce' => 'ic_quote_cart_update_shipping_selection',
		);
		foreach ( $actions as $key => $action ) {
			$nonces[ $key ] = wp_create_nonce( $action );
		}

		return $nonces;
	}

	function get_icons() {
		$this->verify_public_request( 'ic_get_sitewide_icons' );
		global $ic_sitewide_bar;
		if ( ! empty( $ic_sitewide_bar ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sitewide bar generates trusted interactive controls with escaped dynamic leaves; post KSES removes them.
			echo $ic_sitewide_bar->icons();
		}
		wp_die();
	}

	/**
	 * Manages ajax price format
	 *
	 */
	function add_to_cart() {
		$this->verify_public_request( 'ic_add_to_cart' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific cart nonce before dispatch; logged-in calls also require the read capability.
		if ( isset( $_POST['add_cart_data'] ) ) {
			$params    = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific cart nonce before dispatch; logged-in calls also require the read capability.
			$ajax_post = $_POST;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			parse_str( wp_unslash( $_POST['add_cart_data'] ), $params );
			// Sanitize before merging, exactly as ic_cart::content() does with this same payload
			// (cart/ic-cart.php). This is a public nopriv endpoint (:19-20), and the merged values
			// are already visible to the ic_ajax_add_to_cart_return / ic_cart_added_info_html
			// filters and to the widget/container/icon branches below, all of which run before
			// content() re-parses the payload and merges its own sanitized copy over the top.
			// $ajax_post is snapshotted above, so it keeps the pre-merge $_POST either way.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific cart nonce before dispatch; logged-in calls also require the read capability.
			$_POST = array_merge( $_POST, ic_cart_sanitize_parsed_data( $params ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific cart nonce before dispatch; logged-in calls also require the read capability.
			if ( isset( $_POST['ic_cart_name'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific cart nonce before dispatch; logged-in calls also require the read capability.
				$cart_name = sanitize_text_field( wp_unslash( $_POST['ic_cart_name'] ) );
			} else {
				$cart_name = 'cart_content';
			}
			if ( function_exists( 'quote_cart_page_url' ) && 'quote_cart_content' === $cart_name ) {
				$cart_page = quote_cart_page_url();
			} else {
				$cart_page = ic_shopping_cart_page_url();
			}
			$return = apply_filters( 'ic_ajax_add_to_cart_return', array() );
			if ( empty( $return['cart-added-info'] ) && ! empty( $cart_page ) ) {
				$return['cart-added-info'] = apply_filters( 'ic_cart_added_info_html', ic_product_cart_added_info_html( '', $cart_page ), $cart_page );
			}
			$cart_content = false;
			if ( ! empty( $ajax_post['cart_widget'] ) ) {
				$cart_content          = true;
				$label                 = isset( $ajax_post['label'] ) ? sanitize_text_field( $ajax_post['label'] ) : '';
				if ( function_exists( 'ic_quote_cart_widget_button' ) && 'quote_cart_content' === $cart_name ) {
					$return['cart-widget'] = ic_quote_cart_widget_button();
				} else {
					$return['cart-widget'] = ic_shopping_cart_button( false, $label );
				}
			}
			if ( ! empty( $ajax_post['cart_container'] ) ) {
				$cart_content             = true;
				if ( function_exists( 'quote_cart_products' ) && 'quote_cart_content' === $cart_name ) {
					$return['cart-container'] = quote_cart_products();
				} else {
					$return['cart-container'] = shopping_cart_products( false );
				}
			}
			if ( ! empty( $ajax_post['cart_icon'] ) ) {
				global $ic_sitewide_bar;
				if ( ! empty( $ic_sitewide_bar ) ) {
					$cart_content        = true;
					$return['icons-bar'] = $ic_sitewide_bar->icons();
				}

			}
			if ( ! $cart_content ) {
				if ( function_exists( 'quote_cart_content' ) && 'quote_cart_content' === $cart_name ) {
					quote_cart_content( true );
				} else {
					ic_shopping_cart_content( true );
				}
			}
			echo wp_json_encode( $return, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		}

		wp_die();
	}

	function get_cart_button() {
		$this->verify_public_request( 'ic_get_cart_button' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_public_nonce() validates the action-specific cart nonce before dispatch; logged-in calls also require the read capability.
		$label = isset( $_POST['label'] ) ? esc_html( sanitize_text_field( wp_unslash( $_POST['label'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cart button constructor escapes dynamic leaves and returns trusted form controls; post KSES removes them.
		echo ic_shopping_cart_button( false, $label );
		wp_die();
	}

	/**
	 * Returns refreshed quote-cart products-table markup and optionally persists shipping selection first.
	 *
	 * Six actions are registered on this handler (`:21-32`): the `ic_cart_*` and `ic_quote_cart_*`
	 * products-table refresh pairs plus their `*_update_shipping_selection` back-compat aliases. All of
	 * them are `nopriv` by design, because the cart is available to anonymous visitors.
	 *
	 * The endpoint is not privileged: every write lands in `ic_session_cache()` for the calling session
	 * only, and any submitted shipping value is whitelisted against the server-computed options in
	 * `ic_cart_shipping::selected_values_from_request()` (`cart-shipping.php:301-326`) before it is
	 * stored, so a forged request can only alter the sender's own session.
	 *
	 * @return void
	 */
	function update_products_table() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verify_public_nonce() validates the action-specific cart nonce before dispatch; logged-in calls also require the read capability.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'ic_cart_update_products_table';
		$this->verify_public_request( $action );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public nopriv cart refresh (:21-32); writes are session-scoped only.
		$pre_name = isset( $_POST['pre_name'] ) ? sanitize_key( wp_unslash( $_POST['pre_name'] ) ) : 'quote_cart_';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public nopriv cart refresh (:21-32); writes are session-scoped only.
		$raw = isset( $_POST['raw'] ) ? sanitize_text_field( wp_unslash( $_POST['raw'] ) ) : '0';
		if ( empty( $pre_name ) ) {
			$pre_name = 'quote_cart_';
		}

		do_action( 'ic_cart_update_products_table', $pre_name );

		if ( 0 === strpos( $pre_name, 'quote_cart_' ) && function_exists( 'quote_cart_products' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Quote cart output is rendered HTML.
			echo quote_cart_products( $raw );
		} elseif ( function_exists( 'shopping_cart_products' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shopping cart output is rendered HTML.
			echo shopping_cart_products( $raw );
		}
		if ( ! empty( $raw ) && function_exists( 'ic_checkout_shipping_options_section_html' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shipping section output is rendered HTML.
			echo ic_checkout_shipping_options_section_html( $pre_name );
		}

		wp_die();
	}

	function get_cart_button_precached( $parts, $args = array() ) {
		if ( ! isset( $args['ic_get_cart_button'] ) ) {
			return $parts;
		}

		$this_args                   = isset( $args['ic_get_cart_button'] ) ? $args['ic_get_cart_button'] : array();
		$parts['ic_get_cart_button'] = ic_shopping_cart_button( false, $this_args[1] );

		return $parts;
	}

	function register_styles() {
		wp_register_script( 'ic_ajax_cart', plugins_url( '/', __FILE__ ) . 'js/ic-cart.js' . ic_filemtime( dirname( __FILE__ ) . '/js/ic-cart.js' ), array(), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( dirname( __FILE__ ) . '/js/ic-cart.js' ), true );
	}

	function enqueue_styles() {
//wp_enqueue_style( 'ic_variations' );
		wp_enqueue_script( 'ic_ajax_cart' );
	}

}
global $ic_cart_ajax;
$ic_cart_ajax = new ic_cart_ajax;
