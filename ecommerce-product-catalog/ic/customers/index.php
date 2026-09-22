<?php
/**
 * Initialize digital customer functionality.
 *
 * @package impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'AL_CUSTOMERS_BASE_FILE' ) ) {
	define( 'AL_CUSTOMERS_BASE_FILE', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'AL_CUSTOMERS_BASE_URL' ) ) {
	define( 'AL_CUSTOMERS_BASE_URL', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'AL_CUSTOMERS_BASE_PATH' ) ) {
	define( 'AL_CUSTOMERS_BASE_PATH', __DIR__ );
}
if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/admin-access.php' );
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/account-info.php' );
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/account-creation.php' );
}

$settings = get_shopping_cart_settings();
if ( ! empty( $settings['form_registration'] ) && 'user' === $settings['form_registration'] ) {

	add_action( 'digital_product_orders_addons', 'start_transaction_digital_customers', 20 );

	/** Initialize transaction related files. */
	if ( ! function_exists( 'start_transaction_digital_customers' ) ) {
		/** Initialize transaction-related files. */
		function start_transaction_digital_customers() {
			if ( function_exists( 'ic_framework_require_once' ) ) {
				ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/includes/transactions/index.php' );
				ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/functions/transactions/index.php' );
			}
		}
	}

	add_action( 'ecommerce-prodct-catalog-addons', 'start_digital_customers', 30 );

	/** Initialize core extension files. */
	if ( ! function_exists( 'start_digital_customers' ) ) {
		/** Initialize core extension files. */
		function start_digital_customers() {
			if ( function_exists( 'ic_framework_require_once' ) ) {
				ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/functions/index.php' );
				ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/includes/index.php' );
				ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/ext/index.php' );
			}
			do_action( 'digital_customers_addons' );
		}
	}

	add_action( 'register_catalog_styles', 'register_digital_customers_styles' );

	if ( ! function_exists( 'register_digital_customers_styles' ) ) {
		/** Register digital customer assets. */
		function register_digital_customers_styles() {
			wp_register_style( 'jquery-ui-css', AL_CUSTOMERS_BASE_URL . 'css/jquery-ui.css', array(), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : ic_filemtime( AL_CUSTOMERS_BASE_PATH . '/css/jquery-ui.css', true ) );
			wp_register_style(
				'al_digital_customers_styles',
				AL_CUSTOMERS_BASE_URL . 'css/digital-customers.min.css' . ic_filemtime( AL_CUSTOMERS_BASE_PATH . '/css/digital-customers.min.css' ),
				array(
					'dashicons',
					'jquery-ui-css',
				),
				defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : ic_filemtime( AL_CUSTOMERS_BASE_PATH . '/css/digital-customers.min.css', true )
			);
			wp_register_script(
				'front_edit_customer_panel',
				AL_CUSTOMERS_BASE_URL . 'js/customer-panel.min.js' . ic_filemtime( AL_CUSTOMERS_BASE_PATH . '/js/customer-panel.min.js' ),
				array(
					'jquery-ui-tabs',
					'wp-api-fetch',
				),
				defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : ic_filemtime( AL_CUSTOMERS_BASE_PATH . '/js/customer-panel.min.js', true ),
				true
			);
			wp_register_script(
				'front_digital_customers',
				AL_CUSTOMERS_BASE_URL . 'js/digital-customers.min.js' . ic_filemtime( AL_CUSTOMERS_BASE_PATH . '/js/digital-customers.min.js' ),
				array(
					'jquery-effects-slide',
					'jquery-ui-tabs',
				),
				defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : ic_filemtime( AL_CUSTOMERS_BASE_PATH . '/js/digital-customers.min.js', true ),
				true
			);
		}
	}

	if ( ! function_exists( 'digital_customers_styles' ) ) {
		/** Enqueue digital customer assets. */
		function digital_customers_styles() {
			wp_enqueue_style( 'al_digital_customers_styles' );
			wp_enqueue_script( 'front_digital_customers' );
			$front_script_localize = apply_filters( 'ic_customers_front_script_localize', array() );
			if ( ! empty( $front_script_localize ) ) {
				wp_localize_script( 'front_digital_customers', 'ic_digital_customers', $front_script_localize );
			}
			if ( is_ic_digital_customer() ) {
				wp_enqueue_script( 'front_edit_customer_panel' );
				wp_localize_script(
					'front_edit_customer_panel',
					'ic_customer_panel',
					array(
						'customer_id' => ic_get_logged_customer_id(),
						'rest_nonce'  => wp_create_nonce( 'wp_rest' ),
						'ajax_nonce'  => wp_create_nonce( 'ic_ajax' ),
					)
				);
			}
		}
	}
}
