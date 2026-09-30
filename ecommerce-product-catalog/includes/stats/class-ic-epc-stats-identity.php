<?php
/**
 * EPC statistics identity provider.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! class_exists( 'IC_EPC_Stats_Identity' ) ) {
	/**
	 * Resolve EPC identity from the existing IC session.
	 */
	class IC_EPC_Stats_Identity implements IC_Stats_Identity_Provider {
		public function key() {
			$session = function_exists( 'ic_get_global' ) ? ic_get_global( 'ic_session' ) : null;
			return is_object( $session ) && method_exists( $session, 'get_customer_id' ) ? (string) $session->get_customer_id() : '';
		}
		/**
		 * Resolve the current provider-scoped identity.
		 *
		 * @return array
		 */
		public function resolve() {
			$session = function_exists( 'ic_get_global' ) ? ic_get_global( 'ic_session' ) : null;
			if ( ! is_object( $session ) || ! method_exists( $session, 'get_customer_id' ) ) {
				return array(
					'session_hash' => '',
					'persistence' => 'none',
					'quality'     => 'unavailable',
				); }
			$id         = $this->key();
			$persistent = method_exists( $session, 'get_session_cookie' ) ? (bool) $session->get_session_cookie() : false;
			return array(
				'session_hash' => $id ? ic_stats_identity_hash( $id, 'epc' ) : '',
				'user_id'     => is_user_logged_in() ? get_current_user_id() : 0,
				'role'        => 'unknown',
				'persistence' => $persistent ? 'persistent' : 'ephemeral',
				'quality'     => $persistent ? 'exact' : ( $id ? 'degraded' : 'unavailable' ),
			);
		}
	}
	add_filter(
		'ic_stats_identity_provider',
		function ( $provider ) {
			return $provider ? $provider : new IC_EPC_Stats_Identity();
		}
	);
}
