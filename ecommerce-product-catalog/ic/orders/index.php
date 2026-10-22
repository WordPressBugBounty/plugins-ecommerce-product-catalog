<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'AL_PO_BASE_PATH' ) ) {
	define( 'AL_PO_BASE_PATH', __DIR__ );
}
if ( ! defined( 'AL_PO_PLUGIN_BASE_URL' ) ) {
	define( 'AL_PO_PLUGIN_BASE_URL', plugins_url( '/', __FILE__ ) );
}

if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( AL_PO_BASE_PATH . '/functions/activation.php' );
}

add_action( 'ecommerce-prodct-catalog-addons', 'start_digital_products_orders', 20 );

function start_digital_products_orders() {
	if ( function_exists( 'ic_framework_require_once' ) ) {
		ic_framework_require_once( AL_PO_BASE_PATH . '/includes/index.php' );
		ic_framework_require_once( AL_PO_BASE_PATH . '/functions/index.php' );
		ic_framework_require_once( AL_PO_BASE_PATH . '/ext/index.php' );
	}
	do_action( 'digital_product_orders_addons' );
}

add_action( 'register_catalog_admin_styles', 'digital_orders_admin_register_styles' );

function digital_orders_admin_register_styles() {
	wp_register_style( 'al_digital_products_admin_styles', AL_PO_PLUGIN_BASE_URL . 'css/admin-style.min.css' . ic_filemtime( AL_PO_BASE_PATH . '/css/admin-style.min.css' ), array(), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_PO_BASE_PATH . '/css/admin-style.min.css' ) );
}

add_action( 'enqueue_catalog_admin_styles', 'digital_orders_admin_enqueue_styles' );

function digital_orders_admin_enqueue_styles() {
	wp_enqueue_style( 'al_digital_products_admin_styles' );
}

add_action( 'catalog_register_scripts', 'digital_orders_register_styles' );

function digital_orders_register_styles() {
	if ( file_exists( AL_PO_BASE_PATH . '/css/style.css' ) ) {
		wp_register_style( 'al_digital_products_styles', AL_PO_PLUGIN_BASE_URL . 'css/style.css' . ic_filemtime( AL_PO_BASE_PATH . '/css/style.css' ), array(), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_PO_BASE_PATH . '/css/style.css' ) );
	}
}

add_action( 'catalog_enqueue_scripts', 'digital_orders_enqeueue_styles' );

function digital_orders_enqeueue_styles() {
	if ( wp_style_is( 'al_digital_products_styles', 'registered' ) ) {
		wp_enqueue_style( 'al_digital_products_styles' );
	}
}

if ( ! function_exists( 'ic_orders_register_identity_source' ) ) {
	/**
	 * Registers statutory order records for two-phase identity erasure.
	 */
	function ic_orders_register_identity_source() {
		if ( ! function_exists( 'ic_register_erasable_identity_source' ) ) {
			return;
		}
		ic_register_erasable_identity_source(
			'ic_orders',
			array(
				'post_type'       => 'al_digital_orders',
				'customer_meta'   => '_customer_id',
				'identity_meta'   => '_payment_details',
				'identity_fields' => array( 'customer_name', 'customer_email', 'shipping_email', 'email', 'name', 'billing_name', 'street', 'postcode', 'city', 'vatid', 'vat_name', 'vat_address', 'vat_ver_id', 'address', 'tax_code' ),
				'summary_meta'    => '_order_summary',
				'summary_fields'  => array( 'email' ),
				'statutory'       => true,
			)
		);
	}
}
add_action( 'ic_register_erasable_identity_sources', 'ic_orders_register_identity_source' );

add_action( 'enqueue_catalog_admin_styles', 'country_chosen_admin_select_script' );

add_action( 'wp_ajax_ic_order_product_price', 'ic_ajax_order_product_price' );

/**
 * Returns the current filtered catalog price for one editable order row.
 *
 * @return void
 */
function ic_ajax_order_product_price() {
	if ( ! check_ajax_referer( 'ic_order_product_price', 'nonce', false ) ) {
		wp_send_json_error( array( 'code' => 'invalid_nonce' ), 403 );
	}

	if ( ! current_user_can( 'edit_digital_orders' ) ) {
		wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The action-specific nonce is verified above before request data is read.
	$order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	$order    = get_post( $order_id );
	if ( ! $order instanceof WP_Post || 'al_digital_orders' !== $order->post_type ) {
		wp_send_json_error( array( 'code' => 'invalid_order' ), 404 );
	}

	if ( ! current_user_can( 'edit_digital_order', $order_id ) ) {
		wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The action-specific nonce is verified above before request data is read.
	$product_id       = isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$product          = get_post( $product_id );
	$product_types    = function_exists( 'product_post_type_array' ) ? (array) product_post_type_array() : array();
	$visible_statuses = function_exists( 'ic_visible_product_status' ) ? (array) ic_visible_product_status() : array();
	if ( ! $product instanceof WP_Post || ! in_array( $product->post_type, $product_types, true ) || ! in_array( $product->post_status, $visible_statuses, true ) || ! current_user_can( 'read_product', $product_id ) ) {
		wp_send_json_error( array( 'code' => 'invalid_product' ), 404 );
	}

	$price = apply_filters( 'digital_product_prices_table_price', get_post_meta( $product_id, '_price', true ), $product_id );
	$price = is_scalar( $price ) ? (string) $price : '';
	wp_send_json_success(
		array(
			'product_id' => $product_id,
			'price'      => $price,
		)
	);
}

function country_chosen_admin_select_script() {
	if ( is_ic_order_edit_screen() ) {
		global $post;
		$order_id = $post instanceof WP_Post && 'al_digital_orders' === $post->post_type ? (int) $post->ID : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( ! empty( $_GET['payment_details'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			$payment_details = ic_sanitize_decoded_payload( $_GET['payment_details'] );
			if ( is_array( $payment_details ) ) {
				$_POST = $payment_details;
			}
		}
		$edit_order_deps = array( 'ic_chosen' );
		if ( wp_script_is( 'ic-ajax-selector', 'registered' ) ) {
			// The product field becomes the shared AJAX selector on large catalogs, and this script re-initialises injected rows.
			$edit_order_deps[] = 'ic-ajax-selector';
			// A script dependency does not pull in the stylesheet, and the field
			// renderer only reaches its own enqueue once the metabox is printing,
			// which defers the CSS to the late-styles pass and flashes an
			// unstyled control first.
			wp_enqueue_style( 'ic-ajax-selector' );
		}
		wp_enqueue_script( 'digital_order_edit_script', AL_PO_PLUGIN_BASE_URL . 'js/edit-order.min.js' . ic_filemtime( AL_PO_BASE_PATH . '/js/edit-order.min.js' ), $edit_order_deps, defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_PO_BASE_PATH . '/js/edit-order.min.js' ), true );
		// if ( ic_ic_order_manual() ) {
		$translation_array                  = array(
			'digital_products_dropdown_action' => ic_select_product( __( 'Choose product from catalog', 'ecommerce-product-catalog' ), '', 'new_manual_order_product_id', 'digital_products_dropdown', 0 ),
		);
		$translation_array                  = apply_filters( 'digital_order_edit_sctipt_translations', $translation_array );
		$translation_array['product_price'] = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'action'   => 'ic_order_product_price',
			'nonce'    => wp_create_nonce( 'ic_order_product_price' ),
			'order_id' => $order_id,
			'loading'  => __( 'Loading price&hellip;', 'ecommerce-product-catalog' ),
			'error'    => __( 'Could not load the price. Enter it manually.', 'ecommerce-product-catalog' ),
		);
		wp_localize_script( 'digital_order_edit_script', 'digital_order_edit_script_trans', $translation_array );
		// }
	}
}

if ( ! function_exists( 'ic_orders_register_selector_sources' ) ) {

	/**
	 * Registers the order screen's own product selector source.
	 *
	 * The framework already ships `ic_products`, but that source is gated on
	 * `manage_product_settings` because it serves the catalog settings and the
	 * statistics screens. The order edit screen is gated on
	 * `edit_digital_orders` instead, so a role that may edit orders without
	 * managing product settings would fall back to the plain capped `<select>`
	 * and lose search on a large catalog. Widening `ic_products` would grant
	 * catalog-wide search to that role everywhere else too, so the order screen
	 * gets its own source with its own capability -- which is the whole reason
	 * the registry keys a capability per source.
	 *
	 * Everything else matches `ic_products`: the same post types, the same
	 * `ic_visible_product_status()` callable resolved per request, and the same
	 * search fields.
	 *
	 * @return void
	 */
	function ic_orders_register_selector_sources() {
		if ( ! function_exists( 'ic_register_selector_source' ) || ! function_exists( 'product_post_type_array' ) ) {
			return;
		}

		ic_register_selector_source(
			'ic_order_products',
			array(
				'type'           => 'post',
				'subtypes'       => 'product_post_type_array',
				'post_status'    => function_exists( 'ic_visible_product_status' ) ? 'ic_visible_product_status' : array( 'publish' ),
				'capability'     => 'edit_digital_orders',
				'search'         => array(
					'title',
					'id',
					'meta' => array( '_sku' ),
				),
				'per_page'       => 20,
				'label'          => __( 'products', 'ecommerce-product-catalog' ),
				'label_callback' => function_exists( 'get_product_name' ) ? 'get_product_name' : '',
			)
		);
	}

	add_action( 'ic_register_selector_sources', 'ic_orders_register_selector_sources' );
}
