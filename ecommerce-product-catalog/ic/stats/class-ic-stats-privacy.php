<?php
/**
 * WordPress privacy exporter and eraser for the shared statistics tables.
 *
 * ONE registration covers EVERY registered integration. The statistics tables
 * are shared and keyed by identity, not by plugin, so a per-plugin eraser that
 * only knows its own integration key leaves another plugin's facts behind --
 * `implecode-ai` records its own identities and has no eraser of its own.
 *
 * Nothing here deletes a number. The eraser queues the anonymisation job built
 * in `IC_Stats_Manager::anonymise_identity()`, which rewrites the subject out
 * of the aggregates, the facts and the lifecycles and deletes only the raw
 * events. Totals do not move; the identity behind them is destroyed. That is
 * why the eraser always reports `items_retained`.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'IC_Stats_Privacy' ) ) {

	/**
	 * Privacy integration for the shared statistics framework.
	 */
	class IC_Stats_Privacy {

		/**
		 * Non-identifying erasure marker option.
		 *
		 * Holds a count and a timestamp only -- no identity hash, no user id --
		 * so it is not a ledger and cannot re-link anything. Its single job is
		 * to let a rebuild warn that N erasures were performed.
		 */
		const NOTICE_OPTION = 'ic_stats_erasure_notice';

		/**
		 * Rows exported per page.
		 */
		const PAGE_SIZE = 100;

		/**
		 * Anonymisation batches run per eraser page.
		 */
		const BATCHES_PER_PAGE = 10;

		/**
		 * Hard page ceiling before the remainder is handed to cron.
		 */
		const MAX_PAGES = 100;

		/**
		 * Cron hook draining whatever an inline pass could not finish.
		 */
		const DISPATCH_HOOK = 'ic_stats_anonymisation_dispatch';

		/**
		 * Register the exporter, the eraser and the deletion hook.
		 */
		public static function init() {
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
			add_action( 'deleted_user', array( __CLASS__, 'deleted_user' ), 10, 1 );
			add_action( self::DISPATCH_HOOK, array( __CLASS__, 'dispatch' ) );
		}

		/**
		 * Register the statistics exporter.
		 *
		 * @param array $exporters Registered exporters.
		 * @return array
		 */
		public static function register_exporter( $exporters ) {
			if ( ! is_array( $exporters ) ) {
				$exporters = array();
			}
			$exporters['ic-stats'] = array(
				'exporter_friendly_name' => __( 'Statistics', 'ecommerce-product-catalog' ),
				'callback'               => array( __CLASS__, 'exporter' ),
			);
			return $exporters;
		}

		/**
		 * Register the statistics eraser.
		 *
		 * @param array $erasers Registered erasers.
		 * @return array
		 */
		public static function register_eraser( $erasers ) {
			if ( ! is_array( $erasers ) ) {
				$erasers = array();
			}
			$erasers['ic-stats'] = array(
				'eraser_friendly_name' => __( 'Statistics', 'ecommerce-product-catalog' ),
				'callback'             => array( __CLASS__, 'eraser' ),
			);
			return $erasers;
		}

		/**
		 * The retention sentence reported by both the exporter and the eraser.
		 *
		 * @return string
		 */
		public static function retention_message() {
			$message = __( 'Aggregated statistics derived from this account are retained indefinitely in a form that is not linked to the account and, after erasure, cannot be attributed to the subject.', 'ecommerce-product-catalog' );
			/**
			 * Filters the statistics retention notice shown to the data subject.
			 *
			 * @param string $message Retention sentence.
			 */
			return (string) apply_filters( 'ic_stats_privacy_retention_message', $message );
		}

		/**
		 * Export the subject's stored event metadata.
		 *
		 * Identity hashes are NEVER exported: the export file is emailed, and a
		 * hash is a re-identification key, not information the subject needs.
		 *
		 * @param string $email Subject email address.
		 * @param int    $page  Export page, one based.
		 * @return array
		 */
		public static function exporter( $email, $page = 1 ) {
			global $wpdb;
			$user = get_user_by( 'email', sanitize_email( (string) $email ) );
			$page = max( 1, absint( $page ) );
			if ( ! $user ) {
				return array(
					'data' => array(),
					'done' => true,
				);
			}
			$table = $wpdb->prefix . 'ic_stats_events';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; every value is prepared, and a privacy export must read live rows rather than a cache.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT integration,event_key,occurred_at_gmt,currency FROM {$table} WHERE user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", $user->ID, self::PAGE_SIZE, ( $page - 1 ) * self::PAGE_SIZE ), ARRAY_A );
			$rows = is_array( $rows ) ? $rows : array();
			$data = array();
			foreach ( $rows as $row ) {
				$data[] = array(
					'group_id'    => 'ic-stats',
					'group_label' => __( 'Statistics', 'ecommerce-product-catalog' ),
					'item_id'     => 'ic-stats-event-' . sanitize_key( (string) $row['integration'] ) . '-' . sanitize_key( (string) $row['event_key'] ) . '-' . sanitize_key( (string) $row['occurred_at_gmt'] ),
					'data'        => array(
						array(
							'name'  => __( 'Event', 'ecommerce-product-catalog' ),
							'value' => (string) $row['event_key'],
						),
						array(
							'name'  => __( 'Occurred', 'ecommerce-product-catalog' ),
							'value' => (string) $row['occurred_at_gmt'],
						),
						array(
							'name'  => __( 'Currency', 'ecommerce-product-catalog' ),
							'value' => (string) $row['currency'],
						),
					),
				);
			}
			if ( 1 === $page ) {
				foreach ( self::integration_labels() as $integration => $label ) {
					$data[] = array(
						'group_id'    => 'ic-stats',
						'group_label' => __( 'Statistics', 'ecommerce-product-catalog' ),
						'item_id'     => 'ic-stats-retention-' . sanitize_key( $integration ),
						'data'        => array(
							array(
								'name'  => __( 'Statistics source', 'ecommerce-product-catalog' ),
								'value' => $label,
							),
							array(
								'name'  => __( 'Retention', 'ecommerce-product-catalog' ),
								'value' => self::retention_message(),
							),
						),
					);
				}
			}
			return array(
				'data' => $data,
				'done' => count( $rows ) < self::PAGE_SIZE,
			);
		}

		/**
		 * Erase the subject's identity from every statistics integration.
		 *
		 * Paged: one call runs a bounded number of anonymisation batches and
		 * reports `done => false` while stages remain, so a large history never
		 * blocks the request. `items_retained` is ALWAYS true -- the numbers
		 * stay, which is the whole point of the design, and the subject is told
		 * so rather than left to discover it.
		 *
		 * @param string $email Subject email address.
		 * @param int    $page  Eraser page, one based.
		 * @return array
		 */
		public static function eraser( $email, $page = 1 ) {
			$user = get_user_by( 'email', sanitize_email( (string) $email ) );
			$page = max( 1, absint( $page ) );
			if ( ! $user || ! function_exists( 'ic_stats_anonymise_user' ) ) {
				return self::eraser_response( false, true );
			}
			$user_id = absint( $user->ID );
			$job_id  = 'user_' . $user_id;
			$queue   = ic_stats_anonymisation_queue();
			$queued  = isset( $queue[ $job_id ] );
			if ( ! $queued && $page > 1 ) {
				// The job this request started has already finished. Queueing a
				// second one would mint a second token for one person and loop
				// the pager forever.
				return self::eraser_response( false, true );
			}
			if ( ! $queued ) {
				$job_id = ic_stats_anonymise_user( $user_id );
				if ( ! $job_id ) {
					return self::eraser_response( false, true );
				}
				self::record_erasure_notice();
			}
			$identity = self::identity_hash( $user_id );
			$before   = self::subject_row_count( $user_id, $identity );
			$done     = false;
			for ( $batch = 0; $batch < self::BATCHES_PER_PAGE; $batch++ ) {
				$result = ic_stats_anonymisation_step( $job_id );
				if ( ! empty( $result['done'] ) ) {
					$done = true;
					break;
				}
				if ( 'error' === (string) ( $result['state'] ?? '' ) ) {
					break;
				}
			}
			$after = self::subject_row_count( $user_id, $identity );
			if ( ! $done && $page >= self::MAX_PAGES ) {
				// Refuse to page forever. The queued job survives and the cron
				// dispatcher finishes it.
				self::schedule_dispatch();
				$done = true;
			}
			return self::eraser_response( $after < $before, $done );
		}

		/**
		 * Anonymise the statistics of a user WordPress has just deleted.
		 *
		 * `deleted_user`, not `delete_user`: the identifier is all the job
		 * needs, and a deletion can still abort after `delete_user` has fired.
		 *
		 * @param int $user_id Deleted user identifier.
		 */
		public static function deleted_user( $user_id ) {
			$user_id = absint( $user_id );
			if ( ! $user_id || ! function_exists( 'ic_stats_anonymise_user' ) ) {
				return;
			}
			/**
			 * Filters whether deleting a user anonymises their statistics.
			 *
			 * @param bool $anonymise Whether to anonymise.
			 * @param int  $user_id   Deleted user identifier.
			 */
			if ( ! apply_filters( 'ic_stats_anonymise_on_user_delete', true, $user_id ) ) {
				return;
			}
			$queue  = ic_stats_anonymisation_queue();
			$job_id = ic_stats_anonymise_user( $user_id );
			if ( ! $job_id ) {
				return;
			}
			if ( ! isset( $queue[ $job_id ] ) ) {
				self::record_erasure_notice();
			}
			$result = ic_stats_anonymisation_step( $job_id );
			if ( empty( $result['done'] ) ) {
				self::schedule_dispatch();
			}
		}

		/**
		 * Drain whatever an inline pass could not finish.
		 */
		public static function dispatch() {
			if ( function_exists( 'ic_stats_dispatch_anonymisations' ) ) {
				ic_stats_dispatch_anonymisations();
			}
		}

		/**
		 * Read the non-identifying erasure marker.
		 *
		 * @return array `count` and `last_gmt`.
		 */
		public static function notice() {
			$notice = get_option( self::NOTICE_OPTION, array() );
			$notice = is_array( $notice ) ? $notice : array();
			return array(
				'count'    => absint( $notice['count'] ?? 0 ),
				'last_gmt' => (string) ( $notice['last_gmt'] ?? '' ),
			);
		}

		/**
		 * Schedule the cron drain unless one is already pending.
		 */
		private static function schedule_dispatch() {
			if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::DISPATCH_HOOK ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::DISPATCH_HOOK );
			}
		}

		/**
		 * Record that one more erasure happened.
		 *
		 * Deliberately holds NO identity hash and NO user id: a rebuild from
		 * saved orders can undo an erasure, and the operator has to be able to
		 * learn that without the framework keeping a re-identification map.
		 */
		private static function record_erasure_notice() {
			$notice = self::notice();
			update_option(
				self::NOTICE_OPTION,
				array(
					'count'    => $notice['count'] + 1,
					'last_gmt' => gmdate( 'Y-m-d H:i:s' ),
				),
				false
			);
		}

		/**
		 * Build the standard eraser response.
		 *
		 * @param bool $removed Whether a row was deleted or rewritten.
		 * @param bool $done    Whether the job has no stages left.
		 * @return array
		 */
		private static function eraser_response( $removed, $done ) {
			return array(
				'items_removed'  => (bool) $removed,
				'items_retained' => true,
				'messages'       => array( self::retention_message() ),
				'done'           => (bool) $done,
			);
		}

		/**
		 * Resolve the subject's pre-erasure identity hash.
		 *
		 * @param int $user_id Subject user identifier.
		 * @return string
		 */
		private static function identity_hash( $user_id ) {
			$manager = function_exists( 'ic_stats_manager' ) ? ic_stats_manager() : null;
			return $manager instanceof IC_Stats_Manager ? (string) $manager->dedupe_key( 'user:' . absint( $user_id ) ) : '';
		}

		/**
		 * Count the rows still attributable to the subject.
		 *
		 * The eraser reports `items_removed` from the difference across one
		 * page, so a rewrite counts as removal exactly like a delete: after the
		 * job runs, nothing in these four tables points at the person any more.
		 *
		 * @param int    $user_id  Subject user identifier.
		 * @param string $identity Pre-erasure identity hash.
		 * @return int
		 */
		private static function subject_row_count( $user_id, $identity ) {
			global $wpdb;
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				return 0;
			}
			$events     = $wpdb->prefix . 'ic_stats_events';
			$facts      = $wpdb->prefix . 'ic_stats_unique_facts';
			$dimensions = $wpdb->prefix . 'ic_stats_dimensions';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifiers; every value is prepared, and an erasure progress check must read live rows rather than a cache.
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT (SELECT COUNT(*) FROM {$events} WHERE user_id=%d)+(SELECT COUNT(*) FROM {$dimensions} WHERE dimension_key=%s AND dimension_value=%s)", $user_id, 'user_id', (string) $user_id ) );
			if ( '' !== (string) $identity ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the identity hash is prepared, and an erasure progress check must read live rows rather than a cache.
				$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$facts} WHERE identity_hash=%s", (string) $identity ) );
			}
			return (int) apply_filters( 'ic_stats_privacy_subject_row_count', $count, $user_id, $identity );
		}

		/**
		 * List every registered integration with a display label.
		 *
		 * There is no label to read: `register_integration()` REFUSES a
		 * definition carrying any field outside its known list, and `label` is
		 * not one of them. The key is therefore the label, and a consumer that
		 * wants a friendlier one supplies it through the filter.
		 *
		 * @return array Integration key => label.
		 */
		private static function integration_labels() {
			$manager = function_exists( 'ic_stats_manager' ) ? ic_stats_manager() : null;
			if ( ! $manager instanceof IC_Stats_Manager ) {
				return array();
			}
			$labels = array();
			foreach ( array_keys( (array) $manager->integrations() ) as $key ) {
				$key = sanitize_key( (string) $key );
				if ( ! $key ) {
					continue;
				}
				$labels[ $key ] = $key;
			}
			/**
			 * Filters the integration labels used by the privacy exporter.
			 *
			 * @param array $labels Integration key => label.
			 */
			return (array) apply_filters( 'ic_stats_privacy_integration_labels', $labels );
		}
	}

	// Registered inside the class guard so only the FIRST-loaded mirror wires
	// it up: two copies would hand WordPress two identical exporter keys and
	// run the deletion hook twice.
	IC_Stats_Privacy::init();
}
