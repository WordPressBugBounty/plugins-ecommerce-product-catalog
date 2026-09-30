<?php
/**
 * Small retrying transaction helper for Stats workers.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! class_exists( 'IC_Stats_Transaction' ) ) {
	/** Execute a unit of Stats work atomically with bounded deadlock retries. */
	class IC_Stats_Transaction {
		/**
		 * Run a unit of Stats work atomically with bounded deadlock retries.
		 *
		 * @param callable $callback Transaction callback.
		 * @param int      $attempts Maximum attempts.
		 * @return mixed
		 * @throws Exception When the transaction fails.
		 */
		public static function run( $callback, $attempts = 2 ) {
			global $wpdb;
			$attempts = max( 1, min( 3, absint( $attempts ) ) );
			for ( $attempt = 0; $attempt < $attempts; $attempt++ ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
				$wpdb->query( 'START TRANSACTION' );
				try {
					$result = call_user_func( $callback );
					if ( false === $result || ! empty( $wpdb->last_error ) ) {
						throw new Exception( 'stats transaction failed' );
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
					if ( false === $wpdb->query( 'COMMIT' ) ) {
						throw new Exception( 'stats transaction commit failed' );
					}
					return $result;
				} catch ( Throwable $exception ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
					$wpdb->query( 'ROLLBACK' );
					if ( $attempt + 1 >= $attempts ) {
						return false;
					}
				}
			}
			return false;
		}
	}
}
