<?php
/**
 * Neutral email design and preview contract.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders store emails without changing the Classic wrapper contract. */
final class IC_Email_Design {
	const OPTION = 'ic_email_design';
	const ACCENT = '#2f6b9a';
	/** Neutral feature key that unlocks the custom header text and footer text/link. */
	const PREMIUM_FEATURE = 'custom-email-header-and-footer';
	/**
	 * Temporary design options for the current render.
	 *
	 * @var array|null
	 */
	private static $context_options = null;

	/**
	 * Feature setting keys kept when a feature settings form is saved without them.
	 *
	 * @var array
	 */
	private static $kept = array();

	/** Initialize before activation/version markers can change. */
	public static function boot() {
		self::initialize();
		add_action( 'phpmailer_init', array( __CLASS__, 'alternate' ), 1000 );
		add_filter( 'ic_email_template_heading', array( __CLASS__, 'apply_heading' ), 10, 2 );
	}

	/** Persist the first observed install cohort, independently of option absence. */
	public static function initialize() {
		$saved = get_option( self::OPTION, null );
		if ( is_array( $saved ) && isset( $saved['schema'], $saved['cohort'] ) ) {
			return;
		}
		$existing = false;
		foreach ( array( 'ic_epc_first_activation', 'first_activation_version', 'ecommerce_product_catalog_ver' ) as $marker ) {
			if ( get_option( $marker ) ) {
				$existing = true;
			}
		}
		// A malformed saved design is conservatively treated as an existing store.
		$existing                     = $existing || null !== $saved;
		$defaults                     = self::defaults();
		$defaults['cohort']           = $existing ? 'existing' : 'new';
		$defaults['layout']           = $existing ? 'classic' : 'modern';
		$defaults['modern_activated'] = ! $existing;
		if ( is_array( $saved ) ) {
			$defaults = array_merge( $defaults, self::sanitize( $saved, $defaults ) );
		}
		$defaults['schema'] = 1;
		update_option( self::OPTION, $defaults, false );
	}

	/** Neutral default branding. */
	public static function defaults() {
		return array(
			'schema'           => 1,
			'cohort'           => 'existing',
			'layout'           => 'classic',
			'modern_activated' => false,
			'style'            => 'clean',
			'header'           => 'name',
			'header_text'      => '',
			'logo_id'          => 0,
			'accent'           => self::ACCENT,
			'footer_text'      => __( 'This email is a service from [site_name].', 'ecommerce-product-catalog' ),
			'footer_label'     => '',
			'footer_url'       => '',
		);
	}

	/** Read stored options, tolerating corrupt individual values. */
	public static function options() {
		$saved = get_option( self::OPTION, array() );

		$base = self::defaults();
		if ( is_array( $saved ) ) {
			$base['cohort']           = 'new' === ( $saved['cohort'] ?? '' ) ? 'new' : 'existing';
			$base['modern_activated'] = ! empty( $saved['modern_activated'] );
		}
		$options = self::sanitize( is_array( $saved ) ? $saved : array(), $base );
		return null === self::$context_options ? $options : array_merge( $options, self::$context_options );
	}

	/** Scalar-only settings sanitation; immutable cohort fields come from the base.
	 *
	 * @param mixed      $input Settings input.
	 * @param array|null $base Existing sanitized base.
	 */
	public static function sanitize( $input, $base = null ) {
		$base  = is_array( $base ) ? array_merge( self::defaults(), $base ) : self::options();
		$input = is_array( $input ) ? $input : array();
		$get   = static function ( $key, $fallback = '' ) use ( $input ) {
			return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : $fallback;
		};
		foreach ( array(
			'layout' => array( 'classic', 'modern' ),
			'header' => array( 'name', 'logo', 'text' ),
			'style'  => array( 'clean', 'elegant', 'bold' ),
		) as $key => $allowed ) {
			$value        = $get( $key, $base[ $key ] );
			$base[ $key ] = in_array( $value, $allowed, true ) ? $value : $base[ $key ];
		}
		$color = sanitize_hex_color( $get( 'accent', $base['accent'] ) );
		if ( $color && 4 === strlen( $color ) ) {
			$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
		}
		$base['accent']       = $color ? strtolower( $color ) : self::ACCENT;
		$id                   = absint( $get( 'logo_id', (string) $base['logo_id'] ) );
		$base['logo_id']      = $id && 'attachment' === get_post_type( $id ) && in_array( get_post_mime_type( $id ), array( 'image/png', 'image/jpeg' ), true ) ? $id : 0;
		$base['header_text']  = sanitize_text_field( $get( 'header_text', $base['header_text'] ) );
		$base['footer_text']  = sanitize_textarea_field( $get( 'footer_text', $base['footer_text'] ) );
		$base['footer_label'] = sanitize_text_field( $get( 'footer_label', $base['footer_label'] ) );
		$base['footer_url']   = esc_url_raw( $get( 'footer_url', $base['footer_url'] ), array( 'http', 'https' ) );
		if ( 'modern' === $base['layout'] ) {
			$base['modern_activated'] = true;
		}
		return $base;
	}

	/** Render Modern or return null to execute the original Classic bytes.
	 *
	 * @param string $message Body.
	 * @param string $title Subject title.
	 * @param string $sender_name Sender name.
	 * @param string $outside Additional markup.
	 * @param array  $overrides Option overrides.
	 */
	public static function render( $message, $title, $sender_name = '', $outside = '', $overrides = array() ) {
		$options = apply_filters( 'ic_email_design_options', self::effective_options(), $message, $title );
		$options = array_merge( is_array( $options ) ? $options : self::effective_options(), $overrides );
		if ( 'modern' !== ( $options['layout'] ?? 'classic' ) ) {
			return null;
		}
		return self::modern( $message, $title, $options, $outside );
	}

	/**
	 * Options the renderer may use for this store.
	 *
	 * Stored values are kept as saved. Without a plugin that provides the
	 * custom header and footer feature, the header uses the site name or logo,
	 * the footer uses the default text and no footer link is shown. Premium code
	 * attaches to the neutral `ic_email_design_effective_options` filter
	 * ( $effective, $stored ) and returns the stored values it provides.
	 *
	 * @param array|null $options Requested options merged over the stored ones.
	 * @return array
	 */
	public static function effective_options( $options = null ) {
		$options   = is_array( $options ) ? array_merge( self::options(), $options ) : self::options();
		$effective = $options;
		if ( 'text' === ( $effective['header'] ?? '' ) ) {
			$effective['header'] = 'name';
		}
		$effective['header_text']  = '';
		$effective['footer_text']  = self::defaults()['footer_text'];
		$effective['footer_label'] = '';
		$effective['footer_url']   = '';
		$filtered                  = apply_filters( 'ic_email_design_effective_options', $effective, $options );
		return is_array( $filtered ) ? array_merge( $effective, $filtered ) : $effective;
	}

	/**
	 * Render a fixed store presentation, or the original compatibility presentation.
	 *
	 * @param string $message Body markup.
	 * @param string $title Subject title.
	 * @param array  $options Code-owned render options.
	 * @param string $outside Additional markup.
	 * @return string
	 */
	public static function modern( $message, $title, $options = array(), $outside = '' ) {
		$options = array_merge( self::effective_options(), $options );
		if ( ! empty( $options['clean_compatibility'] ) ) {
			return self::compatibility_modern( $message, $title, $options, $outside );
		}
		$style           = in_array( $options['style'] ?? null, array( 'clean', 'elegant', 'bold' ), true ) ? $options['style'] : 'clean';
		$sanitized_color = sanitize_hex_color( is_string( $options['accent'] ) ? $options['accent'] : '' );
		$color           = $sanitized_color ? $sanitized_color : self::ACCENT;
		if ( 4 === strlen( $color ) ) {
			$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
		}
		$options['accent'] = strtolower( $color );
		$color             = $options['accent'];
		$foreground        = self::accent_foreground( $color );
		$tint              = self::accent_tint( $color );
		$html              = self::compatibility_modern( $message, $title, $options, $outside );
		$elegant           = 'elegant' === $style;
		$bold              = 'bold' === $style;
		$footer            = $elegant ? '#8a7f72' : ( $bold ? '#626975' : '#6b7680' );
		$html              = str_replace( '<!--ic-email-modern-->', '<!--ic-email-modern--><!--ic-email-style-' . $style . '-->', $html );
		$html              = str_replace( 'padding:28px 16px 18px;', 'padding:22px 14px 14px;padding:clamp(16px,calc(16px + (100vw - 390px) * .00674),22px) clamp(10px,calc(10px + (100vw - 390px) * .0045),14px) 14px;', $html );
		$html              = str_replace( '<td style="padding:14px 6% 28px;', '<td data-ic-email="body" style="padding:14px 6% 28px;', $html );
		$html              = str_replace( 'padding:22px 6% 0;font-size:15px;font-weight:700;color:#263238;letter-spacing:.01em;', $elegant ? 'padding:26px 24px 16px;text-align:center;font-size:21px;font-weight:400;letter-spacing:.32em;text-transform:uppercase;color:#1c1a17;border-bottom:1px solid #e7dfd2;' : ( $bold ? 'padding:20px 24px 22px;background:' . $color . ';color:' . $foreground . ';font-size:17px;font-weight:800;letter-spacing:.01em;' : 'padding:18px 24px 0;font-size:15px;font-weight:700;color:#263238;' ), $html );
		$html              = str_replace( 'padding:14px 6% 28px;font-size:16px;line-height:1.6;color:#3c4650;overflow-wrap:anywhere;', $elegant ? 'padding:22px 30px 26px;font-size:14.5px;line-height:1.7;color:#3d3833;text-align:center;overflow-wrap:anywhere;' : ( $bold ? 'padding:18px 24px 24px;font-size:14.5px;line-height:1.6;color:#2b2522;overflow-wrap:anywhere;' : 'padding:10px 24px 22px;font-size:14.5px;line-height:1.6;color:#3c4650;overflow-wrap:anywhere;' ), $html );
		$html              = str_replace( 'font-size:12px;line-height:1.6;color:#6b7680;', 'font-size:11.5px;line-height:1.6;color:' . $footer . ';' . ( $elegant ? 'font-family:Georgia,serif;font-style:italic;' : '' ), $html );
		$html              = str_replace( 'margin:14px auto 0;', 'margin:12px auto 0;', $html );
		$html              = str_replace( 'style="color:#6b7680;">', 'style="color:' . $footer . ';">', $html );
		if ( $elegant ) {
			$html = str_replace( array( 'background:#f4f5f7;', 'background:#ffffff;border:1px solid #e3e6ea;border-radius:8px;', 'font-family:Arial,Helvetica,sans-serif;', 'color:#6b7680;', 'border-bottom:1px solid #eef0f2' ), array( 'background:#f5f1ea;', 'background:#fffdf9;border:1px solid #e7dfd2;border-radius:0;', 'font-family:Georgia,serif;', 'color:' . $footer . ';', 'border-bottom:1px solid #eee6da' ), $html );
			$html = str_replace( 'display:block;max-width:200px;', 'display:block;margin:0 auto;max-width:200px;', $html );
		}
		if ( $bold ) {
			$page_tint    = self::accent_tint( $color, 0.05 );
			$divider_tint = self::accent_tint( $color, 0.20 );
			$html         = str_replace( array( 'background:#f4f5f7;', 'border:1px solid #e3e6ea;border-radius:8px;', 'border-bottom:1px solid #eef0f2', 'padding:8px 0;font-weight:700', 'padding:8px 0;text-align:right;font-weight:700' ), array( 'background:' . $page_tint . ';', 'border:0;border-radius:16px;', 'border-bottom:2px dotted ' . $divider_tint, 'padding:8px 0;font-size:16px;font-weight:800', 'padding:8px 0;text-align:right;font-size:16px;font-weight:800' ), $html );
			$html         = str_replace( 'style="color:' . $footer . ';">', 'style="color:' . $footer . ';text-decoration:underline;">', $html );
			$html         = preg_replace( '/<(body|table)\b/i', '<$1 data-ic-email="canvas"', $html, 2 );
			$html         = preg_replace( '~<(td|th)\b([^>]*\bstyle="[^"]*border-bottom:2px dotted ' . $divider_tint . '[^"]*"[^>]*)>~i', '<$1 data-ic-email="item"$2>', $html );
		}
		if ( $elegant || $bold ) {
			$html = preg_replace( '#<tr><td data-ic-email="accent"[^>]*>.*?</td></tr>#is', '', $html, 1 );
		}
		$heading_style = $elegant ? 'font-weight:400;font-size:23px;line-height:1.35;font-style:italic;color:#1c1a17;margin:4px 0 6px;' : ( $bold ? 'font-size:25px;line-height:1.2;margin:10px 0 0;font-weight:800;color:' . $foreground . ';' : 'font-size:20px;line-height:1.35;margin:6px 0 12px;color:#1f2933;font-weight:700;' );
		// Only renderer-owned or unformatted headings receive fixed presentation.
		$html = str_replace( 'font-size:22px;line-height:1.35;margin:6px 0 14px;color:#1f2933;font-weight:700;', $heading_style, $html );
		if ( $bold && preg_match( '#<h1\b[^>]*>.*?</h1>#is', $html, $heading ) && ( false !== strpos( $heading[0], $heading_style ) || false !== strpos( $heading[0], 'data-ic-email="title"' ) ) ) {
			$html = preg_replace( '#<h1\b[^>]*>.*?</h1>#is', '', $html, 1 );
			$html = preg_replace_callback(
				'#(<td data-ic-email="header"[^>]*>)(.*?)(</td>)#is',
				static function ( $matches ) use ( $heading ) {
					return $matches[1] . $matches[2] . $heading[0] . $matches[3];
				},
				$html,
				1
			);
		}
		if ( $elegant ) {
			$html = preg_replace( '#(</h1>)#i', '$1<table data-ic-email="rule" role="presentation" align="center" width="40" cellspacing="0" cellpadding="0" style="width:40px;margin:12px auto 16px;"><tr><td style="height:1px;line-height:1px;font-size:0;background:#a8875a;"></td></tr></table>', $html, 1 );
		}
		// Keep visible separators and line breaks identical across styles for plaintext.
		$button = 'display:inline-block;background:' . $color . ';color:' . $foreground . ';font-weight:700;padding:10px 20px;border-radius:6px;text-decoration:none;margin:0;text-align:center;max-width:100%;box-sizing:border-box;min-width:fit-content;width:clamp(0px,calc((460px - 100vw) * 1000),100%);';
		if ( $elegant ) {
			$button = 'display:inline-block;background:' . $color . ';color:' . $foreground . ';padding:11px 26px;font-size:12.5px;letter-spacing:.18em;text-transform:uppercase;text-decoration:none;margin:0;border-radius:0;text-align:center;max-width:100%;box-sizing:border-box;min-width:fit-content;width:clamp(0px,calc((460px - 100vw) * 1000),100%);';
		}
		if ( $bold ) {
			$button = 'display:block;background:' . $color . ';color:' . $foreground . ';font-weight:800;padding:13px 20px;border-radius:30px;font-size:15.5px;text-decoration:none;margin:0;text-align:center;max-width:100%;box-sizing:border-box;';
		}
		$styles = array(
			'ic-email-button' => $button,
			'ic-email-box'    => $elegant ? 'display:block;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#8a7f72;margin:0 0 16px;' : ( $bold ? 'display:inline-block;background:' . $tint . ';color:' . self::accent_foreground( $tint ) . ';border-radius:20px;padding:4px 12px;font-weight:700;font-size:13px;margin:0 0 12px;' : 'display:block;background:#f4f7fa;border:1px solid #e3e9ef;border-radius:6px;padding:8px 12px;margin:0 0 14px;font-size:13.5px;' ),
			'ic-email-label'  => $elegant || $bold ? 'display:inline;font:inherit;color:inherit;margin-right:4px;' : 'display:block;font-size:11px;color:#6b7680;text-transform:uppercase;letter-spacing:.05em;',
		);
		$html   = preg_replace_callback(
			'/<([a-z][a-z0-9]*)\b([^>]*\bclass=[\'"]([^\'"]*)[\'"][^>]*)>/i',
			static function ( $matches ) use ( $styles ) {
				$classes = preg_split( '/\s+/', $matches[3] );
				if ( 'a' === strtolower( $matches[1] ) && in_array( 'remove-plain', $classes, true ) ) {
					$classes[] = 'ic-email-button';
				}
				foreach ( $classes as $class ) {
					if ( isset( $styles[ $class ] ) ) {
						return '<' . $matches[1] . preg_replace( '/\sstyle=[\'"][^\'"]*[\'"]/i', '', $matches[2] ) . ' style="' . esc_attr( $styles[ $class ] ) . '">';
					}
				}
				return $matches[0];
			},
			$html
		);
		$html   = preg_replace( '#(<div\b[^>]*class="ic-email-box"[^>]*>.*?<b) style="[^"]*"#is', '$1 style="' . ( $elegant || $bold ? 'color:inherit;font-weight:inherit;' : 'color:#1f2933;font-weight:700;' ) . 'word-break:break-all;"', $html );
		// KSES may remove a final semicolon from a saved-template cell style.
		if ( ! $bold ) {
			$html = str_replace( array( 'padding:8px 0;font-weight:700', 'padding:8px 0;text-align:right;font-weight:700' ), array( 'padding:8px 0;font-weight:700;color:' . ( $elegant ? '#1c1a17' : '#1f2933' ), 'padding:8px 0;text-align:right;font-weight:700;color:' . ( $elegant ? '#1c1a17' : '#1f2933' ) ), $html );
		}
		$html = str_replace( 'border-collapse:collapse;font-size:14px;', 'border-collapse:collapse;text-align:left;font-size:' . ( $bold ? '14.5px' : '14px' ) . ';', $html );
		return $html;
	}

	/** Select a WCAG AA foreground for an opaque accent.
	 *
	 * @param string $color Six-digit hex color.
	 * @return string
	 */
	public static function accent_foreground( $color ) {
		$channels = array();
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$value      = hexdec( substr( $color, $offset, 2 ) ) / 255;
			$channels[] = $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}
		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		return 1.05 / ( $luminance + 0.05 ) >= 4.5 ? '#ffffff' : '#000000';
	}

	/** Mix an accent with opaque white for a fixed presentation tint.
	 *
	 * @param string $color Six-digit hex color.
	 * @param float  $amount Accent proportion; the remainder is opaque white.
	 * @return string
	 */
	public static function accent_tint( $color, $amount = 0.08 ) {
		$tint = '#';
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$tint .= sprintf( '%02x', (int) round( hexdec( substr( $color, $offset, 2 ) ) * $amount + 255 * ( 1 - $amount ) ) );
		}
		return $tint;
	}

	/** Always-Modern pure rendering entry point for extension-owned messages.
	 *
	 * @param string $message Body.
	 * @param string $title Subject title.
	 * @param array  $options Options.
	 * @param string $outside Additional markup.
	 */
	private static function compatibility_modern( $message, $title, $options = array(), $outside = '' ) {
		$options = array_merge( self::effective_options(), $options );
		$color   = sanitize_hex_color( is_string( $options['accent'] ) ? $options['accent'] : '' );
		$color   = $color ? $color : self::ACCENT;
		$name    = isset( $options['header_name'] ) && is_scalar( $options['header_name'] ) ? (string) $options['header_name'] : get_bloginfo( 'name' );
		$brand   = esc_html( $name );
		if ( ! empty( $options['header_secondary'] ) && is_scalar( $options['header_secondary'] ) ) {
			$brand .= ' <span style="font-weight:400;color:#6b7680;">' . esc_html( $options['header_secondary'] ) . '</span>';
		}
		if ( 'text' === $options['header'] && ! isset( $options['header_name'] ) && is_scalar( $options['header_text'] ?? null ) && '' !== trim( (string) $options['header_text'] ) ) {
			$brand = esc_html( $options['header_text'] );
		}
		if ( 'logo' === $options['header'] ) {
			$id = absint( $options['logo_id'] );
			if ( 'attachment' === get_post_type( $id ) && in_array( get_post_mime_type( $id ), array( 'image/png', 'image/jpeg' ), true ) ) {
				$url = wp_get_attachment_image_url( $id, 'full' );
				if ( $url ) {
					$brand = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $name ) . '" style="display:block;max-width:200px;max-height:60px;width:auto;height:auto;border:0;" />';
				}
			}
		}
		// Code-owned semantic roles get inline styles; arbitrary edited words remain untouched.
		$message = self::style_body( $message, $color );
		$heading = preg_match( '/<h1\b/i', $message ) ? '' : '<h1 style="font-size:22px;line-height:1.35;margin:6px 0 14px;color:#1f2933;font-weight:700;">' . esc_html( $title ) . '</h1>';
		$footer  = nl2br(
			strtr(
				esc_html( (string) $options['footer_text'] ),
				array(
					'[site_name]' => '<a href="' . esc_url( home_url( '/' ) ) . '" style="color:#6b7680;">' . esc_html( get_bloginfo( 'name' ) ) . '</a>',
					'[site_url]'  => esc_html( site_url() ),
				)
			)
		);
		$link    = '';
		$url     = esc_url( (string) $options['footer_url'], array( 'http', 'https' ) );
		if ( $url && '' !== trim( (string) $options['footer_label'] ) ) {
			$link = '<a href="' . $url . '" style="color:#6b7680;">' . esc_html( $options['footer_label'] ) . '</a>';
		}
		$service = '';
		if ( ! empty( $options['service_url'] ) && ! empty( $options['service_name'] ) ) {
			/* translators: %s: linked service name. */
			$service = ' &middot; ' . sprintf( esc_html__( 'This email is a service from %s.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( $options['service_url'] ) . '" style="color:#6b7680;">' . esc_html( $options['service_name'] ) . '</a>' );
		}
		return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $title ) . '</title></head><body style="margin:0;padding:0;background:#f4f5f7;"><!--ic-email-modern--><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;"><tr><td align="center" style="padding:28px 16px 18px;"><table role="presentation" width="560" cellspacing="0" cellpadding="0" style="width:100%;max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e6ea;border-radius:8px;border-spacing:0;overflow:hidden;"><tr><td data-ic-email="accent" style="height:4px;line-height:4px;font-size:1px;background:' . esc_attr( $color ) . ';border-radius:8px 8px 0 0;">&nbsp;</td></tr><tr><td data-ic-email="header"' . ( isset( $options['header_name'] ) ? ' data-ic-fixed-brand="1"' : '' ) . ' style="padding:22px 6% 0;font-size:15px;font-weight:700;color:#263238;letter-spacing:.01em;">' . $brand . '</td></tr><tr><td style="padding:14px 6% 28px;font-size:16px;line-height:1.6;color:#3c4650;overflow-wrap:anywhere;">' . $heading . $message . '</td></tr></table><table role="presentation" width="560" cellspacing="0" cellpadding="0" style="width:100%;max-width:560px;margin:14px auto 0;"><tr><td align="center" style="font-size:12px;line-height:1.6;color:#6b7680;"><span data-ic-email="footer-text"' . ( isset( $options['header_name'] ) ? ' data-ic-fixed-brand="1"' : '' ) . '>' . $footer . '</span><span data-ic-email="footer-link"' . ( isset( $options['header_name'] ) ? ' data-ic-fixed-brand="1"' : '' ) . '>' . ( $link ? '<br>' . $link : '' ) . '</span><span data-ic-email="service">' . $service . '</span></td></tr></table></td></tr></table>' . wp_kses_post( $outside ) . '</body></html>';
	}

	/** Safe body markup, with styles scoped to neutral email roles.
	 *
	 * @param string $body Body markup.
	 * @param string $color Accent color.
	 */
	public static function style_body( $body, $color = self::ACCENT ) {
		$color = sanitize_hex_color( $color );
		$color = $color ? $color : self::ACCENT;
		$body  = wp_kses_post( (string) $body );
		// Preserve the established esc_url representation of access-link hrefs.
		$body   = preg_replace_callback(
			'/\bhref=([\'"])(.*?)\1/is',
			static function ( $match ) {
				return 'href=' . $match[1] . esc_url( html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . $match[1];
			},
			$body
		);
		$styles = array(
			'ic-email-button' => 'display:inline-block;background:' . $color . ';color:#fff;font-weight:600;font-size:16px;padding:12px 24px;border-radius:6px;text-decoration:none;margin:4px 0 18px;text-align:center;max-width:100%;box-sizing:border-box;',
			'ic-email-box'    => 'display:block;background:#f4f7fa;border:1px solid ' . $color . '33;border-radius:6px;padding:10px 14px;margin:0 0 18px;font-size:15px;',
			'ic-email-label'  => 'display:block;font-size:12px;color:#6b7680;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;',
			'ic-email-note'   => 'font-size:13px;line-height:1.55;color:#6b7680;margin:0 0 10px;',
			'ic-email-link'   => 'font-size:13px;color:' . $color . ';word-break:break-all;',
		);
		$body   = preg_replace_callback(
			'/<([a-z][a-z0-9]*)\b([^>]*\bclass=[\'"]([^\'"]*)[\'"][^>]*)>/i',
			static function ( $match ) use ( $styles ) {
				$classes = preg_split( '/\s+/', $match[3] );
				if ( in_array( 'remove-plain', $classes, true ) && 'a' === strtolower( $match[1] ) ) {
					$classes[] = 'ic-email-button';
				}
				foreach ( $classes as $class ) {
					if ( isset( $styles[ $class ] ) ) {
						$attributes = preg_replace( '/\sstyle=[\'"][^\'"]*[\'"]/i', '', $match[2] );
						return '<' . $match[1] . $attributes . ' style="' . esc_attr( $styles[ $class ] ) . '">';
					}
				}
				return $match[0];
			},
			$body
		);
		return preg_replace_callback(
			'/<(h1|p)\b([^>]*)>/i',
			static function ( $match ) {
				if ( preg_match( '/\bstyle\s*=/i', $match[2] ) ) {
					return $match[0];
				}
				$style = 'h1' === strtolower( $match[1] ) ? 'font-size:22px;line-height:1.35;margin:6px 0 14px;color:#1f2933;font-weight:700;' : 'margin:0 0 14px;';
				return '<' . $match[1] . $match[2] . ' style="' . $style . '">';
			},
			$body
		);
	}

	/** Shared labeled website/order box with an optional semantic state chip.
	 *
	 * @param string $label Label.
	 * @param string $address Address.
	 * @param string $status Status.
	 * @param string $state State.
	 */
	public static function box( $label, $address, $status = '', $state = 'ok' ) {
		$colors = array(
			'ok'   => array( '#e6f4ea', '#1e6b2f', '#b9dfc3' ),
			'warn' => array( '#fdf3dc', '#7a5a06', '#efd99a' ),
			'bad'  => array( '#fcebea', '#a1262a', '#f0c2c3' ),
		);
		$c      = $colors[ $state ] ?? $colors['ok'];
		$chip   = $status ? ' <span style="display:inline-block;font-size:12px;font-weight:600;padding:2px 10px;border-radius:10px;margin-left:8px;vertical-align:2px;text-transform:none;letter-spacing:0;background:' . $c[0] . ';color:' . $c[1] . ';border:1px solid ' . $c[2] . ';">' . esc_html( $status ) . '</span>' : '';
		return '<div class="ic-email-box"><small class="ic-email-label">' . esc_html( $label ) . $chip . '</small><b style="color:#1f2933;font-weight:600;word-break:break-all;">' . esc_html( $address ) . '</b></div>';
	}

	/** Shared accessible link button.
	 *
	 * @param string $label Label.
	 * @param string $url URL.
	 */
	public static function button( $label, $url ) {
		return '<a class="ic-email-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/** Convert the complete visible message to words plus visible destination URLs.
	 *
	 * @param string $html HTML.
	 */
	public static function plaintext( $html ) {
		if ( false !== strpos( (string) $html, '<!--ic-email-style-' ) ) {
			$html = preg_replace( '#<table data-ic-email="rule"[^>]*>.*?</table>#is', '', $html );
			if ( false !== strpos( $html, '<!--ic-email-style-bold-->' ) && preg_match( '#<h1\b[^>]*>.*?</h1>#is', $html, $heading ) ) {
				$html = preg_replace( '#<h1\b[^>]*>.*?</h1>#is', '', $html, 1 );
				$html = preg_replace_callback(
					'#(<td data-ic-email="body"[^>]*>)#i',
					static function ( $matches ) use ( $heading ) {
						return $matches[1] . $heading[0];
					},
					$html,
					1
				);
			}
		}
		$html = preg_replace( '/<head\b[^>]*>.*?<\/head>/is', '', (string) $html );
		$html = preg_replace_callback(
			'/<a\b[^>]*href=[\'"]([^\'"]*)[\'"][^>]*>(.*?)<\/a>/is',
			static function ( $match ) {
				$text = trim( wp_strip_all_tags( $match[2] ) );
				$url  = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				return $text . ( $text === $url ? '' : ' (' . $url . ')' );
			},
			$html
		);
		$html = preg_replace_callback(
			'/<img\b[^>]*alt=[\'"]([^\'"]*)[\'"][^>]*>/i',
			static function ( $match ) {
				return $match[1];
			},
			$html
		);
		$html = preg_replace( '/<(?:br\s*\/?|\/(?:p|h[1-6]|div|tr|table))\s*>/i', "\n", $html );
		$html = preg_replace( '/<\/t[dh]\s*>/i', '  ', $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		return trim( preg_replace( '/\n[ \t]*\n(?:[ \t]*\n)+/', "\n\n", $text ) );
	}

	/** Derive the alternative from this send's body only, with no pending global.
	 *
	 * @param object $mailer Mailer.
	 */
	public static function alternate( $mailer ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer public API.
		if ( false !== strpos( (string) $mailer->Body, '<!--ic-email-modern-->' ) ) {
			$mailer->AltBody = self::plaintext( $mailer->Body );
		}
		// phpcs:enable
	}

	/** Code-owned definitions supplied only by initialized features. */
	public static function previews() {
		$items = apply_filters( 'ic_email_design_previews', array() );
		$items = array_filter(
			is_array( $items ) ? $items : array(),
			static function ( $item ) {
				return is_array( $item ) && is_string( $item['label'] ?? null ) && is_callable( $item['callback'] ?? null );
			}
		);
		foreach ( $items as &$item ) {
			$item['group'] = is_string( $item['group'] ?? null ) ? $item['group'] : __( 'Other emails', 'ecommerce-product-catalog' );
		}
		unset( $item );
		// uasort preserves both stable public IDs and insertion order for equal keys.
		uasort(
			$items,
			static function ( $left, $right ) {
				return strnatcasecmp( $left['group'], $right['group'] ) ?: ( (int) ( $left['order'] ?? 0 ) <=> (int) ( $right['order'] ?? 0 ) );
			}
		);
		return $items;
	}

	/** Use the registered Modern heading independently of the email subject.
	 *
	 * @param string $message Composed feature body.
	 * @param string $id Registered feature email ID.
	 * @return string
	 */
	public static function message_heading( $message, $id ) {
		if ( 'modern' !== self::options()['layout'] ) {
			return $message;
		}
		$definition = self::previews()[ $id ] ?? array();
		$heading    = '';
		$content    = self::content( $definition );
		if ( $content && $content['heading'] ) {
			$stored  = self::lookup( $content['heading'] );
			$heading = $stored[0] && is_string( $stored[1] ) ? trim( $stored[1] ) : '';
		}
		if ( '' === $heading ) {
			$heading = $definition['modern_title'] ?? '';
		}
		if ( ! is_string( $heading ) || '' === $heading ) {
			return $message;
		}
		return '<h1 data-ic-email="title">' . esc_html( $heading ) . '</h1>' . $message;
	}

	/**
	 * Use an admin-set heading as the Modern title of an already composed body.
	 *
	 * Classic or an empty heading returns the body unchanged.
	 *
	 * @param string $message Composed body.
	 * @param string $heading Plain-text heading.
	 * @return string
	 */
	public static function apply_heading( $message, $heading ) {
		$heading = is_string( $heading ) ? trim( $heading ) : '';
		if ( '' === $heading || ! is_string( $message ) || 'modern' !== self::options()['layout'] ) {
			return $message;
		}
		$title  = '<h1 data-ic-email="title">' . esc_html( $heading ) . '</h1>';
		$count  = 0;
		$result = preg_replace_callback(
			'#<h1\b[^>]*>.*?</h1>#is',
			static function () use ( $title ) {
				return $title;
			},
			$message,
			1,
			$count
		);
		return $count && is_string( $result ) ? $result : $title . $message;
	}

	/**
	 * Normalized editable-content descriptor of a registered email.
	 *
	 * Registration key `content`: option_group, optional settings_filter,
	 * subject/text (required) and optional heading storage as
	 * array( 'option' => string, 'key' => string|null ), optional text_sanitize
	 * callable, values callable returning the effective subject and text,
	 * tokens (token => description), settings_label and description.
	 *
	 * @param string|array $id Registered ID or registry item.
	 * @return array|null
	 */
	public static function content( $id ) {
		$item = is_array( $id ) ? $id : ( is_string( $id ) ? ( self::previews()[ $id ] ?? null ) : null );
		if ( ! is_array( $item ) || ! is_array( $item['content'] ?? null ) ) {
			return null;
		}
		$content = $item['content'];
		$group   = $content['option_group'] ?? null;
		if ( ! is_string( $group ) || '' === $group || ! is_callable( $content['values'] ?? null ) ) {
			return null;
		}
		$fields = array();
		foreach ( array( 'subject', 'text', 'heading' ) as $field ) {
			$storage = self::storage( $content[ $field ] ?? null );
			if ( ! $storage && 'heading' !== $field ) {
				return null;
			}
			$fields[ $field ] = $storage;
		}
		$tokens = array();
		foreach ( is_array( $content['tokens'] ?? null ) ? $content['tokens'] : array() as $token => $description ) {
			if ( is_string( $token ) && '' !== $token ) {
				$tokens[ $token ] = is_string( $description ) ? $description : '';
			}
		}
		$label = $content['settings_label'] ?? $item['settings_label'] ?? $item['label'] ?? '';
		$about = $content['description'] ?? $item['description'] ?? '';
		return array_merge(
			$fields,
			array(
				'option_group'    => $group,
				'settings_filter' => is_string( $content['settings_filter'] ?? null ) ? $content['settings_filter'] : '',
				'text_sanitize'   => is_callable( $content['text_sanitize'] ?? null ) ? $content['text_sanitize'] : 'wp_kses_post',
				'values'          => $content['values'],
				'tokens'          => $tokens,
				'settings_label'  => is_string( $label ) ? $label : '',
				'description'     => is_string( $about ) ? $about : '',
				'capability'      => (string) apply_filters( 'option_page_capability_' . $group, 'manage_options' ),
			)
		);
	}

	/**
	 * Validate one storage address.
	 *
	 * @param mixed $storage Declared storage.
	 * @return array|null
	 */
	private static function storage( $storage ) {
		if ( ! is_array( $storage ) || ! is_string( $storage['option'] ?? null ) || '' === $storage['option'] ) {
			return null;
		}
		$key = $storage['key'] ?? null;
		if ( null !== $key && ( ! is_string( $key ) || '' === $key ) ) {
			return null;
		}
		return array(
			'option' => $storage['option'],
			'key'    => $key,
		);
	}

	/**
	 * Raw stored value of one storage address.
	 *
	 * @param array $storage Storage address.
	 * @return array Existence flag and raw value.
	 */
	private static function lookup( $storage ) {
		$missing = new stdClass();
		$raw     = get_option( $storage['option'], $missing );
		if ( $raw === $missing ) {
			return array( false, null );
		}
		if ( null === $storage['key'] ) {
			return array( true, $raw );
		}
		return is_array( $raw ) && array_key_exists( $storage['key'], $raw ) ? array( true, $raw[ $storage['key'] ] ) : array( false, null );
	}

	/**
	 * Raw stored value, or null when absent.
	 *
	 * @param array $storage Storage address.
	 * @return mixed
	 */
	public static function stored( $storage ) {
		$storage = self::storage( $storage );
		return $storage ? self::lookup( $storage )[1] : null;
	}

	/**
	 * Exact stored state of every declared field, including absent versus empty.
	 *
	 * @param array $content Normalized descriptor.
	 * @return array
	 */
	public static function stored_state( $content ) {
		$state = array();
		foreach ( array( 'subject', 'heading', 'text' ) as $field ) {
			if ( ! empty( $content[ $field ] ) ) {
				list( $exists, $value ) = self::lookup( $content[ $field ] );
				$state[ $field ]        = array(
					'exists' => $exists,
					'value'  => $value,
				);
			}
		}
		return $state;
	}

	/**
	 * Hash of the exact stored state, used to reject stale editor saves.
	 *
	 * @param array $content Normalized descriptor.
	 * @return string
	 */
	public static function content_hash( $content ) {
		return hash( 'sha256', (string) wp_json_encode( self::stored_state( $content ) ) );
	}

	/**
	 * Values the editor shows in the stored layout (or the active rendering context).
	 *
	 * @param array $content Normalized descriptor.
	 * @return array
	 */
	public static function content_values( $content ) {
		return self::effective_content( $content );
	}

	/**
	 * Effective subject, heading and text the email sends in the given layout.
	 *
	 * The single source for the editor, the no-JS form, the settings row and the save comparison.
	 *
	 * @param array       $content Normalized descriptor.
	 * @param string|null $layout  'classic', 'modern' or null for the stored layout / active context.
	 * @return array
	 */
	public static function effective_content( $content, $layout = null ) {
		if ( 'classic' === $layout || 'modern' === $layout ) {
			return self::with_options(
				array_merge( (array) self::$context_options, array( 'layout' => $layout ) ),
				static function () use ( $content ) {
					return self::read_values( $content );
				}
			);
		}
		return self::read_values( $content );
	}

	/**
	 * Remove the editor's run markers from a draft text.
	 *
	 * U+E000 + run index + U+E001 opens a run and U+E002 closes it. Markers only travel
	 * in draft previews and are never stored.
	 *
	 * @param string $text Marked text.
	 * @return string
	 */
	public static function strip_marks( $text ) {
		$stripped = preg_replace( '/\x{E000}\d{1,4}\x{E001}|\x{E002}/u', '', (string) $text );
		return null === $stripped ? (string) $text : $stripped;
	}

	/**
	 * Effective subject and text the feature sends, plus the stored heading.
	 *
	 * @param array $content Normalized descriptor.
	 * @return array
	 */
	private static function read_values( $content ) {
		$values  = call_user_func( $content['values'] );
		$values  = is_array( $values ) ? $values : array();
		$heading = '';
		if ( $content['heading'] ) {
			$stored  = self::lookup( $content['heading'] );
			$heading = $stored[0] && is_string( $stored[1] ) ? $stored[1] : '';
		}
		return array(
			'subject' => is_scalar( $values['subject'] ?? null ) ? (string) $values['subject'] : '',
			'heading' => $heading,
			'text'    => is_scalar( $values['text'] ?? null ) ? (string) $values['text'] : '',
		);
	}

	/**
	 * Write only the supplied fields; every other stored byte stays identical.
	 *
	 * An empty heading removes its key, so the registered title applies again.
	 *
	 * @param array $content Normalized descriptor.
	 * @param array $changes Field => sanitized value.
	 * @return true|WP_Error
	 */
	public static function write_content( $content, array $changes ) {
		$options = array();
		foreach ( $changes as $field => $value ) {
			if ( empty( $content[ $field ] ) || ! is_string( $value ) ) {
				continue;
			}
			$options[ $content[ $field ]['option'] ][] = array( $content[ $field ]['key'], $value, $field );
		}
		foreach ( $options as $option => $writes ) {
			$missing = new stdClass();
			$raw     = get_option( $option, $missing );
			$value   = $raw === $missing ? null : $raw;
			foreach ( $writes as $write ) {
				list( $key, $new, $field ) = $write;
				if ( null === $key ) {
					$value = $new;
					continue;
				}
				if ( ! is_array( $value ) ) {
					if ( null !== $value && false !== $value && '' !== $value ) {
						return new WP_Error( 'invalid_storage', __( 'This email text cannot be saved here.', 'ecommerce-product-catalog' ) );
					}
					$value = array();
				}
				if ( 'heading' === $field && '' === $new ) {
					unset( $value[ $key ] );
				} else {
					$value[ $key ] = $new;
				}
			}
			// Feature sanitizers would re-sanitize unrelated legacy keys; store the intended array as is.
			$keep = static function () use ( $value ) {
				return $value;
			};
			add_filter( 'sanitize_option_' . $option, $keep, PHP_INT_MAX );
			try {
				update_option( $option, $value );
			} finally {
				remove_filter( 'sanitize_option_' . $option, $keep, PHP_INT_MAX );
			}
		}
		return true;
	}

	/**
	 * Run a callback while the declared storage reads the unsaved draft values.
	 *
	 * @param array    $content Normalized descriptor.
	 * @param array    $draft Field => sanitized value.
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public static function with_draft( $content, array $draft, $callback ) {
		$options = array();
		$keys    = array();
		foreach ( array( 'subject', 'heading', 'text' ) as $field ) {
			if ( empty( $content[ $field ] ) || ! is_string( $draft[ $field ] ?? null ) ) {
				continue;
			}
			$storage                         = $content[ $field ];
			$options[ $storage['option'] ][] = array( $storage['key'], $draft[ $field ], $field );
			if ( null !== $storage['key'] ) {
				$keys[] = array( $storage['key'], $draft[ $field ], $field );
			}
		}
		$apply   = static function ( $value, $writes ) {
			foreach ( $writes as $write ) {
				list( $key, $new, $field ) = $write;
				if ( null === $key ) {
					$value = $new;
					continue;
				}
				$value = is_array( $value ) ? $value : array();
				if ( 'heading' === $field && '' === $new ) {
					unset( $value[ $key ] );
				} else {
					$value[ $key ] = $new;
				}
			}
			return $value;
		};
		$filters = array();
		foreach ( $options as $option => $writes ) {
			$filter    = static function ( $value ) use ( $apply, $writes ) {
				return $apply( $value, $writes );
			};
			$filters[] = array( 'option_' . $option, $filter, PHP_INT_MAX );
			$filters[] = array( 'default_option_' . $option, $filter, PHP_INT_MAX );
		}
		if ( $content['settings_filter'] && $keys ) {
			$filters[] = array(
				$content['settings_filter'],
				static function ( $settings ) use ( $apply, $keys ) {
					return is_array( $settings ) ? $apply( $settings, $keys ) : $settings;
				},
				1,
			);
		}
		foreach ( $filters as $filter ) {
			add_filter( $filter[0], $filter[1], $filter[2] );
		}
		try {
			return call_user_func( $callback );
		} finally {
			foreach ( $filters as $filter ) {
				remove_filter( $filter[0], $filter[1], $filter[2] );
			}
		}
	}

	/**
	 * Keep moved email fields when a feature settings form (options.php) no longer posts them.
	 *
	 * @param string     $option Option name.
	 * @param array|null $keys Array keys, or null for a whole option.
	 * @return void
	 */
	public static function keep_on_settings_save( $option, $keys ) {
		if ( ! is_string( $option ) || '' === $option ) {
			return;
		}
		if ( ! isset( self::$kept[ $option ] ) ) {
			self::$kept[ $option ] = array(
				'whole' => false,
				'keys'  => array(),
			);
		}
		if ( false === has_filter( 'pre_update_option_' . $option, array( __CLASS__, 'kept_value' ) ) ) {
			add_filter( 'pre_update_option_' . $option, array( __CLASS__, 'kept_value' ), 20, 3 );
		}
		if ( null === $keys ) {
			self::$kept[ $option ]['whole'] = true;
			return;
		}
		self::$kept[ $option ]['keys'] = array_values( array_unique( array_merge( self::$kept[ $option ]['keys'], array_filter( (array) $keys, 'is_string' ) ) ) );
	}

	/**
	 * Restore moved fields absent from an options.php settings save.
	 *
	 * @param mixed  $value New value.
	 * @param mixed  $old Old value.
	 * @param string $option Option name.
	 * @return mixed
	 */
	public static function kept_value( $value, $old, $option ) {
		if ( 'options.php' !== ( $GLOBALS['pagenow'] ?? '' ) || ! isset( self::$kept[ $option ] ) ) {
			return $value;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- options.php verified the settings nonce; only key presence is read, the value is never used.
		$posted = $_POST[ $option ] ?? null;
		$rules  = self::$kept[ $option ];
		if ( $rules['whole'] ) {
			return null === $posted ? $old : $value;
		}
		if ( ! $rules['keys'] ) {
			return $value;
		}
		// The form posts nothing of this option: keep the stored value exactly (an absent option stays absent).
		if ( null === $posted ) {
			return $old;
		}
		if ( ! is_array( $old ) || ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $rules['keys'] as $key ) {
			if ( is_array( $posted ) && array_key_exists( $key, $posted ) ) {
				continue;
			}
			if ( array_key_exists( $key, $old ) ) {
				$value[ $key ] = $old[ $key ];
			} else {
				unset( $value[ $key ] );
			}
		}
		return $value;
	}

	/** Comparison only: never modify stored or delivered bytes.
	 *
	 * @param string $text Text to compare.
	 */
	public static function normalize_default( $text ) {
		$normalized = preg_replace( '/[\s\p{Z}]+/u', ' ', (string) $text );
		return trim( null === $normalized ? (string) $text : $normalized );
	}

	/** Whether supplied raw storage differs from every shipped locale default.
	 *
	 * @param string|null $raw Raw stored body.
	 * @param array       $defaults Shipped defaults.
	 */
	public static function customized( $raw, $defaults ) {
		if ( null === $raw ) {
			return false;
		}
		foreach ( $defaults as $default ) {
			if ( self::normalize_default( $raw ) === self::normalize_default( $default ) ) {
				return false;
			}
		}
		return true;
	}

	/** Resolve supplied feature strings, retaining exact customized storage.
	 *
	 * @param string|null $raw Raw stored value.
	 * @param string      $classic Classic default.
	 * @param string      $modern Modern default.
	 * @param array       $defaults Additional shipped defaults.
	 */
	public static function effective( $raw, $classic, $modern, $defaults = array() ) {
		if ( self::customized( $raw, array_merge( $defaults, array( $classic, $modern ) ) ) ) {
			return $raw;
		}
		return 'modern' === self::options()['layout'] ? $modern : $classic;
	}

	/** Temporary rendering context, restored even if an extension throws.
	 *
	 * @param array    $options Temporary design options.
	 * @param callable $callback Rendering callback.
	 */
	public static function with_options( $options, $callback ) {
		$previous              = self::$context_options;
		self::$context_options = $options;
		try {
			return call_user_func( $callback );
		} finally {
			self::$context_options = $previous;
		}
	}

	/** Preview the shipping composer and shipping wrapper without sending.
	 *
	 * @param array $definition Feature preview definition.
	 * @param array $options Requested store design.
	 */
	public static function preview_email( $definition, $options ) {
		return self::with_options(
			$options,
			static function () use ( $definition, $options ) {
				$sample = call_user_func( $definition['callback'] );
				if ( ! is_array( $sample ) || ! is_string( $sample['body'] ?? null ) || ! is_string( $sample['subject'] ?? null ) ) {
					return new WP_Error( 'invalid_sample' );
				}
				$mode = $sample['render_mode'] ?? $definition['render_mode'] ?? 'template';
				if ( 'plain' === $mode ) {
					return '<!doctype html><html><head><meta charset="utf-8"></head><body><p>' . esc_html__( 'Sent as plain text; Email Design does not apply.', 'ecommerce-product-catalog' ) . '</p><pre style="font-family:monospace;white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( $sample['body'] ) . '</pre></body></html>';
				}
				$branding = static function ( $saved ) use ( $sample, $options ) {
					return array_merge( $saved, self::effective_options( $options ), $sample['options'] ?? array() );
				};
				add_filter( 'ic_email_design_options', $branding, PHP_INT_MAX );
				try {
					return ic_htmlize_email( $sample['body'], $sample['subject'], get_bloginfo( 'name' ) );
				} finally {
					remove_filter( 'ic_email_design_options', $branding, PHP_INT_MAX );
				}
			}
		);
	}

	/** Simple Modern product and total rows; callers own the data and calculation.
	 *
	 * @param array $rows Product rows.
	 * @param array $totals Total rows.
	 */
	public static function products_table( $rows, $totals = array() ) {
		$html = '<table class="ic-email-products" role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;font-size:14px;margin:0 0 14px;">';
		foreach ( $rows as $row ) {
			$html .= '<tr><td style="padding:7px 0;border-bottom:1px solid #eef0f2;">' . wp_kses_post( $row['name'] ) . ( null === ( $row['quantity'] ?? null ) ? '' : ' × ' . wp_kses_post( $row['quantity'] ) ) . '</td><td style="padding:7px 0;border-bottom:1px solid #eef0f2;text-align:right;">' . wp_kses_post( $row['amount'] ?? '' ) . '</td></tr>';
		}
		foreach ( $totals as $line ) {
			$html .= '<tr><td style="padding:8px 0;font-weight:700;">' . wp_kses_post( trim( $line['label'], " \t\n\r\0\x0B=:" ) ) . '</td><td style="padding:8px 0;text-align:right;font-weight:700;">' . wp_kses_post( $line['value'] ) . '</td></tr>';
		}
		return $html . '</table>';
	}

	/**
	 * Restyle the already enriched shipping summary without rerunning its hooks.
	 * Only a known plain table is compacted. Rich or unfamiliar structures retain
	 * every cell, image and note in a lightly styled compatibility presentation.
	 *
	 * @param string $html Composed shipping summary.
	 * @param array  $schema Known product table shape.
	 */
	public static function modern_product_summary( $html, $schema ) {
		if ( ! preg_match( '#<table\b[^>]*>(.*?)</table>#is', $html, $table ) ) {
			return $html; }
		$compact = empty( $schema['rich'] ) && false === stripos( $table[1], '<table' ) && false === stripos( $table[1], '<img' );
		preg_match_all( '#<tr\b[^>]*>(.*?)</tr>#is', $table[1], $matches );
		$compact = $compact && count( $matches[1] ) === $schema['rows'] + 1 && '' === trim( preg_replace( '#<tr\b[^>]*>.*?</tr>#is', '', $table[1] ) );
		$rows    = array();
		foreach ( $matches[1] as $index => $row ) {
			preg_match_all( '#<(td|th)\b([^>]*)>(.*?)</\1>#is', $row, $cells );
			if ( count( $cells[3] ) !== count( $schema['headers'] ) || preg_match( '/\b(?:colspan|rowspan)\s*=/i', $row ) || '' !== trim( preg_replace( '#<(td|th)\b[^>]*>.*?</\1>#is', '', $row ) ) ) {
				$compact = false;
				break; }
			if ( 0 === $index ) {
				foreach ( $schema['headers'] as $column => $label ) {
					if ( trim( html_entity_decode( wp_strip_all_tags( $cells[3][ $column ] ), ENT_QUOTES, 'UTF-8' ) ) !== $label ) {
						$compact = false; }
				}
				continue;
			}
			$rows[] = array(
				'name'     => $cells[3][0],
				'quantity' => null === $schema['quantity'] ? null : $cells[3][ $schema['quantity'] ],
				'amount'   => null === $schema['amount'] ? ( $schema['amounts'][ $index - 1 ] ?? '' ) : $cells[3][ $schema['amount'] ],
			);
		}
		if ( $compact ) {
			return preg_replace_callback(
				'#<table\b[^>]*>.*?</table>#is',
				static function () use ( $rows ) {
					return self::products_table( $rows );
				},
				$html,
				1
			);
		}
		return preg_replace_callback(
			'#<(table|td|th)\b([^>]*)>#i',
			static function ( $match_data ) {
				$tag        = strtolower( $match_data[1] );
				$style      = 'table' === $tag ? 'border:0;border-collapse:collapse;width:100%;font-size:14px;margin:0 0 14px;' : 'border:0;border-bottom:1px solid #eef0f2;padding:7px 6px;vertical-align:top;';
				$attributes = $match_data[2];
				if ( preg_match( '/\bstyle=([\'"])(.*?)\1/is', $attributes, $existing ) ) {
					$attributes = str_replace( $existing[0], 'style="' . esc_attr( $existing[2] . ';' . $style ) . '"', $attributes );
				} else {
					$attributes .= ' style="' . esc_attr( $style ) . '"'; }
				return '<' . $match_data[1] . $attributes . '>';
			},
			$html
		);
	}

	/** New neutral tokens; context is supplied by the sending feature.
	 *
	 * @param string $body Composed email body.
	 * @param array  $context Feature token values.
	 */
	public static function replace_order_tokens( $body, $context ) {
		$modern  = 'modern' === self::options()['layout'];
		$label   = $context['label'] ?? __( 'Order', 'ecommerce-product-catalog' );
		$number  = $context['number'] ?? '';
		$date    = $context['date'] ?? '';
		$summary = '';
		if ( '' !== (string) $number ) {
			$summary = $modern ? self::box( $label, '#' . $number . ' · ' . $date ) : esc_html( $label . ' #' . $number . ' · ' . $date );
		}
		$url    = esc_url( $context['url'] ?? '', array( 'http', 'https' ) );
		$button = '';
		if ( $url ) {
			$button = $modern ? self::button( $context['button_label'], $url ) : '<a href="' . $url . '">' . esc_html( $context['button_label'] ) . '</a>';
		}
		if ( $modern ) {
			$body = preg_replace( '#<p\b[^>]*>\s*\[order_summary\]\s*</p>#i', '[order_summary]', $body ); }
		$tokens = array(
			'[order_summary]' => $summary,
			'[order_button]'  => $button,
		);
		if ( ! shortcode_exists( 'customer_name' ) && isset( $context['customer_name'] ) ) {
			$tokens['[customer_name]'] = esc_html( $context['customer_name'] ); }
		return strtr( $body, $tokens );
	}
}

if ( ! function_exists( 'ic_email_design_render' ) ) {
	/** Dispatch a wrapper to the neutral renderer at send time.
	 *
	 * @param string $message Body.
	 * @param string $title Subject.
	 * @param string $sender_name Sender.
	 * @param string $outside Additional markup.
	 * @param array  $overrides Overrides.
	 */
	function ic_email_design_render( $message, $title, $sender_name, $outside = '', $overrides = array() ) {
		return IC_Email_Design::render( $message, $title, $sender_name, $outside, $overrides );
	}
}
IC_Email_Design::boot();
