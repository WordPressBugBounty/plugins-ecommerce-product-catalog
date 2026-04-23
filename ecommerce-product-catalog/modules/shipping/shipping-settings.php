<?php
/**
 * Shipping settings and defaults configuration.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns the shipping settings admin URL.
 *
 * @return string
 */
function ic_epc_get_shipping_settings_url() {
	return admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=shipping-settings&submenu=shipping' );
}

/**
 * Returns the shipping defaults sync controller instance.
 *
 * @return IC_EPC_Defaults_Sync_Controller
 */
function ic_epc_get_shipping_defaults_sync_controller() {
	static $controller = null;
	if ( null === $controller ) {
		$controller = new IC_EPC_Defaults_Sync_Controller(
			array(
				'sync_key'              => 'ic_epc_shipping_defaults_sync',
				'current_mode_callback' => 'get_shipping_source_mode',
				'actions'               => array(
					'option_price_per_product' => array(
						'labels' => array(
							'label' => __( 'Shipping Labels', 'ecommerce-product-catalog' ),
						),
						'prices' => array(
							'label' => __( 'Shipping Prices', 'ecommerce-product-catalog' ),
						),
					),
					'price_per_product'        => array(
						'prices' => array(
							'label' => __( 'Shipping Prices', 'ecommerce-product-catalog' ),
						),
					),
					'global'                   => array(),
				),
				'request_values'        => array(
					'count'  => 'get_shipping_options_number',
					'labels' => 'get_default_shipping_labels',
					'prices' => 'get_default_shipping_costs',
				),
				'meta_mappings'         => array(
					'labels' => array(
						'request_key'     => 'labels',
						'meta_key_prefix' => '_shipping-label',
					),
					'prices' => array(
						'request_key'     => 'prices',
						'meta_key_prefix' => '_shipping',
					),
				),
				'count_option_name'     => 'product_shipping_options_number',
				'settings_url'          => ic_epc_get_shipping_settings_url(),
				'singular_label'        => __( 'shipping', 'ecommerce-product-catalog' ),
				'plural_label'          => __( 'shipping', 'ecommerce-product-catalog' ),
				'dirty_selector'        => 'input[name^="product_shipping_label["], input[name^="product_shipping_cost["], textarea[name^="product_shipping_note["], input[name^="product_shipping_address_collection["], input[name="general_shipping_settings[source_mode]"]',
			)
		);
	}

	return $controller;
}

ic_epc_get_shipping_defaults_sync_controller()->register_hooks();
add_action( 'ic_settings_page_before_form', 'ic_epc_render_shipping_options_count_form', 10, 3 );
add_action( 'ic_settings_page_after_form', 'ic_epc_render_shipping_defaults_sync_settings_form', 10, 3 );
add_action( 'ic_settings_page_sections_end', 'ic_epc_shipping_settings_page_sections_end', 10, 3 );

/**
 * Renders the shipping defaults sync controls.
 *
 * @param string $shipping_mode Current shipping mode.
 * @param string $form_id Optional external form ID.
 *
 * @return void
 */
function ic_render_shipping_defaults_sync_controls( $shipping_mode, $form_id = '' ) {
	ic_epc_get_shipping_defaults_sync_controller()->render_controls( $shipping_mode, $form_id );
}

/**
 * Renders the standalone shipping defaults sync form.
 *
 * @param string $form_id Form ID.
 *
 * @return void
 */
function ic_render_shipping_defaults_sync_form( $form_id ) {
	ic_epc_get_shipping_defaults_sync_controller()->render_form( $form_id );
}

/**
 * Updates the number of shipping options.
 *
 * @param mixed $value Submitted value.
 *
 * @return int
 */
function ic_epc_update_shipping_options_number( $value ) {
	$count = ic_epc_sanitize_shipping_options_number( $value );
	update_option( 'product_shipping_options_number', $count );

	return $count;
}

/**
 * Sanitizes the number of shipping options.
 *
 * @param mixed $value Submitted value.
 *
 * @return int
 */
function ic_epc_sanitize_shipping_options_number( $value ) {
	return max( 0, intval( $value ) );
}

/**
 * Preserves the current array option on partial submits.
 *
 * @param string $option_name Option name.
 * @param mixed  $value Submitted value.
 *
 * @return array
 */
function ic_epc_preserve_shipping_array_option( $option_name, $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}

	$current = get_option( $option_name, array() );
	if ( is_array( $current ) ) {
		return $current;
	}

	return array();
}

/**
 * Sanitizes shipping cost defaults.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_product_shipping_cost( $value ) {
	return ic_epc_preserve_shipping_array_option( 'product_shipping_cost', $value );
}

/**
 * Sanitizes shipping label defaults.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_product_shipping_label( $value ) {
	return ic_epc_preserve_shipping_array_option( 'product_shipping_label', $value );
}

/**
 * Sanitizes general shipping settings.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_general_shipping_settings( $value ) {
	return ic_epc_preserve_shipping_array_option( 'general_shipping_settings', $value );
}

add_filter( 'pre_update_option_product_shipping_note', 'ic_sanitize_shipping_notes', 10, 2 );
add_filter( 'pre_update_option_product_shipping_address_collection', 'ic_sanitize_shipping_address_collection', 10, 2 );

/**
 * Sanitizes saved shipping notes.
 *
 * @param mixed $new_value New option value.
 * @param mixed $old_value Previous option value.
 *
 * @return array
 */
function ic_sanitize_shipping_notes( $new_value, $old_value ) {
	if ( ! is_array( $new_value ) ) {
		return is_array( $old_value ) ? $old_value : array();
	}

	$sanitized = array();
	foreach ( $new_value as $slot => $note ) {
		$slot = intval( $slot );
		if ( $slot < 1 ) {
			continue;
		}

		$sanitized[ $slot ] = sanitize_textarea_field( wp_unslash( $note ) );
	}

	return $sanitized;
}

/**
 * Sanitizes saved shipping-address collection flags.
 *
 * @param mixed $new_value New option value.
 * @param mixed $old_value Previous option value.
 *
 * @return array
 */
function ic_sanitize_shipping_address_collection( $new_value, $old_value ) {
	if ( ! is_array( $new_value ) ) {
		return is_array( $old_value ) ? $old_value : array();
	}

	$sanitized = array();
	foreach ( $new_value as $slot => $enabled ) {
		$slot = intval( $slot );
		if ( $slot < 1 || empty( $enabled ) ) {
			continue;
		}

		$sanitized[ $slot ] = 1;
	}

	return $sanitized;
}

/**
 * Renders a shipping settings table.
 *
 * @param array $args Table arguments.
 */
function ic_render_shipping_settings_table( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'                   => '',
			'shipping_count'          => get_shipping_options_number(),
			'currency'                => '',
			'container_class'         => 'settings-table-container',
			'container_style'         => 'overflow-x: scroll;',
			'table_class'             => 'wp-list-table widefat product-settings-table dragable',
			'label_header'            => esc_html__( 'Shipping default name', 'ecommerce-product-catalog' ),
			'cost_header'             => esc_html__( 'Shipping default cost', 'ecommerce-product-catalog' ),
			'note_header'             => esc_html__( 'Shipping note', 'ecommerce-product-catalog' ),
			'label_name_pattern'      => 'product_shipping_label[%d]',
			'cost_name_pattern'       => 'product_shipping_cost[%d]',
			'note_name_pattern'       => 'product_shipping_note[%d]',
			'show_address_collection' => false,
			'address_header'          => esc_html__( 'Collect shipping address', 'ecommerce-product-catalog' ),
			'address_name_pattern'    => 'product_shipping_address_collection[%d]',
			'address_button_callback' => '',
			'values'                  => array(),
		)
	);

	$shipping_count = intval( $args['shipping_count'] );
	if ( $shipping_count < 1 ) {
		return;
	}

	if ( '' !== $args['title'] ) {
		echo '<h3>' . esc_html( $args['title'] ) . '</h3>';
	}
	echo '<div class="' . esc_attr( $args['container_class'] ) . '"';
	if ( '' !== $args['container_style'] ) {
		echo ' style="' . esc_attr( $args['container_style'] ) . '"';
	}
	echo '>';
	echo '<table class="' . esc_attr( $args['table_class'] ) . '">';
	echo '<thead><tr><th></th><th class="title"><b>' . esc_html( $args['label_header'] ) . '</b></th><th></th><th class="title"><b>' . esc_html( $args['cost_header'] ) . '</b></th><th class="title"><b>' . esc_html( $args['note_header'] ) . '</b></th>';
	if ( ! empty( $args['show_address_collection'] ) ) {
		echo '<th class="title"><b>' . esc_html( $args['address_header'] ) . '</b></th>';
	}
	echo '<th class="dragger"></th></tr></thead><tbody>';
	for ( $i = 1; $i <= $shipping_count; $i++ ) {
			$label           = ic_get_shipping_table_value( $args['values'], 'label', $i, '' );
			$cost            = ic_get_shipping_table_value( $args['values'], 'cost', $i, 0 );
			$note            = ic_get_shipping_table_value( $args['values'], 'note', $i, '' );
			$collect_address = ic_get_shipping_table_value( $args['values'], 'address_collection', $i, '' );
		echo '<tr><td class="lp-column">' . intval( $i ) . '.</td><td class="product-shipping-label-column"><input class="product-shipping-label" type="text" name="' . esc_attr( ic_get_shipping_table_field_name( $args['label_name_pattern'], $i ) ) . '" value="' . esc_attr( $label ) . '" /></td><td class="lp-column">:</td><td><input id="admin-number-field" class="product-shipping-cost" type="number" min="0" step="0.01" name="' . esc_attr( ic_get_shipping_table_field_name( $args['cost_name_pattern'], $i ) ) . '" value="' . esc_attr( $cost ) . '" /> ' . esc_html( $args['currency'] ) . '</td><td class="product-shipping-note-column"><textarea class="large-text" rows="2" name="' . esc_attr( ic_get_shipping_table_field_name( $args['note_name_pattern'], $i ) ) . '">' . esc_textarea( $note ) . '</textarea></td>';
		if ( ! empty( $args['show_address_collection'] ) ) {
			echo '<td class="product-shipping-address-column"><label><input class="ic-shipping-address-toggle" type="checkbox" name="' . esc_attr( ic_get_shipping_table_field_name( $args['address_name_pattern'], $i ) ) . '" value="1" ' . checked( ! empty( $collect_address ), true, false ) . ' /> ' . esc_html__( 'Enable', 'ecommerce-product-catalog' ) . '</label>';
			do_action( 'ic_render_shipping_settings_table_collect_address', $i, $collect_address, $args );
			echo '</td>';
		}
		echo '<td class="dragger"></td></tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Returns the shared shipping settings page instance.
 *
 * @return IC_Settings_Page
 */
function ic_epc_shipping_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$page_settings = ic_epc_shipping_settings_page_settings();

		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'Shipping Settings', 'ecommerce-product-catalog' ),
				'option_group'       => 'product_shipping',
				'option_name'        => 'general_shipping_settings',
				'registered_options' => array(
					'product_shipping_options_number' => array(
						'sanitize_callback' => 'ic_epc_sanitize_shipping_options_number',
					),
					'display_shipping',
					'product_shipping_cost'           => array(
						'sanitize_callback' => 'ic_epc_sanitize_product_shipping_cost',
					),
					'product_shipping_label'          => array(
						'sanitize_callback' => 'ic_epc_sanitize_product_shipping_label',
					),
					'product_shipping_note',
					'product_shipping_address_collection',
					'general_shipping_settings'       => array(
						'sanitize_callback' => 'ic_epc_sanitize_general_shipping_settings',
					),
				),
				'submenu'            => 'shipping',
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'shipping-settings',
				'screen_tab_label'   => __( 'Shipping', 'ecommerce-product-catalog' ),
				'screen_tab_menu_item_id' => 'shipping-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'shipping',
				),
				'screen_tab_content_wrapper_class' => 'shipping-product-settings',
				'screen_tab_content_wrapper_style' => 'clear:both;',
				'screen_submenu_label' => __( 'Shipping Settings', 'ecommerce-product-catalog' ),
				'submenu_item_id'    => 'shipping-settings',
				'container_class'    => 'setting-content submenu',
				'settings'           => $page_settings,
				'content_settings'   => ic_epc_shipping_settings_page_content_settings( $page_settings ),
				'sections'           => ic_epc_shipping_settings_page_sections( $page_settings ),
				'helpers'            => array(
					'ic_epc_main_helper',
				),
				'submit_label'       => __( 'Save changes', 'ecommerce-product-catalog' ),
				'show_submit'        => $page_settings['shipping_count'] > 0,
			)
		);
	}

	return $page;
}

/**
 * Returns the shipping settings used by the shared page renderer.
 *
 * @return array
 */
function ic_epc_shipping_settings_page_settings() {
	return array(
		'shipping_count'    => get_shipping_options_number(),
		'shipping_mode'     => get_shipping_source_mode(),
		'shipping_settings' => get_general_shipping_settings(),
	);
}

/**
 * Returns the shipping page top settings table configuration.
 *
 * @param array $page_settings Page settings.
 *
 * @return array
 */
function ic_epc_shipping_settings_page_content_settings( $page_settings ) {
	$shipping_count = intval( $page_settings['shipping_count'] );
	if ( $shipping_count < 1 ) {
		return array();
	}

	return array(
		'rows' => array(
			array(
				'type'    => 'radio',
				'label'   => __( 'Shipping options source', 'ecommerce-product-catalog' ),
				'name'    => 'general_shipping_settings[source_mode]',
				'value'   => $page_settings['shipping_mode'],
				'options' => ic_get_shipping_source_modes(),
			),
		),
	);
}

/**
 * Returns the shipping page sections.
 *
 * @param array $page_settings Page settings.
 *
 * @return array
 */
function ic_epc_shipping_settings_page_sections( $page_settings ) {
	$sections = array();

	if ( intval( $page_settings['shipping_count'] ) > 0 ) {
		$sections[] = array(
			'title'            => __( 'Shipping Defaults', 'ecommerce-product-catalog' ),
			'settings'         => $page_settings,
			'content_callback' => 'ic_epc_render_shipping_defaults_section',
		);
	} else {
		$sections[] = array(
			'title'       => __( 'Shipping disabled', 'ecommerce-product-catalog' ),
			'description' => __( 'Shipping disabled. To enable set minimum 1 shipping option.', 'ecommerce-product-catalog' ),
			'settings'    => array(),
		);
	}

	$sections = apply_filters( 'ic_epc_shipping_settings_page_sections', $sections, $page_settings );

	return $sections;
}

/**
 * Renders the shipping options count update form.
 *
 * @param string $option_group Option group.
 * @param array  $settings Page settings.
 *
 * @return void
 */
function ic_epc_render_shipping_options_count_form( $option_group, $settings ) {
	if ( 'product_shipping' !== $option_group ) {
		return;
	}

	$shipping_count = isset( $settings['shipping_count'] ) ? intval( $settings['shipping_count'] ) : get_shipping_options_number();
	ic_register_setting( __( 'Number of shipping options', 'ecommerce-product-catalog' ), 'product_shipping_options_number' );
	?>
	<h3><?php esc_html_e( 'Shipping options', 'ecommerce-product-catalog' ); ?></h3>
	<form method="post" action="<?php echo esc_url( ic_epc_get_shipping_settings_url() ); ?>">
		<?php wp_nonce_field( 'ic_epc_shipping_update_count', 'ic_epc_shipping_update_count_nonce' ); ?>
		<input type="hidden" name="ic_epc_shipping_update_count" value="1"/>
		<table>
			<tr>
				<td colspan="2">
					<?php esc_html_e( 'Number of shipping options', 'ecommerce-product-catalog' ); ?>
					<input size="30" type="number" step="1" min="0" name="product_shipping_options_number" id="admin-number-field" value="<?php echo esc_attr( $shipping_count ); ?>"/>
					<input type="submit" class="button" value="<?php echo esc_attr__( 'Update', 'ecommerce-product-catalog' ); ?>"/>
				</td>
			</tr>
		</table>
	</form>
	<?php
}

/**
 * Renders the shipping defaults section content.
 *
 * @param array $settings Page settings.
 *
 * @return void
 */
function ic_epc_render_shipping_defaults_section( $settings ) {
	$shipping_count = intval( $settings['shipping_count'] );
	$shipping_mode  = $settings['shipping_mode'];
	?>
	<input type="hidden" name="product_shipping_options_number" value="<?php echo esc_attr( $shipping_count ); ?>"/>
		<div class="al-box info">
			<?php if ( 'option_price_per_product' === $shipping_mode ) : ?>
				<p><?php esc_html_e( "If you fill out the fields below, the system will automatically pre-fill the fields when adding a new item, so you don't have to fill them every time.", 'ecommerce-product-catalog' ); ?></p>
				<p><?php esc_html_e( 'When every item in your catalogue has different shipping options you can leave all or just a part of these fields empty.', 'ecommerce-product-catalog' ); ?></p>
				<p><?php esc_html_e( 'You can change these default values for every new item in the catalog.', 'ecommerce-product-catalog' ); ?></p>
			<?php elseif ( 'price_per_product' === $shipping_mode ) : ?>
			<p><?php esc_html_e( 'The shipping names below are global and will be used for every product.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'The shipping costs below are default values used when a product does not override its own price.', 'ecommerce-product-catalog' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'The shipping names and costs below are global and will be used for every product.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'Per-product shipping fields are disabled while global shipping is enabled.', 'ecommerce-product-catalog' ); ?></p>
		<?php endif; ?>
		<p><?php esc_html_e( 'Shipping notes are shown in checkout for the selected shipping option.', 'ecommerce-product-catalog' ); ?></p>
	</div>
	<?php
	$shipping_values = array(
		'label'              => get_default_shipping_labels(),
		'cost'               => get_default_shipping_costs(),
		'note'               => get_default_shipping_notes(),
		'address_collection' => get_default_shipping_address_collection(),
	);
	$currency        = '';
	if ( function_exists( 'product_currency' ) ) {
		$currency = product_currency();
	}
	ic_render_shipping_settings_table(
		array(
			'shipping_count'          => $shipping_count,
			'currency'                => $currency,
			'label_header'            => ( 'option_price_per_product' === $shipping_mode ? esc_html__( 'Shipping default name', 'ecommerce-product-catalog' ) : esc_html__( 'Shipping name', 'ecommerce-product-catalog' ) ),
			'cost_header'             => ( 'global' === $shipping_mode ? esc_html__( 'Shipping cost', 'ecommerce-product-catalog' ) : esc_html__( 'Shipping default cost', 'ecommerce-product-catalog' ) ),
			'show_address_collection' => ic_shipping_address_collection_available(),
			'values'                  => $shipping_values,
		)
	);
	ic_render_shipping_defaults_sync_controls( $shipping_mode, 'ic-epc-shipping-defaults-sync-form' );
}

/**
 * Renders additional shipping settings registered on the legacy hook.
 *
 * @param string $option_group Option group.
 * @param array  $settings Page settings.
 *
 * @return void
 */
function ic_epc_shipping_settings_page_sections_end( $option_group, $settings ) {
	if ( 'product_shipping' !== $option_group || empty( $settings['shipping_count'] ) || ! has_action( 'product-shipping-settings' ) ) {
		return;
	}

	do_action( 'product-shipping-settings', $settings['shipping_settings'] );
}

/**
 * Renders the standalone defaults sync form after the shared shipping page form.
 *
 * @param string $option_group Option group.
 *
 * @return void
 */
function ic_epc_render_shipping_defaults_sync_settings_form( $option_group ) {
	if ( 'product_shipping' !== $option_group ) {
		return;
	}

	ic_render_shipping_defaults_sync_form( 'ic-epc-shipping-defaults-sync-form' );
}

/**
 * Returns general shipping settings.
 *
 * @return array
 */
function get_general_shipping_settings() {
	$shiping_settings = get_option( 'general_shipping_settings' );
	if ( ! is_array( $shiping_settings ) ) {
		$shiping_settings = array();
	}

	return $shiping_settings;
}

/**
 * Returns default shipping labels.
 *
 * @return array
 */
function get_default_shipping_labels() {
	$shipping_labels = get_option( 'product_shipping_label' );
	if ( empty( $shipping_labels ) ) {
		$shipping_labels = array();
	}

	return $shipping_labels;
}

/**
 * Returns default shipping costs.
 *
 * @return array
 */
function get_default_shipping_costs() {
	$shipping_costs = get_option( 'product_shipping_cost' );
	if ( empty( $shipping_costs ) ) {
		$shipping_costs = array();
	}

	return $shipping_costs;
}

/**
 * Returns default shipping notes.
 *
 * @return array
 */
function get_default_shipping_notes() {
	$shipping_notes = get_option( 'product_shipping_note' );
	if ( empty( $shipping_notes ) ) {
		$shipping_notes = array();
	}

	return $shipping_notes;
}

add_filter( 'ic_epc_single_names_table_rows', 'ic_shipping_single_names', 30, 2 );

/**
 * Adds product page shipping label rows.
 *
 * @param array $rows Existing single label rows.
 * @param array $single_names Single labels.
 *
 * @return array
 */
function ic_shipping_single_names( $rows, $single_names ) {
	array_splice(
		$rows,
		1,
		0,
		array(
			array(
				'type'  => 'text',
				'label' => __( 'Shipping Label', 'ecommerce-product-catalog' ),
				'name'  => 'single_names[product_shipping]',
				'value' => $single_names['product_shipping'],
			),
			array(
				'type'  => 'text',
				'label' => __( 'Free Shipping Text', 'ecommerce-product-catalog' ),
				'name'  => 'single_names[free_shipping]',
				'value' => $single_names['free_shipping'],
			),
		)
	);

	return $rows;
}
