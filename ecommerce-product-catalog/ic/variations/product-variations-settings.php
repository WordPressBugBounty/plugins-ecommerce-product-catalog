<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages product variations
 *
 * Here product variations functions are defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */
function product_variations_menu() {
	?>
	<a id="product-variations-settings" class="element"
		   href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=attributes-settings&submenu=product-variations' ) ); ?>"><?php esc_html_e( 'Product Variations', 'ecommerce-product-catalog' ); ?> </a><?php
}

add_action( 'attributes_submenu', 'product_variations_menu' );

function product_variations_settings() {
	register_setting( 'product_variations', 'product_variations_settings', array( 'sanitize_callback' => 'ic_sanitize_product_variations_settings' ) );
	//do_action( 'product_variations_settings' );
}

/**
 * Settings keys whose values are newline-delimited lists, one entry per variation option.
 *
 * @return string[]
 */
function ic_variation_multiline_setting_keys() {
	return array( 'values', 'prices', 'shipping' );
}

/**
 * Sanitizes one level of the variation settings tree.
 *
 * $parent_key is tracked because the multiline keys hold an array of textareas
 * ( $settings['values'][1] ), so the key that says "this is a textarea" belongs to the
 * level above the string being sanitized. It is deliberately NOT a second parameter of
 * ic_sanitize_product_variations_settings(): that function is a register_setting()
 * sanitize_callback, and WordPress runs it through sanitize_option_{$option}, which
 * passes the option name as the second filter argument.
 *
 * @param array  $settings   Submitted settings level.
 * @param string $parent_key Key of the level above, '' at the root.
 * @return array
 */
function ic_sanitize_product_variations_branch( $settings, $parent_key ) {
	$multiline = ic_variation_multiline_setting_keys();
	$clean     = array();
	foreach ( $settings as $key => $value ) {
		if ( is_array( $value ) ) {
			$clean[ $key ] = ic_sanitize_product_variations_branch( $value, (string) $key );
			continue;
		}
		if ( ! in_array( $parent_key, $multiline, true ) ) {
			$clean[ $key ] = sanitize_text_field( (string) $value );
			continue;
		}
		// sanitize_text_field() collapses every \r\n into a space, which silently merged all
		// options of a variation into one entry ("Red\r\nGreen" became "Red Green") the first
		// time this screen was saved. These lists must keep their line breaks.
		$value = sanitize_textarea_field( (string) $value );
		if ( 'prices' === $parent_key || 'shipping' === $parent_key ) {
			$value = ic_normalize_variation_price_list( $value );
		}
		$clean[ $key ] = $value;
	}

	return $clean;
}

/**
 * Stores a newline-delimited price list in canonical dot-decimal form.
 *
 * The per-product variation fields are <input type="number">, so they always submit
 * dot-decimal. This screen uses free-text textareas instead, so on a store configured with
 * a comma decimal separator an admin can type "10,50" here. Left alone it reaches the
 * price arithmetic as a string and is read as 10.0, so it is parsed once, on the way in,
 * with the same helper both _price save paths use.
 *
 * @param string $value Newline-delimited list.
 * @return string
 */
function ic_normalize_variation_price_list( $value ) {
	if ( '' === $value || ! is_callable( array( 'ic_price_display', 'raw_price_format' ) ) ) {
		return $value;
	}
	$eol   = ( false !== strpos( $value, "\r\n" ) ) ? "\r\n" : "\n";
	$lines = explode( $eol, $value );
	foreach ( $lines as $i => $line ) {
		$trimmed = trim( $line );
		// A blank line keeps this list aligned with the values list, and an already canonical
		// number is left byte-identical so saving the screen never rewrites correct data.
		if ( '' === $trimmed || is_numeric( $trimmed ) ) {
			continue;
		}
		$number = ic_price_display::raw_price_format( $trimmed );
		if ( is_numeric( $number ) ) {
			$lines[ $i ] = rtrim( rtrim( number_format( (float) $number, 6, '.', '' ), '0' ), '.' );
		}
	}

	return implode( $eol, $lines );
}

/**
 * Sanitizes variation settings recursively without dropping extension keys.
 *
 * @param mixed $settings Submitted settings.
 * @return mixed
 */
function ic_sanitize_product_variations_settings( $settings ) {
	if ( ! is_array( $settings ) ) {
		return array();
	}
	$clean = ic_sanitize_product_variations_branch( $settings, '' );
	if ( isset( $clean['count'] ) ) {
		$clean['count'] = min( 100, absint( $clean['count'] ) );
	}
	foreach ( array( 'quantity', 'separate_sku', 'empty-enabled', 'hide_unavailable' ) as $flag ) {
		if ( isset( $clean[ $flag ] ) ) {
			$clean[ $flag ] = empty( $clean[ $flag ] ) ? '' : '1';
		}
	}
	return $clean;
}

add_action( 'product-settings-list', 'product_variations_settings' );

function get_product_variations_settings() {
	$product_variations_settings = get_option( 'product_variations_settings' );
	if ( ! is_array( $product_variations_settings ) ) {
		$product_variations_settings = array();
	}
	$product_variations_settings['count']            = isset( $product_variations_settings['count'] ) ? $product_variations_settings['count'] : 2;
	$product_variations_settings['labels']           = isset( $product_variations_settings['labels'] ) ? $product_variations_settings['labels'] : array();
	$product_variations_settings['values']           = isset( $product_variations_settings['values'] ) ? $product_variations_settings['values'] : array();
	$product_variations_settings['prices']           = isset( $product_variations_settings['prices'] ) ? $product_variations_settings['prices'] : array();
	$product_variations_settings['shipping']         = isset( $product_variations_settings['shipping'] ) ? $product_variations_settings['shipping'] : array();
	$product_variations_settings['type']             = isset( $product_variations_settings['type'] ) ? $product_variations_settings['type'] : array();
	$product_variations_settings['mod']              = isset( $product_variations_settings['mod'] ) ? $product_variations_settings['mod'] : array();
	$product_variations_settings['mode']             = isset( $product_variations_settings['mode'] ) ? $product_variations_settings['mode'] : 'normal';
	$product_variations_settings['info']             = isset( $product_variations_settings['info'] ) ? $product_variations_settings['info'] : 'full-price';
	$product_variations_settings['quantity']         = isset( $product_variations_settings['quantity'] ) ? $product_variations_settings['quantity'] : '';
	$product_variations_settings['separate_sku']     = isset( $product_variations_settings['separate_sku'] ) ? $product_variations_settings['separate_sku'] : '';
	$product_variations_settings['empty-enabled']    = isset( $product_variations_settings['empty-enabled'] ) ? $product_variations_settings['empty-enabled'] : '';
	$product_variations_settings['hide_unavailable'] = isset( $product_variations_settings['hide_unavailable'] ) ? $product_variations_settings['hide_unavailable'] : '';

	return apply_filters( 'product_variations_settings', $product_variations_settings );
}

function product_variations_settings_content() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
	$submenu = isset( $_GET['submenu'] ) ? sanitize_key( wp_unslash( $_GET['submenu'] ) ) : '';
	if ( $submenu == 'product-variations' ) {
		?>
		<script>
            jQuery('.settings-submenu a').removeClass('current');
            jQuery('.settings-submenu a#product-variations-settings').addClass('current');
		</script>
		<div class="product-variations-settings setting-content submenu">
			<form method="post" action="options.php">
				<h2><?php esc_html_e( 'Product Variations Settings', 'ecommerce-product-catalog' ); ?></h2>
				<?php
				settings_fields( 'product_variations' );
				$product_variations_settings = get_product_variations_settings();
				?>
				<table>
					<tr>
						<td colspan="2"><?php esc_html_e( 'Number of product variations', 'ecommerce-product-catalog' ); ?> <input
								size="30" type="number" step="1" min="0" name="product_variations_settings[count]"
								id="admin-number-field"
								value="<?php echo esc_attr( $product_variations_settings['count'] ); ?>"/><input type="submit"
						                                                                             class="button"
						                                                                             value="<?php esc_html_e( 'Update', 'ecommerce-product-catalog' ); ?>"/>
						</td>
					</tr>
				</table><?php
				if ( $product_variations_settings['count'] > 0 ) {
					$available_var_types = ic_variation_available_types();
					echo '<table>';
					implecode_settings_radio( __( 'Variations mode', 'ecommerce-product-catalog' ), 'product_variations_settings[mode]', $product_variations_settings['mode'], array( 'normal'       => __( 'Normal', 'ecommerce-product-catalog' ),
					                                                                                                                                                                'product_name' => __( 'Product name from variations', 'ecommerce-product-catalog' )
					) );
					if ( ic_is_variations_price_effect_active() ) {
						implecode_settings_radio( __( 'Variation price info', 'ecommerce-product-catalog' ), 'product_variations_settings[info]', $product_variations_settings['info'], array( 'full-price'   => __( 'Show full price if possible to calculate', 'ecommerce-product-catalog' ),
						                                                                                                                                                                     'price-effect' => __( 'Show price effect only', 'ecommerce-product-catalog' ),
						                                                                                                                                                                     'no-info'      => __( 'Disable variation price info', 'ecommerce-product-catalog' )
						) );
					}
					if ( ic_is_variations_qc_sc_enabled() ) {
						implecode_settings_checkbox( __( 'Enable Variation Quantity', 'ecommerce-product-catalog' ), 'product_variations_settings[quantity]', $product_variations_settings['quantity'], 1, __( 'Each product variation will have a separate quantity selector.', 'ecommerce-product-catalog' ) );
						implecode_settings_checkbox( __( 'Allow Empty Variations', 'ecommerce-product-catalog' ), 'product_variations_settings[empty-enabled]', $product_variations_settings['empty-enabled'], 1, __( 'The user will be able to leave the variation unselected.', 'ecommerce-product-catalog' ) );
						if ( function_exists( 'is_ic_cart_sku_enabled' ) && is_ic_cart_sku_enabled() ) {
							implecode_settings_checkbox( __( 'SKU for each variation', 'ecommerce-product-catalog' ), 'product_variations_settings[separate_sku]', $product_variations_settings['separate_sku'], 1, __( 'Each product variation will have a separate SKU field.', 'ecommerce-product-catalog' ) );
						}
						implecode_settings_checkbox( __( 'Hide unavailable variation options', 'ecommerce-product-catalog' ), 'product_variations_settings[hide_unavailable]', $product_variations_settings['hide_unavailable'], 1, __( 'Unavailable options will be hidden. By default the unavailable options are only disabled.', 'ecommerce-product-catalog' ) );
					}
					echo '</table>';
					?>
					<style>.product-variations-settings table td textarea {
                            min-height: 100px;
                        }</style>
					<div class="al-box info">
						<p><?php esc_html_e( "If you fill out the fields below, system will automatically pre-fill the fields on product pages so you doesn't have to fill them every time you add product.", 'ecommerce-product-catalog' ); ?></p><p><?php esc_html_e( 'When every product in your catalogue is different you can leave all or a part of these field empty.', 'ecommerce-product-catalog' ); ?></p>
						<p><?php esc_html_e( 'You can change these default values on every product page.', 'ecommerce-product-catalog' ); ?></p>
					</div>
					<table class="wp-list-table widefat product-settings-table">
					<thead>
					<tr>
						<th class="title"></th>
						<th class="title"><b><?php esc_html_e( 'Variation default name', 'ecommerce-product-catalog' ); ?></b>
						</th>
						<th></th>
						<th class="title"><b><?php esc_html_e( 'Variation default values', 'ecommerce-product-catalog' ); ?></b>
						</th>
						<?php
						$mod_drop = false;
						if ( ic_is_variations_price_effect_active() ) {
							$mod_drop = true;
							?>
							<th class="title">
								<b><?php esc_html_e( 'Variation default price effects', 'ecommerce-product-catalog' ); ?></b></th>
						<?php } ?>
						<?php
						if ( ic_is_variations_shipping_effect_active() ) {
							$mod_drop = true;
							?>
							<th class="title">
								<b><?php esc_html_e( 'Variation default shipping effects', 'ecommerce-product-catalog' ); ?></b>
							</th>
							<?php
						}
						if ( ic_is_variations_qc_sc_enabled() && ! empty( $available_var_types ) && is_array( $available_var_types ) && count( $available_var_types ) > 1 ) {
							?>
							<th><b><?php esc_html_e( 'Variation default type', 'ecommerce-product-catalog' ); ?></b></th>
							<?php
						}
						if ( $mod_drop ) {
							?>
							<th></th>
						<?php }
						?>

					</tr>
					</thead>
					<tbody><?php
					for ( $i = 1; $i <= $product_variations_settings['count']; $i ++ ) {
						$product_variations_settings['labels'][ $i ]   = isset( $product_variations_settings['labels'][ $i ] ) ? $product_variations_settings['labels'][ $i ] : '';
						$product_variations_settings['values'][ $i ]   = isset( $product_variations_settings['values'][ $i ] ) ? $product_variations_settings['values'][ $i ] : '';
						$product_variations_settings['prices'][ $i ]   = isset( $product_variations_settings['prices'][ $i ] ) ? $product_variations_settings['prices'][ $i ] : '';
						$product_variations_settings['shipping'][ $i ] = isset( $product_variations_settings['shipping'][ $i ] ) ? $product_variations_settings['shipping'][ $i ] : '';
						$product_variations_settings['type'][ $i ]     = isset( $product_variations_settings['type'][ $i ] ) ? $product_variations_settings['type'][ $i ] : 'dropdown';
						$product_variations_settings['mod'][ $i ]      = isset( $product_variations_settings['mod'][ $i ] ) ? $product_variations_settings['mod'][ $i ] : '+';
						?>
							<tr>
								<td class="lp-column lp<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?>.</td>
							<td class="product-variation-label-column"><input class="product-variation-label"
							                                                  type="text"
								name="product_variations_settings[labels][<?php echo esc_attr( $i ); ?>]"
								value="<?php echo esc_attr( $product_variations_settings['labels'][ $i ] ); ?>"/>
							</td>
							<td class="lp-column">:</td>
							<td><textarea
										name="product_variations_settings[values][<?php echo esc_attr( $i ); ?>]"><?php echo esc_textarea( $product_variations_settings['values'][ $i ] ); ?></textarea>
							</td>
							<?php
							if ( ic_is_variations_price_effect_active() ) {
								?>
								<td><textarea
											name="product_variations_settings[prices][<?php echo esc_attr( $i ); ?>]"><?php echo esc_textarea( $product_variations_settings['prices'][ $i ] ); ?></textarea>
								</td>
							<?php } ?>
							<?php
							if ( ic_is_variations_shipping_effect_active() ) {
								?>
								<td><textarea
											name="product_variations_settings[shipping][<?php echo esc_attr( $i ); ?>]"><?php echo esc_textarea( $product_variations_settings['shipping'][ $i ] ); ?></textarea>
								</td>
								<?php
							}
							if ( ic_is_variations_qc_sc_enabled() && ! empty( $available_var_types ) && is_array( $available_var_types ) && count( $available_var_types ) > 1 ) {
								?>
								<td style="padding-left: 3%">
									<?php
									implecode_settings_radio( '', 'product_variations_settings[type][' . $i . ']', $product_variations_settings['type'][ $i ], $available_var_types )
									?>
								</td>
								<?php
							}
							if ( $mod_drop ) {
								?>
									<td>
										<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper generates a select from component-escaped field data and fixed options; post KSES would remove the control. ?>
										<?php echo ic_cart_variations_meta::get_price_modificator_dropdown( 'product_variations_settings[mod][' . absint( $i ) . ']', $product_variations_settings['mod'][ $i ] ); ?>
									</td>
							<?php } ?>
						</tr> <?php }
					?>
					</tbody>
					</table><?php
				} else {
					?>
					<table>
						<tr>
							<td colspan="2">
								<div
									class="al-box warning"><?php esc_html_e( 'Product Variations disabled. To enable set minimum 1 variation.', 'ecommerce-product-catalog' ); ?></div>
							</td>
						</tr>
					</table> <?php
				}
				do_action( 'product-variations-settings' );
				?>
				<p class="submit">
					<input type="submit" class="button-primary"
					       value="<?php esc_html_e( 'Save changes', 'ecommerce-product-catalog' ); ?>"/>
				</p>

			</form>
		</div>
		<?php
	}
}

add_action( 'product-attributes', 'product_variations_settings_content' );
