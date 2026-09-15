<?php
/**
 * Customer login form helper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 *
 *  @version       1.0.0
 *  @author        impleCode
 *
 */
/**
 * Handles customer login UI and actions.
 */
class IC_User_Login {

	/**
	 * Guest login helper.
	 *
	 * @var IC_Guest_Login
	 */
	private $guest_login;

	/**
	 * User registration helper.
	 *
	 * @var IC_User_Registration
	 */
	private $registration;

	/**
	 * Login redirect URL.
	 *
	 * @var mixed|string
	 */
	private $login_redirect;

	/**
	 * Whether registration helper should collect phone numbers.
	 *
	 * @var bool
	 */
	private $collect_phone = true;

	/**
	 * Checks whether the current visitor is already authenticated for login UI purposes.
	 *
	 * @return bool
	 */
	public function is_logged_in() {
		if ( is_user_logged_in() ) {
			return true;
		}

		return (bool) apply_filters( 'ic_is_user_custom_logged', false );
	}

	/**
	 * Checks whether checkout should show the login prompt.
	 *
	 * @return bool
	 */
	public function should_render_checkout_prompt() {
		return ! $this->is_logged_in();
	}

	/**
	 * Initializes the login helper.
	 *
	 * @param bool       $registration   Whether registration is enabled.
	 * @param bool       $collect_phone  Whether phone collection is enabled.
	 * @param bool       $guest_login    Whether guest login is enabled.
	 * @param string|int $login_redirect Redirect URL or page ID.
	 */
	public function __construct( $registration = false, $collect_phone = true, $guest_login = false, $login_redirect = '' ) {
		if ( is_numeric( $login_redirect ) ) {
			$login_redirect = get_permalink( $login_redirect );
		}
		$this->login_redirect = $login_redirect;
		$this->collect_phone  = (bool) $collect_phone;
		if ( file_exists( __DIR__ . '/user-functions.php' ) ) {
			require_once __DIR__ . '/user-functions.php';
		}
		if ( ! class_exists( 'IC_Html_Util' ) && file_exists( __DIR__ . '/class-ic-html-util.php' ) ) {
			require_once __DIR__ . '/class-ic-html-util.php';
		}
		$this->enable_features( $registration, $guest_login );

		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'login_form_middle', array( $this, 'lost_password_link_html' ) );
		add_filter( 'ic_customer_login_form_class', array( $this, 'css_class' ), 10, 2 );
		add_filter( 'ic_customer_login_form', array( $this, 'form_options' ), 10, 4 );
	}

	/**
	 * Enables optional login features requested by a later shared consumer.
	 *
	 * A cached customer login helper may first be created by a guest-only flow
	 * and later reused by a checkout that enables registration. Features are
	 * additive for the request and must not create a second filter owner.
	 *
	 * @param bool $registration Whether registration support should be enabled.
	 * @param bool $guest_login  Whether guest-login support should be enabled.
	 *
	 * @return void
	 */
	public function enable_features( $registration = false, $guest_login = false ) {
		$this->maybe_enable_registration( $registration );
		$this->maybe_enable_guest( $guest_login );
	}

	/**
	 * Boots the registration helper lazily when the current runtime needs it.
	 *
	 * @param bool $enabled Whether registration should be enabled.
	 *
	 * @return void
	 */
	private function maybe_enable_registration( $enabled ) {
		if ( empty( $enabled ) || ! empty( $this->registration ) ) {
			return;
		}
		if ( ! class_exists( 'IC_Confirmation_Transient' ) && file_exists( __DIR__ . '/class-ic-confirmation-transient.php' ) ) {
			require_once __DIR__ . '/class-ic-confirmation-transient.php';
		}
		if ( ! class_exists( 'IC_Email_Templates' ) && file_exists( __DIR__ . '/class-ic-email-templates.php' ) ) {
			require_once __DIR__ . '/class-ic-email-templates.php';
		}
		if ( ! class_exists( 'IC_Email_Template' ) && file_exists( __DIR__ . '/class-ic-email-template.php' ) ) {
			require_once __DIR__ . '/class-ic-email-template.php';
		}
		if ( ! class_exists( 'IC_User_Registration' ) && file_exists( __DIR__ . '/class-ic-user-registration.php' ) ) {
			require_once __DIR__ . '/class-ic-user-registration.php';
		}
		if ( class_exists( 'IC_User_Registration' ) ) {
			$this->registration = new IC_User_Registration( $this->collect_phone );
		}
	}

	/**
	 * Boots the guest helper lazily when the current runtime needs it.
	 *
	 * @param bool $enabled Whether guest checkout should be enabled.
	 *
	 * @return void
	 */
	private function maybe_enable_guest( $enabled ) {
		if ( empty( $enabled ) || ! empty( $this->guest_login ) ) {
			return;
		}
		if ( ! class_exists( 'IC_Guest_Login' ) && file_exists( __DIR__ . '/class-ic-guest-login.php' ) ) {
			require_once __DIR__ . '/class-ic-guest-login.php';
		}
		if ( class_exists( 'IC_Guest_Login' ) ) {
			$this->guest_login = new IC_Guest_Login();
		}
	}

	/**
	 * Enables optional helpers for the current form settings without creating a second login instance.
	 *
	 * @param array $settings Current login settings.
	 *
	 * @return void
	 */
	private function maybe_enable_optional_helpers( $settings ) {
		$this->maybe_enable_registration( apply_filters( 'ic_customer_login_registration_enabled', false, $settings ) );
		$this->maybe_enable_guest( apply_filters( 'ic_customer_login_guest_enabled', false, $settings ) );
	}

	/**
	 * Enqueues login form scripts and styles.
	 *
	 * @param array $settings Current login settings.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $settings = array() ) {
		if ( wp_style_is( 'ic-user-login', 'registered' ) ) {
			wp_enqueue_style( 'ic-user-login' );
		}
		if ( wp_script_is( 'ic-user-login', 'registered' ) ) {
			wp_enqueue_script( 'ic-user-login' );
			wp_localize_script(
				'ic-user-login',
				'ic_user_login',
				array(
					'ajaxurl'             => admin_url( 'admin-ajax.php' ),
					'registration_nonce'  => wp_create_nonce( 'ic_user_register' ),
					'login_cancel_button' => ic_html_util()->button( __( 'Cancel', 'ecommerce-product-catalog' ), 'ic-cancel-login ic-secondary-button' ),
				)
			);
		}
		if ( apply_filters( 'ic_customer_login_registration_enabled', false, $settings ) || apply_filters( 'ic_customer_login_guest_enabled', false, $settings ) ) {
			if ( wp_style_is( 'ic-user-login-qc-sc', 'registered' ) ) {
				wp_enqueue_style( 'ic-user-login-qc-sc' );
			}
			if ( wp_script_is( 'ic-user-login-qc-sc', 'registered' ) ) {
				wp_enqueue_script( 'ic-user-login-qc-sc' );
			}
		}
	}

	/**
	 * Adds login-related body classes.
	 *
	 * @param array $classes Body classes.
	 *
	 * @return array
	 */
	public function body_class( $classes ) {
		if ( (bool) apply_filters( 'ic_is_user_custom_logged', false ) ) {
			$classes[] = 'ic-logged-in';
		}

		return $classes;
	}

	/**
	 * Defines the customer login form markup.
	 *
	 * @param bool   $show       Whether to show the form initially.
	 * @param string $form_class Form CSS class.
	 * @param bool   $closer     Whether to show a closer button.
	 * @param string $title      Form title.
	 * @param string $desc       Form description.
	 * @param array  $settings   Form settings.
	 * @param array  $pre_args   Login form arguments.
	 *
	 * @return string
	 */
	public function form(
		$show = true,
		$form_class = 'login_form',
		$closer = false,
		$title = null,
		$desc = '',
		$settings = array(),
		$pre_args = array()
	) {
		if ( $this->is_logged_in() ) {

			return $this->url( 0, '', false, false, true, false );
		}
		if ( empty( $settings ) ) {
			if ( function_exists( 'get_shopping_cart_settings' ) ) {
				$settings = get_shopping_cart_settings();
			} else {
				$settings = array();
			}
		}
		$this->maybe_enable_optional_helpers( $settings );
		$this->enqueue_scripts( $settings );
		if ( 'login_form' !== $form_class ) {
			$form_class .= ' login_form';
		}
		$title = null !== $title ? $title : __( 'Customer Login', 'ecommerce-product-catalog' );
		$args  = apply_filters(
			'ic_customer_login_form_args',
			array_merge(
				array(
					'echo'              => false,
					'redirect'          => apply_filters( 'ic_login_form_redirect', $this->login_redirect ),
					'form_id'           => 'loginform',
					'label_username'    => __( 'Email Address', 'ecommerce-product-catalog' ),
					'label_password'    => __( 'Password', 'ecommerce-product-catalog' ),
					'label_remember'    => __( 'Remember Me', 'ecommerce-product-catalog' ),
					'label_log_in'      => __( 'Sign in', 'ecommerce-product-catalog' ),
					'id_username'       => 'user_login',
					'id_password'       => 'user_pass',
					'id_remember'       => 'rememberme',
					'id_submit'         => 'wp-submit',
					'remember'          => false,
					'value_username'    => '',
					'value_remember'    => false,
					'required_username' => true,
					'required_password' => true,
				),
				$pre_args
			),
			$settings
		);
		$style = '';
		if ( ! $show ) {
			$style = 'style="display: none"';
		}
		$class = 'not-logged';
		if ( is_user_logged_in() ) {
			$class = 'logged';
		}
		if ( ! empty( $settings['style'] ) ) {
			$class .= ' ic-login-form-style-' . $settings['style'];
		}
		$form = '<div class="' . $form_class . ' ' . apply_filters( 'ic_customer_login_form_class', $class, $settings ) . '" ' . $style . '>';

		$tabs         = apply_filters( 'login_form_tabs', '<li><a href="#login_form">' . __( 'Login', 'ecommerce-product-catalog' ) . '</a></li>' );
		$tabs_num     = substr_count( $tabs, '<li>' );
		$inside_class = 'inside_login';
		if ( $tabs_num > 1 ) {
			$inside_class .= ' ui-tabs';
		}
		$form .= '<div class="' . $inside_class . '">';
		if ( $closer ) {
			$form .= '<span class="closer"></span>';
		}
		if ( $tabs_num > 1 ) {
			$form .= '<ul class="ui-tabs-nav">' . $tabs . '</ul>';
		}
		$form .= apply_filters( 'login_form_start', '<div id="login_form">', $settings );
		if ( ! empty( $title ) ) {
			$form .= '<h2>' . $title . '</h2>';
		}
		$form .= $desc;
		$form .= $this->login_actions();
		if ( function_exists( 'design_schemes' ) ) {
			$design_schemes = design_schemes( 'box', 0 );
		} else {
			$design_schemes = '';
		}
		if ( empty( $args['redirect'] ) ) {
			unset( $args['redirect'] );
		}
		$form .= str_replace( 'button button-primary', 'button ' . $design_schemes, wp_login_form( $args ) ) . '</div>';
		$form .= apply_filters( 'login_form_end', '', $settings );
		$form .= '</div></div>';

		return apply_filters( 'ic_customer_login_form', $form, $settings, $args, $show );
	}

	/**
	 * Returns login error and status messages.
	 *
	 * @return string
	 */
	public function login_actions() {
		$actions = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only login state handling.
		$login_state = isset( $_GET['login'] ) ? sanitize_key( wp_unslash( $_GET['login'] ) ) : '';
		if ( 'failed' === $login_state ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only login state handling.
			if ( ! empty( $_GET['error_message'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is read-only, unslashed, urldecoded, then sanitized before use.
				$message = sanitize_text_field( urldecode( wp_unslash( $_GET['error_message'] ) ) );
			} else {
				/* translators: 1: opening reset password link, 2: closing reset password link. */
				$message = sprintf( __( 'The login failed. If you forgot your password please try to %1$sreset it here%2$s.', 'ecommerce-product-catalog' ), '<a href="' . wp_lostpassword_url( $this->login_redirect ) . '">', '</a>' );
			}
			$actions .= implecode_warning( $message, 0 );
		} elseif ( 'empty' === $login_state ) {
			$actions .= implecode_warning( __( 'Please enter both username and password.', 'ecommerce-product-catalog' ), 0 );
		}

		return $actions;
	}

	/**
	 * Shows the customer login URL.
	 *
	 * @param bool   $should_echo    Whether to echo the output.
	 * @param string $desc           Description HTML.
	 * @param bool   $lower          Whether to lowercase the button.
	 * @param bool   $panel_redirect Unused panel redirect flag.
	 * @param bool   $panel          Whether to show the account panel button.
	 * @param bool   $show_form      Whether to append the popup form.
	 *
	 * @return string
	 */
	public function url( $should_echo = 1, $desc = '', $lower = false, $panel_redirect = false, $panel = true, $show_form = true ) {
		$class      = 'not-logged';
		$button     = '';
		$user_email = '';
		$url        = ic_loginout_url();
		if ( $this->is_logged_in() ) {
			$button_class = ' ic-logout-button';
			if ( $panel ) {
				$button_class .= ' ic-secondary-button';
			}
			$loginout = '<a class="button ' . design_schemes( 'box', 0 ) . $button_class . '" href="' . esc_url( $url ) . '">' . __( 'Sign out', 'ecommerce-product-catalog' ) . '</a>';

			$current_user = wp_get_current_user();
			if ( ! empty( $current_user->data->user_login ) ) {
				$user_email = $current_user->data->user_login;
			}
			$desc  = '';
			$class = 'logged';
			if ( $panel ) {
				$panel_url = '';
				if ( function_exists( 'ic_customer_panel_panel_url' ) ) {
					$panel_url = ic_customer_panel_panel_url();
				}
				if ( ! empty( $panel_url ) ) {
					$button = '<a class="button ic-my-account" href="' . $panel_url . '">' . __( 'My Account', 'ecommerce-product-catalog' ) . '</a>';
				}
			}
		} else {
			$loginout = '<a class="button ' . design_schemes( 'box', 0 ) . ' ic-login-button" href="' . esc_url( $url ) . '">' . __( 'Sign in', 'ecommerce-product-catalog' ) . '</a>';
		}

		$link   = $lower ? strtolower( $loginout ) : $loginout;
		$return = '<span class="login_button ' . $class . '"><span class="ic-logged-username">' . __( 'Login', 'ecommerce-product-catalog' ) . ': ' . $user_email . '</span>' . $button . $link . ' ' . $desc . '</span>';
		if ( $show_form && ! $this->is_logged_in() ) {
			$return .= $this->form( false, 'login_form popup_login_form', true );
		}

		return echo_ic_setting( $return, $should_echo );
	}

	/**
	 * Renders the shared inline checkout login prompt.
	 *
	 * @param string $message Translated prompt string containing `%s` for the login button markup.
	 * @param bool   $panel   Whether the generated login URL should include account-panel actions once logged in.
	 *
	 * @return string
	 */
	public function checkout_prompt( $message = 'Returning customer? %s', $panel = false ) {
		if ( ! $this->should_render_checkout_prompt() ) {
			return '';
		}

		return '<div class="ic-checkout-login-box-container">' . implecode_info( sprintf( $message, $this->url( 0, '', false, false, $panel ) ), 0, 0 ) . '</div>';
	}

	/**
	 * Returns the password reset URL.
	 *
	 * @return string
	 */
	public function pass_reset_url() {
		return apply_filters( 'ic_lost_password_url', get_home_url() . '/wp-login.php?action=lostpassword' );
	}

	/**
	 * Returns the lost password link HTML.
	 *
	 * @return string
	 */
	public function lost_password_link_html() {

		return '<a class="ic-lost-password-link" target="_blank" href="' . $this->pass_reset_url() . '">' . __( 'Forgot password?', 'ecommerce-product-catalog' ) . '</a>';
	}

	/**
	 * Filters the supported login settings.
	 *
	 * @param array $settings Settings array.
	 *
	 * @return array
	 */
	public function valid_settings( $settings ) {
		$valid_settings = array();
		$valid          = array(
			'guest_checkout',
			'user_registration',
			'user_registration_bottom',
			'login_options',
		);
		foreach ( $settings as $key => $set ) {
			if ( in_array( $key, $valid, true ) ) {
				$valid_settings[ $key ] = $set;
			}
		}

		return $valid_settings;
	}

	/**
	 * Builds the login form CSS class string.
	 *
	 * @param string $css_class Current CSS class string.
	 * @param array  $settings  Form settings.
	 *
	 * @return string
	 */
	public function css_class( $css_class, $settings ) {
		$classes  = preg_split( '/\s+/', trim( $css_class ) );
		$settings = $this->valid_settings( $settings );
		foreach ( $settings as $key => $set ) {
			$classes[] = sanitize_title( $key ) . '-' . sanitize_title( $set );
		}

		$classes = array_filter( array_unique( $classes ) );

		return implode( ' ', $classes );
	}

	/**
	 * Adds guest and registration options around the login form.
	 *
	 * @param string $form            Login form HTML.
	 * @param array  $settings        Form settings.
	 * @param array  $login_args      Login form arguments.
	 * @param bool   $show_login_form Whether the login form is shown by default.
	 *
	 * @return string
	 */
	public function form_options( $form, $settings, $login_args, $show_login_form = false ) {
		if ( false !== strpos( $form, 'ic-login-form-container' ) ) {
			return $form;
		}
		$additions         = '';
		$options_class     = 'ic-login-form-options';
		$before_form       = apply_filters( 'ic_login_form_before', '', $settings );
		$buttons           = array();
		$saved_guest_login = '';
		$buttons[]         = array(
			'label' => __( 'Sign In Now', 'ecommerce-product-catalog' ),
			'class' => 'sign-in',
		);
		if ( ! empty( $settings['guest_checkout'] ) && empty( $login_args['disable_guest'] ) && ! empty( $this->guest_login ) ) {
			$saved_guest_login = $this->guest_login->saved_guest_login();
			if ( ! empty( $saved_guest_login ) ) {
				$additions     .= $saved_guest_login;
				$options_class .= ' ic-hidden';
			}

			$buttons[]  = array(
				'label' => __( 'Continue as Guest', 'ecommerce-product-catalog' ),
				'class' => 'guest-sign-in',
			);
			$additions .= $this->guest_login->guest_login( $settings );
		}
		$final_buttons = ic_html_util()->buttons( $buttons );
		$form_options  = apply_filters( 'ic_login_form_options', $final_buttons, $settings );
		if ( isset( $settings['login_options'] ) && false === $settings['login_options'] ) {
			$options = '';
		} else {
			$options = ic_html_util()->div( $form_options, apply_filters( 'ic_customer_login_form_class', $options_class, $settings ) );
		}
		$final_additions = apply_filters( 'ic_login_form_additions', $additions, $settings );
		$container_class = 'ic-login-form-container';
		if ( ! empty( $show_login_form ) && empty( $saved_guest_login ) ) {
			$container_class .= ' ic-login-form-container-show-login-form';
		}
		$is_popup_form = false !== strpos( $form, 'popup_login_form' );
		if ( $is_popup_form && 1 === count( $buttons ) ) {
			return ic_html_util()->div( $before_form . $form . $final_additions, $container_class );
		}
		if ( 1 === count( $buttons ) && $form_options === $final_buttons && $final_additions === $additions ) {
			return ic_html_util()->div( $before_form . $form, $container_class );
		} else {
			return ic_html_util()->div( $before_form . $form . $final_additions . $options, $container_class );
		}
	}

	/**
	 * Returns the stored guest payload or a single guest field.
	 *
	 * @param string|null $what Optional guest field name.
	 *
	 * @return array|string
	 */
	public function get_guest( $what = null ) {
		if ( ! empty( $this->guest_login ) && method_exists( $this->guest_login, 'get_guest' ) ) {
			return $this->guest_login->get_guest( $what );
		}
		if ( ! empty( $what ) ) {
			return '';
		}

		return array();
	}

	/**
	 * Checks whether the current visitor is using guest checkout.
	 *
	 * @param bool $default_value Default result.
	 *
	 * @return bool
	 */
	public function is_guest( $default_value = false ) {
		if ( ! empty( $this->guest_login ) && method_exists( $this->guest_login, 'is_guest' ) ) {
			return $this->guest_login->is_guest( $default_value );
		}

		return $default_value;
	}

	/**
	 * Returns the saved-guest login summary HTML.
	 *
	 * @param string $html Existing summary HTML.
	 *
	 * @return string
	 */
	public function saved_guest_login( $html = '' ) {
		if ( ! empty( $this->guest_login ) && method_exists( $this->guest_login, 'saved_guest_login' ) ) {
			return $this->guest_login->saved_guest_login( $html );
		}

		return $html;
	}

	/**
	 * Returns the guest-login form HTML.
	 *
	 * @param array $settings       Login settings.
	 * @param array $default_values Default form values.
	 *
	 * @return string
	 */
	public function guest_login( $settings, $default_values = array() ) {
		if ( ! empty( $this->guest_login ) && method_exists( $this->guest_login, 'guest_login' ) ) {
			return $this->guest_login->guest_login( $settings, $default_values );
		}

		return '';
	}

	/**
	 * Clears any saved guest session data.
	 *
	 * @return void
	 */
	public function clear_guest() {
		if ( ! empty( $this->guest_login ) && method_exists( $this->guest_login, 'clear_guest' ) ) {
			$this->guest_login->clear_guest();
		}
	}
}

if ( ! class_exists( 'ic_user_login', false ) ) {
	class_alias( 'IC_User_Login', 'ic_user_login' );
}
