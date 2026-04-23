<?php
/**
 * Framework cache helpers.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( function_exists( 'ic_framework_require_once' ) ) {
	ic_framework_require_once( __DIR__ . '/cache/index.php' );
} else {
	require_once __DIR__ . '/cache/index.php';
}

if ( ! function_exists( 'ic_global_cache' ) ) {
	/**
	 * Returns the framework global cache helper singleton.
	 *
	 * @return IC_Global_Cache
	 */
	function ic_global_cache() {
		static $cache = null;

		if ( null === $cache ) {
			$cache = new IC_Global_Cache();
		}

		return $cache;
	}
}

if ( ! function_exists( 'ic_session_cache' ) ) {
	/**
	 * Returns the framework session cache helper singleton.
	 *
	 * @return IC_Session_Cache
	 */
	function ic_session_cache() {
		static $cache = null;

		if ( null === $cache ) {
			$cache = new IC_Session_Cache();
		}

		return $cache;
	}
}

if ( ! function_exists( 'ic_transient_cache' ) ) {
	/**
	 * Returns the framework transient cache helper singleton.
	 *
	 * @return IC_Transient_Cache
	 */
	function ic_transient_cache() {
		static $cache = null;

		if ( null === $cache ) {
			$cache = new IC_Transient_Cache();
		}

		return $cache;
	}
}

/*
 *
 *  @version       1.0.0
 *  @author        impleCode
 *
 */

if ( ! function_exists( 'ic_get_cache' ) ) {
	/**
	 * Return a cached framework value.
	 *
	 * @param string|null $name Cache key.
	 * @param bool        $hard_cached Whether to read from persistent cache.
	 *
	 * @return mixed
	 * @global array $implecode
	 */
	function ic_get_cache( $name = null, $hard_cached = false ) {
		return ic_global_cache()->get( $name, $hard_cached );
	}

}

if ( ! function_exists( 'ic_delete_cache' ) ) {

	/**
	 * Delete a cached framework value.
	 *
	 * @param string|null $name Cache key.
	 *
	 * @return void
	 * @global array $implecode
	 */
	function ic_delete_cache( $name = null ) {
		ic_global_cache()->delete( $name );
	}

}

if ( ! function_exists( 'ic_save_cache' ) ) {

	/**
	 * Save a cached framework value.
	 *
	 * @param string $name Cache key.
	 * @param mixed  $value Cache value.
	 * @param bool   $hard_cache Whether to save to persistent cache.
	 * @param bool   $admin Whether to allow saving in admin context.
	 *
	 * @return bool
	 * @global array $implecode
	 */
	function ic_save_cache( $name, $value, $hard_cache = false, $admin = false ) {
		return ic_global_cache()->set( $name, $value, $hard_cache, $admin );
	}

}
