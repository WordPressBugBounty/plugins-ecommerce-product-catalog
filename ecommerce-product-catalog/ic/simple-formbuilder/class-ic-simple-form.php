<?php
/**
 * Simple form builder form renderer.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once dirname( __DIR__ ) . '/class-ic-form-submission-helper.php';

/**
 * Simple form builder form renderer.
 */
class IC_Simple_Form {
	/**
	 * Form prefix.
	 *
	 * @var string
	 */
	private $pre_name;

	/**
	 * Markup rendered before buttons.
	 *
	 * @var string
	 */
	private $before_button;

	/**
	 * Submit button label.
	 *
	 * @var string|null
	 */
	private $button_text;

	/**
	 * Form layout class.
	 *
	 * @var string
	 */
	private $form_type;

	/**
	 * Whether to show the form after submit.
	 *
	 * @var bool
	 */
	private $show_form_after;

	/**
	 * Success message content.
	 *
	 * @var string|null
	 */
	private $success_message;

	/**
	 * Email line separator.
	 *
	 * @var string
	 */
	private $line;

	/**
	 * Whether admin email should be sent.
	 *
	 * @var bool
	 */
	private $send_mail;

	/**
	 * Sender email address.
	 *
	 * @var string|null
	 */
	private $message_sender;

	/**
	 * Receiver email address.
	 *
	 * @var string|null
	 */
	private $message_receiver;

	/**
	 * Admin email subject.
	 *
	 * @var string|null
	 */
	private $message_topic;

	/**
	 * Submit button CSS class.
	 *
	 * @var string
	 */
	private $button_class;

	/**
	 * Whether customer email should be sent.
	 *
	 * @var bool
	 */
	private $send_customer_email;

	/**
	 * Customer email subject.
	 *
	 * @var string|null
	 */
	private $customer_message_topic;

	/**
	 * Sender display name.
	 *
	 * @var string|null
	 */
	private $message_sender_name;

	/**
	 * Optional redirect URL.
	 *
	 * @var string|null
	 */
	private $redirect;

	/**
	 * Rendered form content.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * Form definition object.
	 *
	 * @var object|null
	 */
	private $contact;

	/**
	 * Whether the form should be rendered.
	 *
	 * @var bool
	 */
	private $show_form;

	/**
	 * Current field error CSS class.
	 *
	 * @var string
	 */
	private $error_class;

	/**
	 * Current field required attribute.
	 *
	 * @var string
	 */
	private $required;

	/**
	 * Uploaded attachments.
	 *
	 * @var array<int, string>
	 */
	private $attachments;

	/**
	 * Customer email address.
	 *
	 * @var string|null
	 */
	private $customer_email;

	/**
	 * Whether form validation failed.
	 *
	 * @var bool
	 */
	private $error;

	/**
	 * Validation errors keyed by field id.
	 *
	 * @var array<string, string>
	 */
	private $error_output;

	/**
	 * Legacy email template flag.
	 *
	 * @var bool
	 */
	private $email_template;

	/**
	 * Legacy field disable markup.
	 *
	 * @var string
	 */
	private $fields_disabled;

	/**
	 * Legacy rendered form HTML cache.
	 *
	 * @var string
	 */
	private $form_html;

	/**
	 * Legacy small field counter.
	 *
	 * @var int
	 */
	private $small_count;

	/**
	 * Legacy section flag.
	 *
	 * @var bool
	 */
	private $is_section;

	/**
	 * Legacy form type CSS class.
	 *
	 * @var string
	 */
	private $form_type_class;

	/**
	 * Inline style rules.
	 *
	 * @var array<string, string>
	 */
	private $style;

	/**
	 * SMTP configuration.
	 *
	 * @var array
	 */
	private $smtp;

	/**
	 * Reply-to email values.
	 *
	 * @var array|string|null
	 */
	private $reply_to;

	/**
	 * Initialize the form renderer.
	 *
	 * @param string      $form_fields           JSON encoded form definition.
	 * @param string      $pre_name              Form name prefix.
	 * @param string      $before_button         Markup rendered before buttons.
	 * @param string|null $button_text           Submit button label.
	 * @param string      $form_type             Form layout class.
	 * @param bool        $show_form_after       Whether to show the form after submit.
	 * @param string|null $success_message       Success message content.
	 * @param string      $line                  Email line separator.
	 * @param bool        $send_mail             Whether admin email should be sent.
	 * @param string|null $message_sender        Sender email address.
	 * @param string|null $message_receiver      Receiver email address.
	 * @param string|null $message_topic         Admin email subject.
	 * @param string      $button_class          Submit button CSS class.
	 * @param bool        $send_customer_email   Whether customer email should be sent.
	 * @param string|null $customer_message_topic Customer email subject.
	 * @param string|null $message_sender_name   Sender display name.
	 * @param string|null $redirect              Optional redirect URL.
	 * @param array       $style                 Inline style rules.
	 * @param array       $smtp                  SMTP configuration.
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
		$style = array(),
		$smtp = array()
	) {
		$this->pre_name               = $pre_name;
		$this->before_button          = $before_button;
		$this->button_text            = ! empty( $button_text ) ? $button_text : __( 'Submit', 'ecommerce-product-catalog' );
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
		$this->redirect               = ! empty( $redirect ) ? esc_url_raw( $redirect ) : null;
		$this->content                = '';
		$this->contact                = json_decode( $form_fields );
		$this->show_form              = true;
		$this->error_class            = '';
		$this->required               = '';
		$this->attachments            = array();
		$this->form_html              = '';
		$this->small_count            = 0;
		$this->is_section             = false;
		$this->style                  = $style;
		$this->smtp                   = $smtp;
		ic_enqueue_simple_form_css();
		if ( function_exists( 'form_builder_enqueue_styles' ) ) {
			form_builder_enqueue_styles();
		}

		add_filter( 'ic_formbuilder_form_beginning', array( $this, 'configuration_notices' ), 10, 2 );

		$this->process_form();
	}

	/**
	 * Add configuration warnings to the form.
	 *
	 * @param string $form     Form markup.
	 * @param string $pre_name Form prefix.
	 *
	 * @return string
	 */
	public function configuration_notices( $form, $pre_name ) {
		if ( $pre_name !== $this->pre_name ) {
			return $form;
		}
		$notices = '';
		if ( empty( $this->message_sender ) || ! is_email( $this->message_sender ) || empty( $this->message_receiver ) || ! is_email( $this->message_receiver ) ) {
			$notices .= implecode_warning( __( 'Please configure the email sender and received in the email settings to use this form.', 'ecommerce-product-catalog' ), 0 );
		}

		return $notices . $form;
	}

	/**
	 * Render and process the form.
	 *
	 * @return void
	 */
	private function process_form() {
		$html_util = ic_html_util();

		if ( $this->is_submitted() && $this->secure() ) {
			$this->submit_form();
		}

		if ( $this->show_form ) {
			$fields_html = $this->get_hidden_fields_html();
			foreach ( $this->contact->fields as $field ) {
				$fields_html .= $this->render_field( $field );
			}

			$submit_button = '<input value="' . esc_attr( $this->button_text ) . '" name="' . esc_attr( $this->pre_name . 'submit' ) . '" class="' . esc_attr( 'button ' . $this->button_class . ' ic-formbuilder-submit-button' ) . '" type="submit">';
			$buttons       = array(
				apply_filters(
					'ic_formbuilder_button',
					$submit_button,
					$this->pre_name
				),
			);

				$form = $html_util->form(
					$this->pre_name,
					'',
					$fields_html,
					$buttons,
					$this->current_url()
				);
			if ( $this->has_file_field() ) {
				$form = $this->add_multipart_encoding( $form );
			}
			if ( ! empty( $this->style ) && is_array( $this->style ) ) {
				$style_inside = 'form.' . $this->pre_name . ', form.' . $this->pre_name . ' select, form.' . $this->pre_name . ' input, form.' . $this->pre_name . ' textarea {';
				foreach ( $this->style as $key => $value ) {
					$style_inside .= $key . ':' . $value . ';';
				}
				$style_inside .= '}';
				$form         .= '<style>' . $style_inside . '</style>';
			}

			$this->content .= apply_filters(
				'ic_formbuilder_form_beginning',
				$html_util->div( $this->before_button . $form, 'ic-form ' . $this->form_type ),
				$this->pre_name
			);
		}
	}

	/**
	 * Render a single form field.
	 *
	 * @param object $field Field definition.
	 *
	 * @return string
	 */
	private function render_field( $field ) {
		$html_util   = ic_html_util();
		$field_value = $this->get_posted_field_value( $field );
		$field_html  = '';

		switch ( $field->field_type ) {
			case 'text':
			case 'email':
			case 'url':
			case 'number':
				$field_html = apply_filters(
					'ic_formbuilder_input_field',
					$html_util->input(
						$field->field_type,
						$field->cid,
						$field_value,
						$field->cid,
						! empty( $field->required ) ? 'required' : '',
						$field->label
					),
					$field,
					$field_value
				);
				break;
			case 'textarea':
				$field_html = apply_filters(
					'ic_formbuilder_textarea_field',
					$html_util->textarea(
						$field->cid,
						$field_value,
						$field->cid,
						! empty( $field->required ) ? 'required' : '',
						$field->label,
						$this->error_class
					),
					$field,
					$field_value
				);
				break;
			case 'dropdown':
				$options    = $this->get_dropdown_options( $field );
				$field_html = apply_filters(
					'ic_formbuilder_dropdown_field',
					$html_util->select( $field->cid, $options, $field_value, $field->label ),
					$field,
					$field_value
				);
				break;
			case 'checkbox':
			case 'radio':
				$field_html = apply_filters(
					'ic_formbuilder_checkbox_radio_field',
					$html_util->radio_checkbox_options(
						$this->get_checkbox_radio_options( $field ),
						$field_value,
						$field->cid,
						'',
						! empty( $field->required ) ? 'required' : '',
						$field->field_type,
						'',
						$field->label
					),
					$field,
					$field_value
				);
				break;
			case 'file':
				$field_html = apply_filters(
					'ic_formbuilder_file_field',
					$html_util->input(
						'file',
						$field->cid,
						'',
						$field->cid,
						! empty( $field->required ) ? 'required' : '',
						$field->label
					),
					$field,
					''
				);
				break;
		}

		return apply_filters(
			'ic_formbuilder_rendered_field',
			$html_util->div( $field_html, 'form-field' ),
			$field,
			$field_value
		);
	}

	/**
	 * Get normalized dropdown options.
	 *
	 * @param object $field Field definition.
	 *
	 * @return array<string, string>
	 */
	private function get_dropdown_options( $field ) {
		$options = array();
		if ( ! empty( $field->field_options->options ) ) {
			foreach ( $field->field_options->options as $option ) {
				$option_value             = isset( $option->value ) ? $option->value : $option->label;
				$options[ $option_value ] = $option->label;
			}
		}

		return $options;
	}

	/**
	 * Get normalized checkbox/radio options.
	 *
	 * @param object $field Field definition.
	 *
	 * @return array<string, string>
	 */
	private function get_checkbox_radio_options( $field ) {
		$options = array();
		if ( ! empty( $field->field_options->options ) ) {
			foreach ( $field->field_options->options as $option ) {
				$option_value             = isset( $option->value ) ? $option->value : $option->label;
				$options[ $option_value ] = $option->label;
			}
		}

		return $options;
	}

	/**
	 * Validate and submit the form.
	 *
	 * @return void
	 */
	private function submit_form() {
		$html_util = ic_html_util();

		$this->error        = false;
		$this->error_output = array();
		$posted_data        = $this->get_submitted_data();
		$message            = '';

		foreach ( $this->contact->fields as $field ) {
			$field_id = $field->cid;
			if ( 'file' === $field->field_type ) {
				$field_value = '';
				if ( $this->has_uploaded_file( $field_id ) ) {
					$attachment = $this->handle_upload( $field_id );
					if ( empty( $attachment ) ) {
						$this->error = true;
						/* translators: Placeholders are substituted with runtime values. */
						$this->error_output[ $field_id ] = sprintf( __( '%s upload failed.', 'ecommerce-product-catalog' ), $field->label );
						continue;
					}
					$this->attachments[] = $attachment;
					$field_value         = wp_basename( $attachment );
				}
			} elseif ( isset( $posted_data[ $field_id ] ) ) {
				if ( is_array( $posted_data[ $field_id ] ) ) {
					$field_value = array_map( 'sanitize_text_field', $posted_data[ $field_id ] );
				} elseif ( 'textarea' === $field->field_type ) {
					$field_value = sanitize_textarea_field( $posted_data[ $field_id ] );
				} else {
					$field_value = sanitize_text_field( $posted_data[ $field_id ] );
				}
			} else {
				$field_value = '';
			}
			if ( isset( $field->field_options->options ) ) {
				$options = $this->get_dropdown_options( $field );
				if ( is_array( $field_value ) ) {
					$new_field_value = array();
					foreach ( $field_value as $value ) {
						$new_field_value[] = isset( $options[ $value ] ) ? $options[ $value ] : $value;
					}
					$field_value = $new_field_value;
				} elseif ( isset( $options[ $field_value ] ) ) {
					$field_value = sanitize_text_field( $options[ $field_value ] );
				}
			}
			if ( is_array( $field_value ) ) {
				$field_value = implode( ', ', $field_value );
			}
			if ( isset( $field->required ) && $field->required && empty( $field_value ) ) {
				$this->error                     = true;
				$this->error_output[ $field_id ] = $field->label . ' is required.';
				continue;
			}
			if ( 'file' === $field->field_type && empty( $field_value ) ) {
				continue;
			}
			if ( 'textarea' === $field->field_type ) {
				$field_value = nl2br( $field_value );
			}

			$message .= $field->label . ': ' . $field_value . $this->line;
			if ( 'email' === $field->field_type && ! empty( $field_value ) && is_email( $field_value ) ) {
				$this->customer_email = $field_value;
				if ( empty( $this->message_sender_name ) ) {
					$this->message_sender_name = $field_value;
				}
				if ( empty( $this->reply_to ) ) {
					$this->reply_to = $field_value;
				}
			}
		}

		$custom_errors = apply_filters( 'ic_simple_form_custom_validation', array(), $posted_data, $this );

		if ( ! empty( $custom_errors ) ) {
			$this->error        = true;
			$this->error_output = array_merge( $this->error_output, $custom_errors );
		}

		if ( $this->error ) {
			$error_list     = $html_util->ul( $this->error_output, 'form-errors' );
			$this->content .= $html_util->div(
				$html_util->p( __( 'Please fix the following errors:', 'ecommerce-product-catalog' ), 'error-heading' ) . $error_list,
				'form-error-container'
			);

			return;
		}

		do_action( 'ic_simple_form_submit_success', $posted_data, $message, $this->pre_name, $this );

		if ( $this->send_mail ) {
			$this->send_email( $message );
		}

		$success_message = ! empty( $this->success_message ) ? $this->success_message : __( 'Your message has been sent successfully!', 'ecommerce-product-catalog' );
		$this->content  .= $html_util->div( $html_util->p( $success_message, 'success-message' ), 'form-success-container' );

		if ( ! empty( $this->redirect ) ) {
			$redirect = html_entity_decode( (string) $this->redirect, ENT_QUOTES, get_bloginfo( 'charset' ) );
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The redirect is caller-configured (null by default), not request input, and documented external HTTP/HTTPS thank-you URLs are supported.
			wp_redirect( esc_url_raw( $redirect, array( 'http', 'https' ) ) );
			exit;
		}

		$this->show_form = $this->show_form_after;
	}

	/**
	 * Send admin and customer emails.
	 *
	 * @param string $message Sanitized message body.
	 *
	 * @return void
	 */
	private function send_email( $message ) {
		if ( ! is_email( $this->message_sender ) ) {
			return;
		}
		$attachments = apply_filters( 'ic_formbuilder_admin_attachments', $this->attachments, $this->pre_name, $this->customer_email );

		if ( is_email( $this->message_receiver ) ) {
			$admin_message = apply_filters( 'ic_formbuilder_admin_email', $message, $this->pre_name );
			ic_mail( $admin_message, $this->message_sender_name, $this->message_sender, $this->message_receiver, $this->message_topic, true, $attachments, array(), $this->smtp, null, $this->reply_to );
		}

		if ( $this->send_customer_email && ! empty( $this->customer_email ) && is_email( $this->customer_email ) ) {
			$user_message = apply_filters( 'ic_formbuilder_user_email', $message, $this->pre_name );
			ic_mail( $user_message, ic_get_site_name(), $this->message_sender, $this->customer_email, $this->customer_message_topic, true, array(), array(), $this->smtp );
		}
	}

	/**
	 * Verify that the form request is valid.
	 *
	 * @return bool
	 */
	private function secure() {
		return IC_Form_Submission_Helper::is_secure_submission( $this->pre_name, 'simple_form_submit' );
	}

	/**
	 * Get the current form action URL.
	 *
	 * @return string
	 */
	private function current_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Optional query arg is preserved for the current page URL only.
		$pr_id = isset( $_GET['pr_id'] ) ? absint( wp_unslash( $_GET['pr_id'] ) ) : 0;

		if ( $pr_id > 0 ) {
			return add_query_arg( 'pr_id', $pr_id, get_permalink() );
		}

		return get_permalink();
	}

	/**
	 * Determine whether this form was submitted.
	 *
	 * @return bool
	 */
	private function is_submitted() {
		$submit_name = $this->pre_name . 'submit';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Submission detection happens before secure() verifies the nonce.
		return isset( $_POST[ $submit_name ] );
	}

	/**
	 * Get nonce action string.
	 *
	 * @return string
	 */
	private function get_nonce_action() {
		return IC_Form_Submission_Helper::nonce_action( $this->pre_name, 'simple_form_submit' );
	}

	/**
	 * Get nonce field name.
	 *
	 * @return string
	 */
	private function get_nonce_name() {
		return IC_Form_Submission_Helper::nonce_name( $this->pre_name );
	}

	/**
	 * Get hidden form fields.
	 *
	 * @return string
	 */
	private function get_hidden_fields_html() {
		return IC_Form_Submission_Helper::hidden_fields_html( $this->pre_name, 'simple_form_submit' );
	}

	/**
	 * Get sanitized submitted data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_submitted_data() {
		if ( ! $this->secure() ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above.
		return wp_unslash( $_POST );
	}

	/**
	 * Get a submitted field value.
	 *
	 * @param object $field Field definition.
	 *
	 * @return string|array
	 */
	private function get_posted_field_value( $field ) {
		if ( ! $this->secure() ) {
			return '';
		}

		$posted_data = $this->get_submitted_data();
		if ( ! isset( $posted_data[ $field->cid ] ) ) {
			return '';
		}

		if ( is_array( $posted_data[ $field->cid ] ) ) {
			return array_map( 'sanitize_text_field', $posted_data[ $field->cid ] );
		}

		if ( 'textarea' === $field->field_type ) {
			return is_string( $posted_data[ $field->cid ] ) ? sanitize_textarea_field( $posted_data[ $field->cid ] ) : '';
		}

		return is_string( $posted_data[ $field->cid ] ) ? sanitize_text_field( $posted_data[ $field->cid ] ) : '';
	}

	/**
	 * Get uploaded attachment file paths.
	 *
	 * @return array<int, string>
	 */
	public function attachments() {
		return $this->attachments;
	}

	/**
	 * Determine whether the form contains a file input.
	 *
	 * @return bool
	 */
	private function has_file_field() {
		if ( empty( $this->contact->fields ) || ! is_array( $this->contact->fields ) ) {
			return false;
		}
		foreach ( $this->contact->fields as $field ) {
			if ( ! empty( $field->field_type ) && 'file' === $field->field_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add multipart encoding to the generated form markup.
	 *
	 * @param string $form Form HTML.
	 *
	 * @return string
	 */
	private function add_multipart_encoding( $form ) {
		if ( strpos( $form, 'multipart/form-data' ) !== false ) {
			return $form;
		}

		return preg_replace( '/<form\b/', '<form enctype="multipart/form-data"', $form, 1 );
	}

	/**
	 * Check whether a file was submitted for a field.
	 *
	 * @param string $field_id Field identifier.
	 *
	 * @return bool
	 */
	private function has_uploaded_file( $field_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- secure() verifies the form-specific nonce before processing submitted fields or uploads.
		if ( empty( $_FILES[ $field_id ] ) || ! is_array( $_FILES[ $field_id ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The PHP upload array is shape-validated and passed through the shared upload helper with an explicit MIME allowlist; text sanitization would corrupt tmp_name.
		$upload = $_FILES[ $field_id ];

		return ! empty( $upload['name'] ) && isset( $upload['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $upload['error'];
	}

	/**
	 * Move a submitted file into the shared attachment folder.
	 *
	 * @param string $field_name Field identifier.
	 *
	 * @return string
	 */
	private function handle_upload( $field_name ) {
		return IC_Form_Submission_Helper::handle_upload( $field_name );
	}

	/**
	 * Get the shared email-attachment folder path or URL.
	 *
	 * @param string|null $type Folder type.
	 *
	 * @return string
	 */
	private function attachment_folder( $type = null ) {
		return IC_Form_Submission_Helper::attachment_folder( $type );
	}
}

if ( ! class_exists( 'ic_simple_form', false ) ) {
	class_alias( 'IC_Simple_Form', 'ic_simple_form' );
}
