<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages product attributes
 *
 * Here all product attributes are defined and managed.
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog/includes
 * @author        impleCode
 */
add_filter( 'admin_product_details', 'ic_mpn_metabox', 10, 2 );
add_filter( 'ic_epc_ai_product_fields', 'ic_mpn_ai_product_field', 10, 3 );

/**
 * Adds the AI MPN field when the MPN module is enabled.
 *
 * @param array  $fields    AI field definitions.
 * @param string $post_type Product post type.
 * @param object $context   EPC AI integration context.
 *
 * @return array
 */
function ic_mpn_ai_product_field( $fields, $post_type, $taxonomy ) {
	if ( ! class_exists( 'IC_Product_Details' ) || ! IC_Product_Details::is_ai_product_post_type( $post_type ) || ! is_ic_mpn_enabled() ) {
		return $fields;
	}

	$fields['mpn'] = array(
		'type'            => 'meta',
		'label'           => __( 'MPN', 'ecommerce-product-catalog' ),
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This declaration binds MPN to its established product meta key.
		'meta_key'        => '_mpn',
		'form_field'      => '_mpn',
		'input_selector'  => '[name="_mpn"]',
		'enhancement_mode' => 'preserve',
		'default_enabled' => true,
	);

	return $fields;
}

/**
 * Adds attributes meatbox
 *
 * @param array $names
 */
function ic_mpn_metabox( $product_details, $product_id, $field_name = '_mpn', $mpn = null ) {
	if ( is_ic_mpn_enabled() ) {
		if ( $mpn === null ) {
			$mpn = get_product_mpn( $product_id );
		}
		$single_names     = get_single_names();
		$product_details .= apply_filters( 'admin_mpn_table', '<table><tr><td class="label-column">' . str_replace( ':', '', $single_names['product_mpn'] ) . ':</td><td class="mpn-column"><input type="text" name="' . $field_name . '" value="' . $mpn . '" class="widefat" /></td></tr></table>', $product_id );
	}

	return $product_details;
}

add_filter( 'product_meta_save', 'ic_save_product_mpn', 1 );

/**
 * Saves product attributes
 *
 * @param type $product_meta
 *
 * @return type
 */
function ic_save_product_mpn( $product_meta ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- IC_Register_Product::implecode_save_products_meta() verifies the product save nonce, the 'editpost' action and the edit_post capability before applying the product_meta_save filter. The value is deliberately left slashed: it is persisted with update_post_meta(), and update_metadata() unslashes it. Adding wp_unslash() here would unslash twice and drop literal backslashes.
	$product_meta['_mpn'] = isset( $_POST['_mpn'] ) ? sanitize_text_field( $_POST['_mpn'] ) : '';

	return $product_meta;
}
