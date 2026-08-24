<?php
/**
 * Server-side registry of searchable selector sources.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'IC_Selector_Registry' ) ) {

	/**
	 * Holds every selector source the site exposes to the search endpoint.
	 *
	 * The client only ever sends a registry KEY. The post type, the taxonomy,
	 * the post status and above all the capability are owned by the server, so
	 * the endpoint can never be talked into listing an arbitrary post type or
	 * enumerating users.
	 */
	class IC_Selector_Registry {

		/**
		 * Singleton instance.
		 *
		 * @var IC_Selector_Registry|null
		 */
		private static $instance = null;

		/**
		 * Registered sources keyed by source key.
		 *
		 * @var array
		 */
		private $sources = array();

		/**
		 * Returns the shared registry instance.
		 *
		 * @return IC_Selector_Registry
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Returns the argument defaults a source is normalized against.
		 *
		 * @return array
		 */
		public static function defaults() {
			return array(
				'type'           => 'post',
				'subtypes'       => array(),
				'post_status'    => array( 'publish' ),
				'capability'     => '',
				'search'         => array( 'title', 'id' ),
				'per_page'       => 20,
				'label'          => '',
				'placeholder'    => '',
				'label_callback' => '',
				'orderby'        => 'title',
				'order'          => 'ASC',
			);
		}

		/**
		 * Registers or replaces a selector source.
		 *
		 * A source with no capability is refused: an endpoint without an
		 * authorisation rule is a data leak, not a convenience.
		 *
		 * @param string $key  Source key sent by the client.
		 * @param array  $args Source definition.
		 *
		 * @return bool True when the source was stored.
		 */
		public function register( $key, $args ) {
			$key = sanitize_key( $key );
			if ( '' === $key || ! is_array( $args ) ) {
				return false;
			}

			$args = wp_parse_args( $args, self::defaults() );

			/**
			 * Filters a selector source definition before it is stored.
			 *
			 * @param array  $args Source definition.
			 * @param string $key  Source key.
			 */
			$args = apply_filters( 'ic_selector_source_args', $args, $key );

			$args['type'] = in_array( $args['type'], array( 'post', 'term', 'user' ), true ) ? $args['type'] : 'post';

			if ( ! is_string( $args['capability'] ) || '' === $args['capability'] ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: selector source key. */
						esc_html__( 'The selector source "%s" was refused because it declares no capability.', 'ecommerce-product-catalog' ),
						esc_html( $key )
					),
					'1.0'
				);

				return false;
			}

			$args['per_page'] = self::normalize_per_page( $args['per_page'] );
			$args['search']   = self::normalize_search( $args['search'] );
			$args['key']      = $key;

			$this->sources[ $key ] = $args;

			return true;
		}

		/**
		 * Returns a registered source.
		 *
		 * @param string $key Source key.
		 *
		 * @return array|false Source definition, or false when unknown.
		 */
		public function get( $key ) {
			$key = sanitize_key( $key );
			if ( '' === $key || ! isset( $this->sources[ $key ] ) ) {
				return false;
			}

			return $this->sources[ $key ];
		}

		/**
		 * Tells whether a source key is registered.
		 *
		 * @param string $key Source key.
		 *
		 * @return bool
		 */
		public function has( $key ) {
			return false !== $this->get( $key );
		}

		/**
		 * Returns every registered source.
		 *
		 * @return array
		 */
		public function all() {
			return $this->sources;
		}

		/**
		 * Removes a registered source.
		 *
		 * @param string $key Source key.
		 *
		 * @return void
		 */
		public function remove( $key ) {
			$key = sanitize_key( $key );
			unset( $this->sources[ $key ] );
		}

		/**
		 * Clamps a page size to the supported range.
		 *
		 * @param mixed $per_page Requested page size.
		 *
		 * @return int
		 */
		public static function normalize_per_page( $per_page ) {
			$per_page = absint( $per_page );
			if ( $per_page < 1 ) {
				$per_page = 20;
			}

			return min( $per_page, self::max_per_page() );
		}

		/**
		 * Returns the hard page-size cap.
		 *
		 * @return int
		 */
		public static function max_per_page() {
			return 100;
		}

		/**
		 * Normalizes the search definition into fields plus meta keys.
		 *
		 * Accepts `array( 'title', 'id', 'meta' => array( '_sku' ) )`.
		 *
		 * @param mixed $search Search definition.
		 *
		 * @return array
		 */
		public static function normalize_search( $search ) {
			$fields = array();
			$meta   = array();

			if ( is_string( $search ) ) {
				$search = array( $search );
			}
			if ( ! is_array( $search ) ) {
				$search = array( 'title', 'id' );
			}

			foreach ( $search as $index => $value ) {
				if ( 'meta' === $index ) {
					$meta = array_merge( $meta, (array) $value );
					continue;
				}
				if ( is_string( $value ) ) {
					$fields[] = sanitize_key( $value );
				}
			}

			$meta = array_values(
				array_filter(
					array_map( 'sanitize_key', $meta )
				)
			);

			if ( empty( $fields ) ) {
				$fields = array( 'title' );
			}

			return array(
				'fields' => array_values( array_unique( $fields ) ),
				'meta'   => array_unique( $meta ),
			);
		}

		/**
		 * Resolves a source argument that may be a callable returning a list.
		 *
		 * @param mixed $value    Registered value.
		 * @param array $fallback Value used when nothing usable is produced.
		 *
		 * @return array
		 */
		public static function resolve_list( $value, $fallback = array() ) {
			if ( is_string( $value ) && function_exists( $value ) ) {
				$value = call_user_func( $value );
			} elseif ( $value instanceof Closure || ( is_array( $value ) && is_callable( $value ) ) ) {
				$value = call_user_func( $value );
			}

			if ( is_string( $value ) && '' !== $value ) {
				$value = array( $value );
			}

			if ( ! is_array( $value ) || empty( $value ) ) {
				return $fallback;
			}

			return array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
		}
	}
}
