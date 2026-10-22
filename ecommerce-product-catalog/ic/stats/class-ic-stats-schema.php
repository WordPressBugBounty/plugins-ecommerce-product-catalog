<?php
/**
 * Statistics framework schema installer.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! function_exists( 'ic_stats_install_schema' ) ) {
	/**
	 * Install or verify the statistics tables.
	 *
	 * A table that had to be CREATED here means the data that used to be in it
	 * is gone -- a raw `DROP TABLE`, a restore predating the stats tables, a
	 * migration that moved `wp_options` but not the custom tables. None of
	 * those fire `ic_stats_aggregates_changed`, so the cached
	 * `ic_stats_has_data()` answer would keep reporting a history that no
	 * longer exists and the consumer that reacts to it would not schedule its
	 * backfill until the transient expired. This is the only place the loss is
	 * observable, so the cache is dropped here -- and ONLY when a table was
	 * actually created, because this function runs on every registration and a
	 * `delete_transient()` on every page load is not free.
	 *
	 * @return bool
	 */
	function ic_stats_install_schema() {
		global $wpdb;
		if ( ! $wpdb || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
			return false; }
		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;
		$created = false;
		$tables  = array(
			"CREATE TABLE {$prefix}ic_stats_events (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				integration varchar(64) NOT NULL,
				event_key varchar(64) NOT NULL,
				occurred_at_gmt datetime NOT NULL,
				recorded_at_gmt datetime NOT NULL,
				timestamp_quality varchar(32) NOT NULL DEFAULT 'live',
				timestamp_provenance varchar(64) NOT NULL DEFAULT 'runtime',
				dedupe_key char(64) NOT NULL,
				dimension_hash char(64) NOT NULL DEFAULT '',
				session_hash char(64) NULL,
				identity_provider varchar(64) NOT NULL DEFAULT '',
				persistence varchar(16) NOT NULL DEFAULT 'none',
				identity_quality varchar(32) NOT NULL DEFAULT 'unknown',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_role varchar(64) NOT NULL DEFAULT '',
				currency varchar(12) NOT NULL DEFAULT '',
				dimensions longtext NULL,
				values_json longtext NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY dedupe (integration,dedupe_key),
				KEY event_time (integration,event_key,occurred_at_gmt),
				KEY dimension_lookup (integration,occurred_at_gmt,dimension_hash)
			) {$charset};",
			"CREATE TABLE {$prefix}ic_stats_event_metrics (
				event_id bigint(20) unsigned NOT NULL,
				metric varchar(64) NOT NULL,
				processed tinyint(1) NOT NULL DEFAULT 0,
				lease_until datetime NULL,
				attempts int unsigned NOT NULL DEFAULT 0,
				last_error text NULL,
				PRIMARY KEY  (event_id,metric),
				KEY pending (processed,lease_until)
			) {$charset};",
			"CREATE TABLE {$prefix}ic_stats_aggregates (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				integration varchar(64) NOT NULL,
				metric varchar(64) NOT NULL,
				bucket varchar(8) NOT NULL,
				bucket_start datetime NOT NULL,
				dimension_hash char(64) NOT NULL,
				dimensions longtext NULL,
				currency varchar(12) NOT NULL DEFAULT '',
				value decimal(20,6) NOT NULL DEFAULT 0,
				count bigint unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY aggregate_key (integration,metric,bucket,bucket_start,dimension_hash,currency),
				KEY dimension_query (integration,metric,bucket,bucket_start,dimension_hash)
			) {$charset};",
			"CREATE TABLE {$prefix}ic_stats_dimensions (
				integration varchar(64) NOT NULL,
				dimension_hash char(64) NOT NULL,
				dimension_key varchar(64) NOT NULL,
				dimension_value varchar(191) NOT NULL,
				first_seen_gmt datetime NOT NULL,
				last_seen_gmt datetime NOT NULL,
				PRIMARY KEY  (integration,dimension_hash,dimension_key),
				KEY value_lookup (integration,dimension_key,dimension_value,dimension_hash),
				KEY recent_lookup (integration,dimension_key,last_seen_gmt)
			) {$charset};",
			"CREATE TABLE {$prefix}ic_stats_unique_facts (
				integration varchar(64) NOT NULL,
				metric varchar(64) NOT NULL,
				period date NOT NULL,
				dimension_hash char(64) NOT NULL,
				identity_hash char(64) NOT NULL,
				visit_hash char(64) NOT NULL DEFAULT '',
				first_seen_gmt datetime NOT NULL,
				last_seen_gmt datetime NOT NULL,
				occurrence_count bigint unsigned NOT NULL DEFAULT 1,
				PRIMARY KEY  (integration,metric,period,identity_hash,visit_hash,dimension_hash),
				KEY metric_period (integration,metric,period,dimension_hash),
				KEY identity_history (integration,metric,identity_hash,first_seen_gmt),
				KEY identity_lookup (identity_hash)
			) {$charset};",
		);
		/**
		 * Filter additive schema statements supplied by independently loaded Stats modules.
		 *
		 * @param string[] $tables Base schema statements.
		 */
		$tables          = apply_filters( 'ic_stats_schema_tables', $tables );
		$required_tables = apply_filters( 'ic_stats_schema_required_tables', array( 'ic_stats_events', 'ic_stats_event_metrics', 'ic_stats_aggregates', 'ic_stats_dimensions', 'ic_stats_unique_facts' ) );
		$columns         = array(
			'ic_stats_events'       => array( 'dimension_hash char(64) NOT NULL DEFAULT ""', 'KEY dimension_lookup (integration,occurred_at_gmt,dimension_hash)' ),
			'ic_stats_aggregates'   => array( 'updated_at_gmt datetime NULL', 'KEY query_index (integration,metric,bucket,bucket_start,currency)', 'KEY dimension_rewrite (dimension_hash)' ),
			'ic_stats_unique_facts' => array( 'KEY identity_lookup (identity_hash)' ),
		);
		$columns         = apply_filters( 'ic_stats_schema_columns', $columns );
		$schema_version  = 4;
		$contract_hash   = hash( 'sha256', serialize( array( $tables, $required_tables, $columns ) ) );
		$installed       = get_option( 'ic_stats_schema_version', array() );
		if ( is_array( $installed ) && (int) ( $installed['version'] ?? 0 ) === $schema_version && ! empty( $installed['contracts'][ $contract_hash ] ) ) {
			return true;
		}
		foreach ( $tables as $sql ) {
			preg_match( '/CREATE TABLE ([^ ]+)/', $sql, $match );
			$name = isset( $match[1] ) ? trim( $match[1], '`' ) : '';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema verification uses a fixed metadata query.
			if ( $name && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) === $name ) {
				continue;
			}
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			dbDelta( $sql );
			$created = true;
		} foreach ( $required_tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema verification uses a fixed metadata query.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . $table ) ) !== $prefix . $table ) {
				return false;
			}
		}
		foreach ( $columns as $table => $definitions ) {
			$name = $prefix . $table;
			foreach ( $definitions as $definition ) {
				if ( 0 === strpos( $definition, 'KEY ' ) ) {
					preg_match( '/^KEY ([a-z0-9_]+)/', $definition, $index_match );
					$index = $index_match[1] ?? '';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework-owned identifiers and prepared index name.
					$exists = $index ? $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$name} WHERE Key_name=%s", $index ) ) : false;
					if ( $index && ! $exists ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Additive fixed framework index definition.
						if ( false === $wpdb->query( "ALTER TABLE {$name} ADD {$definition}" ) ) {
							if ( $created && function_exists( 'ic_stats_flush_all_has_data' ) ) {
								ic_stats_flush_all_has_data();
							}
							return false;
						}
					}
					continue;
				}
				preg_match( '/^([a-z0-9_]+)/', $definition, $match );
				$column = $match[1] ?? '';
				if ( ! $column ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema introspection uses framework-owned identifiers and prepared values.
				$exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$name} LIKE %s", $column ) );
				if ( ! $exists ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Additive migration uses validated framework schema definitions.
					if ( false === $wpdb->query( "ALTER TABLE {$name} ADD COLUMN {$definition}" ) ) {
						if ( $created && function_exists( 'ic_stats_flush_all_has_data' ) ) {
							ic_stats_flush_all_has_data();
						}
						return false;
					}
				}
			}
		}
		foreach ( array( 'timestamp_quality varchar(32) NOT NULL DEFAULT "live"', 'timestamp_provenance varchar(64) NOT NULL DEFAULT "runtime"' ) as $definition ) {
			$column = strtok( $definition, ' ' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema introspection uses framework-owned identifiers and prepared values.
			if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$prefix}ic_stats_events LIKE %s", $column ) ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Additive migration uses validated framework schema definitions.
				if ( false === $wpdb->query( "ALTER TABLE {$prefix}ic_stats_events ADD COLUMN {$definition}" ) ) {
					if ( $created && function_exists( 'ic_stats_flush_all_has_data' ) ) {
						ic_stats_flush_all_has_data();
					}
					return false;
				}
			}
		}
		if ( $created && function_exists( 'ic_stats_flush_all_has_data' ) ) {
			ic_stats_flush_all_has_data();
		}
		if ( ! is_array( $installed ) || (int) ( $installed['version'] ?? 0 ) !== $schema_version ) {
			$installed = array(
				'version'   => $schema_version,
				'contracts' => array(),
			);
		}
		if ( ! isset( $installed['contracts'] ) || ! is_array( $installed['contracts'] ) ) {
			$installed['contracts'] = array();
		}
		$installed['contracts'][ $contract_hash ] = true;
		update_option( 'ic_stats_schema_version', $installed, false );
		return true;
	}
}
