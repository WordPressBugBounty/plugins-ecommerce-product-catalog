<?php
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/*
 *
 *  @version       1.0.0
 *  @package
 *  @author        impleCode
 *
 */

class ic_latest_order {

	function __construct() {
		add_action( 'ic_save_order', array( $this, 'save' ), 10, 1 );
		add_action( 'ic_thank_you_data', array( $this, 'data_html' ) );
	}

	function save( $order_id ) {
		$session						 = get_product_catalog_session();
		$session[ 'latest_order_id' ]	 = $order_id;
		set_product_catalog_session( $session );
	}

	function get() {
		$session = get_product_catalog_session();
		if ( !empty( $session[ 'latest_order_id' ] ) ) {
			return intval( $session[ 'latest_order_id' ] );
		}
	}

	function data_html() {
		$order_id = $this->get();
		if ( empty( $order_id ) ) {
			return;
		}
		?>
		<div class="thank-you-order-data">
			<?php
			$data = $this->data( $order_id );
			echo '<ul>';
			foreach ( $data as $data_name => $data_value ) {
				echo '<li>' . esc_html( $this->readable( $data_name ) . ': ' . $this->sanitize( $data_value, $data_name ) ) . '</li>';
			}
			echo '</ul>';
			?>
		</div>
		<?php
	}

	function data( $order_id ) {
		$payment_details = ic_get_order_payment_details( $order_id );
		$display_data	 = $this->display_data();
		$return			 = array();
		foreach ( $display_data as $data_name ) {
			if ( !isset( $payment_details[ $data_name ] ) ) {
				continue;
			}
			$return[ $data_name ] = $payment_details[ $data_name ];
		}
		return apply_filters( 'ic_thank_you_order_data', $return, $order_id );
	}

	function readable( $data ) {
		return ic_ucfirst( str_replace( array( '_', '-' ), ' ', $data ) );
	}

	function display_data() {
		return array(
			'date',
		);
	}

	function sanitize( $value, $name ) {
		if ( $name === 'date' ) {
			if ( !is_numeric( $value ) ) {
				$value = strtotime( $value );
			}
			$value = wp_date( get_option( 'date_format' ), $value );
		}
		return $value;
	}

}

global $ic_latest_order;
$ic_latest_order = new ic_latest_order;
