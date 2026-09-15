<?php
/**
 * Restartable identity erasure engine.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates restartable identity-erasure jobs.
 */
class IC_Identity_Erasure {
	const QUEUE_OPTION = 'ic_identity_erasure_queue';
	const LOG_OPTION   = 'ic_identity_erasure_log';

	/**
	 * Registered erasable identity sources.
	 *
	 * @var array
	 */
	private static $sources = array();

	/**
	 * Registers queue hooks and the profile source.
	 */
	public static function init() {
		add_action( 'ic_identity_erasure_dispatch', 'ic_identity_erasure_dispatch' );
		add_action( 'ic_identity_retention_sweep', 'ic_identity_retention_sweep' );
		self::register_profile_source();
		if ( ! wp_next_scheduled( 'ic_identity_retention_sweep' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ic_identity_retention_sweep' );
		}
	}

	/**
	 * Registers the shared customer-profile source.
	 */
	public static function register_profile_source() {
		self::register_source(
			'ic_customer_profile',
			array(
				'type'            => 'user',
				'identity_fields' => array( '_customer_data', 'transaction_ids', 'product_ids', 'manual_product_ids', 'custom_manual_products', '_phone', 'customer_license_id', 'customer_free_license_id' ),
				'statutory'       => false,
			)
		);
	}

	/**
	 * Registers one erasable identity source.
	 *
	 * @param string $key  Source key.
	 * @param array  $args Source configuration.
	 * @return bool
	 */
	public static function register_source( $key, $args ) {
		$key = sanitize_key( $key );
		if ( ! $key || ! is_array( $args ) ) {
			return false;
		}
		self::$sources[ $key ] = wp_parse_args(
			$args,
			array(
				'type'            => 'post',
				'post_type'       => array(),
				'customer_meta'   => '_customer_id',
				'identity_meta'   => '_payment_details',
				'identity_fields' => array(),
				'summary_meta'    => '',
				'summary_fields'  => array(),
				'statutory'       => false,
				'entitlement'     => null,
			)
		);
		return true;
	}

	/**
	 * Returns all registered identity sources.
	 *
	 * @return array
	 */
	public static function sources() {
		do_action( 'ic_register_erasable_identity_sources' );
		return self::$sources;
	}

	/**
	 * Returns active-entitlement blockers for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function blockers( $user_id ) {
		$reasons = array();
		foreach ( self::sources() as $key => $source ) {
			if ( is_callable( $source['entitlement'] ) ) {
				$found = call_user_func( $source['entitlement'], absint( $user_id ), $source, $key );
				if ( is_string( $found ) && '' !== $found ) {
					$reasons[] = $found;
				} elseif ( is_array( $found ) ) {
					$reasons = array_merge( $reasons, array_filter( $found, 'is_string' ) );
				}
			}
		}
		return array_values( apply_filters( 'ic_identity_erasure_blockers', $reasons, absint( $user_id ) ) );
	}

	/**
	 * Creates or reuses a user's erasure job.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Job options.
	 * @return string|WP_Error
	 */
	public static function create_job( $user_id, $args = array() ) {
		$user_id  = absint( $user_id );
		$forced   = ! empty( $args['forced'] );
		$blockers = $forced ? array() : self::blockers( $user_id );
		if ( $blockers ) {
			self::log( $user_id, 'refused', array( 'live_entitlement' ) );
			do_action( 'ic_identity_erasure_refused', $user_id, $blockers );
			return new WP_Error( 'ic_identity_erasure_blocked', implode( ' ', $blockers ), $blockers );
		}
		$queue  = get_option( self::QUEUE_OPTION, array() );
		$job_id = 'user_' . $user_id;
		if ( isset( $queue[ $job_id ] ) ) {
			return $job_id;
		}
		$user             = get_userdata( $user_id );
		$queue[ $job_id ] = array(
			'user_id'         => $user_id,
			'token'           => ic_identity_removal_token( $user_id ),
			'user_role'       => ! empty( $args['user_role'] ) ? sanitize_key( $args['user_role'] ) : ( $user && $user->roles ? sanitize_key( reset( $user->roles ) ) : '' ),
			'stage'           => 'check',
			'source'          => '',
			'cursor'          => 0,
			'attempts'        => 0,
			'created_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			'forced'          => $forced,
			'anonymise_stats' => ! array_key_exists( 'anonymise_stats', $args ) || (bool) $args['anonymise_stats'],
		);
		self::persist_queue( $queue );
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'ic_identity_erasure_dispatch' );
		return $job_id;
	}

	/**
	 * Advances one erasure job by one bounded batch.
	 *
	 * @param string $job_id Job ID.
	 * @param int    $limit  Maximum records for this step.
	 * @return array|bool|WP_Error
	 */
	public static function step( $job_id, $limit = 0 ) {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( empty( $queue[ $job_id ] ) ) {
			return false;
		}
		$job   = $queue[ $job_id ];
		$limit = max( 1, min( 1000, $limit ? absint( $limit ) : absint( apply_filters( 'ic_identity_erasure_batch', 200 ) ) ) );
		$ttl   = absint( apply_filters( 'ic_identity_erasure_job_ttl', 7 * DAY_IN_SECONDS ) );
		if ( $ttl && strtotime( $job['created_gmt'] . ' UTC' ) + $ttl < time() ) {
			$job['stage'] = 'cleanup';
		}
		++$job['attempts'];
		switch ( $job['stage'] ) {
			case 'check':
				$blockers = $job['forced'] ? array() : self::blockers( $job['user_id'] );
				if ( $blockers ) {
					unset( $queue[ $job_id ] );
					self::persist_queue( $queue );
					return new WP_Error( 'ic_identity_erasure_blocked', implode( ' ', $blockers ), $blockers );
				}
				$job['stage'] = 'orders';
				break;
			case 'orders':
				$job = self::process_sources( $job, true, $limit, 'entitlements' );
				break;
			case 'entitlements':
				$job = self::process_sources( $job, false, $limit, 'profile' );
				break;
			case 'profile':
				self::erase_profile( $job['user_id'] );
				$job['stage'] = 'schedule_phase_two';
				break;
			case 'schedule_phase_two':
				if ( ! wp_next_scheduled( 'ic_identity_retention_sweep' ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ic_identity_retention_sweep' );
				}
				$job['stage'] = 'stats';
				break;
			case 'stats':
				if ( ! empty( $job['anonymise_stats'] ) && function_exists( 'ic_stats_anonymise_user' ) ) {
					ic_stats_anonymise_user( $job['user_id'], array( 'token' => $job['token'] ) );
				}
				$job['stage'] = 'cleanup';
				break;
			case 'cleanup':
				unset( $queue[ $job_id ] );
				self::persist_queue( $queue );
				self::log( $job['user_id'], $job['forced'] ? 'forced' : 'erased', array() );
				do_action( $job['forced'] ? 'ic_identity_erasure_forced' : 'ic_identity_erased', $job['user_id'], $job['token'] );
				return array_merge( $job, array( 'done' => true ) );
		}
		$queue[ $job_id ] = $job;
		self::persist_queue( $queue );
		return $job;
	}

	/**
	 * Persists or removes the restartable queue.
	 *
	 * @param array $queue Queue state.
	 */
	private static function persist_queue( $queue ) {
		if ( $queue ) {
			update_option( self::QUEUE_OPTION, $queue, false );
		} else {
			delete_option( self::QUEUE_OPTION );
		}
	}

	/**
	 * Processes one class of post sources.
	 *
	 * @param array  $job        Job state.
	 * @param bool   $statutory  Whether sources are statutory.
	 * @param int    $limit      Batch limit.
	 * @param string $next_stage Next queue stage.
	 * @return array
	 */
	private static function process_sources( $job, $statutory, $limit, $next_stage ) {
		$sources = array_filter(
			self::sources(),
			static function ( $source ) use ( $statutory ) {
				return 'post' === $source['type'] && (bool) $source['statutory'] === $statutory;
			}
		);
		$keys    = array_keys( $sources );
		if ( ! $keys ) {
			$job['stage']  = $next_stage;
			$job['source'] = '';
			$job['cursor'] = 0;
			return $job;
		}
		$index = $job['source'] ? array_search( $job['source'], $keys, true ) : 0;
		$index = false === $index ? 0 : $index;
		$key   = $keys[ $index ];
		$ids   = self::record_ids( $sources[ $key ], $job['user_id'], $job['cursor'], $limit );
		foreach ( $ids as $record_id ) {
			self::sever_record( $record_id, $sources[ $key ], $job );
			$job['cursor'] = $record_id;
		}
		$job['source'] = $key;
		if ( count( $ids ) < $limit ) {
			++$index;
			$job['cursor'] = 0;
			if ( isset( $keys[ $index ] ) ) {
				$job['source'] = $keys[ $index ];
			} else {
				$job['source'] = '';
				$job['stage']  = $next_stage;
			}
		}
		return $job;
	}

	/**
	 * Finds the next bounded record IDs for a source.
	 *
	 * @param array $source  Source configuration.
	 * @param int   $user_id User ID.
	 * @param int   $cursor  Last processed ID.
	 * @param int   $limit   Batch limit.
	 * @return array
	 */
	private static function record_ids( $source, $user_id, $cursor, $limit ) {
		global $wpdb;
		$post_types = array_map( 'sanitize_key', (array) $source['post_type'] );
		if ( ! $post_types ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$params       = array_merge( $post_types, array( $source['customer_meta'], (string) $user_id, absint( $cursor ), absint( $limit ) ) );
		$sql          = "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID WHERE p.post_type IN ($placeholders) AND pm.meta_key=%s AND pm.meta_value=%s AND p.ID>%d ORDER BY p.ID ASC LIMIT %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bounded identity lookup uses sanitized source identifiers and prepared values.
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
	}

	/**
	 * Severs a record and applies its phase-one retention policy.
	 *
	 * @param int   $record_id Record ID.
	 * @param array $source    Source configuration.
	 * @param array $job       Job state.
	 */
	private static function sever_record( $record_id, $source, $job ) {
		$removed = 'removed:' . $job['token'];
		delete_post_meta( $record_id, $source['customer_meta'] );
		update_post_meta( $record_id, '_ic_removed_customer', $removed );
		update_post_meta( $record_id, '_ic_removed_customer_role', $job['user_role'] );
		update_post_meta( $record_id, '_ic_customer_severed_gmt', gmdate( 'Y-m-d H:i:s' ) );
		if ( $source['statutory'] ) {
			$date   = get_post_field( 'post_date_gmt', $record_id );
			$date   = $date && '0000-00-00 00:00:00' !== $date ? $date : get_post_field( 'post_date', $record_id );
			$period = apply_filters( 'ic_identity_retention_period', '+10 years', $record_id, $source );
			update_post_meta( $record_id, '_ic_identity_retain_until', gmdate( 'Y-m-d H:i:s', strtotime( $period, strtotime( $date . ' UTC' ) ) ) );
		} else {
			self::wipe_record_identity( $record_id, $source );
			update_post_meta( $record_id, '_ic_identity_erased_gmt', gmdate( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * Clears configured identity fields from a post record.
	 *
	 * @param int   $record_id Record ID.
	 * @param array $source    Source configuration.
	 */
	private static function wipe_record_identity( $record_id, $source ) {
		$data = get_post_meta( $record_id, $source['identity_meta'], true );
		if ( is_array( $data ) ) {
			foreach ( apply_filters( 'ic_identity_erasure_fields', $source['identity_fields'], $source, $record_id ) as $field ) {
				if ( array_key_exists( $field, $data ) ) {
					$data[ $field ] = '';
				}
			}
			update_post_meta( $record_id, $source['identity_meta'], $data );
		}
		if ( $source['summary_meta'] ) {
			$summary = get_post_meta( $record_id, $source['summary_meta'], true );
			if ( is_array( $summary ) ) {
				foreach ( $source['summary_fields'] as $field ) {
					if ( array_key_exists( $field, $summary ) ) {
						$summary[ $field ] = '';
					}
				}
				update_post_meta( $record_id, $source['summary_meta'], $summary );
			}
		}
	}

	/**
	 * Clears the shared profile identity metadata.
	 *
	 * @param int $user_id User ID.
	 */
	private static function erase_profile( $user_id ) {
		$source = isset( self::sources()['ic_customer_profile'] ) ? self::sources()['ic_customer_profile'] : array();
		foreach ( isset( $source['identity_fields'] ) ? $source['identity_fields'] : array() as $key ) {
			delete_user_meta( $user_id, $key );
		}
	}

	/**
	 * Dispatches queued jobs and the retention sweep.
	 *
	 * @return bool
	 */
	public static function dispatch() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		foreach ( array_keys( $queue ) as $job_id ) {
			self::step( $job_id );
		}
		self::retention_sweep();
		if ( get_option( self::QUEUE_OPTION, array() ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'ic_identity_erasure_dispatch' );
		}
		return true;
	}

	/**
	 * Erases statutory identity fields whose deadline has passed.
	 *
	 * @return int Number of records processed.
	 */
	public static function retention_sweep() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded retention lookup uses a prepared metadata query.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value<=%s ORDER BY post_id ASC LIMIT 100", '_ic_identity_retain_until', gmdate( 'Y-m-d H:i:s' ) ) );
		foreach ( array_map( 'absint', $ids ) as $record_id ) {
			foreach ( self::sources() as $source ) {
				if ( $source['statutory'] && in_array( get_post_type( $record_id ), (array) $source['post_type'], true ) ) {
					self::wipe_record_identity( $record_id, $source );
					update_post_meta( $record_id, '_ic_identity_erased_gmt', gmdate( 'Y-m-d H:i:s' ) );
					delete_post_meta( $record_id, '_ic_identity_retain_until' );
					do_action( 'ic_identity_phase_two_erased', $record_id, $source );
					break;
				}
			}
		}
		return count( $ids );
	}

	/**
	 * Appends a bounded audit event.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $event      Event name.
	 * @param array  $reason_keys Reason keys.
	 */
	private static function log( $user_id, $event, $reason_keys ) {
		$log   = get_option( self::LOG_OPTION, array() );
		$log[] = array(
			'user_id'      => absint( $user_id ),
			'event'        => sanitize_key( $event ),
			'reason_keys'  => array_map( 'sanitize_key', $reason_keys ),
			'occurred_gmt' => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( self::LOG_OPTION, array_slice( $log, -100 ), false );
	}
}
