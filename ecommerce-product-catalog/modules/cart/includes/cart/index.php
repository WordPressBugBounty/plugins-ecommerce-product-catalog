<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages quote cart includes folder
 *
 * Here includes folder files defined and managed.
 *
 * @version        1.0.0
 * @package        implecode-quote-cart/includes
 * @author        Norbert Dreszer
 */
require_once __DIR__ . '/cart-conditionals.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ic_cached_cart.php';
require_once __DIR__ . '/ic-cart.php';
require_once __DIR__ . '/cart-info.php';
require_once __DIR__ . '/ic-cart-ajax.php';
if ( function_exists( 'is_ic_shipping_enabled' ) ) {
	require_once __DIR__ . '/cart-shipping.php';
}
