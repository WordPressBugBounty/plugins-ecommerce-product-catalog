<?php
/**
 * Framework cache classes bootstrap.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

ic_framework_require_once( __DIR__ . '/class-ic-global-cache.php' );
ic_framework_require_once( __DIR__ . '/class-ic-session-cache.php' );
ic_framework_require_once( __DIR__ . '/class-ic-transient-cache.php' );
