<?php
/**
 * Shared public cart pluggable helpers.
 *
 * @package ic/cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_clear_variable' ) ) {
	/**
	 * Returns an empty string for output-clearing hooks.
	 *
	 * @return string
	 */
	function ic_clear_variable() {
		return '';
	}
}

if ( ! function_exists( 'ic_success_page' ) ) {
	add_shortcode( 'success_page', 'ic_success_page' );

	/**
	 * Renders the shared success-page shortcode output.
	 *
	 * @return string
	 */
	function ic_success_page() {
		ob_start();
		ic_save_global( 'inside_thank_you', 1 );
		do_action( 'success_page' );
		ic_delete_global( 'inside_thank_you' );

		return ob_get_clean();
	}
}

if ( ! function_exists( 'get_easy_order_sitename' ) ) {
	/**
	 * Returns the configured cart name or site name.
	 *
	 * @return string
	 */
	function get_easy_order_sitename() {
		$settings = get_shopping_cart_settings();
		if ( ! empty( $settings['cart_name'] ) ) {
			return $settings['cart_name'];
		}

		return get_bloginfo( 'name' );
	}
}

if ( ! function_exists( 'ic_get_order_messages_email' ) ) {
	/**
	 * Returns the confirmation sender email.
	 *
	 * @return string
	 */
	function ic_get_order_messages_email() {
		$settings = get_shopping_cart_settings();

		return isset( $settings['send_cart'] ) ? $settings['send_cart'] : '';
	}
}

if ( ! function_exists( 'ic_get_order_messages_email_send' ) ) {
	/**
	 * Returns the admin recipient email.
	 *
	 * @return string
	 */
	function ic_get_order_messages_email_send() {
		$settings = get_shopping_cart_settings();

		return isset( $settings['receive_cart'] ) ? $settings['receive_cart'] : '';
	}
}

if ( ! function_exists( 'ic_get_order_messages_admin_title' ) ) {
	/**
	 * Returns the admin email subject.
	 *
	 * @return string
	 */
	function ic_get_order_messages_admin_title() {
		$settings = get_shopping_cart_settings();

		return isset( $settings['admin_email_subject'] ) ? $settings['admin_email_subject'] : '';
	}
}

if ( ! function_exists( 'ic_get_order_messages_title' ) ) {
	/**
	 * Returns the customer email subject.
	 *
	 * @return string
	 */
	function ic_get_order_messages_title() {
		$settings = get_shopping_cart_settings();

		return isset( $settings['user_email_subject'] ) ? $settings['user_email_subject'] : '';
	}
}

if ( ! function_exists( 'ic_get_country_code' ) ) {
	/**
	 * Resolves the country code from a label or code.
	 *
	 * @param string $country_name Country label or code.
	 *
	 * @return string|false
	 */
	function ic_get_country_code( $country_name ) {
		if ( function_exists( 'implecode_qc_supported_countries' ) ) {
			$countries = implecode_qc_supported_countries();
		} else {
			$countries = implecode_supported_countries();
		}
		$country_code = array_search( $country_name, $countries, true );
		if ( false === $country_code && isset( $countries[ $country_name ] ) ) {
			$country_code = $country_name;
		}

		return $country_code;
	}
}

if ( ! function_exists( 'ic_get_order_form_emails' ) ) {
	/**
	 * Returns the shared order-email settings payload.
	 *
	 * @return array
	 */
	function ic_get_order_form_emails() {
		return array(
			'sender'      => ic_get_order_messages_email(),
			'receiver'    => ic_get_order_messages_email_send(),
			'name'        => get_easy_order_sitename(),
			'title'       => ic_get_order_messages_title(),
			'admin_title' => ic_get_order_messages_admin_title(),
		);
	}
}

if ( ! function_exists( 'get_ic_roles' ) ) {
	/**
	 * Returns the stored impleCode roles for a user.
	 *
	 * @param int $user_id User identifier.
	 *
	 * @return array
	 */
	function get_ic_roles( $user_id ) {
		$ic_roles = get_user_meta( $user_id, '_ic_roles', true );
		if ( empty( $ic_roles ) ) {
			$ic_roles = array();
		}

		return $ic_roles;
	}
}

if ( ! function_exists( 'add_ic_role' ) ) {
	/**
	 * Adds an impleCode role to a user.
	 *
	 * @param int    $user_id   User identifier.
	 * @param string $role_name Role slug.
	 *
	 * @return array
	 */
	function add_ic_role( $user_id, $role_name ) {
		$ic_roles = get_ic_roles( $user_id );
		if ( ! empty( $role_name ) ) {
			$ic_roles[] = $role_name;
			update_user_meta( $user_id, '_ic_roles', $ic_roles );
		}

		return $ic_roles;
	}
}

if ( ! function_exists( 'ic_thank_you_page' ) ) {
	/**
	 * Returns the thank-you page URL.
	 *
	 * @param int|null $catalog_id Unused legacy parameter.
	 *
	 * @return string
	 */
	function ic_thank_you_page( $catalog_id = null ) {
		$settings       = get_shopping_cart_settings();
		$thank_you_page = site_url();
		if ( ! empty( $settings['thank_you_page'] ) ) {
			$thank_you_page = ic_get_permalink( $settings['thank_you_page'] );
		}

		return $thank_you_page;
	}
}

if ( ! function_exists( 'ic_set_shortcode_content' ) ) {
	/**
	 * Returns shortcode content for seeded pages.
	 *
	 * @param string $shortcode Shortcode slug.
	 *
	 * @return string
	 */
	function ic_set_shortcode_content( $shortcode ) {
		if ( function_exists( 'register_block_type' ) ) {
			return '<!-- wp:shortcode -->[' . $shortcode . ']<!-- /wp:shortcode -->';
		}

		return '[' . $shortcode . ']';
	}
}

if ( ! function_exists( 'ic_get_default_date_format' ) ) {
	/**
	 * Returns the WordPress date format or a preview value.
	 *
	 * @param bool $preview Whether to return the formatted preview.
	 * @param bool $jquery  Whether to convert to jQuery UI format.
	 *
	 * @return string
	 */
	function ic_get_default_date_format( $preview = false, $jquery = false ) {
		$format = get_option( 'date_format' );
		if ( $preview ) {
			return date_i18n( $format );
		}
		if ( $jquery ) {
			$format = dateformat_PHP_to_jQueryUI( $format );
		}

		return $format;
	}
}

if ( ! function_exists( 'ic_get_default_time_format' ) ) {
	/**
	 * Returns the WordPress time format or a preview value.
	 *
	 * @param bool $preview Whether to return the formatted preview.
	 * @param bool $jquery  Whether to convert to jQuery UI format.
	 *
	 * @return string
	 */
	function ic_get_default_time_format( $preview = false, $jquery = false ) {
		$format = get_option( 'time_format' );
		if ( $preview ) {
			return date_i18n( $format );
		}
		if ( $jquery ) {
			$format = dateformat_PHP_to_jQueryUI( $format );
		}

		return $format;
	}
}

if ( ! function_exists( 'dateformat_PHP_to_jQueryUI' ) ) {
	/**
	 * Converts a PHP date format into the jQuery UI equivalent.
	 *
	 * @param string $php_format PHP date format string.
	 *
	 * @return string
	 */
	function dateformat_PHP_to_jQueryUI( $php_format ) {
		$symbols_matching = array(
			'd' => 'dd',
			'D' => 'D',
			'j' => 'd',
			'l' => 'DD',
			'N' => '',
			'S' => '',
			'w' => '',
			'z' => 'o',
			'W' => '',
			'F' => 'MM',
			'm' => 'mm',
			'M' => 'M',
			'n' => 'm',
			't' => '',
			'L' => '',
			'o' => '',
			'Y' => 'yy',
			'y' => 'y',
			'a' => 'tt',
			'A' => 'TT',
			'B' => '',
			'g' => 'h',
			'G' => 'H',
			'h' => 'hh',
			'H' => 'HH',
			'i' => 'mm',
			's' => 'ss',
			'T' => 'z',
		);
		$jqueryui_format = '';
		$escaping        = false;
		$length          = strlen( $php_format );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $php_format[ $i ];
			if ( '\\' === $char ) {
				$i++;
				if ( $escaping ) {
					$jqueryui_format .= $php_format[ $i ];
				} else {
					$jqueryui_format .= '\'' . $php_format[ $i ];
				}
				$escaping = true;
			} else {
				if ( $escaping ) {
					$jqueryui_format .= '\'';
					$escaping         = false;
				}
				if ( isset( $symbols_matching[ $char ] ) ) {
					$jqueryui_format .= $symbols_matching[ $char ];
				} else {
					$jqueryui_format .= $char;
				}
			}
		}

		return $jqueryui_format;
	}
}
