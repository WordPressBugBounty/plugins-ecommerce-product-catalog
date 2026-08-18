<?php
/**
 * Shared form submission helpers.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'IC_Form_Submission_Helper' ) ) {
	/**
	 * Provides shared security and upload helpers for form builders.
	 */
	class IC_Form_Submission_Helper {
	/**
	 * Returns one nonce action string.
	 *
	 * @param string $pre_name      Form prefix.
	 * @param string $action_suffix Action suffix.
	 *
	 * @return string
	 */
	public static function nonce_action( $pre_name, $action_suffix = 'form_submit' ) {
		return (string) $pre_name . (string) $action_suffix;
	}

	/**
	 * Returns one nonce field name.
	 *
	 * @param string $pre_name Form prefix.
	 *
	 * @return string
	 */
	public static function nonce_name( $pre_name ) {
		return (string) $pre_name . 'nonce';
	}

	/**
	 * Returns one honeypot field name.
	 *
	 * @param string $pre_name Form prefix.
	 *
	 * @return string
	 */
	public static function honeypot_name( $pre_name ) {
		return (string) $pre_name . 'checkic';
	}

	/**
	 * Returns the hidden security fields HTML.
	 *
	 * @param string $pre_name      Form prefix.
	 * @param string $action_suffix Action suffix.
	 *
	 * @return string
	 */
	public static function hidden_fields_html( $pre_name, $action_suffix = 'form_submit' ) {
		$honeypot_name = esc_attr( self::honeypot_name( $pre_name ) );

		return wp_nonce_field( self::nonce_action( $pre_name, $action_suffix ), self::nonce_name( $pre_name ), true, false ) .
			'<input type="text" name="' . $honeypot_name . '" id="' . $honeypot_name . '" value="" autocomplete="off" tabindex="-1" style="display:none !important;">';
	}

	/**
	 * Returns whether the current request passes honeypot and nonce verification.
	 *
	 * @param string $pre_name      Form prefix.
	 * @param string $action_suffix Action suffix.
	 *
	 * @return bool
	 */
	public static function is_secure_submission( $pre_name, $action_suffix = 'form_submit' ) {
		$honeypot_name = self::honeypot_name( $pre_name );
		$nonce_name    = self::nonce_name( $pre_name );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce field is being read for verification here.
		$honeypot = isset( $_POST[ $honeypot_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $honeypot_name ] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce field is being read for verification here.
		$nonce = isset( $_POST[ $nonce_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ) : '';

		return '' === $honeypot && ! empty( $nonce ) && wp_verify_nonce( $nonce, self::nonce_action( $pre_name, $action_suffix ) );
	}

	/**
	 * Returns the shared attachment folder path or URL.
	 *
	 * @param string|null $type Folder type.
	 *
	 * @return string
	 */
	public static function attachment_folder( $type = null ) {
		$wp_uploads_dir = wp_upload_dir();
		if ( empty( $type ) ) {
			return trailingslashit( $wp_uploads_dir['baseurl'] ) . 'ic-mail-att';
		}

		$destination = trailingslashit( $wp_uploads_dir['basedir'] ) . 'ic-mail-att';
		if ( ! file_exists( $destination ) ) {
			wp_mkdir_p( $destination );
		}

		return $destination;
	}

	/**
	 * Moves one submitted file into the shared attachment folder.
	 *
	 * @param string $field_name Field identifier.
	 *
	 * @return string
	 */
	public static function handle_upload( $field_name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The owning form or AJAX boundary verifies its action-specific nonce before this downstream request value is processed.
		if ( empty( $_FILES[ $field_name ] ) || ! is_array( $_FILES[ $field_name ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The PHP upload array is shape-validated and passed through the shared upload helper with an explicit MIME allowlist; text sanitization would corrupt tmp_name.
		$upload = $_FILES[ $field_name ];
		if ( empty( $upload['name'] ) || empty( $upload['tmp_name'] ) || ! isset( $upload['error'] ) || UPLOAD_ERR_OK !== (int) $upload['error'] ) {
			return '';
		}

		$file_name = is_string( $upload['name'] ) ? sanitize_file_name( wp_unslash( $upload['name'] ) ) : '';
		if ( empty( $file_name ) ) {
			return '';
		}

		$file_type = wp_check_filetype( basename( $file_name ) );
		if ( empty( $file_type['ext'] ) ) {
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload['name'] = $file_name;
		$handled = wp_handle_upload(
			$upload,
			array(
				'test_form' => false,
				'mimes'    => array( $file_type['ext'] => $file_type['type'] ),
			)
		);

		return ( ! empty( $handled['file'] ) && empty( $handled['error'] ) ) ? $handled['file'] : '';
	}
	}
}
