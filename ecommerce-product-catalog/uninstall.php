<?php
/**
 * Uninstall eCommerce Product Catalog.
 *
 * Uninstalling eCommerce Product Catalog deletes user roles and options.
 *
 * @package ecommerce-product-catalog/uninstall
 * @version 2.3.7
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! function_exists( 'ic_ai_manager' ) ) {
	$framework_index = __DIR__ . '/ic/index.php';
	if ( file_exists( $framework_index ) ) {
		require_once $framework_index;
	}
}

if ( function_exists( 'ic_ai_manager' ) ) {
	ic_ai_manager()->revoke_site_key( 'plugin_uninstall' );
}

if ( ! defined( 'AL_BASE_PATH' ) ) {
	if ( ! taxonomy_exists( 'al_product-cat' ) ) {
		register_taxonomy( 'al_product-cat', 'al_product', array( 'public' => false ) );
	}
	if ( ! taxonomy_exists( 'al_product-attributes' ) ) {
		register_taxonomy( 'al_product-attributes', 'al_product', array( 'public' => false ) );
	}
	$uninstall_products = get_option( 'ic_delete_products_uninstall', 0 );

	if ( 1 === (int) $uninstall_products ) {

		if ( ! function_exists( 'ic_delete_all_attribute_terms' ) ) {

			/**
			 * Delete all product attribute terms
			 *
			 * @global type $wpdb
			 */
			function ic_delete_all_attribute_terms() {
				$taxonomy  = 'al_product-attributes';
					$terms = get_terms(
						array(
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
						)
					);

					// Delete terms.
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $attribute_term ) {
						wp_delete_term( (int) $attribute_term->term_id, $taxonomy );
					}
				}
			}

		}

		$product_ids = get_posts(
			array(
				'post_type'      => 'al_product',
				'post_status'    => get_post_stati( array(), 'names' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $product_ids as $product_id ) {
			wp_delete_post( (int) $product_id, true );
		}

			$product_category_taxonomy = 'al_product-cat';
				$terms                 = get_terms(
					array(
						'taxonomy'   => $product_category_taxonomy,
						'hide_empty' => false,
					)
				);

			// Delete terms.
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $catalog_term ) {
				wp_delete_term( (int) $catalog_term->term_id, $product_category_taxonomy );
				delete_option( 'al_product_cat_image_' . $catalog_term->term_id );
			}
		}

		ic_delete_all_attribute_terms();

		remove_role( 'catalog_manager' );
		remove_role( 'ic_order_manager' );

		global $wpdb;
		/*
		 * Statistics data is business history (orders, revenue, payments,
		 * telemetry), so it is removed ONLY under this explicit opt-in, exactly
		 * like the products it describes - and only when no other active plugin
		 * ships the shared statistics framework, because the five `ic_stats_*`
		 * tables are shared by every IC integration on the site. With the opt-in
		 * off, or with a sibling integration still active, nothing statistics
		 * related is touched. Decision recorded in `plugin-architecture.md`.
		 */
		$ic_stats_siblings = 0;
		$ic_active_plugins = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		foreach ( array_unique( $ic_active_plugins ) as $ic_active_plugin ) {
			$ic_plugin_directory = WP_PLUGIN_DIR . '/' . dirname( (string) $ic_active_plugin );
			if ( __DIR__ === $ic_plugin_directory ) {
				continue;
			}
			if ( file_exists( $ic_plugin_directory . '/ic/stats/class-ic-stats-manager.php' ) ) {
				++$ic_stats_siblings;
			}
		}
		if ( 0 === $ic_stats_siblings ) {
			foreach ( array( 'ic_stats_event_metrics', 'ic_stats_events', 'ic_stats_aggregates', 'ic_stats_lifecycle_metrics', 'ic_stats_lifecycles' ) as $ic_stats_table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall drops only fixed allowlisted framework table suffixes joined to the trusted WordPress prefix.
				$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $ic_stats_table );
			}
			$ic_stats_like = $wpdb->esc_like( 'ic_stats_' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall enumerates its own options with prepared values.
			$ic_stats_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $ic_stats_like, '_transient_' . $ic_stats_like, '_transient_timeout_' . $ic_stats_like ) );
			foreach ( (array) $ic_stats_options as $ic_stats_option ) {
				delete_option( $ic_stats_option );
			}
		}
	}


	if ( ! function_exists( 'all_ic_options' ) ) {

			/**
			 * Returns all eCommerce Product Catalog option names.
			 *
			 * @param string $which Option subset selector.
			 * @return array
			 */
		function all_ic_options( $which = 'all' ) {
			$options = array(
				'product_adder_theme_support_check',
				'product_attributes_number',
				'al_display_attributes',
				'product_attribute',
				'product_attribute_label',
				'product_attribute_unit',
				'archive_template',
				'modern_grid_settings',
				'classic_grid_settings',
				'catalog_lightbox',
				'catalog_magnifier',
				'multi_single_options',
				'default_product_thumbnail',
				'design_schemes',
				'archive_names',
				'single_names',
				'product_listing_url',
				'product_currency',
				'product_currency_settings',
				'product_archive',
				'enable_product_listing',
				'archive_multiple_settings',
				'product_shipping_options_number',
				'display_shipping',
				'product_shipping_cost',
				'product_shipping_label',
				'product_archive_page_id',
			);
			$tools   = array(
				'ic_epc_tracking_last_send',
				'ic_epc_tracking_notice',
				'ic_epc_allow_tracking',
				'ic_delete_products_uninstall',
				'ecommerce_product_catalog_ver',
				'sample_product_id',
				'al_permalink_options_update',
				'custom_license_code',
				'implecode_license_owner',
				'no_implecode_license_error',
				'license_active_plugins',
				'product_adder_theme_support_check',
				'implecode_hide_plugin_review_info_count',
				'hide_empty_bar_message',
				'ic_hidden_boxes',
				'ic_hidden_notices',
				'old_sort_bar',
				'first_activation_version',
				'ic_allow_woo_template_file',
				'ic_block_woo_template_file',
			);
			if ( 'all' === $which ) {
				return array_merge( $options, $tools );
			} elseif ( 'tools' === $which ) {
				return $tools;
			} else {
				return $options;
			}
		}

	}

	$all_options = all_ic_options();
	foreach ( $all_options as $option ) {
		delete_option( $option );
	}

	delete_user_meta( get_current_user_id(), 'ic_review_hidden' );
	$hidden_notice_users = get_users(
		array(
			'fields'      => 'ids',
			'number'      => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Uninstall must find users holding the plugin's exact notice meta key for cleanup.
			'meta_key'    => '_ic_hidden_notices',
			'count_total' => false,
		)
	);
	foreach ( $hidden_notice_users as $hidden_notice_user_id ) {
		delete_user_meta( (int) $hidden_notice_user_id, '_ic_hidden_notices' );
	}
}
