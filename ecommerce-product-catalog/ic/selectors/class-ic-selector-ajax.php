<?php
/**
 * Search endpoint backing the shared AJAX selector control.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'IC_Selector_Ajax' ) ) {

	/**
	 * Serves `wp_ajax_ic_selector_search` and owns the shared read path.
	 *
	 * There is deliberately NO `wp_ajax_nopriv_ic_selector_search`
	 * registration: every source is an administrative listing.
	 */
	class IC_Selector_Ajax {

		/**
		 * Nonce action used by every selector request.
		 *
		 * @var string
		 */
		const NONCE_ACTION = 'ic_selector_search';

		/**
		 * Registers the logged-in-only endpoint.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'wp_ajax_ic_selector_search', array( __CLASS__, 'search' ) );
		}

		/**
		 * Handles a selector search request.
		 *
		 * Two independent checks live literally in this body, in this order:
		 * the nonce proves the request came from our own screen, and the
		 * source's own capability proves this user may read that source. A
		 * nonce authenticates a request; it does not authorise a user, so
		 * neither check may stand in for the other.
		 *
		 * @return void
		 */
		public static function search() {
			if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
				wp_send_json_error(
					array(
						'code'    => 'invalid_nonce',
						'status'  => 403,
						'message' => __( 'Your session expired. Reload the page and try again.', 'ecommerce-product-catalog' ),
					),
					403
				);
			}

			$requested_source = isset( $_REQUEST['source'] ) ? sanitize_key( wp_unslash( $_REQUEST['source'] ) ) : '';
			$source           = ic_selector_registry()->get( $requested_source );

			if ( false === $source ) {
				wp_send_json_error(
					array(
						'code'    => 'unknown_source',
						'status'  => 400,
						'message' => __( 'Unknown selector source.', 'ecommerce-product-catalog' ),
					),
					400
				);
			}

			if ( ! current_user_can( $source['capability'] ) ) {
				wp_send_json_error(
					array(
						'code'    => 'insufficient_capability',
						'status'  => 403,
						'message' => __( 'You are not allowed to browse this list.', 'ecommerce-product-catalog' ),
					),
					403
				);
			}

			$term     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
			$page     = isset( $_REQUEST['page'] ) ? absint( wp_unslash( $_REQUEST['page'] ) ) : 1;
			$per_page = isset( $_REQUEST['per_page'] ) ? absint( wp_unslash( $_REQUEST['per_page'] ) ) : $source['per_page'];
			$include  = isset( $_REQUEST['include'] ) ? self::parse_ids( wp_unslash( $_REQUEST['include'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parse_ids() casts every element with absint(), which is stricter than any string sanitizer.

			$response = self::query(
				$source,
				array(
					's'        => $term,
					'page'     => $page,
					'per_page' => $per_page,
					'include'  => $include,
				)
			);

			wp_send_json( $response );
		}

		/**
		 * Runs a bounded search against a registered source.
		 *
		 * Shared by the endpoint and by the no-JS `<select>` fallback, so both
		 * obey the same post status, subtype and page-size rules.
		 *
		 * @param array $source Registered source definition.
		 * @param array $args   Query arguments: `s`, `page`, `per_page`, `include`.
		 *
		 * @return array Response array with `results` and `pagination`.
		 */
		public static function query( $source, $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					's'        => '',
					'page'     => 1,
					'per_page' => isset( $source['per_page'] ) ? $source['per_page'] : 20,
					'include'  => array(),
				)
			);

			$args['page']     = max( 1, absint( $args['page'] ) );
			$args['per_page'] = IC_Selector_Registry::normalize_per_page( $args['per_page'] );
			$args['s']        = is_string( $args['s'] ) ? trim( $args['s'] ) : '';
			$args['include']  = self::parse_ids( $args['include'] );

			switch ( $source['type'] ) {
				case 'term':
					$response = self::query_terms( $source, $args );
					break;
				case 'user':
					$response = self::query_users( $source, $args );
					break;
				default:
					$response = self::query_posts( $source, $args );
					break;
			}

			/**
			 * Filters a selector search response.
			 *
			 * @param array $response Response array.
			 * @param array $source   Registered source definition.
			 * @param array $args     Normalized query arguments.
			 */
			return apply_filters( 'ic_selector_search_results', $response, $source, $args );
		}

		/**
		 * Searches posts of the source's subtypes.
		 *
		 * @param array $source Source definition.
		 * @param array $args   Normalized query arguments.
		 *
		 * @return array
		 */
		private static function query_posts( $source, $args ) {
			$post_types = IC_Selector_Registry::resolve_list( $source['subtypes'], array( 'post' ) );
			$statuses   = IC_Selector_Registry::resolve_list( $source['post_status'], array( 'publish' ) );

			$query_args = array(
				'post_type'              => $post_types,
				'post_status'            => $statuses,
				'posts_per_page'         => $args['per_page'],
				'paged'                  => $args['page'],
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			if ( ! empty( $args['include'] ) ) {
				$query_args['post__in']       = $args['include'];
				$query_args['orderby']        = 'post__in';
				$query_args['posts_per_page'] = count( $args['include'] );
				$query_args['paged']          = 1;
			} elseif ( '' !== $args['s'] ) {
				$query_args['s']                  = $args['s'];
				$query_args['ic_selector_term']   = $args['s'];
				$query_args['ic_selector_search'] = $source['search'];
			}

			add_filter( 'posts_search', array( __CLASS__, 'filter_search_sql' ), 10, 2 );
			$query = new WP_Query( $query_args );
			remove_filter( 'posts_search', array( __CLASS__, 'filter_search_sql' ), 10 );

			$results = array();
			foreach ( $query->posts as $post_id ) {
				$post_id   = (int) $post_id;
				$results[] = array(
					'id'   => $post_id,
					'text' => self::post_label( $post_id, $source ),
				);
			}

			return self::response( $results, $args, (int) $query->found_posts );
		}

		/**
		 * Replaces the core search clause with the source's own field list.
		 *
		 * Title, ID and configured meta keys are OR-ed together, which
		 * `WP_Query` cannot express through `s` plus `meta_query`.
		 *
		 * @param string   $search Search SQL produced by core.
		 * @param WP_Query $query  Query being filtered.
		 *
		 * @return string
		 */
		public static function filter_search_sql( $search, $query ) {
			$term = $query->get( 'ic_selector_term' );
			if ( ! is_string( $term ) || '' === $term ) {
				return $search;
			}

			$config = $query->get( 'ic_selector_search' );
			$fields = isset( $config['fields'] ) ? (array) $config['fields'] : array( 'title' );
			$meta   = isset( $config['meta'] ) ? (array) $config['meta'] : array();

			global $wpdb;

			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$clauses = array();

			if ( in_array( 'title', $fields, true ) ) {
				$clauses[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like );
			}
			if ( in_array( 'id', $fields, true ) && ctype_digit( ltrim( $term, '#' ) ) ) {
				$clauses[] = $wpdb->prepare( "{$wpdb->posts}.ID = %d", absint( ltrim( $term, '#' ) ) );
			}
			if ( ! empty( $meta ) ) {
				$meta_sql = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ic_selector_meta"
					. " WHERE ic_selector_meta.post_id = {$wpdb->posts}.ID"
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The interpolated fragment is a comma separated list of %s placeholders sized from count(), never request data; every meta key is bound by prepare() below.
					. ' AND ic_selector_meta.meta_key IN ( ' . implode( ', ', array_fill( 0, count( $meta ), '%s' ) ) . ' )'
					. ' AND ic_selector_meta.meta_value LIKE %s )';
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $meta_sql is a literal template whose only interpolation is the placeholder list above; the values are bound here.
				$clauses[] = $wpdb->prepare( $meta_sql, array_merge( array_values( $meta ), array( $like ) ) );
			}

			if ( empty( $clauses ) ) {
				return $search;
			}

			return ' AND ( ' . implode( ' OR ', $clauses ) . ' ) ';
		}

		/**
		 * Searches terms of the source's taxonomies.
		 *
		 * @param array $source Source definition.
		 * @param array $args   Normalized query arguments.
		 *
		 * @return array
		 */
		private static function query_terms( $source, $args ) {
			$taxonomies = IC_Selector_Registry::resolve_list( $source['subtypes'], array( 'category' ) );

			$query_args = array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => $args['per_page'],
				'offset'     => ( $args['page'] - 1 ) * $args['per_page'],
			);

			if ( ! empty( $args['include'] ) ) {
				$query_args['include'] = $args['include'];
				$query_args['number']  = count( $args['include'] );
				$query_args['offset']  = 0;
			} elseif ( '' !== $args['s'] ) {
				$query_args['search'] = $args['s'];
			}

			$terms = get_terms( $query_args );
			if ( is_wp_error( $terms ) ) {
				$terms = array();
			}

			$results = array();
			foreach ( $terms as $term ) {
				$results[] = array(
					'id'   => (int) $term->term_id,
					'text' => self::label_for( $term->name, (int) $term->term_id, $source ),
				);
			}

			$count_args           = $query_args;
			$count_args['number'] = 0;
			$count_args['offset'] = 0;
			$count_args['fields'] = 'count';
			$total                = get_terms( $count_args );
			$total                = is_wp_error( $total ) ? count( $results ) : (int) $total;

			return self::response( $results, $args, $total );
		}

		/**
		 * Searches users.
		 *
		 * @param array $source Source definition.
		 * @param array $args   Normalized query arguments.
		 *
		 * @return array
		 */
		private static function query_users( $source, $args ) {
			$roles = IC_Selector_Registry::resolve_list( $source['subtypes'], array() );

			$query_args = array(
				'number'  => $args['per_page'],
				'paged'   => $args['page'],
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			);

			if ( ! empty( $roles ) ) {
				$query_args['role__in'] = $roles;
			}

			if ( ! empty( $args['include'] ) ) {
				$query_args['include'] = $args['include'];
				$query_args['number']  = count( $args['include'] );
				$query_args['paged']   = 1;
			} elseif ( '' !== $args['s'] ) {
				$query_args['search']         = '*' . $args['s'] . '*';
				$query_args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
			}

			$user_query = new WP_User_Query( $query_args );
			$results    = array();
			foreach ( (array) $user_query->get_results() as $user ) {
				$label     = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
				$results[] = array(
					'id'   => (int) $user->ID,
					'text' => self::label_for( $label, (int) $user->ID, $source ),
				);
			}

			return self::response( $results, $args, (int) $user_query->get_total() );
		}

		/**
		 * Builds the response envelope.
		 *
		 * @param array $results Result rows.
		 * @param array $args    Normalized query arguments.
		 * @param int   $total   Total matching records.
		 *
		 * @return array
		 */
		private static function response( $results, $args, $total ) {
			$more = false;
			if ( empty( $args['include'] ) ) {
				$more = ( $args['page'] * $args['per_page'] ) < $total;
			}

			return array(
				'results'    => array_values( $results ),
				'pagination' => array( 'more' => (bool) $more ),
			);
		}

		/**
		 * Resolves a post label through the source's label callback.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $source  Source definition.
		 *
		 * @return string
		 */
		private static function post_label( $post_id, $source ) {
			$label = '';
			if ( ! empty( $source['label_callback'] ) && is_callable( $source['label_callback'] ) ) {
				$label = (string) call_user_func( $source['label_callback'], $post_id );
			}
			if ( '' === $label ) {
				$label = (string) get_the_title( $post_id );
			}

			return self::label_for( $label, $post_id, $source );
		}

		/**
		 * Normalizes a label and lets consumers rewrite it.
		 *
		 * @param string $label  Raw label.
		 * @param int    $id     Record ID.
		 * @param array  $source Source definition.
		 *
		 * @return string
		 */
		private static function label_for( $label, $id, $source ) {
			$label = wp_strip_all_tags( (string) $label );
			if ( '' === trim( $label ) ) {
				/* translators: %d: record ID with no readable label. */
				$label = sprintf( __( '(no title) #%d', 'ecommerce-product-catalog' ), $id );
			}

			/**
			 * Filters the label rendered for one selector result.
			 *
			 * @param string $label  Label text.
			 * @param int    $id     Record ID.
			 * @param array  $source Source definition.
			 */
			return (string) apply_filters( 'ic_selector_result_label', $label, $id, $source );
		}

		/**
		 * Casts a mixed ID list to a bounded array of positive integers.
		 *
		 * @param mixed $ids Comma separated string or array.
		 *
		 * @return array
		 */
		public static function parse_ids( $ids ) {
			if ( is_string( $ids ) ) {
				$ids = explode( ',', $ids );
			}
			if ( ! is_array( $ids ) ) {
				$ids = array( $ids );
			}

			$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

			return array_slice( array_unique( $ids ), 0, IC_Selector_Registry::max_per_page() );
		}
	}
}
