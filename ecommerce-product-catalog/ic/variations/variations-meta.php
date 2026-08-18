<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages framework folder
 *
 * Here framework folder files defined and managed.
 *
 * @version     1.0.0
 * @package     ecommerce-product-catalog-pro/framework
 * @author      Norbert Dreszer
 */
class ic_cart_variations_meta {

	function __construct() {
		add_action( 'add_product_metaboxes', array( $this, 'add_metaboxes' ) );
		add_filter( 'product_meta_save', array( $this, 'save' ) );
	}

	/**
	 * Adds product variations metabox
	 */
	function add_metaboxes() {
		$product_variations_settings = get_product_variations_settings();
		if ( ! empty( $product_variations_settings['count'] ) ) {
			add_meta_box( 'al_cart_variations', __( 'Product Variations', 'ecommerce-product-catalog' ), array( __CLASS__, 'meta_box' ), 'al_product', 'normal', 'default' );
		}
	}

	static function meta_box() {
		global $post;
		$product_variations_settings = get_product_variations_settings();
		?>
		<div class="al-box info"><?php esc_html_e( 'Only variations with values set will be shown on product page.', 'ecommerce-product-catalog' ); ?></div>
		<div style="max-width: 100%;overflow: auto;">
			<table class="variations">
				<thead>
					<tr>
						<th></th>
						<th class="title"><b><?php esc_html_e( 'Name', 'ecommerce-product-catalog' ); ?></b></th>
						<th></th>
						<th class="title"><b><?php esc_html_e( 'Values', 'ecommerce-product-catalog' ); ?></b></th>
						<?php
						if ( ic_is_variations_qc_sc_enabled() ) {
							do_action( 'ic_variations_metabox_ths', $post->ID );
						}
						$mod_drop = false;
						if ( ic_is_variations_price_effect_active() ) {
							$mod_drop = true;
							?>
							<th class="title"><b><?php esc_html_e( 'Price mod', 'ecommerce-product-catalog' ); ?></b></th>
							<?php
						}
						if ( ic_is_variations_shipping_effect_active() ) {
							$mod_drop = true;
							?>
							<th class="title"><b><?php esc_html_e( 'Shipping mod', 'ecommerce-product-catalog' ); ?></b></th>
							<?php
						}
						?>
						<?php if ( ic_is_variations_qc_sc_enabled() ) { ?>
							<th class="title"><b><?php esc_html_e( 'Details', 'ecommerce-product-catalog' ); ?></b></th>
						<?php } ?>
						<?php
						$mod_drop = apply_filters( 'ic_variations_mod_drop_enabled', $mod_drop );
						if ( $mod_drop ) {
							?>
							<th><th>
								<?php
						}
						?>
						<th class="dragger"><th>
					</tr>
				</thead>
				<tbody>
					<?php
					for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
						$variation_label                             = get_post_meta( $post->ID, $i . '_variation_label', true );
						$variation_values                            = get_post_meta( $post->ID, $i . '_variation_values', true );
						$variation_prices                            = get_post_meta( $post->ID, $i . '_variation_prices', true );
						$variation_shipping                          = get_post_meta( $post->ID, $i . '_variation_shipping', true );
						$variation_mod                               = ic_variation_mod_type( $post->ID, $i );
						$product_variations_settings['labels'][ $i ] = isset( $product_variations_settings['labels'][ $i ] ) ? $product_variations_settings['labels'][ $i ] : '';
						$product_variations_settings['values'][ $i ] = isset( $product_variations_settings['values'][ $i ] ) ? $product_variations_settings['values'][ $i ] : '';
						if ( ic_is_variations_price_effect_active() ) {
							$product_variations_settings['prices'][ $i ] = isset( $product_variations_settings['prices'][ $i ] ) ? $product_variations_settings['prices'][ $i ] : '';
						}
						if ( ic_is_variations_shipping_effect_active() ) {
							$product_variations_settings['shipping'][ $i ] = isset( $product_variations_settings['shipping'][ $i ] ) ? $product_variations_settings['shipping'][ $i ] : '';
						}
						if ( is_ic_new_product_screen() ) {
							$variation_label  = ! empty( $variation_label ) ? $variation_label : $product_variations_settings['labels'][ $i ];
							$variation_values = ! empty( $variation_values ) ? $variation_values : $product_variations_settings['values'][ $i ];
							if ( ic_is_variations_price_effect_active() ) {
								$variation_prices = ! empty( $variation_prices ) ? $variation_prices : $product_variations_settings['prices'][ $i ];
							}
							if ( ic_is_variations_shipping_effect_active() ) {
								$variation_shipping = ! empty( $variation_shipping ) ? $variation_shipping : $product_variations_settings['shipping'][ $i ];
							}
						}
						$variation_values = is_array( $variation_values ) ? $variation_values : explode( "\r\n", $variation_values );
						$values_fields    = '';
						$prices_fields    = '';
						$shipping_fields  = '';
						$edit_buttons     = '';
						if ( empty( $variation_values ) ) {
							$variation_values[] = '';
						}
						if ( ic_is_variations_price_effect_active() ) {
							$variation_prices = is_array( $variation_prices ) ? $variation_prices : explode( "\r\n", $variation_prices );
						}
						if ( ic_is_variations_shipping_effect_active() ) {
							$variation_shipping = is_array( $variation_shipping ) ? $variation_shipping : explode( "\r\n", $variation_shipping );
						}

						foreach ( $variation_values as $key => $variation_value ) {
							if ( ! empty( $key ) && ( empty( $variation_value ) && ! is_numeric( $variation_value ) ) ) {
								continue;
							}
							$values_fields .= '<input type="text" data-var_num="' . $i . '" data-var_lp="' . $key . '" name="' . $i . '_variation_values[]" value="' . esc_attr( $variation_value ) . '">';
							if ( ic_is_variations_price_effect_active() ) {
								$variation_prices[ $key ] = isset( $variation_prices[ $key ] ) ? $variation_prices[ $key ] : '';
								$prices_fields           .= '<input class="admin-number-field" type="number" step="0.01" name="' . absint( $i ) . '_variation_prices[]" value="' . esc_attr( $variation_prices[ $key ] ) . '">';
							}
							if ( ic_is_variations_shipping_effect_active() ) {
								$variation_shipping[ $key ] = isset( $variation_shipping[ $key ] ) ? $variation_shipping[ $key ] : '';
								$shipping_fields           .= '<input class="admin-number-field" type="number" step="0.01" name="' . absint( $i ) . '_variation_shipping[]" value="' . esc_attr( $variation_shipping[ $key ] ) . '">';
							}
							if ( ic_is_variations_qc_sc_enabled() && $i == 1 ) {
								$var_edit_box = ic_variation_details_edit( $post->ID, $i, $key );
								if ( ! empty( $var_edit_box ) ) {
									$edit_buttons .= '<div class="variations-details-container" data-var_num="' . esc_attr( $i ) . '" data-var_lp="' . esc_attr( $key ) . '"><div class="button button-small edit_variation">' . esc_html__( 'Edit', 'ecommerce-product-catalog' ) . '</div>' . $var_edit_box . '</div>';
								}
							}
						}
						?>
							<tr class="<?php echo esc_attr( apply_filters( 'ic_cat_variation_tr_class', '', $post->ID, $i ) ); ?>" <?php echo wp_kses_post( apply_filters( 'ic_cat_variation_tr_attr', '', $post->ID, $i ) ); ?>>
							<td class="lp-column">
									<?php echo esc_html( $i ); ?>.
							</td>
							<td class="product-variation-label-column">
									<input class="product-variation-label" type="text" data-var_num="<?php echo esc_attr( $i ); ?>" name="<?php echo esc_attr( $i ); ?>_variation_label" value="<?php echo esc_attr( $variation_label ); ?>"/>
							</td>
							<td class="lp-column">:</td>
							<td class="variation_values">
									<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated input controls have escaped dynamic attributes above; post KSES would remove the controls. ?>
									<?php echo $values_fields; ?>
							</td>
							<?php
							if ( ic_is_variations_qc_sc_enabled() ) {
								do_action( 'ic_variations_metabox_tds', $variation_values, $post->ID, $i );
							}
							if ( ic_is_variations_price_effect_active() ) {
								?>
								<td class="variation_prices">
										<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated input controls have escaped dynamic attributes above; post KSES would remove the controls. ?>
										<?php echo $prices_fields; ?>
								</td>
								<?php
							}
							if ( ic_is_variations_shipping_effect_active() ) {
								?>
								<td class="variation_shipping">
										<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated input controls have escaped dynamic attributes above; post KSES would remove the controls. ?>
										<?php echo $shipping_fields; ?>
								</td>
								<?php
							}
							?>
						<?php if ( ic_is_variations_qc_sc_enabled() ) { ?>
							<td class="variation_details">
									<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Variation detail controls are generated by trusted constructors with escaped dynamic leaves; post KSES removes the controls. ?>
									<?php echo $edit_buttons; ?>
							</td>
							<?php } ?>
							<?php
							if ( $mod_drop ) {
								?>
									<td class="variation_modificator">
										<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Modifier select is generated from fixed options and an integer-derived field name; escaping the complete select would remove the admin control. ?>
										<?php echo self::get_price_modificator_dropdown( $i . '_variation_mod', $variation_mod ); ?>
									</td>
								<?php
							}
							?>
							<td style="vertical-align: top;padding-top: 13px;min-width: 200px;">
								<div style="vertical-align:middle;" class="button button-small add_variation"><span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Value', 'ecommerce-product-catalog' ); ?></div>
								<?php
								if ( ic_is_variations_qc_sc_enabled() ) {
									do_action( 'ic_cat_shopping_variation_buttons', $post->ID, $i );
								}
								?>
							</td>
							<td class="dragger"></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
		if ( ic_is_variations_qc_sc_enabled() ) {
			do_action( 'ic_variations_metabox', $post->ID );
		}
	}

	static function get_price_modificator_dropdown( $name, $value ) {
		$drop  = '<select class="price_modificator" name="' . $name . '">';
		$drop .= '<option value="+" ' . selected( '+', $value, 0 ) . '>+</option>';
		$drop .= '<option value="%" ' . selected( '%', $value, 0 ) . '>%</option>';
		if ( ic_is_variations_qc_sc_enabled() ) {
			$drop .= '<option value="=" ' . selected( '=', $value, 0 ) . '>=</option>';
		}
		$drop .= '</select>';
		return $drop;
	}

	function save( $product_meta ) {
		$product_variations_settings = get_product_variations_settings();
		for ( $i = 1; $i <= $product_variations_settings['count']; $i++ ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			if ( ! empty( $_POST[ $i . '_variation_values' ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$product_meta[ $i . '_variation_label' ]    = isset( $_POST[ $i . '_variation_label' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $i . '_variation_label' ] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$product_meta[ $i . '_variation_values' ]   = ! empty( $_POST[ $i . '_variation_values' ] ) && is_array( $_POST[ $i . '_variation_values' ] ) ? $this->filter_variation_values( array_map( 'sanitize_text_field', wp_unslash( $_POST[ $i . '_variation_values' ] ) ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$product_meta[ $i . '_variation_prices' ]   = isset( $_POST[ $i . '_variation_prices' ] ) && is_array( $_POST[ $i . '_variation_prices' ] ) ? $this->filter_variation_values( array_map( 'sanitize_text_field', wp_unslash( $_POST[ $i . '_variation_prices' ] ) ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$product_meta[ $i . '_variation_shipping' ] = isset( $_POST[ $i . '_variation_shipping' ] ) && is_array( $_POST[ $i . '_variation_shipping' ] ) ? $this->filter_variation_values( array_map( 'sanitize_text_field', wp_unslash( $_POST[ $i . '_variation_shipping' ] ) ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$product_meta[ $i . '_variation_mod' ]      = isset( $_POST[ $i . '_variation_mod' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $i . '_variation_mod' ] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
				$product_meta[ $i . '_variation_type' ]     = isset( $_POST[ $i . '_variation_type' ] ) ? sanitize_key( wp_unslash( $_POST[ $i . '_variation_type' ] ) ) : 'dropdown';
				$product_meta                               = apply_filters( 'save_product_variations', $product_meta, $i );
			}
		}
		return $product_meta;
	}

	function filter_variation_values( $values ) {
		// $values        = explode( "\r\n", rtrim( $values ) );
		$new_values = array();
		foreach ( $values as $value ) {
			$value        = str_replace( array( ', ', '_', '::' ), array( '.', ' ', ':' ), $value );
			$new_values[] = $value;
		}
		return $new_values;
	}
}

global $ic_cart_variations_meta;
$ic_cart_variations_meta = new ic_cart_variations_meta();
