<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'AL_VARIATIONS_BASE_FILE' ) ) {
	define( 'AL_VARIATIONS_BASE_FILE', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'AL_VARIATIONS_BASE_URL' ) ) {
	define( 'AL_VARIATIONS_BASE_URL', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'AL_VARIATIONS_BASE_PATH' ) ) {
	define( 'AL_VARIATIONS_BASE_PATH', __DIR__ );
}

if ( function_exists( 'ic_framework_require_once' ) ) {
	if ( ! function_exists( 'ic_is_variations_price_effect_active' ) ) {
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/variations-conditionals.php' );
	}
	if ( ! function_exists( 'product_variations_menu' ) ) {
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/product-variations-settings.php' );
	}
	if ( ! function_exists( 'add_variation_metaboxes' ) && ! class_exists( 'ic_cart_variations_meta', false ) ) {
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/functions.php' );
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/variations-meta.php' );
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/variations-selectors.php' );
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/variations-front.php' );
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/variations-ajax.php' );
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/product-variations.php' );
		ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/variations-details.php' );
	}
	ic_framework_require_once( AL_VARIATIONS_BASE_PATH . '/ext/index.php' );
}

if ( ! function_exists( 'ic_variations_ai_group_fields' ) ) {
	/**
	 * Returns the variation label/value groups exposed as AI context.
	 *
	 * @param int $count Number of configured variation rows.
	 *
	 * @return array
	 */
	function ic_variations_ai_group_fields( $count ) {
		$count  = absint( $count );
		$fields = array();
		for ( $index = 1; $index <= $count; $index++ ) {
			$label_key                       = $index . '_variation_label';
			$values_key                      = $index . '_variation_values';
			$fields[ 'variation_' . $index ] = array(
				'type'             => 'group',
				/* translators: %d: variation row number. */
				'label'            => sprintf( __( 'Variation %d', 'ecommerce-product-catalog' ), $index ),
				'enhancement_mode' => 'preserve',
				'group_fields'     => array(
					'label'  => array(
						'type'             => 'meta',
						'label'            => __( 'Name', 'ecommerce-product-catalog' ),
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Field-map metadata, not a query.
						'meta_key'         => $label_key,
						'form_field'       => $label_key,
						'selector'         => '[name="' . $label_key . '"]',
						'enhancement_mode' => 'preserve',
					),
					'values' => array(
						'type'             => 'meta',
						'label'            => __( 'Values', 'ecommerce-product-catalog' ),
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Field-map metadata, not a query.
						'meta_key'         => $values_key,
						'form_field'       => $values_key,
						'selector'         => '[name="' . $values_key . '[]"]',
						'enhancement_mode' => 'preserve',
					),
				),
			);
		}

		return $fields;
	}
}

if ( ! function_exists( 'ic_variations_ai_product_field' ) ) {
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Three-argument public filter contract.
	/**
	 * Adds configured product variations as locked AI context.
	 *
	 * @param array  $fields    Product AI field map.
	 * @param string $post_type Product post type.
	 * @param string $taxonomy  Product taxonomy.
	 *
	 * @return array
	 */
	function ic_variations_ai_product_field( $fields, $post_type, $taxonomy ) {
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! class_exists( 'IC_Product_Details' ) || ! IC_Product_Details::is_ai_product_post_type( $post_type ) || ! function_exists( 'get_product_variations_settings' ) ) {
			return $fields;
		}

		$settings = get_product_variations_settings();
		$count    = isset( $settings['count'] ) ? absint( $settings['count'] ) : 0;
		if ( 0 === $count ) {
			return $fields;
		}

		$fields['variations'] = array(
			'type'                  => 'group',
			'label'                 => __( 'Variations', 'ecommerce-product-catalog' ),
			'enhancement_mode'      => 'preserve',
			'lock_enhancement_mode' => true,
			'default_enabled'       => true,
			'group_fields'          => ic_variations_ai_group_fields( $count ),
		);

		return $fields;
	}
}

add_filter( 'ic_epc_ai_product_fields', 'ic_variations_ai_product_field', 10, 3 );

/**
 * Adds variation AJAX nonces to the catalog product object.
 *
 * @param array $nonces Existing nonce map.
 * @return array
 */
function ic_variations_product_object_nonces( $nonces ) {
	$nonces['variation_nonce']           = wp_create_nonce( 'ic_variation_lookup' );
	$nonces['variation_selectors_nonce'] = wp_create_nonce( 'ic_variation_selectors' );

	return $nonces;
}
add_filter( 'ic_catalog_product_object_nonces', 'ic_variations_product_object_nonces' );

if ( ! function_exists( 'ic_variations_register_admin_styles' ) ) {
	add_action( 'register_catalog_admin_styles', 'ic_variations_register_admin_styles' );

	function ic_variations_register_admin_styles() {
		wp_register_style( 'ic_variations_admin', AL_VARIATIONS_BASE_URL . 'css/variations-admin.css' . ic_filemtime( AL_VARIATIONS_BASE_PATH . '/css/variations-admin.css' ), array( 'al_product_admin_styles' ), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_VARIATIONS_BASE_PATH . '/css/variations-admin.css' ) );
		wp_register_script( 'ic_variations_admin', AL_VARIATIONS_BASE_URL . 'js/variations-admin.js' . ic_filemtime( AL_VARIATIONS_BASE_PATH . '/js/variations-admin.js' ), array( 'al_product_admin-scripts', 'ic_chosen' ), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_VARIATIONS_BASE_PATH . '/js/variations-admin.js' ), true );
	}

	add_action( 'enqueue_catalog_admin_scripts', 'ic_variations_enqueue_admin_styles' );

	function ic_variations_enqueue_admin_styles() {
		wp_enqueue_style( 'ic_variations_admin' );
		wp_enqueue_script( 'ic_variations_admin' );
	}

	add_action( 'register_catalog_styles', 'ic_variations_register_styles' );

	function ic_variations_register_styles() {
		wp_register_style( 'ic_variations', AL_VARIATIONS_BASE_URL . 'css/variations-front.css' . ic_filemtime( AL_VARIATIONS_BASE_PATH . '/css/variations-front.css' ), array( 'al_product_styles' ), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_VARIATIONS_BASE_PATH . '/css/variations-front.css' ) );
		wp_register_script( 'ic_variations', AL_VARIATIONS_BASE_URL . 'js/variations.js' . ic_filemtime( AL_VARIATIONS_BASE_PATH . '/js/variations.js' ), array(), defined( 'IC_EPC_VERSION' ) ? IC_EPC_VERSION : filemtime( AL_VARIATIONS_BASE_PATH . '/js/variations.js' ), true );
		wp_localize_script(
			'ic_variations',
			'ic_variations',
			wp_parse_args(
				apply_filters( 'ic_variations_script_localize', array() ),
				array(
					'checkbox_separator' => '>>>',
					'disable_hidden'     => '0',
				)
			)
		);
	}

	add_action( 'enqueue_catalog_scripts', 'ic_variations_enqueue_styles' );

	function ic_variations_enqueue_styles() {
		if ( is_ic_variations_enabled() ) {
			wp_enqueue_style( 'ic_variations' );
			wp_enqueue_script( 'ic_variations' );
		}
	}
}
