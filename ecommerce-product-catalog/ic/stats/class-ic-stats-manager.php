<?php
/**
 * Statistics event, metric and aggregate manager.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! class_exists( 'IC_Stats_Manager' ) ) {
	/**
	 * Manage registered integrations and persisted statistics.
	 */
	class IC_Stats_Manager {
		/**
		 * Registered integration definitions.
		 *
		 * @var array
		 */
		private $integrations = array();
		/**
		 * Whether the registration action has fired.
		 *
		 * @var bool
		 */
		private $ready_fired = false;
		/**
		 * Diagnostics for the most recently completed projection batch.
		 *
		 * This is deliberately separate from process()'s public integer return:
		 * bridges can be finalized without contributing an aggregate.
		 *
		 * @var array
		 */
		private $last_process_stats = array(
			'selected'  => 0,
			'finalized' => 0,
		);
		/**
		 * Initialize the manager and announce registration readiness.
		 *
		 * @param bool $announce Whether to announce registration immediately.
		 */
		public function __construct( $announce = false ) {
			if ( $announce ) {
				$this->announce_registration(); }
		}
		/** Announce registration after the singleton has been assigned. */
		public function announce_registration() {
			if ( ! $this->ready_fired ) {
				$this->ready_fired = true;
				do_action( 'ic_stats_register', $this );
				do_action( 'ic_stats_registry_ready', $this );
			}
		}
		/**
		 * Register an integration definition.
		 *
		 * @param string $key        Integration key.
		 * @param array  $definition Integration definition.
		 * @return bool
		 */
		public function register_integration( $key, $definition ) {
			$key = sanitize_key( $key );
			if ( ! $key || ! is_array( $definition ) ) {
				return false; }
			$api_version_provided = array_key_exists( 'api_version', $definition );
			if ( ! $api_version_provided ) {
				$definition['api_version'] = 2;
			}
			$definition += array(
				'api_version'    => 1,
				'schema_version' => 1,
				'supports'       => array(),
				'metrics'        => array(),
				'defaults'       => array(),
			);
			foreach ( (array) $definition['metrics'] as $metric => $meta ) {
				$definition['metrics'][ $metric ]                   = wp_parse_args(
					(array) $meta,
					array(
						'events'         => array( $metric ),
						'calculation'    => 'count',
						'source_mode'    => 'durable_projection',
						'dimensions'     => array(),
						'alias_of'       => '',
						'derived_from'   => array(),
						'source_metrics' => array(),
						'internal'       => false,
						'enabled_with'   => '',
						'filter_sources' => array(),
						'storage_policy' => 'event',
					)
				);
				$definition['metrics'][ $metric ]['internal']       = ! empty( $definition['metrics'][ $metric ]['internal'] );
				$definition['metrics'][ $metric ]['enabled_with']   = sanitize_key( $definition['metrics'][ $metric ]['enabled_with'] );
				$definition['metrics'][ $metric ]['filter_sources'] = array_map( 'sanitize_key', (array) $definition['metrics'][ $metric ]['filter_sources'] );
				if ( isset( $definition['metrics'][ $metric ]['projector'] ) && ! is_callable( $definition['metrics'][ $metric ]['projector'] ) ) {
					unset( $definition['metrics'][ $metric ]['projector'] ); }
			}
			foreach ( (array) $definition['metrics'] as $metric => $meta ) {
				$enabled_with = sanitize_key( $meta['enabled_with'] ?? '' );
				if ( $enabled_with && ( $enabled_with === $metric || ! isset( $definition['metrics'][ $enabled_with ] ) ) ) {
					return false;
				}
				foreach ( (array) ( $meta['filter_sources'] ?? array() ) as $filter => $source ) {
					if ( 'product' !== $filter || $source === $metric || empty( $definition['metrics'][ $source ]['internal'] ) ) {
						return false;
					}
				}
				$seen = array( $metric => true );
				$next = $enabled_with;
				while ( $next ) {
					if ( isset( $seen[ $next ] ) ) {
						return false;
					}
					$seen[ $next ] = true;
					$next          = sanitize_key( $definition['metrics'][ $next ]['enabled_with'] ?? '' );
				}
			}
			$known_fields = array( 'api_version', 'schema_version', 'projection_version', 'capability', 'supports', 'pages', 'events', 'metrics', 'lifecycle', 'dimensions', 'retention', 'backfill', 'quality', 'defaults', '_extension_owners' );
			foreach ( array_keys( $definition ) as $field ) {
				if ( ! in_array( $field, $known_fields, true ) ) {
					return false;
				}
			}
			if ( (int) $definition['api_version'] < 1 || (int) $definition['api_version'] > 2 || (int) $definition['schema_version'] > 4 ) {
				return false; }
			if ( $api_version_provided && 1 === (int) $definition['api_version'] && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'deprecated_function_run', 'ic_stats_register_integration', '2.0.0', 'Use api_version=2 definitions.' );
			}
			$known = array( 'durable_projection', 'telemetry', 'aggregates', 'identity', 'lifecycle' );
			foreach ( (array) $definition['supports'] as $support ) {
				if ( ! in_array( $support, $known, true ) ) {
					return false; }
			}
			$this->integrations[ $key ] = $definition;
			return true;
		}
		/**
		 * Return one integration definition.
		 *
		 * @param string $key Integration key.
		 * @return array
		 */
		public function integration( $key ) {
			return isset( $this->integrations[ sanitize_key( $key ) ] ) ? $this->integrations[ sanitize_key( $key ) ] : array(); }
		/**
		 * Check whether a metric is enabled.
		 *
		 * @param string $integration Integration key.
		 * @param string $metric      Metric key.
		 * @return bool
		 */
		public function metric_enabled( $integration, $metric ) {
			$def = $this->integration( $integration );
			if ( ! $def ) {
					return false; }
			$metric       = sanitize_key( $metric );
			$enabled_with = sanitize_key( $def['metrics'][ $metric ]['enabled_with'] ?? '' );
			if ( $enabled_with && $enabled_with !== $metric && isset( $def['metrics'][ $enabled_with ] ) ) {
				$metric = $enabled_with;
			}
			$enabled  = isset( $def['metrics'][ $metric ]['default_enabled'] ) ? (bool) $def['metrics'][ $metric ]['default_enabled'] : ( isset( $def['defaults'][ $metric ] ) ? (bool) $def['defaults'][ $metric ] : false );
			$settings = get_option( 'ic_stats_' . sanitize_key( $integration ), array() );
			if ( is_array( $settings ) && array_key_exists( $metric, $settings ) ) {
				$enabled = (bool) $settings[ $metric ]; }
			return (bool) apply_filters( 'ic_stats_metric_enabled', $enabled, $integration, $metric, $def );
		}
		/**
		 * Record one event and create enabled metric bridges.
		 *
		 * @param string $integration Integration key.
		 * @param string $event       Event key.
		 * @param array  $payload     Event payload.
		 * @param array  $context     Event context.
		 * @return int
		 * @throws Throwable When a nested transaction cannot complete.
		 */
		public function record( $integration, $event, $payload = array(), $context = array() ) {
			$def = $this->integration( $integration );
			if ( ! $def || ! is_array( $payload ) ) {
				return 0; }
			$event            = sanitize_key( $event );
			$count            = 0;
			$eligible_metrics = array();
			/**
			 * Resolve the configured provider once per event. Raw provider keys never
			 * cross this boundary; adapters return an already-HMACed session_hash.
			 */
			$identity = is_array( $context['identity'] ?? null ) ? $context['identity'] : array();
			if ( empty( $identity ) ) {
				$provider = apply_filters( 'ic_stats_identity_provider', null, $event, $context );
				if ( $provider && is_object( $provider ) && method_exists( $provider, 'resolve' ) ) {
					$identity = (array) $provider->resolve();
					if ( ! empty( $identity['session_key'] ) && empty( $identity['session_hash'] ) && function_exists( 'ic_stats_identity_hash' ) ) {
						$provider_key             = method_exists( $provider, 'key' ) ? $provider->key() : 'default';
						$identity['session_hash'] = ic_stats_identity_hash( $identity['session_key'], $provider_key );
					}
					unset( $identity['session_key'] );
				}
			}
			$context['identity'] = $identity;
			foreach ( (array) $def['metrics'] as $metric => $metric_def ) {
				if ( ! empty( $metric_def['alias_of'] ) || ! empty( $metric_def['derived_from'] ) || 'derived' === ( $metric_def['storage_policy'] ?? '' ) ) {
					continue;
				}
				if ( ! $this->metric_enabled( $integration, $metric ) ) {
					continue; }
				$events = isset( $metric_def['events'] ) ? (array) $metric_def['events'] : array( $metric );
				if ( in_array( $event, $events, true ) ) {
					if ( ! empty( $metric_def['requires_session'] ) && empty( $identity['session_hash'] ) ) {
						continue;
					}
					++$count;
					$eligible_metrics[] = $metric;
					do_action( 'ic_stats_metric_recorded', $integration, $metric, $event, $payload, $context ); }
			}
			if ( $count ) {
				global $wpdb;
				$owns_transaction = empty( $context['_batch_transaction'] );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
				if ( $owns_transaction ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
					$wpdb->query( 'START TRANSACTION' );
				}
				$table                = $wpdb->prefix . 'ic_stats_events';
				$now                  = gmdate( 'Y-m-d H:i:s' );
				$occurred_raw         = $context['occurred_at_gmt'] ?? '';
				$timestamp_quality    = 'live';
				$timestamp_provenance = 'runtime';
				$trusted_backfill     = ! empty( $context['backfill'] ) || ! empty( $context['timestamp_provenance'] ) || isset( $context['occurred_at_gmt'] );
				if ( $trusted_backfill && ! $occurred_raw && isset( $payload['occurred_at_gmt'] ) ) {
					$occurred_raw = $payload['occurred_at_gmt'];
				}
				if ( $trusted_backfill && is_string( $occurred_raw ) ) {
					$date   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $occurred_raw, new DateTimeZone( 'UTC' ) );
					$errors = DateTimeImmutable::getLastErrors();
					$valid  = $date instanceof DateTimeImmutable && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( 'Y-m-d H:i:s' ) === $occurred_raw;
					if ( $valid ) {
						$occurred_at          = $date->format( 'Y-m-d H:i:s' );
						$timestamp_quality    = sanitize_key( $context['timestamp_quality'] ?? $context['quality'] ?? 'historical' );
						$timestamp_provenance = sanitize_key( $context['timestamp_provenance'] ?? 'trusted_backfill' );
					} else {
						$occurred_at = $now;
					}
				} else {
					$occurred_at = $now;
				}
				$dedupe_source   = ! empty( $payload['dedupe_key'] ) ? array( $integration, $event, sanitize_text_field( (string) $payload['dedupe_key'] ) ) : array( $integration, $event, $payload, $context, microtime( true ), wp_rand() );
				$dedupe          = $this->dedupe_key( wp_json_encode( $dedupe_source ) );
				$safe_payload    = array();
				$allowed_payload = array( 'value', 'amount', 'quantity', 'currency', 'products', 'order_id', 'user_id', 'dimensions', 'occurred_at_gmt', 'gateway', 'gateway_key', 'payment_status', 'product_id', 'related_product_id', 'page_type', 'zero_result', 'cart_size', 'duration', 'duration_ms', 'outcome', 'target', 'object_id', 'source', 'tokens', 'cost', 'status', 'stage', 'query', 'result_count' );
				foreach ( $payload as $payload_key => $payload_value ) {
					$payload_key = sanitize_key( $payload_key );
					if ( ! in_array( $payload_key, $allowed_payload, true ) || in_array( $payload_key, array( 'prompt', 'response', 'email', 'billing', 'cart', 'description', 'title', 'url', 'referrer', 'user_agent', 'ip', 'session_key', 'customer_id' ), true ) ) {
						continue;
					} $safe_payload[ $payload_key ] = is_scalar( $payload_value ) ? sanitize_text_field( (string) $payload_value ) : $payload_value; }
				$dimensions     = $this->normalize_dimensions( $payload['dimensions'] ?? array() );
				$dimension_hash = hash( 'sha256', wp_json_encode( $dimensions ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
				$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (integration,event_key,occurred_at_gmt,recorded_at_gmt,timestamp_quality,timestamp_provenance,dedupe_key,dimension_hash,session_hash,identity_provider,persistence,identity_quality,user_id,user_role,currency,dimensions,values_json) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%s)", $integration, $event, $occurred_at, $now, $timestamp_quality, $timestamp_provenance, $dedupe, $dimension_hash, $identity['session_hash'] ?? '', $identity['provider'] ?? '', $identity['persistence'] ?? 'none', $identity['quality'] ?? 'unknown', absint( $identity['user_id'] ?? $payload['user_id'] ?? 0 ), $identity['role'] ?? '', $payload['currency'] ?? '', wp_json_encode( $dimensions ), wp_json_encode( $safe_payload ) ) );
				$event_id = (int) $wpdb->insert_id;
				if ( ! $event_id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; values are prepared.
					$event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE integration=%s AND dedupe_key=%s", $integration, $dedupe ) );
				}
				$this->index_dimensions( $integration, $dimension_hash, $dimensions, $occurred_at );
				foreach ( $eligible_metrics as $metric ) {
					$metric_def = (array) ( $def['metrics'][ $metric ] ?? array() );
					if ( $event_id ) {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier; all values are prepared.
							$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}ic_stats_event_metrics (event_id,metric) VALUES (%d,%s)", $event_id, $metric ) ); }
				}
				try {
					do_action( 'ic_stats_event_recorded', $integration, $event, $payload, $context );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
					if ( $owns_transaction && false === $wpdb->query( 'COMMIT' ) ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
						$wpdb->query( 'ROLLBACK' );
						return 0;
					}
				} catch ( Throwable $exception ) {
					if ( ! $owns_transaction ) {
						throw $exception;
					}
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
					if ( $owns_transaction ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
						$wpdb->query( 'ROLLBACK' );
					}
					return 0;
				}
			}
			return $count;
		}

		/**
		 * Record a capped batch in one database transaction.
		 *
		 * @param string $integration Integration key.
		 * @param array  $records     Event records.
		 * @return array Accepted and rejected counts.
		 * @throws RuntimeException When a batch operation cannot complete.
		 */
		public function record_batch( $integration, $records ) {
			global $wpdb;
			$accepted = 0;
			$rejected = 0;
			$records  = array_slice( is_array( $records ) ? $records : array(), 0, 100 );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional batch transaction boundary.
			$wpdb->query( 'START TRANSACTION' );
			try {
				foreach ( $records as $record ) {
					if ( ! is_array( $record ) || empty( $record['event'] ) ) {
						++$rejected;
						continue;
					}
					$context                       = (array) ( $record['context'] ?? array() );
					$context['_batch_transaction'] = true;
					$result                        = $this->record( $integration, sanitize_key( $record['event'] ), (array) ( $record['payload'] ?? array() ), $context );
					$result ? ++$accepted : ++$rejected;
					if ( ! empty( $wpdb->last_error ) ) {
						throw new RuntimeException( 'Stats batch database failure.' );
					}
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional batch transaction boundary.
				if ( false === $wpdb->query( 'COMMIT' ) ) {
					throw new RuntimeException( 'Stats batch commit failure.' );
				}
			} catch ( Throwable $exception ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional batch rollback.
				$wpdb->query( 'ROLLBACK' );
				return array(
					'accepted' => 0,
					'rejected' => count( $records ),
				);
			}
			return array(
				'accepted' => $accepted,
				'rejected' => $rejected,
			);
		}
		/**
		 * Normalize dimensions into a stable scalar map.
		 *
		 * @param array $dimensions Raw dimensions.
		 * @return array
		 */
		public function normalize_dimensions( $dimensions ) {
			$dimensions = is_array( $dimensions ) ? $dimensions : array();
			$out        = array();
			foreach ( $dimensions as $key => $value ) {
				$key = sanitize_key( $key );
				if ( ! $key || is_array( $value ) || is_object( $value ) ) {
					continue;
				} $out[ $key ] = sanitize_text_field( (string) $value ); }
			ksort( $out );
			return $out;
		}
		/**
		 * Persist the bounded scalar dimension dictionary used by filters.
		 *
		 * @param string $integration Integration key.
		 * @param string $hash        Dimension hash.
		 * @param array  $dimensions  Normalized dimensions.
		 * @param string $occurred_at Event timestamp.
		 * @return void
		 */
		private function index_dimensions( $integration, $hash, $dimensions, $occurred_at ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ic_stats_dimensions';
			foreach ( (array) $dimensions as $key => $value ) {
				$value = substr( sanitize_text_field( (string) $value ), 0, 191 );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table; values are prepared.
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (integration,dimension_hash,dimension_key,dimension_value,first_seen_gmt,last_seen_gmt) VALUES (%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE dimension_value=VALUES(dimension_value),last_seen_gmt=GREATEST(last_seen_gmt,VALUES(last_seen_gmt))", sanitize_key( $integration ), $hash, sanitize_key( $key ), $value, $occurred_at, $occurred_at ) );
			}
		}
		/**
		 * Create an HMAC dedupe key.
		 *
		 * @param string $value Value to hash.
		 * @return string
		 */
		public function dedupe_key( $value ) {
			return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) ); }
		/**
		 * Return all registered integrations.
		 *
		 * @return array
		 */
		public function integrations() {
			return $this->integrations; }

		/**
		 * Return diagnostics for the most recently completed projection batch.
		 *
		 * @return array Selected and finalized bridge counts.
		 */
		public function last_process_stats() {
			return $this->last_process_stats;
		}

		/**
		 * Process a bounded batch of metric bridges with expiring leases.
		 *
		 * @param int $limit         Maximum rows to process.
		 * @param int $lease_seconds Lease duration in seconds.
		 * @return int
		 */
		public function process( $limit = 100, $lease_seconds = 300 ) {
			global $wpdb;
			$limit = max( 1, min( 500, absint( $limit ) ) );
			$now   = current_time( 'mysql', true );
			$until = gmdate( 'Y-m-d H:i:s', time() + max( 30, absint( $lease_seconds ) ) );
			$table = $wpdb->prefix . 'ic_stats_event_metrics';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
			$rows                     = $wpdb->get_results( $wpdb->prepare( "SELECT event_id,metric FROM {$table} WHERE processed=0 AND (lease_until IS NULL OR lease_until < %s) ORDER BY event_id ASC LIMIT %d", $now, $limit ), ARRAY_A );
			$processed                = 0;
			$finalized                = 0;
			$this->last_process_stats = array(
				'selected'  => count( (array) $rows ),
				'finalized' => 0,
			);
			$changed_integrations     = array();
			foreach ( $rows as $row ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
				$wpdb->query( 'START TRANSACTION' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
				$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET lease_until=%s, attempts=attempts+1 WHERE event_id=%d AND metric=%s AND processed=0 AND (lease_until IS NULL OR lease_until < %s)", $until, absint( $row['event_id'] ), sanitize_key( $row['metric'] ), $now ) );
				if ( 1 !== $claimed ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction rollback is intentionally direct.
					$wpdb->query( 'ROLLBACK' );
					continue; }
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier; all values are prepared.
				$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'ic_stats_events WHERE id=%d', absint( $row['event_id'] ) ), ARRAY_A );
				if ( ! $event ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
					$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET processed=1,lease_until=NULL WHERE event_id=%d AND metric=%s", absint( $row['event_id'] ), sanitize_key( $row['metric'] ) ) );
					if ( 1 !== $updated ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction rollback is intentionally direct.
						$wpdb->query( 'ROLLBACK' );
						continue;
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
					$wpdb->query( 'COMMIT' );
					++$finalized;
					continue; }
				$dimensions    = json_decode( (string) $event['dimensions'], true );
				$dimensions    = $this->normalize_dimensions( $dimensions );
				$aggregate     = $wpdb->prefix . 'ic_stats_aggregates';
				$values        = json_decode( (string) $event['values_json'], true );
				$values        = is_array( $values ) ? $values : array();
				$amount        = isset( $values['amount'] ) ? (float) $values['amount'] : 1.0;
				$quantity      = isset( $values['quantity'] ) ? (float) $values['quantity'] : 1.0;
				$metric_def    = $this->integrations[ $event['integration'] ]['metrics'][ $row['metric'] ] ?? array();
				$contributions = array(
					array(
						'value'      => 'sum' === ( $metric_def['calculation'] ?? '' ) ? ( isset( $values['quantity'] ) && ! isset( $values['amount'] ) ? $quantity : $amount ) : ( 'average' === ( $metric_def['calculation'] ?? '' ) ? $amount : ( 'count' === ( $metric_def['calculation'] ?? 'count' ) ? 1 : $quantity ) ),
						'count'      => 1,
						'currency'   => $event['currency'],
						'dimensions' => $dimensions,
					),
				);
				if ( isset( $metric_def['projector'] ) && is_callable( $metric_def['projector'] ) ) {
					$contributions = (array) call_user_func( $metric_def['projector'], $event, $values, $metric_def, $this ); }
				$contributions = apply_filters( 'ic_stats_metric_contributions', $contributions, $event['integration'], $row['metric'], $event, $values, $metric_def, $this );
				if ( ! $contributions ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
					$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET processed=1,lease_until=NULL,last_error=NULL WHERE event_id=%d AND metric=%s", absint( $row['event_id'] ), sanitize_key( $row['metric'] ) ) );
					if ( 1 !== $updated ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction rollback is intentionally direct.
						$wpdb->query( 'ROLLBACK' );
						continue;
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
					$wpdb->query( 'COMMIT' );
					++$finalized;
					continue; }
				$aggregate_work = array();
				$fact_work      = array();
				foreach ( $contributions as $contribution ) {
					if ( ! is_array( $contribution ) ) {
						continue;
					}
					$aggregate_value         = (float) ( $contribution['value'] ?? 0 );
					$aggregate_count         = max( 0, absint( $contribution['count'] ?? 1 ) );
					$contribution_dimensions = $this->normalize_dimensions( array_merge( $dimensions, (array) ( $contribution['dimensions'] ?? array() ) ) );
					$contribution_currency   = sanitize_text_field( $contribution['currency'] ?? $event['currency'] );
					$hash                    = hash( 'sha256', wp_json_encode( $contribution_dimensions ) );
					$work_key                = $hash . ':' . $contribution_currency;
					$fact_work[ $hash ]      = $contribution_dimensions;
					if ( ! isset( $aggregate_work[ $work_key ] ) ) {
						$aggregate_work[ $work_key ] = array(
							'dimension_hash' => $hash,
							'dimensions'     => $contribution_dimensions,
							'currency'       => $contribution_currency,
							'value'          => 0.0,
							'count'          => 0,
						);
					}
					$aggregate_work[ $work_key ]['value'] += $aggregate_value;
					$aggregate_work[ $work_key ]['count'] += $aggregate_count;
				}
				$fact_results = array();
				foreach ( $fact_work as $hash => $contribution_dimensions ) {
					$this->index_dimensions( $event['integration'], $hash, $contribution_dimensions, $event['occurred_at_gmt'] );
					$fact_results[ $hash ] = $this->record_unique_fact( $event, $row['metric'], $metric_def, $contribution_dimensions, $hash );
				}
				foreach ( $aggregate_work as $work ) {
					$hash = $work['dimension_hash'];
					if ( ! empty( $metric_def['fact_mode'] ) && empty( $fact_results[ $hash ]['countable'] ) ) {
						continue;
					}
					foreach ( array(
						'hour'  => 'Y-m-d H:00:00',
						'day'   => 'Y-m-d 00:00:00',
						'month' => 'Y-m-01 00:00:00',
					) as $aggregate_bucket => $format ) {
						$bucket_start = gmdate( $format, strtotime( $event['occurred_at_gmt'] ) );
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
						$wpdb->query( $wpdb->prepare( "INSERT INTO {$aggregate} (integration,metric,bucket,bucket_start,dimension_hash,dimensions,currency,value,count) VALUES (%s,%s,%s,%s,%s,%s,%s,%f,%d) ON DUPLICATE KEY UPDATE value=value+VALUES(value),count=count+VALUES(count)", $event['integration'], $row['metric'], $aggregate_bucket, $bucket_start, $hash, wp_json_encode( $work['dimensions'] ), $work['currency'], $work['value'], $work['count'] ) );
					}
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET processed=1,lease_until=NULL,last_error=NULL WHERE event_id=%d AND metric=%s", absint( $row['event_id'] ), sanitize_key( $row['metric'] ) ) );
				if ( ! empty( $wpdb->last_error ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction rollback is intentionally direct.
					$wpdb->query( 'ROLLBACK' );
					continue;
				}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control is intentionally direct.
				$wpdb->query( 'COMMIT' );
				++$processed;
				++$finalized;
				$changed_integrations[ (string) $event['integration'] ] = true;
			}
			foreach ( array_keys( $changed_integrations ) as $changed_integration ) {
				do_action( 'ic_stats_aggregates_changed', $changed_integration, 'worker_process' );
			}
			/** Allow independently loaded modules to process their bounded work. */
			do_action( 'ic_stats_process_extension', $limit, $this );
			foreach ( array_keys( $this->integrations ) as $integration ) {
				$option = 'ic_stats_' . sanitize_key( $integration ) . '_projection_status';
				$status = get_option( $option, array() );
				$state  = (string) ( is_array( $status ) ? ( $status['state'] ?? '' ) : '' );
				// A scheduled projection is written as `pending` and nothing ever
				// moved it on, so the completion check below could never fire and the
				// screen reported "still running" forever. Promote it as soon as no
				// backfill request is still dispatching rows for this integration.
				if ( 'pending' === $state ) {
					$request = get_option( 'ic_stats_' . sanitize_key( $integration ) . '_backfill_request', array() );
					if ( ! is_array( $request ) || ! in_array( (string) ( $request['state'] ?? '' ), array( 'pending', 'process' ), true ) ) {
						$state = 'process';
						update_option(
							$option,
							array_merge(
								is_array( $status ) ? $status : array(),
								array(
									'state'          => 'process',
									'updated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
								)
							),
							false
						);
					}
				}
				if ( 'process' !== $state ) {
					continue; }
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Projection completion checks use a bounded framework-owned query.
				$pending = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'ic_stats_event_metrics em INNER JOIN ' . $wpdb->prefix . 'ic_stats_events e ON e.id=em.event_id WHERE e.integration=%s AND em.processed=0', $integration ) );
				if ( 0 === $pending ) {
					update_option(
						'ic_stats_' . sanitize_key( $integration ) . '_projection_status',
						array(
							'state'          => 'complete',
							'version'        => max( 1, absint( $this->integrations[ $integration ]['projection_version'] ?? 4 ) ),
							'updated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
						),
						false
					); }
			}
			$this->last_process_stats['finalized'] = $finalized;
			return $processed;
		}

		/**
		 * Persist exact identity/visit facts and decide whether this event is countable.
		 *
		 * CONTRACT: `process()` calls this ONLY after `ic_stats_metric_contributions`
		 * has confirmed the event contributes to the metric. Do not call it for an
		 * event a metric rejects -- the fact rows written here are the read path for
		 * every `distinct_by` metric, so a fact recorded for a rejected event makes
		 * the reported distinct count wider than the metric's own definition.
		 *
		 * @param array  $event         Event row.
		 * @param string $metric        Metric key.
		 * @param array  $metric_def     Metric definition.
		 * @param array  $dimensions     Normalized event-level dimensions. Also the
		 *                               channel an erased identity travels on: a
		 *                               `removed:<token>` `user_id` with `user_id`
		 *                               0 on the event row.
		 * @param string $dimension_hash Dimension hash.
		 * @return array Fact result.
		 */
		private function record_unique_fact( $event, $metric, $metric_def, $dimensions, $dimension_hash ) {
			global $wpdb;
			$distinct = sanitize_key( $metric_def['distinct_by'] ?? '' );
			$mode     = sanitize_key( $metric_def['fact_mode'] ?? '' );
			if ( ! $distinct && ! $mode ) {
				return array( 'countable' => true );
			}
			$user_id = absint( $event['user_id'] ?? 0 );
			$session = sanitize_text_field( (string) ( $event['session_hash'] ?? '' ) );
			// An erased customer has no `user_id` any more -- the identity travels in
			// the event-level dimensions as `removed:<token>`, written from the
			// severed order. Keying the fact on `dedupe_key( 'removed:<token>' )` is
			// what makes an anonymised row and a rebuilt row land on the SAME
			// identity for the same person (`proposals.md` section 19). The manager
			// can load before `stats/functions.php`, hence the guard.
			$dimension_identity = isset( $dimensions['user_id'] ) && is_scalar( $dimensions['user_id'] ) ? (string) $dimensions['user_id'] : '';
			$removed            = function_exists( 'ic_stats_is_removed_identity' ) && ic_stats_is_removed_identity( $dimension_identity ) ? $dimension_identity : '';
			$identity           = $user_id ? $this->dedupe_key( 'user:' . $user_id ) : ( $removed ? $this->dedupe_key( $removed ) : $session );
			// A removed identity is NOT a guest. Without the second half of this
			// guard an erased customer silently reappears in `guest_order_count`.
			if ( ! $identity || ( 0 === strpos( $mode, 'guest_' ) && ( $user_id || $removed ) ) ) {
				return array( 'countable' => false );
			}
			$table     = $wpdb->prefix . 'ic_stats_unique_facts';
			$occurred  = (string) $event['occurred_at_gmt'];
			$period    = gmdate( 'Y-m-d', strtotime( $occurred ) );
			$visit     = '';
			$returning = false;
			if ( 'visit_hash' === $distinct || false !== strpos( $mode, 'guest_visit' ) ) {
				$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( $occurred ) - ( 30 * MINUTE_IN_SECONDS ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Exact visit lookup uses a framework table and prepared identity.
				$prior = $wpdb->get_row( $wpdb->prepare( "SELECT visit_hash,last_seen_gmt FROM {$table} WHERE integration=%s AND metric=%s AND identity_hash=%s AND last_seen_gmt<%s ORDER BY last_seen_gmt DESC LIMIT 1", $event['integration'], '_guest_visit', $identity, $occurred ), ARRAY_A );
				if ( $prior && $prior['last_seen_gmt'] >= $cutoff ) {
					$visit = $prior['visit_hash'];
				} else {
					$returning = (bool) $prior;
					$visit     = $this->dedupe_key( $identity . ':' . $occurred );
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned exact visit fact; all values are prepared.
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (integration,metric,period,dimension_hash,identity_hash,visit_hash,first_seen_gmt,last_seen_gmt,occurrence_count) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,1) ON DUPLICATE KEY UPDATE last_seen_gmt=GREATEST(last_seen_gmt,VALUES(last_seen_gmt)),occurrence_count=occurrence_count+1", $event['integration'], '_guest_visit', $period, $dimension_hash, $identity, $visit, $occurred, $occurred ) );
			}
			if ( 'returning_identity' === $mode ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Exact identity history lookup uses prepared values.
				$returning = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE integration=%s AND metric=%s AND identity_hash=%s AND first_seen_gmt<%s LIMIT 1", $event['integration'], 'unique_registered_customers', $identity, $occurred ) );
			}
			if ( in_array( $mode, array( 'returning_guest_visit', 'returning_identity' ), true ) && ! $returning ) {
				return array(
					'countable'  => false,
					'returning'  => false,
					'visit_hash' => $visit,
				);
			}
			$fact_visit = 'visit_hash' === $distinct ? $visit : '';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned unique fact; all values are prepared.
			$inserted  = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (integration,metric,period,dimension_hash,identity_hash,visit_hash,first_seen_gmt,last_seen_gmt,occurrence_count) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,1) ON DUPLICATE KEY UPDATE last_seen_gmt=GREATEST(last_seen_gmt,VALUES(last_seen_gmt)),occurrence_count=occurrence_count+1", $event['integration'], $metric, $period, $dimension_hash, $identity, $fact_visit, $occurred, $occurred ) );
			$countable = 1 === $inserted;
			return array(
				'countable'  => $countable,
				'returning'  => $returning,
				'visit_hash' => $visit,
			);
		}


		/**
		 * Remove records older than the configured bounded retention period.
		 *
		 * An event is only expired once every one of its metric bridges has been
		 * projected. Maintenance processes a bounded batch and then retains in the
		 * same pass, so a backfilled historical event is outside the window the
		 * instant it is written; without this guard retention destroys events far
		 * faster than the worker can consume them. Raw events still expire the
		 * configured number of days after they are projected.
		 *
		 * Aggregate buckets and distinct-identity facts have their own horizons,
		 * both filterable and both defaulting to "never purge" for the tiers
		 * that carry history: see `ic_stats_aggregate_retention` and
		 * `ic_stats_unique_fact_retention` below.
		 *
		 * @param int $days  Retention period in days.
		 * @param int $limit Maximum event rows to delete.
		 * @return int
		 */
		public function retain( $days = 90, $limit = 500 ) {
			global $wpdb;
			$days   = max( 1, min( 3650, absint( $days ) ) );
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );
			$events = $wpdb->prefix . 'ic_stats_events';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT e.id FROM {$events} e WHERE e.occurred_at_gmt < %s AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}ic_stats_event_metrics em WHERE em.event_id = e.id AND em.processed = 0) ORDER BY e.id ASC LIMIT %d", $cutoff, max( 1, min( 2000, absint( $limit ) ) ) ) );
			foreach ( array_chunk( array_map( 'absint', $ids ), 250 ) as $chunk ) {
				$marks = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded numeric id list uses prepared placeholders.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ic_stats_event_metrics WHERE event_id IN ({$marks})", $chunk ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded numeric id list uses prepared placeholders.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE id IN ({$marks})", $chunk ) );
			}
			do_action( 'ic_stats_retention_extension', $limit, $this );
			$aggregate = $wpdb->prefix . 'ic_stats_aggregates';
			/**
			 * Filters how long each aggregate bucket is retained.
			 *
			 * Values are relative `strtotime()` expressions. A `0` or otherwise
			 * empty value means that bucket is never purged; month rows are one
			 * row per metric, period and dimension, so full history is kept.
			 *
			 * @param array $retention Bucket key => relative retention expression.
			 */
			$retention = (array) apply_filters(
				'ic_stats_aggregate_retention',
				array(
					'hour'  => '-90 days',
					'day'   => '-25 months',
					'month' => 0,
				)
			);
			$clauses   = array();
			$values    = array();
			foreach ( $retention as $bucket => $expression ) {
				$bucket = sanitize_key( $bucket );
				if ( ! $bucket || empty( $expression ) ) {
					continue; }
				$bucket_cutoff = strtotime( (string) $expression );
				if ( ! $bucket_cutoff ) {
					continue; }
				$clauses[] = '(bucket=%s AND bucket_start < %s)';
				$values[]  = $bucket;
				$values[]  = gmdate( 'Y-m-d H:i:s', $bucket_cutoff );
			}
			if ( $clauses ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the clause list is built from fixed %s placeholders the sniff cannot see through the implode(), and every value is prepared.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$aggregate} WHERE " . implode( ' OR ', $clauses ), $values ) );
			}
			/**
			 * Filters how long distinct-identity facts are retained.
			 *
			 * The value is a relative `strtotime()` expression. A `0` or
			 * otherwise empty value -- the default -- means facts are never
			 * purged, so a distinct-identity metric such as
			 * `unique_registered_customers` keeps answering for the whole of a
			 * site's history instead of silently decaying towards the size of a
			 * rolling window. This is deliberately a sibling of
			 * `ic_stats_aggregate_retention` rather than a key inside it: that
			 * filter is a bucket => expression map consumed by a bucketed
			 * `bucket=%s` DELETE against a different table, and a consumer may
			 * add or drop buckets there, so a non-bucket key would have to be
			 * special-cased by every such consumer.
			 *
			 * @param string|int $retention Relative retention expression, or 0 to never purge.
			 */
			$fact_retention = apply_filters( 'ic_stats_unique_fact_retention', 0 );
			$fact_cutoff    = empty( $fact_retention ) ? false : strtotime( (string) $fact_retention );
			if ( $fact_cutoff ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded fixed-table retention query.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ic_stats_unique_facts WHERE last_seen_gmt < %s LIMIT 2000", gmdate( 'Y-m-d H:i:s', $fact_cutoff ) ) );
			}
			return count( $ids );
		}

		/**
		 * Rewrite one identity out of the retained statistics, one bounded batch.
		 *
		 * THE RULE: numbers are retained, identity is destroyed. Erasing a customer
		 * must not move a single total, so aggregates and facts survive with the
		 * identity rewritten and only the raw events are deleted.
		 *
		 * One call advances the job by at most one batch of the CURRENT stage and
		 * returns the updated job; the caller persists it. Stages run in order:
		 * `events` -> `map` -> `aggregates` -> `facts` -> `extensions` -> `cleanup`
		 * -> `complete`. Every stage is restartable: a job interrupted mid-stage
		 * resumes from its own cursor on the next pass.
		 *
		 * It lives on the manager rather than in `functions.php` because it needs
		 * `normalize_dimensions()` and the private `index_dimensions()` -- the two
		 * pieces that decide what a dimension hash IS. Reimplementing either
		 * outside this class would let the rewritten hash drift from the recorded
		 * one, which is the one failure this whole engine cannot tolerate.
		 *
		 * There is NO job secret. The surrogate is derived from the per-user
		 * removal token carried on the job (and, later, on the severed order), so a
		 * purge-and-rebuild re-derives the SAME identity instead of resurrecting
		 * the real one. See `proposals.md` section 19.
		 *
		 * @param array $job   Job record.
		 * @param int   $limit Maximum rows for this batch.
		 * @return array Updated job record.
		 * @throws Exception When no cryptographic random source is available.
		 */
		public function anonymise_identity( $job, $limit = 200 ) {
			$job  = is_array( $job ) ? $job : array();
			$job += array(
				'user_id'       => 0,
				'identity_hash' => '',
				'token'         => '',
				'stage'         => 'events',
				'cursor'        => 0,
				'hash_map'      => array(),
				'integrations'  => array(),
				'attempts'      => 0,
				'created_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			);

			$job['user_id']      = absint( $job['user_id'] );
			$job['hash_map']     = (array) $job['hash_map'];
			$job['integrations'] = (array) $job['integrations'];
			$limit               = max( 1, min( 1000, absint( $limit ) ) );
			if ( ! $job['user_id'] ) {
				$job['stage'] = 'complete';
				return $job;
			}
			// The token is the secret. A job that reached the manager without one
			// would key `visit_hash` under an empty HMAC key, so mint one here
			// rather than silently degrade; `ic_stats_anonymise_user()` normally
			// supplies it, and the order side passes its own in.
			if ( '' === (string) $job['token'] ) {
				$job['token'] = bin2hex( random_bytes( 6 ) );
			}
			if ( '' === (string) $job['identity_hash'] ) {
				$job['identity_hash'] = $this->dedupe_key( 'user:' . $job['user_id'] );
			}
			switch ( (string) $job['stage'] ) {
				case 'events':
					return $this->anonymise_events( $job, $limit );
				case 'map':
					return $this->anonymise_map( $job, $limit );
				case 'aggregates':
					return $this->anonymise_aggregates( $job, $limit );
				case 'facts':
					return $this->anonymise_facts( $job, $limit );
				case 'extensions':
					return (array) apply_filters( 'ic_stats_anonymise_extension', array_merge( $job, array( 'stage' => 'cleanup' ) ), $limit, $this );
				case 'cleanup':
					return $this->anonymise_cleanup( $job, $limit );
			}
			$job['stage'] = 'complete';
			return $job;
		}

		/**
		 * Resolve the value that stands in for the erased identity.
		 *
		 * @param int    $user_id Subject user identifier.
		 * @param string $token   Per-user removal token.
		 * @return string
		 */
		private function removed_identity_value( $user_id, $token ) {
			$token = (string) $token;
			if ( function_exists( 'ic_stats_removed_identity_value' ) ) {
				return (string) ic_stats_removed_identity_value( $user_id, $token );
			}
			return '' === $token ? 'removed' : 'removed:' . $token;
		}

		/**
		 * Stage `events`: project pending bridges, then delete the subject's events.
		 *
		 * `process()` runs FIRST, up to five passes, so an event whose metric bridge
		 * has not been projected yet becomes an aggregate BEFORE it is destroyed --
		 * the same ordering guarantee `retain()` carries. After five passes the
		 * events are deleted anyway; the pass count stays on the job so the eraser
		 * can report it.
		 *
		 * Deletion is deliberately NOT scoped to one integration: the tables are
		 * shared, and an erasure that only cleared the calling plugin's rows would
		 * leave the subject's identity in every other integration's events.
		 *
		 * @param array $job   Job record.
		 * @param int   $limit Maximum rows for this batch.
		 * @return array
		 */
		private function anonymise_events( $job, $limit ) {
			global $wpdb;
			$user_id = absint( $job['user_id'] );
			$events  = $wpdb->prefix . 'ic_stats_events';
			$bridge  = $wpdb->prefix . 'ic_stats_event_metrics';
			$passes  = 0;
			while ( $passes < 5 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifiers; the subject id is prepared.
				$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bridge} em INNER JOIN {$events} e ON e.id=em.event_id WHERE e.user_id=%d AND em.processed=0", $user_id ) );
				if ( ! $pending ) {
					break;
				}
				++$passes;
				$this->process( $limit );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,integration FROM {$events} WHERE user_id=%d ORDER BY id ASC LIMIT %d", $user_id, $limit ), ARRAY_A );
			$rows = is_array( $rows ) ? $rows : array();
			$ids  = array();
			foreach ( $rows as $row ) {
				$ids[] = absint( $row['id'] );
				$job['integrations'][ sanitize_key( $row['integration'] ) ] = true;
			}
			foreach ( array_chunk( $ids, 250 ) as $chunk ) {
				$marks = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded numeric id list uses prepared placeholders.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$bridge} WHERE event_id IN ({$marks})", $chunk ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Bounded numeric id list uses prepared placeholders.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE id IN ({$marks})", $chunk ) );
			}
			$job['projection_passes'] = absint( $job['projection_passes'] ?? 0 ) + $passes;
			$job['events_deleted']    = absint( $job['events_deleted'] ?? 0 ) + count( $ids );
			if ( count( $ids ) < $limit ) {
				$job['stage']  = 'map';
				$job['cursor'] = '';
			}
			return $job;
		}

		/**
		 * Stage `map`: build the old dimension hash => new dimension hash map.
		 *
		 * Candidates are found through `ic_stats_dimensions KEY value_lookup`, which
		 * exists precisely so this is an index lookup. Nothing here scans JSON: a
		 * `JSON_EXTRACT` sweep over the aggregate table is a full scan, and it runs
		 * ONCE, in `cleanup`, only to catch rows whose index entries were pruned.
		 *
		 * A dimension hash is the sha256 of the encoded dimension set, so all rows
		 * sharing a hash share a dimension set; reading one integration's rows is
		 * enough to reconstruct it.
		 *
		 * @param array $job   Job record.
		 * @param int   $limit Maximum rows for this batch.
		 * @return array
		 */
		private function anonymise_map( $job, $limit ) {
			global $wpdb;
			$user_id = absint( $job['user_id'] );
			$removed = $this->removed_identity_value( $user_id, $job['token'] );
			$dims    = $wpdb->prefix . 'ic_stats_dimensions';
			$cursor  = (string) ( $job['cursor'] ?? '' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared and the lookup rides KEY value_lookup.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT dimension_hash,MIN(integration) AS integration FROM {$dims} WHERE dimension_key=%s AND dimension_value=%s AND dimension_hash>%s GROUP BY dimension_hash ORDER BY dimension_hash ASC LIMIT %d", 'user_id', (string) $user_id, $cursor, $limit ), ARRAY_A );
			$rows = is_array( $rows ) ? $rows : array();
			foreach ( $rows as $row ) {
				$hash        = (string) $row['dimension_hash'];
				$integration = sanitize_key( $row['integration'] );
				$cursor      = $hash;

				$job['integrations'][ $integration ] = true;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the lookup rides the primary key.
				$set = $wpdb->get_results( $wpdb->prepare( "SELECT dimension_key,dimension_value FROM {$dims} WHERE integration=%s AND dimension_hash=%s", $integration, $hash ), ARRAY_A );
				$set = is_array( $set ) ? $set : array();
				if ( ! $set ) {
					continue;
				}
				$decoded = array();
				foreach ( $set as $pair ) {
					$decoded[ (string) $pair['dimension_key'] ] = (string) $pair['dimension_value'];
				}
				$decoded['user_id']       = $removed;
				$new_dimensions           = $this->normalize_dimensions( $decoded );
				$job['hash_map'][ $hash ] = hash( 'sha256', wp_json_encode( $new_dimensions ) );
			}
			$job['cursor'] = $cursor;
			if ( count( $rows ) < $limit ) {
				$job['stage']  = 'aggregates';
				$job['cursor'] = 0;
			}
			return $job;
		}

		/**
		 * Stage `aggregates`: rewrite the subject's aggregate rows, merging collisions.
		 *
		 * @param array $job   Job record.
		 * @param int   $limit Maximum rows for this batch.
		 * @return array
		 */
		private function anonymise_aggregates( $job, $limit ) {
			global $wpdb;
			$hashes = array_keys( (array) $job['hash_map'] );
			if ( ! $hashes ) {
				$job['stage']  = 'facts';
				$job['cursor'] = 0;
				return $job;
			}
			$aggregates = $wpdb->prefix . 'ic_stats_aggregates';
			$cursor     = absint( $job['cursor'] ?? 0 );
			$rows       = array();
			// The hash list is bounded by the number of distinct dimension SETS the
			// subject appears in, not by row count, so it is small in practice; it is
			// still chunked so one pathological job cannot build an unbounded IN list.
			foreach ( array_chunk( $hashes, 200 ) as $chunk ) {
				$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
				$args  = array_merge( array( $cursor ), $chunk, array( $limit ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the hash list is fixed %s placeholders and every value is prepared. The sniff cannot count placeholders behind the implode() nor see that the replacements arrive as one args ARRAY, which wpdb::prepare() unpacks.
				$found = $wpdb->get_results( $wpdb->prepare( "SELECT id,integration,metric,bucket,bucket_start,dimension_hash,dimensions,currency,value,count FROM {$aggregates} WHERE id>%d AND dimension_hash IN ({$marks}) ORDER BY id ASC LIMIT %d", $args ), ARRAY_A );
				$rows  = array_merge( $rows, is_array( $found ) ? $found : array() );
			}
			usort(
				$rows,
				function ( $left, $right ) {
					return absint( $left['id'] ) <=> absint( $right['id'] );
				}
			);
			// Chunks are ordered independently, so the batch is re-sorted and trimmed
			// before the cursor moves; anything past the trim is picked up next pass.
			$rows = array_slice( $rows, 0, $limit );
			foreach ( $rows as $row ) {
				$cursor = absint( $row['id'] );
				$job    = $this->anonymise_aggregate_row( $job, $row );
			}
			$job['cursor'] = $cursor;
			if ( count( $rows ) < $limit ) {
				$job['stage']  = 'facts';
				$job['cursor'] = 0;
			}
			return $job;
		}

		/**
		 * Rewrite one aggregate row onto the removed identity.
		 *
		 * The row's OWN `dimensions` JSON is authoritative -- never reconstruct it
		 * from `ic_stats_dimensions`, which is a lossy dictionary that a retention
		 * pass may have pruned. The write is an upsert that SUMS `value` and
		 * `count`: two rows of the same subject can collide once `user_id` is
		 * rewritten, and overwriting one with the other would silently lower a
		 * total. `user_role` is deliberately kept: low cardinality, not
		 * identifying, and dropping it would merge rows across roles.
		 *
		 * @param array $job Job record.
		 * @param array $row Aggregate row.
		 * @return array
		 */
		private function anonymise_aggregate_row( $job, $row ) {
			global $wpdb;
			$user_id = absint( $job['user_id'] );
			$decoded = json_decode( (string) $row['dimensions'], true );
			$decoded = is_array( $decoded ) ? $decoded : array();
			if ( ! isset( $decoded['user_id'] ) || (string) $decoded['user_id'] !== (string) $user_id ) {
				return $job;
			}
			$decoded['user_id'] = $this->removed_identity_value( $user_id, $job['token'] );
			$new_dimensions     = $this->normalize_dimensions( $decoded );
			$new_hash           = hash( 'sha256', wp_json_encode( $new_dimensions ) );
			$integration        = sanitize_key( $row['integration'] );
			$job['hash_map'][ (string) $row['dimension_hash'] ] = $new_hash;
			$job['integrations'][ $integration ]                = true;
			$aggregates = $wpdb->prefix . 'ic_stats_aggregates';
			$rewrite    = function () use ( $wpdb, $aggregates, $row, $integration, $new_hash, $new_dimensions ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$aggregates} (integration,metric,bucket,bucket_start,dimension_hash,dimensions,currency,value,count) VALUES (%s,%s,%s,%s,%s,%s,%s,%f,%d) ON DUPLICATE KEY UPDATE value=value+VALUES(value),count=count+VALUES(count)", $integration, sanitize_key( $row['metric'] ), sanitize_key( $row['bucket'] ), (string) $row['bucket_start'], $new_hash, wp_json_encode( $new_dimensions ), sanitize_text_field( (string) $row['currency'] ), (float) $row['value'], absint( $row['count'] ) ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the id is prepared.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$aggregates} WHERE id=%d", absint( $row['id'] ) ) );
				return true;
			};
			if ( ! IC_Stats_Transaction::run( $rewrite ) ) {
				$job['aggregate_failures'] = absint( $job['aggregate_failures'] ?? 0 ) + 1;
				return $job;
			}
			$job['aggregates_rewritten'] = absint( $job['aggregates_rewritten'] ?? 0 ) + 1;
			return $job;
		}

		/**
		 * Stage `facts`: re-key the subject's distinct-identity facts.
		 *
		 * `identity_hash` becomes `dedupe_key( 'removed:<token>' )` -- the EXACT
		 * expression `record_unique_fact()` computes on a rebuild, so the
		 * anonymised row and a row rebuilt from the severed order land on the same
		 * identity for the same person. It is deliberately NOT an HMAC over the old
		 * hash under a destroyed job secret: that value is not reproducible from an
		 * order, so the two systems would silently disagree.
		 *
		 * `visit_hash` MUST be re-keyed too. It is `dedupe_key( $identity . ':' .
		 * $occurred )`, so a user id plus 86,400 timestamp guesses recovers a day of
		 * it; re-keying the identity and leaving the visit leaves the hole open.
		 * The token is 48 bits of `random_bytes()` and is never mapped to a person.
		 *
		 * The mapping is injective (one token per user), so `COUNT(DISTINCT
		 * identity_hash)` does not move: two erased customers still read as two.
		 *
		 * @param array $job   Job record.
		 * @param int   $limit Maximum rows for this batch.
		 * @return array
		 */
		private function anonymise_facts( $job, $limit ) {
			global $wpdb;
			$user_id      = absint( $job['user_id'] );
			$token        = (string) $job['token'];
			$old_identity = (string) $job['identity_hash'];
			$new_identity = $this->dedupe_key( $this->removed_identity_value( $user_id, $token ) );
			$table        = $wpdb->prefix . 'ic_stats_unique_facts';
			if ( ! $old_identity || $new_identity === $old_identity ) {
				$job['stage']  = 'extensions';
				$job['cursor'] = 0;
				return $job;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the lookup rides KEY identity_lookup and every value is prepared.
			$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT integration,metric,period,dimension_hash,identity_hash,visit_hash,first_seen_gmt,last_seen_gmt,occurrence_count FROM {$table} WHERE identity_hash=%s LIMIT %d", $old_identity, $limit ), ARRAY_A );
			$rows      = is_array( $rows ) ? $rows : array();
			$rewritten = 0;
			foreach ( $rows as $row ) {
				$integration = sanitize_key( $row['integration'] );
				$old_visit   = (string) $row['visit_hash'];
				$new_visit   = '' === $old_visit ? '' : hash_hmac( 'sha256', $old_visit, $token );
				$old_dim     = (string) $row['dimension_hash'];
				// A hash the map never saw belongs to a pruned dimension row: it
				// already points at nothing, so it is kept as-is and the fact is
				// still re-keyed.
				$new_dim = isset( $job['hash_map'][ $old_dim ] ) ? (string) $job['hash_map'][ $old_dim ] : $old_dim;
				$rewrite = function () use ( $wpdb, $table, $row, $integration, $new_identity, $new_visit, $new_dim, $old_identity, $old_visit, $old_dim ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (integration,metric,period,dimension_hash,identity_hash,visit_hash,first_seen_gmt,last_seen_gmt,occurrence_count) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%d) ON DUPLICATE KEY UPDATE occurrence_count=occurrence_count+VALUES(occurrence_count),first_seen_gmt=LEAST(first_seen_gmt,VALUES(first_seen_gmt)),last_seen_gmt=GREATEST(last_seen_gmt,VALUES(last_seen_gmt))", $integration, sanitize_key( $row['metric'] ), (string) $row['period'], $new_dim, $new_identity, $new_visit, (string) $row['first_seen_gmt'], (string) $row['last_seen_gmt'], max( 1, absint( $row['occurrence_count'] ) ) ) );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the full primary key is prepared.
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE integration=%s AND metric=%s AND period=%s AND identity_hash=%s AND visit_hash=%s AND dimension_hash=%s", $integration, sanitize_key( $row['metric'] ), (string) $row['period'], $old_identity, $old_visit, $old_dim ) );
					return true;
				};
				if ( IC_Stats_Transaction::run( $rewrite ) ) {
					++$rewritten;
					$job['integrations'][ $integration ] = true;
				}
			}
			$job['facts_rewritten'] = absint( $job['facts_rewritten'] ?? 0 ) + $rewritten;
			// A batch that rewrote nothing cannot make progress on a later pass
			// either -- the same rows would be selected forever -- so the stage
			// advances and the failure count travels with the job.
			if ( count( $rows ) < $limit || 0 === $rewritten ) {
				$job['fact_failures'] = absint( $job['fact_failures'] ?? 0 ) + ( count( $rows ) - $rewritten );
				$job['stage']         = 'extensions';
				$job['cursor']        = 0;
			}
			return $job;
		}

		/**
		 * Stage `cleanup`: index the new dimensions, prune the old ones, finish.
		 *
		 * An old dimension row is deleted ONLY when no aggregate, fact or event in
		 * that integration still references its hash -- the dictionary is what makes
		 * a filter dropdown readable, and deleting a hash something still points at
		 * turns a live breakdown row into an unlabelled one.
		 *
		 * The one `JSON_EXTRACT` sweep in the whole engine runs here, on the final
		 * batch, to catch aggregate rows whose dimension index entries were pruned
		 * by an earlier retention pass and which the indexed lookup therefore cannot
		 * see.
		 *
		 * @param array $job   Job record.
		 * @param int   $limit Maximum rows for this batch.
		 * @return array
		 */
		private function anonymise_cleanup( $job, $limit ) {
			global $wpdb;
			$user_id    = absint( $job['user_id'] );
			$removed    = $this->removed_identity_value( $user_id, $job['token'] );
			$dims       = $wpdb->prefix . 'ic_stats_dimensions';
			$aggregates = $wpdb->prefix . 'ic_stats_aggregates';
			$facts      = $wpdb->prefix . 'ic_stats_unique_facts';
			$events     = $wpdb->prefix . 'ic_stats_events';
			$cursor     = (string) ( $job['cursor'] ?? '' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared and the lookup rides KEY value_lookup.
			$pairs = $wpdb->get_results( $wpdb->prepare( "SELECT integration,dimension_hash FROM {$dims} WHERE dimension_key=%s AND dimension_value=%s AND dimension_hash>%s ORDER BY dimension_hash ASC LIMIT %d", 'user_id', (string) $user_id, $cursor, $limit ), ARRAY_A );
			$pairs = is_array( $pairs ) ? $pairs : array();
			foreach ( $pairs as $pair ) {
				$integration = sanitize_key( $pair['integration'] );
				$hash        = (string) $pair['dimension_hash'];
				$cursor      = $hash;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the lookup rides the primary key.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT dimension_key,dimension_value,first_seen_gmt,last_seen_gmt FROM {$dims} WHERE integration=%s AND dimension_hash=%s", $integration, $hash ), ARRAY_A );
				$rows = is_array( $rows ) ? $rows : array();
				if ( ! $rows ) {
					continue;
				}
				$decoded = array();
				$first   = '';
				$last    = '';
				foreach ( $rows as $row ) {
					$decoded[ (string) $row['dimension_key'] ] = (string) $row['dimension_value'];
					if ( '' === $first || (string) $row['first_seen_gmt'] < $first ) {
						$first = (string) $row['first_seen_gmt'];
					}
					if ( (string) $row['last_seen_gmt'] > $last ) {
						$last = (string) $row['last_seen_gmt'];
					}
				}
				$decoded['user_id']                  = $removed;
				$new_dimensions                      = $this->normalize_dimensions( $decoded );
				$new_hash                            = hash( 'sha256', wp_json_encode( $new_dimensions ) );
				$job['hash_map'][ $hash ]            = $new_hash;
				$job['integrations'][ $integration ] = true;
				// Two calls: the first carries the old first_seen through the INSERT,
				// the second lifts last_seen through the writer's own GREATEST().
				$this->index_dimensions( $integration, $new_hash, $new_dimensions, '' === $first ? $last : $first );
				if ( $last && $last !== $first ) {
					$this->index_dimensions( $integration, $new_hash, $new_dimensions, $last );
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifiers; all values are prepared and each probe is LIMIT 1.
				$referenced = (int) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$aggregates} WHERE integration=%s AND dimension_hash=%s LIMIT 1", $integration, $hash ) );
				if ( ! $referenced ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
					$referenced = (int) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$facts} WHERE integration=%s AND dimension_hash=%s LIMIT 1", $integration, $hash ) );
				}
				if ( ! $referenced ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
					$referenced = (int) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$events} WHERE integration=%s AND dimension_hash=%s LIMIT 1", $integration, $hash ) );
				}
				if ( $referenced ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier and sanitized values.
				$wpdb->delete(
					$dims,
					array(
						'integration'    => $integration,
						'dimension_hash' => $hash,
					),
					array( '%s', '%s' )
				);
			}
			$job['cursor'] = $cursor;
			if ( count( $pairs ) >= $limit ) {
				return $job;
			}
			$job = $this->anonymise_sweep( $job, $limit );
			foreach ( array_keys( (array) $job['integrations'] ) as $integration ) {
				do_action( 'ic_stats_aggregates_changed', sanitize_key( $integration ), 'anonymise_identity' );
			}
			$job['stage'] = 'complete';
			/**
			 * Fires once an identity has been rewritten out of the statistics.
			 *
			 * @param int    $user_id Subject user identifier.
			 * @param string $token   Per-user removal token.
			 * @param array  $job     Completed job record.
			 */
			do_action( 'ic_stats_identity_anonymised', $user_id, (string) $job['token'], $job );
			return $job;
		}

		/**
		 * One bounded `JSON_EXTRACT` sweep for aggregate rows the index cannot see.
		 *
		 * @param array $job   Job record.
		 * @param int   $limit Maximum rows for this sweep.
		 * @return array
		 */
		private function anonymise_sweep( $job, $limit ) {
			global $wpdb;
			$aggregates = $wpdb->prefix . 'ic_stats_aggregates';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the subject id is prepared. This is the ONE unindexed sweep in the engine and it runs once per job.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,integration,metric,bucket,bucket_start,dimension_hash,dimensions,currency,value,count FROM {$aggregates} WHERE JSON_UNQUOTE(JSON_EXTRACT(dimensions,'$.user_id'))=%s ORDER BY id ASC LIMIT %d", (string) absint( $job['user_id'] ), $limit ), ARRAY_A );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$job = $this->anonymise_aggregate_row( $job, $row );
			}
			return $job;
		}

		/**
		 * Purge all framework data belonging to one integration.
		 *
		 * @param string $integration Integration key.
		 * @return bool
		 */
		public function purge( $integration ) {
			global $wpdb;
			$integration = sanitize_key( $integration );
			if ( ! $integration ) {
				return false; }
			$events = $wpdb->prefix . 'ic_stats_events';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; all values are prepared.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$events} WHERE integration=%s", $integration ) );
			foreach ( $ids as $id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier and sanitized values.
				$wpdb->delete( $wpdb->prefix . 'ic_stats_event_metrics', array( 'event_id' => absint( $id ) ), array( '%d' ) ); }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier and sanitized values.
			$wpdb->delete( $events, array( 'integration' => $integration ), array( '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier and sanitized values.
			$wpdb->delete( $wpdb->prefix . 'ic_stats_aggregates', array( 'integration' => $integration ), array( '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier and sanitized values.
			$wpdb->delete( $wpdb->prefix . 'ic_stats_dimensions', array( 'integration' => $integration ), array( '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier and sanitized values.
			$wpdb->delete( $wpdb->prefix . 'ic_stats_unique_facts', array( 'integration' => $integration ), array( '%s' ) );
			do_action( 'ic_stats_purge_extension', $integration, $this );
			delete_option( 'ic_stats_' . $integration );
			return true;
		}
	}
}
if ( ! class_exists( 'ic_stats_manager', false ) && class_exists( 'IC_Stats_Manager' ) ) {
	class_alias( 'IC_Stats_Manager', 'ic_stats_manager' ); }
