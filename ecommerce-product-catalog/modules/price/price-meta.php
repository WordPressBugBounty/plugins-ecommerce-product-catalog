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
add_filter( 'admin_product_details', 'ic_price_metabox', 5, 2 );
add_filter( 'ic_epc_ai_product_fields', 'ic_price_ai_product_field', 10, 3 );

/**
 * Adds the AI price field when the price module is enabled.
 *
 * @param array  $fields    AI field definitions.
 * @param string $post_type Product post type.
 * @param object $context   EPC AI integration context.
 *
 * @return array
 */
function ic_price_ai_product_field( $fields, $post_type, $taxonomy ) {
	if ( ! class_exists( 'IC_Product_Details' ) || ! IC_Product_Details::is_ai_product_post_type( $post_type ) || ! is_ic_price_enabled() ) {
		return $fields;
	}

	$fields['price'] = array(
		'type'            => 'meta',
		'label'           => __( 'Price', 'ecommerce-product-catalog' ),
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This declaration binds price to its established product meta key.
		'meta_key'        => '_price',
		'form_field'      => '_price',
		'input_selector'  => '[name="_price"]',
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
function ic_price_metabox( $product_details, $product_id ) {
	if ( is_ic_price_enabled() ) {
		$set = get_currency_settings();
		// $price             = get_post_meta( $product_id, '_price', true );
		$price = product_price( $product_id, 1 );
		if ( ! empty( $price ) ) {
			$formatted_price = price_format( $price, 1, 0 );
		} else {
			$formatted_price = $price;
		}
		/* translators: Placeholders are substituted with runtime values. */
		$product_details .= apply_filters( 'admin_price_table', '<table><tr><td class="label-column">' . __( 'Price', 'ecommerce-product-catalog' ) . ':</td><td class="price-column"><input type="text" title="' . sprintf( __( 'Example price format: %1$s or %2$s', 'ecommerce-product-catalog' ), price_format( '1587.89', 1, 0 ), '1587' . $set['dec_sep'] . '89' ) . ' (' . __( 'you can change it in product settings', 'ecommerce-product-catalog' ) . ')" pattern="^(([1-9](\\d*|\\d{0,2}(' . $set['th_sep'] . '\\d{3})*))|0)(' . $set['dec_sep'] . '\\d{1,' . ic_price_display::decimals() . '})?$" name="_price" value="' . $formatted_price . '" class="widefat ic-input ic-product-meta-field" /></td><td>' . product_currency() . '</td></tr></table>', $product_id );
		/* translators: 1: formatted price using the site currency, 2: numeric price example. */
		$product_details .= '<div id="invalid-_price" class="ui-state-error ui-corner-all message" style="padding: 0 .7em; display: none;"><p>' . sprintf( __( 'Please provide a correct price format according to your currency settings. Example price format: %1$s or %2$s', 'ecommerce-product-catalog' ), price_format( '1587.89', 1, 0 ), '1587' . $set['dec_sep'] . '89' ) . '</p></div>';
	}

	return $product_details;
}

add_filter( 'product_meta_save', 'ic_save_product_price', 1 );

/**
 * Saves product attributes
 *
 * @param type $product_meta
 *
 * @return type
 */
function ic_save_product_price( $product_meta ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- IC_Register_Product::implecode_save_products_meta() verifies the product save nonce, the 'editpost' action and the edit_post capability before applying the product_meta_save filter.
	$posted_price = isset( $_POST['_price'] ) ? sanitize_text_field( wp_unslash( $_POST['_price'] ) ) : '';
	$price        = $posted_price != null ? ic_price_display::raw_price_format( $posted_price ) : '';
	if ( ! empty( $price ) ) {
		$price = floatval( $price );
	}
	$product_meta['_price'] = $price;

	return $product_meta;
}

add_filter( 'product_columns_after_name', 'ic_price_column' );

/**
 * Adds product price column
 *
 * @param type $new_columns
 *
 * @return type
 */
function ic_price_column( $new_columns ) {
	if ( is_ic_price_enabled() ) {
		$new_columns['price'] = __( 'Price', 'ecommerce-product-catalog' );
	}
	$new_columns = apply_filters( 'product_columns_after_price', $new_columns );

	return $new_columns;
}

add_action( 'manage_al_product_posts_custom_column', 'manage_product_price_column', 10, 2 );

/**
 * Shows price column content
 *
 * @param type $column_name
 * @param type $product_id
 */
function manage_product_price_column( $column_name, $product_id ) {
	if ( $column_name == 'price' ) {
		$price_value = product_price( $product_id );
		if ( $price_value != '' ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: built by price_format(), which emits the currency markup.
			echo price_format( $price_value );
		}
	}
}

add_action( 'pre_get_posts', 'column_orderby_price', 20 );

/**
 * Manages order by price in admin
 *
 * @param type $query
 *
 * @return type
 */
function column_orderby_price( $query ) {
	if ( function_exists( 'is_ic_product_list_admin_screen' ) && ! is_ic_product_list_admin_screen() ) {
		return;
	}
	$orderby = $query->get( 'orderby' );

	if ( 'price' == $orderby ) {
		$query->set( 'meta_key', '_price' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}

add_action( 'product_quickedit', 'ic_price_quickedit' );

/**
 * Shows product price quick edit
 *
 * @param type $column_name
 */
function ic_price_quickedit( $column_name ) {
	if ( $column_name == 'price' ) {
		?><span class="title"><?php esc_html_e( 'Price', 'ecommerce-product-catalog' ); ?></span><input type="number" min="0"
																								step="0.01" name="_price"
																								value=""
																								class="widefat"/>
																								<?php
																								echo esc_html( product_currency() );
	}
}

add_action( 'save_product_quick_edit', 'ic_price_quickedit_save', 8 );

/**
 * Handles product quickedit save
 *
 * @param type $product_id
 */
function ic_price_quickedit_save( $product_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- save_product_quick_edit() verifies the inlineeditnonce and the edit_product capability before firing this action.
	$posted_price = isset( $_REQUEST['_price'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_price'] ) ) : '';
	if ( $posted_price != null ) {
		$price = ic_price_display::raw_price_format( $posted_price );
		update_post_meta( $product_id, '_price', $price );
	}
}

add_action( 'ic_register_post_meta', 'ic_price_register_meta' );

function ic_price_register_meta( $post_type ) {
	register_post_meta(
		$post_type,
		'_price',
		array(
			'auth_callback' => '__return_true',
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
		)
	);
}
