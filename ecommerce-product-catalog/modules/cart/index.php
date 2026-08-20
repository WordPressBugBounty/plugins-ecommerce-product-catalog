<?php
/**
 * EPC cart shell bootstrap.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'IC_USE_COOKIES' ) ) {
	define( 'IC_USE_COOKIES', true );
}
if ( ! defined( 'AL_SC_BASE_URL' ) ) {
	define( 'AL_SC_BASE_URL', AL_PLUGIN_BASE_PATH . 'modules/cart/' );
}
if ( ! defined( 'AL_SC_BASE_PATH' ) ) {
	define( 'AL_SC_BASE_PATH', AL_BASE_PATH . '/modules/cart' );
}

if ( ! function_exists( 'ic_epc_orders_labels' ) ) {
	/**
	 * Provides EPC-specific order labels.
	 *
	 * @return array
	 */
	function ic_epc_orders_labels() {
		return array(
			'name'               => __( 'Orders', 'ecommerce-product-catalog' ),
			'singular_name'      => __( 'Order', 'ecommerce-product-catalog' ),
			'add_new'            => __( 'New Order', 'ecommerce-product-catalog' ),
			'add_new_item'       => __( 'Add New Order', 'ecommerce-product-catalog' ),
			'edit_item'          => __( 'Edit Order', 'ecommerce-product-catalog' ),
			'new_item'           => __( 'Add Order', 'ecommerce-product-catalog' ),
			'view_item'          => __( 'View Order', 'ecommerce-product-catalog' ),
			'search_items'       => __( 'Search Orders', 'ecommerce-product-catalog' ),
			'not_found'          => __( 'No orders found', 'ecommerce-product-catalog' ),
			'not_found_in_trash' => __( 'No orders found in the trash', 'ecommerce-product-catalog' ),
		);
	}
}

if ( ! function_exists( 'ic_epc_customer_orders_table_heads' ) ) {
	/**
	 * Restores the legacy EPC customer orders headings.
	 *
	 * @return array
	 */
	function ic_epc_customer_orders_table_heads() {
		return array(
			__( 'Your Orders', 'ecommerce-product-catalog' ),
			'',
		);
	}
}

if ( ! function_exists( 'ic_epc_customer_order_name' ) ) {
	/**
	 * Returns the legacy EPC customer order name.
	 *
	 * @param int $order_id Order identifier.
	 *
	 * @return string
	 */
	function ic_epc_customer_order_name( $order_id ) {
		$payment_details = ic_get_order_payment_details( $order_id );

		if ( ! empty( $payment_details['order_name'] ) ) {
			return sanitize_text_field( $payment_details['order_name'] );
		}
		if ( ! empty( $payment_details['date'] ) && is_numeric( $payment_details['date'] ) ) {
			return date_i18n( get_option( 'date_format' ), $payment_details['date'] );
		}
		if ( ! empty( $payment_details['date'] ) ) {
			return sanitize_text_field( $payment_details['date'] );
		}

		return '';
	}
}

if ( ! function_exists( 'ic_epc_customer_orders_table_cells' ) ) {
	/**
	 * Restores the legacy EPC customer orders cells layout.
	 *
	 * @param array $cells    Existing shared-module cells.
	 * @param int   $order_id Order identifier.
	 *
	 * @return array
	 */
	function ic_epc_customer_orders_table_cells( $cells, $order_id ) {
		$legacy_cells = array();
		$order_name   = ic_epc_customer_order_name( $order_id );

		if ( '' !== $order_name ) {
			$legacy_cells[] = '<span class="dashicons dashicons-edit"></span><span class="order-name">' . esc_html( $order_name ) . '</span>';
		}

		if ( ! empty( $cells ) ) {
			$last_cell = end( $cells );
			if ( is_string( $last_cell ) && false !== strpos( $last_cell, '<form ' ) ) {
				$legacy_cells[] = $last_cell;
			}
		}

		return $legacy_cells;
	}
}

if ( ! function_exists( 'ic_epc_customer_order_repeat_button_label' ) ) {
	/**
	 * Restores the legacy EPC repeat-order button label.
	 *
	 * @return string
	 */
	function ic_epc_customer_order_repeat_button_label() {
		return __( 'Add to Cart', 'ecommerce-product-catalog' );
	}
}

if ( ! function_exists( 'ic_epc_digital_customers_localize' ) ) {
	/**
	 * Preserves the EPC login placeholder localization for the shared customers script.
	 *
	 * @param array $localization Existing shared localization.
	 *
	 * @return array
	 */
	function ic_epc_digital_customers_localize( $localization ) {
		return wp_parse_args(
			$localization,
			array(
				'username_placeholder' => __( 'Username or Email', 'ecommerce-product-catalog' ),
				'pass_placeholder'     => __( 'Password', 'ecommerce-product-catalog' ),
			)
		);
	}
}

if ( ! class_exists( 'ic_shopping_ecommerce_cart' ) ) {
	/**
	 * EPC cart shell module.
	 */
	class ic_shopping_ecommerce_cart {

		/**
		 * Initializes the cart shell hooks.
		 */
		public function __construct() {
			$this->activation();
			add_action( 'ecommerce-prodct-catalog-addons', array( $this, 'include_files' ) );
			// Premium formbuilder registers its frontend dependency at priority 9.
			add_action( 'register_catalog_styles', array( $this, 'register_styles' ), 1 );
			add_action( 'enqueue_catalog_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'register_catalog_admin_styles', array( $this, 'register_admin_styles' ) );
			add_action( 'enqueue_catalog_admin_scripts', array( $this, 'enqueue_admin_styles' ) );
			add_filter( 'ic_maybe_engueue_all', array( $this, 'enqueue_condition' ) );
		}

		/**
		 * Registers activation tasks for the cart shell.
		 *
		 * @return void
		 */
		public function activation() {
			require_once AL_SC_BASE_PATH . '/functions/activation.php';
			register_activation_hook( AL_PLUGIN_MAIN_FILE, 'ic_cart_add_customer_role' );
		}

		/**
		 * Loads EPC cart runtime files.
		 *
		 * @return void
		 */
		public function include_files() {
			if ( ! function_exists( 'formbuilder_form' ) && function_exists( 'ic_framework_require_once' ) ) {
				ic_framework_require_once( AL_BASE_PATH . '/ic/simple-formbuilder/index.php' );
			}

			require_once AL_SC_BASE_PATH . '/includes/product-status.php';
			require_once AL_SC_BASE_PATH . '/includes/cart-widget.php';
			require_once AL_SC_BASE_PATH . '/includes/shopping-cart.php';
			require_once AL_SC_BASE_PATH . '/includes/shopping-settings.php';
			require_once AL_SC_BASE_PATH . '/includes/cart-customer.php';
			require_once AL_SC_BASE_PATH . '/includes/payment.php';

			if ( ! function_exists( 'modify_tax_cart_rate' ) ) {
				require_once AL_SC_BASE_PATH . '/includes/tax.php';
			}
			if ( function_exists( 'ic_framework_require_once' ) ) {
				ic_framework_require_once( AL_BASE_PATH . '/ic/cart/index.php' );
				ic_framework_require_once( AL_BASE_PATH . '/ic/cart/functions.php' );
				ic_framework_require_once( AL_BASE_PATH . '/ic/cart/cart-conditionals.php' );
				ic_framework_require_once( AL_BASE_PATH . '/ic/cart/pluggable.php' );
				ic_framework_require_once( AL_BASE_PATH . '/ic/cart/buttons-fields.php' );
				if ( function_exists( 'ic_register_cart_add_button_hooks' ) ) {
					ic_register_cart_add_button_hooks();
				}
				ic_framework_require_once( AL_BASE_PATH . '/ic/variations/index.php' );
			}

			// Must load AFTER ic/cart/index.php so the class_exists() guard there does not
			// suppress ic/cart/notifications.php - ic/ owns ic_cart_checkout_form_email now.
			require_once AL_SC_BASE_PATH . '/includes/cart-form.php';

			$settings = get_shopping_cart_settings();

			if ( empty( $settings['order_registration_disable'] ) ) {
				add_filter( 'ic_orders_labels', 'ic_epc_orders_labels' );
				add_filter( 'customer_orders_table_heads', 'ic_epc_customer_orders_table_heads' );
				add_filter( 'customer_orders_table_cells', 'ic_epc_customer_orders_table_cells', 10, 2 );
				add_filter( 'ic_customer_order_repeat_button_label', 'ic_epc_customer_order_repeat_button_label' );
				add_filter( 'ic_orders_enable_completion_message', '__return_true' );
				if ( function_exists( 'ic_framework_require_once' ) ) {
					ic_framework_require_once( AL_BASE_PATH . '/ic/orders/index.php' );
				}
			}

			if ( isset( $settings['form_registration'] ) && 'user' === $settings['form_registration'] ) {
				add_filter( 'ic_customers_front_script_localize', 'ic_epc_digital_customers_localize' );
				if ( function_exists( 'ic_framework_require_once' ) ) {
					ic_framework_require_once( AL_BASE_PATH . '/ic/customers/index.php' );
				}
			}

			require_once AL_SC_BASE_PATH . '/functions/activation.php';
			require_once AL_SC_BASE_PATH . '/functions/compatibility.php';
			require_once AL_SC_BASE_PATH . '/functions/cart-functions.php';
			require_once AL_SC_BASE_PATH . '/shortcodes.php';
		}

		/**
		 * Registers shopping cart assets.
		 *
		 * @return void
		 */
		public function register_styles() {
			// Premium formbuilder registers its frontend bundle at priority 9 and
			// declares ic_chosen as a dependency. Register the shared handle here
			// at priority 1 so clean frontend requests never enqueue an unknown dep.
			if ( ! wp_script_is( 'ic_chosen', 'registered' ) ) {
				wp_register_script( 'ic_chosen', AL_SC_BASE_URL . 'ext/chosen/chosen.jquery.min.js', array( 'jquery' ), IC_EPC_VERSION, true );
			}
			if ( ! wp_style_is( 'ic_chosen', 'registered' ) ) {
				wp_register_style( 'ic_chosen', AL_SC_BASE_URL . 'ext/chosen/chosen.min.css', array(), IC_EPC_VERSION );
			}
			wp_register_style(
				'al_shopping_cart_styles',
				AL_SC_BASE_URL . 'css/shopping-cart.min.css' . ic_filemtime( AL_SC_BASE_PATH . '/css/shopping-cart.min.css' ),
				array( 'dashicons' ),
				IC_EPC_VERSION
			);
			wp_register_script(
				'al_shopping_cart_scripts',
				AL_PLUGIN_BASE_PATH . 'ic/cart/js/cart-page.min.js' . ic_filemtime( AL_BASE_PATH . '/ic/cart/js/cart-page.min.js' ),
				array( 'al_product_scripts', 'ic_ajax_cart', 'jquery-effects-slide' ),
				IC_EPC_VERSION,
				true
			);
		}

		/**
		 * Enqueues shopping cart assets.
		 *
		 * @return void
		 */
		public function enqueue_styles() {
			if ( ! function_exists( 'get_currency_settings' ) ) {
				return;
			}

			wp_enqueue_style( 'al_shopping_cart_styles' );
			wp_enqueue_script( 'al_shopping_cart_scripts' );
			$currency = get_currency_settings();
			wp_localize_script(
				'al_shopping_cart_scripts',
				'ic_cart_ajax_object',
				array(
					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'dec_sep'     => $currency['dec_sep'],
					'th_sep'      => $currency['th_sep'],
					'total_label' => __( 'Purchase Total', 'ecommerce-product-catalog' ),
				)
			);
		}

		/**
		 * Registers shopping cart admin assets.
		 *
		 * @return void
		 */
		public function register_admin_styles() {
			wp_register_script( 'ic_chosen', AL_SC_BASE_URL . 'ext/chosen/chosen.jquery.min.js', array( 'jquery' ), IC_EPC_VERSION, true );
			wp_register_style( 'ic_chosen', AL_SC_BASE_URL . 'ext/chosen/chosen.min.css', array(), IC_EPC_VERSION );
		}

		/**
		 * Enqueues shopping cart admin assets.
		 *
		 * @return void
		 */
		public function enqueue_admin_styles() {
			wp_enqueue_style( 'ic_chosen' );
		}

		/**
		 * Forces asset loading on cart/customer pages.
		 *
		 * @param bool $enqueue_all Current enqueue decision.
		 *
		 * @return bool
		 */
		public function enqueue_condition( $enqueue_all ) {
			if ( is_ic_shopping_page() || ( function_exists( 'is_ic_customer_panel' ) && is_ic_customer_panel() ) ) {
				return true;
			}

			return $enqueue_all;
		}
	}
}

if ( ! function_exists( 'start_shopping_cart' ) ) {
	/**
	 * Boots the EPC cart shell.
	 *
	 * @return void
	 */
	function start_shopping_cart() {
		global $ic_shopping_ecommerce_cart;

		if ( ! isset( $ic_shopping_ecommerce_cart ) || ! $ic_shopping_ecommerce_cart instanceof ic_shopping_ecommerce_cart ) {
			$ic_shopping_ecommerce_cart = new ic_shopping_ecommerce_cart();
		}
	}

	start_shopping_cart();
}
