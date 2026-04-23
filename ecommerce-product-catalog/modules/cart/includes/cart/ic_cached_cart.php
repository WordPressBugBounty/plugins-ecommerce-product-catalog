<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
 *
 *  @version       1.0.0
 *  @author        impleCode
 *
 */

class ic_cached_cart {

	/**
	 * @var string
	 */
	private $transient_name = 'ic_cached_cart';

	function __construct() {
		add_action( 'admin_init', array( $this, 'detect_cached_cart' ) );
		foreach ( $this->clearing_hooks() as $hook ) {
			add_action( $hook, array( $this, 'clear_transient' ) );
		}
		add_filter( 'body_class', array( $this, 'cache_class' ) );
		add_filter( 'ic_pre_cached_class', array( $this, 'pre_cached' ) );
	}

	function clearing_hooks() {
		return array(
			'switch_theme',
			'activate_plugin',
			'deactivate_plugin',
			'upgrader_process_complete',
			'update_option_permalink_structure',
		);
	}

	function pre_cached( $class ) {
		if ( $class === 'ic-cached' ) {
			return $class;
		}
		$cached_cart = get_transient( $this->transient_name );
		if ( isset( $cached_cart[1] ) && $cached_cart[1] ) {
			$class = 'ic-cached';
		}

		return $class;
	}

	function cache_class( $classes ) {
		if ( defined( 'WP_CACHE' ) && WP_CACHE && $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			$classes[] = 'ic_cache';
		} else {
			$cached_cart = get_transient( $this->transient_name );
			if ( isset( $cached_cart[1] ) && $cached_cart[1] ) {
				$classes[] = 'ic_cache';
			}
		}

		return $classes;
	}

	function detect_cached_cart() {
		// if ( ! filter_var( ini_get( 'allow_url_fopen' ), FILTER_VALIDATE_BOOLEAN ) ) {
		// return;
		// }
		$cart_url = ic_shopping_cart_page_url();
		if ( empty( $cart_url ) || ! wp_http_validate_url( $cart_url ) ) {
			return;
		}
		$cached_cart = get_transient( $this->transient_name );
		if ( ! isset( $cached_cart[0] ) || $cached_cart[0] !== $cart_url ) {
			$header        = $this->get_headers( $cart_url );
			$cache_found   = false;
			$cache_strings = $this->cache_strings();
			foreach ( $cache_strings as $cache_string ) {
				if ( ic_string_contains( $header, $cache_string, false, false ) ) {
					$cache_found = true;
					break;
				}
			}
			set_transient(
				$this->transient_name,
				array(
					$cart_url,
					intval( $cache_found ),
				),
				DAY_IN_SECONDS
			);
		}
	}

	function get_headers( $url ) {
		$args = array(
			'timeout'     => 10,
			'redirection' => 5,
			'sslverify'   => true,
			'headers'     => array(
				'User-Agent'      => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.9',
			),
		);

		$response = wp_remote_head( $url, $args );

		// Some stacks return incomplete headers or block HEAD; fallback to GET.
		if ( is_wp_error( $response ) ) {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$headers = wp_remote_retrieve_headers( $response );
		if ( empty( $headers ) ) {
			return '';
		}

		$lines = array();

		// Status line (optional but handy for debugging)
		$code    = intval( wp_remote_retrieve_response_code( $response ) );
		$message = strval( wp_remote_retrieve_response_message( $response ) );
		if ( $code ) {
			$lines[] = 'HTTP ' . $code . ( $message !== '' ? ' ' . $message : '' );
		}

		// Convert WP_Http_Headers / array into "Header: value" lines
		foreach ( $headers as $name => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					$lines[] = $name . ': ' . $v;
				}
			} else {
				$lines[] = $name . ': ' . $value;
			}
		}

		return implode( "\n", $lines );
	}

	function cache_strings() {
		return array(
			'x-speedycache-source',
			'X-Proxy-Cache',
			'CDN-Cache-Control',
			'Cache-Tag',
			'cloudflare',
			'varnish',
			'proxy',
			'X-Cache',
			'Age:',
			'Vary: X-Forwarded-Proto',
			'P-LB',
			'Cache-Control',
			'Cache',
		);
	}

	function clear_transient() {
		delete_transient( $this->transient_name );
	}
}
