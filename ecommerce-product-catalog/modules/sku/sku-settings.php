<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages product attributes
 *
 * Here all product attributes are defined and managed.
 *
 * @version     1.0.0
 * @package     ecommerce-product-catalog/includes
 * @author      impleCode
 */
add_filter( 'ic_epc_single_names_table_rows', 'ic_sku_single_names', 20, 2 );

/**
 * Adds SKU product page label rows.
 *
 * @param array $rows Existing single label rows.
 * @param array $single_names Single labels.
 *
 * @return array
 */
function ic_sku_single_names( $rows, $single_names ) {
	array_splice(
		$rows,
		1,
		0,
		array(
			array(
				'type'  => 'text',
				'label' => __( 'SKU Label', 'ecommerce-product-catalog' ),
				'name'  => 'single_names[product_sku]',
				'value' => $single_names['product_sku'],
			),
		)
	);

	return $rows;
}

add_filter( 'ic_epc_general_settings_page_sections', 'ic_sku_settings', 20, 2 );

/**
 * Adds the SKU settings section to the general settings page.
 *
 * @param array $sections General settings sections.
 * @param array $page_settings General page settings.
 *
 * @return array
 */
function ic_sku_settings( $sections, $page_settings ) {
	$rows = apply_filters(
		'ic_epc_general_additional_settings_rows',
		array(
			array(
				'type'  => 'checkbox',
				'label' => __( 'Disable SKU', 'ecommerce-product-catalog' ),
				'name'  => 'archive_multiple_settings[disable_sku]',
				'value' => $page_settings['archive_multiple_settings']['disable_sku'],
			),
		),
		$page_settings['archive_multiple_settings'],
		$page_settings
	);

	if ( ! empty( $rows ) ) {
		$sections[] = array(
			'title'       => __( 'Additional Settings', 'ecommerce-product-catalog' ),
			'table_class' => 'IC_Settings_Standard_Table',
			'settings'    => array(
				'rows' => $rows,
			),
		);
	}

	return $sections;
}
