<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Defines login form widget
 *
 * Created by Norbert Dreszer.
 * Date: 09-Mar-15
 * Time: 17:51
 * Package: login-form-widget.php
 */
class ic_digital_customers_login_form extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname'   => 'ic_digital_customers_login_form',
			'description' => __( 'Show digital customers login form.', 'ecommerce-product-catalog' ),
		);
		parent::__construct( 'ic_digital_customers_login_form', __( 'Login Form', 'ecommerce-product-catalog' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		if ( ! function_exists( 'ic_customer_panel_panel_id' ) || ( function_exists( 'ic_customer_panel_panel_id' ) && get_the_ID() != ic_customer_panel_panel_id() ) ) {
			$customer_id = ic_get_logged_customer_id();
			$is_customer = is_ic_digital_customer( $customer_id );
			if ( ! empty( $customer_id ) && ! $is_customer ) {
				return;
			}
			echo wp_kses_post( $args['before_widget'] );
			if ( $title ) {
				echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
			}
			if ( $is_customer ) {
				ic_digital_customer_login_url();
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The helper escapes form values during construction; post KSES would remove controls and trusted integration markup.
				echo ic_digital_customer_login_form( true, 'login_form widget_login' );
			}
			echo wp_kses_post( $args['after_widget'] );
		}
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
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ecommerce-product-catalog' ); ?>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
					value="<?php echo esc_attr( $title ); ?>"/></label></p>
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
