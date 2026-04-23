<?php
/**
 * Framework transient cache wrapper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Wraps WordPress transient helpers with a consistent framework API.
 */
class IC_Transient_Cache {
	/**
	 * Scheduled hook used to clean expired framework transients.
	 */
	const CLEANUP_ACTION = 'ic_delete_expired_transients';

	/**
	 * Registers the scheduled cleanup callback.
	 */
	public function __construct() {
		add_action( self::CLEANUP_ACTION, array( $this, 'delete_expired_prefixed_transients' ), 10, 1 );
	}

	/**
	 * Returns a transient value.
	 *
	 * @param string $key     Transient key.
	 * @param mixed  $default Default value when the transient is missing.
	 *
	 * @return mixed
	 */
	public function get( $key, $default = false ) {
		$value = get_transient( $key );
		if ( false === $value ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Stores a transient value.
	 *
	 * @param string  $key        Transient key.
	 * @param mixed   $value      Transient value.
	 * @param int     $expiration Expiration in seconds.
	 *
	 * @return bool
	 */
	public function set( $key, $value, $expiration = 0 ) {
		$this->maybe_schedule_expired_prefixed_transients_deletion( $key );

		return set_transient( $key, $value, (int) $expiration );
	}

	/**
	 * Deletes a transient value.
	 *
	 * @param string $key Transient key.
	 *
	 * @return bool
	 */
	public function delete( $key ) {
		return delete_transient( $key );
	}

	/**
	 * Schedules cleanup for expired framework-prefixed transients.
	 *
	 * @param string $key Transient key.
	 *
	 * @return void
	 */
	protected function maybe_schedule_expired_prefixed_transients_deletion( $key ) {
		if ( 0 !== strpos( $key, 'ic_' ) ) {
			return;
		}

		$prefix    = 'ic_';
		$hook_args = array( $prefix );

		if ( wp_next_scheduled( self::CLEANUP_ACTION, $hook_args ) ) {
			return;
		}

		$delay = (int) apply_filters( 'ic_transient_cleanup_delay', MINUTE_IN_SECONDS, $prefix );
		if ( $delay < 1 ) {
			$delay = 1;
		}

		wp_schedule_single_event( time() + $delay, self::CLEANUP_ACTION, $hook_args );
	}

	/**
	 * Deletes expired framework-prefixed transients.
	 *
	 * @param string $prefix Transient key prefix.
	 *
	 * @return void
	 */
	public function delete_expired_prefixed_transients( $prefix = 'ic_' ) {
		$this->delete_expired_option_transients( $prefix );

		if ( is_multisite() ) {
			$this->delete_expired_site_transients( $prefix );
		}
	}

	/**
	 * Deletes expired regular transients for the provided prefix.
	 *
	 * @param string $prefix Transient key prefix.
	 *
	 * @return void
	 */
	protected function delete_expired_option_transients( $prefix ) {
		global $wpdb;

		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted lookup keeps the cleanup scoped to framework transients.
		$expired_transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
					AND option_value < %d",
				$timeout_like,
				time()
			)
		);

		if ( empty( $expired_transients ) ) {
			return;
		}

		foreach ( $expired_transients as $timeout_name ) {
			$transient_name = substr( $timeout_name, strlen( '_transient_timeout_' ) );

			if ( '' !== $transient_name ) {
				delete_transient( $transient_name );
			}
		}
	}

	/**
	 * Deletes expired site transients for the provided prefix on multisite.
	 *
	 * @param string $prefix Transient key prefix.
	 *
	 * @return void
	 */
	protected function delete_expired_site_transients( $prefix ) {
		global $wpdb;

		$timeout_like = $wpdb->esc_like( '_site_transient_timeout_' . $prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted lookup keeps the cleanup scoped to framework transients.
		$expired_transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key
				FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE %s
					AND meta_value < %d",
				$timeout_like,
				time()
			)
		);

		if ( empty( $expired_transients ) ) {
			return;
		}

		foreach ( $expired_transients as $timeout_name ) {
			$transient_name = substr( $timeout_name, strlen( '_site_transient_timeout_' ) );

			if ( '' !== $transient_name ) {
				delete_site_transient( $transient_name );
			}
		}
	}
}
