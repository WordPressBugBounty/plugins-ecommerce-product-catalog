<?php
/**
 * CSV import and export settings.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages support settings
 *
 * Here support settings are defined and managed.
 *
 * @version        1.0.0
 * @package        ecommerce-product-catalog/includes
 * @author        impleCode
 */
add_action( 'general_submenu', 'implecode_custom_csv_menu' );

/**
 * Renders the CSV submenu link.
 */
function implecode_custom_csv_menu() {
	?>
	<a id="csv-settings" class="element"
		href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv' ) ); ?>"><?php esc_html_e( 'Import / Export', 'ecommerce-product-catalog' ); ?></a>
	<?php
}

/**
 * Renders the CSV settings tab content.
 */
function implecode_custom_csv_settings_content() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading submenu state for the admin UI.
	$submenu = isset( $_GET['submenu'] ) ? sanitize_text_field( wp_unslash( $_GET['submenu'] ) ) : '';
	?>
	<?php if ( 'csv' === $submenu ) { ?>
		<div class="setting-content submenu csv-tab">
			<script>
				jQuery('.settings-submenu a').removeClass('current');
				jQuery('.settings-submenu a#csv-settings').addClass('current');
			</script>
				<h2>
				<?php
					esc_html_e( 'Simple CSV', 'ecommerce-product-catalog' );
				?>
				</h2>
				<h3><?php esc_html_e( 'Simple Export', 'ecommerce-product-catalog' ); ?></h3>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading export toggle for the admin UI.
				$export = isset( $_GET['export_csv'] ) ? sanitize_text_field( wp_unslash( $_GET['export_csv'] ) ) : '';
				ic_register_setting( __( 'Export Products', 'ecommerce-product-catalog' ), 'simple-export-button' );
				ic_register_setting( __( 'Import Products', 'ecommerce-product-catalog' ), 'product_csv' );
				if ( '1' === $export ) {
					$url = simple_export_to_csv();
					echo '<a style="display: block; margin-top: 20px;" href="' . esc_url( $url ) . '">' . esc_html__( 'Download CSV', 'ecommerce-product-catalog' ) . '</a>';
				} else {
					?>
					<a style="display: block; margin-top: 20px;"
						href="<?php echo esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv&export_csv=1' ) ); ?>">
						<button class="button simple-export-button"><?php esc_html_e( 'Export all items to CSV file', 'ecommerce-product-catalog' ); ?></button>
					</a>
					<h3><?php esc_html_e( 'Simple Import', 'ecommerce-product-catalog' ); ?></h3>
					<?php
					simple_upload_csv_products_file();
					do_action( 'ic_simple_csv_bottom' );
				}
				?>
		</div>
		<div class="helpers">
		<div class="wrapper">
		<?php
			ic_epc_main_helper();
			ic_epc_doc_helper( __( 'import', 'ecommerce-product-catalog' ), 'product-import' );
		?>
		</div></div>
		<?php
	}
}

add_action( 'admin_init', 'ic_simple_csv_provide_admin_file' );

/**
 * Provides the generated admin CSV files.
 */
function ic_simple_csv_provide_admin_file() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading export toggle for file delivery.
	$provide_export = isset( $_GET['provide_export_csv'] ) ? sanitize_text_field( wp_unslash( $_GET['provide_export_csv'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading import-sample toggle for file delivery.
	$provide_import_sample = isset( $_GET['provide_import_sample'] ) ? sanitize_text_field( wp_unslash( $_GET['provide_import_sample'] ) ) : '';
	if ( '1' === $provide_export ) {
		ic_simple_csv_provide_export();
	} elseif ( '1' === $provide_import_sample ) {
		ic_simple_csv_provide_import_sample();
	}
}


add_action( 'product-settings', 'implecode_custom_csv_settings_content' );

/**
 * Handles CSV upload and import form output.
 */
function simple_upload_csv_products_file() {
	$upload_feedback = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload form processing in admin settings; individual fields are validated below.
	$product_csv = ( isset( $_FILES['product_csv'] ) && is_array( $_FILES['product_csv'] ) ) ? $_FILES['product_csv'] : array();
	if ( ! empty( $product_csv['size'] ) ) {
		$file_name          = isset( $product_csv['name'] ) ? sanitize_file_name( wp_unslash( $product_csv['name'] ) ) : '';
		$arr_file_type      = wp_check_filetype( basename( $file_name ) );
		$uploaded_file_type = $arr_file_type['ext'];
		$allowed_file_type  = 'csv';
		if ( $uploaded_file_type === $allowed_file_type ) {
			$filepath = ic_simple_import_file_name();
			$tmp_name = isset( $product_csv['tmp_name'] ) ? wp_unslash( $product_csv['tmp_name'] ) : '';
			if ( move_uploaded_file( $tmp_name, $filepath ) ) {
				simple_import_product_from_csv();
			} else {
				$upload_feedback = '<div class="al-box warning">' . __( 'There was a problem with your upload.', 'ecommerce-product-catalog' ) . '</div>';
			}
		} else {
			$upload_feedback = '<div class="al-box warning">' . __( 'Please upload only CSV files.', 'ecommerce-product-catalog' ) . '</div>';
		}
		echo wp_kses_post( $upload_feedback );
	} else {
		if ( ! empty( $product_csv['error'] ) ) {
			if ( 1 === (int) $product_csv['error'] || 2 === (int) $product_csv['error'] ) {
				implecode_warning( __( 'The file could not be uploaded because of your server limit. Please contact the server administrator or decrease the file size.', 'ecommerce-product-catalog' ) );
			} else {
				implecode_warning( __( 'There was an error while uploading the file to your server.', 'ecommerce-product-catalog' ) );
			}
		}
		$url = sample_import_file_url();
		echo '<form method="POST" enctype="multipart/form-data"><input type="file" accept=".csv" name="product_csv" id="product_csv" /><input type="submit" class="button" value="' . esc_attr__( 'Import Now', 'ecommerce-product-catalog' ) . '" /></form>';
		$sep = get_simple_separator();
		if ( ';' === $sep ) {
			$sep_label = __( 'Semicolon', 'ecommerce-product-catalog' );
		} else {
			$sep_label = __( 'Comma', 'ecommerce-product-catalog' );
		}
		/* translators: %s: CSV separator label. */
		echo '<div class="al-box info"><p>' . esc_html__( 'The CSV fields should be in the following order: Image URL, Name, Price, Categories, Short Description, Long Description.', 'ecommerce-product-catalog' ) . '</p><p>' . esc_html( sprintf( __( 'The first row should contain the field names. %s should be used as the CSV separator.', 'ecommerce-product-catalog' ), $sep_label ) ) . '</p><a href="' . esc_url( $url ) . '" class="button-primary">' . esc_html__( 'Download CSV Template', 'ecommerce-product-catalog' ) . '</a></div>';
	}
}

/**
 * Imports products from the uploaded CSV file.
 */
function simple_import_product_from_csv() {
	$file_path = ic_simple_import_file_name();
	$fp        = simple_prepare_csv_file( 'r', $file_path );
	$product   = array();
	if ( false !== $fp ) {
		$sep      = apply_filters( 'simple_csv_separator', ';' );
		$csv_cols = fgetcsv( $fp, 0, $sep, '"', '\\' );
		if ( isset( $csv_cols[0] ) && '﻿sep=' === $csv_cols[0] ) {
			$csv_cols = fgetcsv( $fp, 0, $sep, '"', '\\' );
		}
		$import_array = simple_prepare_csv_import_array();
		if ( count( $csv_cols ) === count( $import_array ) ) {
			$i     = 0;
			$error = 0;
			while ( ( $data = fgetcsv( $fp, 0, $sep, '"', '\\' ) ) !== false ) {
				$filtered_data = array_filter( $data );
				if ( empty( $data ) || ! is_array( $data ) || ( is_array( $data ) && empty( $filtered_data ) ) || 1 === count( $data ) ) {
					continue;
				}
				foreach ( $data as $key => $val ) {
					if ( isset( $import_array[ $key ] ) ) {
						unset( $data[ $key ] );
						$new_key          = $import_array[ $key ];
						$data[ $new_key ] = $val;
					}
				}

				$product_id = simple_insert_csv_product( $data );
				if ( ! empty( $product_id ) && ! is_wp_error( $product_id ) ) {
					++$i;
				} else {
					++$error;
				}
			}
			$result = 'success';
			if ( ! empty( $error ) ) {
				$result = 'warning';
			}
				echo '<div class="al-box ' . esc_attr( $result ) . '">';
				/* translators: %s: Number of imported products. */
				echo '<p>' . esc_html( sprintf( __( '%s products successfully added to the catalog', 'ecommerce-product-catalog' ), $i ) ) . '.</p>';
			if ( ! empty( $error ) ) {
				/* translators: %s: Number of failed imports. */
				echo '<p>' . esc_html( sprintf( __( '%s failures occurred. Please check if the file is UTF-8 encoded', 'ecommerce-product-catalog' ), $error ) ) . '.</p>';
			}
			echo '</div>';
		} else {
			$included     = str_replace(
				array( 'Array', '(', ')', ']', '[' ),
				array(
					'',
					'',
					'',
					'',
					'<br>',
				),
				print_r( $csv_cols, true )
			);
			$export_array = prepare_sample_import_file();
			$expected     = str_replace(
				array( 'Array', '(', ')', ']', '[' ),
				array(
					'',
					'',
					'',
					'',
					'<br>',
				),
				print_r( array_values( $export_array[1] ), true )
			);
			echo '<div class = "al-box warning">';
			echo '<p>' . esc_html__( 'Number of product fields and number of fields in CSV file do not match!', 'ecommerce-product-catalog' ) . '</p>';
			/* translators: %s: Included CSV columns. */
			echo '<p>' . esc_html( sprintf( __( 'Columns included in file: %s', 'al-product-csv' ), $included ) ) . '</p>';
			/* translators: %s: Expected CSV columns. */
			echo '<p>' . esc_html( sprintf( __( 'Columns expected in file: %s', 'al-product-csv' ), $expected ) ) . '</p>';
			echo '<p>' . esc_html__( 'Please make sure that only the expected columns exist in the import file and the correct CSV separator is set.', 'ecommerce-product-catalog' ) . '</p>';
			echo '</div>';
		}
	}
	fclose( $fp );
}

/**
 * Opens a CSV file pointer.
 *
 * @param string $type File open mode.
 * @param string $file_path CSV file path.
 *
 * @return resource|false
 */
function simple_prepare_csv_file( $type = 'w', $file_path = '' ) {
	if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
		ini_set( 'auto_detect_line_endings', true );
	}
	$fp = fopen( $file_path, $type );
	if ( false === $fp ) {
		/* translators: %1$s: Opening permissions documentation link. %2$s: Closing link. */
		wp_die( wp_kses_post( implecode_warning( sprintf( __( 'Permission error. Please check WordPress uploads %1$sfolder permissions%2$s.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( 'https://codex.wordpress.org/Changing_File_Permissions' ) . '">', '</a>' ), 0 ) ) );
	}

	return $fp;
}

/**
 * Returns the expected CSV import field order.
 *
 * @return array
 */
function simple_prepare_csv_import_array() {
	$arr   = array( 'image_url' );
	$arr[] = 'product_name';
	if ( function_exists( 'is_ic_price_enabled' ) && is_ic_price_enabled() ) {
		$arr[] = 'product_price';
	}
	$arr[] = 'product_categories';
	$arr[] = 'product_short_desc';
	$arr[] = 'product_desc';

	return $arr;
}

/**
 * Inserts a product from a CSV row.
 *
 * @param array $data CSV row data.
 *
 * @return int|WP_Error
 */
function simple_insert_csv_product( $data ) {
	$short_description = wp_kses_post( $data['product_short_desc'] );
	$long_description  = wp_kses_post( $data['product_desc'] );
	$post              = array(
		'ID'           => '',
		'post_title'   => $data['product_name'],
		'post_status'  => 'publish',
		'post_type'    => 'al_product',
		'post_excerpt' => $short_description,
		'post_content' => $long_description,
	);
	$id                = wp_insert_post( $post );
	if ( ! is_wp_error( $id ) && ! empty( $id ) ) {
		if ( function_exists( 'is_ic_price_enabled' ) && is_ic_price_enabled() && isset( $data['product_price'] ) ) {
			update_post_meta( $id, '_price', ic_price_display::raw_price_format( $data['product_price'] ) );
		}
			$image_url = get_product_image_id( $data['image_url'] );
		set_post_thumbnail( $id, $image_url );
		if ( ! empty( $data['product_categories'] ) ) {
			if ( ic_string_contains( $data['product_categories'], ' | ' ) ) {
				$data['product_categories'] = explode( ' | ', $data['product_categories'] );
			}
			wp_set_object_terms( $id, $data['product_categories'], 'al_product-cat' );
		}
		ic_set_time_limit( 30 );
	}

	return $id;
}

/**
 * Returns a sample import file row set.
 *
 * @return array
 */
function prepare_sample_import_file() {
	$fields                    = array();
	$fields[1]['image_url']    = __( 'Image URL', 'ecommerce-product-catalog' );
	$fields[1]['product_name'] = __( 'Name', 'ecommerce-product-catalog' );
	if ( function_exists( 'is_ic_price_enabled' ) && is_ic_price_enabled() ) {
		$fields[1]['product_price'] = __( 'Price', 'ecommerce-product-catalog' );
	}
	$fields[1]['product_categories'] = __( 'Categories', 'ecommerce-product-catalog' );
	$fields[1]['product_short_desc'] = __( 'Short Description', 'ecommerce-product-catalog' );
	$fields[1]['product_desc']       = __( 'Long Description', 'ecommerce-product-catalog' );

	return array_filter( $fields );
}

/**
 * Generates the sample import file and returns its URL.
 *
 * @return string
 */
function sample_import_file_url() {
	$file_path = ic_simple_import_file_name();
	$fp        = simple_prepare_csv_file( 'w', $file_path );
	$fields    = prepare_sample_import_file();
	fprintf( $fp, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
	$sep = apply_filters( 'simple_csv_separator', ';' );
	foreach ( $fields as $field ) {
		fputcsv( $fp, $field, $sep, '"', '\\' );
	}
	simple_close_csv_file( $fp );

	return ic_simple_import_template_file_url();
}

/**
 * Closes the CSV file pointer.
 *
 * @param resource $fp File pointer.
 */
function simple_close_csv_file( $fp ) {
	fclose( $fp );
	ini_set( 'auto_detect_line_endings', false );
}

/**
 * Returns all exported products.
 *
 * @return array
 */
function simple_get_all_exported_products() {
	$args     = array(
		'posts_per_page'   => 1000,
		'orderby'          => 'title',
		'order'            => 'ASC',
		'post_type'        => 'al_product',
		'post_status'      => ic_visible_product_status(),
		'suppress_filters' => true,
	);
	$products = get_posts( $args );

	return $products;
}

/**
 * Builds the product export rows.
 *
 * @return array
 */
function simple_prepare_products_to_export() {
	$products                  = simple_get_all_exported_products();
	$fields                    = array();
	$fields[1]['image_url']    = __( 'Image URL', 'ecommerce-product-catalog' );
	$fields[1]['product_name'] = __( 'Name', 'ecommerce-product-catalog' );
	if ( class_exists( 'ic_price_display' ) ) {
		$fields[1]['product_price'] = __( 'Price', 'ecommerce-product-catalog' );
	}
	$fields[1]['product_categories'] = __( 'Categories', 'ecommerce-product-catalog' );
	$fields[1]['product_short_desc'] = __( 'Short Description', 'ecommerce-product-catalog' );
	$fields[1]['product_desc']       = __( 'Long Description', 'ecommerce-product-catalog' );
	$z                               = 2;
	foreach ( $products as $product ) {
		$image      = wp_get_attachment_image_src( get_post_thumbnail_id( $product->ID ), 'full' );
		$desc       = get_product_description( $product->ID );
		$short_desc = get_product_short_description( $product->ID );
		if ( empty( $fields[ $z ] ) || ! is_array( $fields[ $z ] ) ) {
			$fields[ $z ] = array();
		}
		$image_url                    = isset( $image[0] ) ? $image[0] : '';
		$fields[ $z ]['image_url']    = $image_url;
		$fields[ $z ]['product_name'] = $product->post_title;
		if ( class_exists( 'ic_price_display' ) ) {
			$fields[ $z ]['product_price'] = get_post_meta( $product->ID, '_price', true );
		}
		$category_array = get_the_terms( $product->ID, 'al_product-cat' );
		$category       = array();
		if ( ! empty( $category_array ) ) {
			foreach ( $category_array as $p_cat ) {
				$value      = html_entity_decode( $p_cat->name );
				$category[] = $value;
			}
		}
		$fields[ $z ]['product_categories'] = implode( ' | ', $category );
		$fields[ $z ]['product_short_desc'] = $short_desc;
		$fields[ $z ]['product_desc']       = $desc;
		++$z;
	}

	return array_filter( $fields );
}

/**
 * Exports products to a CSV file.
 *
 * @return string
 */
function simple_export_to_csv() {
	$file_path = ic_simple_export_file_name();
	$fp        = simple_prepare_csv_file( 'w', $file_path );
	$fields    = simple_prepare_products_to_export();
	fprintf( $fp, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
	$sep = apply_filters( 'simple_csv_separator', ';' );
	foreach ( $fields as $field ) {
		fputcsv( $fp, $field, $sep, '"', '\\' );
	}
	simple_close_csv_file( $fp );

	return ic_simple_export_file_url();
}

/**
 * Provides the export CSV file.
 */
function ic_simple_csv_provide_export() {
	$file_path = ic_simple_export_file_name();
	ic_simple_csv_provide_file( $file_path );
}

/**
 * Provides the import template CSV file.
 */
function ic_simple_csv_provide_import_sample() {
	$file_path = ic_simple_import_file_name();
	ic_simple_csv_provide_file( $file_path );
}

/**
 * Streams a CSV file to the browser.
 *
 * @param string $file_path CSV file path.
 */
function ic_simple_csv_provide_file( $file_path ) {
	if ( ! current_user_can( 'read_private_products' ) ) {
		echo wp_kses_post( implecode_warning( __( "You don't have permission to read the exported file.", 'ecommerce-product-catalog' ) ) );

		return;
	}
	if ( file_exists( $file_path ) ) {
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $file_path ) );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
		exit;
	}
}

/**
 * Returns the export file URL.
 *
 * @return string
 */
function ic_simple_export_file_url() {
	return admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv&provide_export_csv=1' );
}

/**
 * Returns the import template file URL.
 *
 * @return string
 */
function ic_simple_import_template_file_url() {
	return admin_url( 'edit.php?post_type=al_product&page=product-settings.php&tab=product-settings&submenu=csv&provide_import_sample=1' );
}

/**
 * Returns the import file path.
 *
 * @return string
 */
function ic_simple_import_file_name() {
	$csv_temp  = ic_simple_csv_temp_folder();
	$file_name = md5( $csv_temp ) . '-import.csv';

	return $csv_temp . '/' . $file_name;
}

/**
 * Returns the export file path.
 *
 * @return string
 */
function ic_simple_export_file_name() {
	$csv_temp  = ic_simple_csv_temp_folder();
	$file_name = md5( $csv_temp ) . '-export.csv';

	return $csv_temp . '/' . $file_name;
}

/**
 * Returns the CSV temp folder path.
 *
 * @return string
 */
function ic_simple_csv_temp_folder() {
	$csv_temp   = wp_upload_dir( null, false );
	$csv_folder = $csv_temp['basedir'] . '/ic-simple-csv';
	if ( ! file_exists( $csv_folder ) && ! is_dir( $csv_folder ) ) {
		mkdir( $csv_folder );
		$htaccess_data = 'Order deny,allow
Deny from all';
		file_put_contents( $csv_folder . '/.htaccess', $htaccess_data );
		$index_data = '<?php
// Silence is golden.';
		file_put_contents( $csv_folder . '/index.php', $index_data );
	}

	return $csv_folder;
}

add_filter( 'simple_csv_separator', 'get_simple_separator' );

/**
 * Defines the simple CSV separator.
 *
 * @return string
 */
function get_simple_separator() {
	if ( function_exists( 'get_currency_settings' ) ) {
		$product_currency_settings = get_currency_settings();
		if ( ',' === $product_currency_settings['dec_sep'] ) {
			$sep = ';';
		} else {
			$sep = ',';
		}
	} else {
		$sep = ',';
	}

	return $sep;
}

if ( ! function_exists( 'get_product_image_id' ) ) {

	/**
	 * Returns an attachment ID for a product image URL.
	 *
	 * @param string $attachment_url Image URL.
	 *
	 * @return int|false|null
	 */
	function get_product_image_id( $attachment_url = '' ) {
		global $wpdb;
		$attachment_id = false;
		if ( '' === $attachment_url ) {
			return;
		}
		$cache                   = ic_get_global( 'ic_cat_db_image_id_from_url' );
		$oryginal_attachment_url = $attachment_url;
		if ( empty( $cache ) ) {
			$cache = array();
		} elseif ( ! empty( $cache[ $oryginal_attachment_url ] ) ) {
			return intval( $cache[ $oryginal_attachment_url ] );
		}
		$upload_dir_paths = wp_upload_dir( null, false );
		if ( false !== strpos( $attachment_url, $upload_dir_paths['baseurl'] ) ) {
			$attachment_url    = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url );
			$attachment_url    = str_replace( $upload_dir_paths['baseurl'] . '/', '', $attachment_url );
				$attachment_id = intval( $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = %s AND wposts.post_type = 'attachment'", $attachment_url ) ) );

			$cache[ $oryginal_attachment_url ] = $attachment_id;
			ic_save_global( 'ic_cat_db_image_id_from_url', $cache );
		}

		return $attachment_id;
	}

}

add_filter( 'upload_mimes', 'ic_csv_mime', 99 );

/**
 * Adds the CSV mime type.
 *
 * @param array $mimes Allowed mime types.
 *
 * @return array
 */
function ic_csv_mime( $mimes ) {
	if ( empty( $mimes['csv'] ) ) {
		$mimes['csv'] = 'text/csv';
	}

	return $mimes;
}

require_once __DIR__ . '/class-ic-epc-import-post-type.php';

$ic_epc_import_post_types = new IC_EPC_Import_Post_Type();
