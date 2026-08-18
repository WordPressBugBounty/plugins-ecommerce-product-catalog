<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Defines customer panel settings
 *
 * Created by Norbert Dreszer.
 * Date: 10-Mar-15
 * Time: 14:41
 * Package: customer-panel-settings.php
 */
class ic_customer_panel_settings {

	public function __construct() {
		add_action( 'general_submenu', array( $this, 'menu' ) );
		add_action( 'product-settings-list', array( $this, 'register_settings' ) );
		add_action( 'product-settings', array( $this, 'content' ) );
	}

	function menu() {
		?>
		<a id="customer-panel-settings" class="element"
			href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=customer-panel' ) ); ?>"><?php esc_html_e( 'Customer Panel', 'ecommerce-product-catalog' ); ?> </a>
			<?php
	}

	function register_settings() {
		register_setting( 'customer_panel', 'customer_panel', array( 'sanitize_callback' => array( $this, 'ic_customer_panel_sanitize_settings' ) ) );
		do_action( 'customer_panel_settings' );
	}

	/**
	 * Sanitizes customer panel settings while retaining extension keys.
	 *
	 * @param mixed $settings Submitted settings.
	 * @return mixed
	 */
	function ic_customer_panel_sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}
		$clean = array();
		foreach ( $settings as $key => $value ) {
			$clean[ $key ] = is_array( $value ) ? $this->ic_customer_panel_sanitize_settings( $value ) : sanitize_text_field( (string) $value );
		}
		if ( isset( $clean['page_id'] ) ) {
			$clean['page_id'] = absint( $clean['page_id'] );
		}
		return $clean;
	}

	function content() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value selects a read-only submenu; no state mutation occurs.
		$submenu = isset( $_GET['submenu'] ) ? sanitize_key( wp_unslash( $_GET['submenu'] ) ) : '';
		if ( $submenu == 'customer-panel' ) {
			?>
			<script>
				jQuery('.settings-submenu a').removeClass('current');
				jQuery('.settings-submenu a#customer-panel-settings').addClass('current');
			</script>
			<div class="customer-panel-settings setting-content submenu">
				<form method="post" action="options.php">
					<h2><?php esc_html_e( 'Customer Panel Settings', 'ecommerce-product-catalog' ); ?></h2>
					<?php
					settings_fields( 'customer_panel' );
					$panel_settings = ic_get_customer_panel_settings();
					?>
					<h3><?php esc_html_e( 'Customer Panel Page', 'ecommerce-product-catalog' ); ?></h3>
					<table>
						<tr>
							<td><?php esc_html_e( 'Customer Panel Page', 'ecommerce-product-catalog' ); ?></td>
							<td><?php select_page( 'customer_panel[page_id]', __( 'Select page...', 'ecommerce-product-catalog' ), $panel_settings['page_id'], true ); ?> </td>
						</tr>
					</table>

					<?php do_action( 'customer_panel_settings_content', 'customer_panel', $panel_settings ); ?>

					<p class="submit">
						<input type="submit" class="button-primary"
								value="<?php esc_html_e( 'Save changes', 'ecommerce-product-catalog' ); ?>"/>
					</p>

				</form>

			</div>

			<?php
		}
	}
}

$ic_customer_panel_settings = new ic_customer_panel_settings();

function ic_get_customer_panel_settings() {
	$panel_settings = wp_parse_args( get_option( 'customer_panel' ), array( 'page_id' => 'noid' ) );

	return apply_filters( 'ic_customer_panel_settings', $panel_settings );
}
