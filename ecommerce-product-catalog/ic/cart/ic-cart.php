<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages shopping cart
 *
 * Here shopping cart functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */
class ic_cart {

	function __construct() {
		if ( class_exists( 'ic_cached_cart' ) ) {
			new ic_cached_cart();
		}
		add_action( 'ic_cart_products_start', array( $this, 'clear_globals' ) );

		add_filter( 'ic_cart_checkout_default_columns', array( $this, 'image_td' ), 5, 4 );
		add_filter( 'ic_cart_checkout_default_columns', array( $this, 'name_td' ), 6, 5 );
		add_filter( 'ic_cart_checkout_default_columns', array( $this, 'sku_td' ), 7, 2 );
		add_filter( 'ic_cart_checkout_default_columns', array( $this, 'qty_td' ), 7, 6 );
		add_filter( 'ic_cart_checkout_default_columns', array( $this, 'price_td' ), 8, 6 );
		add_filter( 'ic_checkout_products_table_end', array( $this, 'price_summary' ), 8, 4 );

		add_filter( 'admin_product_details', array( $this, 'tax_meta' ), 9, 2 );
		add_filter( 'product_meta_save', array( $this, 'save_tax' ), 10 );
	}

	function clear_globals() {
		ic_delete_global( 'current_cart_total' );
		ic_delete_global( 'current_cart_calculated' );
		ic_delete_global( 'current_cart_tax' );
		ic_delete_global( 'current_cart_tax_counted' );
	}

	/**
	 * Fills in the cart layout and label defaults.
	 *
	 * Kept as a thin wrapper so the shared implementation in cart/functions.php
	 * stays reachable from classes that do not extend ic_cart.
	 *
	 * @param array|mixed $settings Raw shopping cart settings.
	 *
	 * @return array
	 */
	function normalize_settings( $settings ) {
		return ic_cart_normalize_settings( $settings );
	}

	function product_table( $raw = 1, $price = true, $cart = 'cart_content', $settings = null ) {
		ic_save_global( 'inside_cart_products', 1 );
		$pricing_enabled = $price;
		if ( empty( $settings ) ) {
			$settings = get_shopping_cart_settings();
		}
		$settings       = $this->normalize_settings( $settings );
		$cart_content   = $this->content( true, $cart );
		$products_array = ic_cart_products_array( $cart_content, $cart );
		if ( count( $products_array ) < 1 ) {
			ic_cart_clear( $cart );
		}
		// Drop items whose product was trashed or deleted while it sat in the cart, BEFORE any markup is
		// built. Doing it after the row loops would leave the hidden cart field below carrying the removed
		// id, and that field is posted back as the submitted cart, which re-persists the item for one more
		// round trip. Filtering here keeps the hidden field, the rows and the totals describing one cart.
		$unavailable_cart_ids = array();
		foreach ( $products_array as $cart_id => $quantity ) {
			if ( '' === (string) $cart_id ) {
				continue;
			}
			if ( ! ic_cart_product_is_available( cart_id_to_product_id( $cart_id ) ) ) {
				$unavailable_cart_ids[] = $cart_id;
				unset( $products_array[ $cart_id ] );
			}
		}
		if ( ! empty( $unavailable_cart_ids ) ) {
			$cart_content = $this->drop_unavailable_products( $unavailable_cart_ids, $cart );
		}
		$products_table  = '<div class="ic-cart-products-table" data-raw="' . esc_attr( $raw ) . '" data-price="' . esc_attr( $price ) . '" data-cart="' . esc_attr( $cart ) . '">';
		$products_table .= '<div class="before-cart-products">';
		$cart_params     = '<input hidden type="hidden" name="' . esc_attr( $cart ) . '" value="' . esc_attr( $cart_content ) . '">';
		$cart_params    .= '<input hidden type="hidden" name="ic_cart_name" value="' . esc_attr( $cart ) . '">';
		$products_table .= apply_filters( 'ic_cart_products_before', $cart_params, $products_array, $raw, $cart, $price );
		$products_table .= '</div>';
		$table_class     = apply_filters( 'ic_cart_products_table_class', 'cart-products' );
		if ( $raw == 1 ) {
			$table_class .= ' raw';
		}
		$products_table .= '<table class="' . $table_class . '">';
		do_action( 'ic_cart_products_start', $products_array, $cart, $price );
		if ( $settings['cart_page_template'] == 'qty' ) {
			$products_table .= '<thead><tr>';
			$products_table .= apply_filters( 'ic_checkout_products_table_start', '', $products_array, $cart, $price );
			if ( ! empty( $settings['cart_page_image'] ) ) {
				$products_table .= apply_filters( 'cart_checkout_table_header_image', '<th class="th_image"></th>' );
			}

			$products_table .= apply_filters( 'cart_checkout_table_header_product_name', '<th class="th_name">' . $settings['product_name_column_label'] . '</th>' );
			if ( is_ic_cart_sku_enabled() ) {
				$single_names    = get_single_names();
				$products_table .= apply_filters( 'cart_checkout_table_header_sku', '<th class="th_sku">' . str_replace( ':', '', $single_names['product_sku'] ) . '</th>' );
			}
			$products_table .= apply_filters( 'cart_checkout_table_header_qty', '<th class="th_qty">' . $settings['quantity_column_label'] . '</th>' );
			if ( $price !== false && apply_filters( 'ic_cart_price_columns', true ) ) {
				$products_table .= '<th class="th_price">' . $settings['unit_proice_column_label'] . '</th>';
				$products_table .= apply_filters( 'ic_cart_checkout_additional_headers', '', $raw, $price, $cart, $settings );
				$products_table .= '<th class="th_total">' . $settings['total_column_label'] . '</th>';
			} else {
				$products_table .= apply_filters( 'ic_cart_checkout_additional_headers', '', $raw, $price, $cart, $settings );
			}
			$products_table .= '</tr></thead>';
		}
		$products_table .= '<tbody>';
		// $total             = 0;
		if ( $raw == 1 ) {
			foreach ( $products_array as $cart_id => $quantity ) {
				if ( $cart_id != '' ) {
					$product_id = cart_id_to_product_id( $cart_id );
					if ( ! is_ic_product( $product_id ) ) {
						continue;
					}
					ic_set_product_id( $product_id );
					$quantity      = apply_filters( 'ic_cart_qty', $quantity, $product_id, $cart_id );
					$product_price = false;
					if ( $pricing_enabled !== false ) {
						$product_price = get_shopping_cart_product_price( $product_id, $cart_id, $quantity );
					}
					$class = apply_filters( 'ic_cart_tr_class', '', $cart_id );
					if ( ! empty( $class ) ) {
						$class = ' class="' . $class . '"';
					}
					$products_table .= '<tr' . $class . '>';
					$products_table .= apply_filters( 'ic_cart_checkout_default_columns', '', $cart_id, $quantity, $settings, $raw, $product_price );
					$products_table .= '</tr>';
					ic_reset_product_id();
				}
			}
			$products_table = apply_filters( 'ic_after_checkout_products', $products_table, $products_array, $raw );
		} else {
			$style = '';
			foreach ( $products_array as $cart_id => $quantity ) {
				if ( $cart_id != '' ) {
					$style      = 'style="display: none"';
					$product_id = cart_id_to_product_id( $cart_id );
					if ( ! is_ic_product( $product_id ) ) {
						continue;
					}
					ic_set_product_id( $product_id );
					$quantity      = apply_filters( 'ic_cart_qty', $quantity, $product_id, $cart_id );
					$product_price = false;
					if ( $pricing_enabled !== false ) {
						$product_price = get_shopping_cart_product_price( $product_id, $cart_id, $quantity );
					}
					$class = apply_filters( 'ic_cart_tr_class', '', $cart_id );
					if ( ! empty( $class ) ) {
						$class = ' class="' . $class . '"';
					}
					$products_table .= '<tr' . $class . '>';
					$products_table .= apply_filters( 'ic_cart_checkout_default_columns', '', $cart_id, $quantity, $settings, $raw, $product_price );
					$products_table .= '</tr>';
					ic_reset_product_id();
				}
			}
			$products_table = apply_filters( 'ic_after_shopping_cart_products', $products_table, $products_array, $raw );
			if ( is_ic_product_listing_enabled() ) {
				/* translators: Placeholders are substituted with runtime values. */
				$no_products_text = sprintf( __( 'No products. <a href="%s">Go back</a> and choose some.', 'ecommerce-product-catalog' ), ic_get_shopping_back_url() );
			} else {
				$no_products_text = __( 'No products.', 'ecommerce-product-catalog' );
			}
			if ( isset( $settings['empty_cart'] ) && $settings['empty_cart'] != '' ) {
				$no_products_text = $settings['empty_cart'];
			}
			// The empty-state cell must span the whole body row. ic_cart_colspan() returns the width of
			// every column BUT the trailing total cell (that is why the summary rows below emit
			// colspan + one td), so colspan + 1 is the full row width: 4 for the qty template and 3 for
			// no_qty, before the optional SKU and image columns. A fixed literal cannot serve both.
			$empty_row_colspan = ic_cart_colspan( $settings ) + 1;
			$products_table   .= '<tr class="no-products" ' . $style . '><td colspan="' . esc_attr( $empty_row_colspan ) . '">' . apply_filters( 'ic_no_products_text', $no_products_text ) . '</td></tr>';
		}

		$products_table .= apply_filters( 'ic_checkout_products_table_end', '', $pricing_enabled, $cart, $settings );
		$products_table .= '</tbody></table>';
		ob_start();
		do_action( 'ic_cart_products_end', $products_array, $cart, $pricing_enabled, $raw );
		$products_table .= ob_get_clean();
		$products_table .= '</div>';
		ic_delete_global( 'inside_cart_products' );

		return $products_table;
	}

	/**
	 * Appends the no-qty delete controls (hidden cart id, hidden quantity, delete span).
	 *
	 * Emits NO cell wrapper: the controls are rendered inside the product name cell by name_td().
	 * The signature is kept unchanged for third-party direct callers.
	 *
	 * @param string $products_table Markup collected so far.
	 * @param string $cart_id        Cart item identifier.
	 * @param mixed  $quantity       Item quantity (unused, kept for the column callback signature).
	 * @param array  $settings       Cart display settings.
	 * @param bool   $raw            True on the raw (summary/submit) rendering.
	 *
	 * @return string Markup with the delete controls appended when applicable.
	 */
	function delete_button( $products_table, $cart_id, $quantity, $settings, $raw ) {
		if ( ! $raw && isset( $settings['cart_page_template'] ) && $settings['cart_page_template'] == 'no_qty' ) {
			$product_id      = cart_id_to_product_id( $cart_id );
			$products_table .= '<input hidden type="hidden" name="p_id[]" class="product_id" value="' . esc_attr( $cart_id ) . '">';
			$p_price         = product_price( $product_id, 1 );
			if ( ! empty( $p_price ) ) {
				$p_price = price_format( $p_price, 1, 0 );
			}
			$products_table .= '<input class="edit-product-quantity" data-p_id="' . esc_attr( $product_id ) . '" data-price="' . esc_attr( $p_price ) . '" type="hidden" name="p_quantity[]" value="1">';
			$products_table .= '<span class="delete_product"></span>';
		}

		return $products_table;
	}

	function image_td( $products_table, $cart_id, $quantity, $settings ) {
		$product_id = cart_id_to_product_id( $cart_id );
		if ( isset( $settings['cart_page_template'] ) && $settings['cart_page_template'] == 'no_qty' ) {
			$products_table .= '<td class="td-image">' . get_product_image( $product_id ) . '</td>';
		} elseif ( ! empty( $settings['cart_page_image'] ) ) {
			$products_table .= '<td class="td-image">' . $this->product_image( $cart_id ) . '</td>';
		}

		return $products_table;
	}

	function sku_td( $products_table, $cart_id ) {
		if ( is_ic_cart_sku_enabled() ) {
			$product_id      = cart_id_to_product_id( $cart_id );
			$sku             = get_product_sku( $product_id );
			$products_table .= '<td class="td-sku">' . apply_filters( 'ic_cart_product_sku', $sku, $cart_id ) . '</td>';
		}

		return $products_table;
	}

	/**
	 * Removes cart items whose product no longer exists from the stored cart.
	 *
	 * Pruning cart state from here is the established pattern for this method: product_table() already
	 * clears an empty cart. Called once per render with every cart id that could not be drawn.
	 *
	 * @param array  $cart_ids Cart identifiers whose product is gone.
	 * @param string $cart     Cart name.
	 *
	 * @return string Encoded cart content after the removal, for the caller to render.
	 */
	function drop_unavailable_products( $cart_ids, $cart = 'cart_content' ) {
		if ( empty( $cart_ids ) ) {
			return ic_cart_get( $cart );
		}
		$cart_content = ic_decode_json_cart( ic_cart_get( $cart ) );
		if ( ! is_array( $cart_content ) ) {
			return ic_cart_get( $cart );
		}
		$changed = false;
		foreach ( $cart_ids as $cart_id ) {
			if ( isset( $cart_content[ $cart_id ] ) ) {
				unset( $cart_content[ $cart_id ] );
				$changed = true;
			}
		}
		if ( ! $changed ) {
			return ic_cart_get( $cart );
		}
		if ( empty( $cart_content ) ) {
			ic_cart_clear( $cart );

			return '';
		}
		$encoded = ic_cart_save( $cart_content, $cart );
		do_action( 'ic_cart_updated', $cart_content );
		do_action(
			'ic_cart_event',
			array(
				'cart'      => $cart,
				'cart_size' => ic_cart_stats_size( $cart_content ),
				'stage'     => 'remove',
			)
		);

		return $encoded;
	}

	function name_td( $products_table, $cart_id, $quantity, $settings, $raw ) {
		$product_id = cart_id_to_product_id( $cart_id );
		$permalink  = get_product_url( $product_id );
		if ( ! empty( $permalink ) && ! ic_cart_product_is_available( $product_id ) ) {
			// A trashed product has no pretty permalink: get_permalink() falls back to
			// ?post_type=al_product&p=ID, which is a dead link for the customer. Fall through to the
			// plain-name branch below instead of linking to it.
			$permalink = '';
		}
		if ( ! empty( $permalink ) ) {
			$product_name_url = '<a href="' . $permalink . '">' . get_product_name( $product_id ) . '</a>';
		} else {
			$product_name_url = get_product_name( $product_id );
		}
		if ( $raw ) {
			$filter_name = 'cart_summary_product_name';
		} else {
			$filter_name = 'cart_product_name';
		}
		$name_cell = apply_filters( $filter_name, $product_name_url, $cart_id, $product_id );
		// The delete controls MUST stay DIRECT children of td.td-name: ic/cart/js/cart-page.js:76 finds
		// the hidden quantity input with a single jQuery( this ).parent().find( '.edit-product-quantity' )
		// hop from the delete span, with no .closest(). Wrapping them in an inner element silently breaks
		// cart removal. delete_button() self-guards to the non-raw no_qty template.
		$name_cell      .= $this->delete_button( '', $cart_id, $quantity, $settings, $raw );
		$products_table .= apply_filters( 'cart_summary_product_name_td', '<td class="td-name">' . $name_cell . '</td>', $product_id, $cart_id );

		return $products_table;
	}

	function qty_td( $products_table, $cart_id, $quantity, $settings, $raw, $product_price ) {
		if ( isset( $settings['cart_page_template'] ) && $settings['cart_page_template'] == 'qty' ) {
			$product_id = cart_id_to_product_id( $cart_id );
			if ( $raw ) {
				$raw_quantity     = $quantity;
				$display_quantity = apply_filters( 'shopping_cart_submit_raw_quantity', $raw_quantity, $cart_id );
				$quantity         = '<input hidden type="hidden" name="p_id[]" class="product_id" value="' . esc_attr( $cart_id ) . '">';
				$quantity        .= '<input hidden type="hidden" class="cart-raw-product-quantity" name="p_quantity[]" value="' . esc_attr( $raw_quantity ) . '">';
				$quantity        .= $display_quantity;
			} else {
				$qty_field = '<input hidden type="hidden" name="p_id[]" class="product_id" value="' . esc_attr( $cart_id ) . '"><span class="delete_product" p_id="' . esc_attr( $product_id ) . '"></span> ';
				$min       = apply_filters( 'ic_cart_min_qty', 0, $product_id, $cart_id );
				$max       = apply_filters( 'ic_cart_max_qty', '', $product_id, $cart_id );
				$p_price   = '';
				if ( ! empty( $product_price ) ) {
					$p_price = price_format( $product_price, 1, 0 );
				}
				$qty_box    = '<input class="edit-product-quantity" data-p_id="' . esc_attr( $product_id ) . '" data-price="' . esc_attr( $p_price ) . '" type="number" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="1" name="p_quantity[]" value="' . esc_attr( $quantity ) . '">';
				$qty_field .= apply_filters( 'shopping_cart_quantity_box', $qty_box, $cart_id );
				$quantity   = $qty_field;
			}
			$products_table .= apply_filters( 'cart_summary_product_qty_td', '<td class="td-qty">' . $quantity . '</td>', $quantity, $cart_id, $product_id, $raw );
		}

		return $products_table;
	}

	function price_td( $products_table, $cart_id, $quantity, $settings, $raw, $product_price ) {
		$product_id = cart_id_to_product_id( $cart_id );
		if ( $product_price !== '' && $product_price !== false ) {
			$price   = apply_filters( 'shopping_cart_product_price_second_mod', $product_price, $cart_id, $quantity );
			$p_total = apply_filters( 'ic_cart_product_total_calc', floatval( $price ) * $quantity, $cart_id );
			$this->calculation( $p_total, $price, $quantity, $cart_id );
			if ( function_exists( 'is_ic_order_taxed' ) && is_ic_order_taxed() ) {
				ic_cart_update_tax( $product_id, $p_total, $quantity, $cart_id );
			}
			if ( isset( $settings['cart_page_template'] ) && $settings['cart_page_template'] == 'qty' && apply_filters( 'ic_cart_price_columns', true ) ) {
				$products_table .= '<td class="td-price">';
				$products_table .= apply_filters( 'ic_cart_td_price', price_format( $price, 1, 0 ), $cart_id );
				$products_table .= '</td>';
			}
			$products_table .= apply_filters( 'ic_cart_checkout_additional_columns', '', $product_id, $cart_id, $quantity, $raw );
			if ( apply_filters( 'ic_cart_price_columns', true ) ) {
				if ( $raw ) {
					$products_table .= '<td class="td-total"><input type="hidden" hidden name="product_price_' . sanitize_title( $cart_id ) . '" value="' . price_format( $price, 1, 0 ) . '">' . apply_filters( 'ic_cart_td_total', price_format( $p_total, 1, 0 ), $cart_id ) . '</td>';
				} else {
					$products_table .= '<td class="' . $product_id . '_total product_total td-total">' . apply_filters( 'ic_cart_td_total', price_format( $p_total, 1, 0 ), $cart_id );
					$sep_settings    = get_currency_settings();
					$products_table .= '<input type="hidden" hidden class="dec_sep" value="' . $sep_settings['dec_sep'] . '" /><input type="hidden" hidden class="th_sep" value="' . $sep_settings['th_sep'] . '" />';
					$products_table .= '</td>';
				}
			}
		} else {
			$products_table .= apply_filters( 'ic_cart_checkout_additional_columns', '', $product_id, $cart_id, $quantity, $raw );
		}

		return $products_table;
	}

	function calculation( $p_total, $price, $quantity, $cart_id ) {
		$already_calculated = ic_get_global( 'current_cart_calculated' );
		if ( empty( $already_calculated ) ) {
			$already_calculated = array();
		}
		if ( ! in_array( $cart_id, $already_calculated ) ) {
			ic_save_global( 'current_cart_total', ic_get_global( 'current_cart_total' ) + $p_total );
			$already_calculated[] = $cart_id;
			ic_save_global( 'current_cart_calculated', $already_calculated );
			do_action( 'ic_add_cart_row_calculation', $p_total, $price, $quantity, $cart_id );
		}
	}

	function price_summary( $products_table, $price, $cart, $settings ) {
		if ( ! empty( $price ) && ( $cart === 'cart_content' || $cart === 'quote_cart_content' ) ) {
			global $ic_shopping_cart_totals;
			$sep_settings = get_currency_settings();
			if ( function_exists( 'get_cart_tax_rate' ) ) {
				$tax_rate = get_cart_tax_rate();
			}
			$total = apply_filters( 'ic_cart_order_total', ic_get_global( 'current_cart_total' ) );
			if ( function_exists( 'ic_cart_get_tax' ) ) {
				$tax = ic_cart_get_tax();
			} else {
				$tax = 0;
			}
			if ( function_exists( 'is_ic_tax_included' ) && is_ic_tax_included() && ! empty( $total ) ) {
				$total = $total - $tax;
			}
			$currency = product_currency();
			$label    = $settings['summary_total_label'];
			if ( ! empty( $tax_rate['tax_rate'] ) ) {
				$label = $settings['summary_total_net_label'];
			}
			$products_table                  .= '<tr class="section_sep ic-cart-summary-row">';
			$colspan                          = ic_cart_colspan( $settings );
			$products_table                  .= '<td colspan="' . $colspan . '"></td><td></td></tr>';
			$ic_shopping_cart_totals['total'] = $total;
			$ic_shopping_cart_totals['tax']   = $tax;
			$total_net                        = price_format( $total, 1, 0 );
			$products_table                  .= apply_filters( 'ic_before_cart_total', '', $total, $tax );
			if ( ! empty( $total_net ) ) {
				$products_table .= '<tr class="new_section ic-cart-summary-row ic-cart-total-net-row"><td colspan="' . $colspan . '" class="currency-td"><input type="hidden" name="currency_code" value="' . get_product_currency_code() . '"><input type="hidden" hidden name="total_net" value="' . apply_filters( 'ic_cart_calc_total_net', price_format( $total, 1, 0 ) ) . '" />' . apply_filters( 'ic_cart_total_net_label', $label, $price, $cart, $settings ) . '</td><td class="total_net">' . $currency . apply_filters( 'cart_table_total_net', $total_net, $total ) . '</td></tr>';
			}
			if ( ! empty( $tax_rate['tax_rate'] ) ) {
				$current_cart_tax = ic_cart_get_tax( true );
				if ( is_array( $current_cart_tax ) ) {
					foreach ( $current_cart_tax as $rate => $tax_sum ) {
						$products_table .= '<tr class="order-checkout-tax ic-cart-summary-row ic-cart-total-tax-row"><td colspan="' . $colspan . '" class="currency-vat">' . $tax_rate['tax_label'] . ' ' . floatval( $rate ) . '% = ' . '</td><td class="total_vat">' . $currency . apply_filters( 'cart_table_total_tax', price_format( $tax_sum, 1, 0 ), $tax_sum ) . '</td></tr>';
					}
				}
				$total_with_tax  = $total + $tax;
				$products_table .= '<tr class="section_sep ic-cart-summary-row"><td td colspan="' . $colspan . '"></td><td></td></tr>';
				$products_table .= '<tr class="new_section ic-cart-summary-row ic-cart-total-gross-row"><td colspan="' . $colspan . '" class="currency-gross"><input type="hidden" hidden name="total_with_tax" value="' . apply_filters( 'ic_cart_calc_total', price_format( $total_with_tax, 1, 0 ) ) . '" />' . $settings['summary_total_gross_label'] . '</td><td class="total_gross">' . $currency . apply_filters( 'cart_table_total_gross', price_format( $total_with_tax, 1, 0 ), $total_with_tax ) . '</td></tr>';
			}
			$products_table .= apply_filters( 'ic_after_cart_total', '', $total, $tax, $cart, $settings );
		}

		return $products_table;
	}

	function product_image( $cart_id ) {
		$image_html = apply_filters( 'ic_cart_product_image', $cart_id );
		if ( empty( $image_html ) ) {
			$product_id = cart_id_to_product_id( $cart_id );
			$image_html = get_product_image( $product_id );
		}

		return $image_html;
	}

	function product_price( $product_id, $cart_id, $quantity ) {
		$product_price = apply_filters( 'shopping_cart_product_price', product_price( $product_id, 1 ), $cart_id, $quantity );

		return $product_price;
	}

	function content( $json = null, $cart = 'cart_content' ) {
		// $cart_content = '';
		$cart_content = array();
		global $cart_just_created;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( ! empty( $_POST['add_cart_data'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			parse_str( wp_unslash( $_POST['add_cart_data'] ), $params );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			$_POST = array_merge( $_POST, ic_cart_sanitize_parsed_data( $params ) );
		}
		if ( $json ) {
			$submitted_cart_content = $this->submitted_content( $cart );
			if ( null !== $submitted_cart_content ) {
				if ( $this->should_persist_submitted_content( $cart ) ) {
					ic_cart_save( $submitted_cart_content, $cart );
				}

				return $submitted_cart_content;
			}
		}
		if ( ! isset( $cart_just_created[ $cart ] ) && $json ) {
			$save_cart = false;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			if ( isset( $_GET['cart_user_input'] ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
					$url_a = url_to_array( wp_unslash( $_GET['cart_user_input'] ) );
					$url_a = is_array( $url_a ) ? ic_cart_sanitize_parsed_data( $url_a ) : array();
				if ( is_array( $url_a ) ) {
					foreach ( $url_a as $key => $value ) {
						$_POST[ $key ] = $value;
					}
				}
				// $_POST = url_to_array( $_GET[ 'cart_user_input' ] );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			$cart_name = isset( $_POST['ic_cart_name'] ) ? sanitize_key( wp_unslash( $_POST['ic_cart_name'] ) ) : $cart;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			if ( isset( $_POST['p_id'] ) && $cart === $cart_name ) {
				$product_variations_settings = get_product_variations_settings();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning form or AJAX boundary verifies its action-specific nonce; structured cart IDs are unslashed once and validated below without changing accepted values.
				$posted_product_ids = wp_unslash( $_POST['p_id'] );
				$posted_product_ids = is_array( $posted_product_ids ) ? $posted_product_ids : array();
				foreach ( $posted_product_ids as $num => $product_id ) {
					if ( ! is_scalar( $product_id ) ) {
						continue;
					}
					$product_id = (string) $product_id;
					if ( sanitize_text_field( $product_id ) !== $product_id || ! preg_match( '/^[A-Za-z0-9:_> -]+$/', $product_id ) ) {
						continue;
					}
					$product_id                 = get_cart_id_without_variations( $product_id );
					$current_product_variations = get_current_product_variations_string( $product_id, $product_variations_settings );
					if ( $current_product_variations ) {
						// $product_id    = create_variation_id( $cart_content, $product_id, $current_product_variations );
						$product_id .= $current_product_variations;
					}
					/*
						for ( $i = 1; $i <= $_POST[ 'p_quantity' ][ $num ]; $i++ ) {
						$cart_content .= $product_id . ',';
						}
					 */
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
					$product_qty = isset( $_POST['p_quantity'][ $num ] ) ? absint( wp_unslash( $_POST['p_quantity'][ $num ] ) ) : 0;
					if ( ! empty( $product_qty ) ) {
						$cart_content[ $product_id ] = $product_qty;
					}
				}
				do_action( 'ic_cart_updated', $cart_content );
				do_action(
					'ic_cart_event',
					array(
						'cart'      => $cart,
						'cart_size' => ic_cart_stats_size( $cart_content ),
						'stage'     => 'update',
					)
				);
				/*
				foreach ( $_POST[ 'p_id' ] as $num => $_product_id ) {
					$product_id        = strval( $_product_id );
					$quantity      = intval( $_POST[ 'p_quantity' ][ $num ] );
					$cart_content  = ic_cart_insert( $product_id, $quantity, 'cart_content' );
					} */
				$save_cart = true;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream cart payload is processed.
			} elseif ( isset( $_POST[ $cart ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$current_product    = isset( $_POST['current_product'] ) ? apply_filters( 'ic_adding_id_to_cart', absint( wp_unslash( $_POST['current_product'] ) ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
				$additional_product = isset( $_POST['additional_product'] ) ? ic_cart_sanitize_parsed_data( wp_unslash( $_POST['additional_product'] ) ) : array();
				$current_product_id = $current_product;
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$current_quantity   = isset( $_POST['current_quantity'] ) ? absint( wp_unslash( $_POST['current_quantity'] ) ) : 1;
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Quote-cart payload is part of the active frontend cart request.
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The secure form or AJAX boundary verifies its nonce; the structured cart is unslashed once and normalized by the field-specific parser.
				$posted_cart_content  = wp_unslash( $_POST[ $cart ] );
				$session_cart_content = ic_cart_get( $cart );
				$cart_content         = ic_decode_json_cart( $posted_cart_content );
				if ( empty( $cart_content ) ) {
					$cart_content = ic_decode_json_cart( $session_cart_content );
				}
				$product_variations_settings = get_product_variations_settings();

				$current_product_variations = get_current_product_variations_string( $current_product_id, $product_variations_settings );
				$current_product            = create_variation_id( $cart_content, $current_product_id, $current_product_variations );

				if ( $current_product_variations ) {
					$current_product .= $current_product_variations;
				}
				/*
					if ( $current_quantity > 1 ) {
					$current_products = '';
					for ( $i = 1; $i <= $current_quantity; $i++ ) {
					$current_products .= ',' . $current_product;
					}
					$current_product = $current_products;
					}
				 */
				if ( $current_product != '' ) {
					// $cart_content = $cart_content . ',' . $current_product;
					if ( isset( $cart_content[ $current_product ] ) ) {
						$cart_content[ $current_product ] += $current_quantity;
					} else {
						$cart_content[ $current_product ] = $current_quantity;
					}
					do_action( 'ic_cart_added', $current_product, $cart_content, $current_quantity );
					do_action(
						'ic_cart_event',
						array(
							'cart'      => $cart,
							'cart_size' => ic_cart_stats_size( $cart_content ),
							'stage'     => 'add',
						)
					);
					if ( ! empty( $additional_product ) ) {
						foreach ( $additional_product as $additional_product_id ) {
							$current_additional_product_variations = get_current_product_variations_string( $additional_product_id, $product_variations_settings );
							$additional_product_id                 = create_variation_id( $cart_content, $additional_product_id, $current_additional_product_variations );
							if ( $current_additional_product_variations ) {
								$additional_product_id .= $current_additional_product_variations;
							}
							if ( isset( $cart_content[ $additional_product_id ] ) ) {
								$cart_content[ $additional_product_id ] += 1;
							} else {
								$cart_content[ $additional_product_id ] = 1;
							}
							do_action( 'ic_cart_added', $additional_product_id, $cart_content, 1 );
							do_action(
								'ic_cart_event',
								array(
									'cart'      => $cart,
									'cart_size' => ic_cart_stats_size( $cart_content ),
									'stage'     => 'add',
								)
							);
						}
					}
				} else {
					$cart_content = $cart_content;
				}
				$save_cart = true;
			} elseif ( is_ic_cart_initialized( $cart ) ) {
				$cart_content = ic_decode_json_cart( ic_cart_get( $cart ) );
			}
			/*
			else if ( isset( $_POST[ 'current_product' ] ) ) {
				$product_id    = intval( $_POST[ 'current_product' ] );
				$quantity  = 1;
				if ( $_POST[ 'current_quantity' ] ) {
				$quantity = intval( $_POST[ 'current_quantity' ] );
				}
				$cart_content = ic_cart_insert( $product_id, $quantity, 'cart_content' );
				} else {
				$cart_content = ic_cart_get( 'cart_content' );
				} */
			// $cart_content  = implode( ',', array_filter( explode( ',', $cart_content ) ) );
			$cart_content = ic_filter_empty_array_values( $cart_content );
			$cart_content = $this->filter( $cart_content, null, $cart );
				// Checkout submit forms post `cart_type`, but the posted cart still needs to reach the session
				// before other submit-time session writers run.
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$persist_checkout_cart = isset( $_POST['cart_type'] ) && isset( $_POST[ $cart ] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			if ( $save_cart && ( ! isset( $_POST['cart_type'] ) || $persist_checkout_cart ) ) {
				// $_SESSION[ $cart ] = ic_encode_string_cart( $cart_content );
				ic_cart_save( ic_encode_string_cart( $cart_content ), $cart );
				// session_write_close();
			}
			if ( $json ) {
				$cart_content = ic_encode_string_cart( $cart_content );
				if ( ! is_array( $cart_just_created ) ) {
					$cart_just_created = array();
				}
				$cart_just_created[ $cart ] = $cart_content;
			}
		} else {
			$cart_content = $cart_just_created[ $cart ];
		}

		return $cart_content;
	}

	/**
	 * Returns submitted cart content for active checkout submit requests.
	 *
	 * @param string|null $cart Cart bucket name.
	 *
	 * @return string|null
	 */
	function submitted_content( $cart = null ) {
		if ( empty( $cart ) || ! is_string( $cart ) ) {
			return null;
		}

		$cart    = sanitize_key( $cart );
		$sources = array();

		if ( function_exists( 'get_submitted_form_submit_name' ) && function_exists( 'get_submitted_form_content_name' ) ) {
			$submit_name = sanitize_key( get_submitted_form_submit_name() );
			$cart_name   = sanitize_key( get_submitted_form_content_name() );
			if ( ! empty( $submit_name ) && ! empty( $cart_name ) ) {
				$sources[] = array(
					'submit_name' => $submit_name,
					'cart_name'   => $cart_name,
				);
			}
		}
		if ( 'quote_cart_content' === $cart ) {
			$sources[] = array(
				'submit_name' => 'quote_cart_submit',
				'cart_name'   => 'quote_cart_content',
			);
		} elseif ( 'cart_content' === $cart ) {
			$sources[] = array(
				'submit_name' => 'cart_submit',
				'cart_name'   => 'cart_content',
			);
		}

		foreach ( $sources as $source ) {
			if ( $cart !== $source['cart_name'] ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Active checkout submit payload is read directly to avoid mutating session state during cart reads.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The secure checkout form boundary verifies its action nonce before this request-shape check.
			if ( ! empty( $_POST[ $source['submit_name'] ] ) && array_key_exists( $source['cart_name'], $_POST ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Active checkout submit payload is read directly to avoid mutating session state during cart reads.
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The secure checkout boundary verifies its nonce; the structured cart is unslashed once and normalized by the field-specific parser.
				return $this->sanitize_submitted_cart_content( wp_unslash( $_POST[ $source['cart_name'] ] ) );
			}
		}

		return null;
	}

	/**
	 * Checks whether submitted cart content should also be persisted to session.
	 *
	 * @param string|null $cart Cart bucket name.
	 *
	 * @return bool
	 */
	function should_persist_submitted_content( $cart = null ) {
		if ( empty( $cart ) || ! is_string( $cart ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( empty( $_POST['cart_type'] ) ) {
			return false;
		}

		$cart    = sanitize_key( $cart );
		$sources = array();
		if ( function_exists( 'get_submitted_form_submit_name' ) && function_exists( 'get_submitted_form_content_name' ) ) {
			$submit_name = sanitize_key( get_submitted_form_submit_name() );
			$cart_name   = sanitize_key( get_submitted_form_content_name() );
			if ( ! empty( $submit_name ) && ! empty( $cart_name ) ) {
				$sources[] = array(
					'submit_name' => $submit_name,
					'cart_name'   => $cart_name,
				);
			}
		}
		if ( 'quote_cart_content' === $cart ) {
			$sources[] = array(
				'submit_name' => 'quote_cart_submit',
				'cart_name'   => 'quote_cart_content',
			);
		} elseif ( 'cart_content' === $cart ) {
			$sources[] = array(
				'submit_name' => 'cart_submit',
				'cart_name'   => 'cart_content',
			);
		}

		foreach ( $sources as $source ) {
			if ( $cart !== $source['cart_name'] ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Active checkout submit payload is read directly to persist the current checkout cart.
			if ( ! empty( $_POST[ $source['submit_name'] ] ) && array_key_exists( $source['cart_name'], $_POST ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validates and sanitizes submitted checkout cart JSON.
	 *
	 * @param mixed $submitted_cart_source Raw submitted cart payload.
	 *
	 * @return string
	 */
	function sanitize_submitted_cart_content( $submitted_cart_source ) {
		if ( ! is_scalar( $submitted_cart_source ) ) {
			return '';
		}

		$submitted_cart_source = wp_unslash( (string) $submitted_cart_source );
		if ( '' === $submitted_cart_source || ! is_ic_json_cart( $submitted_cart_source ) ) {
			return '';
		}

		$submitted_cart = json_decode( $submitted_cart_source, true );
		if ( ! is_array( $submitted_cart ) ) {
			return '';
		}

		$sanitized_cart = array();
		foreach ( $submitted_cart as $cart_id => $quantity ) {
			$cart_id = (string) $cart_id;
			if ( sanitize_text_field( $cart_id ) !== $cart_id ) {
				continue;
			}
			if ( ! preg_match( '/^[A-Za-z0-9:_> -]+$/', $cart_id ) ) {
				continue;
			}
			if ( is_bool( $quantity ) || ! is_numeric( $quantity ) ) {
				continue;
			}

			$quantity = absint( $quantity );
			if ( empty( $quantity ) ) {
				continue;
			}

			$sanitized_cart[ $cart_id ] = $quantity;
		}

		if ( empty( $sanitized_cart ) ) {
			return '';
		}

		return wp_json_encode( $sanitized_cart );
	}

	function filter( $cart_content, $find_value = null, $cart = null ) {
		if ( ! empty( $cart_content ) ) {
			$cart_content = ic_cart_products_array( $cart_content, $cart );
			if ( ! isset( $find_value ) ) {
				foreach ( $cart_content as $value => $quantity ) {
					$no_var_value = get_cart_id_without_variation_id( $value );
					if ( $no_var_value == $value ) {
						continue;
					}
					foreach ( $cart_content as $sub_value => $sub_quantity ) {
						$var_value = ic_filter_empty_array_values( get_variation_lp_from_cart_id( $sub_value ) );
						if ( empty( $var_value ) ) {
							continue;
						}
						$no_var_sub_value = get_cart_id_without_variation_id( $sub_value );
						// if ( $value != $sub_value && strpos( strval( $no_var_value ), strval( $no_var_sub_value ) ) !== false ) {
						if ( $value != $sub_value && strval( $no_var_value ) === strval( $no_var_sub_value ) ) {
							unset( $cart_content[ $value ] );
							$cart_content[ $sub_value ] = $quantity + $sub_quantity;
							break;
						}
					}
				}

				return apply_filters( 'filter_ic_cart', $cart_content );
			} else {
				foreach ( $cart_content as $sub_value => $sub_quantity ) {
					if ( $find_value != $sub_value && strpos( $find_value, $sub_value ) !== false ) {
						$find_value = $sub_value;
						break;
					}
				}

				return $find_value;
			}
		}

		return apply_filters( 'filter_ic_cart_empty', $cart_content );
	}

	/**
	 * Adds min qty field on product edit/add page in product details box
	 *
	 * @param string $box_content
	 * @param int    $product_id
	 *
	 * @return string
	 */
	function tax_meta( $box_content, $product_id ) {
		if ( function_exists( 'is_ic_tax_per_product' ) && is_ic_tax_per_product() ) {
			$tax_rate     = ic_get_tax_rate( $product_id );
			$box_content .= '<table><tr><td class="label-column">' . __( 'Tax Rate', 'ecommerce-product-catalog' ) . ':</td><td class="sku-column"><input style="width:130px" type="number" name="_tax_rate" value="' . $tax_rate . '" class="widefat" step="0.01" />%</td></tr></table>';
		}

		return $box_content;
	}

	/**
	 * Saves product tax
	 *
	 * @param type $product_meta
	 *
	 * @return type
	 */
	function save_tax( $product_meta ) {
		if ( ! function_exists( 'get_cart_tax_rate' ) ) {
			return $product_meta;
		}
		$default                   = get_cart_tax_rate( 'rate' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		$product_meta['_tax_rate'] = ! empty( $_POST['_tax_rate'] ) || ( isset( $_POST['_tax_rate'] ) && $_POST['_tax_rate'] === '0' ) ? floatval( $_POST['_tax_rate'] ) : '';
		if ( $product_meta['_tax_rate'] === $default ) {
			unset( $product_meta['_tax_rate'] );
		}

		return $product_meta;
	}
}

global $ic_cart;
$ic_cart = new ic_cart();

function ic_cart_products( $raw = 1, $price = true, $cart = 'cart_content', $settings = null ) {
	/**
	 * @var ic_cart $ic_cart
	 */
	global $ic_cart;

	return $ic_cart->product_table( $raw, $price, $cart, $settings );
}

function ic_cart_total( $cart = 'cart_content' ) {
	$total = ic_get_global( 'current_cart_total' );
	if ( $total === false ) {
		global $ic_shopping_cart_totals;
		if ( ! empty( $ic_shopping_cart_totals['total'] ) ) {
			$total = $ic_shopping_cart_totals['total'];
		} else {
			ic_cart_products( 1, true, $cart );
			$total = ic_get_global( 'current_cart_total' );
		}
	}
	if ( $total === false ) {
		$total = 0;
	}

	return $total;
}

if ( ! function_exists( 'get_shopping_cart_product_price' ) ) {

	function get_shopping_cart_product_price( $product_id, $cart_id, $quantity ) {
		global $ic_cart;

		return $ic_cart->product_price( $product_id, $cart_id, $quantity );
	}

}

function ic_cart_content( $json = null, $cart = 'cart_content' ) {
	global $ic_cart;
	return $ic_cart->content( $json, $cart );
}

/**
 * Returns cart content in array cart_id => qty
 *
 * @param type $cart_content
 * @param type $cart
 *
 * @return type
 */
function ic_cart_products_array( $cart_content = null, $cart = null ) {
	if ( $cart_content == '' ) {
		$cart_content = ic_cart_content( true, $cart );
	}
	$product_array = $cart_content;
	if ( ! empty( $cart_content ) ) {
		if ( is_ic_json_cart( $cart_content ) ) {
			$product_array = json_decode( $cart_content, true );
		} elseif ( ! is_array( $cart_content ) ) {
			$product_array = array_count_values( explode( ',', $cart_content ) );
		}
	} else {
		$product_array = array();
	}

	return $product_array;
}

if ( ! function_exists( 'ic_cart_product_group_from_product_id' ) ) {
	/**
	 * Resolves a product group from a concrete product ID.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return string
	 */
	function ic_cart_product_group_from_product_id( $product_id ) {
		$product_id = absint( $product_id );
		if ( empty( $product_id ) ) {
			return '';
		}

		$post_type = get_post_type( $product_id );
		if ( empty( $post_type ) || ! is_string( $post_type ) ) {
			return '';
		}

		return $post_type;
	}
}

if ( ! function_exists( 'ic_cart_product_group_from_cart_content' ) ) {
	/**
	 * Resolves the effective product group from any cart payload.
	 *
	 * @param string|array|null $cart_content Cart payload.
	 * @param string            $cart         Cart key.
	 *
	 * @return string
	 */
	function ic_cart_product_group_from_cart_content( $cart_content = null, $cart = 'cart_content' ) {
		if ( '' === $cart_content || null === $cart_content ) {
			$cart_content = ic_cart_content( true, $cart );
		}

		$product_array = ic_cart_products_array( $cart_content, $cart );
		if ( empty( $product_array ) || ! is_array( $product_array ) ) {
			return '';
		}

		foreach ( array_keys( $product_array ) as $cart_id ) {
			$product_group = ic_cart_product_group_from_product_id( cart_id_to_product_id( $cart_id ) );
			if ( ! empty( $product_group ) ) {
				return $product_group;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'ic_checkout_product_group' ) ) {
	/**
	 * Resolves the current checkout product group.
	 *
	 * Prefers explicit request context, then cart payloads, then the current product.
	 *
	 * @param array|null $request  Optional request payload.
	 * @param string     $fallback Default product group.
	 *
	 * @return string
	 */
	function ic_checkout_product_group( $request = null, $fallback = 'al_product' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		$request = is_array( $request ) ? $request : $_POST;

		if ( ! empty( $request['product_group'] ) ) {
			return sanitize_text_field( wp_unslash( $request['product_group'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads signed gateway return payloads.
		if ( ! empty( $_GET['user_input'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads signed gateway return payloads.
			$user_input = sanitize_text_field( wp_unslash( $_GET['user_input'] ) );
			$parsed     = url_to_array( $user_input );
			if ( ! empty( $parsed['product_group'] ) ) {
				return sanitize_text_field( $parsed['product_group'] );
			}
		}

		foreach ( array( 'cart_content', 'quote_cart_content', 'replace_cart_content' ) as $cart_name ) {
			if ( isset( $request[ $cart_name ] ) ) {
				$product_group = ic_cart_product_group_from_cart_content( wp_unslash( $request[ $cart_name ] ), $cart_name );
				if ( ! empty( $product_group ) ) {
					return $product_group;
				}
			}
		}

		foreach ( array( 'cart_content', 'quote_cart_content', 'replace_cart_content' ) as $cart_name ) {
			$product_group = ic_cart_product_group_from_cart_content( null, $cart_name );
			if ( ! empty( $product_group ) ) {
				return $product_group;
			}
		}

		if ( ! empty( $request['current_product'] ) ) {
			$product_group = ic_cart_product_group_from_product_id( $request['current_product'] );
			if ( ! empty( $product_group ) ) {
				return $product_group;
			}
		}

		$current_product_post_type = apply_filters( 'current_product_post_type', '' );
		if ( ! empty( $current_product_post_type ) && is_string( $current_product_post_type ) ) {
			return $current_product_post_type;
		}

		return $fallback;
	}
}

function ic_decode_json_cart( $cart_content, $count = false, $filter = false ) {
	if ( is_ic_json_cart( $cart_content ) ) {
		$json_content = json_decode( $cart_content, true );
		$item_count   = 0;
		if ( $filter ) {
			$cart_content = array();
			global $ic_cart;
			foreach ( $json_content as $value => $quantity ) {

				$value = $ic_cart->filter( $json_content, $value );

				/*
					for ( $i = 1; $i <= $quantity; $i++ ) {
					if ( !empty( $cart_content ) ) {
					$cart_content .= ',';
					}
					$cart_content .= $value;
					}
				 */
				$cart_content[ $value ] = $quantity;
				$item_count            += $quantity;
			}
		} else {
			$cart_content = $json_content;
			if ( empty( $cart_content ) ) {
				$item_count = 0;
			} else {
				$item_count = count( $cart_content );
			}
		}
		if ( $count ) {
			$temp_content['content'] = $cart_content;
			$temp_content['count']   = $item_count;
			$cart_content            = $temp_content;
		}
	} elseif ( $count ) {
		if ( ! is_array( $cart_content ) ) {
			$cart_content = array();
		}
		$content_array           = ic_filter_empty_array_values( $cart_content );
		$item_count              = count( $content_array );
		$temp_content['content'] = $cart_content;
		$temp_content['count']   = $item_count;
		$cart_content            = $temp_content;
	}
	if ( is_string( $cart_content ) || empty( $cart_content ) ) {
		$cart_content = array();
	}

	return $cart_content;
}

function ic_encode_string_cart( $cart_content, $filter = false ) {
	if ( ! empty( $cart_content ) && ! is_ic_json_cart( $cart_content ) ) {
		$cart_content = ic_cart_products_array( $cart_content );
		if ( $filter ) {
			global $ic_cart;
			$cart_content = $ic_cart->filter( $cart_content );
		}

		$cart_content = json_encode( $cart_content );
	}
	if ( empty( $cart_content ) ) {
		$cart_content = '';
	}

	return $cart_content;
}

if ( ! function_exists( 'cart_id_to_product_id' ) ) {

	function cart_id_to_product_id( $cart_id ) {
		if ( ! is_scalar( $cart_id ) ) {
			return 0;
		}
		$product_id = (string) $cart_id;
		if ( strpos( $product_id, '::' ) !== false ) {
			$temp       = explode( '::', $product_id );
			$temp       = explode( '_', $temp[1] );
			$product_id = $temp[0];
		} elseif ( strpos( $product_id, '_' ) !== false ) {
			$temp       = explode( '_', $product_id );
			$product_id = $temp[0];
		}

		return intval( $product_id );
	}

}


if ( ! function_exists( 'ic_cart_colspan' ) ) {

	/**
	 * Returns the checkout table colspan: the full body row width MINUS the trailing total cell.
	 *
	 * The summary rows emit this colspan plus one trailing `<td>`, and the empty-state row emits
	 * `colspan + 1`, so the value must stay exactly one less than the number of cells a body row
	 * renders. The base counts the columns the template always draws, less that trailing cell;
	 * the SKU and image columns are then added on exactly the conditions their own renderers use.
	 *
	 * @param array $settings Cart display settings.
	 *
	 * @return int
	 */
	function ic_cart_colspan( $settings ) {
		$is_no_qty = isset( $settings['cart_page_template'] ) && $settings['cart_page_template'] == 'no_qty';
		// qty: name + qty + unit price + total, less the trailing total cell.
		$def_colspan = 3;
		if ( $is_no_qty ) {
			// no_qty draws neither the qty cell nor the unit-price cell, and the delete controls no
			// longer occupy their own cell (they render inside td-name), so the raw (submit page) and
			// non-raw no_qty rows are now the same width and the former is_ic_shopping_order()
			// distinction is obsolete. That leaves the name cell plus the trailing total cell.
			$def_colspan = 1;
			if ( function_exists( 'is_ic_quote_pricing_enabled' ) && ! is_ic_quote_pricing_enabled() ) {
				// Quote pricing off: price_td() draws no total cell at all, so only the name cell is
				// left and there is nothing to subtract for.
				--$def_colspan;
			}
		}
		if ( is_ic_cart_sku_enabled() ) {
			++$def_colspan;
		}
		// image_td() draws the image cell UNCONDITIONALLY on the no_qty template and only when
		// cart_page_image is set on qty. The increment has to follow that same condition, or the
		// derived width disagrees with the rendered row whenever no_qty runs with the image off.
		if ( $is_no_qty || ! empty( $settings['cart_page_image'] ) ) {
			++$def_colspan;
		}

		$colspan = apply_filters( 'ic_cart_checkout_table_colspan', $def_colspan );

		return $colspan;
	}

}
