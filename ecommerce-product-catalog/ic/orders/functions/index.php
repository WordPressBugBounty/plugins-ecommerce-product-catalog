<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages digital order functions folder
 *
 * Here all digital order functions files are defined and managed.
 *
 * @version		1.0.0
 * @package		digital-products-order/functions
 * @author 		Norbert Dreszer
 */
if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( AL_PO_BASE_PATH . '/functions/conditionals.php' );
	ic_framework_require_once( AL_PO_BASE_PATH . '/functions/functions.php' );
	ic_framework_require_once( AL_PO_BASE_PATH . '/functions/pluggable.php' );
}
