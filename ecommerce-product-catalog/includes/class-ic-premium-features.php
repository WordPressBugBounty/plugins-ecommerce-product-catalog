<?php
/**
 * Feature details for the Extensions page and locked-setting notes.
 *
 * Reads public, read-only feature data (name, description, guide and the
 * extensions that provide it) from the impleCode docs API by feature key.
 *
 * @package ecommerce-product-catalog/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'IC_Premium_Features', false ) ) {

	/** Neutral feature-details client and Extensions page filter view. */
	final class IC_Premium_Features {
		const DEFAULT_REMOTE = 'https://implecode.com';
		const TRANSIENT      = 'ic_premium_feature_';
		const PREFETCH       = 'ic_premium_feature_prefetch';
		const STYLE          = 'ic-premium-features';
		const EXTENSIONS     = 'edit.php?post_type=al_product&page=extensions.php';

		/** Register hooks. */
		public static function boot() {
			add_filter( 'ic_settings_lock_extension_names', array( __CLASS__, 'lock_names' ), 10, 2 );
			add_action( self::PREFETCH, array( __CLASS__, 'prefetch' ) );
		}

		/**
		 * Normalize a feature key.
		 *
		 * @param mixed $raw Raw key.
		 * @return string Key or '' when invalid.
		 */
		public static function key( $raw ) {
			if ( ! is_string( $raw ) ) {
				return '';
			}
			$key = sanitize_key( $raw );
			return ( '' !== $key && strlen( $key ) <= 100 ) ? $key : '';
		}

		/**
		 * Feature API base URL (absolute https URL without credentials, query or fragment).
		 *
		 * @return string
		 */
		public static function remote_url() {
			$url = apply_filters( 'ic_premium_features_remote_url', self::DEFAULT_REMOTE );
			if ( ! is_string( $url ) ) {
				return self::DEFAULT_REMOTE;
			}
			$url = trim( $url );
			if ( '' === $url ) {
				return self::DEFAULT_REMOTE;
			}
			$parts = wp_parse_url( $url );
			if (
				! is_array( $parts ) ||
				'https' !== strtolower( (string) ( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ) ) ||
				empty( $parts['host'] ) ||
				isset( $parts['user'] ) ||
				isset( $parts['pass'] ) ||
				isset( $parts['query'] ) ||
				isset( $parts['fragment'] )
			) {
				return self::DEFAULT_REMOTE;
			}
			return untrailingslashit( $url );
		}

		/**
		 * Feature API URL.
		 *
		 * @param string $key Feature key.
		 * @return string
		 */
		public static function api_url( $key ) {
			return self::remote_url() . '/wp-json/implecode/v1/features/' . rawurlencode( $key );
		}

		/**
		 * Guide link that works without the API (redirects to the feature guide).
		 *
		 * @param string $key Feature key.
		 * @return string
		 */
		public static function guide_link( $key ) {
			return add_query_arg( 'ic_feature_guide', $key, self::remote_url() . '/' );
		}

		/**
		 * Cached result.
		 *
		 * @param string $key Feature key.
		 * @return array|null
		 */
		public static function cached( $key ) {
			$key = self::key( $key );
			if ( '' === $key ) {
				return null;
			}
			$stored = get_site_transient( self::TRANSIENT . $key );
			return ( is_array( $stored ) && isset( $stored['status'] ) ) ? $stored : null;
		}

		/**
		 * Cached or fetched result.
		 *
		 * @param string $key Feature key.
		 * @return array|null Null for an invalid key.
		 */
		public static function get( $key ) {
			$key = self::key( $key );
			if ( '' === $key ) {
				return null;
			}
			$cached = self::cached( $key );
			return null !== $cached ? $cached : self::fetch( $key );
		}

		/**
		 * Fetch and cache one feature.
		 *
		 * @param string $key Feature key.
		 * @return array
		 */
		public static function fetch( $key ) {
			$response = wp_remote_get(
				self::api_url( $key ),
				array(
					'timeout'             => 5,
					'redirection'         => 2,
					'limit_response_size' => 65536,
				)
			);
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$result   = array( 'status' => 'offline' );
			$ttl      = HOUR_IN_SECONDS;
			if ( 200 === $code ) {
				$feature = self::normalize( json_decode( wp_remote_retrieve_body( $response ), true ), $key );
				if ( null !== $feature ) {
					$result = array(
						'status'  => 'ok',
						'feature' => $feature,
					);
					$ttl    = WEEK_IN_SECONDS;
				}
			} elseif ( 404 === $code ) {
				$result = array( 'status' => 'unknown' );
				$ttl    = DAY_IN_SECONDS;
			}
			set_site_transient( self::TRANSIENT . $key, $result, $ttl );
			return $result;
		}

		/**
		 * Validate and sanitize remote feature data.
		 *
		 * @param mixed  $data Decoded body.
		 * @param string $key  Requested key.
		 * @return array|null
		 */
		public static function normalize( $data, $key ) {
			if ( ! is_array( $data ) || ! isset( $data['key'], $data['name'] ) || $key !== $data['key'] || ! is_string( $data['name'] ) ) {
				return null;
			}
			$name = sanitize_text_field( $data['name'] );
			if ( '' === $name ) {
				return null;
			}
			$extensions = array();
			$remote     = ( isset( $data['extensions'] ) && is_array( $data['extensions'] ) ) ? $data['extensions'] : array();
			foreach ( $remote as $extension ) {
				if ( count( $extensions ) >= 20 ) {
					break;
				}
				if ( ! is_array( $extension ) ) {
					continue;
				}
				$ext_name = self::text( $extension, 'name' );
				$slug     = isset( $extension['slug'] ) && is_string( $extension['slug'] ) ? sanitize_key( $extension['slug'] ) : '';
				if ( '' === $ext_name || '' === $slug ) {
					continue;
				}
				$extensions[] = array(
					'name'        => $ext_name,
					'slug'        => $slug,
					'url'         => self::url( $extension, 'url' ),
					'description' => self::text( $extension, 'description' ),
				);
			}
			return array(
				'key'         => $key,
				'name'        => $name,
				'description' => self::text( $data, 'description' ),
				'guide_url'   => self::url( $data, 'guide_url' ),
				'extensions'  => $extensions,
			);
		}

		/**
		 * Sanitized text field from remote data.
		 *
		 * @param array  $data  Data.
		 * @param string $field Field.
		 * @return string
		 */
		private static function text( $data, $field ) {
			return ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) ? sanitize_text_field( $data[ $field ] ) : '';
		}

		/**
		 * Sanitized http(s) URL from remote data.
		 *
		 * @param array  $data  Data.
		 * @param string $field Field.
		 * @return string
		 */
		private static function url( $data, $field ) {
			return ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) ? esc_url_raw( $data[ $field ], array( 'http', 'https' ) ) : '';
		}

		/**
		 * Install state of a plugin directory.
		 *
		 * @param string $slug Plugin directory.
		 * @return array{0:string,1:string} State (active|installed|missing) and plugin file.
		 */
		public static function plugin_state( $slug ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( array_keys( (array) get_plugins() ) as $file ) {
				if ( dirname( $file ) === $slug ) {
					return array( is_plugin_active( $file ) ? 'active' : 'installed', $file );
				}
			}
			return array( 'missing', '' );
		}

		/**
		 * Extensions page URL without the feature filter.
		 *
		 * @return string
		 */
		public static function clear_url() {
			$page = class_exists( 'IC_Settings_Lock', false ) ? IC_Settings_Lock::EXTENSIONS_PAGE : self::EXTENSIONS;
			return remove_query_arg( 'feature', admin_url( $page ) );
		}

		/**
		 * View for the current Extensions request.
		 *
		 * @return array{filtered:bool,html:string}
		 */
		public static function request_view() {
			$view = array(
				'filtered' => false,
				'html'     => '',
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
			if ( ! isset( $_GET['feature'] ) || ! is_string( $_GET['feature'] ) ) {
				return $view;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
			$key = self::key( sanitize_key( wp_unslash( $_GET['feature'] ) ) );
			if ( '' === $key ) {
				return $view;
			}
			$result = self::get( $key );
			$status = isset( $result['status'] ) ? $result['status'] : '';
			if ( 'ok' === $status && ! empty( $result['feature'] ) ) {
				self::enqueue();
				return array(
					'filtered' => true,
					'html'     => self::filtered_html( $result['feature'] ),
				);
			}
			if ( 'offline' === $status ) {
				$view['html'] = '<div class="ic-feature-offline notice notice-info inline"><p>' . esc_html__( 'Extension details for this feature can\'t be loaded right now.', 'ecommerce-product-catalog' ) . ' <a href="' . esc_url( self::guide_link( $key ) ) . '">' . esc_html__( 'Open the guide on implecode.com', 'ecommerce-product-catalog' ) . '</a></p></div>';
			}
			return $view;
		}

		/**
		 * Filtered Extensions markup.
		 *
		 * @param array $feature Normalized feature.
		 * @return string
		 */
		public static function filtered_html( $feature ) {
			$clear = self::clear_url();
			/* translators: %s: Feature name. */
			$chip  = sprintf( __( 'Feature: %s', 'ecommerce-product-catalog' ), $feature['name'] );
			$html  = '<div class="ic-feature-filter" data-feature="' . esc_attr( $feature['key'] ) . '">';
			$html .= '<p class="ic-feature-filter-bar"><a class="ic-feature-chip" href="' . esc_url( $clear ) . '">' . esc_html( $chip ) . ' <span aria-hidden="true">&times;</span><span class="screen-reader-text">' . esc_html__( 'Clear the feature filter', 'ecommerce-product-catalog' ) . '</span></a> <a class="ic-feature-show-all" href="' . esc_url( $clear ) . '">' . esc_html__( 'Show all extensions', 'ecommerce-product-catalog' ) . '</a></p>';
			$html .= '<div class="ic-feature-box"><b>' . esc_html( $feature['name'] ) . '</b><p>' . esc_html( $feature['description'] );
			if ( '' !== $feature['guide_url'] ) {
				$html .= ' <a href="' . esc_url( $feature['guide_url'] ) . '">' . esc_html__( 'Read the guide', 'ecommerce-product-catalog' ) . '</a>';
			}
			$html .= '</p></div><div class="ic-feature-extensions">';
			foreach ( $feature['extensions'] as $extension ) {
				$html .= self::card( $extension );
			}
			return $html . '</div></div>';
		}

		/**
		 * One extension card.
		 *
		 * @param array $extension Normalized extension.
		 * @return string
		 */
		private static function card( $extension ) {
			list( $state, $file ) = self::plugin_state( $extension['slug'] );
			$name                 = esc_html( $extension['name'] );
			$title                = '' !== $extension['url'] ? '<a href="' . esc_url( $extension['url'] ) . '">' . $name . '</a>' : $name;
			if ( 'active' === $state ) {
				$state_html = '<span class="ic-feature-tag ic-feature-tag-ok">' . esc_html__( 'Active', 'ecommerce-product-catalog' ) . '</span>';
			} elseif ( 'installed' === $state ) {
				$state_html = '<span class="ic-feature-tag ic-feature-tag-ok">' . esc_html__( 'Installed, not active', 'ecommerce-product-catalog' ) . '</span>';
				if ( current_user_can( 'activate_plugins' ) ) {
					$activate    = wp_nonce_url(
						add_query_arg(
							array(
								'ic_cat_activation' => 1,
								'action'            => 'activate',
								'plugin'            => rawurlencode( $file ),
							),
							admin_url( 'plugins.php' )
						),
						'activate-plugin_' . $file
					);
					$state_html .= ' <a class="button ic-feature-activate" href="' . esc_url( $activate ) . '">' . esc_html__( 'Activate', 'ecommerce-product-catalog' ) . '</a>';
				}
			} elseif ( '' !== $extension['url'] ) {
				$state_html = '<a class="button ic-feature-get" href="' . esc_url( $extension['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get it', 'ecommerce-product-catalog' ) . '</a>';
			} else {
				$state_html = '';
			}
			return '<div class="ic-feature-extension ic-feature-extension-' . esc_attr( $state ) . '"><h4>' . $title . '</h4><p>' . esc_html( $extension['description'] ) . '</p>' . $state_html . '</div>';
		}

		/** Enqueue the filter view style (late enqueue prints in the admin footer). */
		public static function enqueue() {
			if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
				wp_register_style( self::STYLE, false, array(), '1' );
				wp_add_inline_style( self::STYLE, self::css() );
			}
			wp_enqueue_style( self::STYLE );
		}

		/** Filter view CSS. */
		public static function css() {
			return '.ic-feature-filter{margin:10px 0 0}'
				. '.ic-feature-filter-bar{margin:0}'
				. '.ic-feature-chip{display:inline-flex;gap:6px;align-items:center;border:1px solid #2271b1;border-radius:12px;padding:1px 9px;font-size:12.5px;color:#2271b1;background:#f0f6fc;text-decoration:none}'
				. '.ic-feature-show-all{margin-left:8px;color:#2271b1;text-decoration:underline}'
				. '.ic-feature-box{background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:10px 14px;margin-top:8px}'
				. '.ic-feature-box b{font-size:14px}'
				. '.ic-feature-box p{margin:4px 0 0;color:#50575e}'
				. '.ic-feature-extensions{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:12px}'
				. '.ic-feature-extension{background:#fff;border:1px solid #c3c4c7;padding:12px}'
				. '.ic-feature-extension h4{margin:0 0 6px;font-size:14px}'
				. '.ic-feature-extension h4 a{color:#2271b1;text-decoration:none}'
				. '.ic-feature-extension p{margin:0 0 10px;color:#50575e;font-size:12.5px;line-height:1.5}'
				. '.ic-feature-tag{display:inline-block;font-size:11px;font-weight:600;border-radius:8px;padding:1px 7px;text-decoration:none}'
				. '.ic-feature-tag-ok{background:#edfaef;color:#00703c}'
				. '.ic-feature-activate{margin-left:6px}'
				. '@media(max-width:782px){.ic-feature-extensions{grid-template-columns:1fr}}';
		}

		/**
		 * Extension names for a locked-setting note (cache only, never blocking HTTP).
		 *
		 * @param array  $names   Names already supplied.
		 * @param string $feature Feature key.
		 * @return array
		 */
		public static function lock_names( $names, $feature ) {
			if ( ! empty( $names ) ) {
				return $names;
			}
			$key = self::key( $feature );
			if ( '' === $key ) {
				return $names;
			}
			$cached = self::cached( $key );
			if ( null === $cached ) {
				if ( false === wp_next_scheduled( self::PREFETCH, array( $key ) ) ) {
					wp_schedule_single_event( time(), self::PREFETCH, array( $key ) );
				}
				return $names;
			}
			if ( 'ok' !== $cached['status'] || empty( $cached['feature']['extensions'] ) ) {
				return $names;
			}
			return array_slice( wp_list_pluck( $cached['feature']['extensions'], 'name' ), 0, 2 );
		}

		/**
		 * Prefetch one feature into the cache.
		 *
		 * @param string $key Feature key.
		 */
		public static function prefetch( $key ) {
			self::get( self::key( $key ) );
		}
	}

	IC_Premium_Features::boot();
}
