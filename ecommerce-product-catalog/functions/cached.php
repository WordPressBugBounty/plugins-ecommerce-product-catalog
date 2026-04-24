<?php
/**
 * Cached query helpers.
 *
 * @package ecommerce-product-catalog/functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages WordPress core fields.
 *
 * Here all WordPress fields are redefined.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/functions
 * @author  impleCode
 *
 * @param int          $parent   Parent term ID.
 * @param string|array $taxonomy Taxonomy slug or list.
 * @param string|int   $number   Number of terms.
 * @param array|string $include  Included term IDs.
 * @param array|string $exclude  Excluded term IDs.
 * @param string       $order    Sort order.
 * @param string       $orderby  Order by field.
 * @param array        $args     Additional term query args.
 * @return array
 */
function ic_catalog_get_categories( $parent = 0, $taxonomy = '', $number = '', $include = array(), $exclude = array(), $order = 'ASC', $orderby = 'name', $args = array() ) {
	if ( empty( $taxonomy ) ) {
		$taxonomy = get_current_screen_tax();
	}
	$string_include = is_array( $include ) ? implode( '_', $include ) : $include;
	$string_exclude = is_array( $exclude ) ? implode( '_', $exclude ) : $exclude;
	$key            = 'get_product_categories' . $parent . $taxonomy . $number . $string_include . $string_exclude . $order . $orderby;
	$cached         = ic_get_global( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	if ( ! empty( $taxonomy ) && empty( $number ) && empty( $include ) && empty( $exclude ) && 'name' === $orderby && empty( $args ) ) {
		$taxonomy_terms = ic_get_global( 'taxonomy_terms_' . $taxonomy );
		if ( false === $taxonomy_terms ) {
			$taxonomy_terms = ic_get_terms( array( 'taxonomy' => $taxonomy ) );
			ic_save_global( 'taxonomy_terms_' . $taxonomy, $taxonomy_terms ); // Get all and save them to cache.
		}
		$terms = array();
		foreach ( $taxonomy_terms as $taxonomy_term ) {
			if ( $taxonomy_term->parent === $parent ) {
				$terms[] = $taxonomy_term;
			}
		}
	} else {
		$terms = ic_get_terms(
			array_merge(
				$args,
				array(
					'taxonomy' => $taxonomy,
					'parent'   => $parent,
					'number'   => $number,
					'include'  => $include,
					'exclude'  => $exclude,
					'order'    => $order,
					'orderby'  => $orderby,
				)
			)
		);
	}

	if ( is_wp_error( $terms ) ) {
		return array();
	}
	if ( ! empty( $key ) ) {
		ic_save_global( $key, $terms );
	}

	return $terms;
}

/**
 * Returns categories available in the current product context.
 *
 * @param string|array $taxonomy      Taxonomy slug or list.
 * @param array|null   $args          Additional term query args.
 * @param array        $excluded_meta Excluded meta keys.
 * @return array
 */
function ic_catalog_get_current_categories( $taxonomy = '', $args = null, $excluded_meta = array() ) {
	if ( empty( $taxonomy ) ) {
		$taxonomy = get_current_screen_tax();
	}
	$key_taxonomy = is_array( $taxonomy ) ? implode( '_', $taxonomy ) : $taxonomy;
	$key          = 'get_current_product_categories' . $key_taxonomy;
	if ( is_product_filters_active() ) {
		$excluded_tax = array( $taxonomy );
	} else {
		$excluded_tax = array();
	}
	$post_ids = ic_get_current_products( array(), $excluded_tax, $excluded_meta );
	if ( ! empty( $post_ids ) ) {
		// $cache_key     .= array_sum( $post_in );
		$pos_in_cache_key = md5( serialize( $post_ids ) );
		$key             .= $pos_in_cache_key;
	}
	$cached = ic_get_global( $key );
	if ( false !== $cached ) {
		return $cached;
	}

	if ( 'all' === $post_ids ) {
		$all_term_args = array( 'taxonomy' => $taxonomy );
		if ( ! empty( $args ) ) {
			$all_term_args = array_merge( $args, $all_term_args );
		}
		if ( ! empty( $args['all'] ) ) {
			unset( $all_term_args['parent'] );
		}
		$terms = ic_get_terms( $all_term_args );
	} elseif ( ! empty( $post_ids ) ) {
		$args['update_term_meta_cache'] = false;
		$terms                          = ic_wp_get_object_terms( $post_ids, $taxonomy, $args, true );
	} else {
		$terms = array();
	}
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	} else {
		$terms = array_values( $terms );
	}

	if ( ! empty( $key ) ) {
		ic_save_global( $key, $terms, 'all' !== $post_ids );
	}

	return $terms;
}

/**
 * Returns category product count with products in child categories.
 *
 * @param int         $cat_id   Category ID.
 * @param string|null $taxonomy Taxonomy slug.
 * @param array|null  $post_in  Post IDs.
 *
 * @return int
 */
function total_product_category_count( $cat_id, $taxonomy = null, $post_in = null ) {
	if ( empty( $taxonomy ) ) {
		$taxonomy = get_current_screen_tax();
	}
	$cache_key = 'category_count' . $cat_id . $taxonomy;
	if ( empty( $post_in ) ) {
		if ( is_product_filters_active() ) {
			$exclude_tax = array( $taxonomy );
		} else {
			$exclude_tax = array();
		}
		$post_in = ic_get_current_products( array(), $exclude_tax );
		if ( empty( $post_in ) ) {

			return 0;
		}

		if ( is_array( $post_in ) ) {
			$count_post_in = count( $post_in );
			if ( $count_post_in > 10000 ) {
				$post_in = null;
			}
		} else {
			$count_post_in = 0;
		}
	}

	if ( 'all' === $post_in ) {
		$post_in = null;
	}

	if ( ! empty( $post_in ) ) {
		sort( $post_in, SORT_NUMERIC );
		// $cache_key     .= array_sum( $post_in );
		$pos_in_cache_key = md5( serialize( $post_in ) );
		$cache_key       .= $pos_in_cache_key;
	}
	$cached = ic_get_global( $cache_key, true );
	if ( false !== $cached ) {

		return $cached;
	}

	if ( empty( $count_post_in ) && is_array( $post_in ) ) {
		$count_post_in = count( $post_in );
	} elseif ( ! is_array( $post_in ) ) {
		$count_post_in = 0;
	}
	if ( ! empty( $post_in ) && $count_post_in < 10000 ) {
		global $wpdb;
		$cache_meta       = 'term_ids_count' . $pos_in_cache_key;
		$term_ids_count   = ic_get_global( $cache_meta, true );
		$terms_cache_meta = 'terms_count' . $pos_in_cache_key;
		$terms            = ic_get_global( $terms_cache_meta, true );

		if ( false === $term_ids_count ) {
			ic_raise_memory_limit();
			$post_in       = array_map( 'absint', $post_in );
			$placeholders  = implode( ', ', array_fill( 0, count( $post_in ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL -- The query is prepared inline with a dynamic list of %d placeholders from absint()-sanitized IDs.
			$query_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->term_relationships} WHERE {$wpdb->term_relationships}.object_id IN ($placeholders)", $post_in ), ARRAY_A );
			$term_ids_count = array_count_values( array_column( $query_results, 'term_taxonomy_id' ) );
			$terms          = array();
			foreach ( $query_results as $result ) {
				if ( isset( $terms[ $result['term_taxonomy_id'] ] ) ) {
					$terms[ $result['term_taxonomy_id'] ][] = $result['object_id'];
				} else {
					$terms[ $result['term_taxonomy_id'] ] = array( $result['object_id'] );
				}
			}
				ic_save_global( $cache_meta, $term_ids_count, false, true );
				ic_save_global( $terms_cache_meta, $terms, false, true );
		}


		$term_object = get_term( $cat_id, $taxonomy );

		if ( ! empty( $term_object->term_id ) && ! empty( $term_object->term_taxonomy_id ) && $term_object->term_id !== $term_object->term_taxonomy_id ) {
			$cat_id = $term_object->term_taxonomy_id;
		}
		if ( ! isset( $term_ids_count[ $cat_id ] ) ) {
			$term_ids_count[ $cat_id ] = 0;
		}
		if ( ! empty( $taxonomy ) ) {
			$children = get_term_children( $cat_id, $taxonomy );
			if ( ! is_wp_error( $children ) ) {
				$products_counted = array();
				if (empty($terms)) {
					$terms = array();
				}
				$terms[ $cat_id ] = isset( $terms[ $cat_id ] ) ? $terms[ $cat_id ] : array();
				foreach ( $children as $child_id ) {
					if ( ! empty( $term_ids_count[ $child_id ] ) ) {
						$diff                       = array_diff( $terms[ $child_id ], $products_counted, $terms[ $cat_id ] );
						$term_ids_count[ $cat_id ] += count( $diff );
						$products_counted           = array_unique( $products_counted + $terms[ $child_id ] );
					}
				}
			}
		}
		ic_save_global( $cache_key, $term_ids_count[ $cat_id ], false, true );
		return $term_ids_count[ $cat_id ];
	}
	$query_args = apply_filters(
		'category_count_query',
		array(
			// 'nopaging'     => true,
			'posts_per_page' => 1,
			'post_status'    => ic_visible_product_status(),
			'tax_query'      => array(
				array(
					'taxonomy'         => $taxonomy,
					'terms'            => $cat_id,
					'include_children' => true,
				),
			),
			'fields'         => 'ids',
		),
		$taxonomy
	);
	if ( $post_in ) {
		$query_args['post__in'] = $post_in;
	}
	if ( isset( $_GET['s'] ) && empty( $post_in ) ) {
		$query_args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
	}
	$query_args['update_post_meta_cache'] = false;
	$query_args['update_post_term_cache'] = false;
	remove_action( 'pre_get_posts', 'ic_pre_get_products', 99 );
	$q = apply_filters( 'ic_catalog_category_count_query', '', $query_args );
	if ( empty( $q ) ) {
		$q = new WP_Query( $query_args );
	}
	add_action( 'pre_get_posts', 'ic_pre_get_products', 99 );
	$count = $q->found_posts;
	if ( ! empty( $cache_key ) ) {
		ic_save_global( $cache_key, $count, false, true );
	}
	return $count;
}

if ( ! function_exists( 'ic_get_current_products' ) ) {

	/**
	 * Returns current query product IDs
	 *
	 * @param array $exclude         Excluded query args.
	 * @param array $exclude_tax     Excluded taxonomies.
	 * @param array $exclude_meta    Excluded meta keys.
	 * @param array $exclude_tax_val Excluded term IDs.
	 * @param bool  $replace_post_in Whether to replace the pre-filtered post IDs.
	 *
	 * @return type
	 * @global type $wp_query
	 * @global type $shortcode_query
	 */
	function ic_get_current_products(
		$exclude = array(),
		$exclude_tax = array(),
		$exclude_meta = array(),
		$exclude_tax_val = array(),
		$replace_post_in = false
	) {
		global $shortcode_query, $wp_query;
		if ( is_ic_shortcode_query() && ! empty( $shortcode_query ) ) {
			if ( empty( $shortcode_query->query_vars['post__in'] ) && is_ic_product_listing( $shortcode_query ) && ! is_product_filters_active() && ! apply_filters( 'ic_force_query_count_calculation', false ) ) {
				return 'all';
			}
			$pre_shortcode_query = $wp_query;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary query swap preserves shortcode query compatibility for downstream helpers.
			$wp_query            = $shortcode_query;
		}
		if ( ! empty( $pre_shortcode_query ) || ic_ic_catalog_archive() || ic_get_global( 'inside_show_catalog_shortcode' ) || is_ic_ajax() ) {

			// $cache_key               = 'current_products' . md5( json_encode( $exclude ) . json_encode( $exclude_tax ) . json_encode( $exclude_meta ) . json_encode( $exclude_tax_val ) );
			// $object_current_products = $wp_query->get( 'ic_current_products' );
			// if ( empty( $object_current_products ) ) {
			// $object_current_products = array();
			// }
			// if ( ! isset( $object_current_products[ $cache_key ] ) ) {
			$removed = remove_action( 'ic_pre_get_products_only', 'set_product_order', 30 );
			$return  = ic_process_current_products( $exclude, $exclude_tax, $exclude_meta, $exclude_tax_val, $replace_post_in );
			// $object_current_products[ $cache_key ] = $return;
			// $wp_query->set( 'ic_current_products', $object_current_products );
			if ( $removed ) {
				add_action( 'ic_pre_get_products_only', 'set_product_order', 30 );
			}
			// } else {
			// $return = $object_current_products[ $cache_key ];
			// }

			if ( ! empty( $pre_shortcode_query ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the original global query after shortcode processing.
				$wp_query = $pre_shortcode_query;
			}

			return $return;
		} else {
			global $wp_query;
			if ( ! empty( $pre_shortcode_query ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the original global query after shortcode processing.
				$wp_query = $pre_shortcode_query;
			}
			$product_ids = apply_filters( 'ic_current_products', '' );

			if ( is_array( $product_ids ) ) {
				return $product_ids;
			}

			if ( ic_is_rendering_block() ) {
				return 'all';
			}

			return array();
		}
	}

}

	/**
	 * Processes product IDs for the current catalog context.
	 *
	 * @param array $exclude         Excluded query args.
	 * @param array $exclude_tax     Excluded taxonomies.
	 * @param array $exclude_meta    Excluded meta keys.
	 * @param array $exclude_tax_val Excluded term IDs.
	 * @param bool  $replace_post_in Whether to replace the pre-filtered post IDs.
	 * @return array|string
	 */
function ic_process_current_products(
	$exclude = array(),
	$exclude_tax = array(),
	$exclude_meta = array(),
	$exclude_tax_val = array(),
	$replace_post_in = false
) {
	global $wp_query;
	if ( empty( $wp_query ) ) {
		return array();
	}
	if ( empty( $wp_query->query['pagename'] ) && $wp_query->max_num_pages <= 1 && is_array( $wp_query->posts ) && empty( $exclude ) && empty( $exclude_tax ) && empty( $exclude_meta ) && empty( $exclude_tax_val ) ) {
		if ( empty( $wp_query->posts ) ) {

			return array();
		}

		return wp_list_pluck( $wp_query->posts, 'ID' );
	}
	$product_ids = apply_filters( 'ic_current_products', '' );
	if ( is_array( $product_ids ) ) {

		return $product_ids;
	}
	if ( is_ic_product_listing() && ! is_product_filters_active() && ! is_ic_only_main_cats() && ! apply_filters( 'ic_force_query_count_calculation', false ) ) {
		return 'all';
	} elseif ( is_ic_taxonomy_page() ) {
		ic_raise_memory_limit();
	}
	if ( is_array( $exclude ) && is_array( $exclude_meta ) && is_array( $exclude_tax_val ) ) {
		$cache_key          = 'current_products' . implode( '_', $exclude ) . wp_json_encode( $exclude_tax ) . implode( '_', $exclude_meta ) . implode( '_', $exclude_tax_val ) . strval( $replace_post_in );
		$cached_product_ids = ic_get_global( $cache_key );
		if ( false !== $cached_product_ids ) {

			return $cached_product_ids;
		}
	}

	$catalog_query = ic_get_catalog_query( true );
	if ( empty( $catalog_query->query_vars ) ) {

		return array();
	}
	$args = ic_get_global( 'ic_catalog_query_vars_filtered_' . md5( serialize( $catalog_query->query_vars ) ) );
	if ( false === $args ) {
		$args = array_filter( $catalog_query->query_vars, 'ic_filter_objects' );
		ic_save_global( 'ic_catalog_query_vars_filtered_' . md5( serialize( $catalog_query->query_vars ) ), $args );
	}
	if ( $replace_post_in && ( ! empty( $args['ic_pre_post__in'] ) || 'hard' === $replace_post_in ) ) {
		$args['post__in'] = isset( $args['ic_pre_post__in'] ) ? $args['ic_pre_post__in'] : array();
	} elseif ( ( ! empty( $exclude_tax ) || ! empty( $exclude ) ) && ! empty( $args['ic_pre_post__in'] ) ) {
		$args['post__in'] = $args['ic_pre_post__in'];
	}
	if ( isset( $args['post__in'] ) && 'all' === $args['post__in'] ) {
		unset( $args['post__in'] );
	}
	$args['nopaging']       = true;
	$args['posts_per_page'] = - 1;
	$args['fields']         = 'ids';
	unset( $args['paged'], $args['page'], $args['orderby'], $args['order'], $args['meta_key'] );
	$excluded_arg           = false;
	foreach ( $exclude as $key ) {
		if ( isset( $args[ $key ] ) ) {
			unset( $args[ $key ] );
			$excluded_arg = true;
		}
	}
	if ( ! $excluded_arg ) {
		$exclude = array();
	}
	$applied_exclude_tax = false;
	if ( ! empty( $exclude_tax ) ) {
		if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
			$applied_exclude_tax = true;
			foreach ( $args['tax_query'] as $tax_key => $tax_query ) {
				if ( is_array( $tax_query ) && isset( $tax_query[0] ) ) {
					foreach ( $tax_query as $deeper_key => $deeper_query ) {
						$args = ic_unset_terms_query_terms( $args, $tax_key, $deeper_key, $deeper_query, $exclude_tax, $exclude_tax_val );
					}
				} elseif ( is_array( $tax_query ) && isset( $tax_query['taxonomy'] ) ) {
					$args = ic_unset_terms_query_terms( $args, $tax_key, '', $tax_query, $exclude_tax, $exclude_tax_val );
				} elseif ( ! empty( $tax_query ['taxonomy'] ) && in_array( $tax_query['taxonomy'], $exclude_tax ) ) {
						unset( $args['tax_query'][ $tax_key ] );
				}
			}
			$args['tax_query'] = array_filter( $args['tax_query'] );
		}

		if ( ! empty( $args['taxonomy'] ) && ! is_array( $args['taxonomy'] ) && in_array( $args['taxonomy'], $exclude_tax ) ) {
			$applied_exclude_tax = true;
			if ( empty( $exclude_tax_val ) ) {
				unset( $args['taxonomy'] );
				unset( $args['term_id'] );
			} else {
				if ( ! empty( $args['term_id'] ) && ! is_array( $args['term_id'] ) && in_array( $args['term_id'], $exclude_tax_val ) ) {
					unset( $args['term_id'] );
				}
				if ( empty( $args['term_id'] ) ) {
					unset( $args['term_id'] );
				}
			}
		}
	}
	if ( ! $applied_exclude_tax ) {
		$exclude_tax = array();
	}
	if ( ! empty( $args ['meta_query'] ) && ! empty( $exclude_meta ) ) {
		foreach ( $args['meta_query'] as $meta_key => $meta_query ) {
				$string_meta_query = wp_json_encode( $meta_query );
			foreach ( $exclude_meta as $excluded_meta ) {
				if ( ic_string_contains( $string_meta_query, '"key":"' . $excluded_meta . '"' ) ) {
					unset( $args['meta_query'][ $meta_key ] );
				}
			}
		}
	} else {
		$exclude_meta = array();
	}
	if ( empty( $wp_query->query['pagename'] ) && $wp_query->max_num_pages <= 1 && is_array( $wp_query->posts ) && empty( $exclude ) && empty( $exclude_tax ) && empty( $exclude_meta ) && empty( $replace_post_in ) ) {
		if ( empty( $wp_query->posts ) ) {

			return array();
		}

		return wp_list_pluck( $wp_query->posts, 'ID' );
	}
	if ( is_array( $exclude ) && is_array( $exclude_meta ) && is_array( $exclude_tax_val ) ) {
		$cache_key          = 'current_products' . implode( '_', $exclude ) . json_encode( $exclude_tax ) . implode( '_', $exclude_meta ) . implode( '_', $exclude_tax_val ) . strval( $replace_post_in );
		$cached_product_ids = ic_get_global( $cache_key );
		if ( false !== $cached_product_ids ) {

			return $cached_product_ids;
		}
	}

	$args['ic_exclude_tax']  = $exclude_tax;
	$args['ic_exclude_meta'] = $exclude_meta;
	$args['ic_exclude']      = $exclude;
	if ( empty( $_GET['s'] ) && empty( $args ['post__in'] ) && empty( $args['al_product-cat'] ) && ( empty( $args ['tax_query'] ) || ( ! empty( $args ['tax_query'] ) && count( $args ['tax_query'] ) === 1 && ! empty( $args ['tax_query'][0]['taxonomy'] ) && 'language' === $args ['tax_query'][0]['taxonomy'] ) ) && empty( $args ['meta_query'] ) && ! is_ic_only_main_cats() && ! apply_filters( 'ic_force_query_count_calculation', false ) ) {

		return 'all';
	}
	if ( empty( $args['s'] ) && ! empty( $_GET['s'] ) ) {
		$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
	}
	$current_query = apply_filters( 'ic_catalog_current_products', '', $args );
	if ( empty( $current_query ) ) {
		$query_cache_key = 'ic_current_products_query_' . md5( wp_json_encode( $args ) );
		$cached_query    = ic_transient_cache()->get( $query_cache_key, false );
		if ( false !== $cached_query ) {
			return $cached_query;
		}
		$args['update_post_term_cache'] = false;
		$args['update_post_meta_cache'] = false;
		if ( empty( $args['post__in'] ) && is_ic_product_listing() && is_ic_only_main_cats() /* && ! in_array( get_current_screen_tax(), $exclude ) && ( empty( $args['ic_exclude_tax'] ) || ! in_array( get_current_screen_tax(), $args['ic_exclude_tax'] ) ) */ ) {
			if ( empty( $args ['tax_query'] ) ) {
				$args ['tax_query'] = array();
			}
			$args ['tax_query'][] = ic_get_limit_loose_products_args();
		}
		$args['ic_current_products'] = 1;
		$current_query               = ic_wp_query( $args );
		ic_transient_cache()->set( $query_cache_key, $current_query->posts, 5 * MINUTE_IN_SECONDS );
	}
	$product_ids = $current_query->posts;

	return $product_ids;
}

/**
 * Removes excluded terms from a tax query.
 *
 * @param array      $args            Query args.
 * @param int|string $tax_key         Tax query key.
 * @param int|string $deeper_key      Nested tax query key.
 * @param array      $deeper_query    Tax query clause.
 * @param array      $exclude_tax     Excluded taxonomies.
 * @param array      $exclude_tax_val Excluded term IDs.
 * @return array
 */
function ic_unset_terms_query_terms( $args, $tax_key, $deeper_key, $deeper_query, $exclude_tax, $exclude_tax_val ) {
	if ( ! empty( $deeper_query['taxonomy'] ) && is_array( $exclude_tax ) && in_array( $deeper_query['taxonomy'], $exclude_tax ) ) {
		if ( empty( $exclude_tax_val ) ) {
			if ( '' === $deeper_key ) {
				unset( $args['tax_query'][ $tax_key ] );
			} else {
				unset( $args['tax_query'][ $tax_key ][ $deeper_key ] );
			}
		} elseif ( is_array( $deeper_query['terms'] ) ) {
			foreach ( $deeper_query['terms'] as $term_key => $term ) {
				if ( is_array( $exclude_tax_val ) && in_array( $term, $exclude_tax_val ) ) {
					if ( '' === $deeper_key ) {
						unset( $args['tax_query'][ $tax_key ]['terms'][ $term_key ] );
					} else {
						unset( $args['tax_query'][ $tax_key ][ $deeper_key ]['terms'][ $term_key ] );
					}
				}
			}
		} elseif ( is_array( $exclude_tax_val ) && ! is_array( $deeper_query['terms'] ) && in_array( $deeper_query['terms'], $exclude_tax_val ) ) {
			if ( '' === $deeper_key ) {
				unset( $args['tax_query'][ $tax_key ] ['terms'] );
			} else {
				unset( $args['tax_query'][ $tax_key ][ $deeper_key ]['terms'] );
			}
		}
		if ( ! empty( $exclude_tax_val ) ) {
			if ( '' === $deeper_key && empty( $args['tax_query'][ $tax_key ] ['terms'] ) ) {
				unset( $args['tax_query'][ $tax_key ] );
			} elseif ( empty( $args['tax_query'][ $tax_key ][ $deeper_key ]['terms'] ) ) {
				unset( $args['tax_query'][ $tax_key ][ $deeper_key ] );
			}
		}
		if ( isset( $args['tax_query'][ $tax_key ] ) && count( $args['tax_query'][ $tax_key ] ) === 1 ) {
			unset( $args['tax_query'][ $tax_key ] );
		}
	}

	return $args;
}

add_filter( 'ic_categories_ready_to_show', 'ic_cache_product_images_meta' );

/**
 * Primes product and term image meta caches.
 *
 * @param array $terms Terms to be shown.
 * @return array
 */
function ic_cache_product_images_meta( $terms ) {
	if ( is_ic_admin() ) {
		return $terms;
	}
	global $wp_query;

	if ( ! empty( $wp_query->posts ) ) {
		$ids = wp_list_pluck( $wp_query->posts, 'ID' );
	}

	if ( isset( $terms[0] ) && ! is_numeric( $terms[0] ) ) {
		$term_ids = wp_list_pluck( $terms, 'term_id' );
	} else {
		$term_ids = $terms;
	}
	if ( ! empty( $ids ) ) {
		$to_cache = array();
		foreach ( $ids as $id ) {
			$image_id = get_post_meta( $id, '_thumbnail_id', true );
			if ( ! empty( $image_id ) ) {
				$to_cache[] = $image_id;
			}
		}
	}
	if ( ! empty( $term_ids ) && function_exists( 'get_term_meta' ) ) {
		if ( empty( $to_cache ) ) {
			$to_cache = array();
		}
		foreach ( $term_ids as $id ) {
			$image_id = get_term_meta( $id, 'thumbnail_id', true );
			if ( ! empty( $image_id ) ) {
				$to_cache[] = $image_id;
			}
		}
	}
	if ( ! empty( $to_cache ) ) {
		update_meta_cache( 'post', $to_cache );
	}

	return $terms;
}

/**
 * Runs a cached WP_Query instance.
 *
 * @param array $args   Query args.
 * @param bool  $cached Whether to use persistent cache.
 * @param bool  $main   Whether this is the main query.
 * @return WP_Query
 */
function ic_wp_query( $args, $cached = false, $main = false ) {
	$cache_key = 'ic_wp_query_' . md5( serialize( $args ) );
	$prev      = ic_get_global( $cache_key, $cached );
	if ( false !== $prev ) {
		return $prev;
	}
	$final_args = array();
	$is_search  = is_ic_product_search();
	foreach ( $args as $i => $arg ) {
		if ( ( ! $is_search || 's' !== $i ) && ( '' === $arg || null === $arg || array() === $arg ) ) {
			continue;
		}
		$final_args[ $i ] = $arg;
	}
	$final_args = apply_filters( 'ic_wp_query_args', $final_args );
	if ( $main ) {
		$final_args['ic_main_query'] = true;
	} elseif ( isset( $final_args['ic_main_query'] ) ) {
		unset( $final_args['ic_main_query'] );
	}
	if ( isset( $final_args['nopaging'] ) && isset( $final_args['posts_per_page'] ) && -1 === $final_args['posts_per_page'] ) {
		$final_args['nopaging'] = true;
	}
	$current_query = new WP_Query( $final_args );

	ic_save_global( $cache_key, $current_query, false, $cached );

	return $current_query;
}

/**
 * Runs a cached object terms lookup.
 *
 * @param array|int    $post_ids Post IDs.
 * @param string|array $taxonomy Taxonomy slug or list.
 * @param array        $args     Term query args.
 * @param bool         $cached   Whether to use persistent cache.
 * @return array|WP_Error
 */
function ic_wp_get_object_terms( $post_ids, $taxonomy, $args = array(), $cached = false ) {
	$cache_key = 'ic_wp_get_object_terms_' . md5( serialize( array( $post_ids, $taxonomy, $args ) ) );
	$pre       = ic_get_global( $cache_key, $cached );
	if ( false !== $pre ) {
		return $pre;
	}
	$args['object_ids'] = $post_ids;
	$args['taxonomy']   = $taxonomy;
	$terms              = ic_get_terms( $args );

	ic_save_global( $cache_key, $terms, false, $cached );

	return $terms;
}
