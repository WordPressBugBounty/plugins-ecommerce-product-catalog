<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages includes files
 *
 * Here all includes files are defined and managed.
 *
 * @version        1.0.0
 * @package        digital-products-order/includes
 * @author        Norbert Dreszer
 */
if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( AL_PO_BASE_PATH . '/includes/verify-payment.php' );
	ic_framework_require_once( AL_PO_BASE_PATH . '/includes/register-digital-orders.php' );
	ic_framework_require_once( AL_PO_BASE_PATH . '/includes/digital-order-columns.php' );
	ic_framework_require_once( AL_PO_BASE_PATH . '/includes/save-order.php' );
	ic_framework_require_once( AL_PO_BASE_PATH . '/includes/latest-order.php' );
}
