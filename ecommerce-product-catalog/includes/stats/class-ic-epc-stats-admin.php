<?php
/**
 * Definition-driven base EPC Stats administration.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'IC_EPC_Stats_Admin' ) ) {
	/** Render, filter, export, and configure every registered base report. */
	class IC_EPC_Stats_Admin {
		/** Register administration hooks. */
		public function __construct() {
			add_action( 'product_settings_menu', array( $this, 'menu' ) );
			add_action( 'admin_init', array( $this, 'save' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
			add_action( 'admin_post_ic_epc_stats_export', array( $this, 'export' ) );
		}

		/** Register the Stats submenu. */
		public function menu() {
			if ( ! $this->available() ) {
				return;
			}
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- EPC registers this capability.
			add_submenu_page( 'edit.php?post_type=al_product', __( 'Stats', 'ecommerce-product-catalog' ), __( 'Stats', 'ecommerce-product-catalog' ), 'manage_product_settings', 'ic-epc-stats', array( $this, 'screen' ) );
		}

		/** Enqueue the existing report assets. */
		public function assets( $hook ) {
			if ( ! $this->available() || false === strpos( (string) $hook, 'ic-epc-stats' ) ) {
				return;
			}
			$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
			$root  = dirname( __DIR__, 2 ) . '/ecommerce-product-catalog.php';
			wp_enqueue_style( 'ic-epc-stats', plugins_url( 'includes/stats/stats' . ( $debug ? '' : '.min' ) . '.css', $root ), array(), '1.0' );
			wp_enqueue_script( 'ic-epc-stats', plugins_url( 'includes/stats/stats' . ( $debug ? '' : '.min' ) . '.js', $root ), array(), '1.0', true );
			wp_enqueue_style( 'ic_chosen' );
			wp_enqueue_script( 'ic_chosen' );
			wp_add_inline_script( 'ic_chosen', 'jQuery(function($){$(".ic-stats-filters .ic_chosen").chosen({width:"200px",search_contains:true,allow_single_deselect:true});});' );
			if ( function_exists( 'ic_selector_enqueue_assets' ) ) {
				ic_selector_enqueue_assets();
			}
		}

		/** Return the current registered integration. */
		private function definition() {
			return (array) ic_stats_manager()->integration( 'ecommerce-product-catalog' );
		}

		/** Return whether base Stats administration is meaningful in this mode. */
		private function available() {
			return function_exists( 'ic_epc_stats_orders_available' ) && ic_epc_stats_orders_available();
		}

		/** Return whether the effective mode uses inquiry presentation. */
		private function inquiry_mode() {
			return function_exists( 'ic_epc_stats_catalog_mode' ) && 'inquiry' === ic_epc_stats_catalog_mode();
		}

		/** Return non-empty registered pages and their public metrics. */
		private function pages() {
			$definition = $this->definition();
			$pages      = array();
			foreach ( (array) ( $definition['pages'] ?? array() ) as $key => $page ) {
				$metrics = array_values(
					array_filter(
						(array) ( $page['metrics'] ?? array() ),
						static function ( $metric ) use ( $definition ) {
							return isset( $definition['metrics'][ $metric ] ) && empty( $definition['metrics'][ $metric ]['internal'] ) && ( ! function_exists( 'ic_epc_stats_metric_block_reason' ) || '' === ic_epc_stats_metric_block_reason( $metric ) );
						}
					)
				);
				if ( $metrics ) {
					$label = (string) ( $page['label'] ?? ucwords( str_replace( '_', ' ', $key ) ) );
					if ( $this->inquiry_mode() ) {
						if ( 'orders' === $key ) {
							$label = __( 'Carts & Inquiries', 'ecommerce-product-catalog' );
						} elseif ( 'sales' === $key ) {
							$label = ic_epc_stats_payments_available() ? __( 'Inquiries & Payments', 'ecommerce-product-catalog' ) : __( 'Inquiries', 'ecommerce-product-catalog' );
						}
					}
					$pages[ sanitize_key( $key ) ] = array(
						'label'   => $label,
						'metrics' => $metrics,
					);
				}
			}
			return $pages;
		}

		/** Resolve the selected registered page. */
		private function current_page() {
			$pages = $this->pages();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report navigation.
			$page = isset( $_GET['stats_tab'] ) ? sanitize_key( wp_unslash( $_GET['stats_tab'] ) ) : ( isset( $_GET['stats_page'] ) ? sanitize_key( wp_unslash( $_GET['stats_page'] ) ) : 'overview' );
			return isset( $pages[ $page ] ) ? $page : (string) array_key_first( $pages );
		}

		/** Return the filters supported by one base report. */
		private function filter_fields( $page ) {
			$pages   = $this->pages();
			$metrics = (array) ( $pages[ $page ]['metrics'] ?? array() );
			$order_metrics = array( 'orders_created', 'orders_completed', 'order_earnings', 'average_order_value', 'purchased_units', 'order_completion_rate', 'registered_customer_orders', 'registered_customer_revenue', 'registered_customer_aov', 'unique_registered_customers', 'returning_registered_customers', 'guest_order_count', 'guest_order_revenue', 'guest_order_aov', 'product_revenue', 'product_order_counts', 'requested_order_value', 'average_requested_order_value' );
			$fields = array(
				'from'     => array( __( 'From', 'ecommerce-product-catalog' ), 'date' ),
				'to'       => array( __( 'To', 'ecommerce-product-catalog' ), 'date' ),
				'catalog'  => array( __( 'Catalog', 'ecommerce-product-catalog' ), 'choice' ),
				'currency' => array( __( 'Currency', 'ecommerce-product-catalog' ), 'choice' ),
			);
			if ( array_intersect( $metrics, $order_metrics ) ) {
				$fields['product']          = array( __( 'Product', 'ecommerce-product-catalog' ), 'product' );
				$fields['order_value_type'] = array( $this->inquiry_mode() ? __( 'Inquiry value', 'ecommerce-product-catalog' ) : __( 'Order value', 'ecommerce-product-catalog' ), 'choice' );
			}
			if ( 'products' === $page ) {
				$fields['category'] = array( __( 'Category', 'ecommerce-product-catalog' ), 'category' );
			}
			if ( 'customers' === $page ) {
				$fields['user'] = array( __( 'Registered user', 'ecommerce-product-catalog' ), 'user' );
				$fields['role'] = array( __( 'User role', 'ecommerce-product-catalog' ), 'role' );
			}
			if ( array_intersect( $metrics, array( 'successful_payments', 'payment_failures' ) ) ) {
				$fields['gateway'] = array( __( 'Gateway', 'ecommerce-product-catalog' ), 'choice' );
				$fields['status']  = array( __( 'Payment status', 'ecommerce-product-catalog' ), 'choice' );
			}
			return $fields;
		}

		/** Sanitize report arguments from the request. */
		private function report_args( $page ) {
			$args = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filters.
			$request = wp_unslash( $_GET );
			foreach ( $this->filter_fields( $page ) as $key => $label ) {
				unset( $label );
				if ( isset( $request[ $key ] ) && is_scalar( $request[ $key ] ) ) {
					$args[ $key ] = sanitize_text_field( $request[ $key ] );
				}
			}
			$args['from']    = $args['from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			$args['to']      = $args['to'] ?? gmdate( 'Y-m-d' );
			$args['compare'] = isset( $request['compare'] ) && 'previous' === sanitize_key( $request['compare'] ) ? 'previous' : 'none';
			return $args;
		}

		/** Save only the current page's registered metrics, preserving every unknown key. */
		public function save() {
			if ( ! $this->available() || empty( $_POST['ic_epc_stats_save'] ) ) {
				return;
			}
			check_admin_referer( 'ic_epc_stats_save' );
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- EPC registers this capability.
			if ( ! current_user_can( 'manage_product_settings' ) ) {
				return;
			}
			$pages     = $this->pages();
			$page      = isset( $_POST['stats_tab'] ) ? sanitize_key( wp_unslash( $_POST['stats_tab'] ) ) : '';
			$allow     = (array) ( $pages[ $page ]['metrics'] ?? array() );
			$saved     = get_option( 'ic_stats_ecommerce-product-catalog', array() );
			$saved     = is_array( $saved ) ? $saved : array();
			$submitted = isset( $_POST['metrics'] ) && is_array( $_POST['metrics'] ) ? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['metrics'] ) ) ) : array();
			foreach ( $allow as $metric ) {
				$saved[ $metric ] = in_array( $metric, $submitted, true );
			}
			update_option( 'ic_stats_ecommerce-product-catalog', $saved, false );
		}

		/** Human label supplied by the registered metric metadata. */
		private function label( $metric ) {
			if ( $this->inquiry_mode() ) {
				$labels = array(
					'orders_created'                 => __( 'Inquiries', 'ecommerce-product-catalog' ),
					'orders_completed'               => __( 'Completed inquiries', 'ecommerce-product-catalog' ),
					'order_completion_rate'          => __( 'Inquiry completion rate', 'ecommerce-product-catalog' ),
					'requested_order_value'          => __( 'Inquiry value', 'ecommerce-product-catalog' ),
					'average_requested_order_value'  => __( 'Average inquiry value', 'ecommerce-product-catalog' ),
					'order_earnings'                 => __( 'Completed inquiry value', 'ecommerce-product-catalog' ),
					'average_order_value'            => __( 'Average completed inquiry value', 'ecommerce-product-catalog' ),
					'purchased_units'                => __( 'Completed inquiry units', 'ecommerce-product-catalog' ),
					'product_revenue'                => __( 'Product inquiry value', 'ecommerce-product-catalog' ),
					'product_order_counts'           => __( 'Product inquiry counts', 'ecommerce-product-catalog' ),
					'registered_customer_orders'     => __( 'Registered customer inquiries', 'ecommerce-product-catalog' ),
					'registered_customer_revenue'    => __( 'Registered customer inquiry value', 'ecommerce-product-catalog' ),
					'registered_customer_aov'        => __( 'Average registered-customer inquiry value', 'ecommerce-product-catalog' ),
					'unique_registered_customers'    => __( 'Unique registered inquirers', 'ecommerce-product-catalog' ),
					'returning_registered_customers' => __( 'Returning registered inquirers', 'ecommerce-product-catalog' ),
					'guest_order_count'              => __( 'Guest saved inquiries', 'ecommerce-product-catalog' ),
					'guest_order_revenue'            => __( 'Guest saved inquiry value', 'ecommerce-product-catalog' ),
					'guest_order_aov'                => __( 'Average guest inquiry value', 'ecommerce-product-catalog' ),
				);
				if ( isset( $labels[ $metric ] ) ) {
					return $labels[ $metric ];
				}
			}
			$definition = $this->definition();
			return (string) ( $definition['metrics'][ $metric ]['label'] ?? ucwords( str_replace( '_', ' ', $metric ) ) );
		}

		/** Format one summary using registered calculation/format metadata. */
		private function format_value( $metric, $summary ) {
			$definition = $this->definition();
			$meta       = (array) ( $definition['metrics'][ $metric ] ?? array() );
			$value      = (float) ( $summary['display_value'] ?? $summary['value'] ?? 0 );
			$format     = (string) ( $meta['format'] ?? '' );
			if ( 'money' === $format ) {
				$currency = (string) array_key_first( (array) ( $summary['currency'] ?? array() ) );
				return trim( $currency . ' ' . number_format_i18n( $value, 2 ) );
			}
			if ( 'percentage' === $format || 'ratio' === ( $meta['calculation'] ?? '' ) ) {
				return number_format_i18n( $value, 2 ) . '%';
			}
			return number_format_i18n( $value, 'average' === ( $meta['calculation'] ?? '' ) ? 2 : 0 );
		}

		/** Build a report URL while preserving the admin route. */
		private function url( $page, $args = array() ) {
			return add_query_arg(
				array_merge(
					array(
						'post_type' => 'al_product',
						'page'      => 'ic-epc-stats',
						'stats_tab' => $page,
					),
					$args
				),
				admin_url( 'edit.php' )
			);
		}

		/** Build a nonce-protected export URL. */
		private function export_url( $page, $args ) {
			return wp_nonce_url(
				add_query_arg(
					array_merge(
						array(
							'action'    => 'ic_epc_stats_export',
							'stats_tab' => $page,
						),
						$args
					),
					admin_url( 'admin-post.php' )
				),
				'ic_epc_stats_export'
			);
		}

		/** Render an AJAX-backed selector, retaining the current value in fallback mode. */
		private function selector_control( $key, $source, $all_label, $selected, $extra_options = '' ) {
			if ( function_exists( 'ic_selector_field' ) && function_exists( 'ic_selector_registry' ) && ic_selector_registry()->has( $source ) ) {
				$field = (string) ic_selector_field(
					array(
						'source'       => $source,
						'name'         => $key,
						'id'           => 'ic-stats-' . $key,
						'value'        => $selected,
						'first_option' => $all_label,
						'class'        => 'ic-stats-selector',
					)
				);
				$close = strrpos( $field, '</select>' );
				if ( '' !== (string) $extra_options && false !== $close ) {
					$field = substr( $field, 0, $close ) . $extra_options . substr( $field, $close );
				}
				return $field;
			}
			$html = '<select id="ic-stats-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"><option value="">' . esc_html( $all_label ) . '</option>';
			if ( '' !== (string) $selected && '' === (string) $extra_options ) {
				$html .= '<option value="' . esc_attr( $selected ) . '" selected="selected">' . esc_html( '#' . $selected ) . '</option>';
			}
			return $html . $extra_options . '</select>';
		}

		/** Return the fixed erased-customer option. */
		private function removed_identity_options( $selected ) {
			if ( ! function_exists( 'ic_stats_is_removed_identity' ) || ! function_exists( 'ic_stats_removed_identity_label' ) ) {
				return '';
			}
			$value = function_exists( 'ic_stats_removed_identity_value' ) ? (string) ic_stats_removed_identity_value( 0 ) : 'removed';
			return '<option value="' . esc_attr( $value ) . '"' . ( ic_stats_is_removed_identity( (string) $selected ) ? ' selected="selected"' : '' ) . '>' . esc_html( ic_stats_removed_identity_label() ) . '</option>';
		}

		/** Build bounded dimension choices and retain a selected legacy value. */
		private function dimension_filter_choices( $key, $selected = '' ) {
			$labels = array( '' => __( 'All', 'ecommerce-product-catalog' ) );
			if ( 'order_value_type' === $key ) {
				$labels['paid'] = $this->inquiry_mode() ? __( 'Priced inquiry', 'ecommerce-product-catalog' ) : __( 'Paid', 'ecommerce-product-catalog' );
				$labels['free'] = $this->inquiry_mode() ? __( 'Zero-value inquiry', 'ecommerce-product-catalog' ) : __( 'Free', 'ecommerce-product-catalog' );
			} elseif ( 'status' === $key ) {
				foreach ( array( 'pending', 'successful', 'failed', 'cancelled', 'refunded' ) as $status ) {
					$labels[ $status ] = ucwords( str_replace( '_', ' ', $status ) );
				}
			} elseif ( 'catalog' === $key ) {
				$post_types = function_exists( 'product_post_type_array' ) ? (array) product_post_type_array() : array( 'al_product' );
				foreach ( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) as $post_type ) {
					$object               = get_post_type_object( $post_type );
					$labels[ $post_type ] = $object && isset( $object->labels->name ) ? $object->labels->name : $post_type;
				}
				$labels['multiple'] = __( 'Multiple catalogs', 'ecommerce-product-catalog' );
			}
			$dimension = 'status' === $key ? 'payment_status' : $key;
			$stored    = function_exists( 'ic_stats_dimension_choices' ) ? (array) ic_stats_dimension_choices( 'ecommerce-product-catalog', array( $dimension ), array( 'limit' => 100 ) ) : array();
			foreach ( (array) ( $stored[ $dimension ] ?? array() ) as $value => $label ) {
				$labels[ (string) $value ] = (string) $label;
			}
			$selected = sanitize_text_field( (string) $selected );
			if ( '' !== $selected && ! isset( $labels[ $selected ] ) ) {
				/* translators: %s: unavailable or legacy filter value. */
				$labels[ $selected ] = sprintf( __( '%s (unavailable or legacy)', 'ecommerce-product-catalog' ), $selected );
			}
			return $labels;
		}

		/** Render report filters from the page's declared base dimensions. */
		private function filter_form( $page, $args ) {
			$html = '<form class="ic-stats-filters" method="get"><input type="hidden" name="post_type" value="al_product" /><input type="hidden" name="page" value="ic-epc-stats" /><input type="hidden" name="stats_tab" value="' . esc_attr( $page ) . '" />';
			foreach ( $this->filter_fields( $page ) as $key => $field ) {
				$html .= '<label for="ic-stats-' . esc_attr( $key ) . '"><span>' . esc_html( $field[0] ) . '</span>';
				if ( 'product' === $field[1] ) {
					$html .= $this->selector_control( $key, 'ic_products', __( 'All products', 'ecommerce-product-catalog' ), $args[ $key ] ?? '' );
				} elseif ( 'category' === $field[1] ) {
					$html .= $this->selector_control( $key, 'ic_product_categories', __( 'All categories', 'ecommerce-product-catalog' ), $args[ $key ] ?? '' );
				} elseif ( 'user' === $field[1] ) {
					$html .= $this->selector_control( $key, 'ic_users', __( 'All registered users', 'ecommerce-product-catalog' ), $args[ $key ] ?? '', $this->removed_identity_options( $args[ $key ] ?? '' ) );
				} elseif ( 'role' === $field[1] ) {
					$choices = array( '' => __( 'All roles', 'ecommerce-product-catalog' ) );
					foreach ( (array) wp_roles()->roles as $slug => $role ) {
						$choices[ $slug ] = translate_user_role( $role['name'] );
					}
					$choices['unknown_historical'] = __( 'Unknown historical role', 'ecommerce-product-catalog' );
					$selected                      = sanitize_text_field( (string) ( $args[ $key ] ?? '' ) );
					if ( '' !== $selected && ! isset( $choices[ $selected ] ) ) {
						/* translators: %s: unavailable or legacy role. */
						$choices[ $selected ] = sprintf( __( '%s (unavailable or legacy)', 'ecommerce-product-catalog' ), $selected );
					}
					$html .= '<select class="ic_chosen" id="ic-stats-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
					foreach ( $choices as $value => $label ) {
						$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
					}
					$html .= '</select>';
				} elseif ( 'choice' === $field[1] ) {
					$html .= '<select class="ic_chosen" id="ic-stats-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
					foreach ( $this->dimension_filter_choices( $key, $args[ $key ] ?? '' ) as $value => $label ) {
						$html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $args[ $key ] ?? '', $value, false ) . '>' . esc_html( $label ) . '</option>';
					}
					$html .= '</select>';
				} else {
					$html .= '<input id="ic-stats-' . esc_attr( $key ) . '" type="date" name="' . esc_attr( $key ) . '" value="' . esc_attr( $args[ $key ] ?? '' ) . '" />';
				}
				$html .= '</label>';
			}
			$html .= '<label for="ic-stats-compare"><span>' . esc_html__( 'Compare', 'ecommerce-product-catalog' ) . '</span><select id="ic-stats-compare" name="compare"><option value="none">' . esc_html__( 'None', 'ecommerce-product-catalog' ) . '</option><option value="previous" ' . selected( 'previous', $args['compare'], false ) . '>' . esc_html__( 'Previous period', 'ecommerce-product-catalog' ) . '</option></select></label><button class="button button-primary">' . esc_html__( 'Apply filters', 'ecommerce-product-catalog' ) . '</button><a class="button" href="' . esc_url( $this->url( $page ) ) . '">' . esc_html__( 'Reset filters', 'ecommerce-product-catalog' ) . '</a></form>';
			return $html;
		}

		/** Render page-scoped metric enablement controls. */
		private function metrics_panel( $page, $metrics ) {
			$definition = $this->definition();
			$saved      = get_option( 'ic_stats_ecommerce-product-catalog', array() );
			$html       = '<details class="ic-stats-metrics" open><summary>' . esc_html__( 'Metrics collected on this report', 'ecommerce-product-catalog' ) . '</summary><p class="description">' . esc_html__( 'Disable a metric to stop new collection. Existing store records can be recalculated; optional telemetry starts only when enabled.', 'ecommerce-product-catalog' ) . '</p><form method="post">' . wp_nonce_field( 'ic_epc_stats_save', '_wpnonce', false, false ) . '<input type="hidden" name="ic_epc_stats_save" value="1" /><input type="hidden" name="stats_tab" value="' . esc_attr( $page ) . '" /><div class="ic-stats-metric-grid">';
			foreach ( $metrics as $metric ) {
				$default    = ! empty( $definition['metrics'][ $metric ]['default_enabled'] );
				$enabled    = isset( $saved[ $metric ] ) ? (bool) $saved[ $metric ] : $default;
				$durable    = 'telemetry' !== ( $definition['metrics'][ $metric ]['source_mode'] ?? 'telemetry' );
				$collection = $durable
					? ( $this->inquiry_mode()
						? __( 'This total is calculated from existing inquiry records, including activity from before you switched it on.', 'ecommerce-product-catalog' )
						: __( 'This total is calculated from existing store records, including activity from before you switched it on.', 'ecommerce-product-catalog' ) )
					: __( 'This starts counting only after you enable it and cannot fill in earlier activity.', 'ecommerce-product-catalog' );
				$body       = '<label><input type="checkbox" name="metrics[' . esc_attr( $metric ) . ']" value="1" ' . checked( $enabled, true, false ) . ' /> <strong>' . esc_html( $this->label( $metric ) ) . '</strong></label><p>' . esc_html( $collection . ' ' . $this->metric_usefulness( $metric ) ) . '</p>';
				$html      .= IC_Stats_Admin_Primitives::metric_tile( $metric, $enabled, '', $body );
			}
			return $html . '</div><p><button class="button">' . esc_html__( 'Save metrics', 'ecommerce-product-catalog' ) . '</button></p></form></details>';
		}

		/** Return a plain-language reason to collect one base metric. */
		private function metric_usefulness( $metric ) {
			if ( $this->inquiry_mode() ) {
				$inquiry = array(
					'orders_created'                 => __( 'Useful for tracking demand and how many inquiries customers submit over time.', 'ecommerce-product-catalog' ),
					'orders_completed'               => __( 'Useful for measuring how many inquiries are completed compared with how many were submitted.', 'ecommerce-product-catalog' ),
					'order_earnings'                 => __( 'Useful for tracking completed inquiry value over time.', 'ecommerce-product-catalog' ),
					'average_order_value'            => __( 'Useful for judging the typical value of a completed inquiry.', 'ecommerce-product-catalog' ),
					'order_completion_rate'          => __( 'Useful for measuring how many submitted inquiries are completed.', 'ecommerce-product-catalog' ),
					'requested_order_value'          => __( 'Useful for tracking the total value customers inquire about.', 'ecommerce-product-catalog' ),
					'average_requested_order_value'  => __( 'Useful for understanding the typical value of an inquiry.', 'ecommerce-product-catalog' ),
					'registered_customer_orders'     => __( 'Useful for tracking inquiries from registered customers.', 'ecommerce-product-catalog' ),
					'registered_customer_revenue'    => __( 'Useful for seeing inquiry value from registered customers.', 'ecommerce-product-catalog' ),
					'registered_customer_aov'        => __( 'Useful for comparing average registered-customer inquiry value with guest inquiries.', 'ecommerce-product-catalog' ),
					'unique_registered_customers'    => __( 'Useful for counting distinct registered inquirers.', 'ecommerce-product-catalog' ),
					'returning_registered_customers' => __( 'Useful for measuring how many registered customers inquire again.', 'ecommerce-product-catalog' ),
					'guest_order_count'              => __( 'Useful for counting inquiries saved by guests.', 'ecommerce-product-catalog' ),
					'guest_order_revenue'            => __( 'Useful for tracking inquiry value from guests.', 'ecommerce-product-catalog' ),
					'guest_order_aov'                => __( 'Useful for understanding the average value of a guest inquiry.', 'ecommerce-product-catalog' ),
					'product_revenue'                => __( 'Useful for identifying products with the highest inquiry value.', 'ecommerce-product-catalog' ),
					'product_order_counts'           => __( 'Useful for seeing which products appear in the most inquiries.', 'ecommerce-product-catalog' ),
				);
				if ( isset( $inquiry[ $metric ] ) ) {
					return $inquiry[ $metric ];
				}
			}
			$map = array(
				'orders_created'                 => __( 'Useful for tracking demand and how many orders customers start over time.', 'ecommerce-product-catalog' ),
				'orders_completed'               => __( 'Useful for measuring how many orders actually go through compared with how many were started.', 'ecommerce-product-catalog' ),
				'order_earnings'                 => __( 'Useful for tracking revenue trends and seeing which periods earn the most.', 'ecommerce-product-catalog' ),
				'average_order_value'            => __( 'Useful for judging how much a typical order is worth.', 'ecommerce-product-catalog' ),
				'purchased_units'                => __( 'Useful for understanding sales volume and stock movement regardless of price.', 'ecommerce-product-catalog' ),
				'product_revenue'                => __( 'Useful for identifying your top-earning products and where revenue concentrates.', 'ecommerce-product-catalog' ),
				'product_order_counts'           => __( 'Useful for seeing which products appear in the most orders regardless of price.', 'ecommerce-product-catalog' ),
				'order_completion_rate'          => __( 'Useful for measuring checkout health and how many started orders actually finish.', 'ecommerce-product-catalog' ),
				'requested_order_value'          => __( 'Useful for tracking the total value customers request before completion.', 'ecommerce-product-catalog' ),
				'average_requested_order_value'  => __( 'Useful for understanding the typical value of a requested order.', 'ecommerce-product-catalog' ),
				'registered_customer_orders'     => __( 'Useful for tracking how much business comes from logged-in buyers.', 'ecommerce-product-catalog' ),
				'registered_customer_revenue'    => __( 'Useful for seeing revenue earned specifically from registered customers.', 'ecommerce-product-catalog' ),
				'registered_customer_aov'        => __( 'Useful for comparing registered-customer spend per order with guest spend.', 'ecommerce-product-catalog' ),
				'unique_registered_customers'    => __( 'Useful for counting distinct registered buyers and customer-base growth.', 'ecommerce-product-catalog' ),
				'returning_registered_customers' => __( 'Useful for measuring loyalty and how many registered customers buy again.', 'ecommerce-product-catalog' ),
				'guest_order_count'              => __( 'Useful for seeing how much business comes from guest checkouts.', 'ecommerce-product-catalog' ),
				'guest_order_revenue'            => __( 'Useful for tracking revenue from guest checkouts.', 'ecommerce-product-catalog' ),
				'guest_order_aov'                => __( 'Useful for comparing guest spend per order with registered-customer spend.', 'ecommerce-product-catalog' ),
				'successful_payments'            => __( 'Useful for confirming how many payments went through successfully.', 'ecommerce-product-catalog' ),
				'payment_failures'               => __( 'Useful for catching gateway or payment problems that cost sales.', 'ecommerce-product-catalog' ),
			);
			return $map[ $metric ] ?? __( 'Useful for following this result over time and spotting meaningful changes.', 'ecommerce-product-catalog' );
		}

		/** Render trend charts for metrics with at least two periods. */
		private function charts( $result, $metrics ) {
			$html = '';
			foreach ( $metrics as $metric ) {
				$points = array();
				foreach ( (array) ( $result['series'][ $metric ] ?? array() ) as $point ) {
					$points[] = array(
						'metric'     => $this->label( $metric ),
						'series'     => $this->label( $metric ),
						'series_key' => $metric,
						'period'     => (string) ( $point['period'] ?? '' ),
						'value'      => (float) ( $point['value'] ?? 0 ),
						'currency'   => (string) ( $point['currency'] ?? '' ),
					);
				}
				if ( count( $points ) >= 2 && function_exists( 'ic_stats_render_chart' ) ) {
					$html .= ic_stats_render_chart(
						array( 'points' => $points ),
						array(
							'title' => $this->label( $metric ),
							'id'    => 'ic-stats-chart-' . sanitize_html_class( $metric ),
						)
					);
				}
			}
			return $html ?: '<div class="ic-stats-empty"><p>' . esc_html__( 'A trend will appear after data exists in at least two periods.', 'ecommerce-product-catalog' ) . '</p></div>';
		}

		/** Render definition-driven dimension tables. */
		private function breakdowns( $result, $metrics ) {
			$html = '';
			foreach ( $metrics as $metric ) {
				foreach ( (array) ( $result['breakdowns'][ $metric ] ?? array() ) as $dimension => $breakdown ) {
					$rows = array_slice( (array) ( $breakdown['rows'] ?? array() ), 0, 20 );
					if ( ! $rows ) {
						continue;
					}
					$html .= '<section class="ic-stats-section"><h2>' . esc_html( sprintf( __( '%1$s by %2$s', 'ecommerce-product-catalog' ), $this->label( $metric ), ucwords( str_replace( '_', ' ', $dimension ) ) ) ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Value', 'ecommerce-product-catalog' ) . '</th><th>' . esc_html( $this->label( $metric ) ) . '</th><th>' . esc_html__( 'Records', 'ecommerce-product-catalog' ) . '</th></tr></thead><tbody>';
					foreach ( $rows as $row ) {
						$html .= '<tr><th scope="row">' . esc_html( (string) ( $row['label'] ?? $row['key'] ?? '' ) ) . '</th><td>' . esc_html( $this->format_value( $metric, $row ) ) . '</td><td>' . esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ) . '</td></tr>';
					}
					$html .= '</tbody></table></section>';
				}
			}
			return $html;
		}

		/** Render the complete selected base report. */
		public function screen() {
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- EPC registers this capability.
			if ( ! $this->available() || ! current_user_can( 'manage_product_settings' ) ) {
				wp_die( esc_html__( 'Access denied.', 'ecommerce-product-catalog' ) );
			}
			$pages              = $this->pages();
			$page               = $this->current_page();
			$metrics            = (array) $pages[ $page ]['metrics'];
			$args               = $this->report_args( $page );
			$filters            = $args;
			$args['metrics']    = $metrics;
			$args['breakdowns'] = array_fill_keys( $metrics, array( 'catalog', 'product_id', 'category_id', 'user_id', 'user_role', 'gateway', 'payment_status', 'order_value_type' ) );
			$args['per_page']   = 200;
			$result             = function_exists( 'ic_stats_query' ) ? ic_stats_query( 'ecommerce-product-catalog', $args ) : array();
			$html               = '<div class="wrap ic-epc-stats"><h1>' . esc_html__( 'Statistics', 'ecommerce-product-catalog' ) . '</h1><nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Statistics sections', 'ecommerce-product-catalog' ) . '">';
			foreach ( $pages as $slug => $registered_page ) {
				$html .= '<a class="nav-tab ' . ( $slug === $page ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $this->url( $slug, array_intersect_key( $args, $this->filter_fields( $slug ) ) ) ) . '">' . esc_html( $registered_page['label'] ) . '</a>';
			}
			$html .= '</nav>' . $this->filter_form( $page, $filters ) . $this->metrics_panel( $page, $metrics ) . '<div class="ic-stats-toolbar"><a class="button" href="' . esc_url( $this->export_url( $page, $filters ) ) . '">' . esc_html__( 'Export CSV', 'ecommerce-product-catalog' ) . '</a><span>' . esc_html( $filters['from'] . ' — ' . $filters['to'] ) . '</span></div><div class="ic-stats-kpis">';
			foreach ( $metrics as $metric ) {
				$summary      = (array) ( $result['summary'][ $metric ] ?? array(
					'display_value' => 0,
					'count'         => 0,
				) );
				$chart_target = count( (array) ( $result['series'][ $metric ] ?? array() ) ) >= 2 ? 'ic-stats-chart-' . sanitize_html_class( $metric ) : '';
				$html        .= ( $chart_target ? '<a class="ic-stats-kpi-link" href="#' . esc_attr( $chart_target ) . '">' : '' ) . '<article><h2>' . esc_html( $this->label( $metric ) ) . '</h2><strong>' . esc_html( $this->format_value( $metric, $summary ) ) . '</strong><span>' . esc_html( sprintf( _n( '%d record', '%d records', (int) ( $summary['count'] ?? 0 ), 'ecommerce-product-catalog' ), (int) ( $summary['count'] ?? 0 ) ) ) . '</span></article>' . ( $chart_target ? '</a>' : '' );
			}
			$html .= '</div>' . $this->charts( $result, $metrics ) . $this->breakdowns( $result, $metrics ) . '<section class="ic-stats-section"><h2>' . esc_html__( 'Summary table', 'ecommerce-product-catalog' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Metric', 'ecommerce-product-catalog' ) . '</th><th>' . esc_html__( 'Value', 'ecommerce-product-catalog' ) . '</th><th>' . esc_html__( 'Records', 'ecommerce-product-catalog' ) . '</th></tr></thead><tbody>';
			foreach ( $metrics as $metric ) {
				$summary = (array) ( $result['summary'][ $metric ] ?? array() );
				$html   .= '<tr><th scope="row">' . esc_html( $this->label( $metric ) ) . '</th><td>' . esc_html( $this->format_value( $metric, $summary ) ) . '</td><td>' . esc_html( number_format_i18n( (int) ( $summary['count'] ?? 0 ) ) ) . '</td></tr>';
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic report values are escaped at their final context.
			echo $html . '</tbody></table></section></div>';
		}

		/** Export the selected report with CSV formula-injection protection. */
		public function export() {
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- EPC registers this capability.
			if ( ! $this->available() || ! current_user_can( 'manage_product_settings' ) || ! check_admin_referer( 'ic_epc_stats_export' ) ) {
				wp_die( esc_html__( 'Access denied.', 'ecommerce-product-catalog' ) );
			}
			$page            = $this->current_page();
			$pages           = $this->pages();
			$args            = $this->report_args( $page );
			$args['metrics'] = (array) $pages[ $page ]['metrics'];
			$result          = ic_stats_query( 'ecommerce-product-catalog', $args );
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="epc-stats-' . sanitize_file_name( $page ) . '.csv"' );
			echo "metric,value,count\n";
			foreach ( $args['metrics'] as $metric ) {
				$summary = (array) ( $result['summary'][ $metric ] ?? array() );
				$row     = array( $this->label( $metric ), $this->format_value( $metric, $summary ), (int) ( $summary['count'] ?? 0 ) );
				$row     = array_map(
					static function ( $value ) {
						$value = (string) $value;
						$value = preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
						return '"' . str_replace( '"', '""', $value ) . '"';
					},
					$row
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dedicated CSV serializer above.
				echo implode( ',', $row ) . "\n";
			}
			exit;
		}
	}
	$GLOBALS['ic_epc_stats_base_admin'] = new IC_EPC_Stats_Admin();
}
