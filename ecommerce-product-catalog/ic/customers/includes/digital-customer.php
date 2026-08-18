<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages digital customer
 *
 * Here digital customer is defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-digital-customers/includes
 * @author        Norbert Dreszer
 */

add_shortcode( 'login_form', 'ic_digital_customer_login_form' );

if ( ! function_exists( 'ic_customer_login_is_custom_logged' ) ) {
	/**
	 * Returns whether the current visitor is authenticated through a custom customer flow.
	 *
	 * @return bool
	 */
	function ic_customer_login_is_custom_logged() {
		return (bool) apply_filters( 'ic_is_user_custom_logged', false );
	}
}

/**
 * Returns the shared login helper instance for the customer module when available.
 *
 * @param array $settings Optional shopping cart settings.
 *
 * @return IC_User_Login|null
 */
function ic_get_customer_login_instance( $settings = array() ) {
	if ( ! function_exists( 'ic_user_login_instance' ) ) {
		$user_functions = dirname( dirname( __DIR__ ) ) . '/user-functions.php';
		if ( file_exists( $user_functions ) ) {
			require_once $user_functions;
		}
	}
	if ( ! function_exists( 'ic_user_login_instance' ) ) {
		return null;
	}
	if ( empty( $settings ) && function_exists( 'get_shopping_cart_settings' ) ) {
		$settings = get_shopping_cart_settings();
	}
	if ( function_exists( 'ic_customers_qc_sc_settings' ) ) {
		$settings = ic_customers_qc_sc_settings( $settings );
	}
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$registration = apply_filters( 'ic_customer_login_registration_enabled', false, $settings );
	$guest_login  = apply_filters( 'ic_customer_login_guest_enabled', false, $settings );
	$redirect     = function_exists( 'ic_get_login_redirect_url' ) ? ic_get_login_redirect_url() : '';

	return ic_user_login_instance( $registration, true, $guest_login, $redirect );
}

/**
 * Defines customer login form shortcode
 *
 * @param boolean $show
 * @param string  $form_class
 * @param boolean $closer
 * @param string  $title
 * @param string  $desc
 *
 * @return string
 */
function ic_digital_customer_login_form(
	$show = true,
	$form_class = 'login_form',
	$closer = false,
	$title = null,
	$desc = '',
	$settings = array(),
	$pre_args = array()
) {
	$user_login = ic_get_customer_login_instance( $settings );
	if ( is_object( $user_login ) ) {
		return $user_login->form( $show, $form_class, $closer, $title, $desc, $settings, $pre_args );
	}
	if ( is_user_logged_in() || ic_customer_login_is_custom_logged() ) {

		return ic_digital_customer_login_url( 0, '', false, false, true, false );
	}
	if ( empty( $settings ) ) {
		$settings = get_shopping_cart_settings();
	}
	$title = $title !== null ? $title : __( 'Customer Login', 'ecommerce-product-catalog' );
	$args  = apply_filters(
		'ic_customer_login_form_args',
		array_merge(
			array(
				'echo'           => false,
				'redirect'       => apply_filters( 'ic_login_form_redirect', ic_get_login_redirect_url() ),
				'form_id'        => 'loginform',
				'label_username' => __( 'Email Address', 'ecommerce-product-catalog' ),
				'label_password' => __( 'Password', 'ecommerce-product-catalog' ),
				'label_remember' => __( 'Remember Me', 'ecommerce-product-catalog' ),
				'label_log_in'   => __( 'Sign in', 'ecommerce-product-catalog' ),
				'id_username'    => 'user_login',
				'id_password'    => 'user_pass',
				'id_remember'    => 'rememberme',
				'id_submit'      => 'wp-submit',
				'remember'       => false,
				'value_username' => '',
				'value_remember' => false,
			),
			$pre_args
		),
		$settings
	);
	$style = '';
	if ( ! $show ) {
		$style = 'style="display: none"';
	}
	$class = 'not-logged';
	if ( is_user_logged_in() ) {
		$class = 'logged';
	}
	$form = '<div class="' . $form_class . ' ' . apply_filters( 'ic_customer_login_form_class', $class, $settings ) . '" ' . $style . '>';

	$tabs         = apply_filters( 'login_form_tabs', '<li><a href="#login_form">' . __( 'Login', 'ecommerce-product-catalog' ) . '</a></li>' );
	$tabs_num     = substr_count( $tabs, '<li>' );
	$inside_class = 'inside_login';
	if ( $tabs_num > 1 ) {
		$inside_class .= 'ui-tabs';
	}
	$form .= '<div class="' . $inside_class . '">';
	if ( $closer ) {
		$form .= '<span class="closer"></span>';
	}
	if ( $tabs_num > 1 ) {
		$form .= '<ul class="ui-tabs-nav">' . $tabs . '</ul>';
	}
	$form .= apply_filters( 'login_form_start', '<div id="login_form">', $settings );
	if ( ! empty( $title ) ) {
		$form .= '<h2>' . $title . '</h2>';
	}
	$form .= $desc;
	$form .= ic_get_customer_login_actions();
	$form .= str_replace( 'button button-primary', 'button ' . design_schemes( 'box', 0 ), wp_login_form( $args ) ) . '</div>';
	$form .= '</div></div>';

	return apply_filters( 'ic_customer_login_form', $form, $settings, $args, $show );
}

/**
 * Shows customer login url
 *
 * @param boolean $echo
 * @param string  $label
 *
 * @return string
 */
function ic_digital_customer_login_url( $echo = 1, $desc = '', $lower = false, $panel_redirect = false, $panel = true, $show_form = true ) {
	$user_login = ic_get_customer_login_instance();
	if ( is_object( $user_login ) ) {
		return $user_login->url( $echo, $desc, $lower, $panel_redirect, $panel, $show_form );
	}
	$class      = 'not-logged';
	$button     = '';
	$user_email = '';
	$url        = ic_loginout_url();
	if ( is_user_logged_in() || ic_customer_login_is_custom_logged() ) {
		$button_class = ' ic-logout-button';
		if ( $panel ) {
			$button_class .= ' ic-secondary-button';
		}
		$loginout = '<a class="button ' . design_schemes( 'box', 0 ) . $button_class . '" href="' . esc_url( $url ) . '">' . __( 'Sign out', 'ecommerce-product-catalog' ) . '</a>';

		$current_user = wp_get_current_user();
		if ( ! empty( $current_user->data->user_login ) ) {
			$user_email = $current_user->data->user_login;
		}
		$desc  = '';
		$class = 'logged';
		if ( $panel ) {
			$panel_url = '';
			if ( function_exists( 'ic_customer_panel_panel_url' ) ) {
				$panel_url = ic_customer_panel_panel_url();
			}
			if ( ! empty( $panel_url ) ) {
				$button = '<a class="button ic-my-account" href="' . $panel_url . '">' . __( 'My Account', 'ecommerce-product-catalog' ) . '</a>';
			}
		}
	} else {
		$loginout = '<a class="button ' . design_schemes( 'box', 0 ) . ' ic-login-button" href="' . esc_url( $url ) . '">' . __( 'Sign in', 'ecommerce-product-catalog' ) . '</a>';
	}

	$link   = $lower ? strtolower( $loginout ) : $loginout;
	$return = '<span class="login_button ' . $class . '"><span class="ic-logged-username">' . __( 'Login', 'ecommerce-product-catalog' ) . ': ' . $user_email . '</span>' . $button . $link . ' ' . $desc . '</span>';
	if ( $show_form ) {
		$return .= ic_digital_customer_login_form( false, 'login_form popup_login_form', true );
	}

	return echo_ic_setting( $return, $echo );
}

if ( ! function_exists( 'ic_loginout_url' ) ) {
	function ic_loginout_url( $force_logout = false ) {
		$panel_url      = '';
		$panel_redirect = false;
		if ( function_exists( 'ic_customer_panel_panel_url' ) ) {
			$panel_url      = ic_customer_panel_panel_url();
			$panel_redirect = true;
		}
		if ( is_ic_shopping_cart() || is_ic_shopping_order() ) {
			$panel_redirect = false;
		}
		$redirect = ! empty( $panel_url ) && $panel_redirect ? $panel_url : ic_current_page_url();
		if ( $force_logout || is_user_logged_in() ) {
			return wp_logout_url( $redirect );
		}

		return wp_login_url( $redirect );
	}
}

if ( ! function_exists( 'ic_current_page_url' ) ) {

	/**
	 * Get current page URL from server global
	 *
	 * @return string
	 */
	function ic_current_page_url() {
		if ( is_ic_ajax() ) {
			if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
					return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			} else {
				return product_listing_url();
			}
		} elseif ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$page_url = 'http';
			if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) {
				$page_url .= 's';
			}

			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			return $page_url . '://' . $host . $path;
		} else {
			return '';
		}
	}

}

add_filter( 'loginout', 'ic_loginout_digital_customers_class' );

/**
 * Adds a custom class to loginout
 *
 * @param string $link
 *
 * @return string
 */
function ic_loginout_digital_customers_class( $link ) {
	$class = 'class="button"';
	$link  = str_replace( '<a ', '<a ' . $class . ' ', $link );

	return $link;
}

add_shortcode( 'login_url', 'ic_digital_customer_login_url_shortcode' );

/**
 * Defines login URL shortcode
 *
 * @return string
 */
function ic_digital_customer_login_url_shortcode() {
	return ic_digital_customer_login_url( 0, '' );
}

/**
 * Manages login messages
 *
 * @return string
 */
function ic_get_customer_login_actions() {
	$user_login = ic_get_customer_login_instance();
	if ( is_object( $user_login ) && method_exists( $user_login, 'login_actions' ) ) {
		return $user_login->login_actions();
	}

	return apply_filters( 'customer_login_actions', '' );
}

global $ic_customer_login;
$ic_customer_login = ic_get_customer_login_instance();
