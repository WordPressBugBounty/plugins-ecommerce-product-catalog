<?php
/**
 * Identity privacy bootstrap.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'IC_IDENTITY_PRIVACY_BOOTSTRAPPED' ) ) {
	return;
}
define( 'IC_IDENTITY_PRIVACY_BOOTSTRAPPED', true );

ic_framework_require_once( __DIR__ . '/functions.php' );
ic_framework_require_once( __DIR__ . '/class-ic-identity-erasure.php' );
ic_framework_require_once( __DIR__ . '/class-ic-identity-privacy.php' );

IC_Identity_Erasure::init();
IC_Identity_Privacy::init();
