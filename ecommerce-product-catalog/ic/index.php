<?php
/**
 * Framework bootstrap.
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
if ( ! function_exists( 'ic_framework_require_once' ) ) {
	require_once __DIR__ . '/functions.php';
}
ic_framework_require_once( __DIR__ . '/cache.php' );
if ( ! function_exists( 'IC_Html_Util' ) ) {
	ic_framework_require_once( __DIR__ . '/class-ic-html-util.php' );
}
ic_framework_require_once( __DIR__ . '/settings/index.php' );
