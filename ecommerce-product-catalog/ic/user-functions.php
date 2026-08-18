<?php
/**
 * User helper functions.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 *
 *  @version       1.0.0
 *  @author        impleCode
 *
 */

if ( ! function_exists( 'ic_temp_user_role' ) ) {
	/**
	 * Returns the temporary framework user role slug.
	 *
	 * @return string
	 */
	function ic_temp_user_role() {
		return 'ic_temp_user';
	}
}

if ( ! function_exists( 'ic_get_login_redirect_url' ) ) {
	/**
	 * Returns the post-login redirect URL.
	 *
	 * @param int|null $customer_id      Optional customer ID.
	 * @param string   $current_redirect Optional requested redirect URL.
	 *
	 * @return string
	 */
	function ic_get_login_redirect_url( $customer_id = null, $current_redirect = '' ) {
		if ( function_exists( 'ic_customer_panel_panel_url' ) ) {
			$url = ic_customer_panel_panel_url();
			if ( empty( $customer_id ) ) {
				$customer_id = ic_get_logged_customer_id();
			}
			if ( ! empty( $customer_id ) && function_exists( 'ic_get_customer_orders' ) ) {
				$orders = ic_get_customer_orders( $customer_id );
				if ( empty( $orders ) && function_exists( 'product_listing_url' ) ) {
					$url = product_listing_url();
				}
			}
		} elseif ( function_exists( 'product_listing_url' ) ) {
			$url = product_listing_url();
		} else {
			$url = home_url();
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

if ( ! function_exists( 'ic_loginout_url' ) ) {
	/**
	 * Returns the current login or logout URL.
	 *
	 * @param bool $force_logout Whether to always build the logout URL.
	 *
	 * @return string
	 */
	function ic_loginout_url( $force_logout = false ) {
		$panel_url      = '';
		$panel_redirect = false;
		if ( function_exists( 'ic_customer_panel_panel_url' ) ) {
			$panel_url      = ic_customer_panel_panel_url();
			$panel_redirect = true;
		}
		if ( function_exists( 'is_ic_shopping_cart' ) && function_exists( 'is_ic_shopping_order' ) && ( is_ic_shopping_cart() || is_ic_shopping_order() ) ) {
			$panel_redirect = false;
		}
		if ( function_exists( 'ic_current_page_url' ) ) {
			$redirect = ! empty( $panel_url ) && $panel_redirect ? $panel_url : ic_current_page_url();
		} else {
			$redirect = ! empty( $panel_url ) && $panel_redirect ? $panel_url : home_url( '/' );
		}
		if ( $force_logout || is_user_logged_in() ) {
			return wp_logout_url( $redirect );
		}

		return wp_login_url( $redirect );
	}
}

if ( ! function_exists( 'is_ic_digital_customer' ) ) {
	/**
	 * Checks if the current user is a digital customer.
	 *
	 * @param int|null $customer_id Customer ID to check, optional.
	 *
	 * @return bool
	 */
	function is_ic_digital_customer( $customer_id = null ) {

		if ( ! is_numeric( $customer_id ) ) {
			$customer_id = 0;
		}
		if ( empty( $customer_id ) && function_exists( 'ic_get_active_order_customer_id' ) ) {
			$customer_id = ic_get_active_order_customer_id();
		}
		if ( empty( $customer_id ) ) {
			$customer_id = ic_get_logged_customer_id();
		}

		if ( ! empty( $customer_id ) ) {
			$ic_roles = get_ic_roles( $customer_id );
			if ( in_array( 'customer', $ic_roles, true ) ) {
				return true;
			}
			$customer_data  = get_userdata( $customer_id );
			$customer_roles = is_object( $customer_data ) ? $customer_data->roles : array();
			if ( in_array( 'administrator', $customer_roles, true ) || in_array( 'customer', $customer_roles, true ) || in_array( 'digital_customer', $customer_roles, true ) || in_array( 'cart_customer', $customer_roles, true ) ) {
				return true;
			}
		}

		return apply_filters( 'ic_ic_digital_customer', false );
	}
}

if ( ! function_exists( 'ic_get_logged_customer_id' ) ) {
	/**
	 * Returns the logged-in customer ID.
	 *
	 * @return int
	 */
	function ic_get_logged_customer_id() {
		$id = get_current_user_id();

		return $id;
	}
}

if ( ! function_exists( 'ic_user_exists' ) ) {
	/**
	 * Checks whether a user exists for the provided email or username.
	 *
	 * @param string $email  Email address or username.
	 * @param bool   $filter Whether to apply the result filter.
	 *
	 * @return int|false
	 */
	function ic_user_exists( $email, $filter = true ) {
		$customer_id = username_exists( $email );
		if ( empty( $customer_id ) ) {
			$customer_id = email_exists( $email );
		}
		if ( $customer_id ) {
			$return = $customer_id;
		} else {
			$return = false;
		}
		if ( $filter ) {
			return apply_filters( 'ic_user_exists', $return, $email, $customer_id );
		} else {
			return $return;
		}
	}
}

if ( ! function_exists( 'ic_user_inactive' ) ) {
	/**
	 * Checks whether the provided user is inactive.
	 *
	 * @param string $email User email address.
	 *
	 * @return bool
	 */
	function ic_user_inactive( $email ) {
		return apply_filters( 'ic_user_inactive', false, $email );
	}
}

if ( ! function_exists( 'ic_user_notification_email_exists' ) ) {
	/**
	 * Checks whether the notification email address exists.
	 *
	 * @param string $email User email address.
	 *
	 * @return bool
	 */
	function ic_user_notification_email_exists( $email ) {
		return apply_filters( 'ic_user_notification_email_exists', false, $email );
	}
}

if ( ! function_exists( 'get_ic_roles' ) ) {
	/**
	 * Returns all impleCode roles assigned to the user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	function get_ic_roles( $user_id ) {
		$ic_roles = get_user_meta( $user_id, '_ic_roles', true );
		if ( empty( $ic_roles ) ) {
			$ic_roles = array();
		}
		$new_ic_roles = get_user_meta( $user_id, '_ic_role', false );
		if ( empty( $ic_roles ) ) {
			return array_unique( array_filter( $new_ic_roles ) );
		}

		return array_unique( array_merge( $ic_roles, array_filter( $new_ic_roles ) ) );
	}

}

if ( ! function_exists( 'add_ic_role' ) ) {
	/**
	 * Adds an impleCode role to the user.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $role_name Role slug.
	 *
	 * @return array
	 */
	function add_ic_role( $user_id, $role_name ) {
		$ic_roles = get_ic_roles( $user_id );
		if ( ! empty( $role_name ) && ! in_array( $role_name, $ic_roles, true ) ) {
			add_user_meta( $user_id, '_ic_role', $role_name );
		}

		return $ic_roles;
	}

}

if ( ! function_exists( 'ic_current_user_id' ) ) {
	/**
	 * Returns the current user ID.
	 *
	 * @return int
	 */
	function ic_current_user_id() {
		return get_current_user_id();
	}
}

if ( ! function_exists( 'ic_user_login_instance' ) ) {
	/**
	 * Returns a cached shared login helper instance for the provided configuration.
	 *
	 * @param bool       $registration   Whether registration support should be enabled.
	 * @param bool       $collect_phone  Whether registration requires phone collection.
	 * @param bool       $guest_login    Whether guest-login support should be enabled.
	 * @param string|int $login_redirect Redirect URL or page ID.
	 *
	 * @return IC_User_Login|null
	 */
	function ic_user_login_instance( $registration = false, $collect_phone = true, $guest_login = false, $login_redirect = '' ) {
		static $instances = array();

		if ( ! class_exists( 'IC_User_Login' ) ) {
			if ( file_exists( __DIR__ . '/class-ic-user-login.php' ) ) {
				if ( function_exists( 'ic_framework_require_once' ) ) {
					ic_framework_require_once( __DIR__ . '/class-ic-user-login.php' );
				} else {
					require_once __DIR__ . '/class-ic-user-login.php';
				}
			}
		}
		if ( ! class_exists( 'IC_User_Login' ) ) {
			return null;
		}

		$key = wp_json_encode(
			array(
				(bool) $registration,
				(bool) $collect_phone,
				(bool) $guest_login,
				(string) $login_redirect,
			)
		);
		if ( ! isset( $instances[ $key ] ) ) {
			$instances[ $key ] = new IC_User_Login( $registration, $collect_phone, $guest_login, $login_redirect );
		}

		return $instances[ $key ];
	}
}

if ( ! function_exists( 'ic_is_checkout_login_prompt_visible' ) ) {
	/**
	 * Determines whether checkout should show a login prompt for the current visitor.
	 *
	 * @return bool
	 */
	function ic_is_checkout_login_prompt_visible() {
		if ( is_user_logged_in() ) {
			return false;
		}

		return ! (bool) apply_filters( 'ic_is_user_custom_logged', false );
	}
}

if ( ! function_exists( 'ic_checkout_login_prompt' ) ) {
	/**
	 * Returns the shared inline checkout login prompt.
	 *
	 * @param string $message      Translated prompt string containing `%s` for the login button markup.
	 * @param bool   $panel        Whether the generated login URL should include account-panel actions once logged in.
	 * @param array  $login_config Optional `IC_User_Login` constructor arguments.
	 *
	 * @return string
	 */
	function ic_checkout_login_prompt( $message = 'Returning customer? %s', $panel = false, $login_config = array() ) {
		$registration   = ! empty( $login_config['registration'] );
		$collect_phone  = ! isset( $login_config['collect_phone'] ) || (bool) $login_config['collect_phone'];
		$guest_login    = ! empty( $login_config['guest_login'] );
		$login_redirect = isset( $login_config['login_redirect'] ) ? $login_config['login_redirect'] : '';
		$user_login     = ic_user_login_instance( $registration, $collect_phone, $guest_login, $login_redirect );

		if ( ! is_object( $user_login ) || ! method_exists( $user_login, 'checkout_prompt' ) ) {
			if ( ! ic_is_checkout_login_prompt_visible() || ! function_exists( 'ic_digital_customer_login_url' ) ) {
				return '';
			}

			return '<div class="ic-checkout-login-box-container">' . implecode_info( sprintf( $message, ic_digital_customer_login_url( 0, '', false, false, $panel ) ), 0, 0 ) . '</div>';
		}

		return $user_login->checkout_prompt( $message, $panel );
	}
}
