<?php
/**
 * Database helper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/* phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed */

/**
 * Initializes a shared database helper instance.
 *
 * @param string $name Table suffix.
 * @param array  $columns Table columns.
 * @param array  $indexed Indexed columns.
 * @param array  $unique_index Unique index definitions.
 *
 * @return IC_DB|false
 */
if ( ! function_exists( 'ic_db_init' ) ) {
	/**
	 * Initializes a shared database helper instance.
	 *
	 * @param string $name Table suffix.
	 * @param array  $columns Table columns.
	 * @param array  $indexed Indexed columns.
	 * @param array  $unique_index Unique index definitions.
	 *
	 * @return IC_DB|false
	 */
	function ic_db_init( $name, $columns = array(), $indexed = array(), $unique_index = array() ) {
		global $implecode_db;

		if ( ! empty( $implecode_db[ $name ] ) ) {
			return $implecode_db[ $name ];
		}

		$implecode_db[ $name ] = new IC_DB( $name, $columns, $indexed, $unique_index );
		if ( empty( $columns ) && ! $implecode_db[ $name ]->exists() ) {
			return false;
		}

		return $implecode_db[ $name ];
	}
}

/**
 * Wraps CRUD access for framework-owned tables.
 */
if ( ! class_exists( 'IC_DB', false ) ) {
	class IC_DB {
	/**
	 * Public API version.
	 *
	 * @var int
	 */
	const API_VERSION = 2;

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Table columns.
	 *
	 * @var array
	 */
	private $columns;

	/**
	 * Indexed columns.
	 *
	 * @var array
	 */
	private $indexed;

	/**
	 * Unique index definitions.
	 *
	 * @var array
	 */
	private $unique;

	/**
	 * Cached table existence state.
	 *
	 * @var bool|string
	 */
	private $exists;

	/**
	 * Problems found during the last schema synchronization.
	 *
	 * @var array
	 */
	private $schema_problems = array();

	/**
	 * Initializes the table helper.
	 *
	 * @param string $name Table suffix.
	 * @param array  $columns Table columns.
	 * @param array  $indexed Indexed columns.
	 * @param array  $unique_index Unique index definitions.
	 */
	public function __construct( $name, $columns = array(), $indexed = array(), $unique_index = array() ) {
		$this->name    = $name;
		$this->columns = $columns;
		$this->indexed = $indexed;
		$this->unique  = $unique_index;
		$this->exists  = 'not_checked';
	}

	/**
	 * Finds rows matching the provided conditions.
	 *
	 * @param array    $data Search conditions.
	 * @param array    $select Selected columns.
	 * @param string   $logic Join logic between conditions.
	 * @param int|null $page Page number.
	 * @param int|null $per_page Rows per page.
	 *
	 * @return array|false
	 */
	public function find( $data, $select = array(), $logic = 'AND', $page = null, $per_page = null ) {
		if ( ! $this->create_db() ) {
			return false;
		}

		global $wpdb;

		$wp_table_name   = $this->name();
		$serialized_data = array_map( 'maybe_serialize', $data );
		$logic           = in_array( strtoupper( $logic ), array( 'AND', 'OR' ), true ) ? strtoupper( $logic ) : 'AND';
		$select_sql      = '*';

		if ( ! empty( $select ) ) {
			$select_sql = implode( ', ', array_map( 'sanitize_key', $select ) );
		}

		$query  = "SELECT {$select_sql} FROM {$wp_table_name}";
		$search = ' WHERE (';

		foreach ( $serialized_data as $data_name => $value ) {
			if ( ' WHERE (' !== $search ) {
				$search .= ' ' . $logic . ' ';
			}

			$search .= sanitize_key( $data_name ) . ' = %s';
		}

		$query .= $search . ') ORDER BY id DESC';

		if ( null !== $page && null !== $per_page ) {
			$page     = max( 1, absint( $page ) );
			$per_page = max( 1, absint( $per_page ) );
			$offset   = ( $page - 1 ) * $per_page;
			$query   .= ' LIMIT %d OFFSET %d';

			$params   = array_values( $serialized_data );
			$params[] = $per_page;
			$params[] = $offset;
		} else {
			$params = array_values( $serialized_data );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL string is assembled from sanitized identifiers and prepared values.
		$prepared = $wpdb->prepare( $query, $params );

		if ( 1 === count( $select ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework helper intentionally executes the prepared query directly.
			$results = $wpdb->get_col( $prepared );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Framework helper intentionally executes the prepared query directly.
			$results = $wpdb->get_results( $prepared, ARRAY_A );
		}

		if ( empty( $results[0] ) ) {
			return false;
		}

		return $results;
	}

	/**
	 * Inserts a row into the table.
	 *
	 * @param array $data Row data.
	 *
	 * @return int|false
	 */
	public function insert( $data ) {
		if ( ! $this->create_db() ) {
			return false;
		}

		global $wpdb;

		$wp_table_name = $this->name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Framework helper writes directly to its own table.
		$wpdb->insert( $wp_table_name, array_map( 'maybe_serialize', $data ) );

		return $wpdb->insert_id;
	}

	/**
	 * Updates rows in the table.
	 *
	 * @param int|array $id Row ID or where clause.
	 * @param array     $data Updated data.
	 *
	 * @return int|false
	 */
	public function update( $id, $data ) {
		if ( ! $this->create_db() ) {
			return false;
		}

		global $wpdb;

		$wp_table_name = $this->name();
		$where         = is_array( $id ) ? $id : array( 'id' => $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework helper writes directly to its own table.
		return $wpdb->update( $wp_table_name, array_map( 'maybe_serialize', $data ), $where );
	}

	/**
	 * Deletes rows from the table.
	 *
	 * @param array $where Delete conditions.
	 *
	 * @return int|false
	 */
	public function delete( $where ) {
		if ( ! $this->create_db() ) {
			return false;
		}

		global $wpdb;

		$wp_table_name = $this->name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Framework helper writes directly to its own table.
		return $wpdb->delete( $wp_table_name, $where );
	}

	/**
	 * Returns all rows from the table.
	 *
	 * @param int|null $page Page number.
	 * @param int|null $per_page Rows per page.
	 *
	 * @return array
	 */
	public function all( $page = null, $per_page = null ) {
		if ( ! $this->create_db() ) {
			return array();
		}

		global $wpdb;

		$wp_table_name = $this->name();
		$sql           = "SELECT * FROM {$wp_table_name} ORDER BY id DESC";

		if ( null !== $page && null !== $per_page ) {
			$page     = max( 1, absint( $page ) );
			$per_page = max( 1, absint( $per_page ) );
			$offset   = ( $page - 1 ) * $per_page;
			$sql     .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized by name(); numeric pagination is prepared above.
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Creates the table if needed.
	 *
	 * @return bool
	 */
	public function create_db() {
		if ( $this->exists() ) {
			return true;
		}

		$sql = $this->schema_sql();
		if ( empty( $sql ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Force a fresh existence check: dbDelta() may have just created the table, and
		// exists() memoizes its result, so the cached value from the check above would
		// otherwise still read as "does not exist" even though it now does.
		$this->exists = 'not_checked';
		if ( $this->exists() ) {
			return true;
		}

		return false;
	}

	/**
	 * Builds the table creation SQL.
	 *
	 * @return string
	 */
	private function schema_sql() {
		global $wpdb;

		if ( empty( $this->columns ) ) {
			return '';
		}

		$wp_table_name   = $this->name();
		$charset_collate = $wpdb->get_charset_collate();
		$definitions     = array( 'id bigint(20) unsigned NOT NULL auto_increment' );

		foreach ( $this->columns as $name => $type ) {
			$column_name = sanitize_key( $name );
			$type        = ! empty( $type ) ? $type : 'text';

			if ( ic_string_contains( $type, 'NULL' ) ) {
				$definitions[] = "{$column_name} {$type}";
			} else {
				$definitions[] = "{$column_name} {$type} NOT NULL";
			}
		}

		$definitions[] = 'PRIMARY KEY  (id)';

		if ( ! empty( $this->indexed ) ) {
			foreach ( $this->indexed as $index ) {
				if ( is_array( $index ) ) {
					$index_columns = array_map( 'sanitize_key', $index );
					$index_name    = implode( '_', $index_columns );
					$columns       = implode( ',', $index_columns );
					$definitions[] = "KEY {$index_name} ({$columns})";
				} else {
					$index         = sanitize_key( $index );
					$definitions[] = "KEY {$index} ({$index})";
				}
			}
		}

		if ( ! empty( $this->unique ) ) {
			foreach ( $this->unique as $unique_index ) {
				if ( is_array( $unique_index ) ) {
					$index_columns = array_map( 'sanitize_key', $unique_index );
					$index_name    = implode( '_', $index_columns );
					$columns       = implode( ',', $index_columns );
					$definitions[] = "UNIQUE KEY {$index_name} ({$columns})";
				} else {
					$unique_index  = sanitize_key( $unique_index );
					$definitions[] = "UNIQUE KEY {$unique_index} ({$unique_index})";
				}
			}
		}

		return "CREATE TABLE {$wp_table_name} (\n\t" . implode( ",\n\t", $definitions ) . "\n) {$charset_collate};";
	}

	/**
	 * Synchronizes and verifies the declared table schema.
	 *
	 * @return bool
	 */
	public function sync_schema() {
		$this->schema_problems = array();
		$sql                   = $this->schema_sql();

		if ( empty( $sql ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		$this->exists = 'not_checked';

		$this->verify_columns();
		$this->verify_indexes();

		return empty( $this->schema_problems );
	}

	/**
	 * Returns problems found during the last schema synchronization.
	 *
	 * @return array
	 */
	public function schema_problems() {
		return $this->schema_problems;
	}

	/**
	 * Verifies declared table columns.
	 *
	 * @return void
	 */
	private function verify_columns() {
		global $wpdb;

		$table_name = $this->name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized by name(); schema inspection is intentional.
		$column_rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
		$actual      = array();

		foreach ( (array) $column_rows as $column ) {
			$actual[ $column['Field'] ] = $column;
		}

		$declared = array(
			'id' => array(
				'type'     => 'bigint(20) unsigned',
				'nullable' => false,
			),
		);

		foreach ( $this->columns as $name => $type ) {
			$type                     = ! empty( $type ) ? $type : 'text';
			$declared[ sanitize_key( $name ) ] = array(
				'type'     => $type,
				'nullable' => $this->definition_is_nullable( $type ),
			);
		}

		foreach ( $declared as $name => $definition ) {
			if ( ! isset( $actual[ $name ] ) ) {
				$this->schema_problems[] = 'missing_column:' . $name;
				continue;
			}

			$actual_nullable = 'YES' === strtoupper( $actual[ $name ]['Null'] );
			if ( $definition['nullable'] !== $actual_nullable ) {
				$this->schema_problems[] = 'nullability:' . $name;
			}
			if ( $this->normalize_type( $definition['type'] ) !== $this->normalize_type( $actual[ $name ]['Type'] ) ) {
				$this->schema_problems[] = 'type:' . $name;
			}
		}
	}

	/**
	 * Verifies declared table indexes.
	 *
	 * @return void
	 */
	private function verify_indexes() {
		global $wpdb;

		$table_name = $this->name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized by name(); schema inspection is intentional.
		$index_rows = $wpdb->get_results( "SHOW INDEX FROM {$table_name}", ARRAY_A );
		$actual     = array();

		foreach ( (array) $index_rows as $index ) {
			$name = $index['Key_name'];
			if ( ! isset( $actual[ $name ] ) ) {
				$actual[ $name ] = array(
					'columns' => array(),
					'unique'  => 0 === (int) $index['Non_unique'],
				);
			}
			$actual[ $name ]['columns'][ (int) $index['Seq_in_index'] ] = $index['Column_name'];
		}

		foreach ( $actual as &$definition ) {
			ksort( $definition['columns'] );
			$definition['columns'] = array_values( $definition['columns'] );
		}
		unset( $definition );

		foreach ( $this->declared_indexes() as $name => $expected ) {
			if ( ! isset( $actual[ $name ] ) ) {
				$this->schema_problems[] = 'missing_index:' . $name;
				continue;
			}
			if ( $expected['columns'] !== $actual[ $name ]['columns'] ) {
				$this->schema_problems[] = 'index_columns:' . $name;
			}
			if ( $expected['unique'] !== $actual[ $name ]['unique'] ) {
				$this->schema_problems[] = 'not_unique:' . $name;
			}
		}
	}

	/**
	 * Returns normalized declared indexes keyed by generated index name.
	 *
	 * @return array
	 */
	private function declared_indexes() {
		$indexes = array();

		foreach ( $this->indexed as $index ) {
			$columns          = is_array( $index ) ? array_map( 'sanitize_key', $index ) : array( sanitize_key( $index ) );
			$name             = implode( '_', $columns );
			$indexes[ $name ] = array(
				'columns' => $columns,
				'unique'  => false,
			);
		}

		foreach ( $this->unique as $index ) {
			$columns          = is_array( $index ) ? array_map( 'sanitize_key', $index ) : array( sanitize_key( $index ) );
			$name             = implode( '_', $columns );
			$indexes[ $name ] = array(
				'columns' => $columns,
				'unique'  => true,
			);
		}

		return $indexes;
	}

	/**
	 * Returns whether a declared column permits null values.
	 *
	 * @param string $definition Column definition.
	 *
	 * @return bool
	 */
	private function definition_is_nullable( $definition ) {
		if ( preg_match( '/\\bNOT\\s+NULL\\b/i', $definition ) ) {
			return false;
		}

		return (bool) preg_match( '/\\bNULL\\b/i', $definition );
	}

	/**
	 * Normalizes a declared or reported column type for comparison.
	 *
	 * @param string $type Column type.
	 *
	 * @return string
	 */
	private function normalize_type( $type ) {
		$type = strtolower( trim( $type ) );
		$type = preg_replace( '/\\s+/', ' ', $type );
		$type = preg_replace( '/\\s+(?:not\\s+null|null|default|auto_increment|primary|unique|comment|collate|character\\s+set)\\b.*$/i', '', $type );
		$type = preg_replace( '/\\b(tinyint|smallint|mediumint|int|integer|bigint)\\(\\d+\\)/i', '$1', $type );

		return trim( $type );
	}

	/**
	 * Returns whether the table exists.
	 *
	 * @return bool
	 */
	public function exists() {
		if ( 'not_checked' !== $this->exists ) {
			return $this->exists;
		}

		global $wpdb;

		$table_name = $this->name();
		$query      = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The table name is prepared above.
		if ( $wpdb->get_var( $query ) === $table_name ) {
			$this->exists = true;
		} else {
			$this->exists = false;
		}

		return $this->exists;
	}

	/**
	 * Returns the full table name.
	 *
	 * @return string
	 */
	public function name() {
		global $wpdb;

		return esc_sql( $wpdb->prefix . $this->name );
	}

	/**
	 * Clears or drops the table.
	 *
	 * @param bool $delete_table Whether to drop the table instead of truncating it.
	 *
	 * @return void
	 */
	public function clear( $delete_table = false ) {
		global $wpdb;

		if ( ! $this->exists() ) {
			return;
		}

		$table_name = $this->name();

		if ( $delete_table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized by name(); schema reset is intentional here.
			$wpdb->query( "DROP TABLE {$table_name}" );
			$this->exists = false;
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized by name(); truncate is intentional here.
			$wpdb->query( "TRUNCATE TABLE {$table_name}" );
		}
	}
	}
}

if ( ! class_exists( 'ic_db', false ) ) {
	class_alias( 'IC_DB', 'ic_db' );
}

/* phpcs:enable Universal.Files.SeparateFunctionsFromOO.Mixed */
