<?php
/**
 * Statistics identity provider contract and helpers.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! interface_exists( 'IC_Stats_Identity_Provider' ) ) {
	/**
	 * Resolve a provider-scoped statistics identity.
	 */
	interface IC_Stats_Identity_Provider {
		/** Return the provider's source key; callers must hash it immediately. */
		public function key();
		/**
		 * Resolve the current identity.
		 *
		 * @return array
		 */
		public function resolve();
	}
}
if ( ! function_exists( 'ic_stats_identity' ) ) {
	/**
	 * Resolve the configured statistics identity provider.
	 *
	 * @return array
	 */
	function ic_stats_identity() { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Compatibility helper intentionally accompanies the provider interface.
		$identity = apply_filters( 'ic_stats_identity_provider', null, '', array() );
		$result   = $identity && is_object( $identity ) && method_exists( $identity, 'resolve' ) ? $identity->resolve() : array();
		return is_array( $result ) ? $result : array(); } }
if ( ! function_exists( 'ic_stats_identity_hash' ) ) {
	/**
	 * Hash a source identity with the site statistics secret.
	 *
	 * @param string $session_key Source identity.
	 * @param string $provider    Provider key.
	 * @return string
	 */
	function ic_stats_identity_hash( $session_key, $provider = 'default' ) {
		if ( ! $session_key ) {
			return '';
		} return hash_hmac( 'sha256', sanitize_key( $provider ) . '|' . (string) $session_key, get_option( 'ic_stats_secret', wp_salt( 'secure_auth' ) ) ); } }
