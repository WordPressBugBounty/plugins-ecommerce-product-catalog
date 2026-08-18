<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages digital orders functions
 *
 * Here all plugin functions are defined and managed.
 *
 * @version        1.0.0
 * @package        digital-products-order/functions
 * @author        Norbert Dreszer
 */
function ic_available_payment_status() {
	$status              = array();
	$status['pending']   = __( 'Pending', 'ecommerce-product-catalog' );
	$status['shipped']   = __( 'Shipped', 'ecommerce-product-catalog' );
	$status['completed'] = __( 'Complete', 'ecommerce-product-catalog' );

	return apply_filters( 'ic_payment_statuses', $status );
}

function ic_order_status_dropdown( $selected = null, $name = 'payment_details_status' ) {
	?>
	<select name="<?php echo esc_attr( $name ); ?>">
		<?php
		$statuses = ic_available_payment_status();
		foreach ( $statuses as $name => $status ) {
			?>
			<option name="payment_status[<?php echo esc_attr( $name ); ?>]"
					value="<?php echo esc_attr( $name ); ?>" <?php selected( $name, $selected ); ?> ><?php echo esc_html( $status ); ?></option>
			<?php
		}
		?>
	</select>
	<?php
}

function ic_product_edit_url( $product_id ) {
	$site    = site_url();
	$product = $site . '/wp-admin/post.php?post=' . $product_id . '&action=edit';

	return $product;
}

function ic_order_details_fields() {
	$fields = array(
		'status',
		'date',
		'name',
		'email',
		'billing_name',
		'tax_code',
		'street',
		'postcode',
		'city',
		'country',
		'country_code',
		'shipping_email',
		'currency',
		'vatid',
		'vat_name',
		'vat_address',
		'vat_country',
		'vat_ver_id',
	);

	return $fields;
}

function ic_order_product_fields() {
	$fields = array(
		'product_id',
		'product_name',
		'product_quantity',
		'product_net_price',
		'product_price',
		'product_summary',
	);

	return $fields;
}

/**
 * Returns auto order product array
 *
 * @param int $order_id
 *
 * @return array Contains product_id, product_name, product_quantity, product_price, product_summary keys
 */
function ic_get_order_products( $order_id ) {
	if ( ! empty( $order_id ) && is_numeric( $order_id ) ) {
		$order_products = get_post_meta( $order_id, '_order_products', true );
	} elseif ( ! empty( $order_id ) && is_array( $order_id ) ) {
		$order_products = $order_id;
	}
	if ( empty( $order_products ) ) {
		$order_products = array();
	}
	$fields         = ic_order_product_fields();
	$order_products = implecode_array_variables_init( $fields, $order_products );

	return $order_products;
}

/**
 * Returns manual order product ids array
 *
 * @param int $order_id
 *
 * @return array of product ids
 */
function ic_get_manual_order_products( $order_id ) {
	$order_products  = ic_get_all_manual_order_products( $order_id );
	$custom_products = array();
	if ( is_array( $order_products ) ) {
		foreach ( $order_products as $key => $product ) {
			if ( $product['id'] != '' ) {
				$custom_products[ $key ] = $product;
			}
		}
	}

	return $custom_products;
}

/**
 * Returns all order products ids (auto and manual)
 *
 * @param int $order_id
 *
 * @return array
 */
function ic_get_order_product_ids( $order_id ) {
	$order_products        = ic_get_order_products( $order_id );
	$manual_order_products = ic_get_manual_order_products( $order_id );
	$product_ids           = array();
	if ( isset( $order_products['product_id'] ) && ! empty( $order_products['product_id'] ) ) {
		if ( is_array( $order_products['product_id'] ) ) {
			$product_ids = $order_products['product_id'];
		} else {
			$product_ids[] = $order_products['product_id'];
		}
	}
	if ( ! empty( $manual_order_products ) ) {
		foreach ( $manual_order_products as $product ) {
			if ( ! empty( $product['id'] ) ) {
				$product_ids[] = $product['id'];
			}
		}
	}

	return $product_ids;
}

function ic_get_custom_manual_order_products( $order_id ) {
	$order_products  = ic_get_all_manual_order_products( $order_id );
	$custom_products = array();
	if ( ! empty( $order_products ) ) {
		foreach ( $order_products as $product ) {
			if ( $product['id'] == '' ) {
				$custom_products[] = $product;
			}
		}
	}

	return $custom_products;
}

function ic_get_all_manual_order_products( $order_id ) {
	if ( ! empty( $order_id ) && is_numeric( $order_id ) ) {
		$order_products = get_post_meta( $order_id, 'manual_order_product', true );
	}
	if ( empty( $order_products ) || ! is_array( $order_products ) ) {
		$order_products = array();
	}

	return $order_products;
}

function ic_get_digital_product_name( $product_id ) {
	$product_name = get_the_title( $product_id );
	if ( $product_name == '' ) {
		$order_id       = explode( '_', $product_id );
		$order_id       = $order_id[3];
		$order_products = ic_get_custom_manual_order_products( $order_id );
		foreach ( $order_products as $product ) {
			if ( $product['c_id'] == $product_id ) {
				$product_name = $product['name'];
			}
		}
	}

	return $product_name;
}

function ic_add_row_button() {
	?>
	<div class="add-digital-product">
		<input type="button" id="add-digital-product" class="button" name="add_product"
				value="<?php esc_html_e( 'Add Product', 'ecommerce-product-catalog' ); ?>"/>
		<input type="button" id="add-custom-digital-product" class="button" name="add_custom_product"
				value="<?php esc_html_e( 'Add Custom Product', 'ecommerce-product-catalog' ); ?>"/></div>
	<?php
}

function ic_get_manual_products( $manual_order_product, $custom_id = null ) {
	$manual_products = array();
	if ( ! empty( $manual_order_product ) ) {
		foreach ( $manual_order_product as $manual_product ) {
			if ( ! empty( $manual_product['id'] ) ) {
				$manual_product_ids[] = $manual_product['id'];
			} elseif ( ! empty( $manual_product['name'] ) && is_numeric( $manual_product['name'] ) && is_ic_product( $manual_product['name'] ) ) {
				$manual_product_ids[] = intval( $manual_product['name'] );
			} elseif ( ! empty( $custom_id ) ) {
					$custom_manual_products[] = $manual_product['name'] . '|' . $manual_product['c_id'];
			} else {
				$custom_manual_products[] = $manual_product['name'];
			}
		}
		$manual_product_ids                        = isset( $manual_product_ids ) ? implode( ',', $manual_product_ids ) : '';
		$custom_manual_products                    = isset( $custom_manual_products ) ? implode( ',', $custom_manual_products ) : '';
		$manual_products['manual_product_ids']     = $manual_product_ids;
		$manual_products['custom_manual_products'] = $custom_manual_products;
	}

	return $manual_products;
}

function ic_should_digital_order_be_taxed( $order_id ) {
	$return = false;
	if ( function_exists( 'eu_tax_system_enabled' ) && eu_tax_system_enabled() ) {
		$payment_details                 = ic_get_order_payment_details( $order_id );
		$payment_details['country_code'] = ( isset( $payment_details['country_code'] ) && ! empty( $payment_details['country_code'] ) ) ? $payment_details['country_code'] : $payment_details['country'];
		if ( empty( $payment_details['country_code'] ) && ! empty( $payment_details['customer_country'] ) ) {
			$payment_details['country_code'] = $payment_details['customer_country'];
		}
		if ( is_eu_country( $payment_details['country_code'] ) ) {
			if ( ! empty( $payment_details['vatid'] ) && ! empty( $payment_details['vat_country'] ) && ! empty( $payment_details['vat_ver_id'] ) ) {
				if ( is_home_country( $payment_details['vat_country'] ) ) {
					$return = true;
				}
			} else {
				$return = true;
			}
		}
	}

	return $return;
}

/**
 * Returns order payment details
 *
 * @param int $order_id
 *
 * @return array
 */
function ic_get_order_payment_details( $order_id ) {
	$payment_details = ic_decode_payment_details( get_post_meta( $order_id, '_payment_details', true ) );
	$fields          = ic_order_details_fields();
	$fields[]        = 'address';
	$payment_details = implecode_array_variables_init( $fields, $payment_details );
	if ( function_exists( 'get_product_currency_code' ) ) {
		$default_currency = get_product_currency_code();
	} else {
		$default_currency = apply_filters( 'ic_payment_currency', '', $order_id );
	}
	$payment_details['currency'] = ! empty( $payment_details['currency'] ) ? $payment_details['currency'] : $default_currency;

	return $payment_details;
}

/**
 * Returns the current order completion status.
 *
 * @return string
 */
function ic_get_order_completion_status() {
	$status = sanitize_key( ic_order_completed_status_trigger() );
	if ( empty( $status ) || 'shipped' === $status ) {
		$status = 'completed';
	}

	return $status;
}

/**
 * Checks whether a status counts as completed.
 *
 * @param string $status Order status.
 *
 * @return bool
 */
function ic_is_order_completion_status( $status ) {
	$status = sanitize_key( (string) $status );
	if ( empty( $status ) ) {
		return false;
	}

	return 'completed' === $status || ic_get_order_completion_status() === $status;
}

/**
 * Checks whether `shipped` should auto-promote through completion first.
 *
 * @param string $status         Requested status.
 * @param string $current_status Current stored status.
 * @param int    $order_id       Order ID.
 *
 * @return bool
 */
function ic_should_auto_promote_shipped_status( $status, $current_status = '', $order_id = 0 ) {
	$status         = sanitize_key( (string) $status );
	$current_status = sanitize_key( (string) $current_status );
	if ( 'shipped' !== $status || 'shipped' === $current_status || ic_is_order_completion_status( $current_status ) ) {
		return false;
	}
	if ( ! empty( $order_id ) && get_post_meta( $order_id, '_order_completed_triggered', true ) ) {
		return false;
	}

	return true;
}

/**
 * Returns order totals, currency and email
 *
 * @param int $order_id
 *
 * @return array
 */
function ic_get_order_summary( $order_id ) {
	$fields        = array( 'price', 'email' );
	$order_summary = implecode_array_variables_init( $fields, get_post_meta( $order_id, '_order_summary', true ) );
	if ( empty( $order_summary['currency'] ) ) {
		$payment_details           = ic_get_order_payment_details( $order_id );
		$order_summary['currency'] = $payment_details['currency'];
	}

	return $order_summary;
}

function ic_get_order_email( $order_id ) {
	$order_summary = ic_get_order_summary( $order_id );
	if ( isset( $order_summary['email'] ) ) {
		return $order_summary['email'];
	}
	$payment_details = ic_get_order_payment_details( $order_id );
	$email           = isset( $payment_details['customer_email'] ) ? $payment_details['customer_email'] : '';
	if ( empty( $email ) ) {
		$email = isset( $payment_details['shipping_email'] ) ? $payment_details['shipping_email'] : '';
	}

	return $email;
}

/**
 * Decodes payment details saved in database
 *
 * @param array $data
 *
 * @return array
 */
function ic_decode_payment_details( $data ) {
	$decoded = array();
	if ( is_array( $data ) ) {
		$charset = get_option( 'blog_charset' );
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) || empty( $value ) ) {
				$decoded[ $key ] = $value;
				continue;
			}
			$decoded[ $key ] = stripslashes( $value );
			$decoded[ $key ] = html_entity_decode( $decoded[ $key ], ENT_NOQUOTES, $charset );
			$decoded[ $key ] = html_entity_decode( $decoded[ $key ], ENT_COMPAT, $charset );
		}
	}

	return $decoded;
}

add_filter( 'is_ic_catalog_admin_page', 'ic_orders_as_catalog_admin_page' );

function ic_orders_as_catalog_admin_page( $is_catalog_page ) {
	$screen = get_current_screen();
	if ( isset( $screen->id ) && ic_string_contains( $screen->id, 'al_digital_orders' ) ) {
		return true;
	}

	return $is_catalog_page;
}

function ic_send_error_message( $topic, $message ) {
	if ( function_exists( 'start_shopping_cart' ) ) {
		$settings = get_shopping_cart_settings();
		$email    = $settings['receive_cart'];
	} elseif ( function_exists( 'start_easy_orders' ) ) {
		$email = get_option( 'easy_email' );
	}
	if ( ! empty( $email ) ) {
		// wp_mail( $email, $topic, $message, 'From: Error Message <' . $email . '>' );
	}
	ic_error_log( $message );
}

add_action( 'auto_order_completed', 'ic_maybe_order_completed_message', 15, 4 );

function ic_maybe_order_completed_message( $order_id, $payment_details, $order_products, $manual_order_product = null ) {
	if ( ! apply_filters( 'ic_orders_enable_completion_message', false, $order_id, $payment_details, $order_products, $manual_order_product ) ) {
		return;
	}

	ic_order_completed_message( $order_id, $payment_details, $order_products, $manual_order_product );
}

/**
 * Sends order to customer
 *
 * @param int   $order_id
 * @param array $payment_details
 * @param array $order_products
 * @param array $manual_order_product
 */
function ic_order_completed_message( $order_id, $payment_details, $order_products, $manual_order_product = null ) {
	$shopping_cart_settings = get_shopping_cart_settings();
	if ( empty( $shopping_cart_settings['complete_email'] ) ) {
		return;
	}
	$admin_email    = ic_get_order_messages_email();
	$customer_email = $payment_details['shipping_email'];
	$site_name      = get_easy_order_sitename();
	$attachments    = array();
	$attachment     = apply_filters( 'digital_order_attachments', $attachments, $order_id );
	$p              = ic_email_paragraph();
	$ep             = ic_email_paragraph_end();
	$message        = str_replace(
		array(
			'[customer_name]',
			'[site_name]',
			'[system_message_notes]',
		),
		array(
			trim( $payment_details['name'] ),
			$site_name,
			apply_filters( 'digital_order_message_intro', '', $order_id ),
		),
		$shopping_cart_settings['complete_email']
	);
	$message        = shortcode_unautop( wpautop( $message ) );
	$message        = str_replace( array( '<p>', '</p>' ), array( $p, $ep ), $message );
	$email_title    = ! empty( $shopping_cart_settings['complete_email_subject'] ) ? $shopping_cart_settings['complete_email_subject'] : __( 'Order Completed', 'ecommerce-product-catalog' );
	ic_mail( do_shortcode( $message ), $site_name, $admin_email, $customer_email, $email_title, true, $attachment );
}

function ic_format_vat_address( $address ) {
	if ( ! empty( $address ) ) {
		if ( strpos( $address, '::' ) !== false ) {
			$address = explode( '::', $address );
		} elseif ( strpos( $address, '  ' ) !== false ) {
			$address = explode( '  ', $address );
		} else {
			$address = explode( ', ', $address );
		}
		if ( $address[0] == '' ) {
			unset( $address[0] );
			foreach ( $address as $i => $add ) {
				$new_address[ $i - 1 ] = $add;
			}
			$address = $new_address;
			// $address[ 0 ]  = isset( $address[ 1 ] ) ? $address[ 1 ] : '';
			// $address[ 1 ]  = isset( $address[ 2 ] ) ? $address[ 2 ] : '';
			// $address[ 2 ]  = '';
		}
		foreach ( $address as $key => $val ) {
			if ( $key != 0 && $key != 1 && $val != '' ) {
				$address[1] .= ' ' . $val;
			}
		}
	} else {
		$address = array();
	}
	$address[0] = isset( $address[0] ) ? $address[0] : '';
	$address[1] = isset( $address[1] ) ? $address[1] : '';

	return $address;
}

/**
 * Returns order product price
 *
 * @param type $order_id
 * @param type $product_id
 *
 * @return boolean
 */
function ic_get_order_product_price( $order_id, $product_id ) {
	$order_products = ic_get_order_products( $order_id );
	if ( isset( $order_products['product_id'] ) && is_array( $order_products['product_id'] ) ) {
		$index = array_search( $product_id, $order_products['product_id'] );
		if ( $index !== false && isset( $order_products['product_price'][ $index ] ) ) {
			return $order_products['product_price'][ $index ];
		}
	} elseif ( isset( $order_products['product_price'] ) && ! is_array( $order_products['product_price'] ) ) {
		return $order_products['product_price'];
	}

	return false;
}

function ic_get_default_order_status() {
	$shopping_cart_settings = get_shopping_cart_settings();
	if ( ! empty( $shopping_cart_settings['order_default_status'] ) ) {
		$status = $shopping_cart_settings['order_default_status'];
	}
	if ( empty( $status ) ) {
		$status = apply_filters( 'ic_default_order_status', 'completed' );
	}

	return $status;
}

function ic_order_currency( $order_id ) {
	$payment_details = ic_get_order_payment_details( $order_id );
	$currency        = isset( $payment_details['currency'] ) ? $payment_details['currency'] : get_product_currency_code();
	if ( ! empty( $payment_details['order-currency'] ) ) {
		$currency = $payment_details['order-currency'];
	}

	return $currency;
}

function ic_generate_new_trans_id() {
	$time = current_time( 'timestamp' );
	$rand = wp_rand( 1, 9999 );
	return $time . $rand;
}

if ( ! function_exists( 'ic_checkout_transaction_id' ) ) {
	/**
	 * Returns the current checkout transaction ID or generates a new one.
	 *
	 * @return string
	 */
	function ic_checkout_transaction_id() {
		$trans_id = '';
		if ( function_exists( 'get_product_catalog_session' ) && function_exists( 'set_product_catalog_session' ) ) {
			$session = get_product_catalog_session();
			if ( ! empty( $session['trans_id'] ) && is_scalar( $session['trans_id'] ) ) {
				$trans_id = sanitize_text_field( (string) $session['trans_id'] );
				if ( empty( $trans_id ) || ( function_exists( 'ic_digital_order_status' ) && ic_digital_order_status( $trans_id ) !== 'pending' ) || ( function_exists( 'is_ic_shopping_cart_empty' ) && is_ic_shopping_cart_empty() ) ) {
					unset( $session['trans_id'] );
					set_product_catalog_session( $session );
					$trans_id = '';
				}
			}
		}
		if ( empty( $trans_id ) ) {
			$trans_id = ic_generate_new_trans_id();
		}

		return $trans_id;
	}
}

function ic_get_customer_orders( $customer_id ) {
	if ( empty( $customer_id ) ) {
		return array();
	}
	$orders = get_posts(
		array(
			'post_type'      => 'al_digital_orders',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This established query shape is required for the bounded lookup; changing storage, indexes, caching, or result semantics is outside this advisory boundary.
			'meta_key'       => '_customer_id',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This established query shape is required for the bounded lookup; changing storage, indexes, caching, or result semantics is outside this advisory boundary.
			'meta_value'     => $customer_id,
			'cache_results'  => false,
			'fields'         => 'ids',
			'posts_per_page' => - 1,
		)
	);
	if ( ! empty( $orders ) ) {
		return $orders;
	} else {
		return array();
	}
}
