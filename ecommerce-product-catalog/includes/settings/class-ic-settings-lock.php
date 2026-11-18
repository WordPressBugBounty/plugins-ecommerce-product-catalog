<?php
/**
 * Reusable locked-setting element for the settings framework.
 *
 * A setting row or a single option can be locked with a neutral feature key.
 * The setting is unlocked as soon as any active plugin declares that feature
 * through the neutral `ic_premium_features` filter.
 *
 * When a note is built without explicit extension names, the neutral
 * `ic_settings_lock_extension_names` filter ( array $names, string $feature_key )
 * may supply display names for the "Available with ..." text.
 *
 * @package ecommerce-product-catalog/includes/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'IC_Settings_Lock', false ) ) {

	/** Neutral locked-setting element. */
	final class IC_Settings_Lock {
		const EXTENSIONS_PAGE = 'edit.php?post_type=al_product&page=extensions.php';
		const STYLE_HANDLE    = 'ic-settings-lock';

		/**
		 * Options already protected against locked-value writes.
		 *
		 * @var array
		 */
		private static $protected = array();

		/** Register the style handle. */
		public static function boot() {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_style' ) );
		}

		/** Register the inline style handle and enqueue it on settings screens. */
		public static function register_style() {
			if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
				wp_register_style( self::STYLE_HANDLE, false, array(), '1' );
				wp_add_inline_style( self::STYLE_HANDLE, self::css() );
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && false !== strpos( (string) $screen->id, 'product-settings' ) ) {
				wp_enqueue_style( self::STYLE_HANDLE );
			}
		}

		/** Enqueue the element style (late enqueue prints in the admin footer). */
		public static function enqueue() {
			if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
				wp_register_style( self::STYLE_HANDLE, false, array(), '1' );
				wp_add_inline_style( self::STYLE_HANDLE, self::css() );
			}
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		/** Element CSS. */
		public static function css() {
			return '.ic-locked-setting label,.ic-locked-setting td:first-child,.ic-locked-setting .ic-email-design-label{color:#646970}'
				. '[data-ic-locked]{background:#f6f7f7;color:#8c8f94;border-color:#dcdcde}'
				. '.ic-locked-option{color:#8c8f94}'
				. '.ic-lock{display:inline-block;width:11px;height:9px;border:1.6px solid #8c8f94;border-radius:2px;position:relative;margin:0 6px 0 0;vertical-align:-1px;box-sizing:content-box}'
				. '.ic-lock:before{content:"";position:absolute;left:1.5px;top:-6px;width:5px;height:5px;border:1.6px solid #8c8f94;border-bottom:0;border-radius:4px 4px 0 0;box-sizing:content-box}'
				. '.ic-locked-note{display:block;margin:6px 0 0;font-size:12.5px;line-height:1.5;color:#50575e}'
				. '.ic-locked-note a{color:#2271b1;text-decoration:underline}';
		}

		/**
		 * Feature keys declared by active plugins.
		 *
		 * @return array
		 */
		public static function features() {
			$features = apply_filters( 'ic_premium_features', array() );
			if ( ! is_array( $features ) ) {
				return array();
			}
			$keys = array();
			foreach ( $features as $feature ) {
				$key = self::key( $feature );
				if ( '' !== $key ) {
					$keys[ $key ] = $key;
				}
			}
			return array_values( $keys );
		}

		/**
		 * Normalize a feature key.
		 *
		 * @param mixed $feature Feature key.
		 * @return string
		 */
		private static function key( $feature ) {
			return is_string( $feature ) ? sanitize_key( $feature ) : '';
		}

		/**
		 * Whether a feature is provided by an active plugin.
		 *
		 * @param string $feature Feature key.
		 * @return bool
		 */
		public static function is_unlocked( $feature ) {
			$key = self::key( $feature );
			return '' === $key || in_array( $key, self::features(), true );
		}

		/**
		 * Whether a feature is locked. An empty or invalid key is never locked.
		 *
		 * @param string $feature Feature key.
		 * @return bool
		 */
		public static function is_locked( $feature ) {
			return ! self::is_unlocked( $feature );
		}

		/**
		 * Products > Extensions URL carrying the feature key.
		 *
		 * @param string $feature Feature key.
		 * @return string
		 */
		public static function extensions_url( $feature ) {
			return add_query_arg( 'feature', self::key( $feature ), admin_url( self::EXTENSIONS_PAGE ) );
		}

		/**
		 * Note element ID for a setting field.
		 *
		 * @param string $field Field name.
		 * @return string
		 */
		public static function note_id( $field ) {
			return 'ic-locked-note-' . sanitize_html_class( str_replace( array( '[', ']' ), array( '-', '' ), (string) $field ) );
		}

		/**
		 * Lock icon markup.
		 *
		 * @return string
		 */
		public static function lock_icon() {
			return '<span class="ic-lock" aria-hidden="true"></span>';
		}

		/**
		 * Wrapper class for a single locked option (one radio choice).
		 *
		 * @return string
		 */
		public static function option_class() {
			return 'ic-locked-option';
		}

		/**
		 * One-line locked note with the extensions link.
		 *
		 * @param string $feature Feature key.
		 * @param string $note_id Note element ID.
		 * @param array  $extensions Optional extension names.
		 * @return string
		 */
		public static function note( $feature, $note_id, $extensions = array() ) {
			self::enqueue();
			if ( empty( $extensions ) ) {
				$extensions = apply_filters( 'ic_settings_lock_extension_names', array(), self::key( $feature ) );
				if ( ! is_array( $extensions ) ) {
					$extensions = array();
				}
			}
			$names = array();
			foreach ( (array) $extensions as $name ) {
				if ( is_scalar( $name ) && '' !== trim( (string) $name ) ) {
					$names[] = esc_html( (string) $name );
				}
			}
			if ( $names ) {
				/* translators: %s: Comma separated extension names. */
				$text = sprintf( esc_html__( 'Available with %s and other premium extensions.', 'ecommerce-product-catalog' ), implode( ', ', $names ) );
			} else {
				$text = esc_html__( 'Available with premium extensions.', 'ecommerce-product-catalog' );
			}
			return '<p class="ic-locked-note" id="' . esc_attr( $note_id ) . '">' . self::lock_icon() . $text . ' <a href="' . esc_url( self::extensions_url( $feature ) ) . '">' . esc_html__( 'See extensions', 'ecommerce-product-catalog' ) . '</a></p>';
		}

		/**
		 * Attributes for a locked control.
		 *
		 * @param string $feature Feature key.
		 * @param string $note_id Note element ID.
		 * @return string
		 */
		public static function control_attributes( $feature, $note_id ) {
			return ' disabled="disabled" aria-disabled="true" aria-describedby="' . esc_attr( $note_id ) . '" data-ic-locked="' . esc_attr( self::key( $feature ) ) . '"';
		}

		/**
		 * Whole locked row for table-based settings screens.
		 *
		 * @param array $args {
		 *     Row arguments.
		 *
		 *     @type string   $label      Plain text label.
		 *     @type string   $field      Field name.
		 *     @type string   $feature    Feature key.
		 *     @type array    $extensions Optional extension names.
		 *     @type callable $control    Receives the attribute string; returns escaped control HTML.
		 * }
		 * @return string
		 */
		public static function row( $args ) {
			$args    = wp_parse_args(
				$args,
				array(
					'label'      => '',
					'field'      => '',
					'feature'    => '',
					'extensions' => array(),
					'control'    => null,
				)
			);
			$note_id = self::note_id( $args['field'] );
			$control = is_callable( $args['control'] ) ? (string) call_user_func( $args['control'], self::control_attributes( $args['feature'], $note_id ) ) : '';
			if ( function_exists( 'ic_register_setting' ) ) {
				ic_register_setting( $args['label'], $args['field'] );
			}
			return '<tr class="ic-locked-setting"><td>' . self::lock_icon() . esc_html( $args['label'] ) . ':</td><td>' . $control . self::note( $args['feature'], $note_id, $args['extensions'] ) . '</td></tr>';
		}

		/**
		 * Lock declared for a field through the neutral `ic_settings_field_lock` filter.
		 *
		 * @param string $field Field name, e.g. option[key].
		 * @return array|null Array with feature, extensions and value keys, or null.
		 */
		public static function field_lock( $field ) {
			$lock = apply_filters( 'ic_settings_field_lock', null, $field );
			if ( is_string( $lock ) ) {
				$lock = array( 'feature' => $lock );
			}
			if ( ! is_array( $lock ) ) {
				return null;
			}
			$feature = self::key( $lock['feature'] ?? '' );
			if ( '' === $feature ) {
				return null;
			}
			$value = $lock['value'] ?? null;
			return array(
				'feature'    => $feature,
				'extensions' => is_array( $lock['extensions'] ?? null ) ? array_values( $lock['extensions'] ) : array(),
				'value'      => is_scalar( $value ) ? $value : null,
			);
		}

		/**
		 * Keep stored values for locked keys.
		 *
		 * @param mixed $input Submitted values.
		 * @param mixed $stored Stored values.
		 * @param array $locks Key => feature, or key => array( 'feature' => ..., 'value' => ... ).
		 * @return mixed
		 */
		public static function preserve( $input, $stored, $locks ) {
			if ( ! is_array( $input ) ) {
				return $input;
			}
			$stored = is_array( $stored ) ? $stored : array();
			foreach ( (array) $locks as $key => $lock ) {
				$feature = is_array( $lock ) ? ( $lock['feature'] ?? '' ) : $lock;
				if ( ! self::is_locked( $feature ) ) {
					continue;
				}
				if ( is_array( $lock ) && isset( $lock['value'] ) && null !== $lock['value'] ) {
					if ( ! array_key_exists( $key, $input ) || (string) $input[ $key ] !== (string) $lock['value'] ) {
						continue;
					}
				}
				if ( array_key_exists( $key, $stored ) ) {
					$input[ $key ] = $stored[ $key ];
				} else {
					unset( $input[ $key ] );
				}
			}
			return $input;
		}

		/**
		 * Protect an array option against locked-field writes.
		 *
		 * @param string $option Option name.
		 */
		public static function protect_option( $option ) {
			$option = (string) $option;
			if ( '' === $option ) {
				return;
			}
			if ( isset( self::$protected[ $option ] ) && false !== has_filter( 'pre_update_option_' . $option, self::$protected[ $option ] ) ) {
				return;
			}
			if ( ! isset( self::$protected[ $option ] ) ) {
				self::$protected[ $option ] = static function ( $value, $old_value ) use ( $option ) {
					if ( ! is_array( $value ) || ! is_array( $old_value ) ) {
						return $value;
					}
					$locks = array();
					foreach ( array_keys( $value + $old_value ) as $key ) {
						$lock = self::field_lock( $option . '[' . $key . ']' );
						if ( $lock ) {
							$locks[ $key ] = $lock;
						}
					}
					return $locks ? self::preserve( $value, $old_value, $locks ) : $value;
				};
			}
			add_filter( 'pre_update_option_' . $option, self::$protected[ $option ], 10, 2 );
		}
	}

	IC_Settings_Lock::boot();
}
