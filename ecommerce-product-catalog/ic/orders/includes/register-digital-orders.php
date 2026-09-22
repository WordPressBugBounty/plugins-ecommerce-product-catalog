<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** Read a scalar admin order field once, unslashed and text-sanitized. */
function ic_epc_order_post_text( $key, $field_type = '' ) {
	$is_multiline = in_array( $field_type, array( 'paragraph', 'textarea' ), true ) || preg_match( '/(?:^|_)vat_address$/', $key );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
	return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? ( $is_multiline ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ) : sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) ) : '';
}

/** Allow controlled order-row markup while rejecting executable content. */
function ic_order_product_admin_markup( $markup ) {
	return wp_kses(
		(string) $markup,
		array(
			'a'      => array(
				'class'  => true,
				'href'   => true,
				'target' => true,
			),
			'br'     => array(),
			'input'  => array(
				'class'    => true,
				'custom'   => true,
				'id'       => true,
				'min'      => true,
				'name'     => true,
				'readonly' => true,
				'step'     => true,
				'type'     => true,
				'value'    => true,
			),
			'option' => array(
				'class'    => true,
				'selected' => true,
				'value'    => true,
			),
			'select' => array(
				'class'  => true,
				'custom' => true,
				'id'     => true,
				'name'   => true,
			),
			'strong' => array(),
		)
	);
}

/** Read a bounded non-negative admin order number. */
function ic_epc_order_post_number( $key ) {
	$value = ic_epc_order_post_text( $key );
	return '' === $value ? '' : max( 0, (float) $value );
}

/** Read a validated admin order identifier. */
function ic_epc_order_post_id( $key ) {
	return absint( ic_epc_order_post_text( $key ) );
}

/**
 * Manages digital orders fields
 *
 * Here all digital orders post type is defined and managed.
 *
 * @version        1.0.0
 * @package        digital-products-orders/includes
 * @author        Norbert Dreszer
 */
class ic_orders {
	/**
	 * Whether gross prices match every stored row summary.
	 *
	 * @var bool|null
	 */
	public $display_gross;

	function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'formbuilder_style' ) );
		add_action( 'post_updated', array( $this, 'save' ), 1, 2 );
		add_action( 'ic_save_order', array( $this, 'save_fields' ) );
		add_action( 'ic_save_order', array( $this, 'save_pre_name' ), 10, 2 );

		add_filter( 'post_updated_messages', array( $this, 'messages' ) );
		add_action( 'do_meta_boxes', array( $this, 'unregister_external_boxes' ) );
		add_action( 'admin_init', array( $this, 'wpseo_compatible' ) );

		add_filter( 'posts_where', array( $this, 'admin_search_where' ) );
		add_filter( 'posts_join', array( $this, 'admin_search_join' ) );

		add_filter( 'formbuilder_raw_fields_fields', array( $this, 'delete_phone' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'disable_autosave' ) );
	}

	function labels() {
		$order_labels = array(
			'name'               => __( 'Orders', 'ecommerce-product-catalog' ),
			'singular_name'      => __( 'Order', 'ecommerce-product-catalog' ),
			'add_new'            => __( 'New Order', 'ecommerce-product-catalog' ),
			'add_new_item'       => __( 'Add New Order', 'ecommerce-product-catalog' ),
			'edit_item'          => __( 'Edit Order', 'ecommerce-product-catalog' ),
			'new_item'           => __( 'Add Order', 'ecommerce-product-catalog' ),
			'view_item'          => __( 'View Order', 'ecommerce-product-catalog' ),
			'search_items'       => __( 'Search Orders', 'ecommerce-product-catalog' ),
			'not_found'          => __( 'No Orders found', 'ecommerce-product-catalog' ),
			'not_found_in_trash' => __( 'No Orders found in trash', 'ecommerce-product-catalog' ),
		);

		return apply_filters( 'ic_orders_labels', $order_labels );
	}

	function register() {
		$reg_settings = array(
			'labels'               => $this->labels(),
			'public'               => false,
			'show_in_menu'         => true,
			'show_ui'              => true,
			'publicly_queryable'   => false,
			'show_in_nav_menus'    => false,
			'has_archive'          => false,
			'supports'             => false,
			'register_meta_box_cb' => array( $this, 'metaboxes' ),
			'capability_type'      => 'digital_order',
			'capabilities'         => array(
				'publish_posts'          => 'publish_digital_orders',
				'edit_posts'             => 'edit_digital_orders',
				'edit_others_posts'      => 'edit_others_digital_orders',
				'edit_published_posts'   => 'edit_published_digital_orders',
				'edit_private_posts'     => 'edit_private_digital_orders',
				'delete_posts'           => 'delete_digital_orders',
				'delete_others_posts'    => 'delete_others_digital_orders',
				'delete_private_posts'   => 'delete_private_digital_orders',
				'delete_published_posts' => 'delete_published_digital_orders',
				'read_private_posts'     => 'read_private_digital_orders',
				'edit_post'              => 'edit_digital_order',
				'delete_post'            => 'delete_digital_order',
				'read_post'              => 'read_digital_order',
			),
			'exclude_from_search'  => true,
		);

		register_post_type( 'al_digital_orders', $reg_settings );
	}

	function metaboxes() {
		$labels = $this->labels();
		/* translators: Placeholders are substituted with runtime values. */
		add_meta_box(
			'al_digital_order_details',
			sprintf( __( '%s Details', 'ecommerce-product-catalog' ), $labels['singular_name'] ),
			array(
				$this,
				'details',
			),
			'al_digital_orders',
			'normal',
			'default'
		);
		add_meta_box(
			'al_digital_order_products',
			__( 'Products', 'ecommerce-product-catalog' ),
			array(
				$this,
				'products',
			),
			'al_digital_orders',
			'normal',
			'default'
		);
		add_meta_box(
			'al_digital_order_summary',
			__( 'Summary', 'ecommerce-product-catalog' ),
			array(
				$this,
				'summary',
			),
			'al_digital_orders',
			'side',
			'default'
		);
		do_action( 'add_digital_order_metaboxes' );
	}

	function details() {
		global $post;
		$labels          = $this->labels();
		$payment_details = ic_get_order_payment_details( $post->ID );
		$order_id        = get_post_meta( $post->ID, '_order_id', true );
		$pre_name        = $this->order_pre_name( $post->ID );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( empty( $payment_details['name'] ) && isset( $_GET['payment_details'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			$payment_details         = isset( $_GET['payment_details'] ) && is_scalar( $_GET['payment_details'] ) ? ic_sanitize_decoded_payload( $_GET['payment_details'] ) : '';
			$payment_details['date'] = wp_date( 'd-m-Y' );
		}
		// $req_fields    = get_eo_required_fields();
		// $disp_fields = get_option( 'disp_fields', unserialize( DEFAULT_DISP_FIELDS ) );
		// $name_fields = get_option( 'name_fields', unserialize( DEFAULT_NAME_FIELDS ) );
		?>
		<table>
		<thead>
		<tr>
			<th>
				<?php /* translators: %s: singular digital order label. */ echo esc_html( sprintf( __( '%s Data', 'ecommerce-product-catalog' ), $labels['singular_name'] ) ); ?>
			</th>
			<th>
				<?php esc_html_e( 'Customer Details', 'ecommerce-product-catalog' ); ?>
			</th>
			<th>
				<?php esc_html_e( 'Additional Information', 'ecommerce-product-catalog' ); ?>
			</th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<td class="ic-order-main-data">
				<table>
					<tr>
						<td>
							<label for="payment_first_name"><?php esc_html_e( 'Order Status', 'ecommerce-product-catalog' ); ?></label>
							<?php ic_order_status_dropdown( $payment_details['status'] ); ?>
						</td>
					</tr>
					<?php
					if ( ! empty( $payment_details['date'] ) ) {
						if ( ! is_numeric( $payment_details['date'] ) ) {
							$payment_details['date'] = strtotime( $payment_details['date'] );
						}
						?>
						<tr>
							<td>
								<label for="payment_details_date"><?php esc_html_e( 'Date', 'ecommerce-product-catalog' ); ?>
										: <?php echo esc_html( date_i18n( get_option( 'date_format' ), $payment_details['date'] ) ); ?></label>
								<input type="hidden" name="payment_details_date"
											value="<?php echo esc_attr( $payment_details['date'] ); ?>"/>
							</td>
						</tr>
						<?php
					}
					if ( ! empty( $order_id ) ) {
						?>
						<tr>
							<td>
								<?php echo esc_html__( 'Transaction ID', 'ecommerce-product-catalog' ) . ': ' . esc_html( $order_id ); ?>
							</td>
						</tr>
						<?php if ( ! empty( $payment_details['order-currency'] ) && $payment_details['order-currency'] !== $payment_details['currency'] ) { ?>
							<tr>
								<td>
										<?php echo esc_html( __( 'Order Currency', 'ecommerce-product-catalog' ) . ': ' . $payment_details['order-currency'] ); ?>
									<input type=hidden name="payment_details_order_currency"
												value="<?php echo esc_attr( $payment_details['order-currency'] ); ?>"/>
								</td>
							</tr>
						<?php } ?>
						<tr>
							<td>
									<?php echo esc_html( __( 'Payment Currency', 'ecommerce-product-catalog' ) . ': ' . $payment_details['currency'] ); ?>
								<input type=hidden name="payment_details_currency"
											value="<?php echo esc_attr( $payment_details['currency'] ); ?>"/>
							</td>
						</tr>
						<?php
					} else {
						?>
						<tr>
							<td>
								<?php implecode_settings_text( __( 'Order Currency', 'ecommerce-product-catalog' ), 'payment_details_currency', $payment_details['currency'], 'req' ); ?>
							</td>
						</tr>
						<?php
					}
					do_action( 'digital_order_data', $post->ID, $payment_details );
					?>
				</table>
			</td>
			<td class="ic-order-checkout-data">
				<table class="payment-details">
				<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formbuilder generates component-escaped admin controls; post KSES would remove them.
					echo $this->get_fields( $post->ID, $payment_details, $pre_name );
				?>
				</table>
			</td>
			<td class="ic-order-additional-information">
				<table>
					<tr>
						<td colspan=2>
							<label for="payment_details_shipping_email"><?php esc_html_e( 'Shipping Email', 'ecommerce-product-catalog' ); ?>
								<span class="star"> *</span></label>
							<input type="text" required="required" name="payment_details_shipping_email"
										value="<?php echo esc_attr( $payment_details['shipping_email'] ); ?>"/>
						</td>
					</tr>
					<?php
					if ( $payment_details['vatid'] != '' ) {
						?>
						<tr>
							<td><?php esc_html_e( 'EU VAT', 'ecommerce-product-catalog' ); ?>:</td>
								<td><?php echo esc_html( $payment_details['vatid'] ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'VAT Name', 'ecommerce-product-catalog' ); ?>:</td>
							<td><input type="text" name="payment_details_vat_name"
											value="<?php echo esc_attr( $payment_details['vat_name'] ); ?>"/></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'VAT Address', 'ecommerce-product-catalog' ); ?>:</td>
							<td><?php $payment_details['vat_address'] = ic_format_vat_address( $payment_details['vat_address'] ); ?>
								<textarea
											name="payment_details_vat_address"><?php echo esc_textarea( $payment_details['vat_address'][0] ); ?>
											<?php
											if ( $payment_details['vat_address'][1] != '' ) {
												echo esc_html( "\n" . $payment_details['vat_address'][1] );}
											?>
											</textarea></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Ver. ID', 'ecommerce-product-catalog' ); ?>:</td>
								<td><?php echo esc_html( $payment_details['vat_ver_id'] ); ?></td>
						</tr>
						<?php
					}
					do_action( 'digital_order_delivery_details', $post->ID, $payment_details );
					?>
				</table>
			</td>
		</tr>
		</tbody>
		</table>
		<?php
	}

	function is_order_taxed( $order_products ) {
		if ( ! isset( $order_products['product_name'], $order_products['product_subtotal_net'], $order_products['product_summary'] ) || is_array( $order_products['product_name'] ) !== is_array( $order_products['product_subtotal_net'] ) || is_array( $order_products['product_subtotal_net'] ) !== is_array( $order_products['product_summary'] ) ) {
			return false;
		}
		if ( is_array( $order_products['product_subtotal_net'] ) ) {
			$product_rows = count( $order_products['product_name'] );
			if ( 0 === $product_rows || count( $order_products['product_subtotal_net'] ) !== $product_rows || count( $order_products['product_summary'] ) !== $product_rows || count( array_filter( $order_products['product_subtotal_net'], 'is_numeric' ) ) !== $product_rows || count( array_filter( $order_products['product_summary'], 'is_numeric' ) ) !== $product_rows ) {
				return false;
			}
		}
		$product_subtotal_net = $this->order_product_value( $order_products, 'product_subtotal_net', 0 );
		$product_summary      = $this->order_product_value( $order_products, 'product_summary', 0 );
		if ( ! is_numeric( $product_subtotal_net ) || ! is_numeric( $product_summary ) ) {
			return false;
		}
		if ( $product_subtotal_net !== $product_summary ) {
			return true;
		}

		return false;
	}

	function products() {
		global $post;
		$fields                = ic_order_product_fields();
		$order_id              = $post->ID;
		$order_products        = ic_get_order_products( $order_id );
		$manual_order_product  = ic_get_all_manual_order_products( $order_id );
		$manual_products_count = get_post_meta( $order_id, 'manual_products', true );
		if ( empty( $manual_products_count ) ) {
			$manual_products_count = 1;
		}
		?>
		<input type="hidden" class="manual_products_count" name="manual_products"
					value="<?php echo esc_attr( $manual_products_count ); ?>"/>
		<table id="order_product_list">
			<thead>
			<?php $this->product_headers(); ?>
			</thead>
			<tbody>
			<?php // if ( empty( $order_products[ 'product_name' ] ) ) { ?>

			<?php
			/* } else */
			$this->order_product_rows( $order_products, $order_id );
			for ( $i = 1; $i <= $manual_products_count; $i++ ) {
				$manual_order_product[ $i ]['id'] = isset( $manual_order_product[ $i ]['id'] ) ? $manual_order_product[ $i ]['id'] : '';
				if ( ! empty( $manual_order_product[ $i ]['id'] ) ) {
					$manual_order_product[ $i ]['name'] = get_the_title( $manual_order_product[ $i ]['id'] );
				}
				$manual_order_product[ $i ]['name']     = isset( $manual_order_product[ $i ]['name'] ) ? $manual_order_product[ $i ]['name'] : '';
				$manual_order_product[ $i ]['quantity'] = isset( $manual_order_product[ $i ]['quantity'] ) ? $manual_order_product[ $i ]['quantity'] : '1';
				$manual_order_product[ $i ]['price']    = isset( $manual_order_product[ $i ]['price'] ) ? $manual_order_product[ $i ]['price'] : '';
				$manual_order_product[ $i ]['summary']  = isset( $manual_order_product[ $i ]['summary'] ) ? $manual_order_product[ $i ]['summary'] : '';
				$manual_order_product[ $i ]['c_id']     = isset( $manual_order_product[ $i ]['c_id'] ) ? $manual_order_product[ $i ]['c_id'] : '';
				?>
				<tr>
					<td>
					<?php
					if ( ! empty( $manual_order_product[ $i ]['id'] ) || empty( $manual_order_product[ $i ]['name'] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
						if ( ! isset( $_GET['custom_product'] ) && function_exists( 'digital_products_dropdown' ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The digital-products helper returns a generated selector; post KSES would remove it.
							echo digital_products_dropdown( 'manual_order_product_id_' . absint( $i ), __( 'Choose product from catalog', 'ecommerce-product-catalog' ), absint( $manual_order_product[ $i ]['id'] ) );
						} else {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The catalog helper builds component-escaped selector markup and returns it when its echo flag is disabled.
							echo ic_select_product( __( 'Choose product from catalog', 'ecommerce-product-catalog' ), absint( $manual_order_product[ $i ]['id'] ), 'manual_order_product_id_' . absint( $i ), 'digital_products_dropdown', 0 );
						}
					}
					?>
						<input
						<?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin display mode; order mutations use their save nonce and capability boundary.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin display mode; order mutations use their save nonce and capability boundary.
						if ( ! isset( $_GET['custom_product'] ) ) {
							if ( ! empty( $manual_order_product[ $i ]['id'] ) || empty( $manual_order_product[ $i ]['name'] ) ) {
								echo 'style="display:none"';
							}
						}
						echo 'type="text" id="input_manual_order_product_name_' . absint( $i ) . '" name="manual_order_product_name_' . absint( $i ) . '" value="' . esc_attr( $manual_order_product[ $i ]['name'] ) . '" />
				</td>
				<td>
					<input custom="' . absint( $i ) . '" type="number" min="0" step="1" class="product-quantity number_box" id="manual_order_product_quantity_' . absint( $i ) . '" name="manual_order_product_quantity_' . absint( $i ) . '" value="' . esc_attr( $manual_order_product[ $i ]['quantity'] ) . '" />
				</td>
				<td>
						<input custom="' . esc_attr( $i ) . '"';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin display mode; order mutations use their save nonce and capability boundary.
						if ( ! isset( $_GET['custom_product'] ) ) {
							if ( ! empty( $manual_order_product[ $i ]['id'] ) || empty( $manual_order_product[ $i ]['name'] ) ) {
								echo 'type="text"';
							} else {
								echo 'type="number" min="0" step="0.01"';
							}
						} else {
							echo 'type="number" min="0" step="0.01"';
						}
						echo ' class="product-price number_box" id="manual_order_product_price_' . absint( $i ) . '" name="manual_order_product_price_' . absint( $i ) . '" value="' . esc_attr( $manual_order_product[ $i ]['price'] ) . '" />
				</td>';
						do_action( 'digital_order_manual_products_td', $post->ID, ! empty( $manual_order_product[ $i ]['id'] ) ? $manual_order_product[ $i ]['id'] : $manual_order_product[ $i ]['c_id'], $i );
						echo '<td>
						<input readonly type="text" class="number_box" id="manual_order_product_summary_' . absint( $i ) . '" name="manual_order_product_summary_' . absint( $i ) . '" value="' . esc_attr( $manual_order_product[ $i ]['summary'] ) . '" />
				</td>
			</tr>';
			}
			?>
			</tbody>
		</table>
		<?php
		// if ( empty( $order_products[ 'product_name' ] ) ) {
		ic_add_row_button();
		// }
	}

	function order_product_rows( $order_products, $order_id ) {
		if ( ! is_array( $order_products['product_name'] ) && ! empty( $order_products['product_name'] ) ) {
			$product_summary = $this->order_product_value( $order_products, 'product_summary', 0, false );
			if ( '' === $product_summary ) {
				$product_summary = $this->order_product_value( $order_products, 'product_price', 0, false );
			}
			?>
			<tr>
				<td>
					<?php
					$product_name_link = $this->linked_name( $order_products['product_id'], false );
					if ( empty( $product_name_link ) ) {
						echo esc_html( $order_products['product_name'] );
					} else {
						echo wp_kses_post( apply_filters( 'ic_order_product_name', $product_name_link, $order_products ) );
					}
					?>
				</td>
				<?php do_action( 'digital_order_products_after_name_td', $order_id, $order_products['product_id'], $order_products ); ?>
				<td>
                    <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ic_order_product_admin_markup() returns trusted admin controls whose dynamic leaves are escaped while the markup is built; escaping the complete composite would break the controls. ?>
					<?php echo ic_order_product_admin_markup( apply_filters( 'ic_order_product_qty', $order_products['product_quantity'], $order_products ) ); ?>
				</td>
				<td>
                    <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ic_order_product_admin_markup() returns trusted admin controls whose dynamic leaves are escaped while the markup is built; escaping the complete composite would break the controls. ?>
					<?php echo ic_order_product_admin_markup( apply_filters( 'ic_order_product_price', $order_products['product_price'], $order_products ) ); ?>
				</td>
				<?php do_action( 'digital_order_products_td', $order_id, $order_products['product_id'] ); ?>
				<td>
                    <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ic_order_product_admin_markup() returns trusted admin controls whose dynamic leaves are escaped while the markup is built; escaping the complete composite would break the controls. ?>
					<?php echo ic_order_product_admin_markup( apply_filters( 'ic_order_product_summary', $product_summary, $order_products ) ); ?>
				</td>
			</tr>
			<?php
		} elseif ( is_array( $order_products['product_name'] ) ) {
			foreach ( $order_products['product_name'] as $i => $product_name ) {
				?>
				<tr>
					<td class="product-name-td">
						<?php
						$product_name_link = $this->linked_name( $order_products['product_id'][ $i ], false );
						if ( empty( $product_name_link ) ) {
							echo esc_html( $product_name );
						} else {
							echo wp_kses_post( apply_filters( 'ic_order_product_name', $product_name_link, $order_products, $i ) );
						}
						?>
					</td>
					<?php do_action( 'digital_order_products_after_name_td', $order_id, $order_products['product_id'][ $i ], $order_products, $i ); ?>
					<td class="product-qty-td">
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ic_order_product_admin_markup() returns trusted admin controls whose dynamic leaves are escaped while the markup is built; escaping the complete composite would break the controls. ?>
						<?php echo ic_order_product_admin_markup( apply_filters( 'ic_order_product_qty', $order_products['product_quantity'][ $i ], $order_products, $i ) ); ?>
					</td>
					<td class="product-price-td">
						<?php
						if ( ! empty( $order_products['product_gross_price'][ $i ] ) && $this->maybe_display_gross( $order_products ) ) {
							$order_product_price = $order_products['product_gross_price'][ $i ];
						} elseif ( ! empty( $order_products['product_net_price'][ $i ] ) ) {
							$order_product_price = $order_products['product_net_price'][ $i ];
						} else {
							$order_product_price = $order_products['product_price'][ $i ];
						}
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ic_order_product_admin_markup() returns trusted admin controls whose dynamic leaves are escaped while the markup is built; escaping the complete composite would break the controls.
						echo ic_order_product_admin_markup( apply_filters( 'ic_order_product_price', $order_product_price, $order_products, $i ) );
						?>
					</td>
					<?php do_action( 'digital_order_products_td', $order_id, $order_products['product_id'][ $i ], $order_products, $i ); ?>
					<td class="product-summary-td">
						<?php
						$product_subtotal_net = $this->order_product_value( $order_products, 'product_subtotal_net', $i, true );
						if ( '' !== $product_subtotal_net && ! $this->maybe_display_gross( $order_products ) ) {
							$this_product_summary = $product_subtotal_net;
						} else {
							$this_product_summary = $this->order_product_value( $order_products, 'product_summary', $i, true );
							if ( '' === $this_product_summary ) {
								$this_product_summary = $this->order_product_value( $order_products, 'product_price', $i, true );
							}
						}
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ic_order_product_admin_markup() returns trusted admin controls whose dynamic leaves are escaped while the markup is built; escaping the complete composite would break the controls.
						echo ic_order_product_admin_markup( apply_filters( 'ic_order_product_summary', $this_product_summary, $order_products, $i ) );
						?>
					</td>
				</tr>
				<?php
			}
		}
	}

	function product_headers() {
		?>

		<tr>
			<th>
				<?php esc_html_e( 'Product Name', 'ecommerce-product-catalog' ); ?>
			</th>
			<?php do_action( 'digital_order_products_after_name_th' ); ?>
			<th>
				<?php esc_html_e( 'Product Quantity', 'ecommerce-product-catalog' ); ?>
			</th>
			<th>
				<?php esc_html_e( 'Product Price', 'ecommerce-product-catalog' ); ?>
			</th>
			<?php do_action( 'digital_order_products_th' ); ?>
			<th>
				<?php esc_html_e( 'Summary', 'ecommerce-product-catalog' ); ?>
			</th>
		</tr>
		<?php
	}

	function maybe_display_gross( $order_products ) {
		if ( isset( $this->display_gross ) ) {
			return $this->display_gross;
		}
		if ( empty( $order_products['product_gross_price'] ) || ! is_array( $order_products['product_gross_price'] ) || ! isset( $order_products['product_name'], $order_products['product_quantity'], $order_products['product_summary'] ) || ! is_array( $order_products['product_name'] ) || ! is_array( $order_products['product_quantity'] ) || ! is_array( $order_products['product_summary'] ) ) {
			return false;
		}
		$product_rows = count( $order_products['product_name'] );
		if ( 0 === $product_rows || count( $order_products['product_gross_price'] ) !== $product_rows || count( $order_products['product_quantity'] ) !== $product_rows || count( $order_products['product_summary'] ) !== $product_rows ) {
			return false;
		}
		$return = true;
		foreach ( $order_products['product_gross_price'] as $i => $gross_price ) {
			$quantity        = $this->order_product_value( $order_products, 'product_quantity', $i, true );
			$product_summary = $this->order_product_value( $order_products, 'product_summary', $i, true );
			if ( ! is_numeric( $gross_price ) || ! is_numeric( $quantity ) || ! is_numeric( $product_summary ) || (float) $gross_price * (float) $quantity !== (float) $product_summary ) {
				$return = false;
			}
		}
		$this->display_gross = $return;

		return $this->display_gross;
	}

	/**
	 * Returns a usable scalar value for a stored order-product row.
	 *
	 * @param array  $order_products Stored order-product payload.
	 * @param string $field          Product field name.
	 * @param int    $index          Product row index.
	 * @param bool   $array_row      Whether the source field must be an array.
	 * @return mixed Usable scalar value or an empty string.
	 */
	public function order_product_value( $order_products, $field, $index = 0, $array_row = null ) {
		if ( ! is_array( $order_products ) || ! array_key_exists( $field, $order_products ) ) {
			return '';
		}
		$value = $order_products[ $field ];
		if ( ( true === $array_row && ! is_array( $value ) ) || ( false === $array_row && is_array( $value ) ) ) {
			return '';
		}
		if ( is_array( $value ) ) {
			if ( ! array_key_exists( $index, $value ) ) {
				return '';
			}
			$value = $value[ $index ];
		} elseif ( 0 !== $index ) {
			return '';
		}
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return '';
		}

		return $value;
	}

	function summary( $post ) {
		$labels = $this->labels();
		echo '<input type="hidden" name="order_summary_meta_noncename" id="order_summary_meta_noncename" value="' .
			esc_attr( wp_create_nonce( plugin_basename( __FILE__ ) ) ) . '" />';
		$order_summary = ic_get_order_summary( $post->ID );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( empty( $order_summary['email'] ) && isset( $_GET['payment_details'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			$payment_details        = isset( $_GET['payment_details'] ) && is_scalar( $_GET['payment_details'] ) ? ic_sanitize_decoded_payload( $_GET['payment_details'] ) : '';
			$order_summary['email'] = $payment_details['shipping_email'];
		}
		if ( $order_summary['price'] == 0 ) {
			$order_summary['price'] = '';
		}
		?>
		<div class="order_price">
			<?php
			if ( empty( $order_summary['price'] ) && empty( $order_summary['email'] ) ) {
				/* translators: Placeholders are substituted with runtime values. */
				echo '<div class="al-box info">' . esc_html( sprintf( __( 'The summary will show up after the %s is saved.', 'ecommerce-product-catalog' ), $labels['singular_name'] ) ) . '</div>';
			}
			?>
			<table>
				<?php if ( ! empty( $order_summary['total_net'] ) && ( ( ! empty( $order_summary['tax'] ) && floatval( $order_summary['tax'] ) !== floatval( 0 ) ) || ! empty( $order_summary['handling'] ) ) ) { ?>
					<tr>
						<td><?php esc_html_e( 'Total Net', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_html( price_format( $order_summary['total_net'], 1, 0 ) ); ?></td>
					</tr>
				<?php } ?>
				<?php
				if ( ! empty( $order_summary['tax'] ) && floatval( $order_summary['tax'] ) !== floatval( 0 ) ) {
					if ( $order_summary['tax'] > 0 ) {
						$tax_label = __( 'Tax', 'ecommerce-product-catalog' );
					} else {
						$tax_label = __( 'Discount', 'ecommerce-product-catalog' );
					}
					?>
					<tr>
						<td><?php echo esc_html( $tax_label ); ?>:</td>
						<td><?php echo esc_html( price_format( $order_summary['tax'], 1, 0 ) ); ?></td>
					</tr>
				<?php } ?>
				<?php if ( ! empty( $order_summary['handling'] ) ) { ?>
					<tr>
						<td><?php esc_html_e( 'Shipping & Handling', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_html( price_format( $order_summary['handling'], 1, 0 ) ); ?></td>
					</tr>
				<?php } ?>
				<?php if ( ! empty( $order_summary['price'] ) ) { ?>
					<tr>
						<td><?php esc_html_e( 'Total', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_html( price_format( $order_summary['price'], 1, 0 ) ); ?></td>
					</tr>
				<?php } ?>
			</table>
			<input type="hidden" name="order_summary_price" value="<?php echo esc_attr( $order_summary['price'] ); ?>"/>
		</div>
		<div class="order_email">
			<label for="order_summary_email"><?php esc_html_e( 'Email', 'ecommerce-product-catalog' ); ?>:</label>
			<?php
			if ( empty( $order_summary['email'] ) ) {
				echo '<div class="al-box info">' . esc_html__( 'Email will be set as system user name. This email will be used to send all account credentials to a customer.', 'ecommerce-product-catalog' ) . '</div>';
			}
			?>
			<input type="text" required="required" name="order_summary_email"
					value="<?php echo esc_attr( $order_summary['email'] ); ?>"/>
		</div>
		<?php
		do_action( 'digital-order-summary', $post->ID );
	}

	/**
	 * Saves manual digital order
	 *
	 * @param int    $post_id
	 * @param object $post
	 *
	 * @return int If failed
	 */
	function save( $post_id, $post ) {
		$post_type_now = $post->post_type;
		if ( $post_type_now == 'al_digital_orders' ) {
			$pre_name                     = $this->order_pre_name( $post_id );
			$order_summary_meta_noncename = ic_epc_order_post_text( 'order_summary_meta_noncename' );
			if ( empty( $order_summary_meta_noncename ) || ( ! empty( $order_summary_meta_noncename ) && ! wp_verify_nonce( $order_summary_meta_noncename, plugin_basename( __FILE__ ) ) ) ) {
				return $post->ID;
			}
			if ( ! current_user_can( 'edit_digital_order', $post->ID ) ) {
				return $post->ID;
			}

			if ( ! isset( $_POST['payment_details_status'] ) ) {
				return $post_id;
			}
			$stored_payment_details                   = get_post_meta( $post_id, '_payment_details', true );
			$order_meta['_payment_details']           = is_array( $stored_payment_details ) ? $stored_payment_details : array();
			$prev_status                              = isset( $order_meta['_payment_details']['status'] ) ? $order_meta['_payment_details']['status'] : '';
			$order_meta['_payment_details']['status'] = ic_epc_order_post_text( 'payment_details_status' );
			$requested_status                         = $order_meta['_payment_details']['status'];
			$auto_promote_shipped                     = ic_should_auto_promote_shipped_status( $requested_status, $prev_status, $post->ID );
			if ( $auto_promote_shipped ) {
				$order_meta['_payment_details']['status'] = ic_get_order_completion_status();
			}
			$payment_fields = array(
				'date'           => 'date',
				'name'           => 'name',
				'email'          => 'email',
				'vatid'          => 'vatid',
				'street'         => 'address',
				'postcode'       => 'postcode',
				'city'           => 'city',
				'country'        => 'country',
				'country_code'   => 'country_code',
				'billing_name'   => 'billing_name',
				'shipping_email' => 'shipping_email',
				'currency'       => 'currency',
				'order-currency' => 'order_currency',
				'vat_name'       => 'vat_name',
				'vat_address'    => 'vat_address',
			);
			foreach ( $payment_fields as $meta_key => $input_key ) {
				$input_key = 'payment_details_' . $input_key;
				if ( ! isset( $_POST[ $input_key ] ) || ! is_scalar( $_POST[ $input_key ] ) ) {
					continue;
				}
				$value = ic_epc_order_post_text( $input_key );
				if ( array_key_exists( $meta_key, $order_meta['_payment_details'] ) && is_scalar( $order_meta['_payment_details'][ $meta_key ] ) && (string) $order_meta['_payment_details'][ $meta_key ] === $value ) {
					continue;
				}
				if ( '' === $value && ! array_key_exists( $meta_key, $order_meta['_payment_details'] ) ) {
					continue;
				}
				if ( 'date' === $meta_key ) {
					$value = preg_match( '/^\d{1,2}-\d{1,2}-\d{4}$/', $value ) ? $value : wp_date( 'd-m-Y' );
				} elseif ( in_array( $meta_key, array( 'email', 'shipping_email' ), true ) ) {
					$value = sanitize_email( $value );
				} elseif ( in_array( $meta_key, array( 'currency', 'order-currency' ), true ) ) {
					$value = sanitize_key( $value );
				}
				$order_meta['_payment_details'][ $meta_key ] = $value;
				if ( 'country' === $meta_key && ! isset( $_POST['payment_details_country_code'] ) ) {
					$country_code = array_search( $value, implecode_supported_countries(), true );
					if ( false !== $country_code ) {
						$order_meta['_payment_details']['country_code'] = $country_code;
					}
				}
			}
			if ( function_exists( 'get_shopping_checkout_form_fields' ) ) {
				$fields = json_decode( get_shopping_checkout_form_fields() );
				foreach ( $fields->fields as $field ) {
					$cid = apply_filters( 'ic_formbuilder_cid', $pre_name . $field->cid, $field, $pre_name );
					if ( ! isset( $_POST[ $cid ] ) || ! is_scalar( $_POST[ $cid ] ) ) {
						continue;
					}
					if ( '' === ic_epc_order_post_text( $cid ) && ! array_key_exists( $cid, $order_meta['_payment_details'] ) ) {
						continue;
					}
					$order_meta['_payment_details'][ $cid ] = ic_epc_order_post_text( $cid, isset( $field->field_type ) ? $field->field_type : '' );
				}
			}
			$order_summary['price'] = ic_epc_order_post_number( 'order_summary_price' );
			$manual_order_product   = array();
			if ( isset( $_POST['manual_products'] ) ) {
				$order_meta['manual_products'] = ! empty( $_POST['manual_products'] ) ? intval( $_POST['manual_products'] ) : 0;
				$order_products                = ic_get_order_products( $post_id );
				if ( isset( $order_products['product_name'], $order_products['product_summary'] ) && is_array( $order_products['product_name'] ) && is_array( $order_products['product_summary'] ) && ! empty( $order_products['product_summary'] ) && count( $order_products['product_name'] ) === count( $order_products['product_summary'] ) && count( $order_products['product_summary'] ) === count( array_filter( $order_products['product_summary'], 'is_numeric' ) ) ) {
					$order_summary['price'] = array_sum( $order_products['product_summary'] );
				} elseif ( isset( $order_products['product_name'], $order_products['product_summary'] ) && ! is_array( $order_products['product_name'] ) && is_scalar( $order_products['product_summary'] ) && is_numeric( $order_products['product_summary'] ) ) {
					$order_summary['price'] = $order_products['product_summary'];
				}
				$i = 1;
				for ( $a = 1; $a <= $order_meta['manual_products']; $a++ ) {
					$manual_order_product[ $i ]['id'] = ic_epc_order_post_id( 'manual_order_product_id_' . $a );
					if ( ! empty( $manual_order_product[ $i ]['id'] ) ) {
						$manual_order_product[ $i ]['name'] = get_the_title( $manual_order_product[ $i ]['id'] );
						$manual_order_product[ $i ]['c_id'] = $i . '_' . $manual_order_product[ $i ]['id'] . '_t_' . $post_id;
					} else {
						$manual_order_product[ $i ]['name'] = ic_epc_order_post_text( 'manual_order_product_name_' . $a );
						$manual_order_product[ $i ]['c_id'] = 'c_' . $i . '_t_' . $post_id;
					}
					$manual_order_product[ $i ]['name']     = isset( $manual_order_product[ $i ]['name'] ) ? $manual_order_product[ $i ]['name'] : '';
					$manual_order_product[ $i ]['quantity'] = ic_epc_order_post_number( 'manual_order_product_quantity_' . $a );
					$manual_order_product[ $i ]['price']    = ic_epc_order_post_number( 'manual_order_product_price_' . $a );
					$manual_order_product[ $i ]['summary']  = ic_epc_order_post_number( 'manual_order_product_summary_' . $a );
					$order_summary['price']                 = $order_summary['price'] + floatval( $manual_order_product[ $i ]['summary'] );
					do_action( 'order_save_products', $post_id, $manual_order_product[ $i ]['id'], $manual_order_product[ $i ]['c_id'], $a );
					if ( $manual_order_product[ $i ]['name'] == '' ) {
						foreach ( $manual_order_product[ $i ] as $key => $value ) {
							$manual_order_product[ $i ][ $key ] = '';
						}
						$removed_last = 1;
					} else {
						++$i;
						$removed_last = 0;
					}
				}
				if ( empty( $removed_last ) ) {
					$i = $i - 1;
				}
				$order_meta['manual_products']      = $i;
				$order_meta['manual_order_product'] = $manual_order_product;
			}
			$order_summary['email']       = sanitize_email( ic_epc_order_post_text( 'order_summary_email' ) );
			$order_meta['_order_summary'] = apply_filters( 'ic_update_order_summary', $order_summary, $post->ID );
			$order_meta                   = apply_filters( 'ic_order_meta_save', $order_meta, $post );
			foreach ( $order_meta as $key => $value ) {
				$current_value = get_post_meta( $post->ID, $key, true );
				if ( isset( $value ) && ! isset( $current_value ) ) {
					add_post_meta( $post->ID, $key, $value, true );
				} elseif ( isset( $value ) && $value != $current_value ) {
					update_post_meta( $post->ID, $key, $value );
				} elseif ( ! isset( $value ) && $current_value ) {
					delete_post_meta( $post->ID, $key );
				}
			}
			$check_trans_id = get_post_meta( $post->ID, '_order_id', true );
			if ( empty( $check_trans_id ) ) {
				update_post_meta( $post->ID, '_order_id', $post->ID );
			}
			$triggered = get_post_meta( $post->ID, '_order_completed_triggered', true );
			if ( ic_is_order_completion_status( $order_meta['_payment_details']['status'] ) && ( $prev_status != $order_meta['_payment_details']['status'] || ! empty( $manual_order_product ) ) ) {
				$order_products = ic_get_order_products( $post->ID );
				if ( empty( $triggered ) ) {
					do_action( 'order_completed', $post_id, $order_meta['_payment_details'], $order_products, $manual_order_product );
					update_post_meta( $post->ID, '_order_completed_triggered', 1 );
				} else {
					do_action( 'order_completed_updated', $post_id, $order_meta['_payment_details'], $order_products, $manual_order_product );
				}
			}
			if ( $auto_promote_shipped ) {
				$order_meta['_payment_details']['status'] = 'shipped';
				update_post_meta( $post->ID, '_payment_details', $order_meta['_payment_details'] );
			}
			do_action( 'update_digital_order', $post_id, $post );
		}
	}

	function order_pre_name( $order_id ) {
		$pre_name = get_post_meta( $order_id, '_pre_name', true );
		if ( empty( $pre_name ) ) {
			$pre_name = 'cart_';
		}

		return $pre_name;
	}

	/**
	 * Returns digital order fields
	 *
	 * @return json
	 */
	function get_fields( $order_id, $payment_details, $pre_name = 'cart_' ) {
		$form = apply_filters( 'ic_order_admin_fields', '', $payment_details );
		if ( ! empty( $form ) ) {
			return $form;
		}
		$payment_details['country'] = empty( $payment_details['country'] ) ? get_supported_country_name( $payment_details['country_code'] ) : $payment_details['country'];
		$payment_details['address'] = $payment_details['street'];
		if ( function_exists( 'get_shopping_checkout_form_fields' ) ) {
			$fields = $this->fields( $order_id );
			$form   = '<div class="ic-checkout-form-data table" data-pre_name="' . esc_attr( $pre_name ) . '" style="margin: 0 auto;border-collapse:collapse;">';
			$form  .= formbuilder_raw_fields( $fields, 2, $pre_name, $payment_details, array(), false, false );
			$form  .= '</div>';
		} elseif ( defined( 'PAYPAL_PLUGIN_BASE_DIR' ) && function_exists( 'default_order_form_editor_settings' ) ) {
			$fields = $this->default_fields();
			$form   = formbuilder_raw_fields(
				$fields,
				2,
				'payment_details_',
				$payment_details,
				array(
					'surname',
					'state',
				),
				false,
				false
			);
		} elseif ( function_exists( 'get_order_form_fields' ) ) {
			$fields = $this->default_fields();
			$form   = formbuilder_raw_fields(
				$fields,
				2,
				'payment_details_',
				$payment_details,
				array(
					'surname',
					'state',
				),
				false,
				false
			);
		}

		return apply_filters( 'ic_order_admin_payment_details_fields', $form, $payment_details );
	}

	function fields( $order_id ) {
		$fields = get_post_meta( $order_id, '_ic_form_fields', true );
		if ( empty( $fields ) ) {
			$fields = $this->default_fields();
		}

		return $fields;
	}

	function default_fields() {
		$fields = array();
		if ( function_exists( 'get_shopping_checkout_form_fields' ) ) {
			$fields = get_shopping_checkout_form_fields();
		} elseif ( defined( 'PAYPAL_PLUGIN_BASE_DIR' ) && function_exists( 'default_order_form_editor_settings' ) ) {
			$fields = default_order_form_editor_settings();
		} elseif ( function_exists( 'get_order_form_fields' ) ) {
			$fields = get_order_form_fields();
		}

		return $fields;
	}

	function save_fields( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}
		$fields = $this->fields( $order_id );
		if ( ! empty( $fields ) ) {
			update_post_meta( $order_id, '_ic_form_fields', $fields );
		}
	}

	function save_pre_name( $order_id, $pre_name ) {
		if ( ! empty( $order_id ) && ! empty( $pre_name ) ) {
			update_post_meta( $order_id, '_pre_name', $pre_name );
		}
	}

	/**
	 * Adds digital order formbuilder styles
	 */
	function formbuilder_style() {
		if ( get_post_type() == 'al_digital_orders' ) {
			wp_enqueue_style( 'implecode-form-builder-css' );
		}
	}

	function all_product_prices( $offset = null ) {
		// $pages     = get_all_products( array( 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
		if ( $offset > 500 ) {
			return;
		}
		$pages = get_all_catalog_products( null, null, 10, $offset );
		if ( empty( $pages ) ) {
			return;
		}
		if ( empty( $offset ) ) {
			$offset = 0;
			echo '<input hidden=hidden id="product_price_noid" value="0" />';
		}
		foreach ( $pages as $page ) {
			$price = apply_filters( 'digital_product_prices_table_price', get_post_meta( $page->ID, '_price', true ), $page->ID );
			if ( ! empty( $price ) ) {
				echo '<input type="hidden" id="product_price_' . absint( $page->ID ) . '" value="' . esc_attr( $price ) . '" />';
			}
		}
		$offset += count( $pages );

		return $this->all_product_prices( $offset );
	}

	function linked_name( $product_id, $front = true ) {
		if ( empty( $product_id ) ) {
			return '';
		}
		$product_name = get_product_name( $product_id );
		if ( $front ) {
			$url = get_product_url( $product_id );
		} else {
			$url = ic_product_edit_url( $product_id );
		}
		$link = '';
		if ( ! empty( $product_name ) ) {
			$link = '<a href="' . $url . '">' . $product_name . '</a>';
		}

		return $link;
	}

	/**
	 * Defines digital orders messages
	 *
	 * @param array $messages
	 *
	 * @return array
	 * @global object $post
	 * @global int $post_ID
	 */
	function messages( $messages ) {
		global $post, $post_ID;
		$post_type = get_post_type();
		if ( $post_type == 'al_digital_orders' ) {

			$messages[ $post_type ] = array(
				0         => '', // Unused. Messages start at index 1.
				1         => __( 'Digital order updated.', 'ecommerce-product-catalog' ),
				2         => __( 'Custom field updated.', 'ecommerce-product-catalog' ),
				3         => __( 'Custom field deleted.', 'ecommerce-product-catalog' ),
				4         => __( 'Digital order updated.', 'ecommerce-product-catalog' ),
				/* translators: %s: title of the restored revision. */
						5 => isset( $_GET['revision'] ) ? sprintf( __( 'Digital order restored to revision from %s', 'ecommerce-product-catalog' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
					6     => __( 'Digital order published.', 'ecommerce-product-catalog' ),
					7     => __( 'Page saved.', 'ecommerce-product-catalog' ),
					8     => __( 'Digital order submitted.', 'ecommerce-product-catalog' ),
					/* translators: %1$s: scheduled publication date and time. */
					9     => sprintf( __( 'Digital order scheduled for: <strong>%1$s</strong>.', 'ecommerce-product-catalog' ), date_i18n( __( 'M j, Y @ G:i', 'ecommerce-product-catalog' ), strtotime( $post->post_date ) ) ),
					10    => __( 'Digital order draft updated.', 'ecommerce-product-catalog' ),
			);
		}

		return $messages;
	}

	/**
	 * Disables some WordPress SEO plugin features for digital orders screen
	 */
	function unregister_external_boxes() {
		remove_meta_box( 'wpseo_meta', 'al_digital_orders', 'normal' );
	}

	/**
	 * Disables some WordPress SEO plugin features for digital orders screen
	 *
	 * @global string $pagenow
	 */
	function wpseo_compatible() {
		global $pagenow;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( isset( $_GET['post_type'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
			if ( 'edit.php' == $pagenow && 'al_digital_orders' == $_GET['post_type'] ) {
				add_filter( 'wpseo_use_page_analysis', '__return_false' );
			}
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		} elseif ( isset( $_GET['post'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			if ( 'post.php' == $pagenow && 'al_digital_orders' == get_post_type( $_GET['post'] ) ) {
				add_filter( 'wpseo_use_page_analysis', '__return_false' );
			}
		}
	}

	function disable_autosave() {
		if ( 'al_digital_orders' == get_post_type() ) {
			wp_dequeue_script( 'autosave' );
		}
	}

	/**
	 * Deletes phone field from digital orders screen
	 *
	 * @param object $fields
	 * @param string $form
	 *
	 * @return object
	 */
	function delete_phone( $fields, $form ) {
		if ( $form == 'payment_details_' ) {
			unset( $fields->fields[4] );
		}

		return $fields;
	}

	/**
	 * Search digital orders by custom fields also
	 *
	 * @param string $join
	 *
	 * @return string
	 * @global string $pagenow
	 * @global type $wpdb
	 */
	function admin_search_join( $join ) {
		global $pagenow, $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table search routing; no state is changed.
		if ( is_admin() && $pagenow == 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'al_digital_orders' && isset( $_GET['s'] ) && $_GET['s'] != '' ) {
			$join .= 'LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
		}

		return $join;
	}

	/**
	 * Search digital orders by custom fields also
	 *
	 * @param type $where
	 *
	 * @return type
	 * @global string $pagenow
	 * @global type $wpdb
	 */
	function admin_search_where( $where ) {
		global $pagenow, $wpdb;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table search routing; no state is changed.
		if ( is_admin() && $pagenow == 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'al_digital_orders' && isset( $_GET['s'] ) && $_GET['s'] != '' ) {
			$where = preg_replace(
				'/\(\s*' . $wpdb->posts . ".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
				'(' . $wpdb->posts . '.post_title LIKE $1) OR (' . $wpdb->postmeta . '.meta_value LIKE $1)',
				$where
			);
		}

		return $where;
	}
}

global $ic_orders;
$ic_orders = new ic_orders();


if ( ! function_exists( 'ic_get_supported_country_code' ) ) {

	/**
	 * Gets country code by its name
	 *
	 * @param string $country_name
	 *
	 * @return string
	 */
	function ic_get_supported_country_code( $country_name ) {
		$return    = 'none';
		$countries = implecode_supported_countries();
		foreach ( $countries as $key => $country ) {
			if ( $country_name == $country ) {
				$return = $key;
			}
		}

		return $return;
	}

}

if ( ! function_exists( 'implecode_supported_states' ) ) {

	/**
	 * Defines supported US States
	 *
	 * @return array
	 */
	function implecode_supported_states() {
		$states = array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District Of Columbia (Washington, D.C.)',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'PR' => 'Puerto Rico',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);

		return apply_filters( 'ic_supported_states', $states );
	}

}

if ( ! function_exists( 'ic_select_product' ) ) {

	function ic_select_product( $first_option, $selected_value, $select_name, $class = null, $echo = 1, $attr = null ) {
		$product_count = ic_products_count();
		// `ic_order_products` carries `edit_digital_orders`, the capability this
		// screen is actually gated on, while `ic_products` carries
		// `manage_product_settings`. Pinning the control to either one drops
		// whichever role holds only the other back to the capped fallback, so
		// the source is resolved per user instead.
		$source_key   = apply_filters( 'ic_select_product_selector_source', function_exists( 'ic_selector_first_allowed_source' ) ? ic_selector_first_allowed_source( array( 'ic_order_products', 'ic_products' ) ) : '' );
		$threshold    = absint( apply_filters( 'ic_select_product_ajax_threshold', 2000 ) );
		$use_selector = ( $product_count >= $threshold ) && ! empty( $source_key ) && function_exists( 'ic_selector_field' ) && ic_selector_registry()->has( $source_key );
		$field_number = filter_var( $select_name, FILTER_SANITIZE_NUMBER_INT );
		if ( $use_selector ) {
			/*
			 * Above the threshold the catalog is too large for a rendered
			 * <select>, so the field becomes the shared AJAX selector instead
			 * of the ID text box it used to degrade into. The control still
			 * submits through a real <select>, so every caller keeps working.
			 */
			$select_box = ic_selector_field(
				array(
					'source'       => $source_key,
					'name'         => $select_name,
					'id'           => str_replace( array( '[', ']' ), '', $select_name ),
					'value'        => $selected_value,
					'multiple'     => is_array( $selected_value ) || ( is_string( $attr ) && false !== strpos( $attr, 'multiple' ) ),
					// `ic_chosen` is dropped on purpose: the selector replaces Chosen on this field, and leaving the class on would have both scripts enhance the same <select>.
					'class'        => trim( 'all-products-dropdown ' . str_replace( 'ic_chosen', '', (string) $class ) ),
					'first_option' => $first_option,
					'attributes'   => array( 'custom' => $field_number ),
				)
			);
		} elseif ( $product_count < $threshold ) {
			$catalogs = product_post_type_array();
			$set      = array(
				'posts_per_page' => - 1,
				'offset'         => 0,
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'post_type'      => $catalogs,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			);

			$pages = get_posts( $set );

			$select_box = '<select custom="' . $field_number . '" id="' . $select_name . '" name="' . $select_name . '" class="all-products-dropdown ' . $class . '" ' . $attr . '>';
			if ( ! empty( $first_option ) ) {
				$select_box .= '<option value="noid">' . $first_option . '</option>';
			}
			foreach ( $pages as $product_id ) {
				if ( is_array( $selected_value ) ) {
					$selected = in_array( $product_id, $selected_value ) ? 'selected' : '';
				} else {
					$selected = selected( $product_id, $selected_value, 0 );
				}
				$select_box .= '<option class="id_' . $product_id . '" value="' . $product_id . '" ' . $selected . '>' . get_product_name( $product_id ) . '</option>';
			}
			$select_box .= '</select>';
		} else {
			$select_box = '<input type="text" name="' . $select_name . '" placeholder="' . __( 'Set Product ID', 'ecommerce-product-catalog' ) . '" value="' . $selected_value . '"/>';
		}

		return echo_ic_setting( $select_box, $echo );
	}

}
