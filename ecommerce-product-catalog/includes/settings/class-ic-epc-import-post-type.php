<?php
/**
 * CSV import post type helper class.
 *
 * @package ecommerce-product-catalog/includes/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports other public post types into the catalog.
 */
class IC_EPC_Import_Post_Type {
	/**
	 * Target term IDs keyed by source taxonomy and source term ID.
	 *
	 * @var array
	 */
	private $source_term_map = array();

	/**
	 * Source terms currently being resolved.
	 *
	 * @var array
	 */
	private $resolving_source_terms = array();

	/**
	 * Nonce action guarding the post type import.
	 */
	const IMPORT_NONCE_ACTION = 'ic_import_post_type';

	/**
	 * Nonce field name used by the post type import form.
	 *
	 * A dedicated name is required because this form is rendered on the same
	 * screen as the CSV upload form, and `wp_nonce_field()` derives the field
	 * `id` from the field name. Sharing the default `_wpnonce` name would emit
	 * a duplicate `id="_wpnonce"` on that screen.
	 */
	const IMPORT_NONCE_FIELD = 'ic_import_post_type_nonce';

	const BATCH_SIZE         = 50;
	const TIME_LIMIT         = 15;
	const RUN_LIFETIME       = DAY_IN_SECONDS;
	const RUN_QUERY_ARG      = 'ic_epc_import_run';
	const RUN_OPTION_PREFIX  = 'ic_epc_import_run_';
	const LOCK_OPTION_PREFIX = 'ic_epc_import_lock_';
	const SITE_UUID_OPTION   = 'ic_epc_import_source_site_uuid';
	const META_SOURCE_KEY    = '_ic_epc_import_source_key';
	const META_SOURCE_SITE   = '_ic_epc_import_source_site';
	const META_SOURCE_TYPE   = '_ic_epc_import_source_post_type';
	const META_SOURCE_ID     = '_ic_epc_import_source_post_id';
	const META_SOURCE_STATE  = '_ic_epc_import_source_state';

	/**
	 * Hooks the import UI into the settings screen.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'ic_simple_csv_bottom', array( $this, 'import_output' ) );
		add_action( 'ic_csv_import_end', array( $this, 'import_output' ), 15 );
	}

	/**
	 * Returns the post type dropdown HTML.
	 *
	 * @return string|null
	 */
	public function post_types_dropdown() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$options    = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only read used to preselect the dropdown option.
		$selected = isset( $_GET['import_post_type'] ) ? sanitize_key( wp_unslash( $_GET['import_post_type'] ) ) : '';
		foreach ( $post_types as $post_type ) {
			if ( ! ic_string_contains( $post_type->name, 'al_product' ) && 'attachment' !== $post_type->name ) {
				$options .= '<option value="' . esc_attr( $post_type->name ) . '" ' . selected( $selected, $post_type->name, false ) . '>' . esc_html( $post_type->label ) . '</option>';
			}
		}
		if ( ! empty( $options ) ) {
			$drop_down = '<select name="import_post_type">' . $options . '</select>';

			return $drop_down;
		}

		return null;
	}

	/**
	 * Handles the import output block.
	 *
	 * @return void
	 */
	public function import_output() {
		$this->import_initial_html();
		if ( ! empty( $_GET['import_post_type'] ) ) {
			if ( ! current_user_can( 'manage_product_settings' ) ) {
				wp_die( esc_html__( 'You are not allowed to import products.', 'ecommerce-product-catalog' ) );
			}
			// The import bulk-inserts posts, so the request must come from the nonced import form.
			$run_token    = $this->request_run_token();
			$nonce_action = empty( $run_token ) ? self::IMPORT_NONCE_ACTION : self::IMPORT_NONCE_ACTION . ':' . $run_token;
			check_admin_referer( $nonce_action, self::IMPORT_NONCE_FIELD );
			$this->process_import_post_type();
		}
	}

	/**
	 * Renders the initial import HTML.
	 *
	 * @return void
	 */
	public function import_initial_html() {
		$post_types_dropdown = $this->post_types_dropdown();
		if ( ! empty( $post_types_dropdown ) ) {
			echo '<h3>' . esc_html__( 'Import from other content', 'ecommerce-product-catalog' ) . '</h3>';
			echo '<form>';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Current admin query args are only mirrored back into the form; the submission itself is nonced below.
			foreach ( $_GET as $key => $value ) {
				if ( ! is_scalar( $value ) || in_array( $key, array( '_wpnonce', '_wp_http_referer', 'import_post_type', self::IMPORT_NONCE_FIELD, self::RUN_QUERY_ARG ), true ) ) {
					continue;
				}
				echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			}
			wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_FIELD );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post_types_dropdown() builds a select from escaped post-type labels/values; post KSES would remove the control.
			echo $post_types_dropdown . ' <button type="submit" class="button-secondary">' . esc_html__( 'Import', 'ecommerce-product-catalog' ) . '</button>';
			echo '</form>';
		}
	}

	/**
	 * Imports posts for the selected post type.
	 *
	 * @return array Import run state/result.
	 */
	public function process_import_post_type() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- import_output() runs check_admin_referer() before calling this method.
		$post_type = isset( $_GET['import_post_type'] ) ? sanitize_key( wp_unslash( $_GET['import_post_type'] ) ) : '';
		if ( ! $this->is_valid_source_type( $post_type ) ) {
			$result = array( 'error' => __( 'The selected source content type is not available for import.', 'ecommerce-product-catalog' ) );
			$this->render_result( $result );

			return $result;
		}

		$run_token = $this->request_run_token();
		$state     = empty( $run_token ) ? $this->new_run_state( $post_type ) : $this->load_run_state( $run_token, $post_type );
		if ( is_wp_error( $state ) ) {
			$result = array( 'error' => $state->get_error_message() );
			$this->render_result( $result );

			return $result;
		}
		$run_token = $state['token'];
		$lock_key  = self::LOCK_OPTION_PREFIX . md5( $state['site_uuid'] . '|' . $post_type );
		if ( ! $this->acquire_lock( $lock_key ) ) {
			$state['error'] = __( 'Another import request for this source is already running. Please try again.', 'ecommerce-product-catalog' );
			$this->render_result( $state );

			return $state;
		}

		try {
			$started = microtime( true );
			$ids     = $this->source_batch_ids( $post_type, $state['cursor'], $state['snapshot_max_id'] );
			$more    = count( $ids ) > self::BATCH_SIZE;
			$ids     = array_slice( $ids, 0, self::BATCH_SIZE );
			foreach ( $ids as $source_id ) {
				if ( microtime( true ) - $started >= self::TIME_LIMIT ) {
					$more = true;
					break;
				}
				$outcome         = $this->import_source_post( $source_id, $post_type, $state['site_uuid'] );
				$state['cursor'] = $source_id;
				++$state['examined'];
				++$state[ $outcome ];
				$state['updated_at'] = time();
				$this->save_run_state( $state );
			}
			if ( ! $more ) {
				$more = ! empty( $this->source_batch_ids( $post_type, $state['cursor'], $state['snapshot_max_id'], 1 ) );
			}
			$state['complete']       = ! $more;
			$state['source_changed'] = $state['complete'] && (int) $state['examined'] !== (int) $state['snapshot_total'];
			$state['updated_at']     = time();
			$this->save_run_state( $state );
		} finally {
			delete_option( $lock_key );
		}

		$this->render_result( $state );

		return $state;
	}

	/** Returns an opaque continuation token from the current request. */
	private function request_run_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorization is performed by import_output().
		return isset( $_GET[ self::RUN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::RUN_QUERY_ARG ] ) ) : '';
	}

	/**
	 * Validates a source against the same rules used by the selector.
	 *
	 * @param string $post_type Source post type.
	 * @return bool Whether the post type can be imported.
	 */
	private function is_valid_source_type( $post_type ) {
		$object = get_post_type_object( $post_type );

		return $object && $object->public && 'attachment' !== $post_type && ! ic_string_contains( $post_type, 'al_product' );
	}

	/**
	 * Creates a bounded import run snapshot.
	 *
	 * @param string $post_type Source post type.
	 * @return array Import run state.
	 */
	private function new_run_state( $post_type ) {
		global $wpdb;
		$site_uuid = $this->site_uuid();
		$summary   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, COALESCE(MAX(ID), 0) AS max_id FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_parent = 0",
				$post_type
			),
			ARRAY_A
		);
		$token     = wp_generate_uuid4();
		$state     = array(
			'token'           => $token,
			'owner'           => get_current_user_id(),
			'post_type'       => $post_type,
			'site_uuid'       => $site_uuid,
			'snapshot_total'  => (int) $summary['total'],
			'snapshot_max_id' => (int) $summary['max_id'],
			'cursor'          => 0,
			'examined'        => 0,
			'imported'        => 0,
			'updated'         => 0,
			'skipped'         => 0,
			'failed'          => 0,
			'created_at'      => time(),
			'updated_at'      => time(),
			'complete'        => false,
			'source_changed'  => false,
		);
		$this->save_run_state( $state );

		return $state;
	}

	/**
	 * Loads and ownership-checks a continuation run.
	 *
	 * @param string $token Continuation token.
	 * @param string $post_type Source post type.
	 * @return array|WP_Error Import state or validation error.
	 */
	private function load_run_state( $token, $post_type ) {
		if ( ! preg_match( '/^[a-f0-9-]{36}$/', $token ) ) {
			return new WP_Error( 'invalid_run', __( 'This import continuation is invalid or has expired.', 'ecommerce-product-catalog' ) );
		}
		$state = get_transient( self::RUN_OPTION_PREFIX . $token );
		if ( ! is_array( $state ) || empty( $state['created_at'] ) || time() - (int) $state['created_at'] >= self::RUN_LIFETIME ) {
			return new WP_Error( 'expired_run', __( 'This import continuation is invalid or has expired.', 'ecommerce-product-catalog' ) );
		}
		if ( get_current_user_id() !== (int) $state['owner'] || $state['post_type'] !== $post_type ) {
			return new WP_Error( 'foreign_run', __( 'This import continuation does not belong to this user or source.', 'ecommerce-product-catalog' ) );
		}

		return $state;
	}

	/**
	 * Persists a run for its fixed 24-hour lifetime.
	 *
	 * @param array $state Import run state.
	 * @return void
	 */
	private function save_run_state( $state ) {
		$remaining = max( 1, self::RUN_LIFETIME - ( time() - (int) $state['created_at'] ) );
		set_transient( self::RUN_OPTION_PREFIX . $state['token'], $state, $remaining );
	}

	/**
	 * Obtains a short, atomic source lock with stale-lock recovery.
	 *
	 * @param string $lock_key Lock option key.
	 * @return bool Whether the lock was acquired.
	 */
	private function acquire_lock( $lock_key ) {
		$now = time();
		if ( add_option( $lock_key, $now, '', 'no' ) ) {
			return true;
		}
		$locked_at = (int) get_option( $lock_key );
		if ( $locked_at && $now - $locked_at <= self::TIME_LIMIT + 30 ) {
			return false;
		}
		delete_option( $lock_key );

		return add_option( $lock_key, $now, '', 'no' );
	}

	/**
	 * Returns source IDs using bounded ascending keyset pagination.
	 *
	 * @param string $post_type Source post type.
	 * @param int    $cursor Last processed source ID.
	 * @param int    $maximum Snapshot maximum source ID.
	 * @param int    $limit Maximum IDs to return.
	 * @return array Source IDs.
	 */
	private function source_batch_ids( $post_type, $cursor, $maximum, $limit = 0 ) {
		global $wpdb;
		$limit = $limit ? absint( $limit ) : self::BATCH_SIZE + 1;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_parent = 0 AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d",
					$post_type,
					(int) $cursor,
					(int) $maximum,
					$limit
				)
			)
		);
	}

	/** Lazily creates the stable source-site UUID. */
	private function site_uuid() {
		$uuid = get_option( self::SITE_UUID_OPTION );
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) ) {
			$candidate = wp_generate_uuid4();
			if ( ! add_option( self::SITE_UUID_OPTION, $candidate, '', 'no' ) ) {
				$stored = get_option( self::SITE_UUID_OPTION );
				if ( is_string( $stored ) && wp_is_uuid( $stored ) ) {
					$candidate = $stored;
				} else {
					update_option( self::SITE_UUID_OPTION, $candidate, false );
					$candidate = get_option( self::SITE_UUID_OPTION );
				}
			}
			$uuid = $candidate;
		}

		return (string) $uuid;
	}

	/**
	 * Builds the versioned, context-specific source identity.
	 *
	 * @param string $site_uuid Source site UUID.
	 * @param string $post_type Source post type.
	 * @param int    $source_id Source post ID.
	 * @return string Source identity key.
	 */
	private function source_key( $site_uuid, $post_type, $source_id ) {
		return 'v1:' . hash( 'sha256', $site_uuid . '|' . sanitize_key( $post_type ) . '|' . (int) $source_id );
	}

	/**
	 * Imports, skips, repairs, or rejects one source post.
	 *
	 * @param int    $source_id Source post ID.
	 * @param string $post_type Source post type.
	 * @param string $site_uuid Source site UUID.
	 * @return string Import outcome.
	 */
	private function import_source_post( $source_id, $post_type, $site_uuid ) {
		$source = get_post( $source_id );
		if ( ! $source || $source->post_type !== $post_type || 'publish' !== $source->post_status || 0 !== (int) $source->post_parent ) {
			return 'skipped';
		}
		$key        = $this->source_key( $site_uuid, $post_type, $source_id );
		$candidates = $this->identity_candidates( $key );
		if ( count( $candidates ) > 1 ) {
			return 'failed';
		}
		$target_id = empty( $candidates ) ? 0 : (int) $candidates[0];
		if ( $target_id && ! $this->identity_matches( $target_id, $key, $site_uuid, $post_type, $source_id ) ) {
			return 'failed';
		}
		if ( $target_id && 'complete' === get_post_meta( $target_id, self::META_SOURCE_STATE, true ) ) {
			return 'skipped';
		}

		$is_update         = (bool) $target_id;
		$target            = clone $source;
		$target->ID        = $target_id;
		$target->post_type = 'al_product';
		$target_id         = $is_update ? wp_update_post( $target, true ) : wp_insert_post( $target, true );
		if ( is_wp_error( $target_id ) || ! $target_id ) {
			return 'failed';
		}
		if ( ! $is_update && ! $this->persist_identity( $target_id, $key, $site_uuid, $post_type, $source_id, 'pending' ) ) {
			wp_delete_post( $target_id, true );

			return 'failed';
		}
		$meta_result = $this->copy_post_meta( (int) $target_id, (int) $source_id );
		$tax_result  = $this->copy_taxonomies( (int) $target_id, (int) $source_id );
		if ( is_wp_error( $meta_result ) || is_wp_error( $tax_result ) ) {
			return 'failed';
		}
		if ( ! $this->persist_identity( $target_id, $key, $site_uuid, $post_type, $source_id, 'complete' ) ) {
			return 'failed';
		}

		return $is_update ? 'updated' : 'imported';
	}

	/**
	 * Finds at most two exact-key target candidates, across all statuses.
	 *
	 * @param string $key Source identity key.
	 * @return array Target post IDs.
	 */
	private function identity_candidates( $key ) {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = 'al_product' AND pm.meta_key = %s AND pm.meta_value = %s ORDER BY p.ID ASC LIMIT 2",
				self::META_SOURCE_KEY,
				$key
			)
		);
	}

	/**
	 * Confirms that an exact lookup-key hit has matching inspectable components.
	 *
	 * @param int    $target_id Target post ID.
	 * @param string $key Source identity key.
	 * @param string $site_uuid Source site UUID.
	 * @param string $post_type Source post type.
	 * @param int    $source_id Source post ID.
	 * @return bool Whether all identity components match.
	 */
	private function identity_matches( $target_id, $key, $site_uuid, $post_type, $source_id ) {
		return get_post_meta( $target_id, self::META_SOURCE_KEY, true ) === $key
				&& get_post_meta( $target_id, self::META_SOURCE_SITE, true ) === $site_uuid
				&& get_post_meta( $target_id, self::META_SOURCE_TYPE, true ) === $post_type
				&& (int) get_post_meta( $target_id, self::META_SOURCE_ID, true ) === (int) $source_id
			&& in_array( get_post_meta( $target_id, self::META_SOURCE_STATE, true ), array( 'pending', 'complete' ), true );
	}

	/**
	 * Stores and verifies every identity component.
	 *
	 * @param int    $target_id Target post ID.
	 * @param string $key Source identity key.
	 * @param string $site_uuid Source site UUID.
	 * @param string $post_type Source post type.
	 * @param int    $source_id Source post ID.
	 * @param string $state Import state.
	 * @return bool Whether the identity was persisted correctly.
	 */
	private function persist_identity( $target_id, $key, $site_uuid, $post_type, $source_id, $state ) {
		update_post_meta( $target_id, self::META_SOURCE_KEY, $key );
		update_post_meta( $target_id, self::META_SOURCE_SITE, $site_uuid );
		update_post_meta( $target_id, self::META_SOURCE_TYPE, $post_type );
		update_post_meta( $target_id, self::META_SOURCE_ID, (int) $source_id );
		update_post_meta( $target_id, self::META_SOURCE_STATE, $state );

		return $this->identity_matches( $target_id, $key, $site_uuid, $post_type, $source_id )
				&& get_post_meta( $target_id, self::META_SOURCE_STATE, true ) === $state;
	}

	/**
	 * Renders progress, counters, continuation, and compatibility guidance.
	 *
	 * @param array $state Import run state.
	 * @return void
	 */
	private function render_result( $state ) {
		if ( ! empty( $state['error'] ) ) {
			implecode_warning( esc_html( $state['error'] ) );

			return;
		}
		$post_type_object = get_post_type_object( $state['post_type'] );
		$source_label     = $post_type_object ? $post_type_object->label : $state['post_type'];
		$message          = sprintf(
			/* translators: 1: source label, 2: examined sources, 3: initial eligible sources, 4: imported, 5: updated, 6: skipped, 7: failed. */
			__( 'Source: %1$s. Import progress: %2$d of %3$d examined. Imported: %4$d; updated: %5$d; skipped: %6$d; failed: %7$d.', 'ecommerce-product-catalog' ),
			$source_label,
			$state['examined'],
			$state['snapshot_total'],
			$state['imported'],
			$state['updated'],
			$state['skipped'],
			$state['failed']
		);
		if ( ! empty( $state['source_changed'] ) ) {
			$message .= ' ' . __( 'The eligible source set changed during this import. Newer items wait for a new run; removed or ineligible items do not delete imported products.', 'ecommerce-product-catalog' );
		}
		if ( ! empty( $state['failed'] ) ) {
			$message .= ' ' . __( 'Rerun the import to retry incomplete products. Completed products will be skipped.', 'ecommerce-product-catalog' );
		}
		if ( ! empty( $state['complete'] ) ) {
			$message .= ' ' . __( 'Import complete. Rerunning skips completed products and retries incomplete products. Products imported by an older version have no source identity and cannot be matched safely, so the first rerun after upgrading may create one identified copy.', 'ecommerce-product-catalog' );
			if ( ! empty( $state['failed'] ) ) {
				implecode_warning( esc_html( $message ) );
			} else {
				implecode_success( esc_html( $message ) );
			}

			return;
		}
		implecode_info( esc_html( $message ) );
		echo '<form>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Values are only mirrored into the nonced continuation form.
		foreach ( $_GET as $name => $value ) {
			if ( ! is_scalar( $value ) || in_array( $name, array( '_wpnonce', '_wp_http_referer', self::IMPORT_NONCE_FIELD, self::RUN_QUERY_ARG ), true ) ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '<input type="hidden" name="' . esc_attr( self::RUN_QUERY_ARG ) . '" value="' . esc_attr( $state['token'] ) . '">';
		echo '<input type="hidden" id="ic_import_post_type_continue_nonce" name="' . esc_attr( self::IMPORT_NONCE_FIELD ) . '" value="' . esc_attr( wp_create_nonce( self::IMPORT_NONCE_ACTION . ':' . $state['token'] ) ) . '">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Continue import', 'ecommerce-product-catalog' ) . '</button></form>';
	}

	/**
	 * Copies post meta to the imported product.
	 *
	 * @param int $target_post_id Target product ID.
	 * @param int $origin_post_id Origin post ID.
	 *
	 * @return true|WP_Error True on success, otherwise an error.
	 */
	public function copy_post_meta( $target_post_id, $origin_post_id ) {
		if ( ! is_int( $target_post_id ) || ! is_int( $origin_post_id ) ) {
			return new WP_Error( 'invalid_post', __( 'Invalid source or target while copying product metadata.', 'ecommerce-product-catalog' ) );
		}
		$post_meta        = get_post_meta( $origin_post_id );
		$restricted_names = $this->meta_import_restricted_names();
		foreach ( $post_meta as $name => $value ) {
			if ( in_array( $name, $restricted_names, true ) ) {
				continue;
			}
			if ( '_length' === $name ) {
				$name = '_size_length';
			}
			if ( '_width' === $name ) {
				$name = '_size_width';
			}
			if ( '_height' === $name ) {
				$name = '_size_height';
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $val ) {
					$updated = update_post_meta( $target_post_id, $name, $val );
					if ( false === $updated && maybe_unserialize( $val ) !== get_post_meta( $target_post_id, $name, true ) ) {
						return new WP_Error( 'meta_copy_failed', __( 'Product metadata could not be copied.', 'ecommerce-product-catalog' ) );
					}
				}
			} else {
				$updated = update_post_meta( $target_post_id, $name, $value );
				if ( false === $updated && maybe_unserialize( $value ) !== get_post_meta( $target_post_id, $name, true ) ) {
					return new WP_Error( 'meta_copy_failed', __( 'Product metadata could not be copied.', 'ecommerce-product-catalog' ) );
				}
			}
		}

		return true;
	}

	/**
	 * Copies hierarchical taxonomies to the imported product.
	 *
	 * @param int $target_post_id Target product ID.
	 * @param int $origin_post_id Origin post ID.
	 *
	 * @return true|WP_Error True on success, otherwise an error.
	 */
	public function copy_taxonomies( $target_post_id, $origin_post_id ) {
		$taxonomies   = get_object_taxonomies( get_post_type( $origin_post_id ), 'objects' );
		$valid_tax    = array();
		$priority_tax = array();
		$term_ids     = array();
		foreach ( $taxonomies as $tax_name => $tax ) {
			if ( empty( $tax->publicly_queryable ) || empty( $tax->public ) || empty( $tax->hierarchical ) ) {
				continue;
			}
			$valid_tax[] = $tax_name;
			if ( ic_string_contains( $tax->label, 'cat' ) || ic_string_contains( $tax->label, 'kat' ) ) {
				$priority_tax[] = $tax_name;
			}
		}
		if ( ! empty( $priority_tax ) ) {
			$valid_tax = $priority_tax;
		}
		if ( ! empty( $valid_tax[0] ) ) {
			$origin_tax = $valid_tax[0];
			$terms      = wp_get_object_terms( $origin_post_id, $origin_tax );
			if ( is_wp_error( $terms ) ) {
				return $terms;
			}
			foreach ( $terms as $term ) {
				$term_id = $this->resolve_target_term( $origin_tax, $term->term_id );
				if ( is_wp_error( $term_id ) ) {
					return $term_id;
				}
				$term_ids[] = $term_id;
			}
		}
		$set_terms = wp_set_object_terms( $target_post_id, $term_ids, 'al_product-cat' );
		if ( is_wp_error( $set_terms ) ) {
			return $set_terms;
		}

		return true;
	}

	/**
	 * Resolves a source term and its ancestors to target product categories.
	 *
	 * @param string $source_taxonomy Source taxonomy name.
	 * @param int    $source_term_id  Source term ID.
	 *
	 * @return int|WP_Error Target term ID or an error.
	 */
	private function resolve_target_term( $source_taxonomy, $source_term_id ) {
		$source_term_id = intval( $source_term_id );
		if ( isset( $this->source_term_map[ $source_taxonomy ][ $source_term_id ] ) ) {
			return $this->source_term_map[ $source_taxonomy ][ $source_term_id ];
		}
		if ( isset( $this->resolving_source_terms[ $source_taxonomy ][ $source_term_id ] ) ) {
			return new WP_Error( 'term_cycle', __( 'A source category hierarchy cycle was detected.', 'ecommerce-product-catalog' ) );
		}

		$term = get_term( $source_term_id, $source_taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'source_term_missing', __( 'A source category could not be read.', 'ecommerce-product-catalog' ) );
		}

		$this->resolving_source_terms[ $source_taxonomy ][ $source_term_id ] = true;
		$target_parent = 0;
		if ( ! empty( $term->parent ) ) {
			$target_parent = $this->resolve_target_term( $source_taxonomy, $term->parent );
			if ( is_wp_error( $target_parent ) ) {
				unset( $this->resolving_source_terms[ $source_taxonomy ][ $source_term_id ] );

				return $target_parent;
			}
		}

		$args        = array(
			'slug'        => $term->slug,
			'parent'      => $target_parent,
			'description' => $term->description,
		);
		$target_term = term_exists( $term->name, 'al_product-cat', $target_parent );
		if ( empty( $target_term ) ) {
			$target_term = wp_insert_term( $term->name, 'al_product-cat', $args );
		}
		$target_term_id = $this->term_result_id( $target_term );
		if ( empty( $target_term_id ) ) {
			unset( $this->resolving_source_terms[ $source_taxonomy ][ $source_term_id ] );

			return is_wp_error( $target_term ) ? $target_term : new WP_Error( 'target_term_failed', __( 'A product category could not be created.', 'ecommerce-product-catalog' ) );
		}
		$this->copy_term_thumbnail( $source_term_id, $target_term_id );
		$this->source_term_map[ $source_taxonomy ][ $source_term_id ] = $target_term_id;
		unset( $this->resolving_source_terms[ $source_taxonomy ][ $source_term_id ] );

		return $target_term_id;
	}

	/**
	 * Normalizes a term insertion or lookup result to a term ID.
	 *
	 * @param int|array|WP_Error|null $term_result Term lookup or insertion result.
	 *
	 * @return int
	 */
	private function term_result_id( $term_result ) {
		if ( is_wp_error( $term_result ) || empty( $term_result ) ) {
			return 0;
		}
		if ( is_array( $term_result ) && ! empty( $term_result['term_id'] ) ) {
			return intval( $term_result['term_id'] );
		}
		if ( is_int( $term_result ) ) {
			return $term_result;
		}

		return 0;
	}

	/**
	 * Copies the source term thumbnail to the target term.
	 *
	 * @param int $source_term_id Source term ID.
	 * @param int $target_term_id Target term ID.
	 *
	 * @return void
	 */
	private function copy_term_thumbnail( $source_term_id, $target_term_id ) {
		if ( ! function_exists( 'get_term_meta' ) ) {
			return;
		}
		$meta = get_term_meta( $source_term_id );
		if ( empty( $meta['thumbnail_id'] ) ) {
			return;
		}
		if ( ! empty( $meta['thumbnail_id'][0] ) ) {
			$image_id = $meta['thumbnail_id'][0];
		} else {
			$image_id = $meta['thumbnail_id'];
		}
		update_term_meta( $target_term_id, 'thumbnail_id', intval( $image_id ) );
	}

	/**
	 * Returns post meta keys that should not be imported.
	 *
	 * @return array
	 */
	public function meta_import_restricted_names() {
		return array( '_wp_page_template', '_edit_last', '_edit_lock', self::META_SOURCE_KEY, self::META_SOURCE_SITE, self::META_SOURCE_TYPE, self::META_SOURCE_ID, self::META_SOURCE_STATE );
	}
}

if ( ! class_exists( 'IC_EPC_import_post_type', false ) ) {
	class_alias( 'IC_EPC_Import_Post_Type', 'IC_EPC_import_post_type' );
}
