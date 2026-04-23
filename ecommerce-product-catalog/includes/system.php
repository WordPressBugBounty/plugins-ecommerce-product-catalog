<?php
/**
 * System status screen.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the system status submenu.
 */
function register_product_system() {
	add_submenu_page( 'edit.php?post_type=al_product', __( 'System Status', 'ecommerce-product-catalog' ), __( 'System Status', 'ecommerce-product-catalog' ), apply_filters( 'ic_system_status_cap', 'manage_product_settings' ), basename( __FILE__ ), 'ic_system_status' );
}

add_action( 'product_settings_menu', 'register_product_system' );

/**
 * Renders the system status page.
 */
function ic_system_status() {
	if ( current_user_can( 'manage_product_settings' ) ) {
		if ( isset( $_GET['reset_product_settings'] ) ) {
			if ( isset( $_GET['reset_product_settings_confirm'] ) && check_admin_referer( 'ic_reset_product_settings_confirm' ) ) {
				foreach ( all_ic_options( 'options' ) as $option ) {
					delete_option( $option );
				}
				permalink_options_update();
				implecode_success( __( 'Catalog Settings successfully reset to default!', 'ecommerce-product-catalog' ) );
			} else {
					echo '<h3>' . esc_html__( 'All catalog settings will be reset to defaults. Would you like to proceed?', 'ecommerce-product-catalog' ) . '</h3>';
					$confirm_reset_url = wp_nonce_url( add_query_arg( 'reset_product_settings_confirm', 1 ), 'ic_reset_product_settings_confirm' );
					echo '<a class="button" href="' . esc_url( $confirm_reset_url ) . '">' . esc_html__( 'Yes', 'ecommerce-product-catalog' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'reset_product_settings' ) ) . '">' . esc_html__( 'No', 'ecommerce-product-catalog' ) . '</a>';
			}
		} elseif ( isset( $_GET['delete_all_products'] ) ) {
			if ( isset( $_GET['delete_all_products_confirm'] ) && check_admin_referer( 'ic_delete_all_products_confirm' ) ) {
				global $wpdb;
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ( 'al_product' );" );
				$wpdb->query( "DELETE meta FROM {$wpdb->postmeta} meta LEFT JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id WHERE posts.ID IS NULL;" );
				if ( function_exists( 'ic_delete_all_attribute_terms' ) ) {
					ic_delete_all_attribute_terms();
				}
				if ( function_exists( 'ic_update_category_count' ) ) {
					ic_update_category_count();
				}
				implecode_success( __( 'All Catalog Products successfully deleted!', 'ecommerce-product-catalog' ) );
			} else {
					echo '<h3>' . esc_html__( 'All items will be permanently deleted. Would you like to proceed?', 'ecommerce-product-catalog' ) . '</h3>';
					$delete_products_confirm_url = wp_nonce_url( add_query_arg( 'delete_all_products_confirm', 1 ), 'ic_delete_all_products_confirm' );
					echo '<a class="button" href="' . esc_url( $delete_products_confirm_url ) . '">' . esc_html__( 'Yes', 'ecommerce-product-catalog' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'delete_all_products' ) ) . '">' . esc_html__( 'No', 'ecommerce-product-catalog' ) . '</a>';
			}
		} elseif ( isset( $_GET['delete_all_product_categories'] ) ) {
			if ( isset( $_GET['delete_all_product_categories_confirm'] ) && check_admin_referer( 'ic_delete_all_product_categories_confirm' ) ) {
				global $wpdb;
				$taxonomy  = 'al_product-cat';
					$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN (%s) ORDER BY t.name ASC", $taxonomy ) );

					// Delete terms.
				if ( $terms ) {
					foreach ( $terms as $term ) {
						$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
						$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
						$wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );
						delete_option( 'al_product_cat_image_' . $term->term_id );
						if ( function_exists( 'delete_term_meta' ) ) {
							delete_term_meta( $term->term_id, 'thumbnail_id' );
						}
					}
				}

					// Delete taxonomy.
					$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ), array( '%s' ) );
					implecode_success( __( 'All Catalog Categories successfully deleted!', 'ecommerce-product-catalog' ) );
			} else {
				echo '<h3>' . esc_html__( 'All catalog categories will be permanently deleted. Would you like to proceed?', 'ecommerce-product-catalog' ) . '</h3>';
				$delete_categories_confirm_url = wp_nonce_url( add_query_arg( 'delete_all_product_categories_confirm', 1 ), 'ic_delete_all_product_categories_confirm' );
				echo '<a class="button" href="' . esc_url( $delete_categories_confirm_url ) . '">' . esc_html__( 'Yes', 'ecommerce-product-catalog' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'delete_all_product_categories' ) ) . '">' . esc_html__( 'No', 'ecommerce-product-catalog' ) . '</a>';
			}
		} elseif ( isset( $_GET['delete_old_filters_bar'] ) ) {
			if ( isset( $_GET['delete_old_filters_bar_confirm'] ) && check_admin_referer( 'ic_delete_old_filters_bar_confirm' ) ) {
				delete_option( 'old_sort_bar' );
				implecode_success( __( 'Filters bar is now empty by default!', 'ecommerce-product-catalog' ) );
			} else {
					echo '<h3>' . esc_html__( 'Default filters bar will become empty.', 'ecommerce-product-catalog' ) . '</h3>';
					$delete_old_filters_bar_confirm = wp_nonce_url( add_query_arg( 'delete_old_filters_bar_confirm', 1 ), 'ic_delete_old_filters_bar_confirm' );
					echo '<a class="button" href="' . esc_url( $delete_old_filters_bar_confirm ) . '">' . esc_html__( 'OK', 'ecommerce-product-catalog' ) . '</a> <a class="button" href="' . esc_url( remove_query_arg( 'delete_old_filters_bar' ) ) . '">' . esc_html__( 'Cancel', 'ecommerce-product-catalog' ) . '</a>';
			}
		} else {
			?>
			<style>table.widefat {
					width: 95%
				}

				table tbody tr td:first-child {
					width: 350px
				}

				table tbody tr:nth-child(even) {
					background: #fafafa;
				}</style>
			<p></p>
			<table class="widefat" cellspacing="0" id="ic_tools">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'impleCode Tools', 'ecommerce-product-catalog' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Reset product settings', 'ecommerce-product-catalog' ); ?>:</td>
						<td><a class="button"
								href="<?php echo esc_url( add_query_arg( 'reset_product_settings', 1 ) ); ?>"><?php esc_html_e( 'Reset Catalog Settings', 'ecommerce-product-catalog' ); ?></a>
					</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Delete all items', 'ecommerce-product-catalog' ); ?>:</td>
						<td><a class="button"
								href="<?php echo esc_url( add_query_arg( 'delete_all_products', 1 ) ); ?>"><?php esc_html_e( 'Delete all Catalog Items', 'ecommerce-product-catalog' ); ?></a>
					</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Delete all categories', 'ecommerce-product-catalog' ); ?>:</td>
						<td><a class="button"
								href="<?php echo esc_url( add_query_arg( 'delete_all_product_categories', 1 ) ); ?>"><?php esc_html_e( 'Delete all Catalog Categories', 'ecommerce-product-catalog' ); ?></a>
					</td>
				</tr>
				<?php
				if ( 1 === (int) get_option( 'old_sort_bar' ) ) {
					?>
					<tr>
							<td><?php esc_html_e( 'Make default filters bar empty.', 'ecommerce-product-catalog' ); ?>:</td>
							<td><a class="button"
									href="<?php echo esc_url( add_query_arg( 'delete_old_filters_bar', 1 ) ); ?>"><?php esc_html_e( 'Empty Default Filters Bar', 'ecommerce-product-catalog' ); ?></a>
						</td>
					</tr>
				<?php } ?>
				<tr>
						<td><?php esc_html_e( 'Delete all items and categories on uninstall', 'ecommerce-product-catalog' ); ?>:
					</td>
					<?php $checked = get_option( 'ic_delete_products_uninstall', 0 ); ?>
					<td><input type="checkbox" name="delete_products_uninstall" <?php checked( 1, $checked ); ?> /></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Reassign product data', 'ecommerce-product-catalog' ); ?>:</td>
					<?php
					$info = '';
					if ( ! empty( $_GET['reassign_product_data'] ) && check_admin_referer( 'ic_reassign_product_data' ) ) {
						$info = ic_update_product_data();
					}
						$button_label = esc_html__( 'Reassign data', 'ecommerce-product-catalog' );
					$done             = get_option( 'ic_update_product_data_done', 0 );
					if ( ! empty( $done ) ) {
							$button_label = esc_html__( 'Speed up', 'ecommerce-product-catalog' );
					}
					$reassign_data_url = wp_nonce_url( admin_url( 'edit.php?post_type=al_product&page=system.php&reassign_product_data=1' ), 'ic_reassign_product_data' );
					$reassign_data     = '<a class="button" href="' . esc_url( $reassign_data_url ) . '">' . $button_label . '</a>';
					if ( ! empty( $done ) ) {
						if ( $done < 0 ) {
							$done = 0;
						}
							$reassign_data .= '<p>' . absint( $done ) . ' ' . esc_html__( 'Items Done! Still processing.', 'ecommerce-product-catalog' ) . '</p>';
					}
					if ( ! empty( $info ) ) {
							$reassign_data .= '<p>' . esc_html( $info ) . '</p>';
					}
					?>
						<td>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
							echo $reassign_data;
							?>
						</td>
				</tr>
				<?php do_action( 'ic_system_tools' ); ?>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'WordPress Environment', 'ecommerce-product-catalog' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Home URL', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_url( home_url() ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Site URL', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_url( site_url() ); ?></td>
				</tr>
				<tr>
					<td>
					<?php
							/* translators: %s: plugin name. */
							printf( esc_html__( '%s Version', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
					?>
						:
					</td>
					<td>
						<?php
						$plugin_data    = get_plugin_data( AL_PLUGIN_MAIN_FILE );
						$plugin_version = $plugin_data['Version'];
							echo esc_html( $plugin_version );
						?>
					</td>
				</tr>
				<tr>
						<td><?php /* translators: %s: plugin name. */ printf( esc_html__( '%s Database Version', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?>
						:
					</td>
						<td><?php echo esc_html( get_option( 'ecommerce_product_catalog_ver', $plugin_version ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Version', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Multisite', 'ecommerce-product-catalog' ); ?>:</td>
					<td>
					<?php
					if ( is_multisite() ) {
						echo '&#10004;';
					} else {
						echo '&ndash;';
					}
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Memory Limit', 'ecommerce-product-catalog' ); ?>:</td>
					<td>
					<?php
						$memory = WP_MEMORY_LIMIT;
					if ( is_numeric( $memory ) ) {
							echo esc_html( size_format( $memory ) );
					} else {
						echo esc_html( $memory );
					}
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'WP Debug Mode', 'ecommerce-product-catalog' ); ?>:</td>
					<td>
					<?php
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						echo '&#10004;';
					} else {
						echo '&ndash;';
					}
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Language', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_html( get_locale() ); ?></td>
				</tr>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Server Environment', 'ecommerce-product-catalog' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Server Info', 'ecommerce-product-catalog' ); ?>:</td>
						<td>
							<?php
							$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
							echo esc_html( $server_software );
							?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'PHP Version', 'ecommerce-product-catalog' ); ?>:</td>
					<td>
					<?php
					if ( function_exists( 'phpversion' ) ) {
							echo esc_html( phpversion() );
					}
					?>
						</td>
				</tr>
				<?php if ( function_exists( 'ini_get' ) ) : ?>
					<tr>
							<td><?php esc_html_e( 'PHP Post Max Size', 'ecommerce-product-catalog' ); ?>:</td>
						<td>
						<?php
							$max_size = ini_get( 'post_max_size' );
						if ( is_numeric( $max_size ) ) {
								echo esc_html( size_format( $max_size ) );
						} else {
							echo esc_html( $max_size );
						}
						?>
							</td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'PHP Time Limit', 'ecommerce-product-catalog' ); ?>:</td>
							<td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?></td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'PHP Max Input Vars', 'ecommerce-product-catalog' ); ?>:</td>
							<td><?php echo esc_html( ini_get( 'max_input_vars' ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
						<td><?php esc_html_e( 'MySQL Version', 'ecommerce-product-catalog' ); ?>:</td>
						<td>
							<?php
							/**
							 * WordPress database abstraction object.
							 *
							 * @var wpdb $wpdb
							 */
							global $wpdb;
							echo esc_html( $wpdb->db_version() );
							?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Max Upload Size', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></td>
				</tr>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Server Locale', 'ecommerce-product-catalog' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php
				$locale = localeconv();
				foreach ( $locale as $key => $val ) {
					if ( in_array(
						$key,
						array(
							'decimal_point',
							'mon_decimal_point',
							'thousands_sep',
							'mon_thousands_sep',
						),
						true
					) ) {
							echo '<tr><td>' . esc_html( $key ) . ':</td><td>' . esc_html( $val ? $val : __( 'N/A', 'ecommerce-product-catalog' ) ) . '</td></tr>';
					}
				}
				?>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0" id="status">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Active Plugins', 'ecommerce-product-catalog' ); ?>
							(<?php echo absint( count( (array) get_option( 'active_plugins' ) ) ); ?>)
					</th>
				</tr>
				</thead>
				<tbody>
				<?php
				$active_plugins = (array) get_option( 'active_plugins', array() );

				if ( is_multisite() ) {
					$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
				}

				foreach ( $active_plugins as $plugin ) {

					$plugin_data    = @get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
					$dirname        = dirname( $plugin );
					$version_string = '';
					$network_string = '';

					if ( ! empty( $plugin_data['Name'] ) ) {

							// Link the plugin name to the plugin URL if available.
							$plugin_name = esc_html( $plugin_data['Name'] );

						if ( ! empty( $plugin_data['PluginURI'] ) ) {
							$plugin_name = '<a href="' . esc_url( $plugin_data['PluginURI'] ) . '" title="' . esc_attr__( 'Visit plugin homepage', 'ecommerce-product-catalog' ) . '">' . $plugin_name . '</a>';
						}
						?>
						<tr>
								<td>
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
									echo $plugin_name;
									?>
								</td>
								<td>
									<?php
									/* translators: %s: plugin author. */
									echo sprintf( esc_html_x( 'by %s', 'by author', 'ecommerce-product-catalog' ), esc_html( wp_strip_all_tags( $plugin_data['Author'] ) ) ) . ' &ndash; ' . esc_html( $plugin_data['Version'] ) . esc_html( $version_string ) . esc_html( $network_string );
									?>
								</td>
						</tr>
						<?php
					}
				}
				?>
				</tbody>
			</table>
			<p></p>
			<table class="widefat" cellspacing="0">
				<thead>
				<tr>
						<th colspan="2"><?php esc_html_e( 'Theme', 'ecommerce-product-catalog' ); ?></th>
				</tr>
				</thead>
				<?php
				$active_theme = wp_get_theme();
				if ( $active_theme->exists() ) {
					?>
				<tbody>
				<tr>
						<td><?php esc_html_e( 'Name', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_html( $active_theme->display( 'Name' ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Version', 'ecommerce-product-catalog' ); ?>:</td>
					<td>
					<?php
							echo esc_html( $active_theme->display( 'Version' ) );
					?>
						</td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Author URL', 'ecommerce-product-catalog' ); ?>:</td>
						<td><?php echo esc_url( $active_theme->display( 'AuthorURI' ) ); ?></td>
				</tr>
				<tr>
						<td><?php esc_html_e( 'Child Theme', 'ecommerce-product-catalog' ); ?>:</td>
						<td>
						<?php
						if ( is_child_theme() ) {
							echo '<mark class="yes">&#10004;</mark>';
						} else {
							echo '&#10005; &ndash; ';
							/* translators: %s: plugin name. */
							printf( esc_html__( 'If you\'re modifying %s or a parent theme you didn\'t build personally we recommend using a child theme. See:', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
							echo ' <a href="' . esc_url( 'http://codex.wordpress.org/Child_Themes' ) . '" target="_blank">' . esc_html__( 'How to create a child theme', 'ecommerce-product-catalog' ) . '</a>';
						}
						?>
						</td>
				</tr>
					<?php
					if ( is_child_theme() && $active_theme->get( 'Template' ) ) {
						$parent_theme = wp_get_theme( $active_theme->get_template() );
						?>
					<tr>
							<td><?php esc_html_e( 'Parent Theme Name', 'ecommerce-product-catalog' ); ?>:</td>
							<td><?php echo esc_html( $parent_theme->display( 'Name' ) ); ?></td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'Parent Theme Version', 'ecommerce-product-catalog' ); ?>:</td>
							<td><?php echo esc_html( $parent_theme->display( 'Version' ) ); ?></td>
					</tr>
					<tr>
							<td><?php esc_html_e( 'Parent Theme Author URL', 'ecommerce-product-catalog' ); ?>:</td>
							<td><?php echo esc_url( $parent_theme->display( 'AuthorURI' ) ); ?></td>
					</tr>
						<?php
					}
				}
				?>
				<tr>
						<td><?php /* translators: %s: plugin name. */ printf( esc_html__( '%s Support', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) ); ?>
						:
					</td>
					<td>
					<?php
					if ( ! is_theme_implecode_supported() ) {
							esc_html_e( 'Not Declared', 'ecommerce-product-catalog' );
					} else {
						echo '&#10004;';
					}
					?>
						</td>
				</tr>
				</tbody>
			</table>
			<script>
				jQuery(document).ready(function () {
					jQuery("input[type='checkbox']").change(function () {
						checkbox = jQuery(this);
						if (checkbox.is(":checked")) {
							checked = 1;
						} else {
							checked = 0;
						}
							data = {
								action: "save_implecode_tools",
								field: checkbox.attr('name') + "|" + checked,
								nonce: "<?php echo esc_js( wp_create_nonce( 'ic-ajax-nonce' ) ); ?>"
							};
							jQuery.post("<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", data, function (response) {
							checkbox.after("<span class='saved'>Saved!</span>");
							jQuery(".saved").delay(2000).fadeOut(300, function () {
								jQuery(this).remove();
							});
						});
					});
				});
			</script>
			<?php
		}
	}
}

add_action( 'wp_ajax_save_implecode_tools', 'ajax_save_implecode_tools' );

/**
 * Saves impleCode tools settings via AJAX.
 */
function ajax_save_implecode_tools() {
	if ( ! empty( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ic-ajax-nonce' ) && current_user_can( 'manage_product_settings' ) ) {
		if ( isset( $_POST['field'] ) ) {
			$checked = sanitize_text_field( wp_unslash( $_POST['field'] ) );
			if ( false !== strpos( $checked, '|' ) ) {
				$checked = explode( '|', $checked );
				if ( isset( $checked[0], $checked[1] ) ) {
					update_option( 'ic_' . sanitize_key( $checked[0] ), sanitize_text_field( $checked[1] ), false );
				}
			}
		}
	}
	echo 'done';

	wp_die(); // This is required to terminate immediately and return a proper response.
}
