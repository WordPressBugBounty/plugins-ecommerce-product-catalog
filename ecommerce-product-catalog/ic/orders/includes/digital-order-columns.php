<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages digital orders fields
 *
 * Here all digital orders post type is defined and managed.
 *
 * @version        1.0.0
 * @package        digital-products-orders/includes
 * @author        Norbert Dreszer
 */
class ic_orders_columns {

	function __construct() {
		add_filter( 'manage_edit-al_digital_orders_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_al_digital_orders_posts_custom_column', array( $this, 'manage_columns' ), 10, 2 );
	}

	function labels() {
		$object = get_post_type_object( 'al_digital_orders' );

		return $object->labels;
	}

	function add_columns( $orders_columns ) {
		$new_columns['cb']      = '<input type="checkbox" />';
		$new_columns['id']      = __( 'ID', 'ecommerce-product-catalog' );
		$new_columns['status']  = __( 'Status', 'ecommerce-product-catalog' );
		$new_columns['product'] = __( 'Product', 'ecommerce-product-catalog' );
		if ( function_exists( 'is_ic_sku_enabled' ) && is_ic_sku_enabled() ) {
			$new_columns['sku'] = __( 'SKU', 'ecommerce-product-catalog' );
		}
		$new_columns['amount']     = __( 'Amount', 'ecommerce-product-catalog' );
		$new_columns['from']       = __( 'From', 'ecommerce-product-catalog' );
		$new_columns['trans_date'] = __( 'Date', 'ecommerce-product-catalog' );

		return apply_filters( 'ic_cart_orders_column_headers', $new_columns );
	}

	function manage_columns( $column_name, $order_id ) {
		$labels               = $this->labels();
		$payment_details      = get_post_meta( $order_id, '_payment_details', true );
		$order_products       = ic_get_order_products( $order_id );
		$order_summary        = get_post_meta( $order_id, '_order_summary', true );
		$customer_id          = get_post_meta( $order_id, '_customer_id', true );
		$manual_order_product = ic_get_all_manual_order_products( $order_id );
		$manual_products      = ic_get_manual_products( $manual_order_product );
		if ( ! is_array( $order_summary ) ) {
			return;
		}
		$order_summary['price']         = isset( $order_summary['price'] ) ? $order_summary['price'] : '';
		$order_products['product_name'] = isset( $order_products['product_name'] ) ? $order_products['product_name'] : '';
		$order_products['product_id']   = isset( $order_products['product_id'] ) ? $order_products['product_id'] : '';
		if ( ! empty( $customer_id ) ) {
			$from_url = '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . absint( $customer_id ), 'http' ) ) . '">' . esc_html( $order_summary['email'] ) . '</a>';
		} else {
			$from_url = $order_summary['email'];
		}
		switch ( $column_name ) {
			case 'id':
				/* translators: Placeholders are substituted with runtime values. */

						echo '<a href="' . esc_url( get_edit_post_link( $order_id ) ) . '" title="' . esc_attr( sprintf( __( 'Edit this %s', 'ecommerce-product-catalog' ), $labels->singular_name ) ) . '">' . esc_html( '#' . $order_id ) . '</a>';
				echo '<div class="row-actions"><span class="edit"><a href="' . esc_url( get_edit_post_link( $order_id ) ) . '" title="Edit this item">Edit</a> | </span><span class="trash"><a class="submitdelete" title="Move this item to the Trash" href="' . esc_url( get_delete_post_link( $order_id ) ) . '">Trash</a></span></div>';
				break;
			case 'status':
				$current_status = strtolower( $payment_details['status'] );
				$statuses       = ic_available_payment_status();
				if ( isset( $statuses[ $current_status ] ) ) {
					$status = $statuses[ $current_status ];
				} else {
					$status = reset( $statuses );
				}
				if ( $current_status === 'pending' && ! empty( $payment_details['payment_price'] ) ) {
					$status .= ' (' . __( 'Partial Payment', 'ecommerce-product-catalog' ) . ')';
				}
				// $statuses[ $current_status ]   = isset( $statuses[ $payment_details[ 'status' ] ] ) ? $statuses[ $payment_details[ 'status' ] ] : __( 'Complete', 'implecode-shopping-cart' );
				echo '<div class="box-' . sanitize_html_class( $current_status ) . '">' . esc_html( $status ) . '</div>';
				break;
			case 'from':
				echo wp_kses_post( $from_url );
				do_action( 'ic_orders_admin_from_column', $order_id );
				break;
			case 'amount':
				echo esc_html( $order_summary['price'] );
				break;
			case 'product':
				if ( is_array( $order_products['product_id'] ) ) {
					foreach ( $order_products['product_id'] as $i => $product_id ) {
						if ( empty( $product_id ) ) {
							echo esc_html( $order_products['product_name'][ $i ] );
						} else {
							/* translators: Placeholders are substituted with runtime values. */
								echo '<a title="' . esc_attr( sprintf( __( 'Edit %s', 'ecommerce-product-catalog' ), get_product_name( $product_id ) ) ) . '" href="' . esc_url( ic_product_edit_url( $product_id ) ) . '">' . esc_html( $order_products['product_name'][ $i ] ) . '</a><br>';
						}
					}
				} elseif ( empty( $order_products['product_id'] ) ) {
							echo esc_html( $order_products['product_name'] );
				} else {
					/* translators: Placeholders are substituted with runtime values. */
					echo '<a title="' . esc_attr( sprintf( __( 'Edit %s', 'ecommerce-product-catalog' ), get_product_name( $order_products['product_id'] ) ) ) . '" href="' . esc_url( ic_product_edit_url( $order_products['product_id'] ) ) . '">' . esc_html( $order_products['product_name'] ) . '</a>';
				}
				if ( ! empty( $manual_products['manual_product_ids'] ) ) {
					if ( ! is_array( $manual_products['manual_product_ids'] ) ) {
							$manual_products['manual_product_ids'] = explode( ',', $manual_products['manual_product_ids'] );
					}
					foreach ( $manual_products['manual_product_ids'] as $product_id ) {
						/* translators: Placeholders are substituted with runtime values. */
						echo '<a title="' . esc_attr( sprintf( __( 'Edit %s', 'ecommerce-product-catalog' ), get_product_name( $product_id ) ) ) . '" href="' . esc_url( ic_product_edit_url( $product_id ) ) . '">' . esc_html( get_the_title( $product_id ) ) . '</a><br>';
					}
				} elseif ( ! empty( $manual_products['custom_manual_products'] ) ) {
									$manual_products['custom_manual_products'] = explode( ',', $manual_products['custom_manual_products'] );
					foreach ( $manual_products['custom_manual_products'] as $product_name ) {
						echo esc_html( $product_name );
					}
				}
				break;
			case 'sku':
				if ( is_array( $order_products['product_id'] ) ) {
					foreach ( $order_products['product_id'] as $i => $product_id ) {
						if ( ! empty( $product_id ) ) {
							/* translators: Placeholders are substituted with runtime values. */
							echo '<a title="' . esc_attr( sprintf( __( 'Edit %s', 'ecommerce-product-catalog' ), get_product_name( $product_id ) ) ) . '" href="' . esc_url( ic_product_edit_url( $product_id ) ) . '">' . esc_html( get_product_sku( $product_id ) ) . '</a><br>';
						} else {
							echo '<br>';
						}
					}
				} elseif ( ! empty( $order_products['product_id'] ) ) {
						/* translators: Placeholders are substituted with runtime values. */
									echo '<a title="' . esc_attr( sprintf( __( 'Edit %s', 'ecommerce-product-catalog' ), get_product_name( $order_products['product_id'] ) ) ) . '" href="' . esc_url( ic_product_edit_url( $order_products['product_id'] ) ) . '">' . esc_html( get_product_sku( $order_products['product_id'] ) ) . '</a>';
				}
				if ( ! empty( $manual_products['manual_product_ids'] ) ) {
					if ( ! is_array( $manual_products['manual_product_ids'] ) ) {
							$manual_products['manual_product_ids'] = explode( ',', $manual_products['manual_product_ids'] );
					}
					foreach ( $manual_products['manual_product_ids'] as $product_id ) {
						/* translators: Placeholders are substituted with runtime values. */
						echo '<a title="' . esc_attr( sprintf( __( 'Edit %s', 'ecommerce-product-catalog' ), get_product_name( $product_id ) ) ) . '" href="' . esc_url( ic_product_edit_url( $product_id ) ) . '">' . esc_html( get_product_sku( $product_id ) ) . '</a><br>';
					}
				}
				break;
			case 'trans_date':
				echo esc_html( human_time_diff( get_the_time( 'U', $order_id ) ) . ' ' . __( 'ago', 'ecommerce-product-catalog' ) );
				break;
			default:
				do_action( 'ic_cart_orders_columns_content', $column_name, $order_id );
				break;
		} // end switch
	}
}

$ic_orders_columns = new ic_orders_columns();
