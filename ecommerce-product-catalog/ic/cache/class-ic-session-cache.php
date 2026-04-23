<?php
/**
 * Framework session cache wrapper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Wraps the product catalog session helpers with keyed accessors.
 */
class IC_Session_Cache {
	/**
	 * Returns the full session payload.
	 *
	 * @return array
	 */
	public function all() {
		if ( ! function_exists( 'get_product_catalog_session' ) ) {
			return array();
		}

		$session = get_product_catalog_session();

		return is_array( $session ) ? $session : array();
	}

	/**
	 * Replaces the full session payload.
	 *
	 * @param array $session Session payload.
	 *
	 * @return void
	 */
	public function replace( $session ) {
		if ( ! is_array( $session ) || ! function_exists( 'set_product_catalog_session' ) ) {
			return;
		}

		set_product_catalog_session( $session );
	}

	/**
	 * Returns a cached session value.
	 *
	 * @param string $key     Session key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	public function get( $key, $default = false ) {
		$session = $this->all();
		if ( array_key_exists( $key, $session ) ) {
			return $session[ $key ];
		}

		return $default;
	}

	/**
	 * Stores a cached session value.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		$session         = $this->all();
		$session[ $key ] = $value;
		$this->replace( $session );
	}

	/**
	 * Deletes one cached session value.
	 *
	 * @param string $key Session key.
	 *
	 * @return void
	 */
	public function delete( $key ) {
		$session = $this->all();
		unset( $session[ $key ] );
		$this->replace( $session );
	}

	/**
	 * Returns whether a key exists in session.
	 *
	 * @param string $key Session key.
	 *
	 * @return bool
	 */
	public function has( $key ) {
		$session = $this->all();

		return array_key_exists( $key, $session );
	}

	/**
	 * Returns one grouped cache entry.
	 *
	 * @param string     $group   Group key.
	 * @param string|int $key     Group item key.
	 * @param mixed      $default Default value.
	 *
	 * @return mixed
	 */
	public function get_group( $group, $key = null, $default = null ) {
		$session = $this->all();
		if ( empty( $session[ $group ] ) || ! is_array( $session[ $group ] ) ) {
			return $default;
		}
		if ( null === $key ) {
			return $session[ $group ];
		}
		if ( array_key_exists( $key, $session[ $group ] ) ) {
			return $session[ $group ][ $key ];
		}

		return $default;
	}

	/**
	 * Stores one grouped cache entry.
	 *
	 * @param string     $group Group key.
	 * @param string|int $key   Group item key.
	 * @param mixed      $value Group item value.
	 *
	 * @return void
	 */
	public function set_group( $group, $key, $value ) {
		$session = $this->all();
		if ( empty( $session[ $group ] ) || ! is_array( $session[ $group ] ) ) {
			$session[ $group ] = array();
		}
		$session[ $group ][ $key ] = $value;
		$this->replace( $session );
	}

	/**
	 * Deletes one grouped cache entry or a whole group.
	 *
	 * @param string     $group Group key.
	 * @param string|int $key   Optional group item key.
	 *
	 * @return void
	 */
	public function delete_group( $group, $key = null ) {
		$session = $this->all();
		if ( null === $key ) {
			unset( $session[ $group ] );
		} elseif ( ! empty( $session[ $group ] ) && is_array( $session[ $group ] ) ) {
			unset( $session[ $group ][ $key ] );
			if ( empty( $session[ $group ] ) ) {
				unset( $session[ $group ] );
			}
		}
		$this->replace( $session );
	}
}
