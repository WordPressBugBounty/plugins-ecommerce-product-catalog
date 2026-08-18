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

	return $settings;
}