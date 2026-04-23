<?php
/**
 * Catalog blocks bootstrap and render callbacks.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Catalog blocks manager.
 */
class IC_EPC_Blocks {

	/**
	 * Singular catalog label.
	 *
	 * @var string
	 */
	public $singular_name;

	/**
	 * Plural catalog label.
	 *
	 * @var string
	 */
	public $plural_name;

	/**
	 * Widget block helpers.
	 *
	 * @var IC_EPC_Widget_Blocks
	 */
	public $widget_blocks;

	/**
	 * Sets up the catalog block manager.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( function_exists( 'register_block_type' ) ) {
			require_once AL_BASE_PATH . '/includes/blocks/functions.php';
			require_once AL_BASE_PATH . '/includes/blocks/class-ic-epc-block-edit.php';
			require_once AL_BASE_PATH . '/includes/blocks/widgets.php';
			require_once AL_BASE_PATH . '/includes/blocks/context-blocks.php';
			require_once AL_BASE_PATH . '/includes/blocks/product-parts.php';

			$this->widget_blocks                 = new IC_EPC_Widget_Blocks();
			$this->widget_blocks->context_blocks = new IC_EPC_Context_Blocks();

			add_action( 'init', array( $this, 'register' ), 50 );
			add_action( 'admin_print_scripts', array( $this, 'js_global' ) );
			add_action( 'register_catalog_admin_styles', array( $this, 'register_js_framework' ) );
			add_action( 'register_catalog_styles', array( $this, 'register_css_framework' ) );
			add_action( 'enqueue_block_assets', array( $this, 'css_framework' ) );
			// Legacy admin enqueue hook kept disabled here.
			global $wp_version;
			if ( version_compare( $wp_version, 5.8 ) < 0 ) {
				add_filter( 'block_categories', array( $this, 'block_category' ), 10, 2 );
			} else {
				add_filter( 'block_categories_all', array( $this, 'block_category' ), 10, 2 );
			}
			add_filter( 'ic_catalog_default_listing_content', array( $this, 'auto_insert_block' ) );
			add_filter( 'ic_catalog_shortcode_name', array( $this, 'block_name' ) );
			add_filter( 'write_your_story', array( $this, 'description_placeholder' ), 10, 2 );
		}
	}

	/**
	 * Extends the editor placeholder for catalog products.
	 *
	 * @param string  $text Placeholder text.
	 * @param WP_Post $post Current post object.
	 * @return string
	 */
	public function description_placeholder( $text, $post ) {
		if ( isset( $post->post_type ) && ic_string_contains( $post->post_type, 'al_product' ) ) {
			/* translators: %s: singular catalog label. */
			$catalog_placeholder = sprintf( __( 'Enter %s description.', 'ecommerce-product-catalog' ), get_catalog_names( 'singular' ) );
			$text                = $catalog_placeholder . ' ' . $text;
		}

		return $text;
	}

	/**
	 * Provides the default catalog block markup.
	 *
	 * @param string $content Default listing content.
	 * @return string
	 */
	public function auto_insert_block( $content ) {
		// Keep the default shortcode markup as the auto-inserted block content.
		$content = '<!-- wp:shortcode -->
[show_product_catalog]
<!-- /wp:shortcode -->';

		return $content;
	}

	/**
	 * Returns the catalog block display name.
	 *
	 * @return string
	 */
	public function block_name() {
		/* translators: %s: block title. */
		return sprintf( __( '%s block', 'ecommerce-product-catalog' ), __( 'Show Catalog', 'ecommerce-product-catalog' ) );
	}

	/**
	 * Registers shared block scripts.
	 *
	 * @return void
	 */
	public function register_js_framework() {
		wp_register_script(
			'ic_blocks_framework',
			AL_PLUGIN_BASE_PATH . 'includes/blocks/js/framework.min.js' . ic_filemtime( AL_BASE_PATH . '/includes/blocks/js/framework.min.js' ),
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-server-side-render',
				'wp-data',
				'wp-compose',
			)
		);
	}

	/**
	 * Registers shared block styles.
	 *
	 * @return void
	 */
	public function register_css_framework() {
		wp_register_style( 'ic_blocks', AL_PLUGIN_BASE_PATH . 'includes/blocks/ic-blocks.min.css' . ic_filemtime( AL_BASE_PATH . '/includes/blocks/ic-blocks.min.css' ) );
	}

	/**
	 * Enqueues shared block scripts.
	 *
	 * @return void
	 */
	public function js_framework() {
		wp_enqueue_script( 'ic_blocks_framework' );
	}

	/**
	 * Enqueues shared block styles.
	 *
	 * @return void
	 */
	public function css_framework() {
		wp_enqueue_style( 'ic_blocks' );
	}

	/**
	 * Prints the localized block editor globals.
	 *
	 * @return void
	 */
	public function js_global() {
		$names               = get_catalog_names();
		$this->singular_name = $names['singular'];
		$this->plural_name   = $names['plural'];
		$js_global           = apply_filters(
			'ic_epc_blocks_localize',
			array(
				'strings'                  => array(
					'show_catalog'                => __( 'Show Catalog', 'ecommerce-product-catalog' ),
					/* translators: %s: plural catalog label. */
					'show_products'               => sprintf( __( 'Show %s', 'ecommerce-product-catalog' ), $this->plural_name ),
					'show_categories'             => __( 'Show Categories', 'ecommerce-product-catalog' ),
					'show'                        => __( 'Show', 'ecommerce-product-catalog' ),
					/* translators: %s: singular catalog label. */
					'select_products'             => sprintf( __( 'Select %s', 'ecommerce-product-catalog' ), $this->singular_name ),
					'select_categories'           => __( 'Select Categories', 'ecommerce-product-catalog' ),
					/* translators: %s: singular catalog label. */
					'select_limit'                => sprintf( __( 'Set %s Limit', 'ecommerce-product-catalog' ), $this->singular_name ),
					'select_orderby'              => __( 'Order by', 'ecommerce-product-catalog' ),
					'select_order'                => __( 'Order Type', 'ecommerce-product-catalog' ),
					'select_template'             => __( 'Listing Template', 'ecommerce-product-catalog' ),
					'select_perrow'               => __( 'Items Per Row', 'ecommerce-product-catalog' ),
					/* translators: %s: plural catalog label. */
					'choose_products'             => sprintf( __( 'Choose %s to Display', 'ecommerce-product-catalog' ), $this->plural_name ),
					'choose_categories'           => __( 'Choose Categories to Display', 'ecommerce-product-catalog' ),
					'by_category'                 => __( 'By Category', 'ecommerce-product-catalog' ),
					/* translators: %s: singular catalog label. */
					'by_product'                  => sprintf( __( 'By %s', 'ecommerce-product-catalog' ), $this->singular_name ),
					'sort_limit'                  => __( 'Sort & Limit', 'ecommerce-product-catalog' ),
					'loading'                     => __( 'Loading', 'ecommerce-product-catalog' ) . '...',
					'all'                         => __( 'All', 'ecommerce-product-catalog' ),
					'small'                       => __( 'Small', 'ecommerce-product-catalog' ),
					'medium'                      => __( 'Medium', 'ecommerce-product-catalog' ),
					'large'                       => __( 'Large', 'ecommerce-product-catalog' ),
					'extra_large'                 => __( 'Extra Large', 'ecommerce-product-catalog' ),
					'options'                     => __( 'Options', 'ecommerce-product-catalog' ),
					'disable_add_cart'            => __( 'Disable add to cart button', 'ecommerce-product-catalog' ),
					'search_product'              => __( 'Search Product', 'ecommerce-product-catalog' ),
					'select_product'              => __( 'Select Product', 'ecommerce-product-catalog' ),
					'search_placeholder'          => __( 'Search by item name and select it in the section below', 'ecommerce-product-catalog' ),
					'category_search_placeholder' => __( 'Search by category name and select it in the section below', 'ecommerce-product-catalog' ),
					'search_category'             => __( 'Search Category', 'ecommerce-product-catalog' ),
					'edit_block'                  => __( 'Edit block settings.', 'ecommerce-product-catalog' ),
					'get_product_from_context'    => __( 'Get product from context.', 'ecommerce-product-catalog' ),
					'context_enabled_help'        => __( 'Disable to select product.', 'ecommerce-product-catalog' ),
					'context_disabled_help'       => __( 'Enable to get the product from context (inside a loop or on a product page).', 'ecommerce-product-catalog' ),
				),
				// 'category_options'         => $this->categories(),
				// 'product_options'          => $this->products(),
				'orderby_options'          => $this->orderby(),
				'category_orderby_options' => $this->category_orderby(),
				'order_options'            => $this->order(),
				'template_options'         => $this->template(),
				'per_row_def'              => get_current_per_row(),
				'products_limit_def'       => ic_get_products_limit(),
				'archive_template_def'     => get_product_listing_template(),
				'context'                  => ic_blocks_context(),
			)
		);
		?>
		<script>
			var ic_blocks_js_global = <?php echo wp_json_encode( $js_global ); ?>;
		</script>
		<?php
	}

	/**
	 * Registers all catalog blocks.
	 *
	 * @return void
	 */
	public function register() {
		register_block_type(
			__DIR__ . '/show-catalog/',
			array(
				'title'           => __( 'Show Catalog', 'ecommerce-product-catalog' ),
				'render_callback' => array( $this, 'render_catalog' ),
			)
		);
		register_block_type(
			__DIR__ . '/show-products/',
			array(
				'title'           => __( 'Show Products', 'ecommerce-product-catalog' ),
				'render_callback' => array( $this, 'render_products' ),
			)
		);
		register_block_type(
			__DIR__ . '/show-categories/',
			array(
				'title'           => __( 'Show Categories', 'ecommerce-product-catalog' ),
				'render_callback' => array( $this, 'render_categories' ),
			)
		);
		register_block_type(
			__DIR__ . '/active-filters/',
			array(
				'title'           => __( 'Active Filters', 'ecommerce-product-catalog' ),
				'render_callback' => array( $this, 'render_active_filters' ),
			)
		);
		register_block_type(
			__DIR__ . '/product-page/',
			array(
				'title'           => __( 'Product Page', 'ecommerce-product-catalog' ),
				'render_callback' => array( $this, 'render_product_page' ),
			)
		);
		register_block_type(
			__DIR__ . '/product-category/',
			array(
				'title'           => __( 'Product Category', 'ecommerce-product-catalog' ),
				'render_callback' => array( $this, 'render_product_category' ),
			)
		);

		do_action( 'ic_register_blocks' );
	}

	/**
	 * Renders the catalog block.
	 *
	 * @param array|null $atts Block attributes.
	 * @return string
	 */
	public function render_catalog( $atts = null ) {
		global $ic_rendering_catalog_block;
		$ic_rendering_catalog_block = 1;
		$rendered                   = do_shortcode( '[show_product_catalog]' );
		if ( ! empty( $rendered ) ) {
			// $rendered = '<div class="ic-catalog-block-container alignwide">' . $rendered . '</div>';
			$rendered = '<div class="ic-catalog-block-container">' . $rendered . '</div>';
		}
		if ( empty( $rendered ) && ic_is_rendering_catalog_block() ) {
			if ( is_ic_product_listing_enabled() ) {
				$rendered = '<hr>';
				/* translators: 1: opening catalog settings link, 2: closing catalog settings link. */
				$rendered .= sprintf( __( 'There is nothing to display yet. Please add your products or %1$sconfigure the catalog%2$s to display something.', 'ecommmerce-product-catalog' ), '<a href="' . admin_url( 'edit.php?post_type=al_product&page=product-settings.php' ) . '">', '</a>' );
				$rendered .= '<hr>';
			} else {
				$rendered  = '<hr>';
				$rendered .= '<h3>' . __( 'Catalog Container', 'ecommerce-product-catalog' ) . '</h3>';
				/* translators: 1: opening catalog settings link, 2: closing catalog settings link. */
				$rendered .= sprintf( __( 'You have disabled the main listing page in %1$scatalog settings%2$s so this block will not display anything. It will only be used to output content on catalog categories and individual product pages.', 'ecommerce-product-catalog' ), '<a href="' . admin_url( 'edit.php?post_type=al_product&page=product-settings.php' ) . '">', '</a>' );
				$rendered .= '<hr>';
			}
		}
		$ic_rendering_catalog_block = 0;

		return $this->widget_blocks->context_blocks->container( $atts, $rendered, 'show-catalog' );
	}

	/**
	 * Renders the product page block.
	 *
	 * @param array|null    $atts          Block attributes.
	 * @param string|null   $block_content Block content.
	 * @param WP_Block|null $block        Parsed block object.
	 * @return string
	 */
	public function render_product_page( $atts = null, $block_content = null, $block = null ) {
		ob_start();
		if ( ! empty( $atts['selectedProduct'] ) ) {
			foreach ( $atts['selectedProduct'] as $product ) {
				if ( empty( $product['value'] ) ) {
					continue;
				}
				ic_set_product_id( intval( $product['value'] ), false, false, true );
				content_product_adder( 'is_catalog' );
				ic_reset_product_id();
			}
		} else {
			content_product_adder();
		}

		return $this->widget_blocks->context_blocks->container( $atts, ob_get_clean(), 'product-page' );
	}

	/**
	 * Renders the generic product listing container.
	 *
	 * @param array $atts Block attributes.
	 * @return string
	 */
	public function render_product_listing( $atts ) {
		ob_start();
		content_product_adder();

		return $this->widget_blocks->context_blocks->container( $atts, ob_get_clean(), 'product-listing' );
	}

	/**
	 * Renders the product category block.
	 *
	 * @param array $atts Block attributes.
	 * @return string
	 */
	public function render_product_category( $atts ) {
		ob_start();
		if ( ! empty( $atts['selectedCategory'] ) ) {
			foreach ( $atts['selectedCategory'] as $category ) {
				if ( empty( $category['value'] ) ) {
					continue;
				}
				global $wp_query;
				$pre_query = $wp_query;
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary query swap preserves existing block rendering behavior.
				$wp_query = new WP_Query(
					array(
						'post_type' => 'al_product',
						'tax_query' => array(
							array(
								'taxonomy' => 'al_product-cat',
								'field'    => 'term_id',
								'terms'    => $category['value'],
							),
						),
					)
				);
				content_product_adder( 'is_catalog' );
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the original global query after block rendering.
				$wp_query = $pre_query;
			}
		} else {
			content_product_adder();
		}

		return $this->widget_blocks->context_blocks->container( $atts, ob_get_clean(), 'product-listing' );
	}

	/**
	 * Renders the products block.
	 *
	 * @param array|null $atts Block attributes.
	 * @return string
	 */
	public function render_products( $atts = null ) {
		global $ic_rendering_products_block;
		$ic_rendering_products_block = 1;
		if ( isset( $atts['selectedProduct'] ) && is_array( $atts['selectedProduct'] ) ) {
			$atts['selectedProduct'] = wp_list_pluck( $atts['selectedProduct'], 'value' );
			if ( empty( $atts['selectedProduct'] ) && ! empty( $atts['product'] ) ) {
				$atts['product'] = implode( ',', $atts['product'] );
			} else {
				$atts['product'] = implode( ',', $atts['selectedProduct'] );
			}
		} elseif ( isset( $atts['product'] ) && is_array( $atts['product'] ) ) {
			$atts['product'] = implode( ',', $atts['product'] );
		}
		if ( isset( $atts['selectedCategory'] ) && is_array( $atts['selectedCategory'] ) ) {
			$atts['selectedCategory'] = wp_list_pluck( $atts['selectedCategory'], 'value' );
			if ( empty( $atts['selectedCategory'] ) && ! empty( $atts['category'] ) ) {
				$atts['category'] = implode( ',', $atts['category'] );
			} else {
				$atts['category'] = implode( ',', $atts['selectedCategory'] );
			}
		} elseif ( isset( $atts['category'] ) && is_array( $atts['category'] ) ) {
			$atts['category'] = implode( ',', $atts['category'] );
		}
		if ( ! empty( $atts['orderby'] ) ) {
			$atts['orderby'] = translate_product_order( $atts['orderby'] );
		}
		$rendered = show_products_outside_loop( $atts );

		$ic_rendering_products_block = 0;

		return $rendered;
	}

	/**
	 * Renders the categories block.
	 *
	 * @param array|null $atts Block attributes.
	 * @return string
	 */
	public function render_categories( $atts = null ) {
		if ( isset( $atts['selectedCategory'] ) && is_array( $atts['selectedCategory'] ) ) {
			$atts['selectedCategory'] = wp_list_pluck( $atts['selectedCategory'], 'value' );
			$atts['include']          = implode( ',', $atts['selectedCategory'] );
		} elseif ( isset( $atts['category'] ) && is_array( $atts['category'] ) ) {
			$atts['include'] = implode( ',', $atts['category'] );
		}
		return product_cat_shortcode( $atts );
	}

	/**
	 * Renders the active filters block.
	 *
	 * @return string
	 */
	public function render_active_filters() {
		if ( ic_is_rendering_block() ) {
			$block_content = '<div style="padding: 20px 10px">' . __( 'Active filters will only show up on the front-end if any filters are active in the current context.', 'ecommerce-product-catalog' ) . '</div>';
		} else {
			$block_content = ic_get_active_filters_html();
		}

		return $block_content;
	}

	/**
	 * Registers the custom block category.
	 *
	 * @param array $categories Existing categories.
	 * @param mixed $post       Current post context.
	 * @return array
	 */
	public function block_category( $categories, $post ) {
		$categories[] = array(
			'slug'  => 'ic-epc-block-cat',
			'title' => __( 'Catalog', 'ecommerce-product-catalog' ),
			'icon'  => null,
		);

		return $categories;
	}

	/**
	 * Returns category options for block controls.
	 *
	 * @return array
	 */
	public function categories() {
		$return   = array();
		$return[] = array(
			'value' => 0,
			'label' => __( 'All', 'ecommerce-product-catalog' ),
		);

		return $this->subcategories( $return, 0 );
	}

	/**
	 * Recursively builds category option labels.
	 *
	 * @param array  $return    Current options list.
	 * @param int    $parent_id Parent term ID.
	 * @param string $tab       Indentation marker.
	 * @return array
	 */
	public function subcategories( $return, $parent_id, $tab = '-' ) {
		$args             = array();
		$args['taxonomy'] = apply_filters( 'show_categories_taxonomy', 'al_product-cat', $args );
		$args['parent']   = $parent_id;
		$cats             = ic_get_terms( $args );
		foreach ( $cats as $cat ) {
			if ( ! empty( $cat->name ) ) {
				if ( ! empty( $parent_id ) ) {
					$name = $tab . $cat->name;
				} else {
					$name = $cat->name;
				}
				$return[] = array(
					'value' => $cat->term_id,
					'label' => $name,
				);
				if ( ! empty( $parent_id ) ) {
					$tab .= '-';
				} else {
					$tab = '-';
				}
				$return = $this->subcategories( $return, $cat->term_id, $tab );
			}
			clean_term_cache( $cat->term_id );
		}

		return array_filter( $return );
	}

	/**
	 * Returns product options for block controls.
	 *
	 * @return array
	 */
	public function products() {
		$all_products = get_all_catalog_products( null, null, - 1, null, array( 'fields' => 'ids' ) );
		$return       = array();
		$return[]     = array(
			'value' => 0,
			'label' => __( 'All', 'ecommerce-product-catalog' ),
		);
		foreach ( $all_products as $product_id ) {
			$return[] = array(
				'value' => $product_id,
				'label' => get_product_name( $product_id ),
			);
			clean_post_cache( $product_id );
		}

		return $return;
	}

	/**
	 * Returns product ordering options.
	 *
	 * @return array
	 */
	public function orderby() {
		$sorting_options = get_product_sort_options();
		$return          = array();
		foreach ( $sorting_options as $name => $label ) {
			$return[] = array(
				'value' => $name,
				'label' => $label,
			);
		}

		return $return;
	}

	/**
	 * Returns category ordering options.
	 *
	 * @return array
	 */
	public function category_orderby() {
		$sorting_options = array(
			'id'    => 'ID',
			'count' => __( 'Count', 'ecommerce-product-catalog' ),
			'name'  => __( 'Name', 'ecommerce-product-catalog' ),
			'none'  => __( 'None', 'ecommerce-product-catalog' ),
		);
		$return          = array();
		foreach ( $sorting_options as $name => $label ) {
			$return[] = array(
				'value' => $name,
				'label' => $label,
			);
		}

		return $return;
	}

	/**
	 * Returns sort direction options.
	 *
	 * @return array
	 */
	public function order() {
		return array(
			array(
				'value' => 'ASC',
				'label' => __( 'ASC', 'ecommerce-product-catalog' ),
			),
			array(
				'value' => 'DESC',
				'label' => __( 'DESC', 'ecommerce-product-catalog' ),
			),
		);
	}

	/**
	 * Returns listing template options.
	 *
	 * @return array
	 */
	public function template() {
		$templates = ic_get_available_templates();
		$return    = array();
		foreach ( $templates as $name => $label ) {
			$return[] = array(
				'value' => $name,
				'label' => $label,
			);
		}

		return $return;
	}
}

if ( ! class_exists( 'ic_epc_blocks', false ) ) {
	class_alias( 'IC_EPC_Blocks', 'ic_epc_blocks' );
}
