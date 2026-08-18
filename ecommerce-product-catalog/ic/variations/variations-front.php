<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages framework folder
 *
 * Here framework folder files defined and managed.
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog-pro/framework
 * @author        Norbert Dreszer
 */
class ic_cart_variations_front {

	function __construct() {
		/* Product Page */
		add_action( 'product_details', array( __CLASS__, 'show_selectors' ), 6, 0 );
		add_shortcode( 'product_variations', array( __CLASS__, 'show_selectors_shortcode' ) );

		/* Cart Page */
		add_filter( 'shopping_cart_product_price', array( __CLASS__, 'modify_variations_price' ), 12, 2 );
		add_filter( 'cart_product_name', array( $this, 'variations_in_shopping_cart' ), 10, 2 );

		/* Checkout Page */
		add_filter( 'cart_summary_product_name', array( $this, 'variations_in_shopping_cart_summary' ), 10, 2 );

		/* Notification Email */
		add_filter( 'cart_email_product_name', array( $this, 'variations_in_shopping_cart_email' ), 10, 3 );

		/* Admin Order Screen */
		add_filter( 'ic_order_product_name', array( $this, 'variations_in_orders_screen' ), 10, 3 );


		/* Styling */
		add_filter( 'product_page_additional_styles', array( __CLASS__, 'inline_styling' ) );
		add_filter( 'product_listing_additional_styles', array( __CLASS__, 'inline_styling' ) );

		/* AJAX */
		add_action( 'wp_ajax_nopriv_ic_get_variation_selectors', array( $this, 'ajax_show_selectors' ) );
		add_action( 'wp_ajax_ic_get_variation_selectors', array( $this, 'ajax_show_selectors' ) );

		add_filter( 'ic_cart_tr_class', array( $this, 'tr_class' ), 10, 2 );
		add_filter( 'ic_cart_products_table_class', array( $this, 'table_class' ) );
	}

	function table_class( $class ) {
		$product_variations_settings = get_product_variations_settings();
		$class                       .= ' var-mode-' . $product_variations_settings['mode'];

		return $class;
	}

	function tr_class( $class, $cart_id ) {
		$product_id = cart_id_to_product_id( $cart_id );
		if ( ic_has_product_variations( $product_id ) ) {
			if ( ! empty( $class ) ) {
				$class .= ' ';
			}
			$class .= 'variable-product';
		}

		return $class;
	}

	function ajax_show_selectors() {
		$nonce = isset( $_REQUEST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ic_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'ic_variation_selectors' ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! empty( $product_id ) ) {
			do_action( 'ic_ajax_self_submit_init' );
			$this->show_selectors( $product_id, 1 );
		}
		wp_die();
	}

	function equal() {
		$equal = apply_filters( 'ic_var_equal', ' = ' );

		return $equal;
	}

	/**
	 * Adds variation price modificator to shopping cart product price
	 *
	 * @param type $price
	 * @param type $cart_id
	 *
	 * @return type
	 */
	static function modify_variations_price( $price, $cart_id ) {
		if ( $selected = get_variation_lp_from_cart_id( $cart_id ) ) {
			//$product_id	 = cart_id_to_product_id( $cart_id );
			$price = get_variations_modificators( $cart_id, $selected, null, false, $price );
		}

		return $price;
	}

	static function show_selectors( $cart_id = null, $echo = 1 ) {
		$product_id = empty( $cart_id ) ? get_the_ID() : cart_id_to_product_id( $cart_id );
		if ( empty( $cart_id ) ) {
			$cart_id = $product_id;
		}
		$return = new ic_cart_variations_selectors( $product_id, $cart_id );

		return echo_ic_setting( $return->selectors(), $echo );
	}

	static function show_selectors_shortcode( $atts ) {
		$args       = shortcode_atts( array(
			'product' => ic_get_product_id(),
		), $atts );
		$product_id = $args['product'];
		if ( empty( $product_id ) ) {
			$product_id = ic_get_product_id();
		}
		$return = new ic_cart_variations_selectors( $product_id );

		return '<div class="ic-variations-shortcode">' . echo_ic_setting( $return->selectors(), 0 ) . '</div>';
	}

	function variations_in_shopping_cart( $content, $product_id ) {
//$product_id = cart_id_to_product_id( $product_id );
		$content .= '<br />' . $this->show_selectors( $product_id, 0 );

		return $content;
	}

	function variations_in_orders_screen( $product_name, $order_products, $i = null ) {
		if ( $i !== null && ! empty( $order_products['variations'][ $i ] ) ) {
			$variations = $order_products['variations'][ $i ];
			$def_qty    = $order_products['product_quantity'][ $i ];
		} else if ( $i === null && ! empty( $order_products['variations'] ) ) {
			$variations = $order_products['variations'];
			$def_qty    = $order_products['product_quantity'];
		}
		if ( ! empty( $variations ) ) {
			$var_info = '';
			foreach ( $variations as $label => $value ) {
				if ( empty( $value ) ) {
					continue;
				}
				$divider = '<br />';
				if ( ! is_array( $value ) ) {
					$value   = array( $value => $def_qty );
					$divider = ', ';
				}
				foreach ( $value as $val_name => $val_qty ) {
					if ( ! empty( $var_info ) ) {
						$var_info .= $divider;
					}
					$var_info .= $label . ' ' . apply_filters( 'ic_variation_value_in_admin_order', $val_name );
				}
			}
			if ( $divider === ', ' ) {
				$product_name .= ' (';
			} else {
				$product_name .= $divider;
			}
			$product_name .= $var_info;
			if ( $divider === ', ' ) {
				$product_name .= ')';
			}
		}

		return $product_name;
	}

	/**
	 * Adds selected variations to product name
	 *
	 * @param type $content
	 * @param type $cart_id
	 *
	 * @return string
	 */
	function variations_in_shopping_cart_summary( $content, $cart_id ) {
		$product_id                  = cart_id_to_product_id( $cart_id );
		$product_variations_settings = get_product_variations_settings();
		$cart_id_non_var             = get_cart_id_without_variations( $cart_id );
		if ( $product_variations_settings['mode'] == 'normal' ) {
//$content .= '<br />';
			$variation_value = false;
			for ( $i = 1; $i <= $product_variations_settings['count']; $i ++ ) {
				$variation_label = get_product_variation_label( $product_id, $i );
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
					if ( isset( $_POST[ $i . "_variation_" . $cart_id ] ) && $_POST[ $i . "_variation_" . $cart_id ] != '' ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
						$variation_lp = sanitize_text_field( wp_unslash( $_POST[ $i . "_variation_" . $cart_id ] ) );
				} else {
					$variation_lp = get_variation_lp_from_cart_id( $cart_id, $i );
				}
				$variation_value = get_variation_value_from_cart_id( $cart_id, $i );

				if ( $variation_value ) {
					$default_summary = '';
					if ( ! is_array( $variation_value ) ) {
						$default_summary = '<input name="' . $i . "_variation_" . $cart_id_non_var . '" type="hidden" hidden value="' . sanitize_title( $variation_lp ) . '" /><span class="chosen_variation">' . $variation_label . $this->equal() . $variation_value . '</span>';
					}
					$content .= apply_filters( 'ic_cart_summary_variation', $default_summary, $i, $cart_id, $cart_id_non_var, $variation_value, $variation_label, $this->equal() );
				}
			}
		} else {
			$variation_value = false;
			$inside_content  = '';
			for ( $i = 1; $i <= $product_variations_settings['count']; $i ++ ) {
				$variation_label = get_product_variation_label( $product_id, $i );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				if ( isset( $_POST[ $i . "_variation_" . $cart_id ] ) && $_POST[ $i . "_variation_" . $cart_id ] != '' ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
						$variation_value = sanitize_text_field( wp_unslash( $_POST[ $i . "_variation_" . $cart_id ] ) );
				} else {
					$variation_value = get_variation_value_from_cart_id( $cart_id, $i );
				}
				if ( $variation_value ) {
					$inside_content .= '<input name="' . $i . "_variation_" . $cart_id_non_var . '" type="hidden" hidden value="' . sanitize_title( $variation_value ) . '" /><span class="product_name_variation ' . esc_attr( $variation_label ) . '">' . $variation_value . '</span>';
				}
			}
			if ( ! empty( $inside_content ) ) {
				$content = $inside_content;
			}
		}

		return $content;
	}

	/**
	 * Adds selected variations to product name
	 *
	 * @param type $content
	 * @param type $product_id
	 * @param type $cart_id
	 *
	 * @return type
	 */
	function variations_in_shopping_cart_email( $content, $product_id, $cart_id ) {
		$product_variations_settings = get_product_variations_settings();
		$inside_content              = '';
		if ( $product_variations_settings['mode'] == 'normal' ) {
			for ( $i = 1; $i <= $product_variations_settings['count']; $i ++ ) {
				$variation_label = get_product_variation_label( $product_id, $i );
				$variation_value = get_variation_value_from_cart_id( $cart_id, $i );
				if ( isset( $variation_value ) && $variation_value != '' ) {
					if ( $i > 1 && ! empty( $inside_content ) ) {
						$inside_content .= apply_filters( 'ic_catalog_notification_variation_divider', ', ' );
					}
					$inside_content .= apply_filters( 'ic_catalog_notification_variation', $variation_label . $this->equal() . $variation_value, $variation_value, $variation_label, $this->equal() );
				}
			}
			if ( $inside_content != '' ) {
				$content .= apply_filters( 'ic_catalog_notification_variation_summary', ' (' . $inside_content . ')', $inside_content );
			}
		} else {
			for ( $i = 1; $i <= $product_variations_settings['count']; $i ++ ) {
				$variation_value = get_variation_value_from_cart_id( $cart_id, $i );
				if ( isset( $variation_value ) && $variation_value != '' ) {
					$inside_content .= $variation_value;
				}
			}
			if ( $inside_content != '' ) {
				$content = $inside_content;
			}
		}

		return $content;
	}

	static function inline_styling( $styles ) {
		$styles .= '.ic_spinner{background: url(' . admin_url() . 'images/spinner.gif) no-repeat;}';

		return $styles;
	}

}

global $ic_cart_variations_front;
$ic_cart_variations_front = new ic_cart_variations_front;
