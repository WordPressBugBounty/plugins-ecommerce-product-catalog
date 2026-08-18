<?php
/**
 * Shared customer account-info helpers.
 *
 * @package ic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_customers_account_info_text' ) ) {
	/**
	 * Resolves shared account-info copy through a context-aware WordPress filter.
	 *
	 * @param string $key     String identifier.
	 * @param string $default Default English copy.
	 * @param string $context Consumer context.
	 *
	 * @return string
	 */
	function ic_customers_account_info_text( $key, $default, $context ) {
		return apply_filters( 'ic_customers_account_info_text', $default, $key, $context );
	}
}

if ( ! function_exists( 'ic_customers_new_account_info_message' ) ) {
	/**
	 * Builds the shared new-account info message.
	 *
	 * @param array $userdata New account user data.
	 * @param array $args     Optional rendering arguments.
	 *
	 * @return string
	 */
	function ic_customers_new_account_info_message( $userdata, $args = array() ) {
		if ( empty( $userdata['user_login'] ) || empty( $userdata['user_pass'] ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'context'    => '',
				'button_url' => '',
			)
		);

		$p                  = ic_email_paragraph();
		$ep                 = ic_email_paragraph_end();
		$ul                 = ic_email_ul();
		$eul                = ic_email_ul_end();
		$li                 = ic_email_li();
		$eli                = ic_email_li_end();
		$user_account_info  = $p . ic_customers_account_info_text( 'intro', 'Please see your account info below', $args['context'] ) . ':' . $ep;
		$user_account_info .= $ul;
		$user_account_info .= $li . ic_customers_account_info_text( 'login_label', 'Login', $args['context'] ) . ': ' . $userdata['user_login'] . $eli;
		$user_account_info .= $li . ic_customers_account_info_text( 'password_label', 'Password', $args['context'] ) . ': ' . $userdata['user_pass'] . $eli;
		$user_account_info .= $eul;
		if ( ! empty( $args['button_url'] ) ) {
			$user_account_info .= ic_email_button( $args['button_url'] ) . ic_customers_account_info_text( 'button_label', 'Login Now', $args['context'] ) . '</a>';
		}

		return $user_account_info;
	}
}

if ( ! function_exists( 'ic_customers_replace_account_info_placeholder' ) ) {
	/**
	 * Replaces the legacy `[account_info]` placeholder with the shared account info block.
	 *
	 * @param string $message  Existing email/message content.
	 * @param array  $userdata New account user data.
	 * @param array  $args     Optional rendering arguments.
	 *
	 * @return string
	 */
	function ic_customers_replace_account_info_placeholder( $message, $userdata, $args = array() ) {
		$account_info = ic_customers_new_account_info_message( $userdata, $args );

		return str_replace( '[account_info]', $account_info, $message );
	}
}
