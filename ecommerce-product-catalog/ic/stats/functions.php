<?php
/**
 * Public statistics framework helpers.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! function_exists( 'ic_stats_manager' ) ) {
	/**
	 * Return the shared statistics manager.
	 *
	 * @return IC_Stats_Manager
	 */
	function ic_stats_manager() {
		static $manager;
		static $constructing = false;
		if ( ! $manager ) {
			if ( $constructing ) {
				return null;
			}
			$constructing = true;
			$manager      = new IC_Stats_Manager( false );
			$manager->announce_registration();
			$constructing = false;
		} return $manager; } }
if ( ! function_exists( 'ic_stats_register_integration' ) ) {
	/**
	 * Register a statistics integration.
	 *
	 * @param string $key        Integration key.
	 * @param array  $definition Integration definition.
	 * @return bool
	 */
	function ic_stats_register_integration( $key, $definition ) {
		$manager = ic_stats_manager();
		return $manager instanceof IC_Stats_Manager ? $manager->register_integration( $key, $definition ) : false; } }
if ( ! function_exists( 'ic_stats_record' ) ) {
	/**
	 * Record an integration event.
	 *
	 * @param string $integration Integration key.
	 * @param string $event       Event key.
	 * @param array  $payload     Event payload.
	 * @param array  $context     Event context.
	 * @return int
	 */
	function ic_stats_record( $integration, $event, $payload = array(), $context = array() ) {
		return ic_stats_manager()->record( $integration, $event, $payload, $context ); } }
if ( ! function_exists( 'ic_stats_record_batch' ) ) {
	/**
	 * Record at most 100 events under one public bounded contract.
	 *
	 * @param string $integration Integration key.
	 * @param array  $records     Event records containing event, payload and context.
	 * @return array Accepted and rejected record counts.
	 */
	function ic_stats_record_batch( $integration, $records ) {
		return ic_stats_manager()->record_batch( $integration, $records );
	}
}
if ( ! function_exists( 'ic_stats_metric_enabled' ) ) {
	/**
	 * Check whether an integration metric is enabled.
	 *
	 * @param string $integration Integration key.
	 * @param string $metric      Metric key.
	 * @return bool
	 */
	function ic_stats_metric_enabled( $integration, $metric ) {
		return ic_stats_manager()->metric_enabled( $integration, $metric ); } }
if ( ! function_exists( 'ic_stats_process' ) ) {
	/**
	 * Process a bounded metric batch.
	 *
	 * @param int $limit Maximum rows to process.
	 * @return int
	 */
	function ic_stats_process( $limit = 100 ) {
		ic_stats_dispatch_backfills( $limit );
		return ic_stats_manager()->process( $limit ); } }
if ( ! function_exists( 'ic_stats_retain' ) ) {
	/**
	 * Apply bounded statistics retention.
	 *
	 * @param int $days  Retention period in days.
	 * @param int $limit Maximum rows to delete.
	 * @return int
	 */
	function ic_stats_retain( $days = 90, $limit = 500 ) {
		return ic_stats_manager()->retain( $days, $limit ); } }
if ( ! function_exists( 'ic_stats_purge' ) ) {
	/**
	 * Purge data for one integration.
	 *
	 * @param string $integration Integration key.
	 * @return bool
	 */
	function ic_stats_purge( $integration ) {
		ic_stats_flush_has_data( $integration );
		return ic_stats_manager()->purge( $integration ); } }
if ( ! function_exists( 'ic_stats_query' ) ) {
	/**
	 * Query aggregate statistics for an integration.
	 *
	 * @param string $integration Integration key.
	 * @param array  $args        Query arguments.
	 * @return array
	 */
	function ic_stats_query( $integration, $args = array() ) {
		return ( new IC_Stats_Query( $integration ) )->run( $args ); }
}
if ( ! function_exists( 'ic_stats_dimension_choices' ) ) {
	/**
	 * Return bounded recent indexed dimension choices.
	 *
	 * @param string $integration Integration key.
	 * @param array  $keys        Dimension keys.
	 * @param array  $args        Choice query arguments.
	 * @return array Dimension choices keyed by dimension.
	 */
	function ic_stats_dimension_choices( $integration, $keys, $args = array() ) {
		global $wpdb;
		$integration = sanitize_key( $integration );
		$keys        = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $keys ) ) ) ), 0, 20 );
		$limit       = min( 200, max( 1, absint( $args['limit'] ?? 100 ) ) );
		$out         = array_fill_keys( $keys, array() );
		if ( ! $integration || ! $keys ) {
			return $out;
		}
		$marks  = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$params = array_merge( array( $integration ), $keys, array( $limit * count( $keys ) ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fixed framework table and allowlisted keys use prepared values.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT dimension_key,dimension_value,MAX(last_seen_gmt) last_seen FROM {$wpdb->prefix}ic_stats_dimensions WHERE integration=%s AND dimension_key IN ({$marks}) GROUP BY dimension_key,dimension_value ORDER BY last_seen DESC LIMIT %d", ...$params ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$key = sanitize_key( $row['dimension_key'] ?? '' );
			if ( isset( $out[ $key ] ) && count( $out[ $key ] ) < $limit ) {
				$out[ $key ][ (string) $row['dimension_value'] ] = (string) $row['dimension_value'];
			}
		}
		return $out;
	}
}
if ( ! function_exists( 'ic_stats_register_backfill_provider' ) ) {
	/**
	 * Register a bounded backfill provider callback.
	 *
	 * @param string   $key      Provider key.
	 * @param callable $callback Provider callback.
	 * @return array
	 */
	function ic_stats_register_backfill_provider( $key, $callback ) {
		static $providers = array();
		$key              = sanitize_key( $key );
		if ( $key && is_callable( $callback ) ) {
			$providers[ $key ] = $callback; }
		return $providers;
	}
}
if ( ! function_exists( 'ic_stats_dispatch_backfills' ) ) {
	/**
	 * Dispatch active backfill requests in bounded batches.
	 *
	 * Only pending and processing requests are active. A completed request is
	 * retained unchanged as the final audit/status record for its lifecycle.
	 *
	 * @param int $limit Maximum rows per provider.
	 * @return void
	 */
	function ic_stats_dispatch_backfills( $limit = 100 ) {
		$requests = array();
		$manager  = ic_stats_manager();
		foreach ( $manager->integrations() as $integration => $definition ) {
			$request = get_option( 'ic_stats_' . sanitize_key( $integration ) . '_backfill_request', array() );
			if ( ! empty( $request['state'] ) && in_array( $request['state'], array( 'pending', 'process' ), true ) ) {
				$requests[ $integration ] = $request;
			}
		}
		$providers = ic_stats_register_backfill_provider( '', null );
		foreach ( $requests as $integration => $request ) {
			$provider = (string) ( $request['args']['provider'] ?? '' );
			if ( ! isset( $providers[ sanitize_key( $provider ) ] ) ) {
				continue; }
			try {
				$result = call_user_func( $providers[ sanitize_key( $provider ) ], max( 1, min( 500, absint( $limit ) ) ), $request );
				if ( is_array( $result ) ) {
					$request = array_merge( $request, $result ); }
				$request['state'] = ! empty( $request['complete'] ) ? 'complete' : ( 'process' === ( $request['state'] ?? '' ) ? 'process' : 'pending' );
				update_option( 'ic_stats_' . sanitize_key( $integration ) . '_backfill_request', $request, false );
			} catch ( Throwable $exception ) {
				$request['attempts'] = absint( $request['attempts'] ?? 0 ) + 1;
				update_option( 'ic_stats_' . sanitize_key( $integration ) . '_backfill_request', $request, false ); }
		}
	}
}
if ( ! function_exists( 'ic_stats_anonymisation_queue' ) ) {
	/**
	 * Read the anonymisation job queue.
	 *
	 * The queue is autoload-false because a job record is transient PERSONAL
	 * DATA: it holds the subject's user id and the removal token, and deleting
	 * it is the last step of the job. Nothing outside this file writes it.
	 *
	 * @return array Job id => job record.
	 */
	function ic_stats_anonymisation_queue() {
		$queue = get_option( 'ic_stats_anonymisation_queue', array() );
		return is_array( $queue ) ? $queue : array();
	}
}
if ( ! function_exists( 'ic_stats_anonymise_user' ) ) {
	/**
	 * Queue an identity for anonymisation across every stats integration.
	 *
	 * Numbers are retained, identity is destroyed: the job rewrites the
	 * subject out of the aggregates, facts and lifecycles and deletes only the
	 * raw events, so no total moves. Nothing runs here -- the job is drained by
	 * `ic_stats_anonymisation_step()`, by the paged WordPress eraser, or by
	 * `ic_stats_maintenance()`.
	 *
	 * Calling it twice for the same user returns the SAME job, keeping the
	 * original token: a second token would give the same person two surrogate
	 * identities and split one customer into two distinct counts.
	 *
	 * @param int   $user_id Subject user identifier.
	 * @param array $args    Optional. `token` supplies the per-user removal
	 *                       token minted by the order side, so the stats and
	 *                       the severed order agree on one surrogate.
	 * @return string Job identifier, or an empty string when nothing was queued.
	 */
	function ic_stats_anonymise_user( $user_id, $args = array() ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}
		$args   = is_array( $args ) ? $args : array();
		$queue  = ic_stats_anonymisation_queue();
		$job_id = 'user_' . $user_id;
		if ( isset( $queue[ $job_id ] ) && is_array( $queue[ $job_id ] ) ) {
			return $job_id;
		}
		$token = substr( preg_replace( '/[^a-f0-9]/', '', strtolower( (string) ( $args['token'] ?? '' ) ) ), 0, 64 );
		if ( '' === $token ) {
			// The token belongs to the `ic/privacy` module, which is a later
			// release; until it ships the stats side mints its own, and the order
			// side passes one in through $args once it can.
			$token = function_exists( 'ic_identity_removal_token' ) ? (string) ic_identity_removal_token( $user_id ) : bin2hex( random_bytes( 6 ) );
			$token = substr( preg_replace( '/[^a-f0-9]/', '', strtolower( $token ) ), 0, 64 );
		}
		if ( '' === $token ) {
			$token = bin2hex( random_bytes( 6 ) );
		}
		$queue[ $job_id ] = array(
			'user_id'       => $user_id,
			'identity_hash' => ic_stats_manager()->dedupe_key( 'user:' . $user_id ),
			'token'         => $token,
			'stage'         => 'events',
			'cursor'        => 0,
			'hash_map'      => array(),
			'integrations'  => array(),
			'attempts'      => 0,
			'created_gmt'   => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( 'ic_stats_anonymisation_queue', $queue, false );
		return $job_id;
	}
}
if ( ! function_exists( 'ic_stats_anonymisation_batch_size' ) ) {
	/**
	 * Resolve the anonymisation batch size.
	 *
	 * @param int $limit Requested limit. 0 uses the filtered default.
	 * @return int
	 */
	function ic_stats_anonymisation_batch_size( $limit = 0 ) {
		$limit = absint( $limit );
		if ( ! $limit ) {
			/**
			 * Filters how many rows one anonymisation batch may touch.
			 *
			 * @param int $limit Batch size.
			 */
			$limit = absint( apply_filters( 'ic_stats_anonymisation_batch', 200 ) );
		}
		return max( 1, min( 1000, $limit ? $limit : 200 ) );
	}
}
if ( ! function_exists( 'ic_stats_anonymisation_step' ) ) {
	/**
	 * Run one bounded batch of one anonymisation job.
	 *
	 * The job record is deleted as soon as the manager reports `complete`, and
	 * the whole option is deleted once the last job goes: the record is the only
	 * thing left linking the surrogate to a person.
	 *
	 * @param string $job_id Job identifier.
	 * @param int    $limit  Optional batch size.
	 * @return array State report: `state`, `stage`, `done`.
	 */
	function ic_stats_anonymisation_step( $job_id, $limit = 0 ) {
		$job_id = sanitize_key( $job_id );
		$queue  = ic_stats_anonymisation_queue();
		if ( ! $job_id || ! isset( $queue[ $job_id ] ) || ! is_array( $queue[ $job_id ] ) ) {
			return array(
				'state' => 'missing',
				'stage' => 'complete',
				'done'  => true,
			);
		}
		$limit = ic_stats_anonymisation_batch_size( $limit );
		try {
			$job = ic_stats_manager()->anonymise_identity( $queue[ $job_id ], $limit );
		} catch ( Throwable $exception ) {
			$queue[ $job_id ]['attempts'] = absint( $queue[ $job_id ]['attempts'] ?? 0 ) + 1;
			update_option( 'ic_stats_anonymisation_queue', $queue, false );
			return array(
				'state' => 'error',
				'stage' => (string) ( $queue[ $job_id ]['stage'] ?? '' ),
				'done'  => false,
			);
		}
		$stage = (string) ( $job['stage'] ?? 'complete' );
		if ( 'complete' === $stage ) {
			unset( $queue[ $job_id ] );
			if ( $queue ) {
				update_option( 'ic_stats_anonymisation_queue', $queue, false );
			} else {
				delete_option( 'ic_stats_anonymisation_queue' );
			}
			return array(
				'state' => 'complete',
				'stage' => 'complete',
				'done'  => true,
			);
		}
		$job['attempts']  = absint( $job['attempts'] ?? 0 ) + 1;
		$queue[ $job_id ] = $job;
		update_option( 'ic_stats_anonymisation_queue', $queue, false );
		return array(
			'state' => 'process',
			'stage' => $stage,
			'done'  => false,
		);
	}
}
if ( ! function_exists( 'ic_stats_dispatch_anonymisations' ) ) {
	/**
	 * Drain every queued anonymisation job in bounded batches.
	 *
	 * Each job is advanced until it completes or the per-pass batch budget runs
	 * out, so one maintenance run stays bounded no matter how much history a
	 * subject has. A job older than `ic_stats_anonymisation_job_ttl` is
	 * force-completed: its `cleanup` stage is run once, then the record is
	 * destroyed regardless of the outcome, because leaving a stalled job on disk
	 * keeps the user id and the token readable forever.
	 *
	 * @param int $limit Batch size.
	 * @return array Summary counts.
	 */
	function ic_stats_dispatch_anonymisations( $limit = 200 ) {
		$queue = ic_stats_anonymisation_queue();
		if ( ! $queue ) {
			return array(
				'jobs'      => 0,
				'completed' => 0,
				'expired'   => 0,
			);
		}
		$limit = ic_stats_anonymisation_batch_size( $limit );
		/**
		 * Filters how long an unfinished anonymisation job may live.
		 *
		 * @param int $ttl Seconds.
		 */
		$ttl       = absint( apply_filters( 'ic_stats_anonymisation_job_ttl', 7 * DAY_IN_SECONDS ) );
		$now       = time();
		$completed = 0;
		$expired   = 0;
		$jobs      = 0;
		foreach ( array_keys( $queue ) as $job_id ) {
			++$jobs;
			$current = ic_stats_anonymisation_queue();
			if ( ! isset( $current[ $job_id ] ) ) {
				continue;
			}
			$created = strtotime( (string) ( $current[ $job_id ]['created_gmt'] ?? '' ) . ' UTC' );
			if ( $ttl && $created && ( $now - $created ) > $ttl ) {
				$current[ $job_id ]['stage'] = 'cleanup';
				update_option( 'ic_stats_anonymisation_queue', $current, false );
				ic_stats_anonymisation_step( $job_id, $limit );
				$stale = ic_stats_anonymisation_queue();
				if ( isset( $stale[ $job_id ] ) ) {
					unset( $stale[ $job_id ] );
					if ( $stale ) {
						update_option( 'ic_stats_anonymisation_queue', $stale, false );
					} else {
						delete_option( 'ic_stats_anonymisation_queue' );
					}
				}
				++$expired;
				continue;
			}
			// Six stages plus a few batches of slack, so a normal job finishes in
			// one dispatch while a huge one still yields to the next pass.
			for ( $batch = 0; $batch < 20; $batch++ ) {
				$result = ic_stats_anonymisation_step( $job_id, $limit );
				if ( ! empty( $result['done'] ) ) {
					if ( 'complete' === (string) $result['state'] ) {
						++$completed;
					}
					break;
				}
				if ( 'error' === (string) $result['state'] ) {
					break;
				}
			}
		}
		return array(
			'jobs'      => $jobs,
			'completed' => $completed,
			'expired'   => $expired,
		);
	}
}
if ( ! function_exists( 'ic_stats_erasure_notice' ) ) {
	/**
	 * Read the non-identifying statistics erasure marker.
	 *
	 * Holds a count and the last erasure timestamp only -- no identity hash and
	 * no user id -- so it is not a ledger and can re-link nothing. Until order
	 * severing ships, a rebuild from saved orders restores an erased stats
	 * identity, and this is how an operator learns that erasures exist at all.
	 *
	 * @return array `count` and `last_gmt`.
	 */
	function ic_stats_erasure_notice() {
		return class_exists( 'IC_Stats_Privacy' ) ? IC_Stats_Privacy::notice() : array(
			'count'    => 0,
			'last_gmt' => '',
		);
	}
}
if ( ! function_exists( 'ic_stats_backfill_progress_snapshot' ) ) {
	/**
	 * Snapshot the request fields which represent meaningful backfill progress.
	 *
	 * Attempts and timestamps are intentionally excluded: provider errors must not
	 * turn a budgeted maintenance invocation into a tight retry loop.
	 *
	 * @return array Progress fields keyed by integration.
	 */
	function ic_stats_backfill_progress_snapshot() {
		$snapshot = array();
		foreach ( array_keys( ic_stats_manager()->integrations() ) as $integration ) {
			$request = get_option( 'ic_stats_' . sanitize_key( $integration ) . '_backfill_request', array() );
			if ( ! is_array( $request ) ) {
				continue;
			}
			$snapshot[ $integration ] = array(
				'state'    => (string) ( $request['state'] ?? '' ),
				'cursor'   => (string) ( $request['cursor'] ?? '' ),
				'complete' => ! empty( $request['complete'] ),
			);
		}
		return $snapshot;
	}
}
if ( ! function_exists( 'ic_stats_memory_near_limit' ) ) {
	/**
	 * Check whether another batch would leave too little PHP memory headroom.
	 *
	 * @return bool Whether at least 10 percent or 5 MB should be reserved.
	 */
	function ic_stats_memory_near_limit() {
		$raw_limit = ini_get( 'memory_limit' );
		$limit     = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( $raw_limit ) : (int) $raw_limit;
		if ( $limit <= 0 ) {
			return false;
		}
		$reserve = max( 5 * MB_IN_BYTES, (int) ceil( $limit * 0.1 ) );
		return memory_get_usage( true ) >= ( $limit - $reserve );
	}
}
if ( ! function_exists( 'ic_stats_maintenance' ) ) {
	/**
	 * Run globally serialized statistics maintenance.
	 *
	 * With no time budget this preserves the legacy single-batch contract.
	 * A positive budget permits repeated bounded projection batches while enough
	 * time, memory and meaningful progress remain.
	 *
	 * @param array $args Optional batch_limit, time_budget, max_batches and continue_callback.
	 * @return array Maintenance result and additive loop diagnostics.
	 */
	function ic_stats_maintenance( $args = array() ) {
		$lock = 'ic_stats_maintenance_lock';
		if ( get_transient( $lock ) ) {
			return array( 'state' => 'locked' );
		}
		$args              = is_array( $args ) ? $args : array();
		$batch_limit       = max( 1, min( 500, absint( $args['batch_limit'] ?? 100 ) ) );
		$time_budget       = max( 0, min( 300, (float) ( $args['time_budget'] ?? 0 ) ) );
		$max_batches       = max( 1, min( 1000, absint( $args['max_batches'] ?? ( $time_budget > 0 ? 1000 : 1 ) ) ) );
		$continue_callback = isset( $args['continue_callback'] ) && is_callable( $args['continue_callback'] ) ? $args['continue_callback'] : null;
		$started           = microtime( true );
		$deadline          = $time_budget > 0 ? $started + $time_budget : 0.0;
		$deadline_reason   = 'budget';
		$php_limit         = (float) ini_get( 'max_execution_time' );
		if ( $time_budget > 0 && $php_limit > 0 ) {
			$request_started = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $started;
			$php_deadline    = $request_started + max( 0, $php_limit - 10 );
			if ( $php_deadline < $deadline ) {
				$deadline        = $php_deadline;
				$deadline_reason = 'server_deadline';
			}
		}
		set_transient( $lock, 1, 10 * MINUTE_IN_SECONDS );
		try {
			foreach ( array_keys( ic_stats_manager()->integrations() ) as $integration ) {
				do_action( 'ic_stats_pre_maintenance_' . sanitize_key( $integration ) );
			}
			$processed   = 0;
			$batches     = 0;
			$longest     = 0.0;
			$stop_reason = $time_budget > 0 ? 'budget' : 'single_pass';
			do {
				$before          = ic_stats_backfill_progress_snapshot();
				$batch_started   = microtime( true );
				$batch_processed = ic_stats_process( $batch_limit );
				$batch_duration  = microtime( true ) - $batch_started;
				$processed      += $batch_processed;
				++$batches;
				$longest  = max( $longest, $batch_duration );
				$stats    = ic_stats_manager()->last_process_stats();
				$progress = ! empty( $stats['finalized'] ) || ic_stats_backfill_progress_snapshot() !== $before;

				if ( $continue_callback && ! call_user_func( $continue_callback ) ) {
					$stop_reason = 'target_complete';
					break;
				}
				if ( ! $progress ) {
					$stop_reason = 'no_progress';
					break;
				}
				if ( $time_budget <= 0 ) {
					$stop_reason = 'single_pass';
					break;
				}
				if ( $batches >= $max_batches ) {
					$stop_reason = 'batch_ceiling';
					break;
				}
				if ( ic_stats_memory_near_limit() ) {
					$stop_reason = 'memory';
					break;
				}
				$reserve = max( 5.0, 1.5 * $longest );
				if ( ( $deadline - microtime( true ) ) <= $reserve ) {
					$stop_reason = $deadline_reason;
					break;
				}
			} while ( true );
			// Anonymisation runs BEFORE retention. Its `events` stage projects the
			// subject's pending bridges and then deletes their events, so letting
			// retention run first would only mean two passes over the same rows --
			// and a job left mid-stage is resumed here rather than on a cron of its
			// own, which is what makes an interrupted erasure finish at all.
			$anonymised = ic_stats_dispatch_anonymisations();
			$retained   = ic_stats_retain( 90, 2000 );
			return array(
				'state'       => 'complete',
				'processed'   => $processed,
				'anonymised'  => $anonymised,
				'retained'    => $retained,
				'batches'     => $batches,
				'elapsed'     => microtime( true ) - $started,
				'stop_reason' => $stop_reason,
			);
		} finally {
			delete_transient( $lock );
		}
	}
}
if ( ! function_exists( 'ic_stats_schedule_maintenance' ) ) {
	/** Schedule shared maintenance without creating duplicate cron entries. */
	function ic_stats_schedule_maintenance() {
		if ( ! wp_next_scheduled( 'ic_stats_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'ic_stats_maintenance' );
		}
	}
}
add_action( 'ic_stats_registry_ready', 'ic_stats_schedule_maintenance', 20 );
add_action( 'ic_stats_maintenance', 'ic_stats_maintenance' );
add_action( 'shutdown', 'ic_stats_schedule_maintenance', 20 );

if ( ! function_exists( 'ic_stats_render_chart' ) ) {
	/**
	 * Render an accessible statistics chart.
	 *
	 * @param array $model Chart model.
	 * @param array $args  Chart arguments.
	 * @return string
	 */
	function ic_stats_render_chart( $model, $args = array() ) {
		return IC_Stats_Chart::render( $model, $args ); }
}
if ( ! function_exists( 'ic_stats_extend_integration' ) ) {
	/**
	 * Extend a registered integration definition without partial mutation.
	 *
	 * @param string $integration Integration key.
	 * @param string $extension   Extension key.
	 * @param array  $definition  Extension definition.
	 * @return bool
	 */
	function ic_stats_extend_integration( $integration, $extension, $definition ) {
		$manager = ic_stats_manager();
		$base    = $manager->integration( $integration );
		if ( ! $base || ! is_array( $definition ) ) {
			return false; }
		$extension = sanitize_key( $extension );
		if ( ! $extension ) {
			return false;
		}
		$owners    = (array) ( $base['_extension_owners'] ?? array() );
		$candidate = $base;
		foreach ( (array) ( $definition['metrics'] ?? array() ) as $metric => $meta ) {
			$metric = sanitize_key( $metric );
			if ( ! $metric ) {
				return false;
			}
			if ( isset( $candidate['metrics'][ $metric ] ) ) {
				if ( ( $owners[ $metric ] ?? '' ) !== $extension || wp_json_encode( $candidate['metrics'][ $metric ] ) !== wp_json_encode( $meta ) ) {
					return false;
				}
				continue;
			}
			$candidate['metrics'][ $metric ] = (array) $meta;
			$owners[ $metric ]               = $extension;
		}
		if ( isset( $definition['metric_order'] ) ) {
			$metric_order = array_values( array_unique( array_map( 'sanitize_key', (array) $definition['metric_order'] ) ) );
			if ( count( $metric_order ) !== count( $candidate['metrics'] ) || array_diff( $metric_order, array_keys( $candidate['metrics'] ) ) ) {
				return false;
			}
			$candidate['metrics'] = array_replace( array_fill_keys( $metric_order, array() ), $candidate['metrics'] );
		}
		foreach ( (array) ( $definition['pages'] ?? array() ) as $page => $page_definition ) {
			$page = sanitize_key( $page );
			if ( ! $page || ! is_array( $page_definition ) ) {
				return false;
			}
			$current = (array) ( $candidate['pages'][ $page ] ?? array() );
			$merged  = array_values( array_unique( array_merge( (array) ( $current['metrics'] ?? array() ), (array) ( $page_definition['metrics'] ?? array() ) ) ) );
			foreach ( $merged as $metric ) {
				if ( ! isset( $candidate['metrics'][ sanitize_key( $metric ) ] ) ) {
					return false;
				}
			}
			$candidate['pages'][ $page ]            = array_merge( $current, $page_definition );
			$candidate['pages'][ $page ]['metrics'] = array_map( 'sanitize_key', $merged );
		}
		foreach ( (array) ( $definition['lifecycle'] ?? array() ) as $event => $metrics ) {
			$event = sanitize_key( $event );
			foreach ( (array) $metrics as $metric ) {
				if ( ! isset( $candidate['metrics'][ sanitize_key( $metric ) ] ) ) {
					return false;
				}
			}
			$candidate['lifecycle'][ $event ] = array_values( array_unique( array_merge( (array) ( $candidate['lifecycle'][ $event ] ?? array() ), array_map( 'sanitize_key', (array) $metrics ) ) ) );
		}
		$candidate['supports']          = array_values( array_unique( array_merge( (array) ( $candidate['supports'] ?? array() ), array_map( 'sanitize_key', (array) ( $definition['supports'] ?? array() ) ) ) ) );
		$candidate['_extension_owners'] = $owners;
		if ( ! $manager->register_integration( $integration, $candidate ) ) {
			return false;
		}
		do_action( 'ic_stats_integration_extended', $integration, $extension, $candidate );
		return true;
	}
}
if ( ! function_exists( 'ic_stats_reset_integration' ) ) {
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Batch limit is reserved for the public reset contract.
	/**
	 * Reset all captured data for one integration.
	 *
	 * @param string $integration Integration key.
	 * @param int    $limit       Reserved batch limit for compatibility.
	 * @return bool
	 */
	function ic_stats_reset_integration( $integration, $limit = 500 ) {
		global $wpdb;
		$limit       = max( 1, absint( $limit ) );
		$integration = sanitize_key( $integration );
		if ( ! $integration ) {
			return false; }
		$events        = $wpdb->prefix . 'ic_stats_events';
		$event_metrics = $wpdb->prefix . 'ic_stats_event_metrics';
		$dimensions    = $wpdb->prefix . 'ic_stats_dimensions';
		$unique_facts  = $wpdb->prefix . 'ic_stats_unique_facts';
		$reset         = function () use ( $wpdb, $event_metrics, $events, $dimensions, $unique_facts, $integration ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifiers; integration value is prepared.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$event_metrics} WHERE event_id IN ( SELECT id FROM {$events} WHERE integration=%s )", $integration ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Remove orphan bridge rows left by legacy schemas; table identifiers are framework-owned.
			$wpdb->query( "DELETE FROM {$event_metrics} WHERE event_id NOT IN ( SELECT id FROM {$events} )" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; integration value is prepared.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE integration=%s", $integration ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework-owned table identifier; integration value is prepared.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ic_stats_aggregates WHERE integration=%s", $integration ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Framework-owned table identifier; integration value is prepared.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$dimensions} WHERE integration=%s", $integration ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Framework-owned table identifier; integration value is prepared.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$unique_facts} WHERE integration=%s", $integration ) );
			return true;
		};
		$result        = class_exists( 'IC_Stats_Transaction' ) ? IC_Stats_Transaction::run( $reset ) : $reset();
		if ( false === $result ) {
			return false;
		}
		do_action( 'ic_stats_reset_integration_extension', $integration );
		do_action( 'ic_stats_aggregates_changed', $integration, 'reset_integration' );
		return true;
	}
}
if ( ! function_exists( 'ic_stats_enabled' ) ) {
	/**
	 * Check whether a metric is enabled.
	 *
	 * @param string $integration Integration key.
	 * @param string $metric      Metric key.
	 * @return bool
	 */
	function ic_stats_enabled( $integration, $metric ) {
		return ic_stats_metric_enabled( $integration, $metric ); } }
if ( ! function_exists( 'ic_stats_register_page' ) ) {
	/**
	 * Register an administration page definition.
	 *
	 * @param string $integration Integration key.
	 * @param string $key         Page key.
	 * @param array  $definition  Page definition.
	 * @return bool
	 */
	function ic_stats_register_page( $integration, $key, $definition ) {
		$manager = ic_stats_manager();
		$base    = $manager->integration( $integration );
		if ( ! $base || ! is_array( $definition ) || ! sanitize_key( $key ) ) {
			return false;
		} $base['pages'][ sanitize_key( $key ) ] = (array) $definition;
		return $manager->register_integration( $integration, $base ); } }
if ( ! function_exists( 'ic_stats_update_metric_settings' ) ) {
	/**
	 * Update page-scoped metric settings.
	 *
	 * @param string $integration Integration key.
	 * @param array  $changes     Metric changes.
	 * @param array  $scope       Page scope.
	 * @return array
	 */
	function ic_stats_update_metric_settings( $integration, $changes, $scope = array() ) {
		$manager      = ic_stats_manager();
		$def          = $manager->integration( $integration );
		$settings     = get_option( 'ic_stats_' . sanitize_key( $integration ), array() );
		$first_optins = get_option( 'ic_stats_' . sanitize_key( $integration ) . '_first_optin', array() );
		$allow        = ! empty( $scope['page'] ) && isset( $def['pages'][ sanitize_key( $scope['page'] ) ]['metrics'] ) ? (array) $def['pages'][ sanitize_key( $scope['page'] ) ]['metrics'] : array_keys( (array) $def['metrics'] );
		foreach ( (array) $changes as $metric => $value ) {
			if ( in_array( $metric, $allow, true ) ) {
				$metric              = sanitize_key( $metric );
				$was_enabled         = $manager->metric_enabled( $integration, $metric );
				$settings[ $metric ] = (bool) $value;
				if ( $settings[ $metric ] ) {
					$meta    = (array) ( $def['metrics'][ $metric ] ?? array() );
					$sources = array_merge( (array) ( $meta['source_metrics'] ?? array() ), (array) ( $meta['derived_from'] ?? array() ), array( $meta['alias_of'] ?? '' ) );
					foreach ( array_filter( array_map( 'sanitize_key', $sources ) ) as $source_metric ) {
						if ( isset( $def['metrics'][ $source_metric ] ) ) {
							$settings[ $source_metric ] = true;
						}
					}
					foreach ( array( 'numerator_metric', 'denominator_metric' ) as $source_key ) {
						$source_metric = sanitize_key( $meta[ $source_key ] ?? '' );
						if ( $source_metric && isset( $def['metrics'][ $source_metric ] ) ) {
							$settings[ $source_metric ] = true;
						}
					}
				}
				if ( $settings[ $metric ] && ! $was_enabled && empty( $first_optins[ $metric ] ) ) {
					$first_optins[ $metric ] = gmdate( 'Y-m-d H:i:s' );
					$meta                    = (array) ( $def['metrics'][ $metric ] ?? array() );
					if ( 'telemetry' !== ( $meta['source_mode'] ?? 'telemetry' ) && ! empty( $meta['backfill_provider'] ) ) {
						do_action( 'ic_stats_metric_first_opt_in', $integration, $metric, $meta['backfill_provider'] );
					}
				}
			}
		}
		update_option( 'ic_stats_' . sanitize_key( $integration ) . '_first_optin', $first_optins, false );
		update_option( 'ic_stats_' . sanitize_key( $integration ), $settings, false );
		do_action( 'ic_stats_metric_settings_changed', $integration, $changes, $scope );
		return $settings; } }
if ( ! function_exists( 'ic_stats_projection_status' ) ) {
	/**
	 * Get projection status for an integration.
	 *
	 * @param string $integration Integration key.
	 * @return array
	 */
	function ic_stats_projection_status( $integration ) {
		return get_option( 'ic_stats_' . sanitize_key( $integration ) . '_projection_status', array( 'state' => 'complete' ) ); } }
if ( ! function_exists( 'ic_stats_schedule_backfill' ) ) {
	/**
	 * Schedule a bounded backfill request.
	 *
	 * A compatible active request is extended. A terminal request is replaced
	 * with a fresh pending lifecycle while the terminal record remains available
	 * to status readers until this function is called explicitly.
	 *
	 * @param string $integration Integration key.
	 * @param array  $metric_keys Metric keys.
	 * @param array  $args        Backfill arguments.
	 * @return bool
	 */
	function ic_stats_schedule_backfill( $integration, $metric_keys, $args = array() ) {
		$integration = sanitize_key( $integration );
		if ( ! $integration ) {
			return false;
		}
		$definition         = (array) ic_stats_manager()->integration( $integration );
		$projection_version = max( 1, absint( $definition['projection_version'] ?? 4 ) );
		$metric_keys        = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $metric_keys ) ) ) );
		$option             = 'ic_stats_' . $integration . '_backfill_request';
		$existing           = get_option( $option, array() );
		$compatible         = is_array( $existing ) && in_array( $existing['state'] ?? '', array( 'pending', 'process' ), true ) && ( (string) ( $existing['args']['provider'] ?? '' ) === (string) ( $args['provider'] ?? '' ) );
		if ( $compatible ) {
			$request            = $existing;
			$request['metrics'] = array_values( array_unique( array_merge( (array) ( $existing['metrics'] ?? array() ), $metric_keys ) ) );
			$request['args']    = array_merge( (array) ( $existing['args'] ?? array() ), (array) $args );
		} else {
			$request = array(
				'metrics'            => $metric_keys,
				'args'               => (array) $args,
				'cursor'             => 0,
				'cutoff_gmt'         => gmdate( 'Y-m-d H:i:s' ),
				'state'              => 'pending',
				'projection_version' => $projection_version,
			);
		}
		$request['projection_version'] = $projection_version;
		update_option( $option, $request, false );
		update_option(
			'ic_stats_' . $integration . '_projection_status',
			array(
				'state'          => 'pending',
				'version'        => $projection_version,
				'updated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
			),
			false
		);
		return true; } }
if ( ! function_exists( 'ic_stats_register_source_query' ) ) {
	/**
	 * Register a source query callback.
	 *
	 * @param string   $integration Integration key.
	 * @param string   $key         Query key.
	 * @param callable $callback    Query callback.
	 * @return bool
	 */
	function ic_stats_register_source_query( $integration, $key, $callback ) {
		if ( ! is_callable( $callback ) ) {
			return false;
		} return add_filter( 'ic_stats_source_query_' . sanitize_key( $integration ) . '_' . sanitize_key( $key ), $callback, 10, 2 ); } }
if ( ! function_exists( 'ic_stats_is_removed_identity' ) ) {
	/**
	 * Check whether a dimension value stands for an erased identity.
	 *
	 * Recognises BOTH shapes the framework can write: the shared literal
	 * `removed` (every erased person merged into one breakdown row) and the
	 * per-user `removed:<token>` (one row per erased person, totals identical
	 * either way). Display code therefore needs one rule, not two.
	 *
	 * @param mixed $value Dimension value.
	 * @return bool
	 */
	function ic_stats_is_removed_identity( $value ) {
		$value   = is_scalar( $value ) ? (string) $value : '';
		$removed = 'removed' === $value || 0 === strpos( $value, 'removed:' );
		return (bool) apply_filters( 'ic_stats_is_removed_identity', $removed, $value );
	}
}
if ( ! function_exists( 'ic_stats_removed_identity_value' ) ) {
	/**
	 * Build the value that replaces an erased identity in stats data.
	 *
	 * The `$token` argument is the whole point. It is the per-user removal
	 * token minted once per erasure job (`ic_identity_removal_token()`) and
	 * written verbatim onto the order, so a purge-and-rebuild re-derives the
	 * IDENTICAL value from the severed order instead of resurrecting the real
	 * user. An earlier design keyed the surrogate off a job secret that was
	 * destroyed with the job; that value is not reproducible from an order, so
	 * the anonymised rows and the rebuilt rows would disagree for the same
	 * person. See `proposals.md` section 19.
	 *
	 * With no token the caller gets the shared literal `removed`: totals stay
	 * exact but every erased person collapses into one breakdown row.
	 *
	 * @param int    $user_id User identifier being removed.
	 * @param string $token   Optional per-user removal token.
	 * @return string
	 */
	function ic_stats_removed_identity_value( $user_id, $token = '' ) {
		$token = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $token ), 0, 64 );
		$value = '' === $token ? 'removed' : 'removed:' . $token;
		return (string) apply_filters( 'ic_stats_removed_identity_value', $value, absint( $user_id ), $token );
	}
}
if ( ! function_exists( 'ic_stats_removed_identity_token' ) ) {
	/**
	 * Extract the removal token from a removed-identity value.
	 *
	 * @param mixed $value Dimension value.
	 * @return string Token, or an empty string when the value is not a removed
	 *                identity or carries no token (the shared `removed` form).
	 */
	function ic_stats_removed_identity_token( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$token = ic_stats_is_removed_identity( $value ) && 0 === strpos( $value, 'removed:' ) ? substr( $value, 8 ) : '';
		return (string) apply_filters( 'ic_stats_removed_identity_token', $token, $value );
	}
}
if ( ! function_exists( 'ic_stats_removed_identity_label' ) ) {
	/**
	 * Human label for a removed identity.
	 *
	 * DELIBERATELY NOT wrapped in `__()`. This file is mirrored byte-for-byte
	 * into every consumer plugin -- `ImplecodeAITest::test_standalone_ai_packages_current_stats_facade`
	 * asserts a sha256 match between the `ecommerce-product-catalog` and
	 * `implecode-ai` copies -- so it can carry no per-plugin text domain. The
	 * framework owns the default; consumers own the rendering and localise
	 * through the filter, exactly as `proposals.md` section 15 splits it.
	 *
	 * @return string
	 */
	function ic_stats_removed_identity_label() {
		return (string) apply_filters( 'ic_stats_removed_identity_label', 'Removed user' );
	}
}
if ( ! function_exists( 'ic_stats_has_data_transient' ) ) {
	/**
	 * Transient name holding one integration's data-presence answer.
	 *
	 * @param string $integration Integration key.
	 * @return string Transient name, or an empty string for an unusable key.
	 */
	function ic_stats_has_data_transient( $integration ) {
		$integration = sanitize_key( (string) $integration );
		return '' === $integration ? '' : 'ic_stats_has_data_' . $integration;
	}
}
if ( ! function_exists( 'ic_stats_flush_has_data' ) ) {
	/**
	 * Forget the cached data-presence answer for one integration.
	 *
	 * @param string $integration Integration key.
	 * @return void
	 */
	function ic_stats_flush_has_data( $integration ) {
		$transient = ic_stats_has_data_transient( $integration );
		if ( '' !== $transient ) {
			delete_transient( $transient );
		}
	}
}
if ( ! function_exists( 'ic_stats_flush_all_has_data' ) ) {
	/**
	 * Forget every cached data-presence answer.
	 *
	 * A raw `DROP TABLE` fires no hook, so the `ic_stats_aggregates_changed`
	 * flush never sees it and a positive answer cached for an hour keeps
	 * `ic_stats_has_data()` claiming a history that no longer exists. The
	 * schema installer recreating a table is the one moment the framework can
	 * observe the loss, and at that moment it cannot know WHICH integrations
	 * had rows -- an integration that has not registered yet on this request
	 * still has a stale transient of its own -- so every cached answer goes.
	 *
	 * Registered integrations are flushed by name, which is the only path that
	 * works under an external object cache. The option scan catches the rest
	 * and is skipped when transients do not live in the options table.
	 *
	 * @return void
	 */
	function ic_stats_flush_all_has_data() {
		global $wpdb;
		foreach ( array_keys( (array) ic_stats_manager()->integrations() ) as $integration ) {
			ic_stats_flush_has_data( $integration );
		}
		if ( ! $wpdb || wp_using_ext_object_cache() ) {
			return;
		}
		// The prefix is derived from the naming helper, never written out as a
		// literal, so renaming the transient cannot leave this scan behind.
		$prefix = substr( '_transient_' . ic_stats_has_data_transient( 'x' ), 0, -1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Option names are read with a prepared LIKE; the point is to find cached answers no cache lookup can enumerate.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		foreach ( (array) $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}
}
if ( ! function_exists( 'ic_stats_has_data' ) ) {
	/**
	 * Whether an integration still has any aggregate row.
	 *
	 * The schema self-heals -- `ic_stats_install_schema()` recreates a dropped
	 * table on the next load -- but the DATA does not, and the flag that says
	 * the history was already built lives in `wp_options`, which a table drop
	 * never touches. So a dropped table set, a restore predating the stats
	 * tables and a migration that moved `wp_options` but not the custom tables
	 * all leave an integration with `projection_status: complete` and seven
	 * empty tables that only accumulate new activity. This is how a consumer
	 * asks the tables themselves instead of trusting that option.
	 *
	 * One indexed row lookup, behind a short transient so registration -- which
	 * runs on every request -- does not add a query to every page load. The
	 * negative answer is cached for much less time than the positive one: a
	 * consumer that reacts to `false` by scheduling a backfill needs the next
	 * request to see the truth reasonably soon, while a store with data has
	 * nothing to re-check.
	 *
	 * @param string $integration Integration key.
	 * @return bool
	 */
	function ic_stats_has_data( $integration ) {
		global $wpdb;
		$transient = ic_stats_has_data_transient( $integration );
		if ( '' === $transient ) {
			return false;
		}
		$cached = get_transient( $transient );
		if ( false !== $cached ) {
			return '1' === (string) $cached;
		}
		$integration = sanitize_key( (string) $integration );
		$table       = $wpdb->prefix . 'ic_stats_aggregates';
		$suppressed  = $wpdb->suppress_errors( true );
		// A stale error from an earlier query would be read as "table missing".
		$wpdb->last_error = '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned table identifier; the integration key is prepared, the answer is cached in the transient below, and the whole point is to read the live table rather than a cache.
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE integration=%s LIMIT 1", $integration ) );
		$error = (string) $wpdb->last_error;
		$wpdb->suppress_errors( $suppressed );
		if ( '' !== $error ) {
			// The table is missing or unreadable. Answering `false` would tell a
			// consumer to rebuild into a table that cannot be written; the
			// schema installer is the thing that fixes this, not a backfill.
			return true;
		}
		$has = ! empty( $found );
		/**
		 * Filters how long the data-presence answer is cached.
		 *
		 * @param int    $ttl         Seconds.
		 * @param string $integration Integration key.
		 * @param bool   $has         Resolved answer.
		 */
		$ttl = absint( apply_filters( 'ic_stats_has_data_cache', $has ? HOUR_IN_SECONDS : MINUTE_IN_SECONDS, $integration, $has ) );
		set_transient( $transient, $has ? '1' : '0', $ttl );
		return $has;
	}
}
// A cached "no data" answer that outlives the backfill it triggered would ask
// for a second backfill, so the answer is dropped the moment the aggregates
// move. `add_action` de-duplicates a named callback, so a second loaded mirror
// cannot register it twice.
add_action( 'ic_stats_aggregates_changed', 'ic_stats_flush_has_data', 10, 1 );
