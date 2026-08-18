<?php
/**
 * EPC sold product status bootstrap.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( AL_BASE_PATH . '/ic/status/sold-product-status.php' );
}

if ( class_exists( 'ic_sold_product_status' ) ) {
	ic_sold_product_status::instance(
		array(
			'sold_label'         => __( 'Sold', 'ecommerce-product-catalog' ),
			/* translators: %s: Number of sold products. */
			'sold_label_count'   => _n_noop( 'Sold <span class="count">(%s)</span>', 'Sold <span class="count">(%s)</span>', 'ecommerce-product-catalog' ),
			/* translators: %s: Product name. */
			'unavailable_text'   => __( 'This %s is currently not available.', 'ecommerce-product-catalog' ),
			'add_visible_status' => true,
		)
	);
}
