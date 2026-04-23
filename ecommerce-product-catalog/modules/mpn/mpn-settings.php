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
add_filter( 'ic_epc_single_names_table_rows', 'ic_mpn_single_names', 10, 2 );

/**
 * Adds MPN product page label rows.
 *
 * @param array $rows Existing single label rows.
 * @param array $single_names Single labels.
 *
 * @return array
 */
function ic_mpn_single_names( $rows, $single_names ) {
	array_splice(
		$rows,
		1,
		0,
		array(
			array(
				'type'  => 'text',
				'label' => __( 'MPN Label', 'ecommerce-product-catalog' ),
				'name'  => 'single_names[product_mpn]',
				'value' => $single_names['product_mpn'],
			),
		)
	);

	return $rows;
}

add_filter( 'ic_epc_general_additional_settings_rows', 'ic_mpn_settings', 10, 2 );

/**
 * Adds the MPN setting row to the general additional settings section.
 *
 * @param array $rows Existing additional settings rows.
 * @param array $archive_multiple_settings Archive settings values.
 *
 * @return array
 */
function ic_mpn_settings( $rows, $archive_multiple_settings ) {
	$rows[] = array(
		'type'  => 'checkbox',
		'label' => __( 'Disable MPN', 'ecommerce-product-catalog' ),
		'name'  => 'archive_multiple_settings[disable_mpn]',
		'value' => $archive_multiple_settings['disable_mpn'],
	);

	return $rows;
}
