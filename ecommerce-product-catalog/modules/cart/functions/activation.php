<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages plugin activation functions
 *
 * Here all plugin activation functions are defined and managed.
 *
 * @version     1.0.0
 * @package     implecode-shopping-cart/functions
 * @author      Norbert Dreszer
 */
add_action( 'admin_init', 'ic_cart_add_customer_role' );

function ic_cart_add_customer_role() {
	$role = get_role( 'customer' );
	if ( empty( $role ) ) {
		add_role( 'customer', __( 'Customer', 'ecommerce-product-catalog' ), array( 'read' => true ) );

		return;
	}
	// Repairs roles created before 'read' was granted. Without it the logged-in branch of
	// ic_verify_public_cart_nonce() rejects every cart AJAX request a customer makes, so
	// they cannot add to cart or check out while anonymous visitors are unaffected.
	if ( ! $role->has_cap( 'read' ) ) {
		$role->add_cap( 'read' );
	}
}
