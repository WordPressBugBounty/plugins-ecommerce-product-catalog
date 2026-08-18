<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages functions files
 *
 * Here all functions files are defined and managed.
 *
 * @version		1.0.0
 * @package		implecode-digital-customers/functions
 * @author 		Norbert Dreszer
 */
if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/functions/pluggable.php' );
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/functions/conditionals.php' );
	ic_framework_require_once( AL_CUSTOMERS_BASE_PATH . '/functions/users-functions.php' );
}
