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
		/* translators: Placeholders are substituted with runtime values. */
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
		esc_attr( wp_create_nonce( AL_BASE_PATH . 'attributes_meta' ) ) . '" />';
	// echo '<div class="al-box info">' . __( 'Only attributes with values set will be shown on product page.', 'ecommerce-product-catalog' ) . ' ' . sprintf( __( 'See <a target="_blank" href="%s">docs</a>.', 'ecommerce-product-catalog' ), 'https://implecode.com/docs/ecommerce-product-catalog/product-attributes/#cam=catalog-add-page-box&key=product-attributes' ) . '</div>';
	if ( function_exists( 'implecode_info' ) ) {
		/* translators: %s: product attributes documentation URL. */
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
	<th class="title"><b>Unit</b></th>';
	do_action( 'product_attributes_edit_table_th', $args );
	echo '<th class="dragger"></th>
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

	$label_meta_base = apply_filters( 'ic_attribute_label_field_name', '_attribute-label' );
	$unit_meta_base  = apply_filters( 'ic_attribute_unit_field_name', '_attribute-unit' );
	for ( $i = 1; $i <= $attributes_number; $i++ ) {
		$attributes_option_field       = get_post_meta( $post->ID, ic_attr_value_field_name( $i ), true );
		$attributes_label_option_field = get_post_meta( $post->ID, $label_meta_base . $i, true );
		$attributes_unit_option_field  = get_post_meta( $post->ID, $unit_meta_base . $i, true );
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
		$attribute_value_field = '<input ' . $autocomplete . ' class="ic_autocomplete attribute-value" type="text" data-base_name="' . esc_attr( $names['value'] ) . '" name="' . esc_attr( $value_name ) . '" value="' . esc_html( $field_value ) . '"' . $placeholder_attr . $value_disabled_attr . ' />';
		?>
		<tr>
			<td class="attributes-label-column"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags. ?><input <?php echo $label_autocomplete; ?>
						class="ic_autocomplete attribute-label" type="text"
						data-base_name="<?php echo esc_attr( $names['label'] ); ?>" name="<?php echo esc_attr( $label_name ); ?>"
						value="<?php echo esc_html( $attributes_label_option_field ); ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'product_attribute_value_edit' filter, which extensions and themes legitimately use to inject their own HTML. ?><?php echo $label_disabled_attr; ?>/></td>
			<td class="break-column">:</td>
			<td class="value-column"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: the return value of the 'product_attribute_value_edit' filter, which extensions and themes legitimately use to inject their own HTML. ?><?php echo apply_filters( 'product_attribute_value_edit', $attribute_value_field, $i, $attributes_option_field, $attributes_label_option_field, $names['value'] ); ?></td>
			<td class="unit-column"><input class="attribute-unit admin-number-field" type="text"
											data-base_name="<?php echo esc_attr( $names['unit'] ); ?>"
											name="<?php echo esc_attr( $unit_name ); ?>"
											value="<?php echo esc_html( $attributes_unit_option_field ); ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared markup, not text: HTML assembled earlier in this scope, so escaping it here would print the tags. ?><?php echo $unit_disabled_attr; ?>/></td>
			<?php do_action( 'product_attributes_edit_table_td', $i, $post->ID, $names, $attributes_label_option_field ); ?>
			<td class="dragger"></td>
		</tr>
	<?php } ?>
	</tbody>
	</table>
	<?php
	do_action( 'product_attributes_edit_single', $post, $args );
}

add_filter( 'product_meta_save', 'ic_save_product_attributes', 1, 2 );
add_filter( 'ic_epc_ai_product_fields', 'ic_attributes_ai_product_fields', 10, 3 );

/**
 * Adds AI size and weight fields when the attributes module enables them.
 *
 * @param array  $fields    AI field definitions.
 * @param string $post_type Product post type.
 * @param object $context   EPC AI integration context.
 *
 * @return array
 */
function ic_attributes_ai_product_fields( $fields, $post_type, $taxonomy ) {
	if ( ! class_exists( 'IC_Product_Details' ) || ! IC_Product_Details::is_ai_product_post_type( $post_type ) ) {
		return $fields;
	}

	if ( is_ic_attributes_weight_enabled() ) {
		$fields['weight'] = array(
			'type'            => 'meta',
			'label'            => __( 'Weight', 'ecommerce-product-catalog' ),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This declaration binds the weight field to its established product meta key.
			'meta_key'        => '_weight',
			'form_field'      => '_weight',
			'input_selector'  => '[name="_weight"]',
			'enhancement_mode' => 'preserve',
			'default_enabled' => true,
		);
	}

	if ( is_ic_attributes_size_enabled() ) {
		$fields['size'] = array(
			'type'            => 'group',
			'label'           => __( 'Size', 'ecommerce-product-catalog' ),
			'enhancement_mode' => 'preserve',
			'default_enabled' => true,
			'group_fields'    => array(
				'length' => array(
					'type'           => 'meta',
					'label'          => __( 'Length', 'ecommerce-product-catalog' ),
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This declaration binds length to its established product meta key.
					'meta_key'       => '_size_length',
					'form_field'     => '_size_length',
					'input_selector' => '[name="_size_length"]',
					'enhancement_mode' => 'preserve',
				),
				'width'  => array(
					'type'           => 'meta',
					'label'          => __( 'Width', 'ecommerce-product-catalog' ),
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This declaration binds width to its established product meta key.
					'meta_key'       => '_size_width',
					'form_field'     => '_size_width',
					'input_selector' => '[name="_size_width"]',
					'enhancement_mode' => 'preserve',
				),
				'height' => array(
					'type'           => 'meta',
					'label'          => __( 'Height', 'ecommerce-product-catalog' ),
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This declaration binds height to its established product meta key.
					'meta_key'       => '_size_height',
					'form_field'     => '_size_height',
					'input_selector' => '[name="_size_height"]',
					'enhancement_mode' => 'preserve',
				),
			),
		);
	}

	if ( ic_product_attribute_values_per_product() && product_attributes_number() > 0 ) {
		$fields['attributes'] = array(
			'type'             => 'group',
			'label'            => __( 'Attributes', 'ecommerce-product-catalog' ),
			'enhancement_mode' => 'preserve',
			'default_enabled'  => true,
			'group_fields'     => ic_attributes_ai_group_fields(),
		);
	}

	return $fields;
}

/**
 * Returns AI group-field definitions for dynamic product attributes.
 *
 * @return array
 */
function ic_attributes_ai_group_fields() {
	$group_fields   = array();
	$max_attributes = product_attributes_number();

	for ( $i = 1; $i <= $max_attributes; $i++ ) {
		$field_name = ic_attr_value_field_name( $i );
		$label      = ic_attributes_ai_group_field_label( $i );

		$group_fields[ 'attribute_' . $i ] = array(
			'type'             => 'meta',
			'label'            => $label,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Custom attribute declarations must retain their registered product meta key.
			'meta_key'         => $field_name,
			'form_field'       => $field_name,
			'input_selector'   => '[name="' . $field_name . '"]',
			'enhancement_mode' => 'preserve',
		);
	}

	return $group_fields;
}

/**
 * Returns the display label for one AI attribute slot.
 *
 * @param int $attribute_id Attribute slot number.
 *
 * @return string
 */
function ic_attributes_ai_group_field_label( $attribute_id ) {
	$attribute_id = absint( $attribute_id );
	$label        = get_default_product_attribute_label( $attribute_id );
	$unit         = get_default_product_attribute_unit( $attribute_id );

	if ( '' === (string) $label ) {
		$label = sprintf(
			/* translators: %d: attribute slot number. */
			__( 'Attribute %d', 'ecommerce-product-catalog' ),
			$attribute_id
		);
	}

	if ( '' !== (string) $unit ) {
		$label .= ' (' . $unit . ')';
	}

	return $label;
}

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
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
			if ( ! empty( $_POST[ '_attribute-label' . $i ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
				if ( $default_label !== $_POST[ '_attribute-label' . $i ] ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
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
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
			if ( ! empty( $_POST[ '_attribute-unit' . $i ] ) ) {
				$default_unit = get_default_product_attribute_unit( $i );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
				if ( $default_unit !== $_POST[ '_attribute-unit' . $i ] ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
					$product_meta[ '_attribute-unit' . $i ] = apply_filters( 'save_product_attribute_unit', ic_sanitize( $_POST[ '_attribute-unit' . $i ] ), $i, $post->ID, $label );
				} else {
					$product_meta[ '_attribute-unit' . $i ] = '';
				}
			} else {
				$product_meta[ '_attribute-unit' . $i ] = '';
			}
		}
		if ( ic_product_attribute_values_per_product() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
			$product_meta[ ic_attr_value_field_name( $i ) ] = isset( $_POST[ '_attribute' . $i ] ) ? apply_filters( 'save_product_attribute_value', ic_sanitize( $_POST[ '_attribute' . $i ], false ), $i, $post->ID, $label ) : '';
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
	if ( ! empty( $_POST['_size_length'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
		$product_meta['_size_length'] = ic_sanitize( $_POST['_size_length'] );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
	if ( ! empty( $_POST['_size_width'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
		$product_meta['_size_width'] = ic_sanitize( $_POST['_size_width'] );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
	if ( ! empty( $_POST['_size_height'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
		$product_meta['_size_height'] = ic_sanitize( $_POST['_size_height'] );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
	if ( ! empty( $_POST['_weight'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce, 'editpost' action and edit_post capability are verified by IC_Register_Product::implecode_save_products_meta() before the product_meta_save filter runs; the value is sanitized with ic_sanitize() and left slashed on purpose because update_post_meta() unslashes it downstream.
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

/**
 * Returns the configured size and weight labels with default fallbacks.
 *
 * @return array Array with 'size' and 'weight' labels.
 */
function ic_size_weight_labels() {
	$size_label   = ic_attributes_get_size_label();
	$weight_label = ic_attributes_get_weight_label();
	if ( '' === trim( (string) $size_label ) ) {
		$size_label = __( 'Size', 'ecommerce-product-catalog' );
	}
	if ( '' === trim( (string) $weight_label ) ) {
		$weight_label = __( 'Weight', 'ecommerce-product-catalog' );
	}

	return array(
		'size'   => $size_label,
		'weight' => $weight_label,
	);
}

/**
 * Returns a single size dimension input with its visible caption.
 *
 * @param string $name       Input name.
 * @param string $caption    Visible caption and placeholder (e.g. L, W, H).
 * @param string $aria_label Accessible label including the unit.
 * @param string $value      Current value.
 *
 * @return string
 */
function ic_size_metabox_field( $name, $caption, $aria_label, $value ) {
	return '<label class="ic-size-field-wrap"><span class="ic-size-field-caption" aria-hidden="true">' . esc_html( $caption ) . '</span><input placeholder="' . esc_attr( $caption ) . '" aria-label="' . esc_attr( $aria_label ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="widefat ic-size-field" /></label>';
}

/**
 * Returns the product size admin table.
 *
 * Called by ic_size_weight_metabox() and directly by the variations details.
 *
 * @param string     $product_details Product details HTML to append to.
 * @param int        $product_id      Product ID.
 * @param array|null $field_name      Optional custom input names (length, width, height).
 * @param array|null $size            Optional size values.
 *
 * @return string
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
		$unit        = ic_attributes_get_size_unit();
		$unit_suffix = '' !== (string) $unit ? ' (' . $unit . ')' : '';
		$aria        = array(
			'length' => __( 'Length', 'ecommerce-product-catalog' ) . $unit_suffix,
			'width'  => __( 'Width', 'ecommerce-product-catalog' ) . $unit_suffix,
			'height' => __( 'Height', 'ecommerce-product-catalog' ) . $unit_suffix,
		);
		$fields      = array();
		if ( ! empty( $field_names ) ) {
			foreach ( $field_names as $field_name => $field_label ) {
				if ( $field_name === '_size_length' ) {
					$val_name = 'length';
				} elseif ( $field_name === '_size_width' ) {
					$val_name = 'width';
				} elseif ( $field_name === '_size_height' ) {
					$val_name = 'height';
				}
				$fields[] = ic_size_metabox_field( $field_name, $field_label, isset( $aria[ $val_name ] ) ? $aria[ $val_name ] : $field_label, $size[ $val_name ] );
			}
		} else {
			$fields[] = ic_size_metabox_field( $field_name[0], ic_attributes_get_length_label(), $aria['length'], $size['length'] );
			$fields[] = ic_size_metabox_field( $field_name[1], ic_attributes_get_width_label(), $aria['width'], $size['width'] );
			$fields[] = ic_size_metabox_field( $field_name[2], ic_attributes_get_height_label(), $aria['height'], $size['height'] );
		}
		$fields           = implode( '<span class="ic-size-sep" aria-hidden="true">&times;</span>', $fields );
		$labels           = ic_size_weight_labels();
		$label            = $labels['size'] . $unit_suffix;
		$product_details .= apply_filters( 'admin_size_table', '<table class="ic-size-table"><tr><td class="label-column">' . esc_html( $label ) . ':</td><td class="size-column"><span class="ic-size-fields" role="group" aria-label="' . esc_attr( $label ) . '">' . $fields . '</span></td></tr></table>', $product_id );
	}

	return $product_details;
}

/**
 * Returns the product weight admin table.
 *
 * Called by ic_size_weight_metabox() and directly by the variations details.
 *
 * @param string      $product_details Product details HTML to append to.
 * @param int         $product_id      Product ID.
 * @param string      $field_name      Input name.
 * @param string|null $weight          Optional weight value.
 *
 * @return string
 */
function ic_weight_metabox( $product_details, $product_id, $field_name = '_weight', $weight = null ) {
	if ( is_ic_attributes_weight_enabled() ) {
		if ( $weight === null ) {
			$weight = get_post_meta( $product_id, '_weight', true );
		}
		$unit             = ic_attributes_get_weight_unit();
		$unit_suffix      = '' !== (string) $unit ? ' (' . $unit . ')' : '';
		$labels           = ic_size_weight_labels();
		$label            = $labels['weight'] . $unit_suffix;
		$product_details .= apply_filters( 'admin_weight_table', '<table class="ic-weight-table"><tr><td class="label-column">' . esc_html( $label ) . ':</td><td class="size-column weight-column"><input aria-label="' . esc_attr( $label ) . '" type="text" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $weight ) . '" class="widefat ic-weight-field" /></td></tr></table>', $product_id );
	}

	return $product_details;
}

add_filter( 'admin_product_details', 'ic_size_weight_metabox', 20, 2 );

/**
 * Appends the grouped Size & Weight section to the Product Details box.
 *
 * Priority 20 places the section after SKU/MPN and extension rows (priorities 10-12),
 * before the extension info notice at 99 (which prepends anyway).
 *
 * @param string $product_details Product details HTML.
 * @param int    $product_id      Product ID.
 *
 * @return string
 */
function ic_size_weight_metabox( $product_details, $product_id ) {
	$size       = ic_size_metabox( '', $product_id );
	$weight     = ic_weight_metabox( '', $product_id );
	$has_size   = '' !== trim( $size );
	$has_weight = '' !== trim( $weight );
	if ( ! $has_size && ! $has_weight ) {
		return $product_details;
	}
	$legend = '';
	if ( $has_size && $has_weight ) {
		$labels  = ic_size_weight_labels();
		$heading = sprintf(
			/* translators: Product Details box section heading. 1: size label (default "Size"), 2: weight label (default "Weight"). */
			_x( '%1$s & %2$s', 'size and weight section heading', 'ecommerce-product-catalog' ),
			$labels['size'],
			$labels['weight']
		);
		$legend = '<legend class="ic-product-details-group-title">' . esc_html( $heading ) . '</legend>';
	}

	return $product_details . '<fieldset class="ic-product-details-group ic-size-weight-group">' . $legend . $size . $weight . '</fieldset>';
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
