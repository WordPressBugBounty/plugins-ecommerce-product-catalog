<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( __DIR__ . '/ic_cached_cart.php' );
	ic_framework_require_once( __DIR__ . '/cart-conditionals.php' );
	ic_framework_require_once( __DIR__ . '/functions.php' );
	ic_framework_require_once( __DIR__ . '/pluggable.php' );
	if ( ! class_exists( 'ic_cart_checkout_form_email', false ) ) {
		ic_framework_require_once( __DIR__ . '/notifications.php' );
	}
	ic_framework_require_once( __DIR__ . '/ic-price-cached.php' );
	ic_framework_require_once( __DIR__ . '/ic-cart.php' );
	ic_framework_require_once( __DIR__ . '/cart-info.php' );
	ic_framework_require_once( __DIR__ . '/ic-cart-ajax.php' );
	ic_framework_require_once( __DIR__ . '/cart-cache.php' );
	if ( function_exists( 'is_ic_shipping_enabled' ) ) {
		ic_framework_require_once( __DIR__ . '/cart-shipping.php' );
	}
}
