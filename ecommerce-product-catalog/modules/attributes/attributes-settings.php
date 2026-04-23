<?php
/**
 * Attributes settings.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'ic_settings_section_after_action', 'ic_epc_attributes_settings_section_after_action', 10, 3 );
add_action( 'ic_settings_section_table_action', 'ic_epc_attributes_settings_table_action', 10, 3 );

/**
 * Returns the attributes settings admin URL.
 *
 * @return string
 */
function ic_epc_get_attributes_settings_url() {
	return admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=attributes-settings&submenu=attributes' );
}

/**
 * Returns the attributes defaults sync controller instance.
 *
 * @return IC_EPC_Defaults_Sync_Controller
 */
function ic_epc_get_attributes_defaults_sync_controller() {
	static $controller = null;
	if ( null === $controller ) {
		$controller = new IC_EPC_Defaults_Sync_Controller(
			array(
				'sync_key'              => 'ic_epc_attributes_defaults_sync',
				'current_mode_callback' => 'get_attributes_source_mode',
				'actions'               => array(
					'label_value_per_product' => array(
						'labels' => array(
							'label' => __( 'Attribute Names', 'ecommerce-product-catalog' ),
						),
						'units'  => array(
							'label' => __( 'Attribute Units', 'ecommerce-product-catalog' ),
						),
						'values' => array(
							'label' => __( 'Attribute Values', 'ecommerce-product-catalog' ),
						),
					),
					'value_per_product'       => array(
						'values' => array(
							'label' => __( 'Attribute Values', 'ecommerce-product-catalog' ),
						),
					),
					'global'                  => array(),
				),
				'request_values'        => array(
					'count'  => 'product_attributes_number',
					'labels' => 'get_default_product_attribute_label',
					'units'  => 'get_default_product_attribute_unit',
					'values' => 'get_default_product_attribute_value',
				),
				'meta_mappings'         => array(
					'labels' => array(
						'request_key'     => 'labels',
						'meta_key_prefix' => '_attribute-label',
					),
					'units'  => array(
						'request_key'     => 'units',
						'meta_key_prefix' => '_attribute-unit',
					),
					'values' => array(
						'request_key'       => 'values',
						'meta_key_callback' => 'ic_attr_value_field_name',
					),
				),
				'count_option_name'     => 'product_attributes_number',
				'settings_url'          => ic_epc_get_attributes_settings_url(),
				'singular_label'        => __( 'attribute', 'ecommerce-product-catalog' ),
				'plural_label'          => __( 'attributes', 'ecommerce-product-catalog' ),
				'dirty_selector'        => 'input[name^="product_attribute_label["], input[name^="product_attribute["], input[name^="product_attribute_unit["], input[name="general_attributes_settings[source_mode]"]',
			)
		);
	}

	return $controller;
}

ic_epc_get_attributes_defaults_sync_controller()->register_hooks();

/**
 * Returns saved general attributes settings.
 *
 * @return array
 */
function get_general_attributes_settings() {
	$attributes_settings = get_option( 'general_attributes_settings' );
	if ( ! is_array( $attributes_settings ) ) {
		$attributes_settings = array();
	}

	return $attributes_settings;
}

/**
 * Returns supported attribute source modes.
 *
 * @return array
 */
function ic_get_attributes_source_modes() {
	return array(
		'label_value_per_product' => __( 'Attribute names and values set per product', 'ecommerce-product-catalog' ),
		'value_per_product'       => __( 'Global attribute names with values set per product', 'ecommerce-product-catalog' ),
		'global'                  => __( 'Global attribute names and values for all products', 'ecommerce-product-catalog' ),
	);
}

/**
 * Returns the current attributes source mode.
 *
 * @return string
 */
function get_attributes_source_mode() {
	$attributes_settings = get_general_attributes_settings();
	$mode                = isset( $attributes_settings['source_mode'] ) ? sanitize_key( $attributes_settings['source_mode'] ) : 'label_value_per_product';
	$allowed_modes       = array_keys( ic_get_attributes_source_modes() );

	if ( ! in_array( $mode, $allowed_modes, true ) ) {
		$mode = 'label_value_per_product';
	}

	return apply_filters( 'ic_attributes_source_mode', $mode, $attributes_settings );
}

/**
 * Returns true when attribute labels and units can be changed per product.
 *
 * @return bool
 */
function ic_product_attribute_labels_per_product() {
	return get_attributes_source_mode() === 'label_value_per_product';
}

/**
 * Returns true when attribute values can be changed per product.
 *
 * @return bool
 */
function ic_product_attribute_values_per_product() {
	return get_attributes_source_mode() !== 'global';
}

/**
 * Checks whether an attribute value should be treated as saved.
 *
 * @param mixed $value Candidate value.
 *
 * @return bool
 */
function ic_attribute_has_value( $value ) {
	if ( is_array( $value ) ) {
		return ! empty( $value );
	}

	return '' !== $value && null !== $value;
}

/**
 * Updates the number of product attributes.
 *
 * @param mixed $value Submitted value.
 *
 * @return int
 */
function ic_epc_update_product_attributes_number( $value ) {
	$count = ic_epc_sanitize_product_attributes_number( $value );
	update_option( 'product_attributes_number', $count );
	ic_delete_global( 'product_attributes_number' );

	return $count;
}

/**
 * Sanitizes the number of product attributes.
 *
 * @param mixed $value Submitted value.
 *
 * @return int
 */
function ic_epc_sanitize_product_attributes_number( $value ) {
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
function ic_epc_preserve_attributes_array_option( $option_name, $value ) {
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
 * Sanitizes attribute default values.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_product_attribute_values( $value ) {
	return ic_epc_preserve_attributes_array_option( 'product_attribute', $value );
}

/**
 * Sanitizes attribute labels.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_product_attribute_labels( $value ) {
	return ic_epc_preserve_attributes_array_option( 'product_attribute_label', $value );
}

/**
 * Sanitizes attribute units.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_product_attribute_units( $value ) {
	return ic_epc_preserve_attributes_array_option( 'product_attribute_unit', $value );
}

/**
 * Sanitizes standard attributes settings.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_standard_attributes( $value ) {
	return ic_epc_preserve_attributes_array_option( 'ic_standard_attributes', $value );
}

/**
 * Sanitizes general attributes settings.
 *
 * @param mixed $value Submitted value.
 *
 * @return array
 */
function ic_epc_sanitize_general_attributes_settings( $value ) {
	return ic_epc_preserve_attributes_array_option( 'general_attributes_settings', $value );
}

/**
 * Renders the attributes defaults sync controls.
 *
 * @param string $attributes_mode Current attributes mode.
 * @param string $form_id Optional external form ID.
 *
 * @return void
 */
function ic_render_attributes_defaults_sync_controls( $attributes_mode, $form_id = '' ) {
	ic_epc_get_attributes_defaults_sync_controller()->render_controls( $attributes_mode, $form_id );
}

/**
 * Renders the standalone attributes defaults sync form.
 *
 * @param string $form_id Form ID.
 *
 * @return void
 */
function ic_render_attributes_defaults_sync_form( $form_id ) {
	ic_epc_get_attributes_defaults_sync_controller()->render_form( $form_id );
}

add_action( 'ic_epc_attributes_defaults_table_after', 'ic_render_attributes_defaults_sync_controls', 10, 2 );
add_action( 'ic_settings_page_before_form', 'ic_epc_render_attributes_count_form', 10, 3 );
add_action( 'ic_settings_page_after_form', 'ic_epc_render_attributes_defaults_sync_settings_form', 10, 2 );

/**
 * Returns the attributes settings page configured for the shared framework renderer.
 *
 * @return IC_Settings_Page
 */
function ic_epc_attributes_settings_page() {
	static $page = null;
	if ( null === $page ) {
		$page = new IC_Settings_Page(
			array(
				'title'              => __( 'Attributes Settings', 'ecommerce-product-catalog' ),
				'option_group'       => 'product_attributes',
				'option_name'        => 'general_attributes_settings',
				'registered_options' => array(
					'product_attributes_number'   => array(
						'sanitize_callback' => 'ic_epc_sanitize_product_attributes_number',
					),
					'al_display_attributes',
					'product_attribute'           => array(
						'sanitize_callback' => 'ic_epc_sanitize_product_attribute_values',
					),
					'product_attribute_label'     => array(
						'sanitize_callback' => 'ic_epc_sanitize_product_attribute_labels',
					),
					'product_attribute_unit'      => array(
						'sanitize_callback' => 'ic_epc_sanitize_product_attribute_units',
					),
					'ic_standard_attributes'      => array(
						'sanitize_callback' => 'ic_epc_sanitize_standard_attributes',
					),
					'general_attributes_settings' => array(
						'sanitize_callback' => 'ic_epc_sanitize_general_attributes_settings',
					),
				),
				'submenu'            => 'attributes',
				'screen'             => 'product-settings.php',
				'screen_tab'         => 'attributes-settings',
				'screen_tab_label'   => __( 'Attributes', 'ecommerce-product-catalog' ),
				'screen_tab_menu_item_id' => 'attributes-settings',
				'screen_tab_query_args' => array(
					'submenu' => 'attributes',
				),
				'screen_tab_content_wrapper_class' => 'attributes-product-settings',
				'screen_tab_content_wrapper_style' => 'clear:both;',
				'screen_submenu_label' => __( 'Attributes Settings', 'ecommerce-product-catalog' ),
				'submenu_item_id'    => 'attributes-settings',
				'container_class'    => 'setting-content submenu',
				'settings'           => ic_epc_attributes_settings_page_settings(),
				'content_settings'   => ic_epc_attributes_settings_page_content_settings(),
				'sections'           => ic_epc_attributes_settings_page_sections(),
				'helpers'            => array(
					'ic_epc_main_helper',
					array(
						'callback' => 'ic_epc_doc_helper',
						'args'     => array( __( 'attributes', 'ecommerce-product-catalog' ), 'product-attributes' ),
					),
				),
				'submit_label'       => __( 'Save changes', 'ecommerce-product-catalog' ),
			)
		);
	}

	return $page;
}

/**
 * Returns current attributes settings used by the shared settings page hooks.
 *
 * @return array
 */
function ic_epc_attributes_settings_page_settings() {
	return array(
		'attributes_count'  => product_attributes_number(),
		'attributes_mode'   => get_attributes_source_mode(),
		'standard_settings' => ic_attributes_standard_settings(),
	);
}

/**
 * Returns the top attributes settings table configuration.
 *
 * @return array
 */
function ic_epc_attributes_settings_page_content_settings() {
	$settings         = ic_epc_attributes_settings_page_settings();
	$attributes_count = isset( $settings['attributes_count'] ) ? intval( $settings['attributes_count'] ) : product_attributes_number();
	$attributes_mode  = isset( $settings['attributes_mode'] ) ? sanitize_key( $settings['attributes_mode'] ) : get_attributes_source_mode();
	$rows             = array();

	if ( $attributes_count > 0 ) {
		$rows[] = array(
			'type'    => 'radio',
			'label'   => __( 'Attributes source', 'ecommerce-product-catalog' ),
			'name'    => 'general_attributes_settings[source_mode]',
			'value'   => $attributes_mode,
			'options' => ic_get_attributes_source_modes(),
		);
	}

	return array(
		'rows' => $rows,
	);
}

/**
 * Renders the attributes count update form.
 *
 * @param string $option_group Option group.
 * @param array  $settings Page settings.
 *
 * @return void
 */
function ic_epc_render_attributes_count_form( $option_group, $settings ) {
	if ( 'product_attributes' !== $option_group ) {
		return;
	}

	$attributes_count = isset( $settings['attributes_count'] ) ? intval( $settings['attributes_count'] ) : product_attributes_number();
	ic_register_setting( __( 'Number of attributes', 'ecommerce-product-catalog' ), 'product_attributes_number' );
	?>
	<h3><?php esc_html_e( 'Attributes', 'ecommerce-product-catalog' ); ?></h3>
	<form method="post" action="<?php echo esc_url( ic_epc_get_attributes_settings_url() ); ?>">
		<?php wp_nonce_field( 'ic_epc_attributes_update_count', 'ic_epc_attributes_update_count_nonce' ); ?>
		<input type="hidden" name="ic_epc_attributes_update_count" value="1"/>
		<table>
			<tr>
				<td colspan="2">
					<?php esc_html_e( 'Number of attributes', 'ecommerce-product-catalog' ); ?>
					<input size="30" type="number" step="1" min="0" name="product_attributes_number" id="admin-number-field" value="<?php echo esc_attr( $attributes_count ); ?>"/>
					<input type="submit" class="button" value="<?php echo esc_attr__( 'Update', 'ecommerce-product-catalog' ); ?>"/>
				</td>
			</tr>
		</table>
	</form>
	<?php
}

/**
 * Returns attributes settings page sections.
 *
 * @return array
 */
function ic_epc_attributes_settings_page_sections() {
	$sections          = array();
	$settings          = ic_epc_attributes_settings_page_settings();
	$attributes_count  = isset( $settings['attributes_count'] ) ? intval( $settings['attributes_count'] ) : product_attributes_number();
	$attributes_mode   = isset( $settings['attributes_mode'] ) ? sanitize_key( $settings['attributes_mode'] ) : get_attributes_source_mode();
	$standard_settings = isset( $settings['standard_settings'] ) && is_array( $settings['standard_settings'] ) ? $settings['standard_settings'] : ic_attributes_standard_settings();

	if ( $attributes_count > 0 ) {
		$sections[] = array(
			'title'         => __( 'Attribute Defaults', 'ecommerce-product-catalog' ),
			'description'   => ic_epc_attributes_settings_source_description( $attributes_mode ),
			'settings'      => ic_epc_attributes_settings_table_settings( $attributes_count ),
			'after_actions' => array(
				array(
					'action' => 'epc_attributes_defaults_table_after',
					'args'   => array( $attributes_mode, 'ic-epc-attributes-defaults-sync-form' ),
				),
				array(
					'action' => 'epc_attributes_settings_after',
				),
			),
		);
	} else {
		$sections[] = array(
			'title'       => __( 'Attributes disabled', 'ecommerce-product-catalog' ),
			'description' => __( 'Attributes disabled. To enable set minimum 1 attribute.', 'ecommerce-product-catalog' ),
			'settings'    => array(),
		);
	}

	$sections[] = array(
		'title'         => __( 'Standard Attributes', 'ecommerce-product-catalog' ),
		'table_class'   => 'IC_Settings_Standard_Table',
		'settings'      => array(
			'rows' => array(
				array(
					'type'    => 'dropdown',
					'label'   => __( 'Size Unit', 'ecommerce-product-catalog' ),
					'name'    => 'ic_standard_attributes[size_unit]',
					'value'   => $standard_settings['size_unit'],
					'options' => ic_available_size_units(),
				),
				array(
					'type'    => 'dropdown',
					'label'   => __( 'Weight Unit', 'ecommerce-product-catalog' ),
					'name'    => 'ic_standard_attributes[weight_unit]',
					'value'   => $standard_settings['weight_unit'],
					'options' => ic_available_weight_units(),
				),
			),
		),
		'after_actions' => array(
			array(
				'action' => 'epc_catalog_standard_attributes_settings',
				'args'   => array( $standard_settings ),
			),
		),
	);

	return $sections;
}

/**
 * Maps stable section action events to legacy attributes hooks.
 *
 * @param array               $action Section action config.
 * @param array               $settings Section settings.
 * @param IC_Settings_Section $section Section renderer.
 *
 * @return void
 */
function ic_epc_attributes_settings_section_after_action( $action, $settings, $section ) {
	$action_id = isset( $action['action'] ) ? (string) $action['action'] : '';
	$args      = isset( $action['args'] ) && is_array( $action['args'] ) ? $action['args'] : array();

	if ( 'epc_attributes_defaults_table_after' === $action_id ) {
		do_action(
			'ic_epc_attributes_defaults_table_after',
			isset( $args[0] ) ? $args[0] : '',
			isset( $args[1] ) ? $args[1] : ''
		);

		return;
	}

	if ( 'epc_attributes_settings_after' === $action_id ) {
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Legacy public EPC hook.
		do_action( 'attributes-settings' );

		return;
	}

	if ( 'epc_catalog_standard_attributes_settings' === $action_id ) {
		do_action( 'ic_catalog_standard_attributes_settings', isset( $args[0] ) ? $args[0] : array() );
	}
}

/**
 * Maps stable table action events to legacy attributes table hooks.
 *
 * @param array                     $action Table action config.
 * @param array                     $settings Table settings.
 * @param IC_Settings_Section_Table $table Table renderer.
 *
 * @return void
 */
function ic_epc_attributes_settings_table_action( $action, $settings, $table ) {
	unset( $settings, $table );

	$action_id = isset( $action['action'] ) ? (string) $action['action'] : '';
	$args      = isset( $action['args'] ) && is_array( $action['args'] ) ? $action['args'] : array();

	if ( 'epc_product_attributes_settings_table_th' === $action_id ) {
		do_action( 'product_attributes_settings_table_th' );

		return;
	}

	if ( 'epc_product_attributes_settings_table_td' === $action_id ) {
		do_action(
			'product_attributes_settings_table_td',
			isset( $args[0] ) ? $args[0] : null,
			isset( $args[1] ) ? $args[1] : array(),
			isset( $args[2] ) ? $args[2] : '',
			isset( $args[3] ) ? $args[3] : '',
			isset( $args[4] ) ? $args[4] : ''
		);
	}
}

/**
 * Returns the source mode description for the attributes settings section.
 *
 * @param string $attributes_mode Current attributes source mode.
 *
 * @return string
 */
function ic_epc_attributes_settings_source_description( $attributes_mode ) {
	if ( 'label_value_per_product' === $attributes_mode ) {
		return __( 'These default values automatically pre-fill your product pages. You can leave fields blank if your products vary, and adjust them per individual item later.', 'ecommerce-product-catalog' );
	}

	if ( 'value_per_product' === $attributes_mode ) {
		return __( 'The attribute names and units below are global and will be used for every product. The attribute values below are default values used when a product does not override its own value.', 'ecommerce-product-catalog' );
	}

	return __( 'The attribute names, values, and units below are global and will be used for every product. Per-product attribute fields are disabled while global attributes are enabled.', 'ecommerce-product-catalog' );
}

/**
 * Returns the attributes defaults table configuration.
 *
 * @param int $attributes_count Number of attributes.
 *
 * @return array
 */
function ic_epc_attributes_settings_table_settings( $attributes_count ) {
	return array(
		'table_type'          => 'fields',
		'wrapper_class'       => 'settings-table-container',
		'wrapper_style'       => 'overflow-x: scroll;',
		'table_class'         => 'wp-list-table widefat product-settings-table dragable',
		'registered_settings' => array(
			array(
				'label' => __( 'Attribute name', 'ecommerce-product-catalog' ),
				'name'  => 'product_attribute_label',
			),
			array(
				'label' => __( 'Attribute value', 'ecommerce-product-catalog' ),
				'name'  => 'product_attribute',
			),
			array(
				'label' => __( 'Attribute Unit', 'ecommerce-product-catalog' ),
				'name'  => 'product_attribute_unit',
			),
		),
		'columns'             => ic_epc_attributes_settings_table_columns(),
		'rows'                => ic_epc_attributes_settings_table_rows( $attributes_count ),
	);
}

/**
 * Returns the attributes defaults table columns.
 *
 * @return array
 */
function ic_epc_attributes_settings_table_columns() {
	return array(
		array(
			'class' => 'title',
			'text'  => '',
		),
		array(
			'class' => 'title',
			'text'  => __( 'Attribute name', 'ecommerce-product-catalog' ),
		),
		array(
			'text' => '',
		),
		array(
			'class' => 'title',
			'text'  => __( 'Attribute value', 'ecommerce-product-catalog' ),
		),
		array(
			'class' => 'title',
			'text'  => __( 'Unit', 'ecommerce-product-catalog' ),
		),
		array(
			'type'   => 'action',
			'action' => 'epc_product_attributes_settings_table_th',
		),
		array(
			'class' => 'dragger',
			'text'  => '',
		),
	);
}

/**
 * Returns the attributes defaults table rows.
 *
 * @param int $attributes_count Number of attributes.
 *
 * @return array
 */
function ic_epc_attributes_settings_table_rows( $attributes_count ) {
	$rows            = array();
	$attribute       = get_default_product_attribute_value();
	$attribute_label = get_default_product_attribute_label();
	$attribute_unit  = get_default_product_attribute_unit();
	$names           = array(
		'label' => 'product_attribute_label',
		'value' => 'product_attribute',
		'unit'  => 'product_attribute_unit',
	);

	for ( $i = 1; $i <= $attributes_count; $i++ ) {
		$label = isset( $attribute_label[ $i ] ) ? $attribute_label[ $i ] : '';
		$value = isset( $attribute[ $i ] ) ? $attribute[ $i ] : '';
		$unit  = isset( $attribute_unit[ $i ] ) ? $attribute_unit[ $i ] : '';

		$rows[] = array(
			'cells' => array(
				array(
					'class' => 'lp-column lp' . $i,
					'text'  => $i . '.',
				),
				array(
					'class'       => 'product-attribute-label-column',
					'type'        => 'input',
					'input_class' => 'product-attribute-label',
					'name'        => 'product_attribute_label[' . $i . ']',
					'value'       => $label,
					'attributes'  => array(
						'data-base_name' => 'product_attribute_label',
					),
				),
				array(
					'class' => 'lp-column',
					'text'  => ':',
				),
				array(
					'type'        => 'input',
					'input_class' => 'product-attribute',
					'name'        => 'product_attribute[' . $i . ']',
					'value'       => $value,
					'attributes'  => array(
						'data-base_name' => 'product_attribute',
					),
				),
				array(
					'type'        => 'input',
					'id'          => 'admin-number-field',
					'input_class' => 'product-attribute-unit',
					'name'        => 'product_attribute_unit[' . $i . ']',
					'value'       => $unit,
					'attributes'  => array(
						'data-base_name' => 'product_attribute_unit',
					),
				),
				array(
					'type'   => 'action',
					'action' => 'epc_product_attributes_settings_table_td',
					'args'   => array( $i, $names, $label, $value, $unit ),
				),
				array(
					'class' => 'dragger',
					'text'  => '',
				),
			),
		);
	}

	return $rows;
}

/**
 * Renders the hidden defaults-sync form outside the managed options form.
 *
 * @param string $option_group Current settings option group.
 *
 * @return void
 */
function ic_epc_render_attributes_defaults_sync_settings_form( $option_group ) {
	if ( 'product_attributes' !== $option_group ) {
		return;
	}

	ic_render_attributes_defaults_sync_form( 'ic-epc-attributes-defaults-sync-form' );
}

/**
 * Renders product attribute settings rows.
 *
 * @param array      $attribute Attribute values.
 * @param array      $attribute_label Attribute labels.
 * @param array      $attribute_unit Attribute units.
 * @param array|null $names Field names.
 * @param int|null   $max_num Maximum number of rows.
 *
 * @return void
 */
function ic_attributes_settings_rows( $attribute, $attribute_label, $attribute_unit, $names = null, $max_num = null ) {
	if ( null === $max_num ) {
		$max_num = product_attributes_number();
	}
	if ( null === $names ) {
		$names['label'] = 'product_attribute_label';
		$names['value'] = 'product_attribute';
		$names['unit']  = 'product_attribute_unit';
	}
	for ( $i = 1; $i <= $max_num; $i++ ) {
		$attribute_label[ $i ] = isset( $attribute_label[ $i ] ) ? $attribute_label[ $i ] : '';
		$attribute[ $i ]       = isset( $attribute[ $i ] ) ? $attribute[ $i ] : '';
		$attribute_unit[ $i ]  = isset( $attribute_unit[ $i ] ) ? $attribute_unit[ $i ] : '';
		?>
			<tr>
				<td class="lp-column lp<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?>.</td>
				<td class="product-attribute-label-column"><input class="product-attribute-label" type="text"
																	data-base_name="<?php echo esc_attr( $names['label'] ); ?>"
																	name="<?php echo esc_attr( $names['label'] ); ?>[<?php echo esc_attr( $i ); ?>]"
																	value="<?php echo esc_html( $attribute_label[ $i ] ); ?>"/>
				</td>
				<td class="lp-column">:</td>
				<td><input class="product-attribute" type="text" data-base_name="<?php echo esc_attr( $names['value'] ); ?>"
							name="<?php echo esc_attr( $names['value'] ); ?>[<?php echo esc_attr( $i ); ?>]"
							value="<?php echo esc_html( $attribute[ $i ] ); ?>"/></td>
				<td><input id="admin-number-field" class="product-attribute-unit" type="text"
							data-base_name="<?php echo esc_attr( $names['unit'] ); ?>"
							name="<?php echo esc_attr( $names['unit'] ); ?>[<?php echo esc_attr( $i ); ?>]"
							value="<?php echo esc_html( $attribute_unit[ $i ] ); ?>"/></td>
			<?php do_action( 'product_attributes_settings_table_td', $i, $names, $attribute_label[ $i ], $attribute[ $i ], $attribute_unit[ $i ] ); ?>
			<td class="dragger"></td>
		</tr> 
		<?php
	}
}

/**
 * Returns the number of defined product attributes
 *
 * @return int
 */
function product_attributes_number() {
	$number = ic_get_global( 'product_attributes_number' );
	if ( ! $number ) {
		$number = get_option( 'product_attributes_number', 3 );
		ic_save_global( 'product_attributes_number', $number );
	}

	return intval( $number );
}

/**
 * Returns default product attribute label defined in product settings
 *
 * @param int $i Attribute index.
 *
 * @return string
 */
function get_default_product_attribute_label( $i = null ) {
	$attribute_label = apply_filters( 'ic_product_attribute_label_option', get_option( 'product_attribute_label' ) );
	if ( ! is_array( $attribute_label ) ) {
		$attribute_label = array();
	}
	if ( null === $i ) {
		return $attribute_label;
	}
	$attribute_label[ $i ] = isset( $attribute_label[ $i ] ) ? $attribute_label[ $i ] : '';

	return $attribute_label[ $i ];
}

/**
 * Returns default product attribute value defined in product settings
 *
 * @param int $i Attribute index.
 *
 * @return string
 */
function get_default_product_attribute_value( $i = null ) {
	$attribute_value = get_option( 'product_attribute' );
	if ( ! is_array( $attribute_value ) ) {
		$attribute_value = array();
	}
	if ( null === $i ) {
		return $attribute_value;
	}
	$attribute_value[ $i ] = isset( $attribute_value[ $i ] ) ? $attribute_value[ $i ] : '';

	return $attribute_value[ $i ];
}

/**
 * Returns default product attribute unit defined in product settings
 *
 * @param int $i Attribute index.
 *
 * @return string
 */
function get_default_product_attribute_unit( $i = null ) {
	$attribute_unit = get_option( 'product_attribute_unit' );
	if ( ! is_array( $attribute_unit ) ) {
		$attribute_unit = array();
	}
	if ( null === $i ) {
		return $attribute_unit;
	}
	$attribute_unit[ $i ] = isset( $attribute_unit[ $i ] ) ? $attribute_unit[ $i ] : '';

	return apply_filters( 'ic_default_product_attribute_unit', $attribute_unit[ $i ], $i );
}

add_filter( 'ic_settings_design_table_render_design_settings', 'ic_listing_attributes_design_settings', 10, 3 );

/**
 * Adds listing attributes settings to generated design settings.
 *
 * @param array $settings Design settings.
 * @param array $design Design row configuration.
 * @param array $table_settings Design table settings.
 *
 * @return array
 */
function ic_listing_attributes_design_settings( $settings, $design, $table_settings ) {
	$listing_names = array(
		'default' => 'modern_grid',
		'list'    => 'classic_list',
		'grid'    => 'classic_grid',
	);

	if ( empty( $design['value'] ) || empty( $listing_names[ $design['value'] ] ) ) {
		return $settings;
	}

	$listing_name         = $listing_names[ $design['value'] ];
	$listing_settings_key = $listing_name . '_settings';
	if ( empty( $table_settings[ $listing_settings_key ] ) || ! is_array( $table_settings[ $listing_settings_key ] ) ) {
		return $settings;
	}

	$listing_settings = $table_settings[ $listing_settings_key ];
	$option_name      = $listing_name . '_settings[attributes]';
	$settings[]       = array(
		'type'  => 'checkbox',
		'label' => __( 'Show Attributes', 'ecommerce-product-catalog' ),
		'name'  => $option_name,
		'value' => isset( $listing_settings['attributes'] ) ? $listing_settings['attributes'] : '',
		'tip'   => __( 'Use this only with short attributes labels and values, e.g. Color: Red', 'ecommerce-product-catalog' ),
	);

	return $settings;
}

/**
 * Renders listing attributes settings.
 *
 * @param array  $listing_settings Listing settings.
 * @param string $listing_name Listing name.
 *
 * @return void
 */
function ic_listing_attributes_settings( $listing_settings, $listing_name ) {
	$option_name = $listing_name . '_settings[attributes]';
	ic_register_setting( __( 'Show Attributes', 'ecommerce-product-catalog' ) . ' ' . ucwords( str_replace( '_', ' ', $listing_name ) ), $option_name );
	?>
	<input title="<?php esc_attr_e( 'Use this only with short attributes labels and values, e.g. Color: Red', 'ecommerce-product-catalog' ); ?>"
			type="checkbox" name="<?php echo esc_attr( $option_name ); ?>"
			value="1"<?php checked( 1, isset( $listing_settings['attributes'] ) ? $listing_settings['attributes'] : '' ); ?>> <?php esc_html_e( 'Show Attributes', 'ecommerce-product-catalog' ); ?>
	<br>
	<?php
}

/**
 * Returns standard attributes settings.
 *
 * @return array
 */
function ic_attributes_standard_settings() {
	$settings = get_option( 'ic_standard_attributes' );
	if ( empty( $settings ) ) {
		$settings = array();
	}
	$settings['weight_unit'] = ! empty( $settings['weight_unit'] ) ? $settings['weight_unit'] : 'kg';
	$settings['size_unit']   = ! empty( $settings['size_unit'] ) ? $settings['size_unit'] : 'cm';

	return $settings;
}

/**
 * Returns available weight units.
 *
 * @return array
 */
function ic_available_weight_units() {
	$units = array(
		'disable' => __( 'Disable Weight', 'ecommerce-product-catalog' ),
		'kg'      => 'kg',
		'g'       => 'g',
		'lbs'     => 'lbs',
		'oz'      => 'oz',
	);

	return $units;
}

/**
 * Returns available size units.
 *
 * @return array
 */
function ic_available_size_units() {
	$units = array(
		'disable' => __( 'Disable Size', 'ecommerce-product-catalog' ),
		'm'       => 'm',
		'cm'      => 'cm',
		'mm'      => 'mm',
		'in'      => 'in',
		'yd'      => 'yd',
		'ft'      => 'ft',
	);

	return $units;
}

/**
 * Returns the selected size unit.
 *
 * @return string
 */
function ic_attributes_get_size_unit() {
	$settings = ic_attributes_standard_settings();
	if ( ! empty( $settings['size_unit'] ) && 'disable' !== $settings['size_unit'] ) {
		return apply_filters( 'ic_size_display_unit', $settings['size_unit'] );
	}

	return '';
}

/**
 * Returns the selected weight unit.
 *
 * @return string
 */
function ic_attributes_get_weight_unit() {
	$settings = ic_attributes_standard_settings();
	if ( ! empty( $settings['weight_unit'] ) && 'disable' !== $settings['weight_unit'] ) {
		return apply_filters( 'ic_weight_display_unit', $settings['weight_unit'] );
	}

	return '';
}

/**
 * Returns the weight label.
 *
 * @return string
 */
function ic_attributes_get_weight_label() {
	$single_names = get_single_names();

	return $single_names['weight'];
}

/**
 * Returns the size label.
 *
 * @return string
 */
function ic_attributes_get_size_label() {
	$single_names = get_single_names();

	return $single_names['size'];
}

/**
 * Returns the height label.
 *
 * @return string
 */
function ic_attributes_get_height_label() {
	$single_names = get_single_names();

	return $single_names['height'];
}

/**
 * Returns the width label.
 *
 * @return string
 */
function ic_attributes_get_width_label() {
	$single_names = get_single_names();

	return $single_names['width'];
}

/**
 * Returns the length label.
 *
 * @return string
 */
function ic_attributes_get_length_label() {
	$single_names = get_single_names();

	return $single_names['length'];
}

add_filter( 'ic_default_single_names', 'ic_attributes_standard_labels' );

/**
 * Adds standard attribute labels to single product names.
 *
 * @param array $single_names Single product names.
 *
 * @return array
 */
function ic_attributes_standard_labels( $single_names ) {
	remove_filter( 'ic_default_single_names', 'ic_attributes_standard_labels' );
	$true_single_names = get_single_names();
	add_filter( 'ic_default_single_names', 'ic_attributes_standard_labels' );
	$single_names['product_size']   = $true_single_names['size'] . ':';
	$single_names['product_weight'] = $true_single_names['weight'] . ':';

	return $single_names;
}

add_filter( 'pre_update_option_product_attribute_label', 'ic_product_attribute_label_update', 10, 2 );

/**
 * Updates related attribute terms after label changes.
 *
 * @param array $new_value New label values.
 * @param array $old_value Old label values.
 *
 * @return array
 */
function ic_product_attribute_label_update( $new_value, $old_value ) {
	if ( empty( $old_value ) || ! is_array( $new_value ) ) {
		return $new_value;
	}
	ic_clear_empty_attributes();
	$all_labels = get_all_attribute_labels();
	foreach ( $old_value as $i => $old ) {
		if ( ! isset( $new_value[ $i ] ) || in_array( $old, $new_value, true ) || ( ! empty( $new_value[ $i ] ) && $old === $new_value[ $i ] ) ) {
			continue;
		} else {
			$new = strval( $new_value[ $i ] );
		}
		if ( empty( $new ) || in_array( $new, $all_labels, true ) ) {
			continue;
		}
		if ( ! in_array( $old, $all_labels, true ) ) {
			continue;
		}
		$attribute_id = intval( ic_get_attribute_id( $old ) );
		if ( empty( $attribute_id ) ) {
			continue;
		}
		wp_update_term(
			$attribute_id,
			'al_product-attributes',
			array(
				'parent' => 0,
				'name'   => $new,
				'slug'   => $new,
			)
		);
	}

	return $new_value;
}
