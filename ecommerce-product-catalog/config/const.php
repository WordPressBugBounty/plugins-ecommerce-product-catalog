<?php
/**
 * Defines catalog constants.
 *
 * Plugin parameters are defined and managed here.
 *
 * @version 1.0.0
 * @package ecommerce-product-catalog/functions
 * @author  impleCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DEF_SHIPPING_OPTIONS_NUMBER', '1' );
define( 'DEF_ATTRIBUTES_OPTIONS_NUMBER', '3' );
define( 'DEF_VALUE', '0' );
if ( ! defined( 'DEF_CATALOG_SINGULAR' ) ) {
	define( 'DEF_CATALOG_SINGULAR', __( 'Product', 'ecommerce-product-catalog' ) );
}
if ( ! defined( 'DEF_CATALOG_PLURAL' ) ) {
	define( 'DEF_CATALOG_PLURAL', __( 'Products', 'ecommerce-product-catalog' ) );
}

if ( ! defined( 'IC_CATALOG_PLUGIN_NAME' ) ) {
	define( 'IC_CATALOG_PLUGIN_NAME', 'eCommerce Product Catalog' );
}

if ( ! defined( 'IC_CATALOG_PLUGIN_SLUG' ) ) {
	define( 'IC_CATALOG_PLUGIN_SLUG', 'ecommerce-product-catalog' );
}
