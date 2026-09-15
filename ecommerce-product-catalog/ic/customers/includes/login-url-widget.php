<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Defines Popup login widget
 *
 * Created by Norbert Dreszer.
 * Date: 09-Mar-15
 * Time: 17:23
 * Package: login-url-widget.php
 */
class ic_digital_customers_popup_login extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname'   => 'popup_login',
			'description' => __( 'Show button that triggers a popup login form.', 'ecommerce-product-catalog' ),
		);
		parent::__construct( 'ic_digital_customers_popup_login', __( 'Popup Login', 'ecommerce-product-catalog' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		// if ( ! function_exists( 'ic_customer_panel_panel_id' ) || ( function_exists( 'ic_customer_panel_panel_id' ) && ( ! ic_customer_panel_panel_id() || get_the_ID() != ic_customer_panel_panel_id() ) ) ) {
		echo wp_kses_post( $args['before_widget'] );
		if ( $title ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		$instance['label']       = isset( $instance['label'] ) ? $instance['label'] : '';
		$instance['desc']        = isset( $instance['desc'] ) ? wp_kses_post( $instance['desc'] ) : '';
		$instance['login_title'] = isset( $instance['login_title'] ) ? wp_strip_all_tags( $instance['login_title'] ) : '';
		/*
		 * Callers that bring their own popup trigger and overlay - the customer login menu
		 * element in qc-sc/includes/login-menu.php is the one in the tree - pass these as
		 * false. Both default to true so a plain sidebar or template widget is self
		 * contained.
		 */
		$show_login_button = ! isset( $instance['login_button'] ) || $instance['login_button'];
		$show_overlay      = ! isset( $instance['overlay'] ) || $instance['overlay'];
		/*
		 * Only the logged out state needs the separate trigger button: when the visitor is
		 * logged in ic_digital_customer_login_form() short circuits to
		 * ic_digital_customer_login_url() and already returns the account buttons, so calling
		 * it here as well would render My Account / Sign out twice. The button is what
		 * ic-user-login.js binds the popup to ('.login_button.not-logged a'), which is why the
		 * popup form has no opener without it. $show_form is false because the widget echoes
		 * the form itself on the next line.
		 */
		if ( $show_login_button && ! is_user_logged_in() && ! ic_customer_login_is_custom_logged() ) {
			ic_digital_customer_login_url( 1, $instance['label'], false, false, true, false );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The helper escapes form values during construction; post KSES would remove controls and trusted integration markup.
		echo ic_digital_customer_login_form( false, 'login_form popup_login_form', false, $instance['login_title'], $instance['desc'] );
		/*
		 * create_ic_overlay() being defined says nothing about an overlay reaching the page:
		 * ecommerce-product-catalog defines it but only the catalog-me theme family hooks it
		 * to wp_footer, so on any other theme function_exists() passed and the popup opened
		 * with no dimmed background at all. Test for the hook that actually prints it.
		 */
		if ( $show_overlay && ! has_action( 'wp_footer', 'create_ic_overlay' ) ) {
			echo '<div id="ic_overlay" class="ic-overlay" style="display:none"></div>';
		}
		echo wp_kses_post( $args['after_widget'] );
		// }
	}

	function form( $instance ) {
		$instance = wp_parse_args(
			(array) $instance,
			array(
				'title' => '',
				'label' => '',
			)
		);
		$title    = $instance['title'];
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ecommerce-product-catalog' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
						value="<?php echo esc_attr( $title ); ?>"/></label></p>
		<p><label
				for="<?php echo esc_attr( $this->get_field_id( 'label' ) ); ?>"><?php esc_html_e( 'Button Description', 'ecommerce-product-catalog' ); ?>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'label' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'label' ) ); ?>" type="text"
					value="<?php echo esc_attr( $instance['label'] ); ?>"/></label></p>
					<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance          = $old_instance;
		$new_instance      = wp_parse_args(
			(array) $new_instance,
			array(
				'title' => '',
				'label' => '',
			)
		);
		$instance['title'] = wp_strip_all_tags( $new_instance['title'] );
		$instance['label'] = wp_strip_all_tags( $new_instance['label'] );
		return $instance;
	}
}
