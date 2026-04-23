<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages product price
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog/includes
 * @author        impleCode
 */
add_filter( 'ic_epc_design_schemes_table_rows', 'ic_price_design_schemes', 10, 3 );

/**
 * Adds price design scheme rows.
 *
 * @param array $rows Existing design scheme rows.
 * @param array $design_schemes Current design schemes.
 *
 * @return array
 */
function ic_price_design_schemes( $rows, $design_schemes ) {
	ic_register_setting( __( 'Price Size', 'ecommerce-product-catalog' ), 'design_schemes[price-size]' );
	ic_register_setting( __( 'Price Color', 'ecommerce-product-catalog' ), 'design_schemes[price-color]' );

	$rows[] = array(
		'type'  => 'cells',
		'cells' => array(
			array(
				'content' => esc_html__( 'Price Size', 'ecommerce-product-catalog' ),
			),
			array(
				'content' => ic_price_design_schemes_size_select_html( $design_schemes ),
			),
			array(
				'content' => ic_price_design_schemes_example_html(),
				'class'   => 'price-value example ' . ic_price_design_schemes_example_class(),
				'rowspan' => 2,
			),
			array(
				'content' => esc_html__( 'single product', 'ecommerce-product-catalog' ),
			),
		),
	);
	$rows[] = array(
		'type'  => 'cells',
		'cells' => array(
			array(
				'content' => esc_html__( 'Price Color', 'ecommerce-product-catalog' ),
			),
			array(
				'content' => ic_price_design_schemes_color_select_html( $design_schemes ),
			),
			array(
				'content' => esc_html__( 'single product', 'ecommerce-product-catalog' ) . ', ' . esc_html__( 'product archive', 'ecommerce-product-catalog' ),
			),
		),
	);

	return $rows;
}

/**
 * Returns the price size select HTML.
 *
 * @param array $design_schemes Current design schemes.
 *
 * @return string
 */
function ic_price_design_schemes_size_select_html( $design_schemes ) {
	$price_size = isset( $design_schemes['price-size'] ) ? $design_schemes['price-size'] : '';

	return implecode_settings_dropdown(
		'',
		'design_schemes[price-size]',
		$price_size,
		array(
			'big-price'   => __( 'Big', 'ecommerce-product-catalog' ),
			'small-price' => __( 'Small', 'ecommerce-product-catalog' ),
		),
		0,
		'id="single_price_size"'
	);
}

/**
 * Returns the price color select HTML.
 *
 * @param array $design_schemes Current design schemes.
 *
 * @return string
 */
function ic_price_design_schemes_color_select_html( $design_schemes ) {
	$price_color = isset( $design_schemes['price-color'] ) ? $design_schemes['price-color'] : '';

	return implecode_settings_dropdown(
		'',
		'design_schemes[price-color]',
		$price_color,
		array(
			'red-price'    => __( 'Red', 'ecommerce-product-catalog' ),
			'orange-price' => __( 'Orange', 'ecommerce-product-catalog' ),
			'green-price'  => __( 'Green', 'ecommerce-product-catalog' ),
			'blue-price'   => __( 'Blue', 'ecommerce-product-catalog' ),
			'grey-price'   => __( 'Grey', 'ecommerce-product-catalog' ),
		),
		0,
		'id="single_price_color"'
	);
}

/**
 * Returns the example price HTML.
 *
 * @return string
 */
function ic_price_design_schemes_example_html() {
	ob_start();
	do_action( 'example_price' );

	return trim( ob_get_clean() );
}

/**
 * Returns the example price cell class.
 *
 * @return string
 */
function ic_price_design_schemes_example_class() {
	ob_start();
	design_schemes();

	return trim( ob_get_clean() );
}

add_filter( 'ic_epc_single_names_table_rows', 'ic_price_single_names', 40, 2 );

/**
 * Adds price product page label rows.
 *
 * @param array $rows Existing single label rows.
 * @param array $single_names Single labels.
 *
 * @return array
 */
function ic_price_single_names( $rows, $single_names ) {
	$new_rows = array(
		array(
			'type'  => 'text',
			'label' => __( 'Price Label', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[product_price]',
			'value' => $single_names['product_price'],
		),
		array(
			'type'  => 'text',
			'label' => __( 'Free Product Text', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[free]',
			'value' => $single_names['free'],
		),
		array(
			'type'  => 'text',
			'label' => __( 'After Price Text', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[after_price]',
			'value' => $single_names['after_price'],
		),
	);

	array_splice( $rows, 1, 0, $new_rows );

	return $rows;
}
add_filter( 'ic_epc_general_settings_page_sections', 'ic_price_settings', 10, 2 );

/**
 * Adds the price settings section to the general settings page.
 *
 * @param array $sections General settings sections.
 *
 * @return array
 */
function ic_price_settings( $sections ) {
	$product_currency_settings = get_currency_settings();

	$sections[] = array(
		'title'       => __( 'Payment and currency', 'ecommerce-product-catalog' ),
		'table_class' => 'IC_Settings_Standard_Table',
		'table_args'  => array(
			'settings' => ic_epc_price_settings_table_settings( $product_currency_settings ),
		),
	);

	return $sections;
}

/**
 * Returns price settings table configuration.
 *
 * @param array $product_currency_settings Currency settings.
 *
 * @return array
 */
function ic_epc_price_settings_table_settings( $product_currency_settings ) {
	return array(
		'table_id' => 'payment_table',
		'rows'     => array(
			array(
				'type'    => 'radio',
				'label'   => __( 'Price', 'ecommerce-product-catalog' ),
				'name'    => 'product_currency_settings[price_enable]',
				'value'   => $product_currency_settings['price_enable'],
				'options' => array(
					'on'  => __( 'On', 'ecommerce-product-catalog' ),
					'off' => __( 'Off', 'ecommerce-product-catalog' ),
				),
				'tip'     => __( 'Whether to enable or disable price functionality for the catalog.', 'ecommerce-product-catalog' ),
			),
		),
		'groups'   => array(
			array(
				'class' => 'ic-payment-settings-enabled',
				'rows'  => array(
					array(
						'callback'                  => 'ic_epc_render_payment_settings_table_start_row',
						'product_currency_settings' => $product_currency_settings,
					),
					array(
						'callback'                  => 'ic_epc_render_currency_switcher_row',
						'product_currency_settings' => $product_currency_settings,
					),
					array(
						'type'  => 'text',
						'label' => __( 'Custom Currency Symbol', 'ecommerce-product-catalog' ),
						'name'  => 'product_currency_settings[custom_symbol]',
						'value' => $product_currency_settings['custom_symbol'],
						'class' => 'small_text_box',
						'tip'   => __( 'If you choose a custom currency symbol, it will override Your Currency setting and let you use any currency.', 'ecommerce-product-catalog' ),
					),
					array(
						'type'    => 'radio',
						'label'   => __( 'Currency position', 'ecommerce-product-catalog' ),
						'name'    => 'product_currency_settings[price_format]',
						'value'   => $product_currency_settings['price_format'],
						'options' => array(
							'before' => __( 'Before Price', 'ecommerce-product-catalog' ),
							'after'  => __( 'After Price', 'ecommerce-product-catalog' ),
						),
					),
					array(
						'type'    => 'radio',
						'label'   => __( 'Space between currency & price', 'ecommerce-product-catalog' ),
						'name'    => 'product_currency_settings[price_space]',
						'value'   => $product_currency_settings['price_space'],
						'options' => array(
							'on'  => __( 'On', 'ecommerce-product-catalog' ),
							'off' => __( 'Off', 'ecommerce-product-catalog' ),
						),
					),
					array(
						'type'  => 'text',
						'label' => __( 'Thousands Separator', 'ecommerce-product-catalog' ),
						'name'  => 'product_currency_settings[th_sep]',
						'value' => $product_currency_settings['th_sep'],
						'class' => 'small_text_box',
					),
					array(
						'type'  => 'text',
						'label' => __( 'Decimal Separator', 'ecommerce-product-catalog' ),
						'name'  => 'product_currency_settings[dec_sep]',
						'value' => $product_currency_settings['dec_sep'],
						'class' => 'small_text_box',
					),
					array(
						'callback'                  => 'ic_epc_render_payment_settings_table_end_row',
						'product_currency_settings' => $product_currency_settings,
					),
				),
			),
		),
		'toggle'   => array(
			'selector' => 'input[name="product_currency_settings[price_enable]"]',
			'groups'   => array(
				array(
					'target' => '.ic-payment-settings-enabled',
					'value'  => 'on',
				),
			),
		),
	);
}

/**
 * Renders extension rows before built-in payment rows.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_payment_settings_table_start_row( $row ) {
	do_action( 'payment_settings_table_start' );
}

/**
 * Renders the custom currency switcher row.
 *
 * @return void
 */
function ic_epc_render_currency_switcher_row() {
	?>
	<tr>
		<td>
			<span title="<?php echo esc_attr__( 'Select a currency from the list. If your currency is not available in the list, please use the Custom Currency Symbol option below.', 'ecommerce-product-catalog' ); ?>" class="dashicons dashicons-editor-help ic_tip"></span><?php esc_html_e( 'Your currency', 'ecommerce-product-catalog' ); ?>:
		</td>
		<td><?php echo ic_cat_get_currency_switcher(); ?></td>
	</tr>
	<?php
}

/**
 * Renders extension rows after built-in payment rows.
 *
 * @param array $row Callback row data.
 *
 * @return void
 */
function ic_epc_render_payment_settings_table_end_row( $row ) {
	do_action( 'payment_settings_table_end', $row['product_currency_settings'] );
}

/**
 * Returns currency settings array(th_sep, dec_sep, price_enable)
 *
 * @return array
 */
function get_currency_settings() {
	if ( $product_currency_settings = ic_get_global( 'product_currency_settings' ) ) {
		return $product_currency_settings;
	}
	$product_currency_settings = get_option(
		'product_currency_settings',
		array(
			'custom_symbol' => '$',
			'price_format'  => 'before',
			'price_space'   => 'off',
			'price_enable'  => 'on',
		)
	);
	if ( ! is_array( $product_currency_settings ) ) {
		$product_currency_settings = array();
	}
	foreach ( $product_currency_settings as $settings_key => $settings_value ) {
		if ( ! is_array( $settings_value ) ) {
			$product_currency_settings[ $settings_key ] = sanitize_text_field( $settings_value );
		}
	}
	global $wp_locale;
	$local['mon_thousands_sep']                 = isset( $wp_locale->number_format['thousands_sep'] ) ? $wp_locale->number_format['thousands_sep'] : ',';
	$local['decimal_point']                     = isset( $wp_locale->number_format['decimal_point'] ) ? $wp_locale->number_format['decimal_point'] : '.';
	$product_currency_settings['th_sep']        = isset( $product_currency_settings['th_sep'] ) ? $product_currency_settings['th_sep'] : $local['mon_thousands_sep'];
	$product_currency_settings['dec_sep']       = isset( $product_currency_settings['dec_sep'] ) ? $product_currency_settings['dec_sep'] : $local['decimal_point'];
	$product_currency_settings['price_enable']  = isset( $product_currency_settings['price_enable'] ) ? $product_currency_settings['price_enable'] : 'on';
	$product_currency_settings['custom_symbol'] = isset( $product_currency_settings['custom_symbol'] ) ? $product_currency_settings['custom_symbol'] : '$';
	$product_currency_settings['price_format']  = isset( $product_currency_settings['price_format'] ) ? $product_currency_settings['price_format'] : 'before';
	$product_currency_settings['price_space']   = isset( $product_currency_settings['price_space'] ) ? $product_currency_settings['price_space'] : 'off';
	$product_currency_settings                  = apply_filters( 'product_currency_settings', $product_currency_settings );
	ic_save_global( 'product_currency_settings', $product_currency_settings );

	return $product_currency_settings;
}

function ic_cat_get_currency_switcher( $name = 'product_currency', $product_currency = null ) {
	if ( empty( $product_currency ) ) {
		$product_currency = get_product_currency_code();
	}
	$currency_names = ic_cat_get_currencies();
	ob_start();
	?>
	<select class="ic_chosen" id="product_currency" name="<?php echo $name; ?>" style="width:200px">
		<?php
		$currencies = available_currencies();
		asort( $currencies );
		foreach ( $currencies as $currency ) {
			$currency_name = $currency;
			if ( ! empty( $currency_names[ $currency ] ) ) {
				$currency_name = $currency . ' (' . $currency_names[ $currency ] . '}';
			}
			?>
			<option value="<?php echo $currency; ?>" <?php selected( $currency, $product_currency ); ?>><?php echo $currency_name; ?></option>
			<?php
		}
		?>
	</select>
	<?php
	ic_register_setting( __( 'Your currency', 'ecommerce-product-catalog' ), $name );

	return ob_get_clean();
}

/**
 * Returns product currency code even if the currency symbol is set
 *
 * @return string
 */
function get_product_currency_code( $filtered = true ) {
	$currency = get_option( 'product_currency', 'USD' );
	if ( $filtered ) {
		return apply_filters( 'ic_product_currency_code', $currency );
	} else {
		return $currency;
	}
}
