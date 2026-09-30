<?php
/**
 * Reusable aggregate query service.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! class_exists( 'IC_Stats_Query' ) ) {
	/**
	 * Query service for one registered statistics integration.
	 */
	class IC_Stats_Query {
		/**
		 * Integration key.
		 *
		 * @var string
		 */
		private $integration;
		/**
		 * Integration definition.
		 *
		 * @var array
		 */
		private $definition;
		/**
		 * Construct the query service.
		 *
		 * @param string $integration Integration key.
		 */
		public function __construct( $integration ) {
			$this->integration = sanitize_key( $integration );
			$this->definition  = ic_stats_manager()->integration( $this->integration );
		}
		/**
		 * Run an aggregate query.
		 *
		 * @param array $args Query arguments.
		 * @return array
		 */
		public function run( $args = array() ) {
			global $wpdb;
			$args             = $this->normalize_args( $args );
			$public_metrics   = array_filter(
				(array) $this->definition['metrics'],
				static function ( $meta ) {
					return empty( $meta['internal'] );
				}
			);
			$metrics          = $args['metrics'] ? $args['metrics'] : array_keys( $public_metrics );
			$allowed          = array_intersect( $metrics, array_keys( (array) $this->definition['metrics'] ) );
			$allowed          = array_values(
				array_filter(
					$allowed,
					function ( $metric ) {
						return empty( $this->definition['metrics'][ $metric ]['internal'] );
					}
				)
			);
			$requested        = $allowed;
			$filtered_sources = array();
			if ( '' !== (string) $args['product'] ) {
				foreach ( $allowed as $position => $metric ) {
					$source = sanitize_key( $this->definition['metrics'][ $metric ]['filter_sources']['product'] ?? '' );
					if ( $source && ! empty( $this->definition['metrics'][ $source ]['internal'] ) ) {
						$filtered_sources[ $metric ] = $source;
						$allowed[ $position ]        = $source;
					}
				}
			}
			$aliases = array();
			foreach ( $allowed as $position => $metric ) {
				$source = sanitize_key( $this->definition['metrics'][ $metric ]['alias_of'] ?? '' );
				if ( ! $source ) {
					$derived = (array) ( $this->definition['metrics'][ $metric ]['derived_from'] ?? array() );
					$source  = 1 === count( $derived ) ? sanitize_key( reset( $derived ) ) : '';
				}
				if ( $source && isset( $this->definition['metrics'][ $source ] ) ) {
					$aliases[ $metric ]   = $source;
					$allowed[ $position ] = $source;
				}
			}
			$allowed       = array_values( array_unique( $allowed ) );
			$ratio_sources = $this->ratio_sources( $allowed );
			if ( $ratio_sources ) {
				$sources = array();
				foreach ( $ratio_sources as $parts ) {
					$sources[] = $parts['numerator'];
					$sources[] = $parts['denominator']; }
				$allowed = array_values( array_unique( array_merge( array_values( $allowed ), $sources ) ) );
			}
			if ( '' !== (string) $args['product'] ) {
				foreach ( $allowed as $position => $metric ) {
					$source = sanitize_key( $this->definition['metrics'][ $metric ]['filter_sources']['product'] ?? '' );
					if ( $source && ! empty( $this->definition['metrics'][ $source ]['internal'] ) ) {
						$filtered_sources[ $metric ] = $source;
						$allowed[ $position ]        = $source;
					}
				}
				foreach ( $ratio_sources as $ratio_metric => $parts ) {
					$ratio_sources[ $ratio_metric ]['numerator']   = $filtered_sources[ $parts['numerator'] ] ?? $parts['numerator'];
					$ratio_sources[ $ratio_metric ]['denominator'] = $filtered_sources[ $parts['denominator'] ] ?? $parts['denominator'];
				}
				$allowed = array_values( array_unique( $allowed ) );
			}
			if ( ! $allowed ) {
				return $this->empty_result( $args ); }
			$bucket  = $this->bucket( $args['from'], $args['to'] );
			$where   = array( 'integration=%s', 'bucket=%s', 'bucket_start BETWEEN %s AND %s' );
			$params  = array( $this->integration, $bucket, $args['from'] . ' 00:00:00', $args['to'] . ' 23:59:59' );
			$marks   = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
			$where[] = "metric IN ($marks)";
			$params  = array_merge( $params, $allowed );
			if ( $args['currency'] ) {
				$where[]  = 'currency=%s';
				$params[] = $args['currency']; }
			foreach ( array_keys( $this->dimension_map() ) as $dimension ) {
				if ( '' !== (string) ( $args[ $dimension ] ?? '' ) ) {
					$path      = 'product' === $dimension ? 'product_id' : ( 'related_product' === $dimension ? 'related_product_id' : ( 'category' === $dimension ? 'category_id' : ( 'user' === $dimension ? 'user_id' : ( 'role' === $dimension ? 'user_role' : ( 'status' === $dimension ? 'payment_status' : $dimension ) ) ) ) );
					$params[]  = $path;
					$condition = $this->dimension_value_condition( sanitize_text_field( $args[ $dimension ] ), $params );
					$where[]   = 'EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'ic_stats_dimensions d WHERE d.integration=' . $wpdb->prefix . 'ic_stats_aggregates.integration AND d.dimension_hash=' . $wpdb->prefix . 'ic_stats_aggregates.dimension_hash AND d.dimension_key=%s AND ' . $condition . ')'; }
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic framework table and allowlisted clauses use prepared values.
			$aggregate_sql = ' FROM ' . $wpdb->prefix . 'ic_stats_aggregates WHERE ' . implode( ' AND ', $where );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic framework table and allowlisted clauses use prepared values.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT metric,bucket_start,'' dimensions,currency,SUM(value) value,SUM(count) count" . $aggregate_sql . ' GROUP BY metric,bucket_start,currency ORDER BY metric,bucket_start ASC', $params ), ARRAY_A );
			// The summary has its OWN aggregate and is never derived from the capped
			// series above. Summing the 10,000-row slice silently understated every
			// headline total on any store large enough to hit the cap -- a real
			// catalog reported 2,896 successful payments against 5,131 and
			// 247,901.56 of earnings against 622,476.05. Grouping only by metric and
			// currency returns a handful of rows whatever the series volume, so the
			// cap can stay where it belongs: on the chart, which nobody plots 57,000
			// points on.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic framework table and allowlisted clauses use prepared values.
			$summary_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT metric,currency,SUM(value) value,SUM(count) count' . $aggregate_sql . ' GROUP BY metric,currency', $params ), ARRAY_A );
			$breakdowns   = $this->breakdowns( $args, $filtered_sources );
			$summary      = array();
			$series       = array();
			foreach ( $allowed as $metric ) {
				$summary[ $metric ] = array(
					'value'    => 0,
					'count'    => 0,
					'currency' => array(),
				);
				$series[ $metric ]  = array(); }
			foreach ( $allowed as $metric ) {
				if ( 'live_projection' === ( $this->definition['metrics'][ $metric ]['source_mode'] ?? '' ) ) {
					if ( $args['from'] > gmdate( 'Y-m-d' ) || $args['to'] < gmdate( 'Y-m-d' ) ) {
						$summary[ $metric ]['quality'] = 'not_applicable_live_current';
						continue;
					}
					$live = apply_filters( 'ic_stats_source_query_' . $this->integration . '_' . $metric, array(), $args );
					if ( is_array( $live ) ) {
						$summary[ $metric ]['value']         = (float) ( $live['value'] ?? 0 );
						$summary[ $metric ]['display_value'] = $summary[ $metric ]['value'];
						$summary[ $metric ]['count']         = 1;
						$summary[ $metric ]['enhanced']      = absint( $live['enhanced'] ?? 0 );
						$summary[ $metric ]['quality']       = sanitize_key( $live['quality'] ?? 'live-source' ); }
				}
			}
			foreach ( $summary_rows as $summary_row ) {
				$metric = (string) $summary_row['metric'];
				if ( ! isset( $summary[ $metric ] ) ) {
					continue; }
				$value                        = (float) $summary_row['value'];
				$currency                     = (string) $summary_row['currency'];
				$summary[ $metric ]['value'] += $value;
				$summary[ $metric ]['count'] += (int) $summary_row['count'];
				if ( $currency ) {
					$summary[ $metric ]['currency'][ $currency ] = ( $summary[ $metric ]['currency'][ $currency ] ?? 0 ) + $value; }
			}
			foreach ( $rows as $row ) {
				$metric              = $row['metric'];
				$value               = (float) $row['value'];
				$count               = (int) $row['count'];
				$currency            = (string) $row['currency'];
				$calculation         = $this->definition['metrics'][ $metric ]['calculation'] ?? 'count';
				$display_value       = 'average' === $calculation ? ( $count > 0 ? $value / $count : 0 ) : $value;
				$dimensions          = json_decode( (string) $row['dimensions'], true );
				$dimensions          = is_array( $dimensions ) ? $dimensions : array();
				$series[ $metric ][] = array(
					'period'        => $row['bucket_start'],
					'value'         => $value,
					'display_value' => $display_value,
					'count'         => $count,
					'currency'      => $currency,
					'dimensions'    => $dimensions,
				);
			}
			foreach ( array( 'unique_registered_customers', 'returning_registered_customers' ) as $customer_metric ) {
				if ( ! isset( $summary[ $customer_metric ] ) ) {
					continue; }
				$users = array();
				foreach ( $rows as $row ) {
					if ( $customer_metric !== $row['metric'] && 'registered_customer_orders' !== $row['metric'] ) {
						continue; }
					$dimensions = json_decode( (string) $row['dimensions'], true );
					$user_id    = absint( is_array( $dimensions ) ? ( $dimensions['user_id'] ?? 0 ) : 0 );
					if ( $user_id ) {
						$users[ $user_id ] = ( $users[ $user_id ] ?? 0 ) + (int) $row['count']; }
				}
				if ( 'returning_registered_customers' === $customer_metric ) {
					$users = array_filter(
						$users,
						static function ( $count ) {
							return $count > 1;
						}
					); }
				$summary[ $customer_metric ]['value'] = count( $users );
				$summary[ $customer_metric ]['count'] = count( $users );
			}
			$distinct_truncated = false;
			foreach ( $allowed as $metric ) {
				$distinct_by = sanitize_key( (string) ( $this->definition['metrics'][ $metric ]['distinct_by'] ?? '' ) );
				if ( ! in_array( $distinct_by, array( 'session_hash', 'user_id', 'visit_hash' ), true ) ) {
					continue; }
				$distinct                    = $this->distinct_counts( $metric, $distinct_by, $bucket, $args );
				$summary[ $metric ]['value'] = (float) $distinct['total'];
				$summary[ $metric ]['count'] = (int) $distinct['total'];
				$series[ $metric ]           = $distinct['series'];
				if ( $distinct['truncated'] ) {
					$summary[ $metric ]['quality'] = 'partial_window';
					$distinct_truncated            = true; }
			}
			$ratio_mismatch = array();
			foreach ( $ratio_sources as $ratio_metric => $ratio_parts ) {
				if ( ! isset( $summary[ $ratio_parts['numerator'] ], $summary[ $ratio_parts['denominator'] ] ) ) {
					continue; }
				$denominator                             = (float) $summary[ $ratio_parts['denominator'] ]['value'];
				$numerator                               = (float) $summary[ $ratio_parts['numerator'] ]['value'];
				$ratio                                   = $denominator > 0 ? $numerator / $denominator : 0;
				$summary[ $ratio_metric ]['value']       = $ratio;
				$summary[ $ratio_metric ]['count']       = (int) $denominator;
				$summary[ $ratio_metric ]['numerator']   = $numerator;
				$summary[ $ratio_metric ]['denominator'] = $denominator;
				// Ratio metrics already contain their normalized value. Do not apply
				// the generic average display division by the denominator/count.
				$summary[ $ratio_metric ]['display_value'] = $ratio;
				$by_period                                 = array();
				foreach ( array(
					array( $ratio_parts['denominator'], 'denominator' ),
					array( $ratio_parts['numerator'], 'numerator' ),
				) as $ratio_source ) {
					list( $source_metric, $part ) = $ratio_source;
					foreach ( (array) ( $series[ $source_metric ] ?? array() ) as $source_row ) {
						$period = (string) ( $source_row['period'] ?? '' );
						if ( '' === $period ) {
							continue; }
						$by_period[ $period ][ $part ] = ( $by_period[ $period ][ $part ] ?? 0 ) + (float) ( $source_row['value'] ?? 0 );
					}
				}
				$series[ $ratio_metric ] = array();
				foreach ( $by_period as $period => $parts ) {
					$period_denominator        = (float) ( $parts['denominator'] ?? 0 );
					$period_numerator          = (float) ( $parts['numerator'] ?? 0 );
					$period_ratio              = $period_denominator > 0 ? $period_numerator / $period_denominator : 0;
					$series[ $ratio_metric ][] = array(
						'period'        => $period,
						'value'         => $period_ratio,
						'display_value' => $period_ratio,
						'count'         => (int) $period_denominator,
						'numerator'     => $period_numerator,
						'denominator'   => $period_denominator,
						'currency'      => '',
						'dimensions'    => array(),
					);
				}
				// A ratio whose two sides start collecting at materially different
				// times is not wrong, it is unanswerable for the early part of the
				// window - and it can legitimately exceed 1. Report that instead of
				// clamping the value, which would hide it.
				$numerator_first   = $this->first_period( $series[ $ratio_parts['numerator'] ] ?? array() );
				$denominator_first = $this->first_period( $series[ $ratio_parts['denominator'] ] ?? array() );
				if ( $numerator_first && $denominator_first && abs( strtotime( $numerator_first ) - strtotime( $denominator_first ) ) > $this->bucket_seconds( $bucket ) ) {
					$summary[ $ratio_metric ]['quality'] = 'partial_window';
					$ratio_mismatch[ $ratio_metric ]     = array(
						'numerator'        => $ratio_parts['numerator'],
						'numerator_from'   => $numerator_first,
						'denominator'      => $ratio_parts['denominator'],
						'denominator_from' => $denominator_first,
					);
				}
			}
			foreach ( $allowed as $metric ) {
				$calc = $this->definition['metrics'][ $metric ]['calculation'] ?? 'count';
				if ( 'ratio' === $calc ) {
					$summary[ $metric ]['display_value'] = (float) ( $summary[ $metric ]['value'] ?? 0 );
				} elseif ( 'average' === $calc && $summary[ $metric ]['count'] ) {
					$summary[ $metric ]['display_value'] = $summary[ $metric ]['value'] / $summary[ $metric ]['count'];
				} else {
					$summary[ $metric ]['display_value'] = $summary[ $metric ]['value']; }
			}
			foreach ( $aliases as $alias => $source ) {
				$summary[ $alias ] = $summary[ $source ] ?? array(
					'value'    => 0,
					'count'    => 0,
					'currency' => array(),
				);
				$series[ $alias ]  = $series[ $source ] ?? array();
			}
			foreach ( $filtered_sources as $public => $source ) {
				$summary[ $public ] = $summary[ $source ] ?? array(
					'value'         => 0,
					'count'         => 0,
					'currency'      => array(),
					'display_value' => 0,
				);
				$series[ $public ]  = $series[ $source ] ?? array();
				unset( $summary[ $source ], $series[ $source ] );
			}
			$injected = array_diff( array_keys( $summary ), array_values( $requested ) );
			foreach ( $injected as $internal_metric ) {
				unset( $summary[ $internal_metric ], $series[ $internal_metric ] );
			}
			$previous = array();
			if ( 'previous' === $args['compare'] ) {
				$length = max( 1, (int) ( ( strtotime( $args['to'] ) - strtotime( $args['from'] ) ) / DAY_IN_SECONDS ) + 1 );
				$prev   = $this->run(
					array_merge(
						$args,
						array(
							'from'    => gmdate( 'Y-m-d', strtotime( $args['from'] ) - ( $length * DAY_IN_SECONDS ) ),
							'to'      => gmdate( 'Y-m-d', strtotime( $args['from'] ) - DAY_IN_SECONDS ),
							'compare' => 'none',
						)
					)
				);
				foreach ( $requested as $metric ) {
					$previous[ $metric ] = 'empty' === ( $prev['quality']['state'] ?? '' ) ? array() : ( $prev['summary'][ $metric ] ?? array() ); }
			}
			return array(
				'integration' => $this->integration,
				'from'        => $args['from'],
				'to'          => $args['to'],
				'bucket'      => $bucket,
				'summary'     => $summary,
				'series'      => $series,
				'previous'    => $previous,
				'breakdowns'  => $breakdowns,
				'quality'     => apply_filters( 'ic_stats_query_quality', array_merge( array( 'state' => 'complete' ), $distinct_truncated ? array( 'distinct_window_truncated' => true ) : array(), $ratio_mismatch ? array( 'ratio_window_mismatch' => $ratio_mismatch ) : array() ), $this->integration, $args ),
			);
		}
		/**
		 * Earliest period present in a series.
		 *
		 * @param array $series Series rows.
		 * @return string Bucket start, or an empty string when the series is empty.
		 */
		private function first_period( $series ) {
			$first = '';
			foreach ( (array) $series as $row ) {
				$period = (string) ( $row['period'] ?? '' );
				if ( '' === $period ) {
					continue; }
				if ( '' === $first || strtotime( $period ) < strtotime( $first ) ) {
					$first = $period; }
			}
			return $first;
		}
		/**
		 * Length of one bucket in seconds.
		 *
		 * @param string $bucket Bucket resolution.
		 * @return int
		 */
		private function bucket_seconds( $bucket ) {
			if ( 'hour' === $bucket ) {
				return HOUR_IN_SECONDS; }
			return 'month' === $bucket ? 31 * DAY_IN_SECONDS : DAY_IN_SECONDS;
		}
		/**
		 * Build the SQL comparison for one dimension filter value.
		 *
		 * Every filter matches `dimension_value` by EQUALITY, with exactly one
		 * exception: the bare removed-identity sentinel. Each erasure mints its
		 * own token, so an anonymised row stores `removed:<token>` and a filter
		 * asking for the literal `removed` matches nothing on a real store --
		 * the direct consequence of "one row per removed user". The sentinel
		 * therefore expands to itself OR its own prefix, so "show me every
		 * erased customer" is expressible again.
		 *
		 * The LIKE pattern is built HERE, from the sentinel, and escaped with
		 * `esc_like()`. It is never interpolated from the request, so this adds
		 * no injection surface and no wildcard the caller controls: a request
		 * carrying `removed%` is not the sentinel and keeps plain equality.
		 *
		 * @param string $value  Dimension value being filtered on.
		 * @param array  $params Prepared parameters, appended by reference.
		 * @return string SQL condition against `d.dimension_value`.
		 */
		private function dimension_value_condition( $value, &$params ) {
			global $wpdb;
			$value    = (string) $value;
			$sentinel = function_exists( 'ic_stats_removed_identity_value' ) ? (string) ic_stats_removed_identity_value( 0 ) : 'removed';
			if ( '' !== $sentinel && $value === $sentinel && function_exists( 'ic_stats_is_removed_identity' ) && ic_stats_is_removed_identity( $value ) ) {
				$params[] = $value;
				$params[] = $wpdb->esc_like( $sentinel . ':' ) . '%';
				return '( d.dimension_value=%s OR d.dimension_value LIKE %s )';
			}
			$params[] = $value;
			return 'd.dimension_value=%s';
		}
		/**
		 * Query independently paged metric/dimension breakdowns.
		 *
		 * @param array $args             Normalized query arguments.
		 * @param array $filtered_sources Public-to-internal product sources.
		 * @return array
		 */
		private function breakdowns( $args, $filtered_sources ) {
			global $wpdb;
			$out = array();
			foreach ( (array) $args['breakdowns'] as $public_metric => $dimensions ) {
				$public_metric = sanitize_key( $public_metric );
				$metric        = $filtered_sources[ $public_metric ] ?? $public_metric;
				$alias_source  = sanitize_key( $this->definition['metrics'][ $metric ]['alias_of'] ?? '' );
				$derived       = (array) ( $this->definition['metrics'][ $metric ]['derived_from'] ?? array() );
				if ( ! $alias_source && 1 === count( $derived ) ) {
					$alias_source = sanitize_key( reset( $derived ) );
				}
				if ( $alias_source ) {
					$metric = $alias_source;
				}
				if ( ! isset( $this->definition['metrics'][ $metric ] ) || ! empty( $this->definition['metrics'][ $public_metric ]['internal'] ) ) {
					continue;
				}
				foreach ( (array) $dimensions as $dimension ) {
					$dimension = sanitize_key( $dimension );
					if ( ! $dimension ) {
						continue;
					}
					$where  = array( 'a.integration=%s', 'a.bucket=%s', 'a.bucket_start BETWEEN %s AND %s', 'a.metric=%s', 'bd.dimension_key=%s' );
					$params = array( $this->integration, $this->bucket( $args['from'], $args['to'] ), $args['from'] . ' 00:00:00', $args['to'] . ' 23:59:59', $metric, $dimension );
					if ( $args['currency'] ) {
						$where[]  = 'a.currency=%s';
						$params[] = $args['currency'];
					}
					foreach ( $this->dimension_map() as $argument => $key ) {
						if ( '' === (string) ( $args[ $argument ] ?? '' ) ) {
							continue;
						}
						$params[]  = $key;
						$condition = $this->dimension_value_condition( (string) $args[ $argument ], $params );
						$where[]   = 'EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'ic_stats_dimensions fd WHERE fd.integration=a.integration AND fd.dimension_hash=a.dimension_hash AND fd.dimension_key=%s AND ' . str_replace( 'd.dimension_value', 'fd.dimension_value', $condition ) . ')';
					}
					$page     = max( 1, absint( $args['page'] ) );
					$per_page = max( 1, min( 200, absint( $args['per_page'] ) ) );
					$offset   = ( $page - 1 ) * $per_page;
					$sql      = 'SELECT bd.dimension_value `key`,a.currency,SUM(a.value) value,SUM(a.count) count,MAX(a.bucket_start) last_period FROM ' . $wpdb->prefix . 'ic_stats_aggregates a INNER JOIN ' . $wpdb->prefix . 'ic_stats_dimensions bd ON bd.integration=a.integration AND bd.dimension_hash=a.dimension_hash WHERE ' . implode( ' AND ', $where ) . ' GROUP BY bd.dimension_value,a.currency ORDER BY value DESC LIMIT %d OFFSET %d';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework tables and allowlisted clauses use prepared values.
					$rows     = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( $params, array( $per_page + 1, $offset ) ) ), ARRAY_A );
					$has_more = count( $rows ) > $per_page;
					$items    = array();
					foreach ( array_slice( $rows, 0, $per_page ) as $row ) {
						$items[] = array(
							'key'         => (string) $row['key'],
							'value'       => (float) $row['value'],
							'count'       => (int) $row['count'],
							'currency'    => (string) $row['currency'],
							'last_period' => (string) $row['last_period'],
						);
					}
					$out[ $public_metric ][ $dimension ] = array(
						'rows'     => $items,
						'page'     => $page,
						'per_page' => $per_page,
						'has_more' => $has_more,
						'complete' => 1 === $page && ! $has_more,
					);
				}
			}
			return $out;
		}

		/** Return query argument to stored dimension keys. */
		private function dimension_map() {
			return (array) apply_filters(
				'ic_stats_query_dimension_map',
				array(
					'catalog'          => 'catalog',
					'product'          => 'product_id',
					'related_product'  => 'related_product_id',
					'category'         => 'category_id',
					'user'             => 'user_id',
					'role'             => 'user_role',
					'gateway'          => 'gateway',
					'status'           => 'payment_status',
					'order_status'     => 'order_status',
					'order_value_type' => 'order_value_type',
					'stage'            => 'stage',
					'target'           => 'target',
					'object'           => 'object_id',
					'post'             => 'post_id',
					'taxonomy'         => 'taxonomy',
					'source'           => 'source',
					'outcome'          => 'outcome',
					'page_type'        => 'page_type',
					'search_outcome'   => 'search_outcome',
					'query_class'      => 'query_class',
					'query_key'        => 'query_key',
					'query_value'      => 'query_value',
					'bot_family'       => 'bot_family',
				)
			);
		}
		/**
		 * Count distinct event identities for one metric.
		 *
		 * Distinct counts are read from the raw events table rather than from the
		 * aggregates, so no per-session dimension ever enters the rollups.
		 *
		 * @param string $metric Metric key.
		 * @param string $column Distinct column: session_hash or user_id.
		 * @param string $bucket Bucket resolution.
		 * @param array  $args   Normalized query arguments.
		 * @return array Total, per-period series and window truncation flag.
		 */
		private function distinct_counts( $metric, $column, $bucket, $args ) {
			global $wpdb;
			$facts         = $wpdb->prefix . 'ic_stats_unique_facts';
			$fact_column   = 'visit_hash' === $column ? 'visit_hash' : 'identity_hash';
			$format        = 'month' === $bucket ? '%Y-%m-01 00:00:00' : '%Y-%m-%d 00:00:00';
			$from          = $args['from'] . ' 00:00:00';
			$to            = $args['to'] . ' 23:59:59';
			$where         = 'f.integration=%s AND f.metric=%s AND f.first_seen_gmt BETWEEN %s AND %s';
			$params        = array( $this->integration, $metric, $from, $to );
			$dimension_map = (array) apply_filters(
				'ic_stats_query_dimension_map',
				array(
					'catalog'          => 'catalog',
					'product'          => 'product_id',
					'related_product'  => 'related_product_id',
					'category'         => 'category_id',
					'user'             => 'user_id',
					'role'             => 'user_role',
					'gateway'          => 'gateway',
					'status'           => 'payment_status',
					'order_status'     => 'order_status',
					'order_value_type' => 'order_value_type',
					'stage'            => 'stage',
					'target'           => 'target',
					'object'           => 'object_id',
					'post'             => 'post_id',
					'taxonomy'         => 'taxonomy',
					'source'           => 'source',
					'outcome'          => 'outcome',
					'page_type'        => 'page_type',
					'search_outcome'   => 'search_outcome',
					'query_class'      => 'query_class',
					'query_key'        => 'query_key',
					'query_value'      => 'query_value',
					'bot_family'       => 'bot_family',
				)
			);
			foreach ( $dimension_map as $argument => $dimension ) {
				if ( '' !== (string) ( $args[ $argument ] ?? '' ) ) {
					$params[]  = $dimension;
					$condition = $this->dimension_value_condition( (string) $args[ $argument ], $params );
					$where    .= ' AND EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'ic_stats_dimensions d WHERE d.integration=f.integration AND d.dimension_hash=f.dimension_hash AND d.dimension_key=%s AND ' . $condition . ')';
				}
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned fact table and allowlisted clauses use prepared values.
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT f.' . $fact_column . ') FROM ' . $facts . ' f WHERE ' . $where, $params ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned fact table and allowlisted clauses use prepared values.
			$fact_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DATE_FORMAT(f.first_seen_gmt,%s) period,COUNT(DISTINCT f.' . $fact_column . ') distinct_count FROM ' . $facts . ' f WHERE ' . $where . ' GROUP BY DATE_FORMAT(f.first_seen_gmt,%s) ORDER BY period ASC', array_merge( array( $format ), $params, array( $format ) ) ), ARRAY_A );
			$series    = array();
			foreach ( (array) $fact_rows as $fact_row ) {
				$count    = (int) $fact_row['distinct_count'];
				$series[] = array(
					'period'        => $fact_row['period'],
					'value'         => (float) $count,
					'display_value' => (float) $count,
					'count'         => $count,
					'currency'      => '',
					'dimensions'    => array(),
				);
			}
			return array(
				'total'     => $total,
				'series'    => $series,
				'truncated' => strtotime( $from ) < strtotime( '-25 months' ),
			);
		}
		/**
		 * Resolve the source metrics of every requested ratio metric.
		 *
		 * Only one level of indirection is supported: a ratio source may not be a
		 * ratio metric itself, may not be the ratio metric, and must be declared.
		 *
		 * @param array $metrics Allowed metric keys.
		 * @return array Map of ratio metric key to numerator/denominator keys.
		 */
		private function ratio_sources( $metrics ) {
			$sources = array();
			foreach ( (array) $metrics as $metric ) {
				$meta = (array) ( $this->definition['metrics'][ $metric ] ?? array() );
				if ( 'ratio' !== ( $meta['calculation'] ?? '' ) ) {
					continue; }
				$numerator   = sanitize_key( (string) ( $meta['numerator_metric'] ?? '' ) );
				$denominator = sanitize_key( (string) ( $meta['denominator_metric'] ?? '' ) );
				if ( ! $this->is_ratio_source( $metric, $numerator ) || ! $this->is_ratio_source( $metric, $denominator ) ) {
					continue; }
				$sources[ $metric ] = array(
					'numerator'   => $numerator,
					'denominator' => $denominator,
				);
			}
			return $sources;
		}
		/**
		 * Check whether one metric may act as a ratio source for another.
		 *
		 * @param string $metric Ratio metric key.
		 * @param string $source Candidate source metric key.
		 * @return bool
		 */
		private function is_ratio_source( $metric, $source ) {
			if ( ! $source || $source === $metric || ! isset( $this->definition['metrics'][ $source ] ) ) {
				return false; }
			return 'ratio' !== ( $this->definition['metrics'][ $source ]['calculation'] ?? '' );
		}
		/**
		 * Normalize allowlisted query arguments.
		 *
		 * @param array $args Raw query arguments.
		 * @return array
		 */
		private function normalize_args( $args ) {
			$today = gmdate( 'Y-m-d' );
			$from  = ! empty( $args['from'] ) ? sanitize_text_field( $args['from'] ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			$to    = ! empty( $args['to'] ) ? sanitize_text_field( $args['to'] ) : $today;
			$out   = array(
				'from'       => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from : gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'to'         => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to : $today,
				'metrics'    => array_map( 'sanitize_key', (array) ( $args['metrics'] ?? array() ) ),
				'currency'   => sanitize_text_field( isset( $args['currency'] ) ? $args['currency'] : '' ),
				'compare'    => 'previous' === ( $args['compare'] ?? '' ) ? 'previous' : 'none',
				'page'       => max( 1, absint( $args['page'] ?? 1 ) ),
				'per_page'   => min( 100, max( 1, absint( $args['per_page'] ?? 25 ) ) ),
				'breakdowns' => array(),
			);
			foreach ( (array) ( $args['breakdowns'] ?? array() ) as $metric => $dimensions ) {
				$out['breakdowns'][ sanitize_key( $metric ) ] = array_values( array_filter( array_map( 'sanitize_key', (array) $dimensions ) ) );
			}
			foreach ( array_keys( $this->dimension_map() ) as $key ) {
				$out[ $key ] = sanitize_text_field( $args[ $key ] ?? '' );
			} return $out;
		}
		/**
		 * Select a bucket resolution for a date range.
		 *
		 * @param string $from Start date.
		 * @param string $to   End date.
		 * @return string
		 */
		private function bucket( $from, $to ) {
			$days     = (int) floor( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
			$age_days = (int) floor( ( time() - strtotime( $to . ' 23:59:59' ) ) / DAY_IN_SECONDS );
			if ( $days <= 2 && $age_days <= 90 ) {
				return 'hour';
			}
			return ( $days <= 25 * 31 && $age_days <= 25 * 31 ) ? 'day' : 'month'; }
		/**
		 * Build an empty query result.
		 *
		 * @param array $args Normalized query arguments.
		 * @return array
		 */
		private function empty_result( $args ) {
			return array(
				'integration' => $this->integration,
				'from'        => $args['from'] ?? '',
				'to'          => $args['to'] ?? '',
				'bucket'      => 'day',
				'summary'     => array(),
				'series'      => array(),
				'previous'    => array(),
				'page'        => 1,
				'per_page'    => 25,
				'quality'     => array( 'state' => 'empty' ),
			); }
	}
}
