<?php
/**
 * Feature-owned saved email templates and generated preview data.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
/** Saved defaults and previews for the owning cart feature. */
final class IC_Cart_Email_Design {
	/**
	 * Register the feature integration hooks.
	 */
	public static function boot() {
		add_filter( 'ic_email_design_previews', array( __CLASS__, 'register' ), 20 );
		add_filter( 'ic_shopping_cart_settings', array( __CLASS__, 'resolve' ), 100 );
		add_filter( 'ic_email_design_message_context', array( __CLASS__, 'message_context' ), 10, 2 );
		if ( class_exists( 'IC_Email_Design' ) && method_exists( 'IC_Email_Design', 'keep_on_settings_save' ) ) {
			IC_Email_Design::keep_on_settings_save( 'shopping_cart_settings', array( 'admin_email_subject', 'admin_email', 'admin_email_heading', 'user_email_subject', 'user_email', 'user_email_heading' ) );
		}
	}
	/**
	 * Editable content of one cart email, stored in the cart settings it uses today.
	 *
	 * @param string $kind Email kind (order or inquiry).
	 * @param string $role Recipient role (user or admin).
	 * @return array
	 */
	public static function content( $kind, $role ) {
		$field  = 'admin' === $role ? 'admin_email' : 'user_email';
		$option = 'shopping_cart_settings';
		$tokens = array(
			'[order_summary]'    => __( 'Order number and date box', 'ecommerce-product-catalog' ),
			'[customer_name]'    => __( 'Customer name', 'ecommerce-product-catalog' ),
			'[customer_details]' => __( 'Products, totals and customer details', 'ecommerce-product-catalog' ),
			'[account_info]'     => __( 'Customer account link', 'ecommerce-product-catalog' ),
			'[edit_order_url]'   => __( 'Link to edit the order', 'ecommerce-product-catalog' ),
			'[payment_details]'  => __( 'Payment details', 'ecommerce-product-catalog' ),
			'[order_button]'     => __( 'Button to the order in the customer account', 'ecommerce-product-catalog' ),
		);
		$lists  = array(
			'order'   => array(
				'user'  => array( '[order_summary]', '[customer_name]', '[customer_details]', '[account_info]', '[payment_details]', '[order_button]' ),
				'admin' => array( '[order_summary]', '[customer_details]', '[edit_order_url]', '[payment_details]' ),
			),
			'inquiry' => array(
				'user'  => array( '[order_summary]', '[customer_name]', '[customer_details]', '[account_info]', '[order_button]' ),
				'admin' => array( '[order_summary]', '[customer_details]', '[edit_order_url]' ),
			),
		);
		$about  = array(
			'order'   => array(
				'user'  => __( 'Sent to the customer after checkout.', 'ecommerce-product-catalog' ),
				'admin' => __( 'Sent to you for every new order.', 'ecommerce-product-catalog' ),
			),
			'inquiry' => array(
				'user'  => __( 'Sent to the customer after an inquiry.', 'ecommerce-product-catalog' ),
				'admin' => __( 'Sent to you for every new inquiry.', 'ecommerce-product-catalog' ),
			),
		);
		$kind   = isset( $lists[ $kind ] ) ? $kind : 'order';
		$picked = array();
		foreach ( $lists[ $kind ][ $role ] as $token ) {
			$picked[ $token ] = $tokens[ $token ];
		}
		return array(
			'option_group'    => 'shopping_cart',
			'settings_filter' => 'ic_shopping_cart_settings',
			'subject'         => array(
				'option' => $option,
				'key'    => $role . '_email_subject',
			),
			'heading'         => array(
				'option' => $option,
				'key'    => $role . '_email_heading',
			),
			'text'            => array(
				'option' => $option,
				'key'    => $field,
			),
			'text_sanitize'   => 'sanitize_textarea_field',
			'values'          => static function () use ( $role, $field ) {
				$settings = get_shopping_cart_settings();
				return array(
					'subject' => $settings[ $role . '_email_subject' ] ?? '',
					'text'    => $settings[ $field ] ?? '',
				);
			},
			'tokens'          => $picked,
			'settings_label'  => 'admin' === $role ? __( 'Admin email', 'ecommerce-product-catalog' ) : __( 'Customer email', 'ecommerce-product-catalog' ),
			'description'     => $about[ $kind ][ $role ],
		);
	}
	/** Only the initialized owner of these settings may register or resolve them. */
	public static function active() {
		if ( ! function_exists( 'get_shopping_cart_settings' ) ) {
			return false; }
		$getter = new ReflectionFunction( 'get_shopping_cart_settings' );
		return 0 === strpos( $getter->getFileName(), dirname( __DIR__ ) . '/' ) && in_array( ic_get_catalog_mode(), array( 'store', 'inquiry' ), true );
	}
	/**
	 * Get the kind of customer message.
	 *
	 * @return string
	 */
	public static function kind() {
		return function_exists( 'ic_get_catalog_mode' ) && 'inquiry' === ic_get_catalog_mode() ? 'inquiry' : 'order'; }
	/**
	 * Get the shipped Classic defaults without modifying storage.
	 *
	 * @param bool $english Whether to return untranslated defaults.
	 * @return array
	 */
	public static function classic( $english = false ) {
		$raw       = get_option( 'shopping_cart_settings', array() );
		$site_name = ! empty( $raw['cart_name'] ) ? $raw['cart_name'] : get_bloginfo( 'name' );
		$defaults  = array(
			'user_email'          => ( $english ? 'Dear Customer,' : __( 'Dear Customer,', 'ecommerce-product-catalog' ) ) . ' ' . "\n\r" . ( $english ? 'Thank you for your order.' : __( 'Thank you for your order.', 'ecommerce-product-catalog' ) ) . ' ' . "\r" . '[customer_details] ' . "\n\r" . '[account_info] ' . "\n\r" . ( $english ? 'We will get in touch with you soon.' : __( 'We will get in touch with you soon.', 'ecommerce-product-catalog' ) ) . "\n\r" . '[payment_details]' . "\n\r" . ( $english ? 'Kind regards,' : __( 'Kind regards,', 'ecommerce-product-catalog' ) ) . "\r" . 'impleCode Team',
			'admin_email'         => ( $english ? 'New order from a customer.' : __( 'New order from a customer.', 'ecommerce-product-catalog' ) ) . "\n\r" . ( $english ? 'Order details' : __( 'Order details', 'ecommerce-product-catalog' ) ) . ': ' . "\r" . '[customer_details]' . "\n\r" . '[edit_order_url]' . "\n\r" . '[payment_details]' . "\n\r" . ( $english ? 'Kind regards,' : __( 'Kind regards,', 'ecommerce-product-catalog' ) ) . "\r" . 'impleCode Team',
			'user_email_subject'  => sprintf(
			/* translators: %s: Site name. */
				( $english ? 'New Order on %s' : /* translators: %s: Site name. */ __( 'New Order on %s', 'ecommerce-product-catalog' ) ),
				$site_name
			),
			'admin_email_subject' => sprintf(
			/* translators: %s: Site name. */
				( $english ? 'Order from %s' : /* translators: %s: Site name. */ __( 'Order from %s', 'ecommerce-product-catalog' ) ),
				$site_name
			),
		);
		if ( 'inquiry' === self::kind() ) {
			foreach ( array( 'user_email', 'admin_email' ) as $key ) {
				$defaults[ $key ] = str_replace( array( ( $english ? 'order' : __( 'order', 'ecommerce-product-catalog' ) ), ( $english ? 'Order' : __( 'Order', 'ecommerce-product-catalog' ) ), '[payment_details]' . "\n\r" ), array( ( $english ? 'quote' : __( 'quote', 'ecommerce-product-catalog' ) ), ( $english ? 'Quote' : __( 'Quote', 'ecommerce-product-catalog' ) ), '' ), $defaults[ $key ] );
			}
		}

		if ( ! $english ) {
			$defaults['user_email']  = apply_filters( 'ic_simple_cart_default_customer_email_text', $defaults['user_email'] );
			$defaults['admin_email'] = apply_filters( 'ic_simple_cart_default_admin_email_text', $defaults['admin_email'] ); }

		return $defaults;
	}
	/**
	 * Get the shipped Modern defaults without modifying storage.
	 *
	 * @param bool $english Whether to return untranslated defaults.
	 * @return array
	 */
	public static function modern( $english = false ) {
		$kind   = self::kind();
		$titles = array(
			'order'   => array( ( $english ? 'Thank you for your order' : __( 'Thank you for your order', 'ecommerce-product-catalog' ) ), ( $english ? 'A new order has arrived' : __( 'A new order has arrived', 'ecommerce-product-catalog' ) ) ),
			'inquiry' => array( ( $english ? 'Thank you for your inquiry' : __( 'Thank you for your inquiry', 'ecommerce-product-catalog' ) ), ( $english ? 'A new inquiry has arrived' : __( 'A new inquiry has arrived', 'ecommerce-product-catalog' ) ) ),
			'quote'   => array( ( $english ? 'Thank you for your quote request' : __( 'Thank you for your quote request', 'ecommerce-product-catalog' ) ), ( $english ? 'A new quote request has arrived' : __( 'A new quote request has arrived', 'ecommerce-product-catalog' ) ) ),
		);
		$copy   = array(
			'order'   => ( $english ? 'Hi [customer_name], we\'ve received your order and will let you know when it\'s on its way.' : __( 'Hi [customer_name], we\'ve received your order and will let you know when it\'s on its way.', 'ecommerce-product-catalog' ) ),
			'inquiry' => ( $english ? 'Hi [customer_name], we\'ve received your inquiry and will get back to you soon.' : __( 'Hi [customer_name], we\'ve received your inquiry and will get back to you soon.', 'ecommerce-product-catalog' ) ),
			'quote'   => ( $english ? 'Hi [customer_name], we\'ve received your quote request and will get back to you soon.' : __( 'Hi [customer_name], we\'ve received your quote request and will get back to you soon.', 'ecommerce-product-catalog' ) ),
		);
		$admin  = array(
			'order'   => ( $english ? 'A customer has placed an order.' : __( 'A customer has placed an order.', 'ecommerce-product-catalog' ) ),
			'inquiry' => ( $english ? 'A customer has sent an inquiry.' : __( 'A customer has sent an inquiry.', 'ecommerce-product-catalog' ) ),
			'quote'   => ( $english ? 'A customer has requested a quote.' : __( 'A customer has requested a quote.', 'ecommerce-product-catalog' ) ),
		);
		return array(
			'user_email'          => "[order_summary]\n\n" . $copy[ $kind ] . "\n\n[customer_details]" . ( 'quote' === $kind ? '' : "\n\n[account_info]" ) . ( 'order' === $kind ? "\n\n[payment_details]" : '' ) . "\n\n[order_button]",
			'admin_email'         => "[order_summary]\n\n" . $admin[ $kind ] . "\n\n[customer_details]" . ( 'quote' === $kind ? '' : "\n\n[edit_order_url]" ) . ( 'order' === $kind ? "\n\n[payment_details]" : '' ),
			'user_email_subject'  => $titles[ $kind ][0],
			'admin_email_subject' => $titles[ $kind ][1],
		);
	}
	/**
	 * Resolve layout defaults while retaining customized values.
	 *
	 * @param array $settings Feature settings.
	 * @return array
	 */
	public static function resolve( $settings ) {
		if ( ! class_exists( 'IC_Email_Design' ) || ! self::active() ) {
			return $settings; }
		$raw            = get_option( 'shopping_cart_settings', array() );
		$classic        = self::classic();
		$english        = self::classic( true );
		$modern         = self::modern();
		$modern_english = self::modern( true );
		foreach ( $classic as $key => $default ) {
			$storage_key = 'admin_email' === $key ? 'admin_email' : $key;
			$value       = array_key_exists( $storage_key, (array) $raw ) ? $raw[ $storage_key ] : null;

			$settings[ $storage_key ] = IC_Email_Design::effective( $value, $default, $modern[ $key ], array( $english[ $key ], $modern_english[ $key ] ) );
		}
		return $settings;
	}
	/**
	 * Register generated previews for the initialized feature.
	 *
	 * @param array $previews Existing preview definitions.
	 * @return array
	 */
	public static function register( $previews ) {
		if ( ! self::active() || ! class_exists( 'IC_Email_Design' ) || ! function_exists( 'get_shopping_cart_settings' ) || ! class_exists( 'ic_cart_checkout_form_email' ) ) {
			return $previews; }
		if ( ! in_array( ic_get_catalog_mode(), array( 'store', 'inquiry' ), true ) ) {
			return $previews; }
		$settings = get_shopping_cart_settings();
		if ( ! is_email( $settings['send_cart'] ?? '' ) ) { return $previews; }
		$kind = self::kind();
		foreach ( array( 'user', 'admin' ) as $role ) {
			if ( 'admin' === $role && ! is_email( $settings['receive_cart'] ?? '' ) ) { continue; }
			$key              = $kind . ( 'admin' === $role ? '_admin' : '' );
			$field            = 'admin' === $role ? 'admin_email' : 'user_email';
			$classic          = self::classic();
			$english          = self::classic( true );
			$modern           = self::modern();
			$modern_english   = self::modern( true );
			$default_key      = 'admin' === $role ? 'admin_email' : 'user_email';
			$labels           = array(
				'order'   => array( __( 'Order confirmation', 'ecommerce-product-catalog' ), __( 'Admin order email', 'ecommerce-product-catalog' ), __( 'Customer order email', 'ecommerce-product-catalog' ) ),
				'inquiry' => array( __( 'Inquiry', 'ecommerce-product-catalog' ), __( 'Admin inquiry email', 'ecommerce-product-catalog' ), __( 'Customer inquiry email', 'ecommerce-product-catalog' ) ),
				'quote'   => array( __( 'Quote request', 'ecommerce-product-catalog' ), __( 'Admin quote email', 'ecommerce-product-catalog' ), __( 'Customer quote email', 'ecommerce-product-catalog' ) ),
			);
			$previews[ $key ] = array(
				'plugin'              => 'ecommerce-product-catalog',
				'group'               => 'eCommerce Product Catalog',
				'order'               => 'admin' === $role ? 20 : 10,
				'recipient'           => $role,
				'label'               => $labels[ $kind ][ 'admin' === $role ? 1 : 0 ],
				'customization_label' => $labels[ $kind ][ 'admin' === $role ? 1 : 2 ],
				'capability'          => 'manage_product_settings',
				'classic'             => $classic[ $default_key ],
				'modern'              => $modern[ $default_key ],
				'modern_title'        => $modern[ $role . '_email_subject' ],
				'defaults'            => array( $classic[ $default_key ], $english[ $default_key ], $modern[ $default_key ], $modern_english[ $default_key ] ),
				'read'                => static function () use ( $field ) {
					$raw = get_option( 'shopping_cart_settings', array() );
					return array(
						'exists' => array_key_exists( $field, (array) $raw ),
						'body'   => $raw[ $field ] ?? null,
					); },
				'write'               => static function ( $state ) use ( $field ) {
					$raw = get_option( 'shopping_cart_settings', array() );
					$raw = is_array( $raw ) ? $raw : array();
					if ( $state['exists'] ) {
						$raw[ $field ] = $state['body'];
					} else {
						unset( $raw[ $field ] );
					} update_option( 'shopping_cart_settings', $raw ); },
				'callback'            => static function () use ( $role ) {
					return self::preview( $role ); },
				'content'             => self::content( $kind, $role ),
			);
		}
		return $previews;
	}
	/**
	 * Get the configured customer panel URL.
	 *
	 * @return string
	 */
	public static function panel_url() {
		return function_exists( 'ic_customer_panel_panel_url' ) ? esc_url_raw( ic_customer_panel_panel_url(), array( 'http', 'https' ) ) : '';
	}
	/**
	 * Provide order tokens only for this feature sender.
	 *
	 * @param array       $context Existing token context.
	 * @param string|null $sender Sending feature.
	 * @return array
	 */
	public static function message_context( $context, $sender = null ) {
		if ( ! self::active() || ( 'order' === self::kind() ? 'cart' : self::kind() ) !== $sender ) {
			return $context; }
		global $ic_orders_save;
		$id      = ! empty( $ic_orders_save ) && method_exists( $ic_orders_save, 'current_order_id' ) ? $ic_orders_save->current_order_id() : 0;
		$date    = $id ? get_post_field( 'post_date', $id ) : '';
		$kind    = self::kind();
		$details = $id && function_exists( 'ic_get_order_payment_details' ) ? ic_get_order_payment_details( $id ) : array();
		$name    = $details['customer_name'] ?? $details['name'] ?? __( 'Customer', 'ecommerce-product-catalog' );
		return array(
			'customer_name' => $name,
			'number'        => $id ? $id : '',
			'date'          => $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '',
			'label'         => ucfirst( $kind ),
			'button_label'  => array(
				'order'   => __( 'View your order', 'ecommerce-product-catalog' ),
				'inquiry' => __( 'View your inquiry', 'ecommerce-product-catalog' ),
				'quote'   => __( 'View your quote', 'ecommerce-product-catalog' ),
			)[ $kind ],
			'url'           => self::panel_url() ? self::panel_url() . '#customer_panel_tabs-orders' : '',
		);
	}
	/**
	 * Build generated sample products and token values.
	 *
	 * @return array
	 */
	public static function sample() {
		$kind     = self::kind();
		$settings = get_shopping_cart_settings();
		$quantity = 'inquiry' === $kind || 'qty' === ( $settings['cart_page_template'] ?? 'qty' );
		$priced   = 'order' === $kind;
		$td       = ic_email_table_td();
		$end      = ic_email_table_td_end();
		$products = ic_email_table() . ic_email_table_th() . ic_email_table_td_first() . esc_html__( 'Product name', 'ecommerce-product-catalog' ) . $end;
		if ( $quantity ) {
			$products .= $td . esc_html__( 'Quantity', 'ecommerce-product-catalog' ) . $end; }
		if ( $priced ) {
			$products .= $td . esc_html__( 'Price', 'ecommerce-product-catalog' ) . $end . $td . esc_html__( 'Subtotal', 'ecommerce-product-catalog' ) . $end; }
		$products .= ic_email_table_th_end();
		foreach ( array( array( 'Walnut side table', 1, 240 ), array( 'Linen cushion', 2, 29 ) ) as $product ) {
			$products .= ic_email_table_tr() . ic_email_table_td_first() . esc_html( $product[0] ) . $end;
			if ( $quantity ) {
				$products .= $td . $product[1] . $end; }
			if ( $priced ) {
				$products .= $td . price_format( $product[2], 1, 0 ) . $end . $td . price_format( $product[1] * $product[2], 1, 0 ) . $end; }
			$products .= ic_email_table_tr_end();
		}
		$products .= ic_email_table_end();
		if ( $priced ) {
			$products .= '<div style="text-align:right;padding-right:10px;">' . ic_email_paragraph( 'margin:0' ) . '<span style="text-align:right;padding-right:5px;"><strong>' . esc_html( $settings['summary_total_label'] ?? __( 'Total', 'ecommerce-product-catalog' ) . ' = ' ) . '</strong></span>' . price_format( 298, 1 ) . ic_email_paragraph_end() . '</div>'; }

		if ( class_exists( 'IC_Email_Design' ) && 'modern' === IC_Email_Design::options()['layout'] ) {
			$rows = array();
			foreach ( array( array( 'Walnut side table', 1, 240 ), array( 'Linen cushion', 2, 29 ) ) as $product ) {
				$rows[] = array(
					'name'     => $product[0],
					'quantity' => $product[1],
					'amount'   => price_format( $product[1] * $product[2], 1 ),
				);
			}
			$products = IC_Email_Design::products_table(
				$rows,
				$priced ? array(
					array(
						'label' => __( 'Total', 'ecommerce-product-catalog' ),
						'value' => price_format( 298, 1 ),
					),
				) : array()
			);
		}
		return array(
			'products' => $products,
			'details'  => 'order' === $kind ? '<p><b>Customer details</b><br>Anna Sample<br>42 Sample Lane, Exampleville<br>anna@example.invalid</p>' : '<p><b>Your message:</b> Is the side table available in a darker finish?</p>',
			'tokens'   => array(
				'[customer_name]'          => 'Anna',
				'[account_info]'           => '',
				'[payment_details]'        => 'order' === $kind ? '<p><b>Payment details</b><br>Bank transfer · Reference #16301</p>' : '',
				'[edit_order_url]'         => '',
				'[notification_order_id]'  => '16301',
				'[form_field name="type"]' => 'quote request',
			),
			'order'    => array(
				'number'       => 'inquiry' === $kind ? '16302' : '16301',
				'date'         => 'November 9, 2026',
				'label'        => ucfirst( $kind ),
				'button_label' => array(
					'order'   => __( 'View your order', 'ecommerce-product-catalog' ),
					'inquiry' => __( 'View your inquiry', 'ecommerce-product-catalog' ),
					'quote'   => __( 'View your quote', 'ecommerce-product-catalog' ),
				)[ $kind ],
				'url'          => self::panel_url() ? self::panel_url() . '#customer_panel_tabs-orders' : '',
			),
		);
	}
	/**
	 * Compose a generated preview without sending.
	 *
	 * @param string $role Recipient role.
	 * @return array
	 */
	public static function preview( $role ) {
		$settings = get_shopping_cart_settings();
		$sample   = self::sample();
		if ( 'inquiry' === self::kind() ) {
			global $ic_simple_quote_cart;
			$composer = $ic_simple_quote_cart;
		} else {
			$composer = new ic_cart_checkout_form_email( false, false, true, $settings ); }
		$method = 'admin' === $role ? 'modify_admin_email' : 'modify_user_email';
		$body   = $composer->$method( $sample['details'], 'cart_', $sample );
		return array(
			'subject' => strtr( $settings[ $role . '_email_subject' ], $sample['tokens'] ),
			'body'    => $body,
		);
	}
}
IC_Cart_Email_Design::boot();
