<?php
/**
 * Shared AJAX selector bootstrap.
 *
 * Replaces capped `<select>` controls with a search-as-you-type field backed
 * by a server-side source registry. The client never names a post type: it
 * sends a registry key and the server owns the type, the subtypes, the post
 * status and the capability.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ic-selector-registry.php';
require_once __DIR__ . '/class-ic-selector-ajax.php';
require_once __DIR__ . '/class-ic-selector-field.php';

if ( ! function_exists( 'ic_selector_registry' ) ) {
	/**
	 * Returns the shared selector registry.
	 *
	 * @return IC_Selector_Registry
	 */
	function ic_selector_registry() {
		return IC_Selector_Registry::instance();
	}
}

if ( ! function_exists( 'ic_register_selector_source' ) ) {
	/**
	 * Registers a searchable selector source.
	 *
	 * @param string $key  Source key sent by the client.
	 * @param array  $args Source definition.
	 *
	 * @return bool
	 */
	function ic_register_selector_source( $key, $args ) {
		return ic_selector_registry()->register( $key, $args );
	}
}

if ( ! function_exists( 'ic_get_selector_source' ) ) {
	/**
	 * Returns a registered selector source.
	 *
	 * @param string $key Source key.
	 *
	 * @return array|false
	 */
	function ic_get_selector_source( $key ) {
		return ic_selector_registry()->get( $key );
	}
}

if ( ! function_exists( 'ic_string_ends_with_brackets' ) ) {
	/**
	 * Tells whether a field name already carries the array suffix.
	 *
	 * @param string $name Field name.
	 *
	 * @return bool
	 */
	function ic_string_ends_with_brackets( $name ) {
		return '[]' === substr( (string) $name, -2 );
	}
}

if ( ! function_exists( 'ic_selector_first_allowed_source' ) ) {
	/**
	 * Returns the first listed source the current user is allowed to search.
	 *
	 * A control that can be reached from screens with different capabilities --
	 * the order editor is gated on `edit_digital_orders`, the catalog settings on
	 * `manage_product_settings` -- must not be pinned to one source, or whichever
	 * role holds the other capability silently drops to the plain fallback. The
	 * caller lists the sources it considers equivalent, most specific first, and
	 * gets back the one this user may actually query.
	 *
	 * @param array|string $keys Source keys in preference order.
	 *
	 * @return string Source key, or an empty string when none is permitted.
	 */
	function ic_selector_first_allowed_source( $keys ) {
		foreach ( (array) $keys as $key ) {
			$source = ic_get_selector_source( $key );
			if ( ! empty( $source['capability'] ) && current_user_can( $source['capability'] ) ) {
				return (string) $source['key'];
			}
		}

		return '';
	}
}

if ( ! function_exists( 'ic_selector_field' ) ) {
	/**
	 * Returns a selector control.
	 *
	 * Use `IC_Selector_Field::render()` to print it instead.
	 *
	 * @param array $args Field arguments.
	 *
	 * @return string
	 */
	function ic_selector_field( $args ) {
		return IC_Selector_Field::get( $args );
	}
}

if ( ! function_exists( 'ic_selectors_register_assets' ) ) {

	/**
	 * Registers the selector script and stylesheet.
	 *
	 * The minified builds are the shipped assets; the unminified sources sit
	 * beside them for development only.
	 *
	 * @return void
	 */
	function ic_selectors_register_assets() {
		$path_root = __DIR__;
		$url_root  = plugins_url( '/', __FILE__ );

		wp_register_style(
			'ic-ajax-selector',
			$url_root . 'selector.min.css',
			array(),
			(string) ic_filemtime( $path_root . '/selector.min.css', true )
		);
		wp_register_script(
			'ic-ajax-selector',
			$url_root . 'selector.min.js',
			array(),
			(string) ic_filemtime( $path_root . '/selector.min.js', true ),
			true
		);
	}

	/**
	 * Enqueues the selector assets, registering them first when needed.
	 *
	 * @return void
	 */
	function ic_selector_enqueue_assets() {
		if ( ! wp_script_is( 'ic-ajax-selector', 'registered' ) ) {
			ic_selectors_register_assets();
		}
		wp_enqueue_style( 'ic-ajax-selector' );
		wp_enqueue_script( 'ic-ajax-selector' );
	}

	/**
	 * Registers the selector sources the framework itself owns.
	 *
	 * Each source carries its own capability. A source is never registered for
	 * a catalog function that is not loaded, so a host without the catalog
	 * simply has no product source rather than a broken one.
	 *
	 * @return void
	 */
	function ic_selectors_register_default_sources() {
		if ( function_exists( 'product_post_type_array' ) ) {
			ic_register_selector_source(
				'ic_products',
				array(
					'type'           => 'post',
					'subtypes'       => 'product_post_type_array',
					'post_status'    => function_exists( 'ic_visible_product_status' ) ? 'ic_visible_product_status' : array( 'publish' ),
					'capability'     => 'manage_product_settings',
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

		if ( function_exists( 'product_taxonomy_array' ) ) {
			ic_register_selector_source(
				'ic_product_categories',
				array(
					'type'       => 'term',
					'subtypes'   => 'product_taxonomy_array',
					'capability' => 'manage_product_settings',
					'search'     => array( 'title', 'id' ),
					'per_page'   => 20,
					'label'      => __( 'categories', 'ecommerce-product-catalog' ),
				)
			);
		}

		ic_register_selector_source(
			'ic_users',
			array(
				'type'       => 'user',
				'capability' => 'list_users',
				'search'     => array( 'title', 'id' ),
				'per_page'   => 20,
				'label'      => __( 'users', 'ecommerce-product-catalog' ),
			)
		);

		/**
		 * Fires once the framework selector sources are registered.
		 *
		 * Consumers register their own sources here.
		 */
		do_action( 'ic_register_selector_sources' );
	}

	add_action( 'init', 'ic_selectors_register_assets' );
	add_action( 'init', 'ic_selectors_register_default_sources', 5 );

	IC_Selector_Ajax::init();
}
