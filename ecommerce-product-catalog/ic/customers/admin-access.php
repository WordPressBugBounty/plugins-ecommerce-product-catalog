<?php
/**
 * Shared customer admin-access helpers.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_customers_admin_access_redirect_url' ) ) {
	/**
	 * Resolves the redirect URL for restricted customer admin access.
	 *
	 * @param string $redirect_url Optional preferred redirect URL.
	 *
	 * @return string
	 */
	function ic_customers_admin_access_redirect_url( $redirect_url = '' ) {
		if ( ! empty( $redirect_url ) ) {
			return $redirect_url;
		}

		return home_url();
	}
}

if ( ! function_exists( 'ic_customers_is_non_ajax_admin_request' ) ) {
	/**
	 * Determines whether the current request is a non-AJAX admin request.
	 *
	 * @return bool
	 */
	function ic_customers_is_non_ajax_admin_request() {
		if ( ! is_admin() ) {
			return false;
		}
		if ( function_exists( 'is_ic_ajax' ) ) {
			return ! is_ic_ajax();
		}

		return ! ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}
}

if ( ! function_exists( 'ic_customers_restrict_admin_access' ) ) {
	/**
	 * Hides the admin bar and redirects restricted customers away from wp-admin.
	 *
	 * @param bool   $restricted   Whether the current user should be restricted.
	 * @param string $redirect_url Optional preferred redirect URL.
	 *
	 * @return void
	 */
	function ic_customers_restrict_admin_access( $restricted, $redirect_url = '' ) {
		if ( ! $restricted ) {
			return;
		}

		show_admin_bar( false );
		if ( 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ) && is_user_logged_in() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Request routing only; the allowed handler verifies its nonce.
			$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( apply_filters( 'ic_customers_allow_authenticated_admin_post', false, $action, get_current_user_id() ) ) {
				return;
			}
		}
		if ( ic_customers_is_non_ajax_admin_request() ) {
			wp_safe_redirect( ic_customers_admin_access_redirect_url( $redirect_url ) );
			exit;
		}
	}
}
