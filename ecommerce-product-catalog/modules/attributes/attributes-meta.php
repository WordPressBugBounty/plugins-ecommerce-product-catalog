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
add_action( 'add_product_metaboxes', 'ic_attributes_metabox' );

/**
 * Adds attributes meatbox
 *
 * @param array $names
 */
function ic_attributes_metabox( $names ) {
	$names['singular'] = ic_ucfirst( $names['singular'] );
	if ( is_plural_form_active() ) {
		$labels['attributes'] = sprintf( __( '%s Attributes', 'ecommerce-product-catalog' ), $names['singular'] );
	} else {
		$labels['attributes'] = __( 'Attributes', 'ecommerce-product-catalog' );
	}
	$attr_num = product_attributes_number();
	if ( $attr_num > 0 ) {
		add_meta_box( 'al_product_attributes', $labels['attributes'], 'al_product_attributes', 'al_product', apply_filters( 'product_attributes_box_column', 'normal' ), apply_filters( 'product_attributes_box_priority', 'default' ) );
	}
}

/**
 * Shows attributes metabox
 *
 * @global type $post
 */
function al_product_attributes( $post, $args = null ) {
	echo '<input type="hidden" name="attributesmeta_noncename" id="attributesmeta_noncename" value="' .
		wp_create_nonce( AL_BASE_PATH . 'attributes_meta' ) . '" />';
	// echo '<div class="al-box info">' . __( 'Only attributes with values set will be shown on product page.', 'ecommerce-product-catalog' ) . ' ' . sprintf( __( 'See <a target="_blank" href="%s">docs</a>.', 'ecommerce-product-catalog' ), 'https://implecode.com/docs/ecommerce-product-catalog/product-attributes/#cam=catalog-add-page-box&key=product-attributes' ) . '</div>';
	if ( function_exists( 'implecode_info' ) ) {
		implecode_info( __( 'Only attributes with values set will be shown on the product page.', 'ecommerce-product-catalog' ) . ' ' . sprintf( __( 'See <a target="_blank" href="%s">docs</a>.', 'ecommerce-product-catalog' ), 'https://implecode.com/docs/ecommerce-product-catalog/product-attributes/#cam=catalog-add-page-box&key=product-attributes' ) );
	}
	$attributes_mode = get_attributes_source_mode();
	if ( $attributes_mode === 'value_per_product' ) {
		echo '<p>' . esc_html__( 'Attribute names and units are defined globally. You can override only the attribute values for this product.', 'ecommerce-product-catalog' ) . '</p>';
	} elseif ( $attributes_mode === 'global' ) {
		echo '<p>' . esc_html__( 'Attribute names, values, and units are defined globally. Per-product attribute overrides are disabled in this mode.', 'ecommerce-product-catalog' ) . '</p>';
	}
	do_action( 'before_product_attributes_edit_single', $args );
	echo '<table class="sort-settings attributes">
	<thead><tr>
	<th class="title"><b>Name</b></th>
	<th></th>
	<th class="title"><b>Value</b></th>
	<th class="title"><b>Unit</b></th>
	<th class="dragger"></th>
	</tr>
	</thead>
	<tbody><tr style="height: 6px;" class="ic-not-sortable"></tr>';
	do_action( 'inside_attributes_edit_table' );
	$attributes_option = isset( $args['args']['attribute_values'] ) ? $args['args']['attribute_values'] : get_option( 'product_attribute', array() );
	if ( ! is_array( $attributes_option ) ) {
		$attributes_option = array();
	}
	$attributes_label_option = isset( $args['args']['attribute_labels'] ) ? $args['args']['attribute_labels'] : get_option( 'product_attribute_label', array() );
	if ( ! is_array( $attributes_label_option ) ) {
		$attributes_label_option = array();
	}
	$attributes_unit_option = isset( $args['args']['attribute_units'] ) ? $args['args']['attribute_units'] : get_option( 'product_attribute_unit', array() );
	if ( ! is_array( $attributes_unit_option ) ) {
		$attributes_unit_option = array();
	}
	$attributes_number  = product_attributes_number();
	$available_labels   = get_all_attribute_labels();
	$label_autocomplete = '';
	if ( ! empty( $available_labels ) ) {
		$label_autocomplete = 'data-ic-autocomplete="' . esc_attr( json_encode( $available_labels ) ) . '"';
	}
	$names = isset( $args['args']['field_names'] ) ? $args['args']['field_names'] : array(
		'value' => '_attribute',
		'label' => '_attribute-label',
		'unit'  => '_attribute-unit',
	);
	for ( $i = 1; $i <= $attributes_number; $i++ ) {
		$attributes_option_field       = get_post_meta( $post->ID, ic_attr_value_field_name( $i ), true );
		$attributes_label_option_field = get_post_meta( $post->ID, '_attribute-label' . $i, true );
		$attributes_unit_option_field  = get_post_meta( $post->ID, '_attribute-unit' . $i, true );
		$attributes_option[ $i ]       = isset( $attributes_option[ $i ] ) ? $attributes_option[ $i ] : '';
		$attributes_label_option[ $i ] = isset( $attributes_label_option[ $i ] ) ? $attributes_label_option[ $i ] : '';
		$attributes_unit_option[ $i ]  = isset( $attributes_unit_option[ $i ] ) ? $attributes_unit_option[ $i ] : '';
		if ( is_ic_new_product_screen() || ! ic_product_attribute_values_per_product() ) {
			$attributes_option_field = ic_attribute_has_value( $attributes_option_field ) ? $attributes_option_field : $attributes_option[ $i ];
		}
		if ( ! ic_attribute_has_value( $attributes_option_field ) && $attributes_mode === 'value_per_product' ) {
			$attribute_placeholder = is_array( $attributes_option[ $i ] ) ? reset( $attributes_option[ $i ] ) : $attributes_option[ $i ];
		} else {
			$attribute_placeholder = '';
		}
		$attributes_label_option_field = ( ic_product_attribute_labels_per_product() && ! empty( $attributes_label_option_field ) ) ? $attributes_label_option_field : $attributes_label_option[ $i ];
		$attributes_unit_option_field  = ( ic_product_attribute_labels_per_product() && ! empty( $attributes_unit_option_field ) ) ? $attributes_unit_option_field : $attributes_unit_option[ $i ];
		$attribute_value_field         = '';
		/*
			if ( is_array( $attributes_option_field ) ) {
			$attributes_option_field = '';
			}
		 *
		 */
		$autocomplete = '';
		if ( ! empty( $attributes_label_option_field ) ) {
			$available_attribute_values = ic_get_attribute_values( $attributes_label_option_field );
			if ( $available_attribute_values ) {
				$autocomplete = 'data-ic-autocomplete="' . esc_attr( json_encode( $available_attribute_values ) ) . '"';
			}
		}
		$label_name            = ic_product_attribute_labels_per_product() ? $names['label'] . $i : '';
		$unit_name             = ic_product_attribute_labels_per_product() ? $names['unit'] . $i : '';
		$value_name            = ic_product_attribute_values_per_product() ? $names['value'] . $i : '';
		$label_disabled_attr   = ! ic_product_attribute_labels_per_product() ? ' disabled="disabled"' : '';
		$unit_disabled_attr    = ! ic_product_attribute_labels_per_product() ? ' disabled="disabled"' : '';
		$value_disabled_attr   = ! ic_product_attribute_values_per_product() ? ' disabled="disabled"' : '';
		$placeholder_attr      = $attribute_placeholder !== '' ? ' placeholder="' . esc_attr( $attribute_placeholder ) . '"' : '';
		$field_value           = is_array( $attributes_option_field ) ? reset( $attributes_option_field ) : $attributes_option_field;
		$attribute_value_field = '<input ' . $autocomplete . ' class="ic_autocomplete attribute-value" type="text" data-base_name="' . $names['value'] . '" name="' . esc_attr( $value_name ) . '" value="' . esc_html( $field_value ) . '"' . $placeholder_attr . $value_disabled_attr . ' />';
		?>
		<tr>
			<td class="attributes-label-column"><input <?php echo $label_autocomplete; ?>
						class="ic_autocomplete attribute-label" type="text"
						data-base_name="<?php echo $names['label']; ?>" name="<?php echo esc_attr( $label_name ); ?>"
						value="<?php echo esc_html( $attributes_label_option_field ); ?>"<?php echo $label_disabled_attr; ?>/></td>
			<td class="break-column">:</td>
			<td class="value-column"><?php echo apply_filters( 'product_attribute_value_edit', $attribute_value_field, $i, $attributes_option_field, $attributes_label_option_field, $names['value'] ); ?></td>
			<td class="unit-column"><input class="attribute-unit admin-number-field" type="text"
											data-base_name="<?php echo $names['unit']; ?>"
											name="<?php echo esc_attr( $unit_name ); ?>"
											value="<?php echo esc_html( $attributes_unit_option_field ); ?>"<?php echo $unit_disabled_attr; ?>/></td>
			<td class="dragger"></td>
		</tr>
	<?php } ?>
	</tbody>
	</table>
	<?php
	do_action( 'product_attributes_edit_single', $post, $args );
}

add_filter( 'product_meta_save', 'ic_save_product_attributes', 1, 2 );

/**
 * Saves product attributes
 *
 * @param type $product_meta
 *
 * @return type
 */
function ic_save_product_attributes( $product_meta, $post ) {
	$max_attributes = product_attributes_number();
	for ( $i = 1; $i <= $max_attributes; $i++ ) {
		$default_label = get_default_product_attribute_label( $i );
		$label         = $default_label;
		if ( ic_product_attribute_labels_per_product() ) {
			$label_default = false;
			if ( ! empty( $_POST[ '_attribute-label' . $i ] ) ) {
				if ( $default_label !== $_POST[ '_attribute-label' . $i ] ) {
					$product_meta[ '_attribute-label' . $i ] = apply_filters( 'save_product_attribute_label', ic_sanitize( $_POST[ '_attribute-label' . $i ] ), $i, $post->ID );
				} else {
					$product_meta[ '_attribute-label' . $i ] = '';
					$label_default                           = true;
				}
			} else {
				$product_meta[ '_attribute-label' . $i ] = '';
			}
			if ( $label_default ) {
				$label = $default_label;
			} else {
				$label = isset( $product_meta[ '_attribute-label' . $i ] ) ? $product_meta[ '_attribute-label' . $i ] : $default_label;
			}
			if ( ! empty( $_POST[ '_attribute-unit' . $i ] ) ) {
				$default_unit = get_default_product_attribute_unit( $i );
				if ( $default_unit !== $_POST[ '_attribute-unit' . $i ] ) {
					$product_meta[ '_attribute-unit' . $i ] = apply_filters( 'save_product_attribute_unit', ic_sanitize( $_POST[ '_attribute-unit' . $i ] ), $i, $post->ID, $label );
				} else {
					$product_meta[ '_attribute-unit' . $i ] = '';
				}
			} else {
				$product_meta[ '_attribute-unit' . $i ] = '';
			}
		}
		if ( ic_product_attribute_values_per_product() ) {
			$product_meta[ ic_attr_value_field_name( $i ) ] = isset( $_POST[ '_attribute' . $i ] ) ? apply_filters( 'save_product_attribute_value', ic_sanitize( $_POST[ '_attribute' . $i ], false ), $i, $post->ID, $label ) : '';
		}
	}

	if ( ! empty( $_POST['_size_length'] ) ) {
		$product_meta['_size_length'] = ic_sanitize( $_POST['_size_length'] );
	}
	if ( ! empty( $_POST['_size_width'] ) ) {
		$product_meta['_size_width'] = ic_sanitize( $_POST['_size_width'] );
	}
	if ( ! empty( $_POST['_size_height'] ) ) {
		$product_meta['_size_height'] = ic_sanitize( $_POST['_size_height'] );
	}
	if ( ! empty( $_POST['_weight'] ) ) {
		$product_meta['_weight'] = floatval( $_POST['_weight'] );
	}

	return $product_meta;
}

function ic_size_field_names() {
	$field_names = array(
		'_size_length' => ic_attributes_get_length_label(),
		'_size_width'  => ic_attributes_get_width_label(),
		'_size_height' => ic_attributes_get_height_label(),
	);

	return apply_filters( 'ic_catalog_size_names', $field_names );
}

add_filter( 'active_product_filters', 'ic_activate_size_filter' );

function ic_activate_size_filter( $filters ) {
	$fields = ic_size_field_names();
	if ( ! empty( $fields ) ) {
		$filters = array_merge( $filters, array_keys( $fields ) );
	}

	return $filters;
}

add_filter( 'product_details_box_visible', 'ic_manage_size_weight_box_visibility' );

function ic_manage_size_weight_box_visibility( $visible ) {
	if ( $visible ) {
		return $visible;
	}
	if ( is_ic_attributes_size_enabled() || is_ic_attributes_weight_enabled() ) {
		return true;
	}

	return false;
}

function ic_get_product_size( $product_id, $force_value = false, $size = null, $enable_desc = true ) {
	if ( empty( $size ) ) {
		$length         = apply_filters( 'ic_front_size_value', get_post_meta( $product_id, '_size_length', true ) );
		$width          = apply_filters( 'ic_front_size_value', get_post_meta( $product_id, '_size_width', true ) );
		$height         = apply_filters( 'ic_front_size_value', get_post_meta( $product_id, '_size_height', true ) );
		$size['length'] = ! empty( $length ) ? $length : '';
		$size['width']  = ! empty( $width ) ? $width : '';
		$size['height'] = ! empty( $height ) ? $height : '';
	}
	if ( ! is_ic_admin() && ! $force_value ) {
		$unit        = ic_attributes_get_size_unit();
		$desc        = '';
		$text_size   = '';
		$field_names = ic_size_field_names();
		foreach ( $field_names as $name => $label ) {
			$this_desc      = '';
			$this_text_size = '';
			if ( $name === '_size_length' && ! empty( $size['length'] ) ) {
				$this_desc      = $label;
				$this_text_size = $size['length'] . $unit;
			} elseif ( $name === '_size_width' && ! empty( $size['width'] ) ) {
				$this_desc      .= $label;
				$this_text_size .= $size['width'] . $unit;
			} elseif ( $name === '_size_height' && ! empty( $size['height'] ) ) {
				$this_desc      .= $label;
				$this_text_size .= $size['height'] . $unit;
			}
			if ( ! empty( $this_desc ) ) {
				if ( ! empty( $desc ) ) {
					$desc .= 'x';
				}
				$desc .= $this_desc;
				if ( ! empty( $text_size ) ) {
					$text_size .= ' x ';
				}
				$text_size .= $this_text_size;
			}
		}

		if ( ! empty( $desc ) ) {
			$desc = '<span class="ic-size-desc">(' . $desc . ')</span>';
		}
		if ( ! empty( $text_size ) && $enable_desc ) {
			$text_size .= ' ' . $desc;
		}

		return $text_size;
	} else {
		return $size;
	}
}

function ic_get_product_weight( $product_id, $force_value = false, $weight = null ) {
	if ( empty( $weight ) ) {
		$weight = apply_filters( 'ic_front_weight_value', get_post_meta( $product_id, '_weight', true ), $product_id );
	}
	if ( ! is_ic_admin() && ! $force_value && ! empty( $weight ) ) {
		$unit   = ic_attributes_get_weight_unit();
		$weight = $weight . ' ' . $unit;
	}

	return $weight;
}

add_filter( 'admin_product_details', 'ic_size_metabox', 10, 2 );

/**
 * Adds attributes meatbox
 *
 * @param array $names
 */
function ic_size_metabox( $product_details, $product_id, $field_name = null, $size = null ) {
	if ( is_ic_attributes_size_enabled() ) {
		if ( $field_name === null || empty( $field_name[0] ) || empty( $field_name[1] ) || empty( $field_name[2] ) ) {
			// $field_name    = array( '_size_length', '_size_width', '_size_height' );
			$field_names = ic_size_field_names();
		}
		if ( $size === null || ! isset( $size['length'] ) || ! isset( $size['width'] ) || ! isset( $size['height'] ) ) {
			$size = ic_get_product_size( $product_id );
		}
		if ( ! empty( $field_names ) ) {
			$fields = '';
			foreach ( $field_names as $field_name => $field_label ) {
				if ( $field_name === '_size_length' ) {
					$val_name = 'length';
				} elseif ( $field_name === '_size_width' ) {
					$val_name = 'width';
				} elseif ( $field_name === '_size_height' ) {
					$val_name = 'height';
				}
				if ( ! empty( $fields ) ) {
					$fields .= 'x ';
				}
				$fields .= '<input placeholder="' . $field_label . '" style="max-width: 25%;" type="text" name="' . $field_name . '" value="' . $size[ $val_name ] . '" class="widefat" />';
			}
		} else {
			$fields  = '<input placeholder="' . ic_attributes_get_length_label() . '" style="max-width: 25%;" type="text" name="' . $field_name[0] . '" value="' . $size['length'] . '" class="widefat" />';
			$fields .= 'x <input placeholder="' . ic_attributes_get_width_label() . '" style="max-width: 25%;" type="text" name="' . $field_name[1] . '" value="' . $size['width'] . '" class="widefat" />';
			$fields .= 'x <input placeholder="' . ic_attributes_get_height_label() . '" style="max-width: 25%;" type="text" name="' . $field_name[2] . '" value="' . $size['height'] . '" class="widefat" />';
		}
		$label = __( 'Size', 'ecommerce-product-catalog' );
		$unit  = ic_attributes_get_size_unit();
		if ( ! empty( $unit ) ) {
			$label .= ' (' . $unit . ')';
		}
		$product_details .= apply_filters( 'admin_size_table', '<table><tr><td class="label-column">' . $label . ':</td><td class="size-column">' . $fields . '</td></tr></table>', $product_id );
	}

	return $product_details;
}

add_filter( 'admin_product_details', 'ic_weight_metabox', 10, 2 );

/**
 * Adds attributes meatbox
 *
 * @param array $names
 */
function ic_weight_metabox( $product_details, $product_id, $field_name = '_weight', $weight = null ) {
	if ( is_ic_attributes_weight_enabled() ) {
		if ( $weight === null ) {
			$weight = get_post_meta( $product_id, '_weight', true );
		}
		$label = ic_attributes_get_weight_label();
		$unit  = ic_attributes_get_weight_unit();
		if ( ! empty( $unit ) ) {
			$label .= ' (' . $unit . ')';
		}
		$product_details .= apply_filters( 'admin_weight_table', '<table><tr><td class="label-column">' . $label . ':</td><td class="size-column"><input style="width: 130px" type="text" name="' . $field_name . '" value="' . $weight . '" class="widefat" /></td></tr></table>', $product_id );
	}

	return $product_details;
}

add_filter( 'ic_default_single_names', 'ic_default_unit_labels' );

function ic_default_unit_labels( $single_names ) {
	$single_names['weight'] = __( 'Weight', 'ecommerce-product-catalog' );
	$single_names['size']   = __( 'Size', 'ecommerce-product-catalog' );
	$single_names['width']  = _x( 'W', 'Width', 'ecommerce-product-catalog' );
	$single_names['length'] = _x( 'L', 'Length', 'ecommerce-product-catalog' );
	$single_names['height'] = _x( 'H', 'Height', 'ecommerce-product-catalog' );

	return $single_names;
}

add_filter( 'ic_epc_single_names_table_rows', 'ic_unit_labels_settings_html', 50, 2 );

/**
 * Adds attribute unit label rows.
 *
 * @param array $rows Existing single label rows.
 * @param array $single_names Single labels.
 *
 * @return array
 */
function ic_unit_labels_settings_html( $rows, $single_names ) {
	$new_rows = array();

	if ( is_ic_attributes_weight_enabled() ) {
		$new_rows[] = array(
			'type'  => 'text',
			'label' => __( 'Weight', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[weight]',
			'value' => $single_names['weight'],
		);
	}
	if ( is_ic_attributes_size_enabled() ) {
		$new_rows[] = array(
			'type'  => 'text',
			'label' => __( 'Size', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[size]',
			'value' => $single_names['size'],
		);
		$new_rows[] = array(
			'type'  => 'text',
			'label' => __( 'Width', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[width]',
			'value' => $single_names['width'],
		);
		$new_rows[] = array(
			'type'  => 'text',
			'label' => __( 'Length', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[length]',
			'value' => $single_names['length'],
		);
		$new_rows[] = array(
			'type'  => 'text',
			'label' => __( 'Height', 'ecommerce-product-catalog' ),
			'name'  => 'single_names[height]',
			'value' => $single_names['height'],
		);
	}

	if ( ! empty( $new_rows ) ) {
		array_splice( $rows, -1, 0, $new_rows );
	}

	return $rows;
}
