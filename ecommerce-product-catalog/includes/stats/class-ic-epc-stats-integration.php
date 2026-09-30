<?php
/**
 * Base EPC Stats integration.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( function_exists( 'ic_epc_register_stats_integration' ) ) {
	return;
}

if ( ! function_exists( 'ic_epc_stats_catalog_mode' ) ) {
	/** Return the effective Catalog Mode used by Stats. */
	function ic_epc_stats_catalog_mode() {
		$mode = function_exists( 'ic_get_catalog_mode' ) ? (string) ic_get_catalog_mode() : 'simple';
		return sanitize_key( (string) apply_filters( 'ic_epc_stats_catalog_mode', $mode ) );
	}
}

if ( ! function_exists( 'ic_epc_stats_cart_available' ) ) {
	/** Return whether the effective mode has a loaded cart implementation. */
	function ic_epc_stats_cart_available() {
		$available = in_array( ic_epc_stats_catalog_mode(), array( 'store', 'inquiry' ), true ) && function_exists( 'ic_cart_save' );
		return (bool) apply_filters( 'ic_epc_stats_cart_available', $available );
	}
}

if ( ! function_exists( 'ic_epc_stats_orders_available' ) ) {
	/** Return whether the effective mode records local orders or inquiries. */
	function ic_epc_stats_orders_available() {
		$available = in_array( ic_epc_stats_catalog_mode(), array( 'store', 'inquiry' ), true );
		return (bool) apply_filters( 'ic_epc_stats_orders_available', $available );
	}
}

if ( ! function_exists( 'ic_epc_stats_payments_available' ) ) {
	/** Return whether payment reporting is meaningful in the effective mode. */
	function ic_epc_stats_payments_available() {
		$mode          = ic_epc_stats_catalog_mode();
		$quote_pricing = function_exists( 'is_ic_quote_pricing_enabled' ) ? (bool) is_ic_quote_pricing_enabled() : (bool) apply_filters( 'is_ic_quote_pricing_enabled', false );
		$available     = 'store' === $mode || ( 'inquiry' === $mode && $quote_pricing );
		return (bool) apply_filters( 'ic_epc_stats_payments_available', $available );
	}
}

if ( ! function_exists( 'ic_epc_stats_ai_available' ) ) {
	/** Return whether the installed AI manager exposes any Stats targets. */
	function ic_epc_stats_ai_available() {
		$available = false;
		if ( function_exists( 'ic_ai_manager' ) ) {
			$manager   = ic_ai_manager();
			$available = is_object( $manager ) && method_exists( $manager, 'targets' ) && (bool) $manager->targets();
		}
		return (bool) apply_filters( 'ic_epc_stats_ai_available', $available );
	}
}

if ( ! function_exists( 'ic_epc_stats_metric_block_reason' ) ) {
	/** Return the capability reason that prevents one metric from collecting. */
	function ic_epc_stats_metric_block_reason( $metric ) {
		$metric          = sanitize_key( $metric );
		$cart_metrics    = array( 'cart_starts', 'active_carts', 'cart_events', 'co_added_products', 'cart_abandonments', 'cart_recoveries', 'cart_duration', 'cart_funnel', 'cart_conversions', 'cart_conversion', 'product_add_to_cart', 'add_to_cart_quantities', 'view_to_add_funnel' );
		$order_metrics   = array( 'orders_created', 'orders_completed', 'order_completion_rate', 'order_status_distribution', 'average_submitted_item_count', 'order_earnings', 'average_order_value', 'purchased_units', 'product_rankings', 'product_order_counts', 'product_revenue', 'frequently_bought_together', 'registered_order_count', 'guest_order_count', 'guest_order_revenue', 'guest_order_aov', 'unique_registered_customers', 'returning_registered_customers', 'registered_customer_orders', 'registered_customer_revenue', 'registered_customer_aov', 'requested_order_value', 'average_requested_order_value', 'free_orders', 'free_order_customers', 'view_to_purchase_funnel', 'guest_session_conversion', 'orders_created_by_product', 'orders_completed_by_product', 'order_earnings_by_product', 'average_order_value_by_product', 'requested_order_value_by_product', 'average_requested_order_value_by_product' );
		$payment_metrics = array( 'payment_receipts', 'successful_payments', 'gateway_distribution', 'payment_status_distribution', 'payment_failures' );
		if ( 'affiliate_clicks' === $metric ) {
			return 'affiliate' === ic_epc_stats_catalog_mode()
				? __( 'Affiliate click tracking is not implemented yet.', 'ecommerce-product-catalog' )
				: __( 'Affiliate click tracking is not part of this catalog.', 'ecommerce-product-catalog' );
		}
		if ( in_array( $metric, $cart_metrics, true ) && ! ic_epc_stats_cart_available() ) {
			return __( 'The shopping cart is not active in this catalog mode, so no cart activity can be recorded.', 'ecommerce-product-catalog' );
		}
		if ( in_array( $metric, array_merge( $order_metrics, $payment_metrics ), true ) && ! ic_epc_stats_orders_available() ) {
			return __( 'Order recording is unavailable on this site, so no order data can be recorded.', 'ecommerce-product-catalog' );
		}
		if ( in_array( $metric, $payment_metrics, true ) && ! ic_epc_stats_payments_available() ) {
			return 'inquiry' === ic_epc_stats_catalog_mode()
				? __( 'This catalog is in inquiry mode, so no payments are taken.', 'ecommerce-product-catalog' )
				: __( 'This catalog does not take payments, so no payment data can be recorded.', 'ecommerce-product-catalog' );
		}
		if ( ( 0 === strpos( $metric, 'ai_' ) || 'current_ai_inventory' === $metric ) && ! ic_epc_stats_ai_available() ) {
			return __( 'AI enhancement is not available on this site, so no AI activity can be recorded.', 'ecommerce-product-catalog' );
		}
		return '';
	}
}

if ( ! function_exists( 'ic_epc_stats_metric_relevance' ) ) {
	/** Apply effective-mode relevance after the saved metric setting. */
	function ic_epc_stats_metric_relevance( $enabled, $integration, $metric ) {
		if ( 'ecommerce-product-catalog' !== $integration ) {
			return $enabled;
		}
		if ( '' !== ic_epc_stats_metric_block_reason( $metric ) ) {
			return false;
		}
		return (bool) apply_filters( 'ic_epc_stats_metric_relevant', $enabled, $metric, ic_epc_stats_catalog_mode() );
	}
}

if ( false === has_filter( 'ic_stats_metric_enabled', 'ic_epc_stats_metric_relevance' ) ) {
	add_filter( 'ic_stats_metric_enabled', 'ic_epc_stats_metric_relevance', 30, 3 );
}

if ( ! function_exists( 'ic_epc_stats_base_metric_definitions' ) ) {
	/**
	 * Return the fixed base metric catalogue.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function ic_epc_stats_base_metric_definitions() {
		$metrics = array(
			'orders_created'                 => array(
				'source_mode' => 'durable_projection',
				'events'      => array( 'order_created' ),
			),
			'orders_completed'               => array(
				'source_mode' => 'durable_projection',
				'events'      => array( 'order_completed' ),
			),
			'order_earnings'                 => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'sum',
				'events'      => array( 'order_completed' ),
			),
			'average_order_value'            => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'average',
				'events'      => array( 'order_completed' ),
			),
			'purchased_units'                => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'sum',
				'events'      => array( 'order_completed' ),
			),
			'order_completion_rate'          => array(
				'source_mode'        => 'durable_projection',
				'calculation'        => 'ratio',
				'numerator_metric'   => 'orders_completed',
				'denominator_metric' => 'orders_created',
				'events'             => array(),
			),
			'registered_customer_orders'     => array(
				'source_mode' => 'durable_projection',
				'events'      => array( 'order_completed' ),
			),
			'registered_customer_revenue'    => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'sum',
				'events'      => array( 'order_completed' ),
			),
			'registered_customer_aov'        => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'average',
				'events'      => array( 'order_completed' ),
			),
			'unique_registered_customers'    => array(
				'source_mode' => 'durable_projection',
				'events'      => array( 'order_completed' ),
				'distinct_by' => 'user_id',
			),
			'returning_registered_customers' => array(
				'source_mode' => 'durable_projection',
				'events'      => array( 'order_completed' ),
				'distinct_by' => 'user_id',
				'fact_mode'   => 'returning_identity',
			),
			'guest_order_count'              => array(
				'source_mode'       => 'durable_projection',
				'events'            => array( 'order_created' ),
				'backfill_provider' => 'saved_order_customer',
			),
			'guest_order_revenue'            => array(
				'source_mode'       => 'durable_projection',
				'calculation'       => 'sum',
				'events'            => array( 'order_created' ),
				'backfill_provider' => 'saved_order_customer',
			),
			'guest_order_aov'                => array(
				'source_mode'       => 'durable_projection',
				'calculation'       => 'average',
				'events'            => array( 'order_created' ),
				'backfill_provider' => 'saved_order_customer',
			),
			'successful_payments'            => array(
				'source_mode' => 'durable_projection',
				'events'      => array( 'payment_recorded' ),
			),
			'product_revenue'                => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'sum',
				'events'      => array( 'order_completed' ),
			),
			'product_order_counts'           => array(
				'source_mode' => 'durable_projection',
				'events'      => array( 'order_completed' ),
			),
			'payment_failures'               => array(
				'source_mode'        => 'telemetry',
				'events'             => array( 'payment_failure' ),
				'data_quality_label' => 'attempts may be incomplete for legacy emitters',
			),
			'requested_order_value'          => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'sum',
				'events'      => array( 'order_created' ),
			),
			'average_requested_order_value'  => array(
				'source_mode' => 'durable_projection',
				'calculation' => 'average',
				'events'      => array( 'order_created' ),
			),
		);
		$labels  = array(
			'orders_created'                 => __( 'Orders created', 'ecommerce-product-catalog' ),
			'orders_completed'               => __( 'Orders completed', 'ecommerce-product-catalog' ),
			'order_earnings'                 => __( 'Gross order earnings', 'ecommerce-product-catalog' ),
			'average_order_value'            => __( 'Average order value', 'ecommerce-product-catalog' ),
			'purchased_units'                => __( 'Purchased units', 'ecommerce-product-catalog' ),
			'order_completion_rate'          => __( 'Order completion rate', 'ecommerce-product-catalog' ),
			'registered_customer_orders'     => __( 'Registered customer orders', 'ecommerce-product-catalog' ),
			'registered_customer_revenue'    => __( 'Registered customer revenue', 'ecommerce-product-catalog' ),
			'registered_customer_aov'        => __( 'Registered customer AOV', 'ecommerce-product-catalog' ),
			'unique_registered_customers'    => __( 'Unique registered customers', 'ecommerce-product-catalog' ),
			'returning_registered_customers' => __( 'Returning registered customers', 'ecommerce-product-catalog' ),
			'guest_order_count'              => __( 'Guest saved orders', 'ecommerce-product-catalog' ),
			'guest_order_revenue'            => __( 'Guest saved-order revenue', 'ecommerce-product-catalog' ),
			'guest_order_aov'                => __( 'Guest saved-order AOV', 'ecommerce-product-catalog' ),
			'successful_payments'            => __( 'Successful payments', 'ecommerce-product-catalog' ),
			'product_revenue'                => __( 'Product revenue', 'ecommerce-product-catalog' ),
			'product_order_counts'           => __( 'Product order counts', 'ecommerce-product-catalog' ),
			'payment_failures'               => __( 'Payment failures', 'ecommerce-product-catalog' ),
			'requested_order_value'          => __( 'Requested order value', 'ecommerce-product-catalog' ),
			'average_requested_order_value'  => __( 'Average requested order value', 'ecommerce-product-catalog' ),
		);
		foreach ( $labels as $metric => $label ) {
			$metrics[ $metric ]['label'] = $label;
		}
		foreach ( array( 'order_earnings', 'average_order_value', 'registered_customer_revenue', 'registered_customer_aov', 'guest_order_revenue', 'guest_order_aov', 'product_revenue', 'requested_order_value', 'average_requested_order_value' ) as $metric ) {
			$metrics[ $metric ]['format'] = 'money';
		}
		$metrics['order_completion_rate']['format'] = 'percentage';

		$product_sources = array(
			'orders_created'                => 'orders_created_by_product',
			'orders_completed'              => 'orders_completed_by_product',
			'order_earnings'                => 'order_earnings_by_product',
			'average_order_value'           => 'average_order_value_by_product',
			'requested_order_value'         => 'requested_order_value_by_product',
			'average_requested_order_value' => 'average_requested_order_value_by_product',
		);
		foreach ( $product_sources as $public_metric => $internal_metric ) {
			$metrics[ $internal_metric ]                    = $metrics[ $public_metric ];
			$metrics[ $internal_metric ]['internal']        = true;
			$metrics[ $internal_metric ]['enabled_with']    = $public_metric;
			$metrics[ $internal_metric ]['default_enabled'] = false;
			$metrics[ $public_metric ]['filter_sources']    = array( 'product' => $internal_metric );
		}
		foreach ( $metrics as $metric => &$definition ) {
			if ( empty( $definition['internal'] ) ) {
				$definition['default_enabled'] = true;
			}
		}
		unset( $definition );
		return $metrics;
	}
}

if ( ! function_exists( 'ic_epc_register_stats_integration' ) ) {
	/** Register EPC's base-only Stats definition. */
	function ic_epc_register_stats_integration() {
		static $registered = false;
		if ( $registered || ! function_exists( 'ic_stats_register_integration' ) ) {
			return;
		}
		$registered = true;
		$metrics    = ic_epc_stats_base_metric_definitions();
		ic_stats_register_integration(
			'ecommerce-product-catalog',
			array(
				'api_version'        => 2,
				'schema_version'     => 4,
				'projection_version' => 6,
				'supports'           => array( 'durable_projection', 'telemetry' ),
				'pages'              => array(
					'overview'  => array(
						'label'   => __( 'Overview', 'ecommerce-product-catalog' ),
						'metrics' => array( 'orders_created', 'orders_completed', 'order_earnings', 'average_order_value', 'purchased_units' ),
					),
					'products'  => array(
						'label'   => __( 'Products', 'ecommerce-product-catalog' ),
						'metrics' => array( 'purchased_units', 'product_revenue', 'product_order_counts' ),
					),
					'orders'    => array(
						'label'   => __( 'Orders', 'ecommerce-product-catalog' ),
						'metrics' => array( 'orders_created', 'orders_completed', 'order_completion_rate', 'requested_order_value', 'average_requested_order_value' ),
					),
					'customers' => array(
						'label'   => __( 'Customers', 'ecommerce-product-catalog' ),
						'metrics' => array( 'registered_customer_orders', 'registered_customer_revenue', 'registered_customer_aov', 'unique_registered_customers', 'returning_registered_customers', 'guest_order_count', 'guest_order_revenue', 'guest_order_aov' ),
					),
					'sales'     => array(
						'label'   => __( 'Sales & Payments', 'ecommerce-product-catalog' ),
						'metrics' => array( 'order_earnings', 'average_order_value', 'requested_order_value', 'average_requested_order_value', 'successful_payments', 'payment_failures' ),
					),
				),
				'metrics'            => $metrics,
				'defaults'           => array_fill_keys(
					array_keys(
						array_filter(
							$metrics,
							static function ( $metric ) {
								return ! empty( $metric['default_enabled'] ); }
						)
					),
					true
				),
			)
		);
		if ( function_exists( 'ic_payment_stats_load_normalizer' ) ) {
			ic_payment_stats_load_normalizer();
		}
		if ( function_exists( 'ic_stats_install_schema' ) ) {
			ic_stats_install_schema();
		}
		ic_epc_stats_register_base_backfill();
		ic_epc_stats_maybe_schedule_backfill();
	}
	add_action( 'ic_epc_loaded', 'ic_epc_register_stats_integration', 20 );
	add_action( 'init', 'ic_epc_register_stats_integration', 20 );
}

if ( ! function_exists( 'ic_epc_stats_backfill_metrics' ) ) {
	/** Return the base metrics supported by the saved-order provider. */
	function ic_epc_stats_backfill_metrics() {
		$metrics = array(
			'orders_created',
			'orders_completed',
			'order_earnings',
			'average_order_value',
			'purchased_units',
			'order_completion_rate',
			'registered_customer_orders',
			'registered_customer_revenue',
			'registered_customer_aov',
			'unique_registered_customers',
			'returning_registered_customers',
			'guest_order_count',
			'guest_order_revenue',
			'guest_order_aov',
			'successful_payments',
			'product_revenue',
			'product_order_counts',
			'payment_failures',
			'requested_order_value',
			'average_requested_order_value',
			'orders_created_by_product',
			'orders_completed_by_product',
			'order_earnings_by_product',
			'average_order_value_by_product',
			'requested_order_value_by_product',
			'average_requested_order_value_by_product',
		);
		if ( function_exists( 'ic_epc_stats_pro_backfill_metrics' ) ) {
			$metrics = array_merge( $metrics, ic_epc_stats_pro_backfill_metrics() );
		}
		return array_values( array_unique( $metrics ) );
	}
}

if ( ! function_exists( 'ic_epc_stats_projection_version' ) ) {
	/** Return EPC's durable saved-order projection version. */
	function ic_epc_stats_projection_version() {
		$definition = function_exists( 'ic_stats_manager' ) ? ic_stats_manager()->integration( 'ecommerce-product-catalog' ) : array();
		return max( 1, absint( $definition['projection_version'] ?? 6 ) );
	}
}

if ( ! function_exists( 'ic_epc_stats_handle_integration_reset' ) ) {
	/** Re-arm the base saved-order projection after a successful reset. */
	function ic_epc_stats_handle_integration_reset( $integration, $reason ) {
		if ( 'ecommerce-product-catalog' !== $integration || 'reset_integration' !== $reason ) {
			return;
		}
		delete_option( 'ic_stats_ecommerce-product-catalog_backfill_request' );
		if ( ic_epc_stats_orders_available() && function_exists( 'ic_stats_schedule_backfill' ) ) {
			ic_stats_schedule_backfill( 'ecommerce-product-catalog', ic_epc_stats_backfill_metrics(), array( 'provider' => 'saved_order_customer' ) );
		}
	}
	add_action( 'ic_stats_aggregates_changed', 'ic_epc_stats_handle_integration_reset', 5, 2 );
}

if ( ! function_exists( 'ic_epc_stats_base_order_lines' ) ) {
	/** Normalize automatic, manual, and custom saved-order lines. */
	function ic_epc_stats_base_order_lines( $order_id, $override = array() ) {
		$order_id = absint( $order_id );
		$stored   = $override;
		if ( ! $stored && $order_id ) {
			$stored = get_post_meta( $order_id, '_order_products', true );
		}
		$stored = is_array( $stored ) ? $stored : array();
		if ( function_exists( 'ic_get_order_products' ) ) {
			$stored = (array) ic_get_order_products( $stored );
		}
		$lines      = array();
		$ids        = (array) ( $stored['product_id'] ?? array() );
		$names      = (array) ( $stored['product_name'] ?? array() );
		$quantities = (array) ( $stored['product_quantity'] ?? array() );
		$prices     = (array) ( $stored['product_price'] ?? array() );
		foreach ( array_unique( array_merge( array_keys( $ids ), array_keys( $names ) ) ) as $index ) {
			$product_id = absint( $ids[ $index ] ?? 0 );
			$name       = sanitize_text_field( (string) ( $names[ $index ] ?? '' ) );
			if ( ! $product_id && '' === $name ) {
				continue;
			}
			$lines[] = array(
				'product_id' => $product_id,
				'name'       => $name,
				'quantity'   => max( 0, (float) ( $quantities[ $index ] ?? 1 ) ),
				'price'      => (float) ( $prices[ $index ] ?? 0 ),
			);
		}
		$manual = $order_id && function_exists( 'ic_get_all_manual_order_products' ) ? ic_get_all_manual_order_products( $order_id ) : ( $order_id ? get_post_meta( $order_id, 'manual_order_product', true ) : array() );
		foreach ( (array) $manual as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$product_id = absint( $row['id'] ?? 0 );
			$name       = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( ! $product_id && '' === $name ) {
				continue;
			}
			$quantity = max( 0, (float) ( $row['quantity'] ?? 1 ) );
			$lines[]  = array(
				'product_id' => $product_id,
				'name'       => $name,
				'quantity'   => $quantity,
				'price'      => isset( $row['summary'] ) && is_numeric( $row['summary'] ) ? (float) $row['summary'] : (float) ( $row['price'] ?? 0 ) * $quantity,
			);
		}
		return $lines;
	}
}

if ( ! function_exists( 'ic_epc_stats_base_order_payload' ) ) {
	/**
	 * Normalize the stable subset used by base order metrics.
	 *
	 * @param int   $order_id Order identifier.
	 * @param array $source   Event payload.
	 * @return array
	 */
	function ic_epc_stats_base_order_payload( $order_id, $source = array() ) {
		$order_id = absint( $order_id );
		$source   = is_array( $source ) ? $source : array();
		$summary  = is_array( $source['order_summary'] ?? null ) ? $source['order_summary'] : array();
		$payment  = is_array( $source['payment_details'] ?? null ) ? $source['payment_details'] : array();
		if ( $order_id && ! $summary ) {
			$stored  = get_post_meta( $order_id, '_order_summary', true );
			$summary = is_array( $stored ) ? $stored : array();
		}
		if ( $order_id && ! $payment ) {
			$stored  = get_post_meta( $order_id, '_payment_details', true );
			$payment = is_array( $stored ) ? $stored : array();
		}
		$products   = isset( $source['products'] ) && is_array( $source['products'] ) ? $source['products'] : ic_epc_stats_base_order_lines( $order_id, (array) ( $source['order_products'] ?? array() ) );
		$amount     = $source['amount'] ?? $summary['price'] ?? $summary['total'] ?? $payment['amount'] ?? 0;
		$currency   = $source['currency'] ?? $payment['currency'] ?? $payment['order-currency'] ?? $summary['currency'] ?? get_option( 'product_currency', '' );
		$customer   = $source['user_id'] ?? ( $order_id ? get_post_meta( $order_id, '_customer_id', true ) : 0 );
		$dimensions = isset( $source['dimensions'] ) && is_array( $source['dimensions'] ) ? $source['dimensions'] : array();
		if ( ! $customer && $order_id ) {
			$removed = (string) get_post_meta( $order_id, '_ic_removed_customer', true );
			if ( function_exists( 'ic_stats_is_removed_identity' ) && ic_stats_is_removed_identity( $removed ) ) {
				$dimensions['user_id'] = $removed;
			}
		}
		$payload = array(
			'order_id'   => absint( $order_id ),
			'amount'     => (float) $amount,
			'currency'   => sanitize_text_field( (string) $currency ),
			'user_id'    => absint( $customer ),
			'products'   => $products,
			'dimensions' => $dimensions,
		);
		$status  = sanitize_key( (string) ( $source['status'] ?? $payment['status'] ?? '' ) );
		if ( $status ) {
			$payload['status'] = $status;
		}
		return $payload;
	}
}

if ( ! function_exists( 'ic_epc_stats_base_record_order' ) ) {
	/** Record a base order event. */
	function ic_epc_stats_base_record_order( $event, $order_id, $source = array() ) {
		if ( function_exists( 'ic_stats_record' ) ) {
			$payload               = ic_epc_stats_base_order_payload( $order_id, $source );
			$payload['dedupe_key'] = sanitize_key( $event ) . ':' . absint( $order_id );
			ic_stats_record( 'ecommerce-product-catalog', $event, $payload );
		}
	}
}

if ( ! function_exists( 'ic_epc_stats_base_product_dimensions' ) ) {
	/** Return stable product/category dimensions for a saved line. */
	function ic_epc_stats_base_product_dimensions( $line ) {
		$product_id = absint( $line['product_id'] ?? 0 );
		if ( ! $product_id ) {
			$label = sanitize_text_field( (string) ( $line['name'] ?? '' ) );
			$name  = sanitize_title( $label );
			return $name ? array(
				'product'       => 'custom:' . $name,
				'product_label' => $label,
			) : array();
		}
		$dimensions = array( 'product_id' => $product_id );
		$post_type  = get_post_type( $product_id );
		if ( $post_type ) {
			$dimensions['catalog'] = sanitize_key( $post_type );
			foreach ( (array) get_object_taxonomies( $post_type ) as $taxonomy ) {
				$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) && $terms ) {
					$dimensions['category_id'] = absint( $terms[0] );
					break;
				}
			}
		}
		return $dimensions;
	}
}

if ( ! function_exists( 'ic_epc_stats_metric_contributions' ) ) {
	/** Preserve base product and customer projection semantics. */
	function ic_epc_stats_metric_contributions( $contributions, $integration, $metric, $event, $payload ) {
		if ( 'ecommerce-product-catalog' !== $integration ) {
			return $contributions;
		}
		$event_key = is_array( $event ) ? sanitize_key( $event['event_key'] ?? '' ) : sanitize_key( $event );
		$payload   = is_array( $payload ) ? $payload : array();
		$base      = function_exists( 'ic_epc_stats_base_metric_definitions' ) ? ic_epc_stats_base_metric_definitions() : array();
		if ( ! isset( $base[ $metric ] ) && function_exists( 'ic_epc_stats_pro_metric_contributions' ) ) {
			return ic_epc_stats_pro_metric_contributions( $contributions, $integration, $metric, $event, $payload );
		}
		if ( ! in_array( $event_key, array( 'order_created', 'order_completed' ), true ) ) {
			return $contributions;
		}
		$products = isset( $payload['products'] ) && is_array( $payload['products'] ) ? $payload['products'] : array();
		$internal = array(
			'orders_created_by_product'                => array( 'order_created', 'count' ),
			'orders_completed_by_product'              => array( 'order_completed', 'count' ),
			'order_earnings_by_product'                => array( 'order_completed', 'amount' ),
			'average_order_value_by_product'           => array( 'order_completed', 'amount' ),
			'requested_order_value_by_product'         => array( 'order_created', 'amount' ),
			'average_requested_order_value_by_product' => array( 'order_created', 'amount' ),
		);
		if ( isset( $internal[ $metric ] ) ) {
			list( $required_event, $value_type ) = $internal[ $metric ];
			if ( $required_event !== $event_key ) {
				return array();
			}
			$out  = array();
			$seen = array();
			foreach ( $products as $line ) {
				$dimensions = ic_epc_stats_base_product_dimensions( (array) $line );
				$key        = (string) ( $dimensions['product_id'] ?? $dimensions['product'] ?? '' );
				if ( ! $key || isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$out[]        = array(
					'value'      => 'amount' === $value_type ? (float) ( $payload['amount'] ?? 0 ) : 1,
					'count'      => 1,
					'currency'   => 'amount' === $value_type ? sanitize_text_field( (string) ( $payload['currency'] ?? '' ) ) : '',
					'dimensions' => $dimensions,
				);
			}
			return $out;
		}
		$customer_metrics = array( 'registered_customer_orders', 'registered_customer_revenue', 'registered_customer_aov', 'unique_registered_customers', 'returning_registered_customers', 'guest_order_count', 'guest_order_revenue', 'guest_order_aov' );
		if ( in_array( $metric, $customer_metrics, true ) ) {
			$removed    = isset( $payload['dimensions']['user_id'] ) && function_exists( 'ic_stats_is_removed_identity' ) && ic_stats_is_removed_identity( $payload['dimensions']['user_id'] ) ? (string) $payload['dimensions']['user_id'] : '';
			$registered = absint( $payload['user_id'] ?? 0 ) ?: $removed;
			$is_guest   = 0 === strpos( $metric, 'guest_' );
			if ( ( $is_guest && $registered ) || ( ! $is_guest && ! $registered ) ) {
				return array();
			}
			$value = in_array( $metric, array( 'registered_customer_revenue', 'registered_customer_aov', 'guest_order_revenue', 'guest_order_aov' ), true ) ? (float) ( $payload['amount'] ?? 0 ) : 1;
			return array(
				array(
					'value'      => $value,
					'count'      => 1,
					'currency'   => sanitize_text_field( (string) ( $payload['currency'] ?? '' ) ),
					'dimensions' => array(
						'user_id'   => $registered,
						'user_role' => is_array( $event ) ? sanitize_key( $event['user_role'] ?? '' ) : '',
					),
				),
			);
		}
		if ( 'product_order_counts' === $metric || in_array( $metric, array( 'purchased_units', 'product_revenue' ), true ) ) {
			$out  = array();
			$seen = array();
			foreach ( $products as $line ) {
				$line       = (array) $line;
				$dimensions = ic_epc_stats_base_product_dimensions( $line );
				$key        = (string) ( $dimensions['product_id'] ?? $dimensions['product'] ?? '' );
				if ( ! $key || ( 'product_order_counts' === $metric && isset( $seen[ $key ] ) ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$out[]        = array(
					'value'      => 'product_revenue' === $metric ? (float) ( $line['price'] ?? 0 ) : ( 'purchased_units' === $metric ? max( 0, (float) ( $line['quantity'] ?? 1 ) ) : 1 ),
					'count'      => 1,
					'currency'   => 'product_revenue' === $metric ? sanitize_text_field( (string) ( $payload['currency'] ?? '' ) ) : '',
					'dimensions' => $dimensions,
				);
			}
			return $out;
		}
		return $contributions;
	}
	add_filter( 'ic_stats_metric_contributions', 'ic_epc_stats_metric_contributions', 10, 7 );
}

if ( ! function_exists( 'ic_epc_stats_backfill' ) ) {
	/** Project one bounded batch of saved EPC orders. */
	function ic_epc_stats_backfill( $limit = 100, $cursor = 0 ) {
		if ( function_exists( 'ic_epc_stats_pro_legacy_backfill' ) ) {
			return ic_epc_stats_pro_legacy_backfill( $limit, $cursor );
		}
		global $wpdb;
		$limit  = max( 1, min( 500, absint( $limit ) ) );
		$cursor = absint( $cursor );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded projection query over the canonical posts table.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND ID > %d ORDER BY ID ASC LIMIT %d", 'al_digital_orders', 'publish', $cursor, $limit ) );
		foreach ( $ids as $id ) {
			$id      = absint( $id );
			$payload = ic_epc_stats_base_order_payload( $id );
			$date    = get_post_field( 'post_date_gmt', $id );
			$context = array(
				'backfill'             => true,
				'occurred_at_gmt'      => $date && '0000-00-00 00:00:00' !== $date ? gmdate( 'Y-m-d H:i:s', strtotime( $date ) ) : '',
				'timestamp_quality'    => 'source',
				'timestamp_provenance' => 'source_post_date_gmt',
				'quality'              => 'unknown_historical',
				'identity'             => array(
					'quality'     => 'unknown_historical',
					'persistence' => 'none',
					'role'        => 'unknown_historical',
				),
			);
			if ( ! empty( $payload['user_id'] ) ) {
				$context['identity']['user_id'] = absint( $payload['user_id'] );
				$context['identity']['quality'] = 'source_saved_order';
				$user                           = get_user_by( 'id', $payload['user_id'] );
				if ( $user && ! empty( $user->roles ) ) {
					$context['identity']['role'] = sanitize_key( $user->roles[0] );
				}
			} elseif ( ! empty( $payload['dimensions']['user_id'] ) ) {
				$context['identity']['quality'] = 'removed_identity';
				$context['identity']['role']    = sanitize_key( get_post_meta( $id, '_ic_removed_customer_role', true ) );
			}
			$payload['dedupe_key'] = 'order_created:' . $id;
			ic_stats_record( 'ecommerce-product-catalog', 'order_created', $payload, $context );
			$status = sanitize_key( $payload['status'] ?? '' );
			$done   = function_exists( 'ic_is_order_completion_status' ) ? ic_is_order_completion_status( $status ) : 'completed' === $status;
			if ( $done ) {
				$payload['dedupe_key'] = 'order_completed:' . $id;
				ic_stats_record( 'ecommerce-product-catalog', 'order_completed', $payload, $context );
				$outcome = class_exists( 'IC_Payment_Stats_Normalizer' ) && method_exists( 'IC_Payment_Stats_Normalizer', 'outcome' ) ? IC_Payment_Stats_Normalizer::outcome( $status ) : 'successful';
				if ( (float) $payload['amount'] > 0 && 'successful' === $outcome ) {
					$payment                   = get_post_meta( $id, '_payment_details', true );
					$payment                   = is_array( $payment ) ? $payment : array();
					$gateway                   = sanitize_key( $payment['payment_name'] ?? '' );
					$payload['gateway']        = $gateway ?: 'unknown';
					$payload['payment_status'] = $status;
					$payload['dimensions']     = array_merge(
						(array) ( $payload['dimensions'] ?? array() ),
						array(
							'gateway'        => $payload['gateway'],
							'gateway_source' => $gateway ? 'recorded' : 'unknown',
							'payment_status' => $status,
						)
					);
					$payload['dedupe_key']     = 'payment_recorded:' . $id;
					ic_stats_record( 'ecommerce-product-catalog', 'payment_recorded', $payload, $context );
				}
			}
			$cursor = $id;
		}
		if ( count( $ids ) < $limit ) {
			update_option(
				'ic_stats_ecommerce-product-catalog_projection_status',
				array(
					'state'          => 'process',
					'version'        => ic_epc_stats_projection_version(),
					'updated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				),
				false
			);
		}
		return array(
			'processed' => count( $ids ),
			'cursor'    => $cursor,
		);
	}
}

if ( ! function_exists( 'ic_epc_stats_maybe_schedule_backfill' ) ) {
	/** Schedule saved-order projection only when history is absent or failed. */
	function ic_epc_stats_maybe_schedule_backfill() {
		if ( ! ic_epc_stats_orders_available() || ! function_exists( 'ic_stats_projection_status' ) || ! function_exists( 'ic_stats_schedule_backfill' ) ) {
			return false;
		}
		$projection = get_option( 'ic_stats_ecommerce-product-catalog_projection_status', false );
		$state      = is_array( $projection ) ? (string) ( $projection['state'] ?? '' ) : '';
		$missing    = 'complete' === $state && function_exists( 'ic_stats_has_data' ) && ! ic_stats_has_data( 'ecommerce-product-catalog' );
		if ( false !== $projection && 'failed' !== $state && ! $missing ) {
			return false;
		}
		ic_stats_schedule_backfill( 'ecommerce-product-catalog', ic_epc_stats_backfill_metrics(), array( 'provider' => 'saved_order_customer' ) );
		return true;
	}
}

if ( ! function_exists( 'ic_epc_stats_backfill_provider' ) ) {
	/** Adapter for the shared isolated backfill worker. */
	function ic_epc_stats_backfill_provider( $limit = 100, $request = array() ) {
		$cursor = absint( $request['cursor'] ?? 0 );
		$result = ic_epc_stats_backfill( $limit, $cursor );
		return array(
			'processed' => absint( $result['processed'] ?? 0 ),
			'cursor'    => absint( $result['cursor'] ?? $cursor ),
			'complete'  => absint( $result['processed'] ?? 0 ) < absint( $limit ),
			'state'     => absint( $result['processed'] ?? 0 ) < absint( $limit ) ? 'complete' : 'pending',
		);
	}
}

if ( ! function_exists( 'ic_epc_stats_register_base_backfill' ) ) {
	/** Register the base saved-order provider independently of Pro. */
	function ic_epc_stats_register_base_backfill() {
		if ( function_exists( 'ic_stats_register_backfill_provider' ) ) {
			ic_stats_register_backfill_provider( 'saved_order_customer', 'ic_epc_stats_backfill_provider' );
		}
	}
	add_action( 'ic_stats_registry_ready', 'ic_epc_stats_register_base_backfill', 20 );
}

/** Base hook adapters are named so a later Pro load can replace them cleanly. */
function ic_epc_stats_base_order_created( $order_id, $data = array() ) {
	ic_epc_stats_base_record_order( 'order_created', $order_id, $data );
}
function ic_epc_stats_base_order_saved( $order_id = 0, $pre_name = '', $payment_details = array(), $order_products = array(), $order_summary = array() ) {
	unset( $pre_name );
	ic_epc_stats_base_record_order( 'order_created', $order_id, compact( 'payment_details', 'order_products', 'order_summary' ) );
}
function ic_epc_stats_base_order_completed( $order_id = 0, $payment_details = array(), $order_products = array() ) {
	ic_epc_stats_base_record_order( 'order_completed', $order_id, compact( 'payment_details', 'order_products' ) );
}
function ic_epc_stats_base_order_completed_payload( $order_id, $data = array() ) {
	ic_epc_stats_base_record_order( 'order_completed', $order_id, $data );
}
function ic_epc_stats_base_payment_recorded( $payload ) {
	if ( function_exists( 'ic_stats_record' ) ) {
		ic_stats_record( 'ecommerce-product-catalog', 'payment_recorded', is_array( $payload ) ? $payload : array() );
	}
}
function ic_epc_stats_base_payment_failed( $payload ) {
	if ( function_exists( 'ic_stats_record' ) ) {
		ic_stats_record( 'ecommerce-product-catalog', 'payment_failure', is_array( $payload ) ? $payload : array() );
	}
}
function ic_epc_stats_detach_base_collectors() {
	remove_action( 'ic_order_created', 'ic_epc_stats_base_order_created', 20 );
	remove_action( 'ic_save_order', 'ic_epc_stats_base_order_saved', 20 );
	remove_action( 'order_completed', 'ic_epc_stats_base_order_completed', 20 );
	remove_action( 'ic_order_completed', 'ic_epc_stats_base_order_completed_payload', 20 );
	remove_action( 'ic_payment_transaction_recorded', 'ic_epc_stats_base_payment_recorded', 20 );
	remove_action( 'ic_payment_transaction_failed', 'ic_epc_stats_base_payment_failed', 20 );
}

add_action( 'ic_order_created', 'ic_epc_stats_base_order_created', 20, 2 );
add_action( 'ic_save_order', 'ic_epc_stats_base_order_saved', 20, 5 );
add_action( 'order_completed', 'ic_epc_stats_base_order_completed', 20, 3 );
add_action( 'ic_order_completed', 'ic_epc_stats_base_order_completed_payload', 20, 2 );
add_action( 'ic_payment_transaction_recorded', 'ic_epc_stats_base_payment_recorded', 20 );
add_action( 'ic_payment_transaction_failed', 'ic_epc_stats_base_payment_failed', 20 );
