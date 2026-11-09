<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
 *
 *  @version       1.0.0
 *  @author        impleCode
 *
 */

add_filter( 'ic_customer_panel_settings', 'ic_quote_cart_lang_panel_settings' );
function ic_quote_cart_lang_panel_settings( $settings ) {
	global $ic_catalog_multilingual;
	if ( ! is_ic_admin() && ! empty( $settings['page_id'] ) && ! empty( $ic_catalog_multilingual ) ) {
		$settings['page_id'] = $ic_catalog_multilingual->replace_product_listing_id( $settings['page_id'] );
	}

	if ( ! is_ic_admin() ) {
		foreach ( array( 'contact_details_intro', 'billing_details_intro' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$settings[ $key ] = apply_filters( 'wpml_translate_single_string', $settings[ $key ], 'Customer Panel', $key );
				if ( function_exists( 'pll__' ) ) {
					$settings[ $key ] = pll__( $settings[ $key ] );
				}
			}
		}
	}

	return $settings;
}

add_action( 'admin_init', 'ic_customer_panel_register_intro_strings' );
/** Register configured panel introductions with multilingual providers. */
function ic_customer_panel_register_intro_strings() {
	$settings = get_option( 'customer_panel', array() );
	foreach ( array( 'contact_details_intro', 'billing_details_intro' ) as $key ) {
		if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
			do_action( 'wpml_register_single_string', 'Customer Panel', $key, $settings[ $key ] );
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( $key, $settings[ $key ], 'Customer Panel', true );
			}
		}
	}
}
