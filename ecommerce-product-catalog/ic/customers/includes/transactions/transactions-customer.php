<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 *
 *
 *
 * Package: transactions-customer.php
 */
if ( ! class_exists( 'ic_orders_customer' ) ) {
class ic_orders_customer {
	public $settings;

	function __construct() {
		add_action( 'order_completed', array( $this, 'create' ), 10, 4 );
		add_action( 'order_completed_updated', array( $this, 'create' ), 10, 4 );
		add_action( 'ic_new_order_created', array( $this, 'new_create' ), 10, 5 );
		add_filter( 'ic_formbuilder_user_email', array( $this, 'login' ), 6, 2 );
		$this->settings = get_shopping_cart_settings();
		if ( ! empty( $this->settings['choose_form_save'] ) ) {
			add_filter( 'ic_form_fields', array( $this, 'save_checkout_row' ), 5, 2 );
		}
	}

	function new_create( $payment_details, $order_products, $order_summary, $trans_id, $order_id ) {
		$this->create( $order_id, $payment_details, $order_products );
	}

	/**
	 *
	 * @param int   $trans_id
	 * @param array $payment_details
	 * @param array $order_products
	 * @param array $manual_order_product
	 *
	 * @return int|void
	 */
	function create( $trans_id, $payment_details, $order_products, $manual_order_product = null ) {
		if ( ! apply_filters( 'ic_orders_customer_create', true, $trans_id, $payment_details, $order_products ) ) {
			return;
		}
		remove_action( 'order_completed', array( $this, 'create' ), 10, 4 );
		remove_action( 'ic_new_order_created', array( $this, 'new_create' ), 10, 4 );
		remove_action( 'order_completed_updated', array( $this, 'create' ), 10, 4 );
		$email = isset( $payment_details['customer_email'] ) ? $payment_details['customer_email'] : '';
		if ( empty( $email ) ) {
			$email = isset( $payment_details['shipping_email'] ) ? $payment_details['shipping_email'] : '';
		}
		if ( ! empty( $email ) ) {
			$product_id = isset( $order_products['product_id'] ) ? $order_products['product_id'] : '';
			if ( is_array( $product_id ) ) {
				$product_id = implode( ',', $product_id );
			}
			$manual_products = ic_get_manual_products( $manual_order_product, 1 );
			$account         = ic_customers_ensure_email_account( $email );
			$customer_id     = $account['id'];
			$just_created    = ! empty( $account['created'] );
			if ( ! is_ic_digital_customer( $customer_id ) ) {
				$just_created = true;
				add_ic_role( $customer_id, 'customer' );
			}
			if ( $just_created ) {
				$transaction_data = $this->normalized_customer_data( $payment_details, $trans_id );
				$this->update_data( $customer_id, $transaction_data );
				update_user_meta( $customer_id, 'transaction_ids', $trans_id );
				update_user_meta( $customer_id, 'product_ids', $product_id );
				do_action( 'new_digital_customer', $customer_id, $trans_id );
			} else {
				$prev_trans_id    = get_user_meta( $customer_id, 'transaction_ids', true );
				$prev_product_id  = get_user_meta( $customer_id, 'product_ids', true );
				$customer_license = get_user_meta( $customer_id, '_customer_license', true );
				$prev_trans_array = explode( ',', $prev_trans_id );
				if ( ! in_array( $trans_id, $prev_trans_array ) ) {
					$prev_trans_id .= ',' . $trans_id;
					$new_trans_ids  = $prev_trans_id;
					update_user_meta( $customer_id, 'transaction_ids', $new_trans_ids );
				}
				if ( ! empty( $product_id ) ) {
					$prev_product_id .= ',' . $product_id;
					$new_product_ids  = $prev_product_id;
					update_user_meta( $customer_id, 'product_ids', $new_product_ids );
				}
				if ( ! empty( $payment_details ) ) {
					$this->update_data( $customer_id, $this->normalized_customer_data( $payment_details, $trans_id ) );
				}
			}

			$manual_products['manual_product_ids']     = isset( $manual_products['manual_product_ids'] ) ? $manual_products['manual_product_ids'] : '';
			$manual_products['custom_manual_products'] = isset( $manual_products['custom_manual_products'] ) ? $manual_products['custom_manual_products'] : '';
			if ( ! empty( $manual_products['manual_product_ids'] ) ) {
				$old_manual_products = get_user_meta( $customer_id, 'manual_product_ids', true );
				if ( ! empty( $old_manual_products ) ) {
					$old_manual_products .= ',' . $manual_products['manual_product_ids'];
					$new_manual_products  = $old_manual_products;
				} else {
					$new_manual_products = $manual_products['manual_product_ids'];
				}
				update_user_meta( $customer_id, 'manual_product_ids', $new_manual_products );
			}
			if ( ! empty( $manual_products['custom_manual_products'] ) ) {
				$old_custom_manual_products = get_user_meta( $customer_id, 'custom_manual_products', true );
				if ( ! empty( $old_custom_manual_products ) ) {
					$old_custom_manual_products .= ',' . $manual_products['custom_manual_products'];
					$new_custom_manual_products  = $old_custom_manual_products;
				} else {
					$new_custom_manual_products = $manual_products['custom_manual_products'];
				}
				update_user_meta( $customer_id, 'custom_manual_products', $new_custom_manual_products );
			}

			update_post_meta( $trans_id, '_customer_id', $customer_id );

			do_action( 'existing_customer_buy', $customer_id, $trans_id, $product_id, $manual_products['manual_product_ids'], $manual_products['custom_manual_products'], $payment_details );

			if ( ! is_wp_error( $customer_id ) ) {
				return $customer_id;
			}
		}
	}

	function save_checkout_row( $fields, $pre_name ) {
		if ( $pre_name == 'quote_cart_' || $pre_name == 'cart_' ) {
			$field                         = new stdClass();
			$field->label                  = '';
			$field->cid                    = 'ic_save_form';
			$field->field_type             = 'checkboxes';
			$field->required               = false;
			$field->field_options          = new stdClass();
			$option                        = new stdClass();
			$option->label                 = __( 'Save address for future orders', 'ecommerce-product-catalog' );
			$option->checked               = false;
			$field->field_options->options = array( $option );
			$fields->fields[]              = $field;
		}

		return $fields;
	}

	function update_data( $customer_id, $customer_data ) {
		if ( ! empty( $this->settings['choose_form_save'] ) && empty( $customer_data['quote_cart_ic_save_form'] ) ) {

			return;
		}
		update_user_meta( $customer_id, '_customer_data', $customer_data );
		do_action( 'ic_customer_data_updated', '', $customer_id, $customer_data );
	}

	/**
	 * Normalizes checkout aliases into canonical customer fields.
	 *
	 * @param array $payment_details Submitted payment details.
	 * @param int   $trans_id        Order ID.
	 *
	 * @return array
	 */
	private function normalized_customer_data( $payment_details, $trans_id ) {
		$saved_details = get_post_meta( $trans_id, '_payment_details', true );
		if ( ! is_array( $saved_details ) ) {
			$saved_details = array();
		}
		if ( ! is_array( $payment_details ) ) {
			$payment_details = array();
		}

		$customer_data = array_merge( $saved_details, $payment_details );
		if ( empty( $customer_data['name'] ) && ! empty( $customer_data['customer_name'] ) ) {
			$customer_data['name'] = $customer_data['customer_name'];
		}
		if ( empty( $customer_data['email'] ) && ! empty( $customer_data['customer_email'] ) ) {
			$customer_data['email'] = $customer_data['customer_email'];
		}
		if ( empty( $customer_data['shipping_email'] ) && ! empty( $customer_data['customer_email'] ) ) {
			$customer_data['shipping_email'] = $customer_data['customer_email'];
		}
		if ( empty( $customer_data['billing_name'] ) && ! empty( $customer_data['name'] ) ) {
			$customer_data['billing_name'] = $customer_data['name'];
		}
		$filtered_customer_data = apply_filters( 'ic_order_customer_data', $customer_data, $trans_id, $payment_details );
		if ( is_array( $filtered_customer_data ) ) {
			$customer_data = $filtered_customer_data;
		}

		return $customer_data;
	}

	function login( $message, $pre_name ) {
		if ( $pre_name == 'cart_' ) {
			$userdata  = ic_get_global( 'ic_new_customer_data' );
			$panel_url = function_exists( 'ic_customer_panel_panel_url' ) ? ic_customer_panel_panel_url() : '';
			if ( ! empty( $panel_url ) ) {
				$message = ic_customers_replace_account_info_placeholder(
					$message,
					$userdata,
					array(
						'context'    => 'shopping_cart',
						'button_url' => $panel_url,
					)
				);
			} else {
				$message = str_replace( '[account_info]', '', $message );
			}
		}

		return $message;
	}
}

$ic_orders_customer = new ic_orders_customer();
}
