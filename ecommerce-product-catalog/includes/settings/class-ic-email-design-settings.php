<?php
/**
 * Email design settings, preview and explicitly requested test delivery.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Neutral Catalog settings controller. */
final class IC_Email_Design_Settings {
	/** Install scoped admin hooks. */
	public static function boot() {
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy' ) );
		add_action( 'wp_ajax_ic_email_design_template', array( __CLASS__, 'template_action' ) );
		add_action( 'admin_post_ic_email_design_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_ic_email_design_test', array( __CLASS__, 'test_email' ) );
		add_action( 'wp_ajax_ic_email_design_test', array( __CLASS__, 'ajax_test_email' ) );
		add_action( 'wp_ajax_ic_email_design_preview', array( __CLASS__, 'preview' ) );
		add_action( 'wp_ajax_ic_email_design_dismiss', array( __CLASS__, 'dismiss' ) );
		add_action( 'wp_ajax_ic_email_design_content', array( __CLASS__, 'content_action' ) );
		add_action( 'admin_post_ic_email_design_content', array( __CLASS__, 'content_post' ) );
		add_filter( 'ic_email_template_design_preview', array( __CLASS__, 'template_preview' ), 10, 3 );
		add_action( 'ic_email_template_editor', array( __CLASS__, 'template_editor' ) );
		add_action( 'ic_catalog_admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	/** Register the tab with the shared settings screen. */
	public static function page() {
		static $page = null;
		if ( null !== $page ) {
			return $page;
		}
		$page = new IC_Settings_Page(
			array(
				'title'                            => __( 'Email Design', 'ecommerce-product-catalog' ),
				'screen'           => 'product-settings.php',
				'screen_tab'                       => 'design-settings',
				'screen_tab_label'                 => __( 'Catalog Design', 'ecommerce-product-catalog' ),
				'screen_tab_menu_item_id'          => 'design-settings',
				'screen_tab_query_args'            => array( 'submenu' => 'archive-design' ),
				'screen_tab_content_wrapper_class' => 'design-product-settings',
				'submenu'                          => 'email-design',
				'submenu_item_id'                  => 'email-design',
				'screen_submenu_label'             => __( 'Email Design', 'ecommerce-product-catalog' ),
				'container_class'                  => 'setting-content submenu ic-email-design-content',
				'content_callback' => array( __CLASS__, 'content' ),
				'show_form'        => false,
				'show_submit'      => false,
				'show_title'       => false,
			)
		);
		return $page;
	}

	/**
	 * Premium-only design keys, locked unless a plugin provides the feature.
	 *
	 * @return array
	 */
	public static function locks() {
		$feature = IC_Email_Design::PREMIUM_FEATURE;
		return array(
			'header'       => array(
				'feature' => $feature,
				'value'   => 'text',
			),
			'header_text'  => $feature,
			'footer_text'  => $feature,
			'footer_label' => $feature,
			'footer_url'   => $feature,
		);
	}

	/** Tab URL. */
	public static function url() {
		return admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=design-settings&submenu=email-design' );
	}

	/** Redirect only the former Catalog settings route. */
	public static function redirect_legacy() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only legacy route redirect.
		$args = $_GET;
		if ( 'product-settings.php' === ( $args['page'] ?? null ) && 'al_product' === ( $args['post_type'] ?? null ) && 'email-design' === ( $args['tab'] ?? null ) ) {
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	/** Restrict notices to the Catalog post type and its settings screens.
	 *
	 * @param object|null $screen Current screen.
	 */
	public static function catalog_screen( $screen = null ) {
		$screen = $screen ? $screen : get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		$types = function_exists( 'product_post_type_array' ) ? product_post_type_array() : array( 'al_product' );
		return in_array( $screen->post_type, (array) $types, true ) || false !== strpos( $screen->id, 'al_product_page_product-settings' );
	}

	/** One-time notice eligibility, including the irreversible Modern selection latch.
	 *
	 * @param object|null $screen Current screen.
	 */
	public static function show_notice( $screen = null ) {
		$o = IC_Email_Design::options();
		return current_user_can( 'manage_product_settings' ) && self::catalog_screen( $screen ) && 'existing' === $o['cohort'] && 'classic' === $o['layout'] && ! $o['modern_activated'] && ! get_user_meta( get_current_user_id(), 'ic_email_design_notice_dismissed', true );
	}

	/** Any attached legacy custom styling hook merits the exact explanatory sentence. */
	public static function custom_styling() {
		foreach ( $GLOBALS['wp_filter'] as $name => $hook ) {
			if ( 0 === strpos( $name, 'ic_catalog_notification_' ) && has_filter( $name ) ) {
				return true;
			}
		}
		return false;
	}

	/** Render the Catalog-only permanent-dismiss notice. */
	public static function notice() {
		if ( ! self::show_notice() ) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible ic-email-design-notice"><p><strong>' . esc_html__( 'A new email design is available.', 'ecommerce-product-catalog' ) . '</strong> ' . esc_html__( 'Your emails still use the classic layout.', 'ecommerce-product-catalog' ) . ' ';
		if ( self::custom_styling() ) {
			echo ' ' . esc_html__( 'Your emails have custom styling; it stays on the classic layout.', 'ecommerce-product-catalog' );
		}
		echo ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Preview the new design', 'ecommerce-product-catalog' ) . '</a></p></div>';
	}

	/** Whether the current request is the Email Design tab. */
	public static function design_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$args = $_GET;
		return self::catalog_screen() && 'design-settings' === ( $args['tab'] ?? null ) && 'email-design' === ( $args['submenu'] ?? null );
	}

	/**
	 * Apply the final layout before the settings screen paints.
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return self::design_screen() ? $classes . ' ic-email-design-screen' : $classes;
	}

	/** Assets only for the tab or eligible notice. */
	public static function assets() {
		$tab = self::design_screen();
		if ( ! $tab && ! self::show_notice() ) {
			return;
		}
		if ( $tab ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( 'ic-email-design', plugins_url( 'css/email-design.css', AL_BASE_PATH . '/ecommerce-product-catalog.php' ), array(), IC_EPC_VERSION );
		}
		wp_enqueue_script( 'ic-email-design', plugins_url( 'js/email-design.js', AL_BASE_PATH . '/ecommerce-product-catalog.php' ), $tab ? array( 'jquery', 'wp-color-picker', 'media-views' ) : array( 'jquery' ), IC_EPC_VERSION, true );
		wp_localize_script(
			'ic-email-design',
			'icEmailDesign',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'ic_email_design' ),
				'contrastWarning' => __( 'Black text is used on this accent color to keep it readable.', 'ecommerce-product-catalog' ),
				'siteName'        => get_bloginfo( 'name' ),
				'homeUrl'         => home_url( '/' ),
				'siteUrl'         => site_url(),
				'confirmText'     => __( 'Use the Modern default text', 'ecommerce-product-catalog' ),
				'cancelText'      => __( 'Cancel', 'ecommerce-product-catalog' ),
				'undoText'        => __( 'Undo', 'ecommerce-product-catalog' ),
				'sending'         => __( 'Sending…', 'ecommerce-product-catalog' ),
				'mailFailure'     => __( 'The test email could not be sent.', 'ecommerce-product-catalog' ),
				'noLogo'          => __( 'No logo selected', 'ecommerce-product-catalog' ),
				'contents'        => $tab ? self::contents() : array(),
				'editors'         => $tab ? self::editors() : array(),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag that opens the editor.
				'edit'            => isset( $_GET['edit'] ) && '1' === $_GET['edit'],
				/* translators: %s: Email name. */
				'editTitle'       => __( 'Edit email: %s', 'ecommerce-product-catalog' ),
				'textSaved'       => __( 'Email text saved.', 'ecommerce-product-catalog' ),
				'saveFailed'      => __( 'The email text could not be saved.', 'ecommerce-product-catalog' ),
				/* translators: %s: Registered Modern title of the email. */
				'headingHelp'     => __( 'The large title at the top of the email (Modern layout). Leave empty to use "%s".', 'ecommerce-product-catalog' ),
			)
		);
	}

	/**
	 * Editor data of every editable email the current user may save.
	 *
	 * @return array
	 */
	public static function contents() {
		$contents = array();
		foreach ( IC_Email_Design::previews() as $id => $item ) {
			$content = self::editable( $item );
			if ( $content ) {
				$contents[ $id ] = self::editor_data( $item, $content );
			}
		}
		return $contents;
	}

	/**
	 * Own-editor URLs of emails edited elsewhere.
	 *
	 * @return array
	 */
	public static function editors() {
		$editors = array();
		foreach ( IC_Email_Design::previews() as $id => $item ) {
			$url = self::editor_url( $item );
			if ( $url ) {
				$editors[ $id ] = $url;
			}
		}
		return $editors;
	}

	/** Check POST, capability and action nonce before reading any settings.
	 *
	 * @param array $request Request data.
	 */
	public static function authorize( $request ) {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		return 'POST' === $request_method && current_user_can( 'manage_product_settings' ) && is_array( $request ) && is_string( $request['_wpnonce'] ?? null ) && wp_verify_nonce( $request['_wpnonce'], 'ic_email_design' );
	}

	/** Pure request dispatcher, shared by handlers and transport-stubbed tests.
	 *
	 * @param string $action Action.
	 * @param array  $request Request data.
	 */
	public static function process( $action, $request ) {
		if ( ! self::authorize( $request ) ) {
			return new WP_Error( 'forbidden', __( 'You are not allowed to change email design.', 'ecommerce-product-catalog' ) );
		}
		if ( 'dismiss' === $action ) {
			update_user_meta( get_current_user_id(), 'ic_email_design_notice_dismissed', 1 );
			return true;
		}
		if ( in_array( $action, array( 'template_prepare', 'template_use', 'template_undo' ), true ) ) {
			return self::mutate_template( $action, $request );
		}
		if ( 'content_save' === $action ) {
			return self::save_content( $request );
		}
		// Locked values are never saved, previewed or sent; the stored value is kept.
		$design  = IC_Settings_Lock::preserve( is_array( $request['design'] ?? null ) ? $request['design'] : array(), IC_Email_Design::options(), self::locks() );
		$options = IC_Email_Design::sanitize( $design );
		if ( 'save' === $action ) {
			update_option( IC_Email_Design::OPTION, $options, false );
			return true;
		}
		$registry = IC_Email_Design::previews();
		$key      = is_string( $request['sample'] ?? null ) ? $request['sample'] : '';
		if ( ! isset( $registry[ $key ] ) || ! in_array( $action, array( 'preview', 'test' ), true ) ) {
			return new WP_Error( 'invalid_sample', __( 'Choose an available email preview.', 'ecommerce-product-catalog' ) );
		}
		if ( isset( $registry[ $key ]['capability'] ) && ! current_user_can( $registry[ $key ]['capability'] ) ) { return new WP_Error( 'forbidden' ); }
		if ( 'preview' === $action && is_array( $request['content'] ?? null ) ) {
			return self::draft_preview( $key, $registry[ $key ], $request['content'], $options, ! empty( $request['marks'] ) );
		}
		$html = IC_Email_Design::preview_email( $registry[ $key ], $options );
		if ( is_wp_error( $html ) || 'preview' === $action ) {
			return $html; }
		$sample = IC_Email_Design::with_options( $options, $registry[ $key ]['callback'] );
		// Atomic option reservation is per site/user; another request cannot race the claim.
		$rate_key = 'ic_email_design_test_' . get_current_user_id();
		$claim    = (string) time() . ':' . wp_generate_uuid4();
		$previous = get_option( $rate_key, '' );
		if ( $previous && (int) $previous + 60 > time() ) {
			return new WP_Error( 'rate_limit', __( 'Please wait a minute before sending another test email.', 'ecommerce-product-catalog' ) );
		}
		if ( $previous ) {
			// Compare-and-delete the expired claim before competing for the unique option key.
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $rate_key, $previous ) );
			wp_cache_delete( $rate_key, 'options' );
		}
		if ( ! add_option( $rate_key, $claim, '', false ) ) {
			return new WP_Error( 'rate_limit', __( 'Please wait a minute before sending another test email.', 'ecommerce-product-catalog' ) );
		}
		/* translators: %s: Original email subject. */
		$subject = sprintf( __( '[Test] %s', 'ecommerce-product-catalog' ), $sample['subject'] );
		return wp_mail( get_option( 'admin_email' ), $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/** Hash the exact feature-owned state, including absent versus empty storage.
	 *
	 * @param array $state Exact feature storage state.
	 */
	public static function state_hash( $state ) {
		return hash( 'sha256', wp_json_encode( $state ) );
	}

	/** Authenticated, feature-authorized adoption and single-use exact Undo.
	 *
	 * @param string $action Template operation.
	 * @param array  $request Authorized request fields.
	 */
	private static function mutate_template( $action, $request ) {
		$registry = IC_Email_Design::previews();
		$key      = is_string( $request['sample'] ?? null ) ? $request['sample'] : '';
		$item     = $registry[ $key ] ?? array();
		if ( ! is_callable( $item['read'] ?? null ) || ! is_callable( $item['write'] ?? null ) || ! current_user_can( $item['capability'] ?? 'manage_product_settings' ) ) {
			return new WP_Error( 'forbidden', __( 'You are not allowed to change this email.', 'ecommerce-product-catalog' ) );
		}
		$state = call_user_func( $item['read'] );
		$hash  = self::state_hash( $state );
		if ( 'template_prepare' === $action ) {
			return array(
				'before' => $state['body'] ?? $item['classic'],
				'after'  => $item['modern'],
				'hash'   => $hash,
			);
		}
		if ( 'template_use' === $action ) {
			if ( ! is_string( $request['hash'] ?? null ) || ! hash_equals( $hash, $request['hash'] ) ) {
				return new WP_Error( 'stale', __( 'This email changed. Open the confirmation again.', 'ecommerce-product-catalog' ) );
			}
			$after = array(
				'exists' => true,
				'body'   => $item['modern'],
			);
			call_user_func( $item['write'], $after );
			$operation = wp_generate_uuid4();
			set_transient(
				'ic_email_undo_' . $operation,
				array(
					'user'       => get_current_user_id(),
					'site'       => get_current_blog_id(),
					'sample'     => $key,
					'before'     => $state,
					'after_hash' => self::state_hash( call_user_func( $item['read'] ) ),
				),
				15 * MINUTE_IN_SECONDS
			);
			return array( 'operation' => $operation );
		}
		$operation = is_string( $request['operation'] ?? null ) ? $request['operation'] : '';
		if ( ! preg_match( '/^[a-f0-9-]{36}$/', $operation ) ) {
			return new WP_Error( 'invalid_undo' ); }
		$receipt = get_transient( 'ic_email_undo_' . $operation );
		if ( ! is_array( $receipt ) || get_current_user_id() !== $receipt['user'] || get_current_blog_id() !== $receipt['site'] || $key !== $receipt['sample'] || ! hash_equals( $receipt['after_hash'], $hash ) ) {
			return new WP_Error( 'stale', __( 'Undo is unavailable because this email changed or the request expired.', 'ecommerce-product-catalog' ) );
		}
		delete_transient( 'ic_email_undo_' . $operation );
		call_user_func( $item['write'], $receipt['before'] );
		return array( 'restored' => true );
	}

	/**
	 * Editable content of a registered email the current user may save, or null.
	 *
	 * @param array|null $item Registry item.
	 * @return array|null
	 */
	public static function editable( $item ) {
		$content = is_array( $item ) ? IC_Email_Design::content( $item ) : null;
		if ( ! $content || ! current_user_can( 'manage_product_settings' ) || ! current_user_can( $content['capability'] ) || ( isset( $item['capability'] ) && ! current_user_can( $item['capability'] ) ) ) {
			return null;
		}
		return $content;
	}

	/**
	 * Same line endings for comparing a submitted textarea with the shown text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function same_lines( $text ) {
		return str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	}

	/**
	 * Layout the editor showed its values for.
	 *
	 * @param array $request Request data.
	 * @return string|null 'classic', 'modern' or null for the stored layout.
	 */
	private static function layout_param( $request ) {
		$layout = is_array( $request ) && is_string( $request['layout'] ?? null ) ? $request['layout'] : '';
		return in_array( $layout, array( 'classic', 'modern' ), true ) ? $layout : null;
	}

	/**
	 * Sanitized submitted editor fields, in the declared storage only.
	 *
	 * @param array $content Normalized descriptor.
	 * @param mixed $input Submitted content fields.
	 * @return array|WP_Error
	 */
	private static function submitted_content( $content, $input ) {
		$input  = is_array( $input ) ? $input : array();
		$fields = array();
		foreach ( array( 'subject', 'heading', 'text' ) as $field ) {
			if ( empty( $content[ $field ] ) || ! array_key_exists( $field, $input ) ) {
				continue;
			}
			if ( ! is_string( $input[ $field ] ) ) {
				return new WP_Error( 'invalid_content', __( 'Subject and heading must be a single line.', 'ecommerce-product-catalog' ) );
			}
			if ( 'text' !== $field && preg_match( '/[\r\n]/', $input[ $field ] ) ) {
				return new WP_Error( 'invalid_content', __( 'Subject and heading must be a single line.', 'ecommerce-product-catalog' ) );
			}
			$fields[ $field ] = $input[ $field ];
		}
		return $fields;
	}

	/**
	 * Sanitize one editor field like the feature stores it.
	 *
	 * @param array  $content Normalized descriptor.
	 * @param string $field Field.
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_content_field( $content, $field, $value ) {
		if ( 'text' === $field ) {
			$sanitized = call_user_func( $content['text_sanitize'], $value );
			return is_string( $sanitized ) ? $sanitized : '';
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Save changed editor fields into the feature's own settings.
	 *
	 * @param array $request Authorized request.
	 * @return array|WP_Error
	 */
	private static function save_content( $request ) {
		$registry = IC_Email_Design::previews();
		$key      = is_string( $request['sample'] ?? null ) ? $request['sample'] : '';
		$item     = $registry[ $key ] ?? null;
		$content  = self::editable( $item );
		if ( ! $content ) {
			return new WP_Error( 'forbidden', __( 'You are not allowed to change this email.', 'ecommerce-product-catalog' ) );
		}
		if ( ! is_string( $request['hash'] ?? null ) || ! hash_equals( IC_Email_Design::content_hash( $content ), $request['hash'] ) ) {
			return new WP_Error( 'stale', __( 'This email changed. Open the editor again.', 'ecommerce-product-catalog' ) );
		}
		$fields = self::submitted_content( $content, $request['content'] ?? null );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$layout  = self::layout_param( $request );
		$shown   = IC_Email_Design::effective_content( $content, $layout );
		$changes = array();
		foreach ( $fields as $field => $value ) {
			if ( self::same_lines( $value ) !== self::same_lines( $shown[ $field ] ) ) {
				$changes[ $field ] = self::sanitize_content_field( $content, $field, $value );
			}
		}
		if ( $changes ) {
			$written = IC_Email_Design::write_content( $content, $changes );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
		}
		$registry = IC_Email_Design::previews();
		$item     = $registry[ $key ] ?? $item;
		$values   = IC_Email_Design::effective_content( $content, $layout );
		return array(
			'values'     => $values,
			'hash'       => IC_Email_Design::content_hash( $content ),
			'customized' => self::customized_text( $item, $content ),
			'subject'    => $values['subject'],
			'editor'     => self::editor_data( $item, $content ),
		);
	}

	/**
	 * Render a preview with the unsaved editor fields; nothing is stored.
	 *
	 * @param string $key Registered ID.
	 * @param array  $item Registry item.
	 * @param array  $input Submitted content fields.
	 * @param array  $options Requested design.
	 * @param bool   $marks Whether the text carries the editor's run markers.
	 * @return string|WP_Error
	 */
	private static function draft_preview( $key, $item, $input, $options, $marks = false ) {
		$content = self::editable( $item );
		if ( ! $content ) {
			return new WP_Error( 'forbidden', __( 'You are not allowed to change this email.', 'ecommerce-product-catalog' ) );
		}
		$fields = self::submitted_content( $content, $input );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		if ( $marks && isset( $fields['text'] ) ) {
			// The other layout's shipped default renders as this layout's default: draw it unmarked, as it would be sent.
			$plain    = IC_Email_Design::strip_marks( $fields['text'] );
			$own      = $item[ $options['layout'] ] ?? null;
			$defaults = is_array( $item['defaults'] ?? null ) ? $item['defaults'] : array_values( array_filter( array( $item['classic'] ?? null, $item['modern'] ?? null ), 'is_string' ) );
			if ( is_string( $own ) && ! IC_Email_Design::customized( $plain, $defaults ) && IC_Email_Design::normalize_default( $plain ) !== IC_Email_Design::normalize_default( $own ) ) {
				$fields['text'] = $plain;
			}
		}
		$draft = array();
		foreach ( $fields as $field => $value ) {
			$draft[ $field ] = self::sanitize_content_field( $content, $field, $value );
		}
		return IC_Email_Design::with_draft(
			$content,
			$draft,
			static function () use ( $key, $options ) {
				// Feature registrations capture their settings, so the registry is rebuilt inside the draft.
				$fresh = IC_Email_Design::previews();
				if ( ! isset( $fresh[ $key ] ) ) {
					return new WP_Error( 'invalid_sample', __( 'Choose an available email preview.', 'ecommerce-product-catalog' ) );
				}
				return IC_Email_Design::preview_email( $fresh[ $key ], $options );
			}
		);
	}

	/**
	 * Whether the email's registered text sanitizer removes HTML tags (e.g. sanitize_textarea_field).
	 *
	 * Decided by what the feature's own sanitizer does, not by a list of emails.
	 *
	 * @param array $content Normalized descriptor.
	 * @return bool
	 */
	public static function plain_text( $content ) {
		$sanitized = call_user_func( $content['text_sanitize'], '<b>ic</b>' );
		return is_string( $sanitized ) && false === strpos( $sanitized, '<' );
	}

	/**
	 * Whether the stored text is the admin's own text (same comparison as the Email texts box).
	 *
	 * @param array $item Registry item.
	 * @param array $content Normalized descriptor.
	 * @return bool
	 */
	public static function customized_text( $item, $content ) {
		$defaults = is_array( $item['defaults'] ?? null ) ? $item['defaults'] : array_values( array_filter( array( $item['classic'] ?? null, $item['modern'] ?? null ), 'is_string' ) );
		if ( ! $defaults ) {
			return false;
		}
		if ( is_callable( $item['read'] ?? null ) ) {
			$raw = call_user_func( $item['read'] );
			$raw = is_array( $raw ) && is_string( $raw['body'] ?? null ) ? $raw['body'] : null;
		} else {
			$raw = IC_Email_Design::stored( $content['text'] );
			$raw = is_string( $raw ) ? $raw : null;
		}
		return IC_Email_Design::customized( $raw, $defaults );
	}

	/**
	 * Editor data for one editable email.
	 *
	 * @param array $item Registry item.
	 * @param array $content Normalized descriptor.
	 * @return array
	 */
	public static function editor_data( $item, $content ) {
		$values = IC_Email_Design::effective_content( $content );
		$tokens = array();
		foreach ( $content['tokens'] as $token => $description ) {
			$tokens[] = array( $token, $description );
		}
		return array(
			'label'       => (string) $item['label'],
			'subject'     => $values['subject'],
			'heading'     => $values['heading'],
			'text'        => $values['text'],
			'tokens'      => $tokens,
			'modern'      => is_string( $item['modern'] ?? null ) ? $item['modern'] : '',
			'modernTitle' => is_string( $item['modern_title'] ?? null ) ? $item['modern_title'] : '',
			'hasHeading'  => (bool) $content['heading'],
			'hash'        => IC_Email_Design::content_hash( $content ),
			'customized'  => self::customized_text( $item, $content ),
			'plainText'   => self::plain_text( $content ),
			'layouts'     => array(
				'classic' => IC_Email_Design::effective_content( $content, 'classic' ),
				'modern'  => IC_Email_Design::effective_content( $content, 'modern' ),
			),
		);
	}

	/**
	 * Heading field description.
	 *
	 * @param string $title Registered Modern title.
	 * @return string
	 */
	public static function heading_description( $title ) {
		/* translators: %s: Registered Modern title of the email. */
		return sprintf( __( 'The large title at the top of the email (Modern layout). Leave empty to use "%s".', 'ecommerce-product-catalog' ), $title );
	}

	/**
	 * Popup / no-JS inline editor for the chosen email.
	 *
	 * @param string $id Registered ID.
	 * @param array  $item Registry item.
	 * @param array  $o Stored design.
	 * @param bool   $shown Whether the chosen email is editable (else a hidden shell for JS).
	 * @return string
	 */
	public static function content_form( $id, $item, $o, $shown = true ) {
		$content = self::editable( $item );
		if ( ! $content ) {
			return '';
		}
		$values = IC_Email_Design::effective_content( $content, $o['layout'] );
		$plain  = self::plain_text( $content );
		$title  = is_string( $item['modern_title'] ?? null ) ? $item['modern_title'] : '';
		$html   = '<div id="ic-email-content" class="ic-email-content" data-sample="' . esc_attr( $id ) . '"' . ( $shown ? '' : ' hidden' ) . '><form id="ic-email-content-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html  .= '<input type="hidden" name="action" value="ic_email_design_content"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'ic_email_design' ) ) . '">';
		$html  .= '<input type="hidden" name="sample" value="' . esc_attr( $id ) . '"><input type="hidden" name="hash" value="' . esc_attr( IC_Email_Design::content_hash( $content ) ) . '"><input type="hidden" name="layout" value="' . esc_attr( $o['layout'] ) . '">';
		/* translators: %s: Email name. */
		$html .= '<div class="ic-email-content-head"><h2 id="ic-email-content-title">' . sprintf( esc_html__( 'Edit email: %s', 'ecommerce-product-catalog' ), '<span class="ic-email-content-label">' . esc_html( $item['label'] ) . '</span>' ) . '</h2><button type="button" class="ic-email-content-close" aria-label="' . esc_attr__( 'Close', 'ecommerce-product-catalog' ) . '" hidden>&times;</button></div>';
		$html .= '<label class="ic-email-content-switch" hidden><input type="checkbox" role="switch" id="ic-email-content-preview-switch"> ' . esc_html__( 'Show preview', 'ecommerce-product-catalog' ) . '</label>';
		$html .= '<div class="ic-email-content-field"><label for="ic-email-content-subject">' . esc_html__( 'Subject', 'ecommerce-product-catalog' ) . '</label><input type="text" id="ic-email-content-subject" name="content[subject]" value="' . esc_attr( $values['subject'] ) . '"></div>';
		$html .= '<div class="ic-email-content-field ic-email-content-heading"' . ( $content['heading'] && 'modern' === $o['layout'] ? '' : ' hidden' ) . '><label for="ic-email-content-heading">' . esc_html__( 'Heading', 'ecommerce-product-catalog' ) . '</label><input type="text" id="ic-email-content-heading" name="content[heading]" value="' . esc_attr( $values['heading'] ) . '" placeholder="' . esc_attr( $title ) . '"' . ( $content['heading'] ? '' : ' disabled' ) . '><p class="description" id="ic-email-content-heading-help">' . esc_html( self::heading_description( $title ) ) . '</p></div>';
		$html .= '<div class="ic-email-content-field"><label for="ic-email-content-text">' . esc_html__( 'Text', 'ecommerce-product-catalog' ) . '</label><textarea id="ic-email-content-text" name="content[text]" rows="12"' . ( $plain ? ' aria-describedby="ic-email-content-text-help"' : '' ) . '>' . esc_textarea( $values['text'] ) . '</textarea><p class="description ic-email-content-plain" id="ic-email-content-text-help"' . ( $plain ? '' : ' hidden' ) . '>' . esc_html__( 'Plain text: HTML tags are removed.', 'ecommerce-product-catalog' ) . '</p></div>';
		$html .= '<div class="ic-email-content-tokens"' . ( $content['tokens'] ? '' : ' hidden' ) . '><p>' . esc_html__( 'Click a token to insert it at the cursor:', 'ecommerce-product-catalog' ) . '</p><div class="ic-email-content-token-list">';
		foreach ( $content['tokens'] as $token => $description ) {
			$html .= '<button type="button" class="button button-small ic-email-token" data-token="' . esc_attr( $token ) . '" title="' . esc_attr( $description ) . '">' . esc_html( $token ) . '</button> ';
		}
		$html .= '</div></div><p class="description">' . esc_html__( 'Changes show in the preview as you type. They reach your customers after you click Save text.', 'ecommerce-product-catalog' ) . '</p>';
		$html .= '<div class="ic-email-content-actions"><button type="button" class="button-link ic-email-content-default" hidden>' . esc_html__( 'Use the Modern default text', 'ecommerce-product-catalog' ) . '</button><span class="ic-email-content-buttons"><a class="button ic-email-content-cancel" href="' . esc_url( self::url() . '&email=' . rawurlencode( $id ) ) . '">' . esc_html__( 'Cancel', 'ecommerce-product-catalog' ) . '</a> <button type="submit" class="button button-primary ic-email-content-save">' . esc_html__( 'Save text', 'ecommerce-product-catalog' ) . '</button></span></div>';
		$html .= '<p class="ic-email-content-status" role="status" aria-live="polite"></p></form></div>';
		return $html;
	}

	/** AJAX editor save. */
	public static function content_action() {
		$result = self::request( 'content_save' );
		if ( is_wp_error( $result ) ) {
			$statuses = array(
				'forbidden' => 403,
				'stale'     => 409,
			);
			wp_send_json_error( array( 'message' => $result->get_error_message() ), $statuses[ $result->get_error_code() ] ?? 400 );
		}
		wp_send_json_success( $result );
	}

	/** No-JS editor save: post/redirect/get back to the chosen email. */
	public static function content_post() {
		$result = self::request( 'content_save' );
		if ( is_wp_error( $result ) && 'forbidden' === $result->get_error_code() ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- process() verified the nonce; the ID is only echoed back as a sanitized key.
		$key  = isset( $_POST['sample'] ) && is_string( $_POST['sample'] ) ? sanitize_key( wp_unslash( $_POST['sample'] ) ) : '';
		$code = is_wp_error( $result ) ? $result->get_error_code() : 'text_saved';
		wp_safe_redirect( add_query_arg( 'email_design_result', $code, self::url() . ( $key ? '&email=' . rawurlencode( $key ) : '' ) ) );
		exit;
	}

	/**
	 * One feature-settings row that replaces an email's subject and template fields.
	 *
	 * @param string $id Registered email ID.
	 * @return string Escaped table row, or '' when the email is not editable here.
	 */
	public static function settings_row( $id ) {
		$registry = is_string( $id ) ? IC_Email_Design::previews() : array();
		$item     = $registry[ $id ] ?? null;
		$content  = self::editable( $item );
		if ( ! $content ) {
			return '';
		}
		$values = IC_Email_Design::effective_content( $content );
		$own    = self::customized_text( $item, $content );
		$name   = '' !== $content['settings_label'] ? $content['settings_label'] : $item['label'];
		$badge  = '<span class="ic-email-text-badge ' . ( $own ? 'ic-email-text-own' : 'ic-email-text-default' ) . '">' . ( $own ? esc_html__( 'Your own text', 'ecommerce-product-catalog' ) : esc_html__( 'Default text', 'ecommerce-product-catalog' ) ) . '</span>';
		$text   = '<span class="ic-email-text-subject">' . esc_html( $values['subject'] ) . '</span> ' . $badge . ( '' !== $content['description'] ? '<p class="description">' . esc_html( $content['description'] ) . '</p>' : '' );
		$button = '<a class="button" href="' . esc_url( self::url() . '&email=' . rawurlencode( $id ) . '&edit=1' ) . '">' . esc_html__( 'Edit email', 'ecommerce-product-catalog' ) . '</a>';
		// Phones get the same content in one full-width cell, so it wraps inside the settings column.
		return self::row_style() . '<tr class="ic-email-text-row" data-email="' . esc_attr( $id ) . '"><td><b>' . esc_html( $name ) . '</b></td><td><div class="ic-email-text-summary"><div>' . $text . '</div>' . $button . '</div></td></tr><tr class="ic-email-text-row-phone" data-email="' . esc_attr( $id ) . '"><td colspan="2"><div class="ic-email-text-phone"><b>' . esc_html( $name ) . '</b><div>' . $text . '</div>' . $button . '</div></td></tr>';
	}

	/**
	 * Styles of the moved email rows; printed once per request.
	 *
	 * @return string
	 */
	private static function row_style() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;
		return '<style>' . self::row_css() . '</style>';
	}

	/**
	 * CSS of the moved email rows.
	 *
	 * @return string
	 */
	private static function row_css() {
		return '.ic-email-text-row td{vertical-align:top;padding-top:10px;padding-bottom:10px}.ic-email-text-summary{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;max-width:560px}.ic-email-text-summary .description,.ic-email-text-phone .description{margin:4px 0 0}.ic-email-text-badge{display:inline-block;margin-left:6px;padding:1px 8px;border-radius:10px;font-size:12px;line-height:16px;border:1px solid #8c8f94;background:#fff;color:#50575e}.ic-email-text-badge.ic-email-text-own{border-color:#c08c00;background:#fcf0c3;color:#6e5a10}.ic-email-text-summary .button{flex-shrink:0}.ic-email-text-summary>div{min-width:0;overflow-wrap:anywhere}.ic-email-text-note p{white-space:normal;overflow-wrap:anywhere}tr.ic-email-text-row-phone{display:none}@media(max-width:782px){tr.ic-email-text-row{display:none}tr.ic-email-text-row-phone{display:table-row}tr.ic-email-text-row-phone>td{padding-top:10px;padding-bottom:10px}.ic-email-text-phone,.ic-email-text-note p{box-sizing:border-box;max-width:calc(100vw - 56px);white-space:normal;overflow-wrap:anywhere}.ic-email-text-phone{display:flex;flex-direction:column;align-items:flex-start;gap:6px}.ic-email-text-phone>div{max-width:100%}.ic-email-text-phone .button{max-width:100%;white-space:normal;height:auto}}';
	}

	/**
	 * Note under the moved email rows; printed once per request.
	 *
	 * @return string
	 */
	public static function settings_note() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;
		return self::row_style() . '<tr class="ic-email-text-note"><td colspan="2"><p class="description">' . esc_html__( 'Edit email opens Catalog Design → Email Design with this email in the preview and its editor open.', 'ecommerce-product-catalog' ) . '</p></td></tr>';
	}

	/**
	 * Real email in the store Email Design for an editable template's Preview box.
	 *
	 * @param mixed  $value Previous filter value (null).
	 * @param object $model Editable email template model.
	 * @param string $role Recipient role.
	 * @return mixed
	 */
	public static function template_preview( $value, $model, $role ) {
		if ( ! is_object( $model ) || ! method_exists( $model, 'format' ) || ! method_exists( $model, 'preview' ) || ! method_exists( $model, 'key' ) || 'plain' === $model->format() ) {
			return $value;
		}
		$id         = null;
		$definition = null;
		foreach ( IC_Email_Design::previews() as $key => $item ) {
			$template = $item['template'] ?? null;
			if ( is_array( $template ) && ( $template['key'] ?? null ) === $model->key() && ( $template['role'] ?? null ) === $role ) {
				$id         = $key;
				$definition = $item;
				break;
			}
		}
		if ( ! $definition ) {
			$definition = array(
				'callback' => static function () use ( $model, $role ) {
					return $model->preview( $role );
				},
			);
		}
		$o    = IC_Email_Design::options();
		$html = IC_Email_Design::preview_email( $definition, $o );
		if ( ! is_string( $html ) ) {
			return $value;
		}
		if ( is_string( $definition['design_notice'] ?? null ) && '' !== $definition['design_notice'] ) {
			$notice = esc_html( $definition['design_notice'] );
		} elseif ( 'modern' === $o['layout'] ) {
			$styles = array(
				'clean'   => __( 'Clean', 'ecommerce-product-catalog' ),
				'elegant' => __( 'Elegant', 'ecommerce-product-catalog' ),
				'bold'    => __( 'Bold', 'ecommerce-product-catalog' ),
			);
			/* translators: %s: Email layout and style, e.g. "Modern, Clean". */
			$notice = sprintf( esc_html__( 'Shown with your Email Design: %s, accent and footer included.', 'ecommerce-product-catalog' ), '<b>' . esc_html( 'Modern, ' . ( $styles[ $o['style'] ] ?? $styles['clean'] ) ) . '</b>' );
		} else {
			/* translators: %s: Email layout name. */
			$notice = sprintf( esc_html__( 'Shown with your Email Design: %s.', 'ecommerce-product-catalog' ), '<b>Classic</b>' );
		}
		$url = current_user_can( 'manage_product_settings' ) ? self::url() . ( $id ? '&email=' . rawurlencode( $id ) : '' ) : '';
		return array(
			'html'       => $html,
			'notice'     => $notice,
			'url'        => $url,
			'link_label' => __( 'Edit the email design', 'ecommerce-product-catalog' ),
		);
	}

	/**
	 * Load the live preview script on an editable email template editor.
	 *
	 * @param object $model Editable email template model.
	 * @return void
	 */
	public static function template_editor( $model ) {
		if ( ! is_object( $model ) || ! method_exists( $model, 'format' ) ) {
			return;
		}
		wp_enqueue_script( 'ic-email-template-preview', plugins_url( 'js/email-template-preview.js', AL_BASE_PATH . '/ecommerce-product-catalog.php' ), array( 'jquery' ), IC_EPC_VERSION, true );
		wp_localize_script(
			'ic-email-template-preview',
			'icEmailTemplatePreview',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'live'    => __( 'The preview updates as you type. Nothing is saved until you click Update.', 'ecommerce-product-catalog' ),
				'failed'  => __( 'The preview could not be updated.', 'ecommerce-product-catalog' ),
			)
		);
	}

	/** AJAX mutation endpoint, with no caller-controlled storage addresses. */
	public static function template_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- process() checks method, capability and nonce.
		$request   = wp_unslash( $_POST );
		$operation = is_string( $request['operation_type'] ?? null ) ? $request['operation_type'] : '';
		if ( ! in_array( $operation, array( 'prepare', 'use', 'undo' ), true ) ) {
			wp_send_json_error( '', 400 ); }
		$result = self::process( 'template_' . $operation, $request );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 403 ); }
		wp_send_json_success( $result );
	}

	/** Unslash only after the authorization input is isolated by process().
	 *
	 * @param string $action Action.
	 */
	private static function request( $action ) {
		return self::process( $action, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- process() verifies capability, method and nonce before use.
	}

	/** Save and return to the tab. */
	public static function save() {
		self::finish( self::request( 'save' ), 'saved' );
	}

	/** Explicit button-triggered test mail only. */
	public static function test_email() {
		self::finish( self::request( 'test' ), 'sent' );
	}

	/** AJAX adapter for the same authorized, rate-limited test delivery. */
	public static function ajax_test_email() {
		$result = self::request( 'test' );
		if ( is_wp_error( $result ) ) {
			$statuses = array(
				'forbidden'  => 403,
				'rate_limit' => 429,
			);
			wp_send_json_error( array( 'message' => $result->get_error_message() ), $statuses[ $result->get_error_code() ] ?? 400 );
		}
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'The test email could not be sent.', 'ecommerce-product-catalog' ) ), 500 );
		}
		/* translators: %s: Administrator email address. */
		wp_send_json_success( array( 'message' => sprintf( __( 'Test email sent to %s.', 'ecommerce-product-catalog' ), get_option( 'admin_email' ) ) ) );
	}

	/** Safe post/redirect/get result.
	 *
	 * @param mixed  $result Result.
	 * @param string $success Success code.
	 */
	private static function finish( $result, $success ) {
		if ( is_wp_error( $result ) && 'forbidden' === $result->get_error_code() ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		$code = is_wp_error( $result ) ? $result->get_error_code() : ( $result ? $success : 'failed' );
		wp_safe_redirect( add_query_arg( 'email_design_result', $code, self::url() ) );
		exit;
	}

	/** AJAX preview never sends or persists. */
	public static function preview() {
		$result = self::request( 'preview' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 403 );
		}
		wp_send_json_success( array( 'html' => $result ) );
	}

	/** Per-current-user permanent dismissal. */
	public static function dismiss() {
		$result = self::request( 'dismiss' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 403 );
		}
		wp_send_json_success();
	}

	/** Customized-template notice and safe before/after dialog.
	 *
	 * @param array $previews Registered feature previews.
	 */
	public static function customized_rows( $previews ) {
		$custom = array();
		foreach ( $previews as $key => $item ) {
			if ( is_callable( $item['read'] ?? null ) && ( ! isset( $item['capability'] ) || current_user_can( $item['capability'] ) ) ) {
				$raw = call_user_func( $item['read'] );
				if ( is_array( $raw ) && is_string( $raw['body'] ?? null ) && is_array( $item['defaults'] ?? null ) && IC_Email_Design::customized( $raw['body'], $item['defaults'] ) ) {
					$custom[ $key ] = $item; }
			}
		}
		/* translators: %d: Number of customized emails. */
		echo '<div id="ic-email-customized"' . ( $custom && 'modern' === IC_Email_Design::options()['layout'] ? '' : ' hidden' ) . '><div class="ic-email-design-row"><div class="ic-email-design-label">' . esc_html__( 'Email texts', 'ecommerce-product-catalog' ) . '</div><div><div class="ic-email-custom-notice"><b class="ic-email-custom-count">' . esc_html( sprintf( _n( '%d email uses your own text, so it keeps it in the Modern layout', '%d emails use your own text, so they keep it in the Modern layout', count( $custom ), 'ecommerce-product-catalog' ), count( $custom ) ) ) . '</b>:';
		foreach ( $custom as $key => $item ) {
			echo '<div class="ic-email-custom-row" data-sample="' . esc_attr( $key ) . '"><span>' . esc_html( $item['customization_label'] ?? $item['label'] ) . '</span><span><button type="button" class="button-link ic-email-custom-preview">' . esc_html__( 'Preview', 'ecommerce-product-catalog' ) . '</button>';
			if ( is_callable( $item['write'] ?? null ) ) {
				echo ' <button type="button" class="button ic-email-use-default">' . esc_html__( 'Use the Modern default text', 'ecommerce-product-catalog' ) . '</button>';
			}
			echo '</span></div>';
		}
		echo '</div><p class="description">' . esc_html__( "Using the Modern default replaces that email's text in its own settings. You see the old and new text before you confirm, and Undo restores your text.", 'ecommerce-product-catalog' ) . '</p></div></div></div><div id="ic-email-template-status" role="status"></div><dialog id="ic-email-template-dialog" aria-labelledby="ic-email-dialog-title"><h2 id="ic-email-dialog-title">' . esc_html__( 'Use the Modern default text', 'ecommerce-product-catalog' ) . '</h2><div class="ic-email-before-after"><div><h3>' . esc_html__( 'Your current text', 'ecommerce-product-catalog' ) . '</h3><pre id="ic-email-before"></pre></div><div><h3>' . esc_html__( 'Modern default text', 'ecommerce-product-catalog' ) . '</h3><pre id="ic-email-after"></pre></div></div><button type="button" class="button button-primary" id="ic-email-confirm-default">' . esc_html__( 'Use the Modern default text', 'ecommerce-product-catalog' ) . '</button> <button type="button" class="button" id="ic-email-cancel-default">' . esc_html__( 'Cancel', 'ecommerce-product-catalog' ) . '</button></dialog>';
	}

	/**
	 * Header "Custom text" choice and its one-line input.
	 *
	 * @param array $o Stored options.
	 * @param bool  $locked Whether the premium feature is locked.
	 * @return string
	 */
	public static function header_text_controls( $o, $locked ) {
		$feature = IC_Email_Design::PREMIUM_FEATURE;
		$note_id = IC_Settings_Lock::note_id( 'ic_email_design[header]' );
		$attrs   = $locked ? IC_Settings_Lock::control_attributes( $feature, $note_id ) : '';
		$class   = 'ic-email-radio' . ( $locked ? ' ' . IC_Settings_Lock::option_class() : '' );
		$html    = '<label class="' . esc_attr( $class ) . '"><input type="radio" id="ic-email-header-custom" name="design[header]" value="text" ' . checked( $o['header'], 'text', false ) . $attrs . '> ' . esc_html__( 'Custom text', 'ecommerce-product-catalog' ) . '</label>';
		if ( ! $locked || '' !== (string) $o['header_text'] ) {
			$html .= '<div id="ic-email-header-text-fields" class="ic-email-header-option-fields"' . ( 'text' !== $o['header'] ? ' hidden' : '' ) . '><input type="text" id="ic-email-header-text" class="ic-email-header-text" name="design[header_text]" aria-label="' . esc_attr__( 'Custom header text', 'ecommerce-product-catalog' ) . '" placeholder="' . esc_attr__( 'e.g. Handmade furniture since 1998', 'ecommerce-product-catalog' ) . '" value="' . esc_attr( $o['header_text'] ) . '"' . $attrs . '></div>';
		}
		if ( $locked ) {
			$html .= IC_Settings_Lock::note( $feature, $note_id );
		}
		return $html;
	}

	/**
	 * Footer text and footer link rows.
	 *
	 * @param array $o Stored options.
	 * @param bool  $locked Whether the premium feature is locked.
	 * @return string
	 */
	public static function footer_rows( $o, $locked ) {
		$feature   = IC_Email_Design::PREMIUM_FEATURE;
		$row_class = 'ic-email-design-row' . ( $locked ? ' ic-locked-setting' : '' );
		$icon      = $locked ? IC_Settings_Lock::lock_icon() : '';
		$text_note = IC_Settings_Lock::note_id( 'ic_email_design[footer_text]' );
		$link_note = IC_Settings_Lock::note_id( 'ic_email_design[footer_link]' );
		$text_attr = $locked ? IC_Settings_Lock::control_attributes( $feature, $text_note ) : '';
		$link_attr = $locked ? IC_Settings_Lock::control_attributes( $feature, $link_note ) : '';
		$html      = '<div class="' . esc_attr( $row_class ) . '"><label for="ic-email-footer" class="ic-email-design-label">' . $icon . esc_html__( 'Footer text', 'ecommerce-product-catalog' ) . '</label><div><textarea id="ic-email-footer" name="design[footer_text]" rows="3"' . $text_attr . '>' . esc_textarea( $o['footer_text'] ) . '</textarea><p class="description">' . esc_html__( 'Shown under every email. Available: [site_name], [site_url].', 'ecommerce-product-catalog' ) . '</p>' . ( $locked ? IC_Settings_Lock::note( $feature, $text_note ) : '' ) . '</div></div>';
		$html     .= '<div class="' . esc_attr( $row_class ) . '"><label for="ic-email-footer-label" class="ic-email-design-label">' . $icon . esc_html__( 'Footer link', 'ecommerce-product-catalog' ) . '</label><div><input type="text" id="ic-email-footer-label" placeholder="' . esc_attr__( 'Link text, e.g. My account', 'ecommerce-product-catalog' ) . '" name="design[footer_label]" aria-label="' . esc_attr__( 'Footer link label', 'ecommerce-product-catalog' ) . '" value="' . esc_attr( $o['footer_label'] ) . '"' . $link_attr . '><input type="url" placeholder="' . esc_attr__( 'Link address, e.g. https://…/my-account/', 'ecommerce-product-catalog' ) . '" name="design[footer_url]" aria-label="' . esc_attr__( 'Footer link URL', 'ecommerce-product-catalog' ) . '" value="' . esc_attr( $o['footer_url'] ) . '"' . $link_attr . '>' . ( $locked ? IC_Settings_Lock::note( $feature, $link_note ) : '<p class="description">' . esc_html__( 'Optional. Leave empty to hide the link.', 'ecommerce-product-catalog' ) . '</p>' ) . '</div></div>';
		return $html;
	}

	/**
	 * Email chosen for the preview: the requested one, else the default.
	 *
	 * @param array $previews Registry.
	 * @return string|null
	 */
	public static function chosen( $previews ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview selection.
		$requested = isset( $_GET['email'] ) && is_string( $_GET['email'] ) ? sanitize_key( wp_unslash( $_GET['email'] ) ) : '';
		if ( '' !== $requested && isset( $previews[ $requested ] ) ) {
			return $requested;
		}
		return isset( $previews['order'] ) ? 'order' : key( $previews );
	}

	/**
	 * External editor URL of an email with its own editor.
	 *
	 * @param array|null $item Registry item.
	 * @return string
	 */
	public static function editor_url( $item ) {
		return is_array( $item ) && is_string( $item['editor_url'] ?? null ) && ( ! isset( $item['capability'] ) || current_user_can( $item['capability'] ) ) ? $item['editor_url'] : '';
	}

	/**
	 * "Edit content" next to the preview list: popup, own editor link, or hidden.
	 *
	 * @param array|null $item Chosen registry item.
	 * @return string
	 */
	public static function edit_content_button( $item ) {
		$label = esc_html__( 'Edit content', 'ecommerce-product-catalog' );
		if ( self::editable( $item ) ) {
			return '<a class="button" id="ic-email-edit-content" href="#ic-email-content" aria-controls="ic-email-content">' . $label . '</a>';
		}
		$url = self::editor_url( $item );
		return '<a class="button" id="ic-email-edit-content" href="' . esc_url( $url ? $url : '#ic-email-content' ) . '"' . ( $url ? '' : ' hidden' ) . '>' . $label . '</a>';
	}

	/**
	 * Inline editor for the chosen editable email, or a hidden shell for the first editable one.
	 *
	 * @param array       $previews Registry.
	 * @param string|null $key Chosen email.
	 * @param array       $o Stored design.
	 * @return string
	 */
	public static function content_shell( $previews, $key, $o ) {
		if ( $key && self::editable( $previews[ $key ] ) ) {
			return self::content_form( $key, $previews[ $key ], $o );
		}
		foreach ( $previews as $id => $item ) {
			if ( self::editable( $item ) ) {
				return self::content_form( $id, $item, $o, false );
			}
		}
		return '';
	}

	/** Render approved copy and controls. */
	public static function content() {
		if ( ! current_user_can( 'manage_product_settings' ) ) {
			return;
		}
		$o        = IC_Email_Design::options();
		$locked   = IC_Settings_Lock::is_locked( IC_Email_Design::PREMIUM_FEATURE );
		$previews = IC_Email_Design::previews();
		$key      = self::chosen( $previews );
		$html     = $key ? IC_Email_Design::preview_email( $previews[ $key ], $o ) : '';
		$html     = is_wp_error( $html ) ? '' : $html;

		/* translators: %s: Email layout name. */
		$preview_label = sprintf( __( 'Preview: %s layout', 'ecommerce-product-catalog' ), ucfirst( $previews[ $key ]['layout'] ?? $o['layout'] ) );

		$result   = isset( $_GET['email_design_result'] ) && is_string( $_GET['email_design_result'] ) ? sanitize_key( $_GET['email_design_result'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Escaped redirect result display only.
		$messages = array(
			'saved'          => __( 'Email design saved.', 'ecommerce-product-catalog' ),
			'sent'           => __( 'Test email sent.', 'ecommerce-product-catalog' ),
			'failed'         => __( 'The test email could not be sent.', 'ecommerce-product-catalog' ),
			'rate_limit'     => __( 'Please wait a minute before sending another test email.', 'ecommerce-product-catalog' ),
			'invalid_sample'  => __( 'Choose an available email preview.', 'ecommerce-product-catalog' ),
			'text_saved'      => __( 'Email text saved.', 'ecommerce-product-catalog' ),
			'stale'           => __( 'This email changed. Open the editor again.', 'ecommerce-product-catalog' ),
			'invalid_content' => __( 'Subject and heading must be a single line.', 'ecommerce-product-catalog' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
		?>
		<div class="ic-email-design-columns">
		<?php
		if ( ! $previews ) {
			echo '<p>' . esc_html__( 'No active features have registered an email preview.', 'ecommerce-product-catalog' ) . '</p>'; }
		?>
		<form class="ic-email-design-card" id="ic-email-design-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="ic_email_design_save">
			<?php wp_nonce_field( 'ic_email_design' ); ?>
			<h2><?php esc_html_e( 'Email Design', 'ecommerce-product-catalog' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One design for every email your store sends: orders, quotes, inquiries, subscriptions and website notifications. To change what an email says, choose it under Preview and click Edit content.', 'ecommerce-product-catalog' ); ?></p>
			<div class="ic-email-design-row"><div class="ic-email-design-label"><?php esc_html_e( 'Email layout', 'ecommerce-product-catalog' ); ?></div><div>
				<label class="ic-email-radio"><input type="radio" name="design[layout]" value="classic" <?php checked( $o['layout'], 'classic' ); ?>> <b>Classic</b>: <?php esc_html_e( 'your current emails, unchanged', 'ecommerce-product-catalog' ); ?></label>
				<label class="ic-email-radio"><input type="radio" name="design[layout]" value="modern" <?php checked( $o['layout'], 'modern' ); ?>> <b>Modern</b>: <?php esc_html_e( 'the new design below', 'ecommerce-product-catalog' ); ?></label>
				<p class="description"><?php esc_html_e( 'Existing stores stay on Classic until you switch. You can switch back at any time; your Modern settings are kept.', 'ecommerce-product-catalog' ); ?></p>
			</div></div>
			<?php self::customized_rows( $previews ); ?>
			<div id="ic-email-modern-fields" class="<?php echo 'classic' === $o['layout'] ? 'ic-email-dim' : ''; ?>">
				<p class="ic-email-dim-note"><?php esc_html_e( 'These settings apply to the Modern layout. Preview them by choosing Modern above; nothing changes for your customers until you save.', 'ecommerce-product-catalog' ); ?></p>
				<div class="ic-email-design-row"><label for="ic-email-style" class="ic-email-design-label"><?php esc_html_e( 'Style', 'ecommerce-product-catalog' ); ?></label><div><select id="ic-email-style" name="design[style]">
					<option value="clean" <?php selected( $o['style'], 'clean' ); ?>><?php esc_html_e( 'Clean', 'ecommerce-product-catalog' ); ?></option>
					<option value="elegant" <?php selected( $o['style'], 'elegant' ); ?>><?php esc_html_e( 'Elegant', 'ecommerce-product-catalog' ); ?></option>
					<option value="bold" <?php selected( $o['style'], 'bold' ); ?>><?php esc_html_e( 'Bold', 'ecommerce-product-catalog' ); ?></option>
				</select></div></div>
				<div class="ic-email-design-row"><div class="ic-email-design-label"><?php esc_html_e( 'Header', 'ecommerce-product-catalog' ); ?></div><div>
					<label class="ic-email-radio"><input type="radio" name="design[header]" value="name" <?php checked( $o['header'], 'name' ); ?>> <?php esc_html_e( 'Site name:', 'ecommerce-product-catalog' ); ?> <b><?php echo esc_html( get_bloginfo( 'name' ) ); ?></b></label>
					<label class="ic-email-radio"><input type="radio" name="design[header]" value="logo" <?php checked( $o['header'], 'logo' ); ?>> <?php esc_html_e( 'Logo', 'ecommerce-product-catalog' ); ?></label>
					<div id="ic-email-header-logo-fields" class="ic-email-header-option-fields"<?php echo 'logo' !== $o['header'] ? ' hidden' : ''; ?>>
						<div class="ic-email-logo-control"><span id="ic-email-logo-preview">
						<?php
						if ( $o['logo_id'] ) {
							echo wp_get_attachment_image( $o['logo_id'], array( 120, 36 ) );
						} else {
							esc_html_e( 'No logo selected', 'ecommerce-product-catalog' ); }
						?>
						</span><button type="button" class="button" id="ic-email-choose-logo"><?php esc_html_e( 'Choose image', 'ecommerce-product-catalog' ); ?></button><button type="button" class="button-link" id="ic-email-remove-logo"><?php esc_html_e( 'Remove', 'ecommerce-product-catalog' ); ?></button><input type="hidden" name="design[logo_id]" id="ic-email-logo-id" value="<?php echo esc_attr( $o['logo_id'] ); ?>"></div>
						<p class="description"><?php esc_html_e( "The logo is shown at up to 200 × 60 px. Use a PNG or JPG; some email apps don't show SVG.", 'ecommerce-product-catalog' ); ?></p>
					</div>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped element markup.
					echo self::header_text_controls( $o, $locked );
					?>
				</div></div>
				<div class="ic-email-design-row"><label for="ic-email-accent" class="ic-email-design-label"><?php esc_html_e( 'Accent color', 'ecommerce-product-catalog' ); ?></label><div><input type="text" id="ic-email-accent" name="design[accent]" value="<?php echo esc_attr( $o['accent'] ); ?>"><button type="button" class="button" id="ic-email-accent-default"><?php esc_html_e( 'Default', 'ecommerce-product-catalog' ); ?></button><p class="description"><?php esc_html_e( 'Used for buttons and style accents. Button and colored header text automatically use black or white for readable contrast.', 'ecommerce-product-catalog' ); ?></p><p id="ic-email-contrast-warning" role="status" hidden></p></div></div>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped element markup.
				echo self::footer_rows( $o, $locked );
				?>
			</div>
			<div class="ic-email-design-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'ecommerce-product-catalog' ); ?></button><button type="submit" name="action" value="ic_email_design_test" class="button ic-email-design-test"><?php esc_html_e( 'Send a test email to', 'ecommerce-product-catalog' ); ?> <span class="ic-email-design-test-recipient"><?php echo esc_html( get_option( 'admin_email' ) ); ?></span></button><span class="spinner" aria-hidden="true"></span><div id="ic-email-test-result" role="status" aria-live="polite" hidden></div></div>
		</form>
		<div class="ic-email-design-preview"><div class="ic-email-preview-title"><b><?php echo esc_html( $preview_label ); ?></b><label class="screen-reader-text" for="ic-email-preview-select"><?php esc_html_e( 'Email preview', 'ecommerce-product-catalog' ); ?></label><div class="ic-email-preview-choose"><select id="ic-email-preview-select" name="sample" form="ic-email-design-form">
		<?php
		$preview_group = null;
		foreach ( $previews as $id => $preview ) {
			if ( $preview_group !== $preview['group'] ) {
				if ( null !== $preview_group ) {
					echo '</optgroup>';
				}
				$preview_group = $preview['group'];
				echo '<optgroup label="' . esc_attr( $preview_group ) . '">';
			}
			?>
			<option value="<?php echo esc_attr( $id ); ?>" data-layout="<?php echo esc_attr( $preview['layout'] ?? '' ); ?>" data-preview-help="<?php echo esc_attr( $preview['preview_help'] ?? '' ); ?>" <?php selected( $id, $key ); ?>><?php echo esc_html( $preview['label'] ); ?></option><?php } if ( null !== $preview_group ) { echo '</optgroup>'; } ?></select>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped element markup.
			echo self::edit_content_button( $key ? $previews[ $key ] : null );
			?>
			</div><p id="ic-email-preview-help" class="description"<?php echo empty( $previews[ $key ]['preview_help'] ) ? ' hidden' : ''; ?>><?php echo esc_html( $previews[ $key ]['preview_help'] ?? '' ); ?></p><p class="description"><?php esc_html_e( 'Lists only the emails of the features that are active on this store.', 'ecommerce-product-catalog' ); ?></p></div><div class="ic-email-preview-viewport"><iframe id="ic-email-preview" title="<?php esc_attr_e( 'Email preview', 'ecommerce-product-catalog' ); ?>" sandbox="allow-same-origin" srcdoc="<?php echo esc_attr( $html ); ?>"></iframe></div><p class="description"><?php esc_html_e( 'The preview updates as you type. The list shows the emails of your active extensions.', 'ecommerce-product-catalog' ); ?></p><p id="ic-email-preview-status" role="status"></p><p id="ic-email-content-result" role="status" aria-live="polite"></p><p id="ic-email-content-dirty" class="ic-email-content-dirty" hidden><?php esc_html_e( 'Unsaved changes: the highlighted parts are what you changed. Cancel restores the saved text.', 'ecommerce-product-catalog' ); ?></p>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped element markup.
			echo self::content_shell( $previews, $key, $o );
			?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ic_email_design_settings_row' ) ) {
	/**
	 * Feature settings row that replaces an email's subject and template fields.
	 *
	 * @param string $id Registered email ID.
	 * @return string Escaped table row, or '' to keep the legacy fields.
	 */
	function ic_email_design_settings_row( $id ) {
		return IC_Email_Design_Settings::settings_row( $id );
	}
}

if ( ! function_exists( 'ic_email_design_settings_note' ) ) {
	/**
	 * Note under the moved email rows, printed once.
	 *
	 * @return string Escaped table row.
	 */
	function ic_email_design_settings_note() {
		return IC_Email_Design_Settings::settings_note();
	}
}
IC_Email_Design_Settings::boot();
