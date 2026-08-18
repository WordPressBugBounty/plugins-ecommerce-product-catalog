<?php
/**
 * Asset registration utilities.
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

add_action( 'init', 'ic_register_styles_scripts' );

/**
 * Registers shared framework styles and scripts.
 */
function ic_register_styles_scripts() {
	$path_root = __DIR__;
	$url_root  = plugins_url( '/', __FILE__ );
	wp_register_style( 'jquery-ui-css', plugins_url( '/customers/css/jquery-ui.css', __FILE__ ), array(), file_exists( $path_root . '/customers/css/jquery-ui.css' ) ? (string) filemtime( $path_root . '/customers/css/jquery-ui.css' ) : false );
	wp_register_style( 'ic-selectors', $url_root . 'css/ic-selectors.min.css', array(), file_exists( $path_root . '/css/ic-selectors.min.css' ) ? (string) filemtime( $path_root . '/css/ic-selectors.min.css' ) : false );
	wp_register_style( 'ic-html', $url_root . 'css/ic-html.min.css', array(), file_exists( $path_root . '/css/ic-html.min.css' ) ? (string) filemtime( $path_root . '/css/ic-html.min.css' ) : false );
	wp_register_style( 'ic-user-login', $url_root . 'css/ic-user-login.min.css', array( 'dashicons', 'jquery-ui-css' ), file_exists( $path_root . '/css/ic-user-login.min.css' ) ? (string) filemtime( $path_root . '/css/ic-user-login.min.css' ) : false );
	wp_register_style( 'ic-user-panel', $url_root . 'css/ic-user-panel.min.css', array( 'dashicons', 'jquery-ui-css' ), file_exists( $path_root . '/css/ic-user-panel.min.css' ) ? (string) filemtime( $path_root . '/css/ic-user-panel.min.css' ) : false );
	wp_register_style( 'ic-chosen-general', $url_root . 'ext/chosen/chosen.min.css', array(), file_exists( $path_root . '/ext/chosen/chosen.min.css' ) ? (string) filemtime( $path_root . '/ext/chosen/chosen.min.css' ) : false );

	wp_register_script( 'ic-hooks', $url_root . 'js/ic-hooks.min.js', array( 'jquery' ), file_exists( $path_root . '/js/ic-hooks.min.js' ) ? (string) filemtime( $path_root . '/js/ic-hooks.min.js' ) : false, false );
	wp_register_script(
		'ic-html',
		$url_root . 'js/ic-html.min.js',
		array(
			'jquery',
			'ic-hooks',
		),
		file_exists( $path_root . '/js/ic-html.min.js' ) ? (string) filemtime( $path_root . '/js/ic-html.min.js' ) : false,
		false
	);
	wp_register_script( 'ic-selectors', $url_root . 'js/ic-selectors.min.js', array( 'ic-hooks' ), file_exists( $path_root . '/js/ic-selectors.min.js' ) ? (string) filemtime( $path_root . '/js/ic-selectors.min.js' ) : false, false );
	wp_register_script( 'ic-user-login', $url_root . 'js/ic-user-login.min.js', array( 'jquery', 'jquery-effects-slide', 'jquery-ui-tabs', 'ic-hooks' ), file_exists( $path_root . '/js/ic-user-login.min.js' ) ? (string) filemtime( $path_root . '/js/ic-user-login.min.js' ) : false, false );
	wp_register_script( 'ic-user-panel', plugins_url( '/customers/js/customer-panel.min.js', __FILE__ ), array( 'jquery', 'jquery-ui-tabs', 'wp-api-fetch' ), file_exists( $path_root . '/customers/js/customer-panel.min.js' ) ? (string) filemtime( $path_root . '/customers/js/customer-panel.min.js' ) : false, false );
	wp_register_script( 'ic-chosen-general', $url_root . 'ext/chosen/chosen.jquery.min.js', array( 'jquery' ), file_exists( $path_root . '/ext/chosen/chosen.jquery.min.js' ) ? (string) filemtime( $path_root . '/ext/chosen/chosen.jquery.min.js' ) : false, false );
	wp_register_script( 'ic-chosen', $url_root . 'js/ic-chosen.min.js', array( 'ic-chosen-general', 'ic-hooks' ), file_exists( $path_root . '/js/ic-chosen.min.js' ) ? (string) filemtime( $path_root . '/js/ic-chosen.min.js' ) : false, false );
	wp_register_script(
		'ic-screen-api',
		$url_root . 'js/ic-screen-api.min.js',
		array(
			'jquery',
			'wp-api-fetch',
			'ic-hooks',
		),
		file_exists( $path_root . '/js/ic-screen-api.min.js' ) ? (string) filemtime( $path_root . '/js/ic-screen-api.min.js' ) : false,
		false
	);
}

/**
 * Enqueues the shared Chosen assets.
 */
function ic_chosen_init() {
	wp_enqueue_script( 'ic-chosen' );
	wp_enqueue_style( 'ic-chosen-general' );
}

add_action( 'wp_enqueue_scripts', 'ic_localize_scripts' );
add_action( 'admin_enqueue_scripts', 'ic_localize_scripts' );

/**
 * Localizes shared framework script data.
 */
function ic_localize_scripts() {
	wp_localize_script(
		'ic-selectors',
		'ic_selector_vars',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		)
	);
	wp_localize_script(
		'ic-screen-api',
		'ic_screen_api',
		array(
			'nonce' => wp_create_nonce( 'wp_rest' ),
		)
	);
}
