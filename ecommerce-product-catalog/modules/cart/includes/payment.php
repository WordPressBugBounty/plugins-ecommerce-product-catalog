<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages shopping customer
 *
 * Here shopping customer is defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-shopping-cart/includes
 * @author        Norbert Dreszer
 */
function ic_get_order_payments() {
	$payments = apply_filters( 'ic_normal_payments', array() );

	return $payments;
}

class ic_cart_payments {

	public $cart_settings;

	function __construct() {
		$this->settings();
		$this->hooks();
		add_action( 'wp', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_hooks' ) );
	}

	function init() {
		$this->wp_hooks();
	}

	function hooks() {
		add_filter( 'ic_payment_statuses', array( $this, 'add_payment_status' ) );
		add_filter( 'order_completed_status_trigger', array( $this, 'add_payment_status_trigger' ) );
		add_filter( 'ic_default_order_status', array( $this, 'set_payment_default_order_status' ) );
		add_filter( 'ic_save_order_payment_details', array( $this, 'order_save_payment_label' ) );
	}

	function wp_hooks() {
		if ( is_ic_shopping_order() ) {
			if ( ! function_exists( 'order_form_payment_options' ) ) {
				add_filter( 'ic_formbuilder_before_button', array( $this, 'payment_options_html' ), 8, 2 );
			}
			add_filter( 'ic_formbuilder_user_email', array( $this, 'email_payment_details' ), 10, 2 );
			add_filter( 'ic_formbuilder_admin_email', array( $this, 'email_payment_details' ), 10, 2 );
		}
	}

	function admin_hooks() {
		add_filter( 'ic_payment_statuses', array( $this, 'add_payment_status' ) );
	}

	function settings() {
		$this->cart_settings = get_shopping_cart_settings();
	}

	/**
	 * Adds payment options to order form
	 *
	 * @param string $content
	 * @param string $pre_name
	 *
	 * @return string
	 */
	function payment_options_html( $content, $pre_name ) {
		if ( $pre_name == 'order_form_' || $pre_name == 'cart_' ) {
			$payment_options = apply_filters( 'payment_options', '' );
			if ( ! empty( $payment_options ) ) {
				$content .= '<div class="form_section payment-options-section">';
				$content .= '<div class="order_form_row row section_break"><h5 class="section-break"><strong>' . __( 'PAYMENT', 'ecommerce-product-catalog' ) . '</strong></h5></div>';
				$content .= $payment_options;
				$content .= '</div>';
			}
		}

		return $content;
	}

	/**
	 * Adds selected custom payment details if selected
	 *
	 * @param string $message
	 * @param string $pre_name
	 *
	 * @return string
	 */
	function email_payment_details( $message, $pre_name ) {
		if ( ic_string_contains( $message, '[payment_details]' ) ) {
			$payment_details = '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public checkout submission; the posted gateway is only used as a key into the ic_get_order_payments() allow-list and is normalized with sanitize_title() (not sanitize_text_field(), which would strip the %xx octets that sanitize_title() produces for non-ASCII gateway names).
			$posted_gateway = isset( $_POST['gateway'] ) ? sanitize_title( wp_unslash( $_POST['gateway'] ) ) : '';
			if ( $pre_name == 'cart_' && '' !== $posted_gateway ) {
				$normal_payemts = ic_get_order_payments();
				if ( ! empty( $normal_payemts[ $posted_gateway ] ) ) {
					$p               = ic_email_paragraph();
					$ep              = ic_email_paragraph_end();
					$payment_details = $p . __( 'Payment Method', 'ecommerce-product-catalog' ) . ': ' . $normal_payemts[ $posted_gateway ] . $ep;
				}
			}
			$message = str_replace( '[payment_details]', $payment_details, $message );
		}

		return $message;
	}

	function order_save_payment_label( $payment_details ) {
		$payment_details['payment_label'] = $this->current_payment_label();
		$payment_details['payment_name']  = $this->current_payment_name();

		return $payment_details;
	}

	function current_payment_label() {
		$payment_label = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public checkout submission; the posted gateway is only used as a key into the ic_get_order_payments() allow-list and is normalized with sanitize_title() (not sanitize_text_field(), which would strip the %xx octets that sanitize_title() produces for non-ASCII gateway names).
		$posted_gateway = isset( $_POST['gateway'] ) ? sanitize_title( wp_unslash( $_POST['gateway'] ) ) : '';
		if ( '' !== $posted_gateway ) {
			$normal_payments = ic_get_order_payments();
			if ( ! empty( $normal_payments[ $posted_gateway ] ) ) {
				$payment_label = $normal_payments[ $posted_gateway ];
			}
		}

		return $payment_label;
	}

	function current_payment_name() {
		$payment_name = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public checkout submission; the posted gateway is only used as a key into the ic_get_order_payments() allow-list and is normalized with sanitize_title() (not sanitize_text_field(), which would strip the %xx octets that sanitize_title() produces for non-ASCII gateway names).
		if ( isset( $_POST['gateway'] ) ) {
			// Normalize with sanitize_title(), NOT sanitize_text_field(). The gateway radio posts a
			// sanitize_title() slug of the custom payment name, and sanitize_title() PERCENT-ENCODES
			// non-ASCII names while sanitize_text_field() strips every %xx octet. The destroyed value is
			// persisted by order_save_payment_label() into _payment_details['payment_name'] and later read
			// by IC_Payment_Stats_Normalizer, which bucketed every such order as "unknown".
			// sanitize_title() is idempotent on an already-slugged value.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public checkout submission; the posted gateway is only used as a key into the ic_get_order_payments() allow-list and is normalized with sanitize_title() (not sanitize_text_field(), which would strip the %xx octets that sanitize_title() produces for non-ASCII gateway names).
			$payment_name = sanitize_title( wp_unslash( $_POST['gateway'] ) );
		}

		return $payment_name;
	}

	function add_payment_status( $statuses ) {
		if ( is_ic_any_payment_gateway_active() ) {
			if ( ! empty( $statuses['shipped'] ) ) {
				$old_status['shipped'] = $statuses['shipped'];
				unset( $statuses['shipped'] );
			}
			if ( ! empty( $statuses['completed'] ) ) {
				$old_status['completed'] = $statuses['completed'];
				unset( $statuses['completed'] );
			}
			$statuses['confirmed'] = __( 'Paid', 'ecommerce-product-catalog' );
			if ( ! empty( $old_status ) ) {
				$statuses = array_merge( $statuses, $old_status );
			}
		}

		return $statuses;
	}

	function add_payment_status_trigger( $status ) {
		if ( is_ic_any_payment_gateway_active() ) {
			$status = 'confirmed';
		}

		return $status;
	}

	function set_payment_default_order_status( $status ) {
		if ( is_ic_any_payment_gateway_active() ) {
			$status = 'pending';
		}

		return $status;
	}
}

$ic_cart_payments = new ic_cart_payments();
