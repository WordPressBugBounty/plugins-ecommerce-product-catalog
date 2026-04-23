<?php
/**
 * Support settings screen helpers.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'implecode_support_menu' ) ) :

	/**
	 * Prints the support submenu link.
	 *
	 * @return void
	 */
	function implecode_custom_support_menu() {
		?>
		<a id="support-settings" class="element"
			href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=support' ) ); ?>"><?php esc_html_e( 'Support', 'ecommerce-product-catalog' ); ?></a>
		<?php
	}

	add_action( 'general_submenu', 'implecode_custom_support_menu', 20 );

	/**
	 * Renders the support settings tab content.
	 *
	 * @return void
	 */
	function implecode_custom_support_settings_content() {
		$submenu = filter_input( INPUT_GET, 'submenu', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		?>
		<?php if ( 'support' === $submenu ) { ?>
			<div class="setting-content submenu support-tab">
				<script>
					jQuery('.settings-submenu a').removeClass('current');
					jQuery('.settings-submenu a#support-settings').addClass('current');
				</script>
				<style>
					.setting-content p {
						max-width: 800px;
					}
				</style>
				<h2><?php esc_html_e( 'impleCode Support', 'ecommerce-product-catalog' ); ?></h2>
				<p>
					<?php
					/*
					 * translators: %s: plugin name.
					 */
					printf( __( '<b>%s is free to use</b>. That\'s great! It\'s a pleasure to serve it to you. Let\'s keep it free forever!', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
					?>
				</p>
				<p>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
					echo __( 'This awesome plugin is being developed under impleCode brand, which is a legally operating European company. It means that you can be assured that the high-quality <b>development will be continuous</b>.', 'ecommerce-product-catalog' );
					?>
				</p>
				<p>
					<?php
					/*
					 * translators: 1: opening support forum link, 2: closing support forum link.
					 */
					printf( __( 'For <b>free support</b>, please visit %1$ssupport forums%2$s where the plugin developers will give you valuable advice.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( 'https://wordpress.org/support/plugin/' . IC_CATALOG_PLUGIN_SLUG ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
					?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Please consider upgrading to support the project.', 'ecommerce-product-catalog' ); ?></strong>
				</p>
				<div style="clear: both; height: 10px;"></div>
				<div class="extension premium-support">
						<a href="https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link">
							<h3><span><?php esc_html_e( 'Premium Toolset', 'ecommerce-product-catalog' ); ?></span></h3></a>
					<p><?php esc_html_e( 'An upgrade that will add many valuable features to the catalog functionality!', 'ecommerce-product-catalog' ); ?></p>
					<p>
						<?php
						/*
						 * translators: 1: opening premium support link, 2: closing premium support link, 3: price.
						 */
						printf( __( 'One year of high quality and speedy %1$sPremium Support%2$s from the developers for just %3$s.', 'ecommerce-product-catalog' ), '<a href="https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link">', '</a>', '$49' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</p>
					<form style="text-align: center; position: relative; top: 10px;"
							action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
						<input type="hidden" name="cmd" value="_s-xclick">
						<input type="hidden" name="hosted_button_id" value="LCRGR95EST66S">
						<input style="cursor:pointer;" type="image"
								src="https://www.paypalobjects.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0"
								name="submit" alt="PayPal - The safer, easier way to pay online!">
						<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1"
							height="1">
					</form>
				</div>
				<div class="extension premium-support">
						<a href="https://implecode.com/wordpress/plugins/#extensions&cam=catalog-support-tab&key=extensions-link">
							<h3><span><?php echo esc_html( IC_CATALOG_PLUGIN_NAME ); ?> <?php esc_html_e( 'Extensions', 'ecommerce-product-catalog' ); ?></span></h3></a>
					<p>
						<?php
						/*
						 * translators: 1: SEO URL, 2: usability URL, 3: productivity URL, 4: conversion URL, 5: plugin name.
						 */
						printf( __( '<b>Extensions apart of premium support</b> provide additional useful features. They improve %5$s in a field of <a href="%1$s">SEO</a>, <a href="%2$s">Usability</a>, <a href="%3$s">Productivity</a> and <a href="%4$s">Conversion</a>.', 'ecommerce-product-catalog' ), esc_url( 'https://implecode.com/wordpress/plugins/#seo_usability_boosters&cam=catalog-support-tab&key=extensions-link-seo' ), esc_url( 'https://implecode.com/wordpress/plugins/#seo_usability_boosters&cam=catalog-support-tab&key=extensions-link-usability' ), esc_url( 'https://implecode.com/wordpress/plugins/#productivity_boosters&cam=catalog-support-tab&key=extensions-link-productivity' ), esc_url( 'https://implecode.com/wordpress/plugins/#conversion_boosters&cam=catalog-support-tab&key=extensions-link-conversion' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</p>
					<p>
						<a href="https://implecode.com/wordpress/plugins/#extensions&cam=catalog-support-tab&key=extensions-link"><input
									style="cursor:pointer;" class="button-primary" type="button"
									value="Check out the extensions &raquo;"></a></p>
				</div>
				<div style="clear: both; height: 10px;"></div>
					<h2><?php esc_html_e( 'Premium Toolset Features', 'ecommerce-product-catalog' ); ?></h2>
					<p>
						<?php
						/*
						 * translators: %s: plugin name.
						 */
						printf( __( 'Apart of fast, confidential email support <b>you will receive some advanced features</b> for %s.', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</p>
					<h4><?php esc_html_e( 'Premium Features:', 'ecommerce-product-catalog' ); ?></h4>
				<ol>
						<li><strong><?php esc_html_e( 'Alternative Products', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'select alternative products for each product and display them in a separate tab or section', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Alternative Products Table', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'display alternative products as a table with many different parameters to compare', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Price Filter Ranges', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'set clickable price filter ranges in the price filter widget', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Category template', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'select different template for categories', 'ecommerce-product-catalog' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Disabled default image', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'disable default image for category pages', 'ecommerce-product-catalog' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Disabled category name', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'disable category name - useful if your category image already has the name included', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Automatic Product Descriptions', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'generate product descriptions from category descriptions', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Enhanced category filter', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'only bottom category clickable or always show child categories', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Checkbox category filter', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'checkbox instead of links for category filter', 'ecommerce-product-catalog' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Alternative listing template', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'let the user switch the listing template on front-end - for example between grid or list.', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product Count widget', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'product count widget that shows the number of displayed products', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product Pagination widget', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'display pagination in any widget area', 'ecommerce-product-catalog' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Product Promo widget', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'display selected or random product in widget area', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product tags', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'which is considered as SEO booster if used properly', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Product tags filter', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'filter products by tags', 'ecommerce-product-catalog' ); ?>;
					</li>
						<li><strong><?php esc_html_e( 'Separate sidebar', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'on main listing, individual product, catalog search and category pages', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Responsive sidebar', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'select styling and appearance for sidebar on small screens', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'More styling', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'set product sidebar and product description width', 'ecommerce-product-catalog' ); ?>
						;
					</li>
						<li><strong><?php esc_html_e( 'Category widget enhancement', 'ecommerce-product-catalog' ); ?></strong>
							- <?php esc_html_e( 'Show child categories only on parent category pages', 'ecommerce-product-catalog' ); ?>
						.
					</li>
				</ol>
					<p><?php esc_html_e( 'Go ahead and use the Buy Now button above to receive the premium support service and the described features immediately. You will receive the premium extension on your PayPal email address immediately after the payment is confirmed.', 'ecommerce-product-catalog' ); ?></p>
					<p>
						<?php
						/*
						 * translators: %s: impleCode premium support URL.
						 */
						printf( __( 'If you need to get it on a different email address, please use the <a href="%s">impleCode website to order the premium support</a>. It will let you set a different email address than the one for PayPal.', 'ecommerce-product-catalog' ), esc_url( 'https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link-1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</p>
					<p>
						<?php
						/*
						 * translators: 1: opening extensions link, 2: closing extensions link.
						 */
						printf( __( 'You can also choose one of the %1$scatalog extensions%2$s to get the premium support.', 'ecommerce-product-catalog' ), '<a href="https://implecode.com/wordpress/plugins/#cam=catalog-support-tab&key=extensions-link">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</p>
					<h2><?php esc_html_e( 'Theme Integration', 'ecommerce-product-catalog' ); ?></h2>
					<p>
						<?php
						/*
						 * translators: 1: plugin name, 2: theme integration guide URL, 3: advanced theme integration URL, 4: premium support URL.
						 */
						printf( __( 'As you may already know some themes may need Theme Integration to support %1$s fully. We wrote this <a href="%2$s">theme integrations guide</a>, however, to make it even easier you will get <a href="%3$s">Advanced Theme Integration</a> service for free if you choose <a href="%4$s">Premium Support</a> service.', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ), esc_url( 'https://implecode.com/wordpress/product-catalog/theme-integration-guide/#cam=catalog-support-tab&key=integration-link' ), esc_url( 'https://implecode.com/wordpress/plugins/advanced-theme-integration/#cam=catalog-support-tab&key=integration-service-link' ), esc_url( 'https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link-2' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</p>
					<h2>
						<?php
						/*
						 * translators: %s: plugin name.
						 */
						printf( __( '%s documentation', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</h2>
					<p>
						<?php
						/*
						 * translators: 1: documentation URL, 2: support forum URL, 3: premium support URL, 4: plugin name.
						 */
						printf( __( '<b>%4$s</b> documentation is being developed <a href="%1$s">here</a>. For questions about %4$s please use <a href="%2$s">support forum</a> or <a href="%3$s">Premium Support service</a>.', 'ecommerce-product-catalog' ), esc_url( 'https://implecode.com/wordpress/product-catalog/#cam=catalog-support-tab&key=docs-link' ), esc_url( 'http://wordpress.org/support/plugin/ecommerce-product-catalog' ), esc_url( 'https://implecode.com/wordpress/plugins/premium-support/#cam=catalog-support-tab&key=support-link-3' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
						?>
					</p>
			</div>
			<div class="helpers">
			<div class="wrapper">
			<?php
				ic_epc_main_helper();
				ic_epc_did_know_helper( 'support', __( 'You can get instant premium support from plugin developers', 'ecommerce-product-catalog' ), 'https://implecode.com/wordpress/plugins/premium-support/' );
				ic_epc_bug_report_helper();
				ic_epc_review_helper();
			?>
			</div></div>
			<?php
		}
	}

	add_action( 'product-settings', 'implecode_custom_support_settings_content' );

	add_action( 'admin_init', 'ic_disable_ic_updater', 4 );

	/**
	 * Disables premium updates and support on demand
	 */
	function ic_disable_ic_updater() {
		if ( 1 === (int) get_option( 'ic_disable_license_message' ) ) {
			add_action( 'admin_init', 'ic_disable_license_message', 6 );
		}
		if ( 1 === (int) get_option( 'ic_disable_ic_updater' ) ) {
			if ( ! function_exists( 'start_implecode_updater' ) ) {
				/**
				 * Prevents the updater bootstrap from loading.
				 *
				 * @return void
				 */
				function start_implecode_updater() {
				}
			}
			if ( ! function_exists( 'implecode_support_menu' ) ) {
				/**
				 * Prevents the support menu from loading.
				 *
				 * @return void
				 */
				function implecode_support_menu() {
				}
			}
		}
	}

	/**
	 * Disables premium license check message
	 */
	function ic_disable_license_message() {
		remove_action( 'admin_init', 'check_if_license_exists', 99 );
	}


endif;
