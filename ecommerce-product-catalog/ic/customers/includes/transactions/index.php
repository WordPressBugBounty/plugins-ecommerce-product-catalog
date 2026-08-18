<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages transactions includes folder
 *
 * Created by Norbert Dreszer.
 * Date: 05-Mar-15
 * Time: 14:45
 * Package: index.php
 */
if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/includes/transactions/transactions-customer.php' );
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/includes/transactions/customer-panel.php' );
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/includes/transactions/customer-panel-settings.php' );
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/includes/transactions/customer-panel-security.php' );
}
