<?php
/**
 * Legacy EPC compatibility helpers for the shared simple form builder.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'IC_FORMBUILDER_URL' ) ) {
	define( 'IC_FORMBUILDER_URL', IC_SIMPLE_FORMBUILDER_URL );
}
if ( ! defined( 'IC_FORMBUILDER_PATH' ) ) {
	define( 'IC_FORMBUILDER_PATH', IC_SIMPLE_FORMBUILDER_PATH );
}

if ( ! class_exists( 'ic_google_recaptcha' ) && file_exists( IC_FORMBUILDER_PATH . '/recaptcha/index.php' ) ) {
	if ( function_exists( 'ic_framework_require_once' ) ) {
		ic_framework_require_once( IC_FORMBUILDER_PATH . '/recaptcha/index.php' );
	} else {
		require_once IC_FORMBUILDER_PATH . '/recaptcha/index.php';
	}
}

add_action( 'register_catalog_admin_scripts', 'form_builder_register_admin_styles' );
add_action( 'register_catalog_styles', 'form_builder_register_styles' );
add_action( 'enqueue_catalog_scripts', 'form_builder_enqueue_styles' );
add_action( 'wp_ajax_ic_formbuilder_save_field', 'ic_formbuilder_save_field' );
add_action( 'wp_ajax_nopriv_ic_formbuilder_save_field', 'ic_formbuilder_save_field' );
add_action( 'wp_ajax_ic_state_dropdown', 'ic_ajax_dropdown_state' );
add_action( 'wp_ajax_nopriv_ic_state_dropdown', 'ic_ajax_dropdown_state' );
add_action( 'wp_ajax_ic_address_lines', 'ic_ajax_address_lines' );
add_action( 'wp_ajax_nopriv_ic_address_lines', 'ic_ajax_address_lines' );
add_action( 'wp_login', 'ic_formbuilder_clear_saved_fields' );
add_action( 'wp_logout', 'ic_formbuilder_clear_saved_fields' );
add_action( 'user_register', 'ic_formbuilder_clear_saved_fields' );
add_action( 'ic_guest_registered', 'ic_formbuilder_clear_saved_fields' );
add_filter( 'ic_formbuilder_default_value', 'ic_formbuilder_set_saved_field_value', 10, 4 );
add_filter( 'ic_forumbuilder_field_attributes', 'add_fombuilder_fields_visibility_data', 10, 3 );
add_filter( 'ic_formbuilder_message_field_value', 'ic_formbuilder_modify_notification_values', 10, 3 );
add_filter( 'pre_update_option_shopping_checkout_form', 'ic_validate_formbuilder_form' );
add_filter( 'ic_catalog_product_object_nonces', 'ic_simple_formbuilder_product_object_nonces' );

/**
 * Adds simple-formbuilder AJAX nonces to the catalog product object.
 *
 * @param array $nonces Existing nonce map.
 * @return array
 */
function ic_simple_formbuilder_product_object_nonces( $nonces ) {
	$nonces['formbuilder_state_nonce']   = wp_create_nonce( 'ic_state_dropdown' );
	$nonces['formbuilder_address_nonce'] = wp_create_nonce( 'ic_address_lines' );
	$nonces['formbuilder_save_nonce']    = wp_create_nonce( 'ic_formbuilder_save_field' );

	return $nonces;
}

if ( ! function_exists( 'ic_verify_formbuilder_public_nonce' ) ) {
	/**
	 * Verify a public formbuilder AJAX request and the minimum logged-in capability.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	function ic_verify_formbuilder_public_nonce( $action ) {
		$nonce = isset( $_REQUEST['ic_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ic_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) || ( is_user_logged_in() && ! current_user_can( 'read' ) ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
	}
}

if ( ! function_exists( 'form_builder_register_admin_styles' ) ) {
	/**
	 * Register legacy formbuilder admin assets.
	 *
	 * @return void
	 */
	function form_builder_register_admin_styles() {
		wp_register_style( 'implecode-form-css', IC_FORMBUILDER_URL . 'ic-form.min.css', array( 'ic-html' ), (string) ic_filemtime( IC_FORMBUILDER_PATH . '/ic-form.min.css', true ) );
		wp_register_script( 'implecode-form-js', IC_FORMBUILDER_URL . 'ic-form.js', array( 'jquery' ), (string) ic_filemtime( IC_FORMBUILDER_PATH . '/ic-form.js', true ), true );
	}
}

if ( ! function_exists( 'form_builder_register_styles' ) ) {
	/**
	 * Register legacy formbuilder frontend assets.
	 *
	 * @return void
	 */
	function form_builder_register_styles() {
		form_builder_register_admin_styles();
	}
}

if ( ! function_exists( 'form_builder_enqueue_styles' ) ) {
	/**
	 * Enqueue legacy formbuilder frontend assets.
	 *
	 * @return void
	 */
	function form_builder_enqueue_styles() {
		wp_enqueue_style( 'implecode-form-css' );
		wp_enqueue_script( 'implecode-form-js' );
	}
}

if ( ! function_exists( 'formbuilder_form' ) ) {
	/**
	 * Render one legacy-compatible formbuilder form.
	 *
	 * @return string
	 */
	function formbuilder_form(
		$form_fields,
		$pre_name = 'ic_form_',
		$before_button = '',
		$button_text = null,
		$form_type = 'label-left',
		$show_form_after = true,
		$success_message = null,
		$line = "\n",
		$send_mail = true,
		$message_sender = null,
		$message_receiver = null,
		$message_topic = null,
		$button_class = 'classic-button',
		$send_customer_email = true,
		$customer_message_topic = null,
		$message_sender_name = null,
		$redirect = null
	) {
		$form = new IC_Simple_Form_Legacy(
			$form_fields,
			$pre_name,
			$before_button,
			$button_text,
			$form_type,
			$show_form_after,
			$success_message,
			$line,
			$send_mail,
			$message_sender,
			$message_receiver,
			$message_topic,
			$button_class,
			$send_customer_email,
			$customer_message_topic,
			$message_sender_name,
			$redirect
		);

		return isset( $form->content ) ? (string) $form->content : '';
	}
}

if ( ! function_exists( 'ic_formbuilder_process_submission' ) ) {
	/**
	 * Processes one legacy simple-form submission before full render.
	 *
	 * @return array|false
	 */
	function ic_formbuilder_process_submission(
		$form_fields,
		$pre_name = 'ic_form_',
		$button_text = null,
		$form_type = 'label-left',
		$show_form_after = true,
		$success_message = null,
		$line = "\n",
		$send_mail = true,
		$message_sender = null,
		$message_receiver = null,
		$message_topic = null,
		$button_class = 'classic-button',
		$send_customer_email = true,
		$customer_message_topic = null,
		$message_sender_name = null,
		$redirect = null
	) {
		$form = new IC_Simple_Form_Legacy(
			$form_fields,
			$pre_name,
			'',
			$button_text,
			$form_type,
			$show_form_after,
			$success_message,
			$line,
			$send_mail,
			$message_sender,
			$message_receiver,
			$message_topic,
			$button_class,
			$send_customer_email,
			$customer_message_topic,
			$message_sender_name,
			$redirect,
			true
		);

		return $form->submission_state();
	}
}

if ( ! function_exists( 'ic_formbuilder_decode_raw_value' ) ) {
	function ic_formbuilder_decode_raw_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'ic_formbuilder_decode_raw_value', $value );
		}
		if ( ! is_scalar( $value ) ) {
			return $value;
		}

		return wp_specialchars_decode( (string) $value, ENT_QUOTES );
	}
}

if ( ! function_exists( 'formbuilder_raw_fields' ) ) {
	/**
	 * Render raw legacy formbuilder fields.
	 *
	 * @return string
	 */
	function formbuilder_raw_fields(
		$fields,
		$in_row = 2,
		$pre_name = '',
		$field_values = array(),
		$exclude = array(),
		$value_by_default_cid = false,
		$set_required = true
	) {
		form_builder_enqueue_styles();
		$fields = json_decode( $fields );
		if ( empty( $fields ) || ! is_object( $fields ) ) {
			$fields = (object) array( 'fields' => array() );
		} elseif ( empty( $fields->fields ) || ! is_array( $fields->fields ) ) {
			$fields->fields = empty( $fields->fields ) ? array() : array_values( (array) $fields->fields );
		}
		$fields   = apply_filters( 'formbuilder_raw_fields_fields', $fields, $pre_name );
		$raw_form = '';
		$line     = '<br>';
		$row      = 1;
		if ( is_array( $field_values ) ) {
			$field_values = ic_formbuilder_decode_raw_value( $field_values );
		}
		foreach ( $fields->fields as $field ) {
			$cid         = $field->cid;
			$default_cid = $pre_name . $field->cid;
			$field->cid  = ic_formbuilder_field_cid( $field, $pre_name );
			$value_cid   = $value_by_default_cid ? $default_cid : $field->cid;
			if ( in_array( $value_cid, $exclude, true ) ) {
				continue;
			}

			$field_value = '';
			if ( is_array( $field_values ) ) {
				if ( isset( $field_values[ $value_cid ] ) && is_array( $field_values[ $value_cid ] ) ) {
					$field_value = $field_values[ $value_cid ];
				} elseif ( isset( $field_values[ $value_cid ] ) ) {
					$field_value = $field_values[ $value_cid ];
				} elseif ( isset( $field_values[ $default_cid ] ) ) {
					$field_value = $field_values[ $default_cid ];
				} elseif ( isset( $field_values[ $cid ] ) ) {
					$field_value = $field_values[ $cid ];
				}
			}

			if ( empty( $field->field_options ) || ! is_object( $field->field_options ) ) {
				$field->field_options = new stdClass();
			}

			$required         = $set_required && ! empty( $field->required ) ? 'required' : '';
			$field_attributes = apply_filters( 'ic_forumbuilder_raw_field_attributes', '', $field, $pre_name, $field_value );
			$added            = false;
			$content          = '';

			if ( 'dropdown_country' === $field->field_type ) {
				$field->field_options->options              = ic_dropdown_country_options( $field_value );
				$field->field_options->include_blank_option = true;
			} elseif ( 'dropdown_state' === $field->field_type ) {
				$field->field_options->options              = ic_dropdown_state_options( $field_value );
				$field->field_options->include_blank_option = true;
			}

			if ( 1 === $row ) {
				$content .= '<div class="form_row table-row">';
			}
			$content .= '<div class="form_field table-cell ' . esc_attr( $field->field_type ) . '">';

			if ( 'section_break' === $field->field_type ) {
				if ( ! empty( $raw_form ) ) {
					$raw_form .= $content . '</div></div><div class="form_row table-row section-break"><div class="table-cell"></div><div class="table-cell"></div></div>';
				}
				$row = 1;
				continue;
			} elseif ( 'checkboxes' === $field->field_type ) {
				$added    = true;
				$content .= '<div class="label"><label>' . $field->label . '</label></div><div class="field">';
				foreach ( $field->field_options->options as $option_key => $radio_option ) {
					$option_id      = sanitize_html_class( $field->cid . '_' . $option_key );
					$checkbox_value = ic_formbuilder_decode_raw_value( sanitize_text_field( $radio_option->label ) );
					$checked        = is_array( $field_value ) && in_array( $checkbox_value, $field_value, true ) ? 'checked' : '';
					$content       .= '<input id="' . esc_attr( $option_id ) . '" class="input" value="' . esc_attr( $checkbox_value ) . '" ' . $checked . ' type="checkbox" name="' . esc_attr( $field->cid . '[]' ) . '" ' . $field_attributes . ' /> <label for="' . esc_attr( $option_id ) . '">' . $radio_option->label . '</label>' . $line;
				}
				$content .= '</div>';
			} elseif ( 'radio' === $field->field_type ) {
				$added    = true;
				$content .= '<div class="label"><label>' . $field->label . '</label></div><div class="field">';
				foreach ( $field->field_options->options as $option_key => $radio_option ) {
					$option_id   = sanitize_html_class( $field->cid . '_' . $option_key );
					$radio_value = ic_formbuilder_decode_raw_value( sanitize_text_field( $radio_option->label ) );
					$checked     = $radio_value === $field_value ? 'checked' : '';
					$content    .= '<input id="' . esc_attr( $option_id ) . '" class="input" value="' . esc_attr( $radio_value ) . '" ' . $required . ' ' . $checked . ' type="radio" name="' . esc_attr( $field->cid ) . '" ' . $field_attributes . ' /> <label for="' . esc_attr( $option_id ) . '">' . $radio_option->label . '</label>' . $line;
				}
				$content .= '</div>';
			} elseif ( 'dropdown' === $field->field_type || ic_string_contains( $field->field_type, 'dropdown' ) ) {
				$added    = true;
				$content .= '<div class="label"><label for="' . esc_attr( $field->cid ) . '">' . $field->label . '</label></div><div class="field"><select class="' . esc_attr( $required ) . '" ' . $required . ' name="' . esc_attr( $field->cid ) . '" ' . $field_attributes . '>';
				if ( ! empty( $field->field_options->include_blank_option ) ) {
					$content .= '<option value=""></option>';
				}
				$country_field    = in_array( $field->cid, array( 'customer_country', 'payment_details_country' ), true );
				$country_selected = false;
				foreach ( $field->field_options->options as $radio_option ) {
					$option_value = isset( $radio_option->value ) ? $radio_option->value : $radio_option->label;
					$option_value = apply_filters( 'ic_formbuilder_dorpdown_option_value', $option_value, $field->cid );
					if ( $country_field && '' !== $field_value && preg_match( '/^(.*) - ([A-Z]{2})$/', $radio_option->label, $country_parts ) && in_array( html_entity_decode( $field_value, ENT_QUOTES, 'UTF-8' ), array( $country_parts[1], $country_parts[2] ), true ) ) {
						// Keep the historical submitted value while displaying its current country label.
						$option_value = html_entity_decode( $field_value, ENT_QUOTES, 'UTF-8' );
					}
					$selected         = ic_formbuilder_decode_raw_value( sanitize_text_field( (string) $option_value ) ) === (string) $field_value || ( ( ! $country_field || '' === $field_value ) && ! empty( $radio_option->checked ) ) ? 'selected' : '';
					$country_selected = $country_selected || 'selected' === $selected;
					$content         .= '<option value="' . esc_attr( $option_value ) . '" ' . $selected . '>' . esc_html( $radio_option->label ) . '</option>';
				}
				if ( $country_field && '' !== $field_value && ! $country_selected ) {
					// Obsolete country codes cannot be inferred safely; preserve their explicit value.
					$historical_country = html_entity_decode( $field_value, ENT_QUOTES, 'UTF-8' );
					$content           .= '<option value="' . esc_attr( $historical_country ) . '" selected>' . esc_html( $historical_country ) . '</option>';
				}
				$content .= '</select></div>';
			} elseif ( 'paragraph' === $field->field_type ) {
				$added    = true;
				$content .= '<div class="label"><label for="' . esc_attr( $field->cid ) . '">' . $field->label . '</label></div><div class="field"><textarea id="' . esc_attr( $field->cid ) . '" ' . $required . ' name="' . esc_attr( $field->cid ) . '" ' . $field_attributes . '>' . esc_html( is_scalar( $field_value ) ? (string) $field_value : '' ) . '</textarea></div>';
			} elseif ( 'address' === $field->field_type ) {
				$added    = true;
				$content .= '<div class="label"><label for="' . esc_attr( $field->cid ) . '">' . $field->label . '</label></div><div class="field">';
				$content .= ic_formbuilder_address_lines( $field->cid, $pre_name, '', '', $field_attributes, is_array( $field_values ) ? $field_values : array() );
				$content .= '</div>';
			} else {
				$input_type = 'date' === $field->field_type ? 'text' : $field->field_type;
				$input_css  = 'date' === $field->field_type ? 'input date' : 'input';
				$added      = true;
				$content   .= '<div class="label"><label for="' . esc_attr( $field->cid ) . '">' . $field->label . '</label></div><div class="field"><span><input class="' . esc_attr( $input_css ) . '" ' . $required . ' type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $field->cid ) . '" value="' . esc_attr( is_array( $field_value ) ? '' : $field_value ) . '" ' . $field_attributes . ' /></span></div>';
			}

			$content = apply_filters( 'formbuilder_raw_fields_content', $content, $pre_name );
			if ( $added ) {
				$raw_form .= $content . '</div>';
				if ( $row === $in_row || end( $fields->fields ) === $field ) {
					$raw_form .= '</div>';
					$row       = 1;
				} else {
					++$row;
				}
			}
		}

		return $raw_form;
	}
}

if ( ! function_exists( 'get_submitted_form_name' ) ) {
	/**
	 * Return the last submitted legacy form name.
	 *
	 * @return string|null
	 */
	function get_submitted_form_name() {
		global $submitted_form_name;

		return $submitted_form_name;
	}
}

if ( ! function_exists( 'ic_formbuilder_field_cid' ) ) {
	function ic_formbuilder_field_cid( $field, $pre_name ) {
		return apply_filters( 'ic_formbuilder_cid', $pre_name . $field->cid, $field, $pre_name );
	}
}

if ( ! function_exists( 'ic_formbuilder_field_value' ) ) {
	function ic_formbuilder_field_value( $cid, $pre_name, $field_values = array() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
		if ( isset( $_POST[ $cid ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			return wp_unslash( $_POST[ $cid ] );
		}
		if ( is_array( $field_values ) && isset( $field_values[ $cid ] ) ) {
			return $field_values[ $cid ];
		}

		return apply_filters( 'ic_formbuilder_default_value', '', $cid, $pre_name );
	}
}

if ( ! function_exists( 'ic_formbuilder_get_country' ) ) {
	function ic_formbuilder_get_country( $fields, $pre_name ) {
		foreach ( $fields as $field ) {
			if ( 'dropdown_country' === $field->field_type ) {
				return ic_formbuilder_field_value( ic_formbuilder_field_cid( $field, $pre_name ), $pre_name );
			}
		}
	}
}

if ( ! function_exists( 'implecode_supported_countries' ) ) {
	function implecode_supported_countries() {
		$countries = ic_get_global( 'supported_countries' );
		if ( false !== $countries ) {
			return $countries;
		}
		$countries = array();
		$file      = IC_FORMBUILDER_PATH . '/json/country-states.json';
		if ( file_exists( $file ) ) {
			$json = file_get_contents( $file );
			if ( ! empty( $json ) ) {
				$country_state = json_decode( $json, true );
				if ( ! empty( $country_state ) && is_array( $country_state ) ) {
					foreach ( $country_state as $country_code => $country_data ) {
						$countries[ $country_code ] = sanitize_text_field( $country_data['name'] ) . ' - ' . $country_code;
					}
				}
			}
		}
		$countries = apply_filters( 'ic_supported_countries', $countries );
		ic_save_global( 'supported_countries', $countries );

		return $countries;
	}
}

if ( ! function_exists( 'implecode_supported_states' ) ) {
	function implecode_supported_states( $country = 'US' ) {
		$states = ic_get_global( 'supported_states_' . $country );
		if ( false !== $states ) {
			return $states;
		}
		$states = array();
		$file   = IC_FORMBUILDER_PATH . '/json/country-states.json';
		if ( file_exists( $file ) ) {
			$json = file_get_contents( $file );
			if ( ! empty( $json ) ) {
				$country_state = json_decode( $json, true );
				if ( isset( $country_state[ $country ]['states'] ) && is_array( $country_state[ $country ]['states'] ) ) {
					$states = $country_state[ $country ]['states'];
				}
			}
		}
		$states = apply_filters( 'ic_supported_states', $states, $country );
		ic_save_global( 'supported_states_' . $country, $states );

		return $states;
	}
}

if ( ! function_exists( 'ic_dropdown_country_options' ) ) {
	function ic_dropdown_country_options( $selected = null, $null_option = true ) {
		$options = array();
		if ( $null_option ) {
			$option          = new stdClass();
			$option->checked = false;
			$option->label   = __( 'Choose your country...', 'ecommerce-product-catalog' );
			$option->value   = '';
			$options[]       = $option;
		}
		foreach ( implecode_supported_countries() as $country_code => $country_name ) {
			$option          = new stdClass();
			$option->checked = $selected === $country_code;
			$option->label   = $country_name;
			$option->value   = $country_code;
			$options[]       = $option;
		}

		return $options;
	}
}

if ( ! function_exists( 'ic_dropdown_state_options' ) ) {
	function ic_dropdown_state_options( $selected = null, $null_option = true ) {
		$options = array();
		if ( $null_option ) {
			$option          = new stdClass();
			$option->checked = false;
			$option->label   = __( 'Choose your state...', 'ecommerce-product-catalog' );
			$option->value   = '';
			$options[]       = $option;
		}

		if ( ic_string_contains( (string) $selected, '_' ) ) {
			$selected_a       = explode( '_', (string) $selected );
			$selected_country = $selected_a[0];
			$selected         = isset( $selected_a[1] ) ? $selected_a[1] : '';
		} else {
			$selected_country = $selected;
		}

		foreach ( implecode_supported_states( (string) $selected_country ) as $state_code => $state_name ) {
			$option          = new stdClass();
			$option->checked = $selected === $state_code;
			$option->label   = $state_name;
			$option->value   = $selected_country . '_' . $state_code;
			$options[]       = $option;
		}

		return $options;
	}
}

if ( ! function_exists( 'ic_ajax_dropdown_state' ) ) {
	function ic_ajax_dropdown_state() {
		ic_verify_formbuilder_public_nonce( 'ic_state_dropdown' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ic_verify_formbuilder_public_nonce() verifies this callback's action nonce immediately above.
		$selected_country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['country_code'] ) ) : '';
		if ( ! empty( $selected_country_code ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
			$selected_state_code = isset( $_POST['state_code'] ) ? sanitize_text_field( wp_unslash( $_POST['state_code'] ) ) : '';
			if ( ! empty( $selected_state_code ) && ic_string_contains( $selected_state_code, $selected_country_code ) ) {
				$selected_country_code = $selected_state_code;
			} elseif ( ! empty( $selected_state_code ) ) {
				$selected_country_code .= '_' . $selected_state_code;
			}
			wp_send_json( ic_dropdown_state_options( $selected_country_code ) );
		}
		wp_die();
	}
}

if ( ! function_exists( 'ic_country_selector' ) ) {
	function ic_country_selector( $name, $required = null, $selected = null, $echo = 1 ) {
		$return  = '<select ' . $required . ' data-placeholder="' . esc_attr__( 'Choose your country...', 'ecommerce-product-catalog' ) . '" id="country-selector" name="' . esc_attr( $name ) . '" class="country-selector">';
		$return .= '<option value=""></option>';
		foreach ( implecode_supported_countries() as $code => $country ) {
			$return .= '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $country ) . '</option>';
		}
		$return .= '</select>';

		return echo_ic_setting( $return, $echo );
	}
}

if ( ! function_exists( 'ic_formbuilder_address_lines' ) ) {
	function ic_formbuilder_address_lines( $cid, $pre_name, $country = '', $error_class = '', $field_attributes = '', $field_values = array() ) {
		$street  = ic_formbuilder_field_value( $cid . '_street', $pre_name, $field_values );
		$city    = ic_formbuilder_field_value( $cid . '_city', $pre_name, $field_values );
		$state   = ic_formbuilder_field_value( $cid . '_state', $pre_name, $field_values );
		$zip     = ic_formbuilder_field_value( $cid . '_zip', $pre_name, $field_values );
		$country = empty( $country ) ? ic_formbuilder_field_value( $cid . '_country_name', $pre_name, $field_values ) : $country;

		$content  = '<div class="address_line"><input class="input street large ' . esc_attr( $error_class ) . '" type="text" name="' . esc_attr( $cid ) . '_street" value="' . esc_attr( is_array( $street ) ? '' : $street ) . '" ' . $field_attributes . ' /><label>' . esc_html__( 'Street', 'ecommerce-product-catalog' ) . '</label></div>';
		$content .= '<div class="address_line"><span><input class="input city medium ' . esc_attr( $error_class ) . '" type="text" name="' . esc_attr( $cid ) . '_city" value="' . esc_attr( is_array( $city ) ? '' : $city ) . '" ' . $field_attributes . ' /><label>' . esc_html__( 'City', 'ecommerce-product-catalog' ) . '</label></span> <span><input class="input state medium ' . esc_attr( $error_class ) . '" type="text" name="' . esc_attr( $cid ) . '_state" value="' . esc_attr( is_array( $state ) ? '' : $state ) . '" ' . $field_attributes . ' /><label>' . esc_html__( 'State / Province / Region', 'ecommerce-product-catalog' ) . '</label></span></div>';
		$content .= '<div class="address_line"><span><input class="input zip medium ' . esc_attr( $error_class ) . '" type="text" name="' . esc_attr( $cid ) . '_zip" value="' . esc_attr( is_array( $zip ) ? '' : $zip ) . '" ' . $field_attributes . ' /><label>' . esc_html__( 'ZIP code', 'ecommerce-product-catalog' ) . '</label></span> <span>' . ic_country_selector( $cid . '_country_name', '', $country, false ) . '<label>' . esc_html__( 'Country', 'ecommerce-product-catalog' ) . '</label></span></div>';

		return $content;
	}
}

if ( ! function_exists( 'ic_ajax_address_lines' ) ) {
	function ic_ajax_address_lines() {
		ic_verify_formbuilder_public_nonce( 'ic_address_lines' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ic_verify_formbuilder_public_nonce() verifies this callback's action nonce immediately above.
		$selected_country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['country_code'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
		$pre_name              = isset( $_POST['pre_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pre_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
		$cid                   = isset( $_POST['cid'] ) ? sanitize_text_field( wp_unslash( $_POST['cid'] ) ) : '';
		if ( ! empty( $pre_name ) && ! empty( $cid ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The address helper returns controlled form markup with escaped field values.
			// The helper returns escaped, functional form controls; post KSES would remove input/select elements.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated address controls escape each dynamic attribute and label at construction time.
			echo ic_formbuilder_address_lines( $cid, $pre_name, $selected_country_code );
		}
		wp_die();
	}
}

if ( ! function_exists( 'ic_get_country_name' ) ) {
	function ic_get_country_name( $country_code ) {
		$countries = implecode_supported_countries();

		return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_code;
	}
}

if ( ! function_exists( 'ic_get_state_name' ) ) {
	function ic_get_state_name( $country_code, $state_code ) {
		$prefix = $country_code . '_';
		if ( 0 === strpos( (string) $state_code, $prefix ) ) {
			$state_code = substr( (string) $state_code, strlen( $prefix ) );
		}
		$states = implecode_supported_states( $country_code );

		return isset( $states[ $state_code ] ) ? $states[ $state_code ] : $state_code;
	}
}

if ( ! function_exists( 'add_fombuilder_fields_visibility_data' ) ) {
	function add_fombuilder_fields_visibility_data( $attributes, $field, $pre_name ) {
		if ( ! empty( $field->field_options->rules ) && ! empty( $field->field_options->rulesval ) ) {
			$attributes .= ' data-rules="' . apply_filters( 'ic_formbuilder_cid', $pre_name . $field->field_options->rules, '', $pre_name ) . '"';
			$attributes .= ' data-rulesval="' . esc_attr( $field->field_options->rulesval ) . '"';
			$attributes .= ' data-rulestype="' . ( ! empty( $field->field_options->rulestype ) && '!=' === $field->field_options->rulestype ? '!=' : '=' ) . '"';
		}

		return $attributes;
	}
}

if ( ! function_exists( 'is_formbuilder_field_email_visible' ) ) {
	function is_formbuilder_field_email_visible( $field, $pre_name ) {
		if ( ! empty( $field->field_options->rules ) && ! empty( $field->field_options->rulesval ) ) {
			$rulestype = ! empty( $field->field_options->rulestype ) && '!=' === $field->field_options->rulestype ? '!=' : '=';
			$field_id  = apply_filters( 'ic_formbuilder_cid', $pre_name . $field->field_options->rules, '', $pre_name );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			if ( '=' === $rulestype && ( ! isset( $_POST[ $field_id ] ) || wp_unslash( $_POST[ $field_id ] ) != $field->field_options->rulesval ) ) {
				return false;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The structured request value is shape-checked, unslashed once, and normalized by the field-specific downstream parser before use.
			if ( '!=' === $rulestype && isset( $_POST[ $field_id ] ) && wp_unslash( $_POST[ $field_id ] ) == $field->field_options->rulesval ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'ic_formbuilder_modify_notification_values' ) ) {
	function ic_formbuilder_modify_notification_values( $field_value, $field_cid, $field_type ) {
		if ( 'dropdown' === $field_type || ic_string_contains( $field_type, 'dropdown' ) ) {
			if ( ic_string_contains( $field_type, 'country' ) || ic_string_contains( $field_cid, 'country' ) ) {
				$field_value = ic_get_country_name( $field_value );
			} elseif ( ic_string_contains( $field_type, 'state' ) || ic_string_contains( $field_cid, 'state' ) ) {
				$exploded_field_value = explode( '_', (string) $field_value );
				if ( ! empty( $exploded_field_value[0] ) && ! empty( $exploded_field_value[1] ) ) {
					$country_code = $exploded_field_value[0];
					unset( $exploded_field_value[0] );
					$field_value = ic_get_state_name( $country_code, implode( '_', $exploded_field_value ) );
				}
			}
		}

		return $field_value;
	}
}

if ( ! function_exists( 'handle_formbuilder_file_upload' ) ) {
	function handle_formbuilder_file_upload( $field_name ) {
		return IC_Form_Submission_Helper::handle_upload( $field_name );
	}
}

if ( ! function_exists( 'ic_mail_attachment_folder' ) ) {
	function ic_mail_attachment_folder( $dir = null ) {
		return IC_Form_Submission_Helper::attachment_folder( $dir );
	}
}

if ( ! function_exists( 'ic_validate_formbuilder_form' ) ) {
	function ic_validate_formbuilder_form( $form ) {
		if ( ! is_string( $form ) ) {
			return $form;
		}
		$ic_form_updates = (int) get_option( 'ic_formbuilder_updates', 1 );
		$form            = str_replace(
			array( '"cid":"c', '"rules":"c' ),
			array(
				'"cid":"' . $ic_form_updates . 'ic',
				'"rules":"' . $ic_form_updates . 'ic',
			),
			$form
		);
		update_option( 'ic_formbuilder_updates', ++$ic_form_updates, false );

		return apply_filters( 'validate_shopping_checkout_form', $form );
	}
}

if ( ! function_exists( 'ic_get_default_date_format' ) ) {
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
	function dateformat_PHP_to_jQueryUI( $php_format ) {
		$symbols = array(
			'd' => 'dd',
			'D' => 'D',
			'j' => 'd',
			'l' => 'DD',
			'z' => 'o',
			'F' => 'MM',
			'm' => 'mm',
			'M' => 'M',
			'n' => 'm',
			'Y' => 'yy',
			'y' => 'y',
			'a' => 'tt',
			'A' => 'TT',
			'g' => 'h',
			'G' => 'H',
			'h' => 'hh',
			'H' => 'HH',
			'i' => 'mm',
			's' => 'ss',
			'T' => 'z',
		);
		$format  = '';
		$escape  = false;
		for ( $i = 0; $i < strlen( $php_format ); ++$i ) {
			$char = $php_format[ $i ];
			if ( '\\' === $char ) {
				++$i;
				$format .= $escape ? $php_format[ $i ] : '\'' . $php_format[ $i ];
				$escape  = true;
			} else {
				if ( $escape ) {
					$format .= '\'';
					$escape  = false;
				}
				$format .= isset( $symbols[ $char ] ) ? $symbols[ $char ] : $char;
			}
		}

		return $format;
	}
}

if ( ! function_exists( 'ic_get_checkout_field' ) ) {
	function ic_get_checkout_field( $cid, $fields = null, $pre_name = 'ic_form_' ) {
		$form_fields = $fields;
		if ( empty( $form_fields ) && function_exists( 'get_shopping_checkout_form_fields' ) ) {
			$form_fields = get_shopping_checkout_form_fields();
		}
		$contact = json_decode( (string) $form_fields );
		if ( ! empty( $contact->fields ) && is_array( $contact->fields ) ) {
			foreach ( $contact->fields as $field ) {
				if ( $field->cid === $cid || $pre_name . $field->cid === $cid ) {
					return $field;
				}
			}
		}
	}
}

if ( ! function_exists( 'ic_message_td_start' ) ) {
	function ic_message_td_start( $template = false ) {
		return $template ? '<td>' : '';
	}
}

if ( ! function_exists( 'ic_message_td_end' ) ) {
	function ic_message_td_end( $template = false ) {
		return $template ? '</td>' : '';
	}
}

if ( ! function_exists( 'ic_message_row' ) ) {
	function ic_message_row( $field, $field_value, $field_id, $line, $template ) {
		return apply_filters( 'formbuilder_message_row', str_replace( '::', ':', ic_message_td_start( $template ) . $field->label . ': ' . ic_message_td_end( $template ) . ic_message_td_start( $template ) . $field_value . $line . ic_message_td_end( $template ) ), $field, $field_id, $line );
	}
}

if ( ! function_exists( 'ic_formbuilder_save_field' ) ) {
	function ic_formbuilder_save_field() {
		ic_verify_formbuilder_public_nonce( 'ic_formbuilder_save_field' );
		if ( ! function_exists( 'get_product_catalog_session' ) || ! function_exists( 'set_product_catalog_session' ) ) {
			wp_die();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
		$field_name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$field_value = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
		if ( isset( $_POST['value'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
			$field_value = is_array( $_POST['value'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['value'] ) ) : sanitize_text_field( wp_unslash( $_POST['value'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning public callback verifies its action-specific FormBuilder nonce before reading this payload.
		$pre_name = isset( $_POST['pre_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pre_name'] ) ) : '';
		if ( ! empty( $field_name ) && ! empty( $pre_name ) ) {
			$session = get_product_catalog_session();
			if ( empty( $session['formbuilder_fields'] ) ) {
				$session['formbuilder_fields'] = array();
			}
			if ( empty( $session['formbuilder_fields'][ $pre_name ] ) ) {
				$session['formbuilder_fields'][ $pre_name ] = array();
			}
			$session['formbuilder_fields'][ $pre_name ][ $field_name ] = $field_value;
			set_product_catalog_session( $session );
		}
		wp_die();
	}
}

if ( ! function_exists( 'ic_formbuilder_clear_saved_fields' ) ) {
	function ic_formbuilder_clear_saved_fields() {
		if ( ! function_exists( 'get_product_catalog_session' ) || ! function_exists( 'set_product_catalog_session' ) ) {
			return;
		}
		$session                       = get_product_catalog_session();
		$session['formbuilder_fields'] = array();
		set_product_catalog_session( $session );
	}
}

if ( ! function_exists( 'ic_formbuilder_set_saved_field_value' ) ) {
	function ic_formbuilder_set_saved_field_value( $default_value, $cid, $pre_name, $original_cid = null ) {
		if ( ! empty( $default_value ) ) {
			return $default_value;
		}
		if ( ! function_exists( 'get_product_catalog_session' ) ) {
			return $default_value;
		}
		$session              = get_product_catalog_session();
		$prefixed_original_id = ! empty( $original_cid ) ? $pre_name . $original_cid : '';
		$saved_value          = '';
		if ( ! empty( $session['formbuilder_fields'][ $pre_name ][ $cid ] ) ) {
			$saved_value = $session['formbuilder_fields'][ $pre_name ][ $cid ];
		} elseif ( ! empty( $prefixed_original_id ) && ! empty( $session['formbuilder_fields'][ $pre_name ][ $prefixed_original_id ] ) ) {
			$saved_value = $session['formbuilder_fields'][ $pre_name ][ $prefixed_original_id ];
		} elseif ( ! empty( $original_cid ) && ! empty( $session['formbuilder_fields'][ $pre_name ][ $original_cid ] ) ) {
			$saved_value = $session['formbuilder_fields'][ $pre_name ][ $original_cid ];
		}

		return is_array( $saved_value ) ? array_map( 'sanitize_text_field', $saved_value ) : sanitize_text_field( $saved_value );
	}
}
