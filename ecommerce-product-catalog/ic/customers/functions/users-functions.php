<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages license functions
 *
 * Here all plugin functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-digital-customers/functions
 * @author        Norbert Dreszer
 */
function ic_get_user_urls( $customer_id, $meta_name ) {
	$customer_urls = get_user_meta( $customer_id, $meta_name, true );
	$customer_urls = explode( ',', $customer_urls );
	$urls          = '';
	foreach ( $customer_urls as $url ) {
		$urls .= '<a href="' . get_edit_post_link( $url ) . '">' . $url . '</a>';
	}

	return $urls;
}

function ic_get_customer_url( $customer_id, $anchor = null ) {
	if ( empty( $anchor ) && ! empty( $customer_id ) ) {
		$user_info = get_userdata( $customer_id );
		if ( ! empty( $user_info->user_login ) ) {
			$anchor = $user_info->user_login;
		} else {
			$anchor = '';
		}
	}
	if ( ! empty( $customer_id ) ) {
		$customer_url = '<a href="' . admin_url( 'user-edit.php?user_id=' . $customer_id, 'http' ) . '">' . $anchor . '</a>';
	} else {
		$customer_url = $anchor;
	}

	return $customer_url;
}

add_action( 'digital-order-summary', 'ic_show_customer_url', 10, 1 );

function ic_show_customer_url( $content_id ) {
	$customer_id = get_post_meta( $content_id, '_customer_id', true );
	if ( ! empty( $customer_id ) ) {
			echo '<label>' . esc_html__( 'Customer Login', 'ecommerce-product-catalog' ) . '</label>';
			echo '<div class="license-detail">' . esc_url( ic_get_customer_url( $customer_id ) ) . '</div>';
	}
}


if ( ! function_exists( 'ic_get_customer_id_from_email' ) ) {
	/**
	 * Resolves a customer user ID from an address.
	 *
	 * The address is looked up as an email first, which is what the function name
	 * promises and the only lookup that works when a customer's WordPress login
	 * differs from the email used on their order. The historical username_exists()
	 * lookup is kept as a fallback so no install that resolves today stops
	 * resolving; where login equals email both lookups return the same user.
	 *
	 * @param string $email Customer email address.
	 *
	 * @return int|false Customer user ID or false when no user matches.
	 */
	function ic_get_customer_id_from_email( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return $user->ID;
		}
		$customer_id = username_exists( $email );

		return ! empty( $customer_id ) ? $customer_id : false;
	}
}

function ic_get_customer_id_from_order_id( $order_id ) {
	$payment_details = ic_get_order_payment_details( $order_id );

	return ic_get_customer_id_from_email( $payment_details['shipping_email'] );
}

if ( ! function_exists( 'ic_get_logged_customer_id' ) ) {
	function ic_get_logged_customer_id() {
		$id = get_current_user_id();

		return $id;
	}
}

function ic_get_customer_fields() {
	return apply_filters(
		'ic_customer_fields',
		array(
			'name'  => __( 'Name', 'ecommerce-product-catalog' ),
			'email' => __( 'Email', 'ecommerce-product-catalog' ),
			'phone' => __( 'Phone', 'ecommerce-product-catalog' ),
		)
	);
}

add_shortcode( 'customer_info', 'ic_customer_data_shortcode' );

function ic_customer_data_shortcode( $atts ) {
	$available_args = array(
		'order_id' => '',
	);
	$args           = shortcode_atts( $available_args, $atts );
	$order_id       = intval( $args['order_id'] );
	$user_data      = ic_get_customer_data( true, $order_id );
	if ( empty( $user_data ) ) {
		return;
	}
	$p              = ic_email_paragraph( 'margin-bottom:0;' );
	$p_line         = ic_email_paragraph( 'margin:0;padding-left: 15px;' );
	$ep             = ic_email_paragraph_end();
	$user_data_html = $p . __( 'Customer', 'ecommerce-product-catalog' ) . ':' . $ep;
	foreach ( $user_data as $data_label => $data_value ) {
		$user_data_html .= $p_line . $data_label . ': ' . $data_value . $ep;
	}

	return $user_data_html;
}

function ic_get_customer_data( $labels = false, $order_id = null, $customer_id = '' ) {
	$fields    = ic_get_customer_fields();
	$user_data = array();
	if ( empty( $customer_id ) && ! empty( $order_id ) ) {
		$customer_id = get_post_meta( $order_id, '_customer_id', true );
	}
	foreach ( $fields as $name => $label ) {
		$value = '';
		if ( $name === 'name' ) {
			$value = ic_digital_customer_name( $customer_id, $order_id );
		} elseif ( $name === 'email' ) {
			$value = ic_get_digital_customer_email( $customer_id, $order_id );
		} elseif ( $name === 'phone' ) {
			$value = ic_digital_customer_phone( $customer_id, $order_id );
		}
		if ( ! empty( $value ) ) {
			if ( $labels ) {
				$user_data[ $label ] = $value;
			} else {
				$user_data[ $name ] = $value;
			}
		}
	}

	return $user_data;
}

function ic_get_digital_customer_email( $customer_id = null, $order_id = null ) {
	if ( ! is_user_logged_in() ) {
		$not_logged_email = sanitize_email( apply_filters( 'ic_not_logged_in_user_email', '' ) );
		if ( ! empty( $not_logged_email ) ) {
			return $not_logged_email;
		}
	}

	if ( empty( $customer_id ) ) {
		$customer_id = ic_get_logged_customer_id();
	}
	if ( empty( $customer_id ) && function_exists( 'ic_get_active_order_customer_id' ) ) {
		$customer_id = ic_get_active_order_customer_id();
	}
	if ( ! empty( $customer_id ) ) {
		$customer_data = get_userdata( $customer_id );

		return $customer_data->user_email;
	}

	return do_shortcode( '[form_field name="customer_email" order_id="' . $order_id . '"]' );
}

function ic_digital_customer_name( $customer_id = null, $order_id = null ) {
	if ( ! is_user_logged_in() ) {
		$not_logged_name = sanitize_text_field( apply_filters( 'ic_not_logged_in_user_name', '' ) );
		if ( ! empty( $not_logged_name ) ) {
			return $not_logged_name;
		}
	}
	$active_name = do_shortcode( '[form_field name="customer_name" order_id="' . $order_id . '"]' );

	if ( empty( $customer_id ) ) {
		$customer_id = ic_get_logged_customer_id();
	}
	if ( empty( $customer_id ) && function_exists( 'ic_get_active_order_customer_id' ) ) {
		$customer_id = ic_get_active_order_customer_id();
	}
	if ( ! empty( $customer_id ) ) {
		$customer_data = get_userdata( $customer_id );

		if ( ! empty( $active_name ) && $customer_data->display_name !== $active_name ) {
			$updated = wp_update_user(
				array(
					'ID'           => $customer_id,
					'display_name' => $active_name,
				)
			);
			if ( ! is_wp_error( $updated ) ) {
				return $active_name;
			}
		}

		return $customer_data->display_name;
	}

	return $active_name;
}

function ic_digital_customer_phone( $customer_id = null, $order_id = null ) {
	$active_phone = do_shortcode( '[form_field name="customer_phone" order_id="' . $order_id . '"]' );
	if ( is_user_logged_in() || ! empty( $customer_id ) ) {
		if ( empty( $customer_id ) ) {
			$customer_id = get_current_user_id();
		}
		$phone = get_user_meta( $customer_id, '_phone', true );
		if ( ! empty( $active_phone ) && $phone !== $active_phone ) {
			update_user_meta( $customer_id, '_phone', $active_phone );
			$phone = $active_phone;
		}
	} else {
		$phone = apply_filters( 'ic_not_logged_in_user_phone', false );
	}
	if ( ! empty( $phone ) ) {
		return $phone;
	}

	return $active_phone;
}

function ic_get_digital_customer_login( $customer_id = null ) {
	if ( empty( $customer_id ) ) {
		$customer_id = ic_get_logged_customer_id();
	}
	if ( ! empty( $customer_id ) ) {
		$customer_data = get_userdata( $customer_id );

		return $customer_data->user_login;
	}

	return '';
}

if ( ! function_exists( 'get_ic_roles' ) ) {

	function get_ic_roles( $user_id ) {
		$ic_roles = get_user_meta( $user_id, '_ic_roles', true );
		if ( empty( $ic_roles ) ) {
			$ic_roles = array();
		}

		return $ic_roles;
	}

}

if ( ! function_exists( 'add_ic_role' ) ) {

	function add_ic_role( $user_id, $role_name ) {
		$ic_roles = get_ic_roles( $user_id );
		if ( ! empty( $role_name ) ) {
			$ic_roles[] = $role_name;
			update_user_meta( $user_id, '_ic_roles', $ic_roles );
		}

		return $ic_roles;
	}

}

if ( ! function_exists( 'ic_get_login_redirect_url' ) ) {
	function ic_get_login_redirect_url( $customer_id = null, $current_redirect = '' ) {
		if ( function_exists( 'ic_customer_panel_panel_url' ) ) {
			$url = ic_customer_panel_panel_url();
			if ( empty( $customer_id ) ) {
				$customer_id = ic_get_logged_customer_id();
			}
			if ( ! empty( $customer_id ) ) {
				$orders = ic_get_customer_orders( $customer_id );

				if ( empty( $orders ) ) {
					$url = product_listing_url();
				}
			}
		} else {
			$url = product_listing_url();
		}
		$is_shopping_checkout = function_exists( 'is_ic_shopping_cart' ) &&
			function_exists( 'is_ic_shopping_order' ) &&
			( is_ic_shopping_cart() || is_ic_shopping_order() );
		$is_quote_checkout    = function_exists( 'is_ic_quote_cart' ) &&
			function_exists( 'is_ic_quote_order' ) &&
			( is_ic_quote_cart() || is_ic_quote_order() );
		if ( ( $is_shopping_checkout || $is_quote_checkout ) && function_exists( 'ic_current_page_url' ) ) {
			$url = ic_current_page_url();
		}

		if ( ! empty( $current_redirect ) ) {
			$checkout_pages = array();
			if ( function_exists( 'ic_shopping_submit_page_url' ) ) {
				$checkout_pages[] = ic_shopping_submit_page_url();
			}
			if ( function_exists( 'ic_shopping_cart_page_url' ) ) {
				$checkout_pages[] = ic_shopping_cart_page_url();
			}
			if ( function_exists( 'quote_submit_page_url' ) ) {
				$checkout_pages[] = quote_submit_page_url();
			}
			if ( function_exists( 'quote_cart_page_url' ) ) {
				$checkout_pages[] = quote_cart_page_url();
			}
			$current_redirect_path = wp_parse_url( $current_redirect, PHP_URL_PATH );
			foreach ( $checkout_pages as $checkout_page ) {
				$checkout_page_path = wp_parse_url( $checkout_page, PHP_URL_PATH );
				if (
					! empty( $checkout_page ) &&
					(
						false !== strpos( $current_redirect, $checkout_page ) ||
						( ! empty( $current_redirect_path ) && ! empty( $checkout_page_path ) && 0 === strpos( $current_redirect_path, $checkout_page_path ) )
					)
				) {
					$url = $current_redirect;
					break;
				}
			}
		}

		// Never resolve to an empty redirect (e.g. customer-panel page unconfigured).
		if ( empty( $url ) ) {
			$url = function_exists( 'product_listing_url' ) ? product_listing_url() : '';
		}
		if ( empty( $url ) ) {
			$url = home_url();
		}

		return $url;
	}
}
