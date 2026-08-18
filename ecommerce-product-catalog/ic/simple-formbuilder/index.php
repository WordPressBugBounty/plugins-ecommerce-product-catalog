<?php
/**
 * Simple form builder bootstrap.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'IC_SIMPLE_FORMBUILDER_URL' ) ) {
	define( 'IC_SIMPLE_FORMBUILDER_URL', plugins_url( '/', __FILE__ ) );
	define( 'IC_SIMPLE_FORMBUILDER_PATH', __DIR__ );
}

if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( IC_SIMPLE_FORMBUILDER_PATH . '/pluggable.php' );
	ic_framework_require_once( IC_SIMPLE_FORMBUILDER_PATH . '/class-ic-simple-form.php' );
	ic_framework_require_once( IC_SIMPLE_FORMBUILDER_PATH . '/class-ic-simple-form-legacy.php' );
} else {
	require_once IC_SIMPLE_FORMBUILDER_PATH . '/pluggable.php';
	require_once IC_SIMPLE_FORMBUILDER_PATH . '/class-ic-simple-form.php';
	require_once IC_SIMPLE_FORMBUILDER_PATH . '/class-ic-simple-form-legacy.php';
}

if ( defined( 'IC_SIMPLE_FORMBUILDER_ENABLE_LEGACY_COMPAT' ) && IC_SIMPLE_FORMBUILDER_ENABLE_LEGACY_COMPAT ) {
	if ( function_exists( 'ic_framework_require_once' ) ) {
		ic_framework_require_once( IC_SIMPLE_FORMBUILDER_PATH . '/compat.php' );
	} else {
		require_once IC_SIMPLE_FORMBUILDER_PATH . '/compat.php';
	}
}

// This runtime is frequently required LAZILY: Store Locations loads it from inside the
// [locations_contact] shortcode, i.e. during the_content, long after init has fired. In that
// case an init hook alone never runs, the handles are never registered, and the
// wp_enqueue_style()/wp_enqueue_script() calls in ic_enqueue_simple_form_css() below refer to
// unknown handles that WordPress silently drops - shipping the form with no CSS and no JS.
// ic_sl_register_form_css() is declared unconditionally at file scope, so it is hoisted and
// safe to call here, above its own definition.
if ( did_action( 'init' ) ) {
	ic_sl_register_form_css();
} else {
	add_action( 'init', 'ic_sl_register_form_css', 10 );
}

/**
 * Register simple form builder assets.
 *
 * @return void
 */
function ic_sl_register_form_css() {
	$form_css_version = file_exists( IC_SIMPLE_FORMBUILDER_PATH . '/ic-form.css' ) ? (string) filemtime( IC_SIMPLE_FORMBUILDER_PATH . '/ic-form.css' ) : false;
	$form_js_version  = file_exists( IC_SIMPLE_FORMBUILDER_PATH . '/ic-form.js' ) ? (string) filemtime( IC_SIMPLE_FORMBUILDER_PATH . '/ic-form.js' ) : false;

	wp_register_style( 'implecode-simple-form-css', IC_SIMPLE_FORMBUILDER_URL . 'ic-form.css', array( 'ic-html' ), $form_css_version );
	wp_register_script( 'implecode-simple-form-js', IC_SIMPLE_FORMBUILDER_URL . 'ic-form.js', array( 'jquery' ), $form_js_version, true );
}

/**
 * Enqueue simple form builder assets.
 *
 * @return void
 */
function ic_enqueue_simple_form_css() {
	if ( ! defined( 'IC_FORMBUILDER_URL' ) ) {
		wp_enqueue_style( 'ic-html' );
		wp_enqueue_style( 'implecode-simple-form-css' );
		wp_enqueue_script( 'implecode-simple-form-js' );
	}
}
