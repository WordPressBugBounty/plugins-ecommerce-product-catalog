<?php
/**
 * Shared customer account-creation helpers.
 *
 * @package ic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_customers_ensure_email_account' ) ) {
	/**
	 * Finds or creates an email-login user account.
	 *
	 * @param string $email Customer email address.
	 * @param array  $args  Optional account arguments.
	 *
	 * @return array
	 */
	function ic_customers_ensure_email_account( $email, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'role'               => 'customer',
				'allow_email_lookup' => true,
				'save_global'        => true,
			)
		);

		$account = array(
			'id'      => 0,
			'pass'    => '',
			'created' => false,
		);
		if ( ! is_email( $email ) ) {
			return $account;
		}

		$customer_id = username_exists( $email );
		if ( empty( $customer_id ) && ! empty( $args['allow_email_lookup'] ) ) {
			$customer_id = email_exists( $email );
		}
		if ( empty( $customer_id ) ) {
			$password = wp_generate_password();
			$userdata = array(
				'user_login' => $email,
				'user_pass'  => $password,
				'user_email' => $email,
				'role'       => $args['role'],
			);
			if ( ! empty( $args['save_global'] ) ) {
				ic_save_global( 'ic_new_customer_data', $userdata );
			}
			$customer_id = wp_insert_user( $userdata );
			if ( ! is_wp_error( $customer_id ) ) {
				$account['pass']    = $password;
				$account['created'] = true;
			}
		}

		$account['id'] = $customer_id;

		return $account;
	}
}
