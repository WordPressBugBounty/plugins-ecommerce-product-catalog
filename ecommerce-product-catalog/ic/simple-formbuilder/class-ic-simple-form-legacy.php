<?php
/**
 * Legacy simple form builder implementation.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Legacy simple form builder implementation.
 */
class IC_Simple_Form_Legacy {
	// Legacy implementation retained for compatibility. The active implementation lives in
	// class-ic-simple-form.php. The list below is EXPLICIT on purpose: it was a bare
	// `phpcs:disable` until 2026-08-09, which silenced every sniff across 1,049 of this
	// file's 1,070 lines - including 160 WordPress.Security.* findings that nobody could see.
	// Only legacy-style and documentation sniffs are suppressed here. NO WordPress.Security.*
	// sniff is suppressed: those findings are real, they are now visible, and they are tracked
	// as their own remediation task. Do not add a Security sniff to this list.
	// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
	// phpcs:disable Squiz.Scope.MethodScope.Missing
	// phpcs:disable Squiz.Commenting.FunctionComment
	// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
	// phpcs:disable Squiz.PHP.CommentedOutCode.Found
	// phpcs:disable Universal.Operators.StrictComparisons
	// phpcs:disable Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnValueFound
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// Generic.Files.LineEndings.InvalidEOLChar is deliberately not listed. It is a file-level
	// sniff reported at line 1, so a disable starting here could not reach it anyway. It no
	// longer fires: this file is LF, in line with the whole tree since 2026-08-19.
	// phpcs:disable PSR2.Classes.ClassDeclaration.CloseBraceAfterBody
	// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
	// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	// phpcs:disable WordPress.Files.FileName.InvalidClassFileName

	/**
	 * Form prefix.
	 *
	 * @var mixed|string
	 */
	private $pre_name;

	/**
	 * Markup before the submit button.
	 *
	 * @var mixed|string
	 */
	private $before_button;

	/**
	 * Button label.
	 *
	 * @var mixed|string|null
	 */
	private $button_text;

	/**
	 * Form type.
	 *
	 * @var mixed|string
	 */
	private $form_type;

	/**
	 * Whether to show the form after submit.
	 *
	 * @var mixed|true
	 */
	private $show_form_after;

	/**
	 * Success message.
	 *
	 * @var mixed|null
	 */
	private $success_message;

	/**
	 * Line separator.
	 *
	 * @var mixed|string
	 */
	private $line;

	/**
	 * Whether to send email.
	 *
	 * @var mixed|true
	 */
	private $send_mail;

	/**
	 * Sender email.
	 *
	 * @var mixed|null
	 */
	private $message_sender;

	/**
	 * Receiver email.
	 *
	 * @var mixed|null
	 */
	private $message_receiver;

	/**
	 * Message topic.
	 *
	 * @var mixed|null
	 */
	private $message_topic;

	/**
	 * Button CSS class.
	 *
	 * @var mixed|string
	 */
	private $button_class;

	/**
	 * Whether to send customer email.
	 *
	 * @var mixed|true
	 */
	private $send_customer_email;

	/**
	 * Customer message topic.
	 *
	 * @var mixed|null
	 */
	private $customer_message_topic;

	/**
	 * Sender display name.
	 *
	 * @var mixed|null
	 */
	private $message_sender_name;

	/**
	 * Redirect URL.
	 *
	 * @var mixed|null
	 */
	private $redirect;

	/**
	 * Rendered content.
	 *
	 * @var false|string
	 */
	public $content;

	/**
	 * Form HTML output.
	 *
	 * @var string
	 */
	private $form_html;

	/**
	 * Form definition.
	 *
	 * @var mixed
	 */
	private $contact;

	/**
	 * Whether to show the form.
	 *
	 * @var true
	 */
	private $show_form;

	/**
	 * Error CSS class.
	 *
	 * @var string
	 */
	private $error_class;

	/**
	 * Required flag markup.
	 *
	 * @var string
	 */
	private $required;

	/**
	 * Uploaded attachments.
	 *
	 * @var array|mixed|null
	 */
	private $attachments;

	/**
	 * Customer email.
	 *
	 * @var mixed|string
	 */
	private $customer_email;

	/**
	 * Error state.
	 *
	 * @var mixed|true|null
	 */
	private $error;

	/**
	 * Error output.
	 *
	 * @var array
	 */
	private $error_output;

	/**
	 * Email template flag.
	 *
	 * @var bool
	 */
	private $email_template;

	/**
	 * Disabled fields markup.
	 *
	 * @var string
	 */
	private $fields_disabled;

	/**
	 * Small field counter.
	 *
	 * @var int
	 */
	private $small_count;

	/**
	 * Section flag.
	 *
	 * @var bool
	 */
	private $is_section;

	/**
	 * Form type CSS class.
	 *
	 * @var mixed|string
	 */
	private $form_type_class;

	/**
	 * Maximum field size used by the form.
	 *
	 * @var string
	 */
	private $max_size;

	/**
	 * Notification header prefix.
	 *
	 * @var string
	 */
	private $before_header;

	/**
	 * Notification header suffix.
	 *
	 * @var string
	 */
	private $after_header;

	/**
	 * Opening wrapper HTML.
	 *
	 * @var string
	 */
	private $content_pre;

	/**
	 * Closing wrapper HTML.
	 *
	 * @var string
	 */
	private $content_post;

	/**
	 * Cached submit state key.
	 *
	 * @var string
	 */
	private $submit_state_key;

	/**
	 * Whether the current instance should process submission only.
	 *
	 * @var bool
	 */
	private $process_only = false;

	/**
	 * Cached submit state.
	 *
	 * @var array|false
	 */
	private $submit_state = false;

	/**
	 * Initialize the legacy form builder.
	 *
	 * @param mixed       $form_fields            Form field configuration.
	 * @param string      $pre_name               Form prefix.
	 * @param string      $before_button          Markup before the submit button.
	 * @param string|null $button_text            Button label.
	 * @param string      $form_type              Form type.
	 * @param bool        $show_form_after        Whether to show the form after submit.
	 * @param mixed       $success_message        Success message.
	 * @param string      $line                   Line separator.
	 * @param bool        $send_mail              Whether to send email.
	 * @param string|null $message_sender         Sender email.
	 * @param string|null $message_receiver       Receiver email.
	 * @param string|null $message_topic          Message topic.
	 * @param string      $button_class           Button CSS class.
	 * @param bool        $send_customer_email    Whether to send customer email.
	 * @param string|null $customer_message_topic Customer message topic.
	 * @param string|null $message_sender_name    Sender display name.
	 * @param string|null $redirect               Redirect URL.
	 */
	public function __construct(
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
		$redirect = null,
		$process_only = false
	) {

		$this->pre_name               = $pre_name;
		$this->before_button          = $before_button;
		$this->button_text            = $button_text;
		$this->form_type              = $form_type;
		$this->show_form_after        = $show_form_after;
		$this->success_message        = $success_message;
		$this->line                   = $line;
		$this->send_mail              = $send_mail;
		$this->message_sender         = $message_sender;
		$this->message_receiver       = apply_filters( 'ic_formbuilder_admin_message_receiver', $message_receiver );
		$this->message_topic          = $message_topic;
		$this->button_class           = $button_class;
		$this->send_customer_email    = $send_customer_email;
		$this->customer_message_topic = $customer_message_topic;
		$this->message_sender_name    = $message_sender_name;
		$this->redirect               = $redirect;
		$this->process_only           = ! empty( $process_only );
		$this->submit_state_key       = 'formbuilder_submit_state_' . $this->pre_name;
		$this->content                = ic_get_global( 'formbuilder_submit_content_' . $this->pre_name );
		if ( $this->content ) {
			// Cached content is already stored on the instance; stop the rest of the setup.
			return;
		} else {
			$this->content = '';
		}
		$this->form_html   = '';
		$this->button_text = empty( $this->button_text ) ? __( 'Submit', 'ecommerce-product-catalog' ) : $this->button_text;
		$this->contact     = json_decode( $form_fields );
		$this->show_form   = true;
		$this->error_class = '';
		$this->required    = '';
		ic_enqueue_simple_form_css();
		if ( function_exists( 'form_builder_enqueue_styles' ) ) {
			form_builder_enqueue_styles();
		}
		$this->process_form();
	}

	/**
	 * Returns the cached submit state.
	 *
	 * @return array|false
	 */
	public function submission_state() {
		return $this->submit_state;
	}

	/**
	 * Checks if form field should be visible in email
	 *
	 * @param type $field
	 *
	 * @return boolean
	 */
	/**
	 * Reads one posted value, unslashed and recursively sanitized.
	 *
	 * The state-changing path (submit_form()) runs only behind secure(), which verifies the
	 * wp_nonce_field emitted by security() plus a honeypot - see
	 * IC_Form_Submission_Helper::is_secure_submission(). PHPCS cannot follow that gate through
	 * a helper, hence the suppressions below. The renderers also read POST BEFORE that gate, to
	 * repopulate fields after a failed submit, which is why every read is sanitized here rather
	 * than trusted.
	 *
	 * @param string $key           Posted field name.
	 * @param mixed  $default_value Value to use when the field is absent.
	 *
	 * @return array|string
	 */
	private function posted_value( $key, $default_value = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Display repopulation and message building; the state change in submit_form() is gated by secure().
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default_value;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- See the docblock for the nonce. sanitize_posted() IS the sanitizer: it recurses into arrays and applies sanitize_text_field(), or sanitize_textarea_field() for multi-line fields, to every leaf, which PHPCS cannot follow through a method call.
		return $this->sanitize_posted( wp_unslash( $_POST[ $key ] ), $this->is_multiline_key( $key ) );
	}

	/**
	 * Tells whether a posted key belongs to a multi-line field of this form.
	 *
	 * The process_form() loop rewrites each $field->cid in place to the prefixed name it
	 * posts under,
	 * so both the bare and the prefixed form are matched: this runs before and after that.
	 *
	 * @param string $key Posted field name.
	 *
	 * @return bool
	 */
	private function is_multiline_key( $key ) {
		if ( empty( $this->contact->fields ) || ! function_exists( 'ic_formbuilder_is_multiline_field' ) ) {
			return false;
		}
		foreach ( $this->contact->fields as $field ) {
			if ( empty( $field->cid ) || ! ic_formbuilder_is_multiline_field( $field ) ) {
				continue;
			}
			if ( $key === $field->cid || $key === $this->pre_name . $field->cid ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively sanitizes a posted value, so array fields (checkboxes) are covered too.
	 *
	 * @param mixed $value     Raw value.
	 * @param bool  $multiline Whether the value comes from a textarea and must keep newlines.
	 *
	 * @return array|string
	 */
	private function sanitize_posted( $value, $multiline = false ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $index => $item ) {
				$clean[ $index ] = $this->sanitize_posted( $item, $multiline );
			}

			return $clean;
		}

		return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	/**
	 * Tells whether a posted field is set, mirroring isset() on the superglobal.
	 *
	 * @param string $key Posted field name.
	 *
	 * @return bool
	 */
	private function posted_isset( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence test only, no value is read; see posted_value().
		return isset( $_POST[ $key ] );
	}

	/**
	 * Escapes one posted value for use inside an HTML attribute.
	 *
	 * Posted values can legitimately be arrays (checkbox groups), and an attacker can turn any
	 * scalar field into one by posting name[]=x. esc_attr() raises a TypeError on an array under
	 * PHP 8, so an array collapses to an empty attribute here instead of taking the page down.
	 *
	 * @param mixed $value Sanitized posted value.
	 *
	 * @return string
	 */
	private function attr_value( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}

		return esc_attr( $value );
	}

	/**
	 * Escapes a filtered visitor value for the current mail context.
	 *
	 * @param mixed  $value      Filtered field value.
	 * @param string $field_type FormBuilder field type.
	 *
	 * @return string
	 */
	private function message_value( $value, $field_type = '' ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = esc_html( wp_specialchars_decode( (string) $value, ENT_QUOTES ) );
		if ( $this->email_template && in_array( $field_type, array( 'paragraph', 'textarea' ), true ) ) {
			$value = preg_replace( '/\r\n|\r|\n/', '<br>', $value );
		}

		return $value;
	}

	/**
	 * Tells whether a posted field is present and non-empty, mirroring ! empty() semantics.
	 *
	 * @param string $key Posted field name.
	 *
	 * @return bool
	 */
	private function posted_filled( $key ) {
		$value = $this->posted_value( $key );

		return ! empty( $value );
	}

	function is_email_visible( $field, $pre_name ) {
		if ( ! empty( $field->field_options->rules ) && ! empty( $field->field_options->rulesval ) ) {
			$rulestype = ! empty( $field->field_options->rulestype ) && $field->field_options->rulestype === '!=' ? '!=' : '=';
			$field_id  = apply_filters( 'ic_formbuilder_cid', $pre_name . $field->field_options->rules, '', $pre_name );
			if ( $rulestype === '=' && ( ! $this->posted_isset( $field_id ) || $this->posted_value( $field_id ) != $field->field_options->rulesval ) ) {
				return false;
			} elseif ( $rulestype === '!=' && $this->posted_isset( $field_id ) && $this->posted_value( $field_id ) == $field->field_options->rulesval ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Handles formbuilder file upload
	 *
	 * @param type $field_name
	 *
	 * @return string
	 */
	function handle_upload( $field_name ) {

		return class_exists( 'IC_Form_Submission_Helper' ) ? IC_Form_Submission_Helper::handle_upload( $field_name ) : '';
	}
	function field( $field, $field_id ) {
		if ( ! empty( $field->original_cid ) ) {
			$original_cid = $field->original_cid;
		} else {
			$original_cid = $field->cid;
		}
		$this->set_error_class( $field );
		$field_func       = $field->field_type;
		$field_form_value = $this->posted_isset( $field->cid ) ? $this->posted_value( $field->cid ) : apply_filters( 'ic_formbuilder_default_value', '', $field_id, $original_cid, $this->pre_name );
		$field_attributes = apply_filters( 'ic_forumbuilder_field_attributes', $this->fields_disabled, $field, $this->pre_name, $field_form_value );
		if ( method_exists( $this, $field_func ) ) {
			return $this->$field_func( $field, $field_id, $field_form_value, $field_attributes );
		} else {
			return $this->any_field( $field, $field_id, $field_form_value, $field_attributes, true );
		}
	}

	function section_break( $field, $field_id, $field_form_value, $field_attributes ) {
		$this->form_html .= '<h5 class="section-break">' . $field->label . '</h5>';
		$this->form_html .= '<input type="hidden" value="' . $field->label . '" name="' . $field->cid . '" />';

		if ( empty( $this->before_header ) ) {
			$this->before_header = apply_filters( 'ic_catalog_notification_before_header', '_____________________________________________' . $this->line, $this->email_template );
		}
		if ( empty( $this->after_header ) ) {
			$this->after_header = apply_filters( 'ic_catalog_notification_after_header', $this->line . $this->line, $this->email_template );
		}
		$section_break  = $this->before_header;
		$section_break .= strtoupper( sanitize_text_field( $field->label ) );
		$section_break .= $this->after_header;

		return $section_break;
	}

	function paragraph( $field, $field_id, $field_form_value, $field_attributes ) {
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field"><textarea class="' . $field->field_options->size . ' ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' name="' . $field->cid . '">' . esc_html( is_scalar( $field_form_value ) ? (string) $field_form_value : '' ) . '</textarea></div>';

		return $this->any_field( $field, $field_id, $field_form_value, $field_attributes );
	}

	function checkboxes( $field, $field_id, $field_form_value, $field_attributes ) {
		$checked = '';
		if ( $field->label === ' *' ) {
			$field->label = '';
		}
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field">';
		foreach ( $field->field_options->options as $option_key => $radio_option ) {
			$radio_option = apply_filters( 'ic_formbuilder_option', $radio_option );
			$option_id    = sanitize_html_class( $field->cid . '_' . $option_key );
			if ( ! $this->posted_isset( $field->cid ) && $radio_option->checked ) {
				$checked = 'checked';
			} elseif ( $this->posted_isset( $field->cid ) && $this->posted_value( $field->cid ) == $radio_option->label ) {
				$checked = 'checked';
			} else {
				$checked = '';
			}
			$checked          = apply_filters( 'ic_formbuilder_option_attributes', $checked, $radio_option, $field );
			$this->form_html .= '<input class="input ' . $this->error_class . '" id="' . esc_attr( $option_id ) . '" value="' . esc_attr( strip_tags( $radio_option->label ) ) . '" ' . $checked . ' ' . $field_attributes . ' type="checkbox" name="' . $field->cid . '[]" /> <label for="' . esc_attr( $option_id ) . '">' . apply_filters( 'ic_formbuilder_option_label', $radio_option->label, $radio_option ) . '</label><br>';
		}
		$this->form_html .= '</div>';

		if ( is_array( $this->posted_value( $field_id ) ) ) {
			$checkboxes = str_replace( '::', ':', $field->label . ': ' );
			$add_comma  = false;
			foreach ( $this->posted_value( $field_id ) as $field_value ) {
				$field_value = apply_filters( 'ic_formbuilder_message_field_value', $field_value, $field_id, $field->field_type );
				$field_value = $this->message_value( $field_value, $field->field_type );
				if ( $add_comma ) {
					$checkboxes .= ', ';
				}
				$checkboxes .= $field_value;
				$add_comma   = true;
			}
			$checkboxes .= $this->line;

			return $checkboxes;
		}
	}

	function radio( $field, $field_id, $field_form_value, $field_attributes ) {
		$checked          = '';
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field">';
		foreach ( $field->field_options->options as $option_key => $radio_option ) {
			$radio_option = apply_filters( 'ic_formbuilder_option', $radio_option );
			$option_id    = sanitize_html_class( $field->cid . '_' . $option_key );
			if ( ! $this->posted_isset( $field->cid ) && $radio_option->checked ) {
				$checked = 'checked';
			} elseif ( $this->posted_isset( $field->cid ) && $this->posted_value( $field->cid ) == $radio_option->label ) {
				$checked = 'checked';
			} else {
				$checked = '';
			}
			$checked          = apply_filters( 'ic_formbuilder_option_attributes', $checked, $radio_option, $field );
			$this->form_html .= '<input class="input ' . $this->error_class . '" id="' . esc_attr( $option_id ) . '" value="' . esc_attr( $radio_option->label ) . '" ' . $checked . ' ' . $field_attributes . ' type="' . $field->field_type . '" name="' . $field->cid . '" /> <label for="' . esc_attr( $option_id ) . '">' . apply_filters( 'ic_formbuilder_option_label', $radio_option->label, $radio_option ) . '</label>' . $this->line;
		}
		$this->form_html .= '</div>';

		return $this->any_field( $field, $field_id, $field_form_value, $field_attributes );
	}

	function dropdown( $field, $field_id, $field_form_value, $field_attributes ) {
		$checked          = '';
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field">';
		if ( count( $field->field_options->options ) > 5 ) {
			$this->error_class .= ' ic-chosen';
		}
		$this->form_html .= '<select data-placeholder="Choose a ' . str_replace(
			array(
				':',
				'*',
			),
			'',
			$field->label
		) . '..." class="' . $this->error_class . ' ' . $this->required . '" ' . $field_attributes . ' name="' . $field->cid . '">';
		if ( ! empty( $field->field_options->include_blank_option ) ) {
			$this->form_html .= '<option value=""></option>';
		}
		foreach ( $field->field_options->options as $radio_option ) {
			if ( ! $this->posted_isset( $field->cid ) && $radio_option->checked ) {
				$checked = 'selected';
			} else {
				$checked = '';
			}
			$checked          = apply_filters( 'ic_formbuilder_option_attributes', $checked, $radio_option, $field );
			$this->form_html .= '<option value="' . apply_filters( 'ic_formbuilder_dorpdown_option_value', $radio_option->label, $field->cid ) . '" ' . $checked . '>' . apply_filters( 'ic_formbuilder_option_label', $radio_option->label, $radio_option ) . '</option>';
		}
		$this->form_html .= '</select>';
		$this->form_html .= '</div>';

		return $this->any_field( $field, $field_id, $field_form_value, $field_attributes );
	}

	/**
	 * Render one country dropdown field.
	 *
	 * @param object $field            Field definition.
	 * @param string $field_id         Submitted field identifier.
	 * @param mixed  $field_form_value Current field value.
	 * @param string $field_attributes Field HTML attributes.
	 *
	 * @return string|null
	 */
	function dropdown_country( $field, $field_id, $field_form_value, $field_attributes ) {
		if ( empty( $field->field_options ) || ! is_object( $field->field_options ) ) {
			$field->field_options = new stdClass();
		}
		$field->field_options->options              = ic_dropdown_country_options( $field_form_value );
		$field->field_options->include_blank_option = true;

		return $this->dropdown( $field, $field_id, $field_form_value, $field_attributes );
	}

	/**
	 * Render one state dropdown field.
	 *
	 * @param object $field            Field definition.
	 * @param string $field_id         Submitted field identifier.
	 * @param mixed  $field_form_value Current field value.
	 * @param string $field_attributes Field HTML attributes.
	 *
	 * @return string|null
	 */
	function dropdown_state( $field, $field_id, $field_form_value, $field_attributes ) {
		if ( empty( $field->field_options ) || ! is_object( $field->field_options ) ) {
			$field->field_options = new stdClass();
		}
		$field->field_options->options              = ic_dropdown_state_options( $field_form_value );
		$field->field_options->include_blank_option = true;

		return $this->dropdown( $field, $field_id, $field_form_value, $field_attributes );
	}

	function date( $field, $field_id, $field_form_value, $field_attributes ) {
		$month            = $this->posted_value( $field->cid . '_month', date( 'm' ) );
		$day              = $this->posted_value( $field->cid . '_day', date( 'd' ) );
		$year             = $this->posted_value( $field->cid . '_year', date( 'Y' ) );
		$min              = isset( $field->field_options->min ) ? $field->field_options->min : '';
		$max              = isset( $field->field_options->max ) ? $field->field_options->max : '';
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '_month">' . $field->label . '</label></div>';
		$this->form_html .= '<div class="field">';
		if ( ! empty( $field->field_options->picker ) ) {
			$this->form_html .= '<input type="text" class="ic_datepicker" name="' . $field->cid . '" value="' . ic_get_default_date_format( true ) . '">';
		} else {
			$this->form_html .= '<span class="month"><input min="1" max="12" step="1" class="input number date-field ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '_month" value="' . $this->attr_value( $month ) . '" id="' . $field->cid . '_month" /><label for="' . $field->cid . '_month">MM</label></span> <span class="sep">/</span> <span class="day"><input min="1" max="31" step="1" class="input number date-field ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '_day" value="' . $this->attr_value( $day ) . '" id="' . $field->cid . '_day" /><label for="' . $field->cid . '_day">DD</label></span> <span class="sep">/</span> <span class="year"><input min="' . $min . '" max="' . $max . '" step="1" class="input number date-field ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '_year" value="' . $this->attr_value( $year ) . '" id="' . $field->cid . '_year" /><label for="' . $field->cid . '_year">YYYY</label></span>';
		}
		$this->form_html .= '</div>';

		if ( ( ! $this->posted_filled( $field_id . '_month' ) || ! $this->posted_filled( $field_id . '_day' ) || ! $this->posted_filled( $field_id . '_year' ) ) && ! $this->posted_filled( $field_id ) ) {
			if ( ! empty( $field->required ) ) {
				$this->error                     = true;
				$this->error_output[ $field_id ] = $field->label;
			}
		} else {
			if ( $this->posted_filled( $field_id ) ) {
				$field_value_date = $this->posted_value( $field_id );
			} else {
				$field_value_date = $this->posted_value( $field_id . '_month' ) . '/' . $this->posted_value( $field_id . '_day' ) . '/' . $this->posted_value( $field_id . '_year' );
			}
			$field_value = apply_filters( 'ic_formbuilder_message_field_value', $field_value_date, $field_id, $field->field_type );
			$field_value = $this->message_value( $field_value, $field->field_type );

			return str_replace( '::', ':', $field->label . ': ' . $field_value . $this->line );
		}
	}

	function time( $field, $field_id, $field_form_value, $field_attributes ) {
		$hour             = $this->posted_value( $field->cid . '_hour', date( 'h' ) );
		$minute           = $this->posted_value( $field->cid . '_minute', date( 'i' ) );
		$second           = $this->posted_value( $field->cid . '_second', date( 's' ) );
		$day_time         = $this->posted_value( $field->cid . '_day_time', date( 'a' ) );
		$min              = isset( $field->field_options->min ) ? $field->field_options->min : '';
		$max              = isset( $field->field_options->max ) ? $field->field_options->max : '';
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '_hour">' . $field->label . '</label></div>';
		$this->form_html .= '<div class="field">';
		if ( ! empty( $field->field_options->picker ) ) {
			$this->form_html .= '<input type="text" class="ic_timepicker" name="' . $field->cid . '" value="' . ic_get_default_time_format( true ) . '">';
		} else {
			$this->form_html .= '<span class="hour"><input min="1" max="12" step="1" class="input number time ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '_hour" value="' . $this->attr_value( $hour ) . '" id="' . $field->cid . '_hour" /><label for="' . $field->cid . '_hour">HH</label></span> <span class="sep">:</span> <span class="minute"><input min="0" max="60" step="1" class="input number time ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '_minute" value="' . $this->attr_value( $minute ) . '" id="' . $field->cid . '_minute" /><label for="' . $field->cid . '_minute">MM</label></span> <span class="sep">:</span> <span class="second"><input min="0" max="60" step="1" class="input number time ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '_second" value="' . $this->attr_value( $second ) . '" id="' . $field->cid . '_second" /><label for="' . $field->cid . '_second">SS</label></span> <span class="time-of-day"><select name="' . $field->cid . '_day_time"><option value="am" ' . selected( $day_time, 'am', 0 ) . '>AM</option><option value="pm" ' . selected( $day_time, 'pm', 0 ) . '>PM</option></select></span>';
		}
		$this->form_html .= '</div>';

		if ( ( ! $this->posted_filled( $field_id . '_hour' ) || '' === $this->posted_value( $field_id . '_minute' ) || '' === $this->posted_value( $field_id . '_second' ) ) && ! $this->posted_filled( $field_id ) ) {
			if ( ! empty( $field->required ) ) {
				$this->error                     = true;
				$this->error_output[ $field_id ] = $field->label;
			}
		} else {
			if ( $this->posted_filled( $field_id ) ) {
				$field_value_time = $this->posted_value( $field_id );
			} else {
				$field_value_time = $this->posted_value( $field_id . '_hour' ) . ':' . $this->posted_value( $field_id . '_minute' ) . ':' . $this->posted_value( $field_id . '_second' ) . ' ' . strtoupper( $this->posted_value( $field_id . '_day_time' ) );
			}
			$field_value = apply_filters( 'ic_formbuilder_message_field_value', $field_value_time, $field_id, $field->field_type );
			$field_value = $this->message_value( $field_value, $field->field_type );
			$time        = str_replace( '::', ':', $field->label . ': ' . $field_value . $this->line );

			return $time;
		}
	}

	function address( $field, $field_id, $field_form_value, $field_attributes ) {
		$street  = $this->posted_value( $field->cid . '_street' );
		$city    = $this->posted_value( $field->cid . '_city' );
		$state   = $this->posted_value( $field->cid . '_state' );
		$zip     = $this->posted_value( $field->cid . '_zip' );
		$country = $this->posted_value( $field->cid . '_country' );

		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div>';
		$this->form_html .= '<div class="field">';
		$this->form_html .= '<div class="address_line"><input class="input street large ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="text" name="' . $field->cid . '_street" value="' . $this->attr_value( $street ) . '" /><label for="' . $field->cid . '_street">' . __( 'Street', 'ecommerce-product-catalog' ) . '</label></div>';
		$this->form_html .= '<div class="address_line"><span><input class="input city medium ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="text" name="' . $field->cid . '_city" value="' . $this->attr_value( $city ) . '" /><label for="' . $field->cid . '_city">' . __( 'City', 'ecommerce-product-catalog' ) . '</label></span> <span><input class="input state medium ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="text" name="' . $field->cid . '_state" value="' . $this->attr_value( $state ) . '" /><label for="' . $field->cid . '_state">' . __( 'State / Province / Region', 'ecommerce-product-catalog' ) . '</label></span></div>';
		$this->form_html .= '<div class="address_line"><span><input class="input zip medium ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="text" name="' . $field->cid . '_zip" value="' . $this->attr_value( $zip ) . '" /><label for="' . $field->cid . '_zip">' . __( 'ZIP code', 'ecommerce-product-catalog' ) . '</label></span> <span>' . ic_country_selector( $field->cid . '_country', $this->required, $country, 0 ) . '<label for="' . $field->cid . '_country">' . __( 'Country', 'ecommerce-product-catalog' ) . '</label></span></div>';
		$this->form_html .= '</div>';

		if ( ! $this->posted_filled( $field_id . '_street' ) || ! $this->posted_filled( $field_id . '_city' ) || ! $this->posted_filled( $field_id . '_state' ) || ! $this->posted_filled( $field_id . '_zip' ) || ! $this->posted_filled( $field_id . '_country' ) ) {
			if ( ! empty( $field->required ) ) {
				$this->error                     = true;
				$this->error_output[ $field_id ] = $field->label;
			}
		} else {
			$field_value_address = $this->posted_value( $field_id . '_street' ) . ', ' . $this->posted_value( $field_id . '_zip' ) . ' ' . $this->posted_value( $field_id . '_city' ) . ', ' . $this->posted_value( $field_id . '_state' ) . ' ' . ic_get_country_name( $this->posted_value( $field_id . '_country' ) );
			$field_value         = apply_filters( 'ic_formbuilder_message_field_value', $field_value_address, $field_id, $field->field_type );
			$field_value         = $this->message_value( $field_value, $field->field_type );
			$address             = str_replace( '::', ':', $field->label . ': ' . $field_value . $this->line );

			return $address;
		}
	}

	function file( $field, $field_id, $field_form_value, $field_attributes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Runs inside submit_form(), which secure() gates. Only ['error'] and ['size'] are read here, both as presence/emptiness tests; the payload itself is handled in handle_upload().
		$file_entry = isset( $_FILES[ $field_id ] ) && is_array( $_FILES[ $field_id ] ) ? $_FILES[ $field_id ] : array();
		if ( empty( $file_entry ) || ! empty( $file_entry['error'] ) || empty( $file_entry['size'] ) ) {
			if ( ! empty( $field->required ) ) {
				$this->error                     = true;
				$this->error_output[ $field_id ] = $field->label . '- file error';
			}
		} else {
			$this->attachments[] = $this->handle_upload( $field_id );
		}
	}

	function email( $field, $field_id, $field_form_value, $field_attributes ) {
		if ( $this->posted_filled( $field_id ) ) {
			if ( is_email( $this->posted_value( $field_id ) ) ) {
				$this->customer_email = $this->posted_value( $field_id );
			} else {
				$this->error                     = true;
				$this->error_output[ $field_id ] = $field->label;
			}
		}

		return $this->any_field( $field, $field_id, $field_form_value, $field_attributes, true );
	}

	function website( $field, $field_id, $field_form_value, $field_attributes ) {
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field"><span><input class="input website ' . $field->field_options->size . ' ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="url" name="' . $field->cid . '" placeholder="http://" value="' . $this->attr_value( $field_form_value ) . '" /></span></div>';

		return $this->any_field( $field, $field_id, $field_form_value, $field_attributes );
	}

	function price( $field, $field_id, $field_form_value, $field_attributes ) {
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field"><span><input min="0" step="0.01" class="input price ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '" placeholder="' . product_currency() . '" value="' . $this->attr_value( $field_form_value ) . '" /></span></div>';

		return $this->any_field( $field, $field_id, $field_form_value, $field_attributes );
	}

	function number( $field, $field_id, $field_form_value, $field_attributes ) {
		$min              = isset( $field->field_options->min ) ? $field->field_options->min : '';
		$max              = isset( $field->field_options->max ) ? $field->field_options->max : '';
		$units            = isset( $field->field_options->units ) ? $field->field_options->units : '';
		$step             = ( isset( $field->field_options->integer_only ) && $field->field_options->integer_only == 1 ) ? 'step="1"' : 'step="0.01"';
		$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field"><input min="' . $min . '" max="' . $max . '" ' . $step . ' class="input number  ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="number" name="' . $field->cid . '" value="' . $this->attr_value( $field_form_value ) . '" /><span class="units">' . $units . '</span></div>';

		return $this->any_field( $field, $field_id, $field_form_value, $field_attributes );
	}

	function any_field( $field, $field_id, $field_form_value, $field_attributes, $form_html = false ) {
		if ( $form_html ) {
			$this->form_html .= '<div class="label"><label for="' . $field->cid . '">' . $field->label . '</label></div><div class="field"><span><input class="input ' . $field->field_options->size . ' ' . $this->error_class . '" ' . $this->required . ' ' . $field_attributes . ' type="' . $field->field_type . '" name="' . $field->cid . '" value="' . $this->attr_value( $field_form_value ) . '" /></span></div>';
		}
		if ( ! $this->posted_filled( $field_id ) ) {
			if ( ! empty( $field->required ) ) {
				$this->error                     = true;
				$this->error_output[ $field_id ] = $field->label;
			}
		} else {
			$field_value = apply_filters( 'ic_formbuilder_message_field_value', $this->posted_value( $field_id ), $field_id, $field->field_type );
			if ( $field->field_type == 'price' ) {
				$field_value = price_format( $field_value );
			}
			$field_value = $this->message_value( $field_value, $field->field_type );
			$return = apply_filters( 'formbuilder_message_row', str_replace( '::', ':', $field->label . ': ' . $field_value . $this->line ), $field, $field_id, $this->line );

			return $return;
		}
	}

	function send_email( $message ) {
		if ( ! is_email( $this->message_sender ) ) {
			return;
		}

		$this->message_topic = isset( $this->message_topic ) ? $this->message_topic : __( 'Message from Website', 'ecommerce-product-catalog' );
		$this->message_topic = apply_filters( 'admin_message_topic', $this->message_topic );
		$admin_message       = apply_filters( 'ic_formbuilder_admin_email', $message, $this->pre_name );
		$this->attachments   = apply_filters( 'ic_formbuilder_admin_attachments', $this->attachments, $this->pre_name, $this->customer_email );
		ic_mail( $admin_message, $this->customer_email, $this->message_sender, $this->message_receiver, $this->message_topic, $this->email_template, $this->attachments );
		if ( $this->send_customer_email ) {
			$this->customer_message_topic = isset( $this->customer_message_topic ) ? $this->customer_message_topic : __( 'Confirmation', 'ecommerce-product-catalog' );
			$this->customer_message_topic = apply_filters( 'user_message_topic', $this->customer_message_topic );
			$user_message                 = apply_filters( 'ic_formbuilder_user_email', $message, $this->pre_name, $this->customer_email, $this->contact );
			$this->message_sender_name    = empty( $this->message_sender_name ) ? get_bloginfo( 'name' ) : $this->message_sender_name;
			$this->send_customer_email    = apply_filters( 'enable_customer_email', $this->send_customer_email, $this->customer_email, $this->customer_message_topic, $user_message, $this->message_sender_name, $this->message_sender );
			if ( $this->send_customer_email ) {
				ic_mail( $user_message, $this->message_sender_name, $this->message_sender, $this->customer_email, $this->customer_message_topic, $this->email_template );
			}
		}
		do_action( 'ic_formbuilder_email', $message, $this->customer_email, $this->redirect );
		$this->success_message = isset( $this->success_message ) ? $this->success_message : __( 'Your message has been sent successfully!', 'ecommerce-product-catalog' );
		$this->content        .= '<div class="ic_formbuilder_success center">' . implecode_success( $this->success_message, 0 ) . '</div>';
		$this->content        .= '<script>jQuery(document).ready(function() {jQuery(document.body).addClass("form-submitted");});</script>';
	}

	function submit_form() {
		global $submitted_form_name;
		$submitted_form_name = $this->pre_name;

		$message              = '';
		$this->error          = false;
		$this->customer_email = '';
		$this->error_output   = array();
		$this->email_template = false;
		if ( $this->line == '<br>' ) {
			$this->email_template = true;
		}
		$this->attachments = array();
		global $ic_formbuilder_filled_fields;
		$ic_formbuilder_filled_fields = array();
		foreach ( $this->contact->fields as $field ) {
			if ( ! $this->is_email_visible( $field, $this->pre_name ) ) {
				continue;
			}
			$field_id = apply_filters( 'ic_formbuilder_cid', $this->pre_name . $field->cid, $field, $this->pre_name );
			if ( $this->posted_filled( $field_id ) ) {
				$ic_formbuilder_filled_fields[ $field_id ] = $this->posted_value( $field_id );
			}
			$message .= $this->field( $field, $field_id );
		}
		$ic_formbuilder_filled_fields = apply_filters( 'ic_formbuilder_filled_fields', $ic_formbuilder_filled_fields );
		$this->error                  = apply_filters( 'ic_formbuilder_error', $this->error );
		$this->error_output           = apply_filters( 'ic_formbuilder_error_output', $this->error_output );

		if ( ! $this->error ) {
			$this->fields_disabled = ' disabled';
			if ( ! is_email( $this->customer_email ) && function_exists( 'ic_get_digital_customer_email' ) ) {
				$this->customer_email = ic_get_digital_customer_email();
			}
			do_action( 'ic_formbuilder_before_mail', $message, $this->customer_email, $this->redirect, $this->contact, $this->pre_name );
			if ( $this->send_mail ) {
				$this->send_email( $message );
			} else {
				do_action( 'ic_formbuilder_no_email', $message, $this->customer_email, $this->redirect );
			}
			do_action( 'ic_formbuilder_after_mail', $message, $this->customer_email, $this->redirect, $this->contact );
			if ( ! empty( $this->redirect ) ) {
				ob_end_clean();
				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- $this->redirect is a constructor argument supplied by the calling plugin (null by default), never request input. An off-site "thank you" URL is the documented intent of the parameter, which is why esc_url() explicitly allows http and https; wp_safe_redirect() would silently drop every external target.
				wp_redirect( esc_url_raw( $this->redirect ) );
				exit;
				exit;
			}
			$this->show_form = $this->show_form_after;
		} else {
			$box_content = __( 'Please fix the following', 'ecommerce-product-catalog' ) . ': <div class="smaller">';
			foreach ( $this->error_output as $field_label ) {
				$box_content .= str_replace( ':', '', $field_label ) . $this->line;
			}
			$box_content   .= '</div>';
			$this->content .= '<div class="ic_formbuilder_warning center">' . implecode_warning( $box_content, 0 );

			$this->content .= '</div>';
			$this->content .= '<script>jQuery(document).ready(function() {jQuery(document.body).addClass("form-error");});</script>';
		}
	}

	function max_size( $field ) {
		if ( empty( $this->max_size ) ) {
			$this->max_size = 'large';
		}
		if ( isset( $field->field_options->size ) && $field->field_options->size == 'large' ) {
			$this->max_size = 'large';
		} elseif ( ! isset( $field->field_options->size ) || ( isset( $field->field_options->size ) && $field->field_options->size == 'medium' ) ) {
			$this->max_size = 'medium';
		}
	}

	function field_comment( $field ) {
		if ( $this->form_type != 'label-top' ) {
			$field_comment = isset( $field->field_options->description ) ? '<div class="order_form_row row comment"><div class="label"></div><div class="field"><div class="field-comment">' . $field->field_options->description . '</div></div></div>' : '';
		} else {
			$field_comment              = isset( $field->field_options->description ) ? '<div class="field-comment">' . $field->field_options->description . '</div>' : '';
			$field->field_options->size = ( $field->field_type == 'section_break' || $field->field_type == 'address'/* || $field->field_type == 'date' || $field->field_type == 'time' */ ) ? 'large' : ( isset( $field->field_options->size ) ? $field->field_options->size : 'medium' );
		}

		return $field_comment;
	}

	function set_error_class( $field ) {
		$this->error_class = '';
		if ( isset( $this->error_output[ $field->cid ] ) ) {
			$this->error_class = 'error';
		}
	}

	function row_html_start_end( $field ) {
		if ( $this->form_type == 'label-left' ) {
			$this->content .= '<div class="' . $this->pre_name . 'row row ' . $field->field_type . '">';
		} elseif ( $field->field_options->size == 'small' && $this->small_count == 0 ) {
			$this->content    .= '<div class="small-table"><div class="' . $this->pre_name . 'row small-row"><div class="cell ' . $field->field_type . '"><div class="small-container first">';
			$this->small_count = 1;
		} elseif ( $field->field_options->size == 'small' && $this->small_count == 1 ) {
			$this->content    .= '</div></div><div class="cell second ' . $field->field_type . '"><div class="small-container second">';
			$this->small_count = 2;
		} elseif ( $field->field_options->size == 'small' && $this->small_count == 2 ) {
			$this->content    .= '</div></div></div></div>';
			$this->content    .= '<div class="small-table"><div class="' . $this->pre_name . 'row small-row ' . $field->field_type . '"><div class="cell"><div class="small-container first">';
			$this->small_count = 1;
		} elseif ( $field->field_options->size != 'small' && $this->small_count != 0 ) {
			$this->content    .= '</div></div></div></div>';
			$this->small_count = 0;
		}
	}

	function section_html_start( $field ) {
		if ( ! $this->is_section && $field->field_type != 'section_break' ) {
			$this->content   .= '<div class="form_section">';
			$this->is_section = true;
		} elseif ( $this->is_section && $field->field_type == 'section_break' ) {
			$this->content   .= '</div>';
			$this->content   .= '<div class="form_section">';
			$this->is_section = true;
		} elseif ( $field->field_type == 'section_break' ) {
			$this->content   .= '<div class="form_section">';
			$this->is_section = true;
		}
		if ( $this->is_section ) {
			$this->content .= apply_filters( 'ic_simple_form_section_start', '', $this->pre_name );
		}
	}

	function process_form() {
		$this->small_count = 0;

		$this->fields_disabled = '';
		if ( $this->hydrate_submit_state() ) {
			if ( $this->process_only ) {
				return $this->submit_state;
			}
		} elseif ( $this->posted_isset( $this->pre_name . 'submit' ) && $this->secure() ) {
			$this->submit_form();
			$this->cache_submit_state();
		}
		if ( $this->process_only ) {
			return $this->submit_state;
		}
		if ( $this->show_form ) {

			$this->form_type_class = ( $this->form_type == 'centered' ) ? 'label-left centered' : $this->form_type;
			$this->form_type       = ( $this->form_type == 'centered' ) ? 'label-left' : $this->form_type;

			if ( ! empty( $this->redirect ) ) {
				$this->content .= '<input type="hidden" value="' . esc_url(
					$this->redirect,
					array(
						'http',
						'https',
					)
				) . '" name="ic_formbuilder_redirect" />';
			}
			$this->content    = apply_filters( 'ic_formbuilder_form_beginning', $this->content, $this->pre_name );
			$this->is_section = false;
			// $this->content .= '<div class="form_section">';
			foreach ( $this->contact->fields as $field ) {
				$this->form_html = '';
				$this->max_size( $field );
				if ( ! isset( $field->field_options ) ) {
					$field->field_options = new stdClass();
				}

				if ( $this->form_type == 'label-top' ) {
					$field->field_options->size = ( $field->field_type == 'section_break' || $field->field_type == 'address'/* || $field->field_type == 'date' || $field->field_type == 'time' */ ) ? 'large' : ( isset( $field->field_options->size ) ? $field->field_options->size : 'medium' );
				} else {
					$field->field_options->size = isset( $field->field_options->size ) ? $field->field_options->size : 'medium';
				}
				$field->original_cid = $field->cid;
				$field->cid          = apply_filters( 'ic_formbuilder_cid', $this->pre_name . $field->cid, $field, $this->pre_name );
				$field_value         = $this->posted_isset( $field->cid ) ? $this->posted_value( $field->cid ) : apply_filters( 'ic_formbuilder_default_value', '', $field->cid, $field->original_cid, $this->pre_name );
				// $field_attributes  = apply_filters( 'ic_forumbuilder_field_attributes', $this->fields_disabled, $field, $this->pre_name, $field_value );

				$field_comment = $this->field_comment( $field );

				if ( isset( $field->required ) && $field->required ) {
					$this->required = 'required';
					$field->label   = $field->label . ' *';
				} else {
					$this->required = '';
				}

				$this->section_html_start( $field );

				$this->row_html_start_end( $field );
				// $field_value       = isset( $_POST[ $field->cid ] ) ? $_POST[ $field->cid ] : apply_filters( 'ic_formbuilder_default_value', '', $field->cid );

				$this->field( $field, $field->cid );
				if ( ! empty( $this->form_html ) ) {
					$this->content .= $this->form_html;
				}

				if ( $this->form_type != 'label-top' ) {
					$this->content .= '</div>' . $field_comment;
				} else {
					$this->content .= $field_comment;
				}
				$this->content = apply_filters( 'ic_formbuilder_after_field', $this->content, $field->field_type, $field->cid, $this->form_type );
			}
			// $this->content .= '</div>';
			$this->content .= $this->before_button;
			if ( $this->is_section ) {
				$this->content .= '</div>';
			}

			$this->form_submit_button();

			$this->content_pre  = '<div class="ic-form ' . $this->form_type_class . ' ' . $this->max_size . '"><form action="' . $this->current_url() . '" enctype="multipart/form-data" method="post">';
			$this->content_pre .= apply_filters( 'ic_simple_form_start', '', $this->pre_name );
			$this->content_post = '</form></div>';

			$this->content = $this->content_pre . $this->content . $this->content_post;
		}
		ic_save_global( 'formbuilder_submit_content_' . $this->pre_name, $this->content );

		return $this->content;
	}

	/**
	 * Loads one cached submit state into the current renderer.
	 *
	 * @return bool
	 */
	private function hydrate_submit_state() {
		$state = ic_get_global( $this->submit_state_key );
		if ( ! is_array( $state ) ) {
			return false;
		}

		$this->submit_state    = $state;
		$this->content         = isset( $state['content'] ) ? (string) $state['content'] : '';
		$this->show_form       = isset( $state['show_form'] ) ? (bool) $state['show_form'] : true;
		$this->fields_disabled = isset( $state['fields_disabled'] ) ? (string) $state['fields_disabled'] : '';
		$this->error_output    = ! empty( $state['error_output'] ) && is_array( $state['error_output'] ) ? $state['error_output'] : array();

		return true;
	}

	/**
	 * Stores the current submit state for the full render pass.
	 *
	 * @return void
	 */
	private function cache_submit_state() {
		$this->submit_state = array(
			'content'         => $this->content,
			'show_form'       => $this->show_form,
			'fields_disabled' => $this->fields_disabled,
			'error_output'    => is_array( $this->error_output ) ? $this->error_output : array(),
		);

		ic_save_global( $this->submit_state_key, $this->submit_state );
	}

	function security() {
		return IC_Form_Submission_Helper::hidden_fields_html( $this->pre_name, 'simple_form_legacy_submit' );
	}

	function secure() {
		return IC_Form_Submission_Helper::is_secure_submission( $this->pre_name, 'simple_form_legacy_submit' );
	}

	function form_submit_button() {
		$this->content  = apply_filters( 'ic_formbuilder_before_button', $this->content, $this->pre_name );
		$this->content .= $this->security();
		$this->content .= '<div class="' . $this->pre_name . 'button button-container">';
		$this->content .= apply_filters( 'ic_formbuilder_button', '<input value="' . $this->button_text . '" name="' . $this->pre_name . 'submit" ' . $this->fields_disabled . ' class="button ' . $this->button_class . '" type="submit">', $this->pre_name );
		$this->content .= '</div>';
	}

	function current_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: builds the form's own action URL from a product id, cast with absint().
		if ( isset( $_GET['pr_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
			$current_url = add_query_arg( 'pr_id', absint( wp_unslash( $_GET['pr_id'] ) ), get_permalink() );
		} else {
			$current_url = get_permalink();
		}

		return $current_url;
	}

	// phpcs:enable
}

if ( ! class_exists( 'ic_simple_form', false ) ) {
	class_alias( 'IC_Simple_Form_Legacy', 'ic_simple_form' );
}
