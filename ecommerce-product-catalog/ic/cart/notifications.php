<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
 *
 *  @version       1.0.0
 *  @author        impleCode
 *
 */

class ic_cart_checkout_form_email {

	/**
	 * @var string
	 */
	private $order_reminder_meta = '_ic_order_reminder_sent';

	/**
	 * @var boolean
	 */
	private $price_summary = true;

	/**
	 * @var string
	 */
	public $p;

	/**
	 * @var string
	 */
	public $ep;

	/**
	 * @var float
	 */
	private $order_total = 0;

	/**
	 * @var float
	 */
	private $total_tax = 0;

	/**
	 * @var float
	 */
	private $total_net;

	/**
	 * @var array
	 */
	public $settings;

	/**
	 * @var string
	 */
	private $cart_name;

	/**
	 * @var string
	 */
	public $span;

	/**
	 * @var string
	 */
	public $espan;

	/**
	 * @var int
	 */
	private $order_id;

	function __construct( $run = true, $default_columns = true, $price = true, $settings = array(), $cart = 'cart_content' ) {
		$this->email_variables();
		if ( ! empty( $cart ) ) {
			$this->cart_name = $cart;
		}
		if ( ! $price ) {
			$this->price_summary = false;
		}
		if ( ! empty( $settings ) ) {
			$this->settings = $settings;
		} else {
			$this->settings = get_shopping_cart_settings();
		}
		if ( $run ) {
			add_filter( 'ic_formbuilder_admin_email', array( $this, 'modify_admin_email' ), 5, 2 );
			add_filter( 'ic_formbuilder_admin_email', array( $this, 'ensure_shipping_address_in_email' ), 8, 2 );
			add_filter( 'ic_formbuilder_admin_email', 'strip_shortcodes', 99 );

			add_filter( 'ic_formbuilder_user_email', array( $this, 'modify_user_email' ), 5, 2 );
			add_filter( 'ic_formbuilder_user_email', array( $this, 'ensure_shipping_address_in_email' ), 8, 2 );
			add_filter( 'ic_formbuilder_user_email', 'strip_shortcodes', 99 );

			add_action( 'ic_formbuilder_email', array( $this, 'send_packing_list' ), 10, 5 );

			add_action( 'ic_new_order_created', array( $this, 'reminder' ), 10, 6 );
			add_action( 'ic_send_order_reminder', array( $this, 'send_reminder' ), 10, 4 );
		}
		if ( $default_columns ) {
			$this->default_columns();
		}
	}

	function email_variables() {
		$this->p     = ic_email_paragraph( 'margin:0' );
		$this->ep    = ic_email_paragraph_end();
		$this->span  = '<span style="text-align:right;padding-right:5px;">';
		$this->espan = '</span>';
	}

	function default_columns( $price = true ) {
		add_filter( 'cart_checkout_user_product_data', array( $this, 'fix_paragraphs' ), 50 );
		add_filter( 'cart_checkout_admin_product_data', array( $this, 'fix_paragraphs' ), 50 );
		add_filter( 'ic_cart_notification_default_columns', array( $this, 'summary_name' ), 10, 8 );
		add_filter( 'ic_cart_notification_default_columns', array( $this, 'summary_sku' ), 15, 8 );
		if ( $this->settings['cart_page_template'] === 'qty' ) {
			add_filter( 'ic_cart_notification_default_columns', array( $this, 'summary_qty' ), 20, 8 );
		}
		if ( $this->price_summary ) {
			add_filter( 'ic_cart_notification_default_columns', array( $this, 'summary_price' ), 25, 8 );
			add_filter( 'ic_cart_notification_default_columns', array( $this, 'summary_total' ), 30, 8 );
			add_filter( 'ic_cart_after_email_products_table', array( $this, 'summary_totals' ), 5 );
		}
	}

	function fix_paragraphs( $content ) {
		return str_replace( '<p>', $this->p, $content );
	}

	function reminder( $payment_details, $order_products, $order_summary, $trans_id, $order_id ) {
		$settings = get_shopping_cart_settings();
		if ( ! isset( $settings['unpaid_order_notification_delay'] ) || ( isset( $settings['unpaid_order_notification_delay'] ) && $settings['unpaid_order_notification_delay'] === '' ) ) {
			return;
		}
		if ( ! is_email( $payment_details['email'] ) ) {
			return;
		}
		$days = intval( $settings['unpaid_order_notification_delay'] );
		if ( empty( $days ) && empty( $settings['unpaid_order_notification_delay_date'] ) ) {
			return;
		}
		if ( ! empty( $settings['unpaid_order_notification_time'] ) ) {
			$time = strtotime( $settings['unpaid_order_notification_time'] );
		} else {
			$time = time();
		}
		if ( ! empty( $settings['unpaid_order_notification_delay_date'] ) ) {
			if ( ! empty( $payment_details[ $settings['unpaid_order_notification_delay_date'] ] ) ) {
				$new_time = strtotime( $payment_details[ $settings['unpaid_order_notification_delay_date'] ] . ' ' . wp_timezone_string() );
				if ( $new_time ) {
					$time = $new_time;
					if ( ! empty( $settings['unpaid_order_notification_time'] ) ) {
						$hours    = strtotime( $settings['unpaid_order_notification_time'] );
						$midnight = strtotime( 'today midnight' );
						if ( ! empty( $hours ) && ! empty( $midnight ) ) {
							$hours_in_seconds = $hours - $midnight;
							$time             = $time + $hours_in_seconds;
						}
					}
				}
			}
		}
		update_post_meta( $order_id, $this->order_reminder_meta, 1 );
		wp_schedule_single_event(
			$time + $days * DAY_IN_SECONDS,
			'ic_send_order_reminder',
			array(
				$payment_details['email'],
				$order_id,
				$order_products,
				$payment_details,
			)
		);
	}

	function correct_cron_time( $time ) {
		$crons = _get_cron_array();
		if ( isset( $crons[ $time ] ) ) {
			if ( count( $crons[ $time ] ) > 30 ) {
				$time = $time + 1;
				$time = $this->correct_cron_time( $time );
			}
		}

		return $time;
	}

	function send_reminder( $email, $order_id, $order_products, $payment_details ) {
		if ( empty( $order_id ) ) {
			return;
		}
		$status = ic_digital_order_status( '', $order_id );
		if ( $status == 'completed' ) {
			return;
		}
		if ( ! is_email( $email ) ) {
			return;
		}

		$sent = get_post_meta( $order_id, $this->order_reminder_meta, true );
		if ( empty( $sent ) || ( ! empty( $sent ) && $sent != 1 ) ) {
			return;
		}
		$settings = get_shopping_cart_settings();
		// $email         = ic_get_digital_customer_email( $customer_id );
		if ( ! empty( $settings['unpaid_order_notification'] ) && ! empty( $settings['unpaid_order_notification_subject'] ) && ! empty( $settings['send_cart'] ) && ! empty( $settings['cart_name'] ) && ! empty( $email ) ) {
			ic_mail( $settings['unpaid_order_notification'], $settings['cart_name'], $settings['send_cart'], $email, $settings['unpaid_order_notification_subject'] );
		}

		do_action( 'ic_sc_notification_send', $email, $order_id, $order_products, $payment_details, $settings );

		update_post_meta( $order_id, $this->order_reminder_meta, 2 );
	}

	/**
	 * Replaces customer_details shortcode in admin email template with order data
	 *
	 * @param string $message
	 * @param string $pre_name
	 *
	 * @return string
	 */
	function modify_admin_email( $message, $pre_name ) {
		if ( $pre_name == 'cart_' ) {
			$email_settings = get_shopping_cart_settings();
			$order_data     = $this->products_summary( 'admin' );
			$order_data    .= $message;
			$p              = ic_email_paragraph();
			$ep             = ic_email_paragraph_end();
			$new_message    = $email_settings['admin_email'];
			$new_message    = shortcode_unautop( wpautop( $new_message ) );
			$new_message    = str_replace( '[customer_details]', $order_data, $new_message );
			$new_message    = str_replace( array( '<p>', '</p>' ), array( $p, $ep ), $new_message );

			return do_shortcode( $new_message );
		}

		return $message;
	}

	/**
	 * Replaces customer_details shortcode in admin email template with order data
	 *
	 * @param string $message
	 * @param string $pre_name
	 *
	 * @return string
	 */
	function send_packing_list( $message, $customer_email, $redirect, $pre_name = null, $template = null ) {
		if ( $pre_name == 'cart_' ) {
			$email_settings = get_shopping_cart_settings();
			if ( empty( $email_settings['receive_packing_list'] ) ) {
				return;
			}
			$order_data  = $this->packing_list();
			$order_data .= $message;
			$p           = ic_email_paragraph();
			$ep          = ic_email_paragraph_end();
			$new_message = $email_settings['packing_email'];
			$new_message = shortcode_unautop( wpautop( $new_message ) );
			$new_message = str_replace( '[customer_details]', $order_data, $new_message );
			$new_message = do_shortcode( str_replace( array( '<p>', '</p>' ), array( $p, $ep ), $new_message ) );
			ic_mail( $new_message, $email_settings['cart_name'], $email_settings['send_cart'], $email_settings['receive_packing_list'], $email_settings['packing_email_subject'], $template );
		}

		return $message;
	}

	/**
	 * Replaces customer_details shortcode in customer email template with order data
	 *
	 * @param string $message
	 * @param string $pre_name
	 *
	 * @return string
	 */
	function modify_user_email( $message, $pre_name ) {
		if ( $pre_name == 'cart_' ) {
			$email_settings = get_shopping_cart_settings();
			$order_data     = $this->products_summary( 'user' );
			$order_data    .= $message;
			$p              = ic_email_paragraph();
			$ep             = ic_email_paragraph_end();
			$new_message    = $email_settings['user_email'];
			$new_message    = shortcode_unautop( wpautop( $new_message ) );
			$new_message    = str_replace( '[customer_details]', $order_data, $new_message );
			$new_message    = str_replace( array( '<p>', '</p>' ), array( $p, $ep ), $new_message );

			return do_shortcode( $new_message );
		}

		return $message;
	}

	/**
	 * Ensures the selected shipping address is present in notification templates.
	 *
	 * Complementary to ic_cart_shipping::shipping_address(), which only runs when the template
	 * carries the [shipping_address] token. This method covers templates without the token and
	 * bails when it is present, so the two can never both emit the delivery block.
	 *
	 * The matching shipping COST line is deliberately not handled here - summary_totals() already
	 * emits it inside the products summary.
	 *
	 * @param string $message  Email message.
	 * @param string $pre_name Form field prefix.
	 *
	 * @return string
	 */
	public function ensure_shipping_address_in_email( $message, $pre_name ) {
		if ( 'cart_' !== $pre_name || false !== strpos( $message, '[shipping_address]' ) ) {
			return $message;
		}

		$shipping_address = $this->shipping_address_email_block( $pre_name );
		if ( empty( $shipping_address['html'] ) ) {
			return $message;
		}

		if ( strpos( $message, $shipping_address['plain'] ) !== false ) {
			return $message;
		}

		if ( strpos( $message, '[payment_details]' ) !== false ) {
			return str_replace( '[payment_details]', $shipping_address['html'] . '[payment_details]', $message );
		}

		return $message . $shipping_address['html'];
	}

	/**
	 * Returns the selected shipping-address block for notification emails.
	 *
	 * @param string $pre_name Form field prefix.
	 *
	 * @return array
	 */
	public function shipping_address_email_block( $pre_name = 'cart_' ) {
		ic_shopping_cart_content( true );
		$shipping_address = apply_filters( 'ic_notification_shipping_address', '', $pre_name );
		if ( empty( $shipping_address ) ) {
			return array(
				'html'  => '',
				'plain' => '',
			);
		}

		$plain_address = trim( wp_strip_all_tags( str_replace( array( '<br />', '<br/>', '<br>' ), "\n", $shipping_address ) ) );
		if ( '' === $plain_address ) {
			return array(
				'html'  => '',
				'plain' => '',
			);
		}

		$plain = __( 'Delivery:', 'ecommerce-product-catalog' ) . "\n" . $plain_address;
		$html  = ic_email_paragraph( 'margin-bottom:0px;font-weight:bold;' ) . __( 'Delivery:', 'ecommerce-product-catalog' ) . ic_email_paragraph_end() . ic_email_paragraph() . $shipping_address . ic_email_paragraph_end();

		return array(
			'html'  => $html,
			'plain' => $plain,
		);
	}

	function summary_name( $pre_message, $product_id, $cart_id, $p_quantity, $product_price, $product_total, $td, $etd ) {

		$pre_message .= apply_filters( 'ic_cart_checkout_email_name_td', ic_email_table_td_first() . apply_filters( 'cart_email_product_name', html_entity_decode( get_the_title( $product_id ), ENT_COMPAT, 'UTF-8' ), $product_id, $cart_id ) . ic_email_table_td_end(), $product_id, $cart_id );

		return $pre_message;
	}

	function summary_sku( $pre_message, $product_id, $cart_id, $p_quantity, $product_price, $product_total, $td, $etd ) {
		if ( is_ic_cart_sku_enabled() ) {
			$sku          = get_product_sku( $product_id );
			$pre_message .= $td . apply_filters( 'ic_cart_product_sku', $sku, $cart_id ) . $etd;
		}

		return $pre_message;
	}

	function summary_qty( $pre_message, $product_id, $cart_id, $p_quantity, $product_price, $product_total, $td, $etd ) {
		$pre_message .= $td . apply_filters( 'ic_cart_checkout_email_qty_td', $p_quantity, $cart_id ) . $etd;

		return $pre_message;
	}

	function summary_price( $pre_message, $product_id, $cart_id, $p_quantity, $product_price, $product_total, $td, $etd ) {
		$pre_message .= $td . apply_filters( 'ic_cart_checkout_email_price_td', price_format( $product_price, 1, 0 ), $cart_id ) . $etd;

		return $pre_message;
	}

	function summary_total( $pre_message, $product_id, $cart_id, $p_quantity, $product_price, $product_total, $td, $etd ) {
		if ( '' === $product_price || false === $product_price ) {
			$pre_message .= $td . apply_filters( 'ic_cart_checkout_email_total_td', '', $cart_id ) . $etd;

			return $pre_message;
		}
		$pre_message .= $td . apply_filters( 'ic_cart_checkout_email_total_td', price_format( $product_total, 1, 0 ), $cart_id ) . $etd;

		return $pre_message;
	}

	/**
	 * Returns order products summary for email
	 *
	 * @param string $who
	 *
	 * @return string
	 */
	function products_summary( $who = '', $order_id = null ) {
		global $ic_orders_save;
		if ( ! empty( $order_id ) && ! empty( $ic_orders_save ) ) {
			$cart_content   = $ic_orders_save->cart_content( $order_id );
			$this->order_id = $order_id;
		}
		if ( empty( $cart_content ) ) {
			$cart_content = ic_shopping_cart_content( true );
		}
		$products_array  = shopping_cart_products_array( $cart_content );
		$pre_message     = '';
		$line            = '<br>';
		$this->total_net = 0;
		$td              = ic_email_table_td();
		$etd             = ic_email_table_td_end();
		$pre_message    .= ic_email_table();
		$pre_message    .= ic_email_table_th();

		$pre_message .= apply_filters( 'ic_cart_checkout_email_name_header', ic_email_table_td_first() . __( 'Product name', 'ecommerce-product-catalog' ) . ic_email_table_td_end(), $products_array );
		if ( is_ic_cart_sku_enabled() ) {
			$single_names = get_single_names();
			$pre_message .= $td . str_replace( ':', '', $single_names['product_sku'] ) . $etd;
		}
		if ( $this->settings['cart_page_template'] == 'qty' ) {
			$pre_message .= $td . __( 'Quantity', 'ecommerce-product-catalog' ) . $etd;
		}
		if ( $this->price_summary ) {
			$pre_message .= $td . __( 'Price', 'ecommerce-product-catalog' ) . $etd;
			$pre_message .= $td . __( 'Subtotal', 'ecommerce-product-catalog' ) . $etd;
		}
		$pre_message .= ic_email_table_th_end();
		global $ic_shopping_cart_totals;
		$ic_shopping_cart_totals['total'] = 0;
		foreach ( $products_array as $cart_id => $p_quantity ) {
			$product_id    = cart_id_to_product_id( $cart_id );
			$pre_message  .= ic_email_table_tr();
			$product_price = get_shopping_cart_product_price( $product_id, $cart_id, $p_quantity );
			$product_total = apply_filters( 'ic_cart_product_total_calc', floatval( $product_price ) * $p_quantity, $cart_id );
			if ( function_exists( 'ic_cart_update_tax' ) ) {
				ic_cart_update_tax( $product_id, $product_total, $p_quantity, $cart_id );
			}
			$this->total_net                  += $product_total;
			$ic_shopping_cart_totals['total'] += $product_total;

			$pre_message .= apply_filters( 'ic_cart_notification_default_columns', '', $product_id, $cart_id, $p_quantity, $product_price, $product_total, $td, $etd );
			$pre_message .= ic_email_table_tr_end();
		}
		$pre_message .= apply_filters( 'ic_cart_after_email_products', '', $products_array );
		$pre_message .= ic_email_table_end();
		$pre_message .= apply_filters( 'ic_cart_after_email_products_table', '', $this );

		return apply_filters( 'cart_checkout_' . $who . '_product_data', $pre_message, $this->order_total, $this->total_tax, $this->p, $this->ep, $line );
	}

	function summary_totals( $pre_message = '' ) {
		$pre_message .= '<div style="text-align:right;padding-right: 10px;">';
		global $order_price_effect_data;
		if ( isset( $order_price_effect_data ) && ! empty( $order_price_effect_data['message'] ) ) {
			$pre_message .= $this->p . $order_price_effect_data['message'] . $this->ep;
			if ( ! empty( $order_price_effect_data['total'] ) ) {
				$this->total_net += $order_price_effect_data['total'];
			}
		}
		$shipping = 0;
		if ( function_exists( 'ic_count_shipping_cost_payment' ) ) {
			$shipping = ic_count_shipping_cost_payment( 0, 'cart_' );
			if ( ! empty( $shipping ) ) {
				$labels = '';
				// Display only: the label goes straight into the customer order summary. Prefer the
				// display variant, which returns the human-readable option name. The plain
				// ic_get_order_shipping_labels() returns sanitize_title() slugs, so the customer read
				// "Shipping (express-shipping)" and, for non-Latin option names, a percent-encoded
				// string. Guarded the same way as ic/orders/includes/save-order.php and
				// ic/cart/qc-sc/payment-render.php. Do not revert this to the slug variant.
				// The function_exists() guard on the FALLBACK is deliberate - do not simplify this
				// back to a ternary. Some legacy cart layouts ship ic_count_shipping_cost_payment()
				// without either label function, and an unguarded fallback call is a fatal error
				// there. Same shape as ecommerce-product-catalog/modules/cart/includes/cart-form.php.
				if ( function_exists( 'ic_get_order_shipping_display_labels' ) ) {
					$labels = ic_get_order_shipping_display_labels( 'cart_' );
				} elseif ( function_exists( 'ic_get_order_shipping_labels' ) ) {
					$labels = ic_get_order_shipping_labels( 'cart_' );
				}
				if ( ! empty( $labels ) ) {
					$labels = ' (' . $labels . ')';
				}
				$shipping_label = __( 'Shipping', 'ecommerce-product-catalog' ) . $labels;
			}
		}
		$lines = array();
		if ( function_exists( 'is_ic_order_taxed' ) && is_ic_order_taxed() ) {
			$this->total_tax = ic_cart_get_tax();
			if ( is_ic_tax_included() ) {
				$this->total_net = $this->total_net - $this->total_tax;
			}
			$this->order_total = $this->total_net + $this->total_tax + $shipping;
			$lines[]           = array(
				'label' => apply_filters( 'ic_cart_total_net_label', $this->settings['summary_total_net_label'], $this->price_summary, $this->cart_name, $this->settings ),
				'value' => price_format( $this->total_net, 1, 0 ),
			);
			// $pre_message       .= $this->p . $this->span . apply_filters( 'ic_cart_total_net_label', $this->settings['summary_total_net_label'], $this->price_summary, $this->cart_name, $this->settings ) . ' ' . $this->espan . price_format( $this->total_net, 1, 0 ) . $this->ep;
			$lines[] = array(
				'label' => __( 'Tax', 'ecommerce-product-catalog' ),
				'value' => price_format( $this->total_tax, 1, 0 ),
			);
			// $pre_message .= $this->p . $this->span . __( 'Tax:', 'implecode-quote-cart' ) . ' ' . $this->espan . price_format( $this->total_tax, 1, 0 ) . $this->ep;
			if ( ! empty( $shipping_label ) ) {
				$lines[] = array(
					'label' => $shipping_label,
					'value' => price_format( $shipping ),
				);
				// $pre_message .= $this->p . $this->span . $shipping_label . ': ' . $this->espan . price_format( $shipping ) . $this->ep;
			}
			$lines[] = array(
				'label' => $this->settings['summary_total_gross_label'],
				'value' => price_format( $this->order_total, 1 ),
			);
			// $pre_message .= $this->p . $this->span . '<strong>' . $this->settings['summary_total_gross_label'] . ' ' . $this->espan . price_format( $this->order_total, 1 ) . '</strong>' . $this->ep;
		} else {
			$this->total_tax   = 0;
			$this->order_total = $this->total_net + $shipping;
			if ( ! empty( $shipping_label ) ) {
				$lines[] = array(
					'label' => $shipping_label,
					'value' => price_format( $shipping ),
				);
				// $pre_message .= $this->p . $this->span . $shipping_label . ': ' . $this->espan . price_format( $shipping ) . $this->ep;
			}
			$lines[] = array(
				'label' => apply_filters( 'ic_cart_total_net_label', $this->settings['summary_total_label'], $this->price_summary, $this->cart_name, $this->settings ),
				'value' => apply_filters( 'ic_cart_total_net_value', price_format( $this->order_total, 1 ), $this->price_summary, $this->cart_name, $this->settings ),
			);
			// $pre_message .= $this->p . $this->span . apply_filters( 'ic_cart_total_net_label', '<strong>' . $this->settings['summary_total_label'] . '</strong>', $this->price_summary, $this->cart_name, $this->settings ) . ' ' . $this->espan . apply_filters( 'ic_cart_total_net_value', '<strong>' . price_format( $this->order_total, 1 ) . '</strong>', $this->price_summary, $this->cart_name, $this->settings ) . $this->ep;
		}
		$lines = apply_filters( 'ic_cart_notification_pre_paid_total_lines', $lines, $this );
		if ( ! empty( $this->order_id ) ) {
			$payment_details = ic_get_order_payment_details( $this->order_id );
			if ( ! empty( $payment_details['payment_price'] ) && ! empty( $payment_details['currency'] ) ) {
				$lines[] = array(
					'label' => apply_filters( 'ic_odoo_ecommerce_paid_label', __( 'Paid', 'ecommerce-product-catalog' ) ),
					'value' => $payment_details['currency'] . ' ' . price_format( $payment_details['payment_price'], 1, 0 ),
				);
				// $pre_message .= $this->p . $this->span . apply_filters( 'ic_odoo_ecommerce_paid_label', __( 'Paid', 'ic-odoo-ecommerce' ) . ' =' ) . ' ' . $this->espan . $payment_details['payment_price'];
			}
		}
		$pre_message .= $this->total_lines( apply_filters( 'ic_cart_notification_total_lines', $lines, $this ) );

		return apply_filters( 'ic_cart_notification_totals', $pre_message, $this ) . '</div>';
	}

	function total_lines( $lines ) {
		if ( empty( $lines ) ) {
			return '';
		}
		$lines_html = '';
		$last_key   = ic_array_key_last( $lines );
		$strong     = false;
		foreach ( $lines as $key => $line ) {
			if ( $key === $last_key ) {
				$strong = true;
			}
			$lines_html .= $this->total_line( $line['label'], $line['value'], $strong );
		}

		return $lines_html;
	}

	function total_line( $label, $value, $strong = false ) {
		if ( $strong ) {
			$label = '<strong>' . $label . '</strong>';
		}

		return $this->p . $this->span . $label . ' ' . $this->espan . $value . $this->ep;
	}

	/**
	 * Returns order products summary for email
	 *
	 * @param string $message
	 * @param string $pre_name
	 *
	 * @return string
	 */
	function packing_list() {
		$cart_content   = ic_shopping_cart_content( true );
		$products_array = shopping_cart_products_array( $cart_content );
		$pre_message    = '';
		$line           = '<br>';
		$td             = ic_email_table_td();
		$etd            = ic_email_table_td_end();
		$pre_message   .= ic_email_table();
		$pre_message   .= ic_email_table_th();

		$pre_message .= apply_filters( 'ic_cart_checkout_email_name_header', ic_email_table_td_first() . __( 'Product name', 'ecommerce-product-catalog' ) . ic_email_table_td_end(), $products_array );
		if ( is_ic_cart_sku_enabled() ) {
			$single_names = get_single_names();
			$pre_message .= $td . str_replace( ':', '', $single_names['product_sku'] ) . $etd;
		}
		$pre_message .= $td . __( 'Quantity', 'ecommerce-product-catalog' ) . $etd;
		$pre_message .= ic_email_table_th_end();
		foreach ( $products_array as $cart_id => $p_quantity ) {
			$product_id   = cart_id_to_product_id( $cart_id );
			$pre_message .= ic_email_table_tr();
			$pre_message .= apply_filters( 'ic_cart_checkout_email_name_td', ic_email_table_td_first() . apply_filters( 'cart_email_product_name', html_entity_decode( get_the_title( $product_id ), ENT_NOQUOTES | ENT_HTML401, 'UTF-8' ), $product_id, $cart_id ) . ic_email_table_td_end(), $product_id );
			if ( is_ic_cart_sku_enabled() ) {
				$sku          = get_product_sku( $product_id );
				$pre_message .= $td . $sku . $etd;
			}
			$pre_message .= $td . $p_quantity . $etd;
		}
		$pre_message .= ic_email_table_end();
		$p            = ic_email_paragraph();
		$ep           = ic_email_paragraph_end();
		if ( function_exists( 'ic_count_shipping_cost_payment' ) ) {
			$shipping = ic_count_shipping_cost_payment( 0, 'cart_' );
			if ( ! empty( $shipping ) ) {
				$labels = '';
				// Display only: this string is printed in the customer packing-list email. Prefer the
				// display variant for the human-readable option name; ic_get_order_shipping_labels()
				// returns sanitize_title() slugs, which reach the shopper as "express-shipping" or, for
				// non-Latin names, as a percent-encoded string. Guarded like save-order.php and
				// payment-render.php. Do not revert this to the slug variant.
				// The function_exists() guard on the FALLBACK is deliberate - do not simplify this
				// back to a ternary. Some legacy cart layouts ship ic_count_shipping_cost_payment()
				// without either label function, and an unguarded fallback call is a fatal error
				// there. Same shape as ecommerce-product-catalog/modules/cart/includes/cart-form.php.
				if ( function_exists( 'ic_get_order_shipping_display_labels' ) ) {
					$labels = ic_get_order_shipping_display_labels( 'cart_' );
				} elseif ( function_exists( 'ic_get_order_shipping_labels' ) ) {
					$labels = ic_get_order_shipping_labels( 'cart_' );
				}
				if ( ! empty( $labels ) ) {
					$labels = ' (' . $labels . ')';
				}
				$shipping_label = __( 'Shipping', 'ecommerce-product-catalog' ) . $labels;
			}
			if ( ! empty( $shipping_label ) ) {
				$shipping_price = function_exists( 'ic_shipping_price_format' ) ? ic_shipping_price_format( $shipping ) : price_format( $shipping );
				$pre_message   .= $p . $shipping_label . ': ' . $shipping_price . $ep;
			}
		}

		return apply_filters( 'cart_checkout_packing_list_product_data', $pre_message, $p, $ep, $line );
	}
}
