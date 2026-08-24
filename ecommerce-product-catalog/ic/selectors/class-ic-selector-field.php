<?php
/**
 * Renderer for the shared AJAX selector control.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'IC_Selector_Field' ) ) {

	/**
	 * Renders a search-as-you-type control backed by a registered source.
	 *
	 * The markup is a real `<select>` wrapped in a container. With JavaScript
	 * disabled the user still gets a working, if capped, native dropdown; with
	 * JavaScript on, `selector.js` hides the native control and drives the same
	 * element from the search endpoint, so the submitted value is identical
	 * either way.
	 */
	class IC_Selector_Field {

		/**
		 * Cached fallback option sets, keyed by source and limit.
		 *
		 * @var array
		 */
		private static $fallback_cache = array();

		/**
		 * Returns the control markup.
		 *
		 * @param array $args Field arguments.
		 *
		 * @return string
		 */
		public static function get( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'source'         => '',
					'name'           => '',
					'id'             => '',
					'value'          => '',
					'multiple'       => false,
					'placeholder'    => '',
					'class'          => '',
					'first_option'   => '',
					'required'       => false,
					'disabled'       => false,
					'fallback_limit' => 0,
					'attributes'     => array(),
				)
			);

			$source = ic_selector_registry()->get( $args['source'] );
			if ( false === $source ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: selector source key. */
						esc_html__( 'Unknown selector source "%s".', 'ecommerce-product-catalog' ),
						esc_html( (string) $args['source'] )
					),
					'1.0'
				);

				return '';
			}

			if ( '' === $args['name'] ) {
				return '';
			}

			$args['multiple'] = ! empty( $args['multiple'] );
			$name             = $args['multiple'] && ! ic_string_ends_with_brackets( $args['name'] ) ? $args['name'] . '[]' : $args['name'];
			$field_id         = '' !== $args['id'] ? $args['id'] : sanitize_html_class( str_replace( array( '[', ']' ), array( '_', '' ), $args['name'] ) );
			$values           = self::normalize_values( $args['value'] );
			$placeholder      = '' !== $args['placeholder'] ? $args['placeholder'] : self::default_placeholder( $source );
			$enhanced         = current_user_can( $source['capability'] );

			$options = self::options( $source, $values, $args, $enhanced );

			$select_attributes = array(
				'name'  => $name,
				'id'    => $field_id,
				'class' => trim( 'ic-selector-native ' . $args['class'] ),
			);
			if ( $args['multiple'] ) {
				$select_attributes['multiple'] = 'multiple';
			}
			if ( ! empty( $args['required'] ) ) {
				$select_attributes['required'] = 'required';
			}
			if ( ! empty( $args['disabled'] ) ) {
				$select_attributes['disabled'] = 'disabled';
			}
			if ( is_array( $args['attributes'] ) ) {
				$select_attributes = array_merge( $select_attributes, $args['attributes'] );
			}

			$html  = '<div class="ic-selector' . ( $enhanced ? ' ic-selector-enhanced' : '' ) . '"';
			$html .= self::attributes( self::container_attributes( $source, $placeholder, $enhanced, $args ) );
			$html .= '>';
			$html .= '<select' . self::attributes( $select_attributes ) . '>';

			if ( ! $args['multiple'] ) {
				$first_option = '' !== $args['first_option'] ? $args['first_option'] : $placeholder;
				$html        .= '<option value="">' . esc_html( $first_option ) . '</option>';
			}

			foreach ( $options as $option ) {
				$selected = in_array( (int) $option['id'], $values, true ) ? ' selected="selected"' : '';
				$html    .= '<option value="' . esc_attr( $option['id'] ) . '"' . $selected . '>' . esc_html( $option['text'] ) . '</option>';
			}

			$html .= '</select>';
			$html .= '</div>';

			if ( $enhanced ) {
				ic_selector_enqueue_assets();
			}

			/**
			 * Filters the rendered selector control.
			 *
			 * @param string $html   Control markup.
			 * @param array  $args   Field arguments.
			 * @param array  $source Source definition.
			 */
			return apply_filters( 'ic_selector_field_html', $html, $args, $source );
		}

		/**
		 * Echoes the control markup.
		 *
		 * @param array $args Field arguments.
		 *
		 * @return void
		 */
		public static function render( $args = array() ) {
			echo wp_kses( self::get( $args ), self::allowed_html() );
		}

		/**
		 * Returns the `wp_kses` allowlist covering the control markup.
		 *
		 * @return array
		 */
		public static function allowed_html() {
			return array(
				'div'    => array(
					'class'            => true,
					'data-source'      => true,
					'data-nonce'       => true,
					'data-ajax-url'    => true,
					'data-per-page'    => true,
					'data-placeholder' => true,
					'data-multiple'    => true,
					'data-searching'   => true,
					'data-empty'       => true,
					'data-error'       => true,
					'data-more'        => true,
				),
				'select' => array(
					'name'     => true,
					'id'       => true,
					'class'    => true,
					'multiple' => true,
					'required' => true,
					'disabled' => true,
					'data-*'   => true,
					'custom'   => true,
					'style'    => true,
				),
				'option' => array(
					'value'    => true,
					'selected' => true,
					'class'    => true,
				),
			);
		}

		/**
		 * Builds the container data attributes the script reads.
		 *
		 * @param array  $source      Source definition.
		 * @param string $placeholder Placeholder text.
		 * @param bool   $enhanced    Whether the control may call the endpoint.
		 * @param array  $args        Field arguments.
		 *
		 * @return array
		 */
		private static function container_attributes( $source, $placeholder, $enhanced, $args ) {
			if ( ! $enhanced ) {
				return array();
			}

			return array(
				'data-source'      => $source['key'],
				'data-nonce'       => wp_create_nonce( IC_Selector_Ajax::NONCE_ACTION ),
				'data-ajax-url'    => admin_url( 'admin-ajax.php' ),
				'data-per-page'    => (string) $source['per_page'],
				'data-placeholder' => $placeholder,
				'data-multiple'    => $args['multiple'] ? '1' : '0',
				'data-searching'   => __( 'Searching...', 'ecommerce-product-catalog' ),
				'data-empty'       => __( 'No matches found.', 'ecommerce-product-catalog' ),
				'data-error'       => __( 'The list could not be loaded.', 'ecommerce-product-catalog' ),
				'data-more'        => __( 'Load more', 'ecommerce-product-catalog' ),
			);
		}

		/**
		 * Builds the `<option>` set: selected records first, then a capped page.
		 *
		 * @param array $source   Source definition.
		 * @param array $values   Selected IDs.
		 * @param array $args     Field arguments.
		 * @param bool  $enhanced Whether the current user holds the source capability.
		 *
		 * @return array
		 */
		private static function options( $source, $values, $args, $enhanced = true ) {
			$options = array();
			$seen    = array();

			if ( ! $enhanced ) {
				/*
				 * The endpoint answers this user with a 403, so the fallback must
				 * not answer with a listing: that would hand over exactly the
				 * records the capability withholds. Already-selected values are
				 * still emitted, by bare ID, so the surrounding form round-trips
				 * instead of silently clearing a value the user cannot see.
				 */
				foreach ( $values as $value ) {
					$options[] = array(
						'id'   => $value,
						'text' => '#' . $value,
					);
				}

				return $options;
			}

			if ( ! empty( $values ) ) {
				foreach ( IC_Selector_Ajax::query( $source, array( 'include' => $values ) )['results'] as $result ) {
					$options[]                   = $result;
					$seen[ (int) $result['id'] ] = true;
				}
			}

			$limit = absint( $args['fallback_limit'] );
			if ( $limit < 1 ) {
				/**
				 * Filters how many records the no-JS fallback lists.
				 *
				 * @param int   $limit  Option count.
				 * @param array $source Source definition.
				 */
				$limit = absint( apply_filters( 'ic_selector_fallback_limit', 50, $source ) );
			}

			if ( $limit > 0 ) {
				$cache_key = $source['key'] . ':' . $limit;
				if ( ! isset( self::$fallback_cache[ $cache_key ] ) ) {
					self::$fallback_cache[ $cache_key ] = IC_Selector_Ajax::query( $source, array( 'per_page' => $limit ) )['results'];
				}
				foreach ( self::$fallback_cache[ $cache_key ] as $result ) {
					if ( isset( $seen[ (int) $result['id'] ] ) ) {
						continue;
					}
					$options[]                   = $result;
					$seen[ (int) $result['id'] ] = true;
				}
			}

			return $options;
		}

		/**
		 * Casts a submitted value to a list of positive integers.
		 *
		 * @param mixed $value Raw value.
		 *
		 * @return array
		 */
		private static function normalize_values( $value ) {
			if ( '' === $value || null === $value || false === $value ) {
				return array();
			}

			return IC_Selector_Ajax::parse_ids( $value );
		}

		/**
		 * Returns the default placeholder for a source.
		 *
		 * @param array $source Source definition.
		 *
		 * @return string
		 */
		private static function default_placeholder( $source ) {
			if ( ! empty( $source['placeholder'] ) ) {
				return $source['placeholder'];
			}
			if ( ! empty( $source['label'] ) ) {
				/* translators: %s: selector source label. */
				return sprintf( __( 'Search %s', 'ecommerce-product-catalog' ), $source['label'] );
			}

			return __( 'Type to search', 'ecommerce-product-catalog' );
		}

		/**
		 * Renders an attribute map.
		 *
		 * @param array $attributes Attribute map.
		 *
		 * @return string
		 */
		private static function attributes( $attributes ) {
			$html = '';
			foreach ( $attributes as $key => $value ) {
				if ( null === $value || false === $value ) {
					continue;
				}
				$html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}

			return $html;
		}
	}
}
