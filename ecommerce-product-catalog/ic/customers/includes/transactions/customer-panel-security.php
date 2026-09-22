<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Blocks digital customers to enter the default WordPress Admin
 *
 * Created by Norbert Dreszer.
 * Date: 11-Mar-15
 * Time: 13:22
 * Package: customer-panel-security.php
 */
class ic_customer_panel_security {

	function __construct() {
		add_action( 'ic_epc_loaded', array( __CLASS__, 'remove_admin_bar' ) );
		add_action( 'admin_init', array( __CLASS__, 'stop_access_profile' ) );
		add_action( 'admin_menu', array( __CLASS__, 'remove_demo_menus' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_admin' ) );
		add_filter( 'login_url', array( __CLASS__, 'login_url' ), 10, 3 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_url' ), 10, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'login_fail' ) );
		add_action( 'authenticate', array( __CLASS__, 'check_password' ), 1, 3 );
		add_filter( 'customer_login_actions', array( __CLASS__, 'login_errors' ) );
		add_action( 'init', array( __CLASS__, 'set_session_ref' ), 1 );
	}

	/**
	 * Hide Admin bar for digital customers
	 */
	static function remove_admin_bar() {
		if ( self::restricted_logged_customer() ) {
			show_admin_bar( false );
		}
	}

	static function stop_access_profile() {
		if ( defined( 'IS_PROFILE_PAGE' ) && IS_PROFILE_PAGE && self::restricted_logged_customer() ) {
			wp_die( 'You are not permitted to change profile details.' );
		}
	}

	static function remove_demo_menus() {
		if ( self::restricted_logged_customer() ) {
			remove_menu_page( 'profile.php' );
			remove_menu_page( 'upload.php' );
			remove_submenu_page( 'users.php', 'profile.php' );
		}
	}

	static function redirect_admin() {
		if ( 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ) && is_user_logged_in() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Request routing only; the allowed handler verifies its nonce.
			$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( apply_filters( 'ic_customers_allow_authenticated_admin_post', false, $action, get_current_user_id() ) ) {
				return;
			}
		}
		if ( self::restricted_logged_customer() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
			$url = ic_get_login_redirect_url();
			if ( ! empty( $url ) ) {
				wp_safe_redirect( $url );
				exit;
				exit;
			}
		}
	}

	/**
	 * Modifies the login redirect URL based on the user's roles and other criteria.
	 *
	 * @param string       $url The original login URL.
	 * @param string       $request The request type associated with the login action.
	 * @param WP_User|null $user The user object. If not an object, the original URL is returned.
	 *
	 * @return string Returns the modified URL based on the user's roles or customer-related conditions.
	 *                If no modifications are required, returns the original URL.
	 */
	static function login_url( $url, $request, $user ) {
		if ( self::is_alternate_authentication_request() ) {
			return $url;
		}

		if ( ! is_object( $user ) ) {
			return $url;
		}
		$customer_id = null;
		if ( ! empty( $user->ID ) ) {
			$customer_id = intval( $user->ID );
		}
		if ( empty( $customer_id ) ) {
			return $url;
		}
		if ( isset( $user->roles ) && is_array( $user->roles ) ) {
			// Redirect admins to the dashboard
			if ( in_array( 'administrator', $user->roles, true ) ) {
				return admin_url();
			}

			// Redirect any user who can edit posts or pages to dashboard
			if ( user_can( $user->ID, 'edit_posts' ) || user_can( $user->ID, 'edit_pages' ) ) {
				return admin_url(); // Redirect to the WordPress dashboard
			}
		}
		$panel_url = ic_get_login_redirect_url( $customer_id, $url );
		if ( ! empty( $panel_url ) && self::restricted_customer_id( $customer_id ) ) {
			if ( is_string( $panel_url ) ) {
				return esc_url( $panel_url );
			}
		}

		return $url;
	}

	static function login_fail( $username ) {
		if ( ! self::is_classic_login_request() ) {
			return;
		}

		$session = get_product_catalog_session();
		if ( empty( $session['referrer'] ) ) {
			return;
		}
		$referrer = $session['referrer'];
		if ( ! strstr( $referrer, 'wp-login' ) && ! strstr( $referrer, 'wp-admin' ) && ! current_user_can( 'administrator' ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This value is used only for request-shape and login-failure routing; credential mutations use WordPress login/reset verification.
				$redirect_to = isset( $_POST['redirect_to'] ) && is_scalar( $_POST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : '';
			if ( ! empty( $redirect_to ) && strstr( $redirect_to, 'wp-admin' ) ) {
				return;
			}
			$url = ic_customer_panel_panel_url();
			if ( ! empty( $url ) ) {
				$url = add_query_arg( 'login', 'failed', $url );
				wp_safe_redirect( $url );
				exit;
				exit;
			}
		}
	}

	/**
	 * Redirects to customer panel when username or password is empty
	 *
	 * @param type   $login
	 * @param string $username
	 * @param string $password
	 */
	static function check_password( $login, $username, $password ) {
		if ( ! self::is_classic_login_request() ) {
			return $login;
		}

		$session = get_product_catalog_session();
		if ( empty( $session['referrer'] ) ) {
			return $login;
		}
		$referrer = $session['referrer'];
		if ( ! empty( $referrer ) && ! strstr( $referrer, 'wp-login' ) && ! strstr( $referrer, 'wp-admin' ) ) {
			if ( '' === $username || '' === $password ) {
				$url = ic_customer_panel_panel_url();
				if ( ! empty( $url ) ) {
					$url = add_query_arg( 'login', 'empty', $url );
					wp_safe_redirect( $url );
					exit;
					exit;
				}
			}
		}

		return $login;
	}

	/**
	 * Determines whether the current request is a classic WordPress credential login.
	 *
	 * Other authentication providers can post through the same WordPress login hooks
	 * without username/password fields, so Quote Cart must not redirect those flows.
	 *
	 * @return bool
	 */
	public static function is_classic_login_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This only detects request shape for login compatibility.
		return isset( $_POST['log'], $_POST['pwd'] );
	}

	/**
	 * Determines whether the current request belongs to an alternate auth flow.
	 *
	 * @return bool
	 */
	public static function is_alternate_authentication_request() {
		if ( function_exists( 'ic_quote_cart_is_alternate_authentication_request' ) ) {
			return ic_quote_cart_is_alternate_authentication_request();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Request shape detection only.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( in_array( $action, array( 'validate_2fa', 'grace_2fa', 'backup_2fa' ), true ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request shape detection only.
		if ( isset( $_POST['wp-auth-id'] ) || isset( $_POST['wp-auth-nonce'] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request shape detection only.
		return isset( $_POST['authcode'], $_POST['provider'] );
	}

	/**
	 * Determines whether Quote Cart should stay passive for the current auth request.
	 *
	 * @return bool
	 */
	public static function is_wordpress_auth_request() {
		if ( function_exists( 'ic_quote_cart_is_wordpress_auth_request' ) ) {
			return ic_quote_cart_is_wordpress_auth_request();
		}

		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			return true;
		}

		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
		if ( 'wp-login.php' === basename( $script_name ) ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== strpos( $request_uri, '/wp-login.php' ) ) {
			return true;
		}

		return self::is_alternate_authentication_request();
	}

	/**
	 * Adds customer login error messages
	 *
	 * @param string $actions
	 *
	 * @return string
	 */
	static function login_errors( $actions ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value is used only for request-shape and login-failure routing; credential mutations use WordPress login/reset verification.
		if ( isset( $_GET['login'] ) && $_GET['login'] == 'failed' ) {
			$redirect = ic_customer_panel_panel_url();
			/* translators: Placeholders are substituted with runtime values. */
			$actions .= implecode_warning( sprintf( __( 'The login failed. If you forgot your password please try to %1$sreset it here%2$s.', 'ecommerce-product-catalog' ), '<a href="' . wp_lostpassword_url( $redirect ) . '">', '</a>' ), 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value is used only for request-shape and login-failure routing; credential mutations use WordPress login/reset verification.
		} elseif ( isset( $_GET['login'] ) && $_GET['login'] == 'empty' ) {
			$actions .= implecode_warning( __( 'Please enter both username and password.', 'ecommerce-product-catalog' ), 0 );
		}

		return $actions;
	}

	/**
	 * Determines whether the provided user ID should be treated as a restricted customer.
	 *
	 * @param int|null $customer_id Optional customer ID.
	 *
	 * @return bool
	 */
	static function restricted_customer_id( $customer_id = null ) {
		$customer_id = absint( $customer_id );
		if ( empty( $customer_id ) ) {
			return false;
		}

		$user = get_userdata( $customer_id );
		if ( empty( $user ) || empty( $user->roles ) || ! is_array( $user->roles ) ) {
			return false;
		}

		if (
			user_can( $user, 'manage_options' ) ||
			user_can( $user, 'edit_posts' ) ||
			user_can( $user, 'edit_pages' )
		) {
			return false;
		}

		return is_ic_digital_customer( $customer_id );
	}

	/**
	 * Determines whether the current request belongs to a restricted logged-in customer.
	 *
	 * @param int|null $customer_id Optional customer ID.
	 *
	 * @return bool
	 */
	static function restricted_logged_customer( $customer_id = null ) {
		if ( empty( $customer_id ) ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}
			$customer_id = get_current_user_id();
		}

		return self::restricted_customer_id( $customer_id );
	}

	/**
	 * Define session referrer
	 */
	static function set_session_ref() {
		if ( is_admin() && ( function_exists( 'is_ic_ajax' ) && ! is_ic_ajax() ) ) {
			return;
		}
		if ( self::is_wordpress_auth_request() ) {
			return;
		}

		$ic_session = get_product_catalog_session();
		if ( isset( $ic_session['next_referrer'] ) ) {
			// Get existing referrer
			$ic_session['referrer'] = $ic_session['next_referrer'];
		} elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			// Use given referrer
			$ic_session['referrer'] = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		} else {
			$ic_session['referrer'] = '';
		}

		// Save current page as next page's referrer
		$ic_session['next_referrer'] = ic_current_page_url();
		set_product_catalog_session( $ic_session );
	}
}

$ic_customer_panel_security = new ic_customer_panel_security();
