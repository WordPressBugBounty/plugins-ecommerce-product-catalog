<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages digital orders save
 *
 * @version        1.0.0
 * @package        digital-products-orders/includes
 * @author        Norbert Dreszer
 */
class ic_orders_save {

	function __construct() {
		add_action( 'ic_formbuilder_before_mail', array( $this, 'save_formbuilder' ), 10, 5 );
		add_action( 'digital_order_delivery_details', array( $this, 'shipping_summary' ) );
		add_action( 'digital_order_data', array( $this, 'payment_label' ), 10, 2 );
		add_action( 'digital_order_data', array( $this, 'payment_data' ), 10, 2 );
		add_filter( 'ic_order_admin_payment_details_fields', array( $this, 'add_payment_data' ), 10, 2 );
		add_filter( 'ic_checkout_products_table_end', array( $this, 'trans_id' ) );

		add_shortcode( 'customer-orders', array( $this, 'orders_table' ) );
		add_shortcode( 'notification_order_id', array( $this, 'id_shortcode' ) );
		add_shortcode( 'notification_payment_id', array( $this, 'payment_id_shortcode' ) );

		add_filter( 'customer_panel_content', array( $this, 'orders_panel' ), 10, 2 );
		add_filter( 'customer_panel_tabs', array( $this, 'orders_tab' ) );

		add_filter( 'filter_ic_cart_empty', array( $this, 'replace_cart_content' ), 99 );
		add_filter( 'filter_ic_cart', array( $this, 'replace_cart_content' ), 99 );

		add_action( 'wp_ajax_change_order_name', array( $this, 'rename' ) );

		add_filter( 'ic_formbuilder_admin_email', array( $this, 'url_shortcode' ), 6, 2 );

		add_action( 'payment_status_change', array( $this, 'paid' ), 10, 4 );

		add_action( 'payment_quick_status_change', array( $this, 'quick_paid' ) );

		add_action( 'auto_order_completed', array( $this, 'delete_cron_customer_email' ), 10, 3 );

		add_filter( 'ic_update_order_summary', array( $this, 'update_shipping_summary' ), 10, 2 );
	}

	function save_formbuilder( $message, $customer_email, $redirect, $contact, $pre_name ) {
		if ( in_array( $pre_name, array( 'cart_', 'quote_cart_', 'order_form_' ), true ) ) {
			global $ic_formbuilder_filled_fields;
			$payment_details = apply_filters( 'ic_save_order_payment_details', $ic_formbuilder_filled_fields );
			$this->save( $customer_email, $pre_name, $payment_details );
		}
	}

	function save( $customer_email, $pre_name, $payment_details = array(), $order = array() ) {
		$payment_details['status']         = ic_get_default_order_status();
		$payment_details['date']           = current_time( 'timestamp' );
		$payment_details['shipping_email'] = empty( $payment_details['shipping_email'] ) ? $customer_email : $payment_details['shipping_email'];
		$payment_details['email']          = empty( $payment_details['email'] ) ? $customer_email : $payment_details['email'];
		$payment_details['currency']       = ! empty( $payment_details['currency'] ) ? $payment_details['currency'] : get_product_currency_code();
		$payment_details['order-currency'] = $payment_details['currency'];
		if ( empty( $order ) ) {
			$order = get_email_order_details();
		}
		$order_products = array(
			'product_id'        => $order['product_id'],
			'cart_id'           => isset( $order['cart_id'] ) ? $order['cart_id'] : '',
			'product_name'      => $order['product'],
			'product_quantity'  => $order['quantity'],
			'product_net_price' => $order['sum'],
			'product_price'     => $order['product_total'],
			'product_summary'   => $order['product_total'],
		);
		if ( ! empty( $order['product_gross'] ) ) {
			$order_products['product_gross_price'] = $order['product_gross'];
		}
		if ( ! empty( $order['product_total_net'] ) ) {
			$order_products['product_subtotal_net'] = $order['product_total_net'];
		}
		if ( isset( $order['variations'] ) ) {
			$order_products['variations'] = $order['variations'];
		}
		if ( isset( $order['variation_calculated'] ) ) {
			$order_products['variation_calculated'] = $order['variation_calculated'];
		}
		if ( isset( $order['variation_calculated_row'] ) ) {
			$order_products['variation_calculated_row'] = $order['variation_calculated_row'];
		}
		if ( isset( $order['color_picker'] ) ) {
			$order_products['color_picker'] = $order['color_picker'];
		}
		$order_summary = array(
			'price'     => $order['total_taxed'],
			'total_net' => $order['total_net'],
			'tax'       => $order['tax'],
			'handling'  => $order['handling'],
			'email'     => $customer_email,
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		$trans_id      = isset( $_POST['trans_id'] ) && is_scalar( $_POST['trans_id'] ) ? sanitize_text_field( wp_unslash( $_POST['trans_id'] ) ) : '';
		$order_id      = ic_update_digital_order_status( $payment_details, $order_products, $order_summary, $trans_id );
		ic_save_global( 'ic_save_order_id', $order_id );
		if ( function_exists( 'ic_count_shipping_cost_payment' ) ) {
			$shipping_summary['price'] = ic_count_shipping_cost_payment( 0, $pre_name );
			if ( ! empty( $order_id ) && ! is_wp_error( $order_id ) /* && !empty( $shipping_summary[ 'price' ] ) */ ) {
				$labels                     = ic_get_order_shipping_labels( $pre_name );
				$shipping_summary['labels'] = $labels;
				if ( function_exists( 'ic_get_order_shipping_display_labels' ) ) {
					$shipping_summary['display_labels'] = ic_get_order_shipping_display_labels( $pre_name );
				}
				if ( ! empty( $shipping_summary['labels'] ) || ! empty( $shipping_summary['display_labels'] ) ) {
					update_post_meta( $order_id, '_shipping_summary', $shipping_summary );
				}
			}
		}
		$cart_name    = function_exists( 'get_submitted_form_content_name' ) ? get_submitted_form_content_name() : $pre_name . 'content';
		$cart_content = ic_cart_content( true, $cart_name );
		if ( ! empty( $cart_content ) ) {
			update_post_meta( $order_id, '_cart_content', $cart_content );
		}
		do_action( 'ic_save_order', $order_id, $pre_name, $payment_details, $order_products, $order_summary, $trans_id, $cart_content );

		return $order_id;
	}

	function update_shipping_summary( $order_summary, $order_id ) {
		$shipping_summary = get_post_meta( $order_id, '_shipping_summary', true );
		if ( ! empty( $shipping_summary['price'] ) ) {
			$order_summary['price'] += $shipping_summary['price'];
		}

		return $order_summary;
	}

	function shipping_summary( $order_id ) {
		$shipping_summary = get_post_meta( $order_id, '_shipping_summary', true );
		if ( ! empty( $shipping_summary ) && ! empty( $shipping_summary['labels'] ) ) {
			?>
			<tr>
				<td><?php echo esc_html( __( 'Shipping', 'ecommerce-product-catalog' ) . ' (' . wp_strip_all_tags( $shipping_summary['labels'] ) . ')' ); ?></td>
				<td><?php echo esc_html( ic_shipping_price_format( $shipping_summary['price'] ) ); ?></td>
			</tr>
			<?php
		}
	}

	function payment_label( $order_id, $payment_details ) {
		if ( ! empty( $payment_details['payment_label'] ) ) {
			?>
			<tr>
				<td><?php echo esc_html__( 'Payment Gateway', 'ecommerce-product-catalog' ); ?>
					: <?php echo esc_html( $payment_details['payment_label'] ); ?></td>
			</tr>
			<?php
		}
		if ( ! empty( $payment_details['payment_id'] ) ) {
			?>
			<tr>
				<td><?php echo esc_html__( 'Payment ID', 'ecommerce-product-catalog' ); ?>
					: <?php echo esc_html( $payment_details['payment_id'] ); ?></td>
			</tr>
			<?php
		}
		if ( ! empty( $payment_details['payment_price'] ) ) {
			?>
			<tr>
				<td><?php echo esc_html__( 'Payment Price', 'ecommerce-product-catalog' ); ?>
					: <?php echo esc_html( $payment_details['payment_price'] ); ?></td>
			</tr>
			<?php
		}
	}

	function add_payment_data( $form, $payment_details ) {
		/*
		if ( count( $payment_details ) >= 30 ) {
			return $form;
		}
		*/
		$fields = $this->payment_fields();
		if ( ! empty( $fields ) ) {
			$form .= '<div class="ic-payment-data-form">';
			$form .= '<h2>' . __( 'Payment Data', 'ecommerce-product-catalog' ) . '</h2>';
			$form .= '<div class="table" style="margin: 0 auto;border-collapse:collapse;">';
			$form .= formbuilder_raw_fields(
				$fields,
				2,
				'payment_details_',
				$payment_details,
				array(
					'surname',
					'state',
				),
				true
			);
			$form .= '</div>';
			$form .= '</div>';
		}

		return $form;
	}

	function payment_data( $order_id, $payment_details ) {
		if ( count( $payment_details ) <= 30 ) {
			return;
		}
		$fields = $this->payment_fields();
		if ( ! empty( $fields ) ) {
			?>
			<tr>
				<td>
					<div class="ic-payment-data-form" style="max-width: 200px">
						<h2><?php esc_html_e( 'Payment Data', 'ecommerce-product-catalog' ); ?></h2>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formbuilder generates trusted controls and escapes their dynamic leaves; post KSES removes input and select elements.
						echo formbuilder_raw_fields(
							$fields,
							1,
							'payment_details_',
							$payment_details,
							array(
								'surname',
								'state',
							),
							true
						);
						?>
					</div>
				</td>
			</tr>
			<?php
		}
	}

	function payment_fields() {
		$normal_payments = ic_get_order_payments();
		if ( empty( $normal_payments ) ) {
			return '';
		}
		$default  = '{"fields":[{"label":"' . __( 'Order details', 'ecommerce-product-catalog' ) . '","field_type":"section_break","required":false,"cid":"inside_header_1"},{"label":"' . __( 'First Name', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"name"},{"label":"' . __( 'Last Name', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"surname"},{"label":"' . __( 'Email address', 'ecommerce-product-catalog' ) . ':","field_type":"email","required":false,"field_options":{"size":"medium"},"cid":"email"},{"label":"' . __( 'Phone number', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"phone"},{"label":"' . __( 'Billing address', 'ecommerce-product-catalog' ) . '","field_type":"section_break","required":false,"cid":"inside_header_2"},{"label":"' . __( 'Billing Name', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"billing_name"},{"label":"' . __( 'Street Address', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"address"},{"label":"' . __( 'ZIP / Postal Code', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"postcode"},{"label":"' . __( 'City', 'ecommerce-product-catalog' ) . ':","field_type":"text","required":false,"field_options":{"size":"medium"},"cid":"city"},{"label":"' . __( 'Country', 'ecommerce-product-catalog' ) . ':","field_type":"dropdown","required":false,"field_options":{"size":"medium"';
		$default .= ',"options":[';
		foreach ( implecode_supported_countries() as $code => $country ) {
			$default .= '{"label":"' . $country . '", "checked":false},';
		}
		$default .= ']},"cid":"country"},{"label":"' . __( 'State', 'ecommerce-product-catalog' ) . ':","field_type":"dropdown","required":false,"field_options":{"size":"medium"';
		$default .= ',"options":[';
		foreach ( implecode_supported_states() as $s_code => $state ) {
			$default .= '{"label":"' . $state . '", "checked":false},';
		}
		$default .= ']},"cid":"state"}]}';

		return str_replace( ',]', ']', $default );
	}

	function trans_id( $content ) {
		$trans_id = function_exists( 'ic_checkout_transaction_id' ) ? ic_checkout_transaction_id() : ic_generate_new_trans_id();
		$content .= '<input type="hidden" value="' . $trans_id . '" name="trans_id">';

		return $content;
	}

	function orders_table( $customer_id = null ) {
		if ( empty( $customer_id ) && function_exists( 'ic_get_logged_customer_id' ) ) {
			$customer_id = ic_get_logged_customer_id();
		}
		if ( empty( $customer_id ) ) {
			$pre_panel  = ic_customer_panel_actions();
			$pre_panel .= ic_digital_customer_login_form( true, 'login_form panel_login' );

			return $pre_panel;
		}
		$trans_ids = ic_filter_empty_array_values( ic_customer_transaction_ids( $customer_id ) );
		$table     = apply_filters( 'before_customer_panel_orders_table', '', $customer_id );
		if ( ! empty( $trans_ids ) ) {
			$table      .= '<div id="customer_orders_table" class="table">';
			$table      .= '<div class="table-row">';
			$table_heads = $this->table_heads();
			foreach ( $table_heads as $head ) {
				$table .= '<div class="table-head">' . $head . '</div>';
			}
			$table .= '</div>';
			$table .= apply_filters( 'before_customer_panel_orders', '', $customer_id );
			foreach ( $trans_ids as $trans_id ) {
				$order_customer_id = get_post_meta( $trans_id, '_customer_id', true );
				if ( empty( $order_customer_id ) || intval( $order_customer_id ) !== intval( $customer_id ) ) {
					continue;
				}
				$table      .= '<div class="table-row" data-order_id="' . $trans_id . '">';
				$table_cells = $this->table_cells( $trans_id );
				foreach ( $table_cells as $cell ) {
					$table .= '<div class="table-cell">' . $cell . '</div>';
				}
				$table .= '</div>';
			}
			$table .= '</div>';
		} else {
			$table .= __( "You don't have any completed orders yet.", 'ecommerce-product-catalog' );
		}

		return $table;
	}

	function orders_tab( $tabs ) {
		$tabs .= '<li><a href="#customer_panel_tabs-orders">' . __( 'Orders', 'ecommerce-product-catalog' ) . '</a></li>';

		return $tabs;
	}

	function orders_panel( $panel, $customer_id ) {
		$panel .= '<div id="customer_panel_tabs-orders">';
		$panel .= $this->orders_table( $customer_id );
		$panel .= '</div>';

		return $panel;
	}

	function table_cells( $order_id ) {
		$cart_content = $this->cart_content( $order_id );
		$cells        = array();
		$cells[]      = '<span class="dashicons dashicons-edit"></span>' . '<span class="order-name">' . $this->name( $order_id ) . '</span>';
		$cells[]      = '<span class="order-date">' . $this->date( $order_id ) . '</span>';
		$cells[]      = '<span class="order-status">' . $this->status( $order_id ) . '</span>';
		if ( ! empty( $cart_content ) ) {
			$cells[] = $this->replace_cart_content_button( $cart_content, $order_id );
		}

		return apply_filters( 'customer_orders_table_cells', $cells, $order_id );
	}

	function replace_cart_content_button( $new_cart_content, $order_id ) {
		if ( ! empty( $new_cart_content ) ) {
			$shopping_cart_settings = get_shopping_cart_settings();
			if ( empty( $shopping_cart_settings['url_button'] ) || $shopping_cart_settings['url_button'] != 1 ) {
				$button_class = 'button';
			} else {
				$button_class = 'link';
			}
			$button_class .= ' ' . design_schemes( 'box', 0 );
			$button_label  = ! empty( $shopping_cart_settings['button_label'] ) ? $shopping_cart_settings['button_label'] : __( 'Add to Cart', 'ecommerce-product-catalog' );
			$button_label  = apply_filters( 'ic_customer_order_repeat_button_label', $button_label, $order_id, $new_cart_content );
			$form          = '<form action="' . ic_shopping_cart_page_url() . '" method="post">';
			$form         .= '<input type="hidden" name="replace_cart_content" value="' . esc_attr( $new_cart_content ) . '" >';
			$form         .= '<input type="hidden" name="cart_content" value="' . esc_attr( $new_cart_content ) . '" >';
			$form         .= '<button type="submit" class="' . $button_class . '">' . esc_html( $button_label ) . ' (' . __( 'Order ID', 'ecommerce-product-catalog' ) . ': ' . $order_id . ')</button>';
			$form         .= '</form>';
		}

		return $form;
	}

	function replace_cart_content( $cart_content ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( ! empty( $_POST['replace_cart_content'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			$cart_content = ic_decode_json_cart( wp_unslash( $_POST['replace_cart_content'] ) );
		}

		return $cart_content;
	}

	function table_heads() {
		return apply_filters(
			'customer_orders_table_heads',
			array(
				__( 'Order Name', 'ecommerce-product-catalog' ),
				__( 'Date', 'ecommerce-product-catalog' ),
				__( 'Status', 'ecommerce-product-catalog' ),
				'',
			)
		);
	}

	function cart_content( $order_id, $array = false ) {
		$cart_content = get_post_meta( $order_id, '_cart_content', true );
		if ( $array ) {
			return ic_cart_products_array( $cart_content );
		}

		return $cart_content;
	}

	function date( $order_id ) {
		$payment_details = ic_get_order_payment_details( $order_id );
		if ( is_numeric( $payment_details['date'] ) ) {
			$order_date = date_i18n( get_option( 'date_format' ), $payment_details['date'] );
		} else {
			$order_date = $payment_details['date'];
		}

		return $order_date;
	}

	function name( $order_id ) {
		$payment_details = ic_get_order_payment_details( $order_id );

		return ! empty( $payment_details['order_name'] ) ? sanitize_text_field( $payment_details['order_name'] ) : __( 'My Order', 'ecommerce-product-catalog' );
	}

	function status( $order_id ) {
		$payment_details = ic_get_order_payment_details( $order_id );
		$statuses        = ic_available_payment_status();
		if ( empty( $payment_details['status'] ) ) {
			$current_status = strtolower( array_key_first( $statuses ) );
			$status         = reset( $statuses );
		} else {
			$current_status = strtolower( $payment_details['status'] );
			if ( isset( $statuses[ $current_status ] ) ) {
				$status = $statuses[ $current_status ];
			} else {
				$status = reset( $statuses );
			}
		}
		if ( $current_status === 'pending' && ! empty( $payment_details['payment_price'] ) ) {
			$status .= ' (' . __( 'Partial Payment', 'ecommerce-product-catalog' ) . ')';
		}

		return '<div class="box-' . $current_status . '">' . $status . '</div>';
	}

	/**
	 * Renames one of the logged-in customer's own orders via AJAX.
	 *
	 * Registered as wp_ajax_change_order_name only, with no nopriv twin, so the caller is
	 * always authenticated and WordPress mints a real per-user nonce. The shared ic_ajax
	 * nonce is localized as ic_customer_panel.ajax_nonce by ic_customer_panel::customer_panel()
	 * and posted by customers/js/customer-panel.js, which already uses it for the sibling
	 * customer_panel_password_reset call on the same screen.
	 *
	 * The nonce only closes the CSRF hole. Order ownership stays the primary control: the
	 * order id must appear in the logged-in customer's own transaction list. No capability
	 * check is used, because ownership rather than a role is the correct boundary for a
	 * customer-facing endpoint.
	 */
	function rename() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_ajax' ) ) {
			wp_die();
		}
		if ( ! empty( $_POST['order_id'] ) && ! empty( $_POST['new_name'] ) && is_ic_digital_customer() ) {
			$customer_id              = ic_get_logged_customer_id();
			$customer_transaction_ids = ic_customer_transaction_ids( $customer_id );
			if ( ! empty( $customer_transaction_ids ) ) {
				$order_id = intval( $_POST['order_id'] );
				if ( in_array( $order_id, $customer_transaction_ids ) ) {
					$payment_details               = get_post_meta( $order_id, '_payment_details', true );
					$payment_details['order_name'] = sanitize_text_field( wp_unslash( $_POST['new_name'] ) );
					update_post_meta( $order_id, '_payment_details', $payment_details );
					echo 'done';
				}
			}
		}
		wp_die();
	}

	function url_shortcode( $message, $pre_name ) {
		if ( $pre_name == 'cart_' || $pre_name == 'quote_cart_' ) {
			$order_id = ic_get_global( 'ic_save_order_id' );
			if ( ! empty( $order_id ) ) {
				$message = str_replace( '[edit_order_url]', ic_email_button( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ) . __( 'See Order', 'ecommerce-product-catalog' ) . '</a>', $message );
			} else {
				$message = str_replace( '[edit_order_url]', '', $message );
			}
		}

		return $message;
	}

	function current_order_id() {
		$order_id = ic_get_global( 'ic_save_order_id' );

		return apply_filters( 'ic_current_order_id', $order_id );
	}

	/**
	 * Clears the active local order runtime state.
	 *
	 * Keeps cart contents and plugin-specific order state intact while
	 * invalidating the current local order flow after context changes.
	 *
	 * @return void
	 */
	function clear_current_order() {
		ic_delete_global( 'ic_save_order_id' );
		ic_delete_global( 'payment_order_details' );

		$session = get_product_catalog_session();
		if ( isset( $session['trans_id'] ) ) {
			unset( $session['trans_id'] );
			set_product_catalog_session( $session );
		}
	}

	function id_shortcode( $order_id = null ) {
		if ( empty( $order_id ) || is_array( $order_id ) ) {
			$order_id = $this->current_order_id();
		}

		return apply_filters( 'ic_notification_order_id', intval( $order_id ) );
	}

	function payment_id_shortcode( $order_id = null ) {
		if ( empty( $order_id ) || is_array( $order_id ) ) {
			$order_id = $this->current_order_id();
		}
		if ( ! empty( $order_id ) ) {
			$payment_details               = ic_get_order_payment_details( $order_id );
			$payment_details['payment_id'] = isset( $payment_details['payment_id'] ) ? $payment_details['payment_id'] : '';

			return apply_filters( 'ic_notification_payment_id', $payment_details['payment_id'], $order_id, $payment_details );
		}
	}

	function paid( $payment_details = array(), $order_products = array(), $order_summary = array(), $trans_id = 0 ) {
		if ( ! is_array( $payment_details ) || ! is_array( $order_products ) || ! is_array( $order_summary ) || empty( $trans_id ) || empty( $order_products['product_id'] ) || ! isset( $order_summary['price'] ) || empty( $payment_details['currency'] ) ) {
			return;
		}
		global $verify_payment;
		$verify_payment['payment_details'] = $payment_details;
		$verify_payment['order_products']  = $order_products;
		$verify_payment['order_summary']   = $order_summary;
		do_action( 'ic_verify_payment_start', $verify_payment );
		if ( ic_verify_payment_price( $order_products['product_id'], $order_summary['price'], $payment_details['currency'], $order_products, $trans_id ) ) {
			$payment_details['status'] = 'confirmed';
			if ( ic_digital_order_status( $trans_id ) != $payment_details['status'] ) {
				do_action( 'ic_verify_payment_paid', $payment_details, $order_products, $order_summary, $trans_id );
				ic_update_digital_order_status( $payment_details, $order_products, $order_summary, $trans_id, true );
			}
		}
		do_action( 'ic_verify_payment_end', $verify_payment );
	}

	function quick_paid( $trans_id ) {
		$payment_details['status'] = 'confirmed';
		ic_update_digital_order_status( $payment_details, '', '', $trans_id, true );
	}

	function delete_cron_customer_email( $order_id, $payment_details, $order_products ) {
		$this->delete_order_email_cron( $payment_details['shipping_email'] );
	}

	function delete_order_email_cron( $email ) {
		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return;
		}
		foreach ( $crons as $timestamp => $cron ) {
			if ( isset( $cron['send_cron_email_order'] ) ) {
				foreach ( $cron['send_cron_email_order'] as $hash => $current_cron ) {
					if ( $current_cron['args'][0] == $email ) {
						unset( $crons[ $timestamp ]['send_cron_email_order'][ $hash ] );
						if ( empty( $crons[ $timestamp ]['send_cron_email_order'] ) ) {
							unset( $crons[ $timestamp ]['send_cron_email_order'] );
						}
					}
				}
			}
		}
		_set_cron_array( $crons );
	}
}

global $ic_orders_save;
$ic_orders_save = new ic_orders_save();

if ( ! function_exists( 'get_email_order_details' ) ) {

	function get_email_order_details() {
		$order = ic_get_global( 'payment_order_details' );
		if ( empty( $order ) ) {
			$order = apply_filters( 'payment_order_details', array() );
			ic_save_global( 'payment_order_details', $order );
		}

		return $order;
	}

}

function ic_order_completed_status_trigger() {
	return apply_filters( 'order_completed_status_trigger', 'completed' );
}
