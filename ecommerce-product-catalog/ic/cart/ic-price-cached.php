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

class ic_price_cached {
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		if ( apply_filters( 'ic_pre_cache_price', false ) ) {
			add_filter( 'ic_price_container_class', array( $this, 'price_container_class' ) );
			add_filter( 'ic_price_container_attr', array( $this, 'price_container_attr' ), 10, 2 );
			add_filter( 'ic_pre_cached_parts', array( $this, 'get_part' ), 10, 2 );
		}
	}

	function price_container_class( $class ) {
		if ( ic_is_cached_content() ) {
			$class .= ' ' . ic_pre_cached_class();
		}

		return $class;
	}

	function price_container_attr( $attr, $product_id ) {
		if ( ic_is_cached_content() ) {
			$attr .= ' data-product_id="' . esc_attr( $product_id ) . '"  data-args="' . esc_attr( json_encode( [ $product_id ] ) ) . '" data-action="ic_price_table_get"';
		}

		return $attr;
	}


	function get_part( $parts, $args = array() ) {
		if ( ! isset( $args['ic_price_table_get'] ) ) {
			return $parts;
		}
		$this_args = $args['ic_price_table_get'];
		$first_arg = reset( $this_args );
		if ( is_array( $first_arg ) ) {
			foreach ( $this_args as $product_id => $args ) {
				$parts['ic_price_table_get'][ $product_id ] = ic_price_display::get_product_price_table( $product_id );
			}
		} else {
			$parts['ic_price_table_get'] = ic_price_display::get_product_price_table( $this_args );
		}

		return $parts;
	}
}

new ic_price_cached;