<?php
/**
 * EPC bundled framework bootstrap.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_framework_require_once' ) ) {
	require_once __DIR__ . '/functions.php';
}
if ( ! defined( 'IC_FRAMEWORK_BOOTSTRAPPED' ) ) {
	define( 'IC_FRAMEWORK_BOOTSTRAPPED', true );
}

ic_framework_require_once( __DIR__ . '/conditionals.php' );
ic_framework_require_once( __DIR__ . '/cache.php' );
ic_framework_require_once( __DIR__ . '/user-functions.php' );

if ( ! function_exists( 'IC_Html_Util' ) ) {
	ic_framework_require_once( __DIR__ . '/class-ic-html-util.php' );
}

ic_framework_require_once( __DIR__ . '/settings/index.php' );
ic_framework_require_once( __DIR__ . '/settings/class-ic-epc-defaults-sync-controller.php' );
ic_framework_require_once( __DIR__ . '/style-scripts.php' );
if ( ! defined( 'IC_SIMPLE_FORMBUILDER_ENABLE_LEGACY_COMPAT' ) ) {
	define( 'IC_SIMPLE_FORMBUILDER_ENABLE_LEGACY_COMPAT', true );
}
ic_framework_require_once( __DIR__ . '/simple-formbuilder/index.php' );
ic_framework_require_once( __DIR__ . '/ai/index.php' );
ic_framework_require_once( __DIR__ . '/selectors/index.php' );
ic_framework_require_once( __DIR__ . '/privacy/index.php' );
