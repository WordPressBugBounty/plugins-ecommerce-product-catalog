<?php
/**
 * Sitewide bar integration.
 *
 * @package responsive-bar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles sitewide catalog bar rendering and settings.
 */
class IC_Sitewide_Bar {

	/**
	 * Icons display mode.
	 *
	 * @var string
	 */
	private $display = '';

	/**
	 * Search icon display flag.
	 *
	 * @var string
	 */
	private $search = '';

	/**
	 * Catalog icon display flag.
	 *
	 * @var string
	 */
	private $catalog = '';

	/**
	 * Search icon mode.
	 *
	 * @var string
	 */
	private $search_type = '';

	/**
	 * Hooks the sitewide bar integrations.
	 */
	public function __construct() {
		add_action( 'wp_footer', array( $this, 'show' ) );
		add_filter( 'wp_nav_menu_items', array( $this, 'show' ), 99, 2 );

		add_action( 'ic_catalog_design_schemes_top', array( $this, 'settings' ) );
		add_filter( 'ic_catalog_design_schemes', array( $this, 'settings_default' ) );
		add_action( 'ic_catalog_bar_content', array( $this, 'listing' ) );
		add_action( 'ic_catalog_bar_content', array( $this, 'search' ) );

		add_action( 'ic_catalog_customizer_sections', array( $this, 'customizer_sections' ) );
		add_filter( 'ic_customizer_settings', array( $this, 'customizer' ), 10, 2 );

		add_action( 'ic_register_blocks', array( $this, 'register_block' ) );

		add_action( 'enqueue_block_assets', array( $this, 'enqueue' ) );

		add_action( 'wp', array( $this, 'init' ) );
	}

	/**
	 * Enqueues frontend styles.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'al_product_styles' );
	}

	/**
	 * Loads the current design scheme settings.
	 *
	 * @return void
	 */
	public function init() {
		$design_schemes    = ic_get_design_schemes();
		$this->display     = $design_schemes['icons_display'];
		$this->search      = $design_schemes['icons_display_search'];
		$this->catalog     = $design_schemes['icons_display_catalog'];
		$this->search_type = $design_schemes['icons_search'];
	}

	/**
	 * Outputs the sitewide bar in supported menu locations.
	 *
	 * @param string|null $nav_menu Existing menu HTML.
	 * @param object|null $args     Menu arguments.
	 * @return string|null
	 */
	public function show( $nav_menu = null, $args = null ) {
		if ( ! $this->is_displayed() ) {
			return $nav_menu;
		}
		if ( ! empty( $nav_menu ) && ! empty( $args ) ) {

			if ( ( ! ic_string_contains( $args->theme_location, 'primary' ) && ! ic_string_contains( $args->theme_location, 'main' ) ) || 'all' !== $this->display ) {
				return $nav_menu;
			}

			$nav_menu .= $this->icons();
		}

		return $nav_menu;
	}

	/**
	 * Builds the sitewide icons markup.
	 *
	 * @return string
	 */
	public function icons() {
		ob_start();
		echo '<div id="ic-catalog-menu-bar">';
		ic_show_template_file( 'sitewide-icons/icon-bar.php' );
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Outputs an icon container.
	 *
	 * @param string $content   Icon HTML content.
	 * @param string $css_class Optional container class.
	 * @return void
	 */
	public function icon_container( $content, $css_class = '' ) {
		if ( empty( $content ) ) {
			return;
		}
		if ( is_custom_product_listing_page() ) {
			if ( ! empty( $css_class ) ) {
				$css_class .= ' ';
			}
			$css_class .= 'current-menu-item';
		}
		?>
		<div class="ic-bar-icon <?php echo esc_attr( $css_class ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
			echo $content;
			?>
		</div>
		<?php
	}

	/**
	 * Builds a single icon link.
	 *
	 * @param string      $url             Icon URL or HTML content.
	 * @param string      $icon            Icon class suffix.
	 * @param string|null $content         Optional hidden content.
	 * @param string      $container_class Optional container class.
	 * @return void
	 */
	public function icon( $url, $icon, $content = null, $container_class = '' ) {
		if ( empty( $url ) || empty( $icon ) ) {
			return;
		}
		if ( ! $this->is_url( $url ) ) {
			$content = $url;
			$url     = '';
		}
		$design_schemes = ic_get_design_schemes();
		$class          = ' button ' . implode( ' ', array_filter( $design_schemes ) );
		if ( ! empty( $content ) ) {
			$class .= ' ic-show-content';
		}
		$icon_content  = '<a class="ic-icon-url' . $class . '" href="' . $url . '">';
		$icon_content .= '<span class="' . $this->icons_type() . $icon . '"></span>';
		$icon_content .= '</a>';
		if ( ! empty( $content ) ) {
			$icon_content .= '<div class="ic-icon-hidden-content"><div class="ic-icon-hidden-content-inside"><span class="ic-popup-close dashicons dashicons-no-alt"></span>' . $content . '</div></div>';
		}
		$this->icon_container( $icon_content, $container_class );
	}

	/**
	 * Outputs text inside the sitewide bar.
	 *
	 * @param string $text      Text or trusted HTML content.
	 * @param string $css_class Optional text class suffix.
	 * @return void
	 */
	public function text( $text, $css_class = '' ) {
		if ( empty( $text ) ) {
			return;
		}
		if ( ! empty( $css_class ) ) {
			$css_class = 'ic-bar-text-' . $css_class;
		}
		?>
		<div class="ic-bar-text <?php echo esc_attr( $css_class ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
			echo $text;
			?>
		</div>
		<?php
	}

	/**
	 * Returns the icon class prefix.
	 *
	 * @return string
	 */
	public function icons_type() {
		return apply_filters( 'ic_catalog_bar_icons_type', 'dashicons dashicons-' );
	}

	/**
	 * Outputs the listing icon when enabled.
	 *
	 * @return void
	 */
	public function listing() {
		if ( ! is_ic_product_listing_enabled() ) {
			return;
		}

		if ( ! empty( $this->catalog ) ) {
			return;
		}
		$listing_page = product_listing_url();
		if ( ! empty( $listing_page ) ) {
			$this->icon( $listing_page, 'store' );
		}
	}

	/**
	 * Outputs the search icon when enabled.
	 *
	 * @return void
	 */
	public function search() {
		if ( ! empty( $this->search ) ) {
			return;
		}
		ob_start();
		ic_save_global( 'search_widget_instance', array( 'title' => '' ) );
		add_filter( 'ic_search_box_class', array( __CLASS__, 'box_class' ) );
		ic_show_search_widget_form();
		$search = ob_get_clean();
		if ( ! empty( $search ) ) {
			$this->icon( $search, 'search' );
		}
	}

	/**
	 * Adds the design scheme box class to the search widget.
	 *
	 * @param string $box_class Existing search widget classes.
	 * @return string
	 */
	public static function box_class( $box_class ) {
		if ( ! empty( $box_class ) ) {
			$box_class .= ' ';
		}
		$box_class .= design_schemes( 'box', 0 );

		return $box_class;
	}

	/**
	 * Registers the sitewide icons block.
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			__DIR__ . '/blocks/sitewide-icons/',
			array(
				'render_callback' => array( $this, 'icons' ),
			)
		);
	}

	/**
	 * Outputs the design settings fields.
	 *
	 * @param array $design_schemes Current design settings.
	 * @return void
	 */
	public function settings( $design_schemes ) {
		?>
		<h3><?php esc_html_e( 'Sitewide Icons', 'ecommerce-product-catalog' ); ?></h3>
		<table>
			<?php
			implecode_settings_radio( __( 'Icons Display', 'ecommerce-product-catalog' ), 'design_schemes[icons_display]', $design_schemes['icons_display'], $this->icons_display_options() );
			implecode_settings_checkbox( __( 'Hide Catalog Icon', 'ecommerce-product-catalog' ), 'design_schemes[icons_display_catalog]', $design_schemes['icons_display_catalog'] );
			implecode_settings_checkbox( __( 'Hide Search Icon', 'ecommerce-product-catalog' ), 'design_schemes[icons_display_search]', $design_schemes['icons_display_search'] );
			implecode_settings_radio( __( 'Search Icon', 'ecommerce-product-catalog' ), 'design_schemes[icons_search]', $design_schemes['icons_search'], $this->icons_search_options() );
			do_action( 'ic_catalog_sitewide_icons_settings_html', $design_schemes );
			?>
		</table>
		<?php
	}

	/**
	 * Returns display mode options.
	 *
	 * @return array
	 */
	public function icons_display_options() {
		return array(
			'all'   => __( 'All devices', 'ecommerce-product-catalog' ),
			'small' => __( 'Small screens only', 'ecommerce-product-catalog' ),
			'none'  => __( 'Disabled', 'ecommerce-product-catalog' ),
		);
	}

	/**
	 * Returns search mode options.
	 *
	 * @return array
	 */
	public function icons_search_options() {
		return array(
			'field'    => __( 'Simple Field', 'ecommerce-product-catalog' ),
			'ic_popup' => __( 'Popup', 'ecommerce-product-catalog' ),
		);
	}

	/**
	 * Adds default values for the sitewide icon settings.
	 *
	 * @param array $settings Existing design settings.
	 * @return array
	 */
	public function settings_default( $settings ) {
		$settings['icons_display']         = isset( $settings['icons_display'] ) ? $settings['icons_display'] : 'none';
		$settings['icons_display_catalog'] = isset( $settings['icons_display_catalog'] ) ? $settings['icons_display_catalog'] : '';
		$settings['icons_display_search']  = isset( $settings['icons_display_search'] ) ? $settings['icons_display_search'] : '';
		$settings['icons_search']          = isset( $settings['icons_search'] ) ? $settings['icons_search'] : 'ic_popup';

		return apply_filters( 'ic_catalog_sitewide_icons_settings', $settings );
	}

	/**
	 * Determines whether the sitewide bar should be displayed.
	 *
	 * @return bool
	 */
	public function is_displayed() {
		if ( empty( $this->display ) || ( ! empty( $this->display ) && 'none' === $this->display ) ) {
			return false;
		}
		if ( empty( $this->catalog ) || empty( $this->search ) ) {
			return true;
		}

		return apply_filters( 'ic_catalog_sitewide_icons_displayed', false );
	}

	/**
	 * Determines whether the provided value is a URL.
	 *
	 * @param string $url Value to validate.
	 * @return bool
	 */
	public function is_url( $url ) {
		if ( esc_url_raw( $url ) === $url ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the wrapper classes for the sitewide bar.
	 *
	 * @return string
	 */
	public static function container_class() {
		$design_schemes = ic_get_design_schemes();
		$class          = 'ic-catalog-bar device-' . $design_schemes['icons_display'] . ' ' . $design_schemes['icons_search'];

		return $class;
	}

	/**
	 * Registers Customizer sections for the sitewide bar.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager instance.
	 * @return void
	 */
	public function customizer_sections( $wp_customize ) {
		$message         = __( 'The icons will appear in the main menu.', 'ecommerce-product-catalog' );
		$site_editor_url = admin_url( 'site-editor.php' );
		if ( ! empty( $site_editor_url ) ) {
			$message .= ' ';
			/* translators: 1: opening link tag to the Site Editor, 2: closing link tag. */
			$message .= sprintf( __( 'You can also %1$sadd the Catalog Icons block to the menu%2$s if your theme supports site editing with blocks.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( $site_editor_url ) . '">', '</a>' );
		}

		$wp_customize->add_section(
			'ic_product_catalog_icons',
			array(
				'title'       => __( 'Sitewide Icons', 'ecommerce-product-catalog' ),
				'priority'    => 30,
				'panel'       => 'ic_product_catalog',
				'description' => $message,
			)
		);
	}

	/**
	 * Registers Customizer settings for the sitewide bar.
	 *
	 * @param array  $settings   Existing Customizer settings.
	 * @param object $customizer Customizer helper instance.
	 * @return array
	 */
	public function customizer( $settings, $customizer ) {
		$settings[] = array(
			'name'    => 'design_schemes[icons_display]',
			'args'    => array(
				'type'    => 'option',
				'default' => 'none',
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_display',
				'args' => array(
					'label'    => __( 'Icons Display', 'ecommerce-product-catalog' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_display]',
					'type'     => 'radio',
					'choices'  => $this->icons_display_options(),
				),
			),
		);
		$settings[] = array(
			'name'    => 'design_schemes[icons_display_catalog]',
			'args'    => array(
				'type'              => 'option',
				'default'           => '',
				'sanitize_callback' => array( $customizer, 'sanitize_checkbox' ),
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_display_catalog',
				'args' => array(
					'label'    => __( 'Hide Catalog Icon', 'ecommerce-product-catalog' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_display_catalog]',
					'type'     => 'checkbox',
				),
			),
		);
		$settings[] = array(
			'name'    => 'design_schemes[icons_display_search]',
			'args'    => array(
				'type'              => 'option',
				'default'           => '',
				'sanitize_callback' => array( $customizer, 'sanitize_checkbox' ),
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_display_search',
				'args' => array(
					'label'    => __( 'Hide Search Icon', 'ecommerce-product-catalog' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_display_search]',
					'type'     => 'checkbox',
				),
			),
		);
		$settings[] = array(
			'name'    => 'design_schemes[icons_search]',
			'args'    => array(
				'type'    => 'option',
				'default' => 'ic_popup',
			),
			'control' => array(
				'name' => 'ic_pc_integration_icons_search',
				'args' => array(
					'label'    => __( 'Search Icon', 'ecommerce-product-catalog' ),
					'section'  => 'ic_product_catalog_icons',
					'settings' => 'design_schemes[icons_search]',
					'type'     => 'radio',
					'choices'  => $this->icons_search_options(),
				),
			),
		);

		return $settings;
	}
}

global $ic_sitewide_bar;
$ic_sitewide_bar = new IC_Sitewide_Bar();
