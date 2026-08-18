<?php
/**
 * Shared public cart button helpers.
 *
 * @package ic/cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds the cached add-to-cart button nonce to the catalog product object.
 *
 * @param array $nonces Existing nonce map.
 * @return array
 */
function ic_cart_buttons_product_object_nonces( $nonces ) {
	$nonces['cart_add_button_nonce'] = wp_create_nonce( 'ic_shopping_add_cart_get' );

	return $nonces;
}
add_filter( 'ic_catalog_product_object_nonces', 'ic_cart_buttons_product_object_nonces' );

add_filter( 'ic_cart_add_button', 'ic_cart_added_info_button', 10, 2 );

/**
 * Registers the shared add-to-cart button product-page hook.
 *
 * @return void
 */
function ic_register_cart_add_button_hooks() {
	if ( ! has_action( 'price_table', 'ic_cart_add_button' ) ) {
		add_action( 'price_table', 'ic_cart_add_button', 10, 0 );
	}
}

/**
 * Outputs or returns the add-to-cart button markup.
 *
 * @param int|string      $echo                Whether to echo the result.
 * @param int|string      $table               Whether to wrap the output in a table row.
 * @param int|string      $desc                Whether to output the helper description.
 * @param int|null|string $product_id          Product identifier.
 * @param bool            $qty                 Whether to render the quantity field.
 * @param string|null     $label               Custom button label.
 * @param bool            $redirect            Whether to redirect to cart on submit.
 * @param string          $before_button       HTML before the button.
 * @param string|null     $cart_content        Serialized cart content.
 * @param array|null      $additional_products Extra product IDs to include.
 *
 * @return string|null
 */
function ic_cart_add_button(
	$echo = 1,
	$table = 1,
	$desc = 1,
	$product_id = null,
	$qty = true,
	$label = null,
	$redirect = false,
	$before_button = '',
	$cart_content = null,
	$additional_products = null
) {
	if ( empty( $product_id ) ) {
		$product_id = function_exists( 'ic_get_product_id' ) ? ic_get_product_id() : get_the_ID();
	} else {
		$product_id = intval( $product_id );
	}

	$show_button = product_price( $product_id ) !== '';
	if ( function_exists( 'is_ic_add_to_cart_shown' ) ) {
		$show_button = is_ic_add_to_cart_shown( $product_id );
	}
	if ( ! $show_button ) {
		return null;
	}

	if ( empty( $cart_content ) ) {
		$cart_content = ic_shopping_cart_content( true );
	}

	$shopping_cart_settings = get_shopping_cart_settings();
	$button_class           = ( ! empty( $shopping_cart_settings['url_button'] ) && intval( $shopping_cart_settings['url_button'] ) === 1 ) ? 'link' : 'button';
	$button_class          .= ' ' . design_schemes( 'box', 0 );
	$action                 = '';
	if ( ! empty( $shopping_cart_settings['cart_redirect'] ) || $redirect ) {
		$action = ic_shopping_cart_page_url();
	}

	$return = '';
	if ( intval( $table ) === 1 ) {
		$return .= '<tr><td colspan="4">';
	}

	$form_class           = 'add-to-shopping-cart reg_add';
	$form_container_class = 'add_to_cart_form_container';
	$recently_added       = function_exists( 'ic_get_recently_added_product' ) ? ic_get_recently_added_product() : '';
	if ( function_exists( 'ic_has_product_variations' ) && ic_has_product_variations( $product_id ) && ! empty( $recently_added ) ) {
		$form_container_class .= ' ic-button-hidden';
	}

	$attr          = '';
	$function_args = func_get_args();
	if ( function_exists( 'ic_is_cached_content' ) && ic_is_cached_content() ) {
		$class                 = function_exists( 'ic_pre_cached_class' ) ? ic_pre_cached_class() : 'ic-pre-cached';
		$form_container_class .= ' ' . $class;
		$attr                 .= ' data-args="' . esc_attr( wp_json_encode( $function_args ) ) . '" data-action="ic_shopping_add_cart_get"';
		if ( ! empty( $product_id ) ) {
			$attr .= ' data-product_id="' . esc_attr( $product_id ) . '"';
		}
	}

	$return .= '<div class="' . esc_attr( $form_container_class ) . '"' . $attr . '><form id="reg_add_' . esc_attr( $product_id ) . '" class="' . esc_attr( $form_class ) . '" action="' . esc_url( $action ) . '" method="post">';
	$return .= '<input hidden type="hidden" name="current_product" value="' . esc_attr( $product_id ) . '">';
	if ( function_exists( 'get_product_name' ) ) {
		$return .= '<input hidden type="hidden" name="current_product_name" value="' . esc_attr( get_product_name( $product_id ) ) . '">';
	}
	if ( function_exists( 'product_price' ) ) {
		$return .= '<input hidden type="hidden" name="current_product_price" value="' . esc_attr( product_price( $product_id ) ) . '">';
	}
	if ( function_exists( 'ic_cart_total' ) ) {
		$return .= '<input hidden type="hidden" name="current_cart_total" value="' . esc_attr( ic_cart_total() ) . '">';
	}
	if ( ! empty( $additional_products ) && is_array( $additional_products ) ) {
		foreach ( $additional_products as $additional_product_id ) {
			$return .= '<input hidden type="hidden" name="additional_product[]" value="' . esc_attr( $additional_product_id ) . '">';
		}
	}
	$return .= '<input hidden type="hidden" name="cart_content" value="' . esc_attr( $cart_content ) . '">';

	$product_variation_values = function_exists( 'get_product_variations_values' ) ? get_product_variations_values( $product_id ) : array();
	foreach ( $product_variation_values as $i => $variation_values ) {
		$var_id = $i + 1;
		if ( function_exists( 'ic_cat_get_variation_type' ) && 'custom' !== ic_cat_get_variation_type( $product_id, $var_id ) ) {
			$variation_values = function_exists( 'ic_filter_empty_array_values' ) ? ic_filter_empty_array_values( $variation_values ) : array_filter( $variation_values );
		}
		if ( ! empty( $variation_values ) ) {
			$hidden  = '<input type="hidden" name="' . esc_attr( $var_id ) . '_variation_' . esc_attr( $product_id ) . '" value="">';
			$return .= apply_filters( 'ic_add_to_cart_variation_hidden', $hidden, $product_id, $var_id );
		}
	}

	if ( empty( $label ) ) {
		$label = $shopping_cart_settings['button_label'];
	}
	$add_to_cart_button = apply_filters( 'ic_cart_add_button', '<button type="submit" class="' . esc_attr( $button_class ) . '">' . esc_html( $label ) . '</button>', $product_id );
	$return            .= apply_filters( 'before_shopping_cart_button', $before_button, $product_id );
	if ( empty( $shopping_cart_settings['quantity_box'] ) && $qty && $shopping_cart_settings['cart_page_template'] !== 'no_qty' ) {
		$return .= ic_cart_quantity_field( $product_id );
	} else {
		$return .= '<input type="hidden" name="current_quantity" value="1">';
	}
	$return .= $add_to_cart_button;
	if ( intval( $desc ) === 1 && ! empty( $shopping_cart_settings['button_desc'] ) ) {
		$return .= '<div class="cart_info">' . apply_filters( 'shopping_cart_button_info', $shopping_cart_settings['button_desc'] ) . '</div>';
	}
	$return .= '</form>';
	$return .= apply_filters( 'after_shopping_cart_button', '', $product_id );

	if ( function_exists( 'get_discount_field' ) ) {
		$disc = get_discount_field( $product_id );
		if ( ! empty( $disc['qty'] ) ) {
			$return .= '<form style="display: none;" id="qty_add_' . esc_attr( $product_id ) . '" class="add-to-shopping-cart qty_add" action="' . esc_url( $action ) . '" method="post"><input hidden type="hidden" name="current_product" value="' . esc_attr( $product_id ) . '"><input hidden type="hidden" name="current_quantity" value="' . esc_attr( $disc['qty'] ) . '"><input hidden type="hidden" name="cart_content" value="' . esc_attr( $cart_content ) . '">';
			foreach ( $product_variation_values as $i => $variation_values ) {
				$variation_values = function_exists( 'ic_filter_empty_array_values' ) ? ic_filter_empty_array_values( $variation_values ) : array_filter( $variation_values );
				if ( ! empty( $variation_values ) ) {
					++$i;
					$return .= '<input hidden type="hidden" name="' . esc_attr( $i ) . '_variation_' . esc_attr( $product_id ) . '" value="">';
				}
			}
			$return .= '</form>';
		}
	}

	$return .= '</div>';
	if ( intval( $table ) === 1 ) {
		$return .= '</td></tr>';
	}

	return echo_ic_setting( $return, $echo );
}

add_action( 'wp_ajax_nopriv_ic_shopping_add_cart_get', 'ic_ajax_shopping_add_cart_get' );
add_action( 'wp_ajax_ic_shopping_add_cart_get', 'ic_ajax_shopping_add_cart_get' );

/**
 * Returns a cached add-to-cart button fragment.
 *
 * @return void
 */
function ic_ajax_shopping_add_cart_get() {
	$nonce = isset( $_REQUEST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ic_nonce'] ) ) : '';
	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_shopping_add_cart_get' ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	$args = isset( $_POST['args'] ) && is_array( $_POST['args'] ) ? map_deep( wp_unslash( $_POST['args'] ), 'sanitize_text_field' ) : array();
	if ( ! empty( $args ) && ! is_array( $args ) ) {
		$args = json_decode( stripslashes( $args ), true );
		if ( ! empty( $args ) && ! is_array( $args ) ) {
			$args = json_decode( $args, true );
		}
	}
	if ( ! is_array( $args ) ) {
		$args = array();
	}
	$args[0] = 0;
	if ( ! empty( $args[3] ) ) {
		$product_id = intval( $args[3] );
		if ( function_exists( 'is_ic_product' ) && is_ic_product( $product_id ) ) {
			ic_set_product_id( $product_id );
		}
	}
	// Generated cart controls escape each attribute and label; post KSES would remove functional inputs.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated cart controls are escaped at construction time.
	echo call_user_func_array( 'ic_cart_add_button', $args );
	wp_die();
}

add_filter( 'ic_pre_cached_parts', 'ic_ajax_shopping_add_cart_add_part', 10, 2 );

/**
 * Adds add-to-cart button fragments to the shared precache response.
 *
 * @param array $parts Existing precached parts.
 * @param array $args  Request arguments.
 *
 * @return array
 */
function ic_ajax_shopping_add_cart_add_part( $parts, $args = array() ) {
	if ( ! isset( $args['ic_shopping_add_cart_get'] ) ) {
		return $parts;
	}
	$this_args = isset( $args['ic_shopping_add_cart_get'] ) ? $args['ic_shopping_add_cart_get'] : array();
	$first_arg = reset( $this_args );
	if ( is_array( $first_arg ) ) {
		foreach ( $this_args as $product_id => $product_args ) {
			$parts['ic_shopping_add_cart_get'][ $product_id ] = ic_get_cart_add_button_part( $product_args );
		}
	} else {
		$parts['ic_shopping_add_cart_get'] = ic_get_cart_add_button_part( $this_args );
	}

	return $parts;
}

/**
 * Renders one shared add-to-cart precache fragment.
 *
 * @param array $this_args Button arguments.
 *
 * @return string
 */
function ic_get_cart_add_button_part( $this_args ) {
	if ( ! is_array( $this_args ) ) {
		return '';
	}
	$this_args[0] = 0;
	if ( ! empty( $this_args[3] ) ) {
		$product_id = intval( $this_args[3] );
		if ( function_exists( 'is_ic_product' ) && is_ic_product( $product_id ) ) {
			ic_set_product_id( $product_id );
		}
	}

	return call_user_func_array( 'ic_cart_add_button', $this_args );
}

/**
 * Returns the choose-options button markup.
 *
 * @param int $product_id Product identifier.
 *
 * @return string
 */
function ic_cart_choose_options_button( $product_id ) {
	$shopping_cart_settings = get_shopping_cart_settings();
	$button_class           = ( ! empty( $shopping_cart_settings['url_button'] ) && intval( $shopping_cart_settings['url_button'] ) === 1 ) ? 'link' : 'button';
	$button_class          .= ' ' . design_schemes( 'box', 0 );

	return '<a href="' . esc_url( ic_get_permalink( $product_id ) ) . '" class="' . esc_attr( $button_class ) . ' choose-options">' . esc_html__( 'Select Options', 'ecommerce-product-catalog' ) . '</a>';
}

if ( ! function_exists( 'ic_cart_quantity_field' ) ) {
	/**
	 * Returns the quantity field markup.
	 *
	 * @param int         $product_id Product identifier.
	 * @param string      $name       Field name.
	 * @param int|float   $value      Current value.
	 * @param string|null $cart_id    Cart identifier.
	 * @param int|float   $min        Minimum value.
	 * @param int|float   $max        Maximum value.
	 * @param string      $attr       Extra HTML attributes.
	 *
	 * @return string
	 */
	function ic_cart_quantity_field(
		$product_id,
		$name = 'current_quantity',
		$value = 1,
		$cart_id = null,
		$min = 1,
		$max = '',
		$attr = ''
	) {
		if ( function_exists( 'is_ic_min_qty_enabled' ) && is_ic_min_qty_enabled() && function_exists( 'get_product_min_qty' ) ) {
			$min = get_product_min_qty( $product_id );
		}
		if ( function_exists( 'is_ic_max_qty_enabled' ) && is_ic_max_qty_enabled() && function_exists( 'get_product_max_qty' ) ) {
			$max = get_product_max_qty( $product_id );
		}
		if ( $max !== '' && $min > $max ) {
			$min = $max;
		}
		if ( $value < $min ) {
			$value = $min;
		}
		if ( empty( $cart_id ) ) {
			$cart_id = $product_id;
		}

		return apply_filters( 'ic_product_page_quantity_field', '<input class="ic-current-quantity" data-product_id="' . esc_attr( $cart_id ) . '" name="' . esc_attr( $name ) . '" type="number" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="1" value="' . esc_attr( $value ) . '"' . $attr . '> ' );
	}
}

add_filter( 'design_schemes_output', 'ic_cart_clickable_price', 10, 2 );

/**
 * Adds the clickable-price helper class when enabled.
 *
 * @param string $design_schemes Existing classes.
 * @param string $which          Design context.
 *
 * @return string
 */
function ic_cart_clickable_price( $design_schemes, $which ) {
	$shopping_cart_settings = get_shopping_cart_settings();
	if ( empty( $shopping_cart_settings['add_on_price'] ) && 'price' === $which && is_ic_product_listing() ) {
		$design_schemes .= ' price-add-to-cart';
	}

	return $design_schemes;
}

if ( ! function_exists( 'ic_listing_add_to_cart' ) ) {
	add_action( 'classic_grid_product_listing_element', 'ic_listing_add_to_cart', 10, 2 );

	/**
	 * Outputs add-to-cart in listing layouts when enabled.
	 *
	 * @param int   $product_id       Product identifier.
	 * @param array $listing_settings Listing settings.
	 *
	 * @return void
	 */
	function ic_listing_add_to_cart( $product_id, $listing_settings ) {
		if ( ! empty( $listing_settings['add_to_cart'] ) ) {
			ic_cart_add_button( 1, 0, 0, $product_id, false );
		}
	}
}
