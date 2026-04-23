<?php
/**
 * Framework global cache wrapper.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Wraps impleCode cache helper behavior.
 */
class IC_Global_Cache {
	/**
	 * Returns a cached value.
	 *
	 * @param string|null $name        Cache key.
	 * @param bool        $hard_cached Whether to use persistent object cache.
	 * @param mixed       $default     Default value.
	 *
	 * @return mixed
	 */
	public function get( $name = null, $hard_cached = false ) {
		global $implecode;

		if ( ! empty( $name ) ) {
			if ( isset( $implecode[ $name ] ) ) {
				return $implecode[ $name ];
			} else {
				if ( $hard_cached ) {
					$cached_value = wp_cache_get( $name, 'implecode' );
					if ( false !== $cached_value ) {
						return $cached_value;
					}
				}
				$fallback = apply_filters( 'ic_get_global', false, $name, $hard_cached );
				if ( false !== $fallback ) {
					if ( $hard_cached ) {
						wp_cache_set( $name, $fallback, 'implecode' );
					}

					return $fallback;
				}

				return false;
			}
		}

		return $implecode;
	}

	/**
	 * Stores a cached value.
	 *
	 * @param string $name Cache key.
	 * @param mixed  $value Cache value.
	 * @param bool   $hard_cache Whether to save to persistent object cache.
	 * @param bool   $admin Whether to allow admin-context writes.
	 *
	 * @return bool
	 */
	public function set( $name, $value, $hard_cache = false, $admin = false ) {
		if ( ! $admin && is_ic_admin() && ! ic_is_rendering_block() ) {
			return false;
		}
		global $implecode;
		if ( ! empty( $name ) ) {
			if ( $hard_cache ) {
				wp_cache_set( $name, $value, 'implecode' );
			}
			if ( null === $value ) {
				$value = '';
			}
			$implecode[ $name ] = $value;
			do_action( 'ic_save_cache', $name, $value, $hard_cache );

			return true;
		}

		return false;
	}

	/**
	 * Deletes a cached value.
	 *
	 * @param string|null $name Cache key.
	 *
	 * @return void
	 */
	public function delete( $name = null ) {
		global $implecode;

		if ( ! empty( $name ) ) {
			do_action( 'ic_delete_global', $name );
			wp_cache_delete( $name, 'implecode' );
			unset( $implecode[ $name ] );

			return;
		}

		wp_cache_flush_group( 'implecode' );
		unset( $implecode );
	}
}
