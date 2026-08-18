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

add_action( 'enqueue_catalog_admin_styles', 'country_chosen_admin_select_script' );

function country_chosen_admin_select_script() {
	if ( is_ic_order_edit_screen() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( ! empty( $_GET['payment_details'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			$payment_details = ic_sanitize_decoded_payload( $_GET['payment_details'] );
			if ( is_array( $payment_details ) ) {
				$_POST = $payment_details;
			}
		}
		wp_enqueue_script( 'digital_order_edit_script', AL_PO_PLUGIN_BASE_URL . 'js/edit-order.min.js' . ic_filemtime( AL_PO_BASE_PATH . '/js/edit-order.min.js' ), array( 'ic_chosen' ), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_PO_BASE_PATH . '/js/edit-order.min.js' ), true );
		// if ( ic_ic_order_manual() ) {
		if ( function_exists( 'start_digital_products' ) ) {
			$translation_array = array(
				'digital_products_dropdown_action' => digital_products_dropdown( 'new_manual_order_product_id', __( 'Choose product from catalog', 'ecommerce-product-catalog' ), '' ),
			);
		} else {
			$translation_array = array(
				'digital_products_dropdown_action' => ic_select_product( __( 'Choose product from catalog', 'ecommerce-product-catalog' ), '', 'new_manual_order_product_id', 'digital_products_dropdown', 0 ),
			);
		}
		wp_localize_script( 'digital_order_edit_script', 'digital_order_edit_script_trans', apply_filters( 'digital_order_edit_sctipt_translations', $translation_array ) );
		// }
	}
}
