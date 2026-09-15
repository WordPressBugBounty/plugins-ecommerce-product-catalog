<?php
/**
 * Shared sold product status runtime.
 *
 * @package ic/status
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'ic_sold_product_status' ) ) {
	/**
	 * Registers and renders the shared sold product status.
	 */
	class ic_sold_product_status {
		/**
		 * Shared singleton instance.
		 *
		 * @var self|null
		 */
		protected static $instance = null;

		/**
		 * Runtime arguments.
		 *
		 * @var array
		 */
		protected $args = array();

		/**
		 * Returns the shared singleton instance.
		 *
		 * @param array $args Optional runtime configuration.
		 *
		 * @return self
		 */
		public static function instance( $args = array() ) {
			if ( null === self::$instance ) {
				self::$instance = new self( $args );
			} elseif ( ! empty( $args ) ) {
				self::$instance->configure( $args );
			}

			return self::$instance;
		}

		/**
		 * Sets up the sold status hooks.
		 *
		 * @param array $args Optional runtime configuration.
		 */
		public function __construct( $args = array() ) {
			if ( null !== self::$instance ) {
				return;
			}

			$this->args = array(
				'sold_label'         => __( 'Sold', 'ecommerce-product-catalog' ),
				/* translators: %s: number of sold products. */
				'sold_label_count'   => _n_noop( 'Sold <span class="count">(%s)</span>', 'Sold <span class="count">(%s)</span>', 'ecommerce-product-catalog' ),
				/* translators: %s: singular catalog item label. */
				'unavailable_text'   => __( 'This %s is currently not available.', 'ecommerce-product-catalog' ),
				'add_visible_status' => false,
			);

			add_action( 'init', array( $this, 'register_status' ) );
			add_action( 'post_submitbox_misc_actions', array( $this, 'append_status_list' ) );
			add_action( 'admin_footer-edit.php', array( $this, 'append_archive_status_list' ) );
			add_filter( 'display_post_states', array( $this, 'display_sold_state' ) );
			add_action( 'single_product_begin', array( $this, 'disable_add_to_cart_button_for_sold' ) );
			add_action( 'product_details', array( $this, 'front_sold_info' ), 10, 1 );

			$this->configure( $args );
		}

		/**
		 * Merges plugin runtime configuration into the shared instance.
		 *
		 * @param array $args Optional runtime configuration.
		 *
		 * @return void
		 */
		protected function configure( $args ) {
			$args = wp_parse_args( $args, array() );

			if ( isset( $args['sold_label'] ) ) {
				$this->args['sold_label'] = (string) $args['sold_label'];
			}
			if ( isset( $args['sold_label_count'] ) ) {
				$this->args['sold_label_count'] = (array) $args['sold_label_count'];
			}
			if ( isset( $args['unavailable_text'] ) ) {
				$this->args['unavailable_text'] = (string) $args['unavailable_text'];
			}
			if ( ! empty( $args['add_visible_status'] ) ) {
				$this->args['add_visible_status'] = true;
				if ( false === has_filter( 'ic_visible_product_status', array( $this, 'add_sold_status_as_visible' ) ) ) {
					add_filter( 'ic_visible_product_status', array( $this, 'add_sold_status_as_visible' ) );
				}
			}
		}

		/**
		 * Returns the sold label.
		 *
		 * @return string
		 */
		protected function sold_label() {
			return (string) $this->args['sold_label'];
		}

		/**
		 * Returns the sold label-count pair.
		 *
		 * @return array
		 */
		protected function sold_label_count() {
			return (array) $this->args['sold_label_count'];
		}

		/**
		 * Returns the unavailable text format.
		 *
		 * @return string
		 */
		protected function unavailable_text() {
			return (string) $this->args['unavailable_text'];
		}

		/**
		 * Checks if the given post is a product post.
		 *
		 * @param WP_Post|object|null $post Candidate post.
		 *
		 * @return bool
		 */
		protected function is_product_post( $post ) {
			if ( empty( $post->post_type ) || ! function_exists( 'product_post_type_array' ) ) {
				return false;
			}

			return in_array( $post->post_type, product_post_type_array(), true );
		}

		/**
		 * Registers the sold post status.
		 *
		 * @return void
		 */
		public function register_status() {
			register_post_status(
				'sold',
				array(
					'label'                     => $this->sold_label(),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => $this->sold_label_count(),
				)
			);
		}

		/**
		 * Adds the sold status to the admin publish-box status dropdown.
		 *
		 * @return void
		 */
		public function append_status_list() {
			global $post;

			if ( ! $this->is_product_post( $post ) ) {
				return;
			}

			$is_selected = ! empty( $post->post_status ) && 'sold' === $post->post_status;
			$script      = 'jQuery(function($){';
			$script     .= 'var $status=$("select#post_status,.inline-edit-status select[name=\"_status\"]");';
			$script     .= 'if(!$status.find("option[value=\"sold\"]").length){$status.append($("<option>",{value:"sold",text:' . wp_json_encode( $this->sold_label() ) . ',selected:' . ( $is_selected ? 'true' : 'false' ) . '}));}';

			if ( $is_selected ) {
				$label_html = '<span id="post-status-display"> ' . $this->sold_label() . '</span>';
				$script    .= '$(".misc-pub-section label").append(' . wp_json_encode( $label_html ) . ');';
			}

			$script .= '});';

			$this->print_inline_script( $script );
		}

		/**
		 * Adds the sold status to quick-edit and bulk-edit dropdowns.
		 *
		 * @return void
		 */
		public function append_archive_status_list() {
			global $post;

			if ( ! $this->is_product_post( $post ) ) {
				return;
			}

			$script  = 'jQuery(function($){';
			$script .= 'var $status=$("select#post_status,.inline-edit-status select[name=\"_status\"]");';
			$script .= 'if(!$status.find("option[value=\"sold\"]").length){$status.append($("<option>",{value:"sold",text:' . wp_json_encode( $this->sold_label() ) . '}));}';
			$script .= '});';

			$this->print_inline_script( $script );
		}

		/**
		 * Prints an inline admin script when WordPress can do it safely.
		 *
		 * wp_print_inline_script_tag() only exists since WordPress 5.7. The plugin
		 * requires 5.9, so older installs simply skip the enhancement instead of
		 * fataling on an undefined function.
		 *
		 * @param string $script Inline script body.
		 *
		 * @return void
		 */
		private function print_inline_script( $script ) {
			if ( ! function_exists( 'wp_print_inline_script_tag' ) ) {
				return;
			}

			wp_print_inline_script_tag( $script );
		}

		/**
		 * Adds the sold badge on the admin products table.
		 *
		 * @param array $states Current post states.
		 *
		 * @return array
		 */
		public function display_sold_state( $states ) {
			global $post;

			$arg = get_query_var( 'post_status' );
			if ( 'sold' !== $arg && ! empty( $post->post_status ) && 'sold' === $post->post_status ) {
				return array( $this->sold_label() );
			}

			return $states;
		}

		/**
		 * Removes quote/cart buttons from sold products.
		 *
		 * @return void
		 */
		public function disable_add_to_cart_button_for_sold() {
			global $post;

			if ( empty( $post->post_status ) || 'sold' !== $post->post_status ) {
				return;
			}

			remove_action( 'product_details', 'quote_cart_button', 10 );
			remove_action( 'price_table', 'ic_cart_add_button', 10 );
			remove_action( 'product_details', 'availability_form_top', 15 );
			remove_action( 'single_product_end', 'availability_form_bottom' );
			remove_action( 'single_product_end', 'bottom_quote_button' );
			remove_action( 'product_details', 'quote_button' );
		}

		/**
		 * Outputs the sold notice on product pages.
		 *
		 * @param WP_Post|object $post Product post.
		 *
		 * @return void
		 */
		public function front_sold_info( $post ) {
			if ( empty( $post->post_status ) || 'sold' !== $post->post_status ) {
				return;
			}

			$names = get_catalog_names();
			/* translators: %s: singular product label. */
			echo '<p class="item-sold-info">' . sprintf( esc_html( $this->unavailable_text() ), esc_html( strtolower( $names['singular'] ) ) ) . '</p>';
		}

		/**
		 * Adds sold to the visible product statuses.
		 *
		 * @param array $status Current visible product statuses.
		 *
		 * @return array
		 */
		public function add_sold_status_as_visible( $status ) {
			if ( is_array( $status ) && ! in_array( 'sold', $status, true ) ) {
				$status[] = 'sold';
			}

			return $status;
		}
	}
}
