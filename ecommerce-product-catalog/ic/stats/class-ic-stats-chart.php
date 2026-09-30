<?php
/**
 * Accessible dependency-free SVG chart renderer.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! class_exists( 'IC_Stats_Chart' ) ) {
	/**
	 * Render chart models as accessible SVG and table markup.
	 */
	class IC_Stats_Chart {
		/**
		 * Series colour palette.
		 *
		 * @var array
		 */
		private static $palette = array( '#2271b1', '#008a20', '#b32d2e', '#8c5e00', '#6c2eb9', '#0a7c86', '#bd5a00' );
		/**
		 * Render a chart model.
		 *
		 * Recognised point keys: `period`, `value`, `metric`, `currency`, plus the
		 * optional `series` (legend label), `series_key` (stable colour key) and
		 * `label` (pre-formatted display string). Recognised arguments: `title`,
		 * `value_suffix`, `format` (callable float -> string), `decimals`, `ticks`,
		 * `x_label_max` and `baseline`. Every one of them is optional.
		 *
		 * @param array $model Chart model.
		 * @param array $args  Chart arguments.
		 * @return string
		 */
		public static function render( $model, $args = array() ) {
			$args   = (array) $args;
			$title  = esc_html( $args['title'] ?? __( 'Statistics chart', 'ecommerce-product-catalog' ) );
			$suffix = (string) ( $args['value_suffix'] ?? '' );
			$points = array_values( (array) ( $model['points'] ?? $model ) );
			$id     = sanitize_html_class( (string) ( $args['id'] ?? '' ) );
			if ( ! $points ) {
				return '';
			}
			$format = self::formatter( $points, $args, $suffix );
			$series = array();
			$groups = array();
			$values = array();
			foreach ( $points as $index => $point ) {
				$label                      = (string) ( $point['series'] ?? ( $point['metric'] ?? '' ) );
				$key                        = (string) ( $point['series_key'] ?? $label );
				$points[ $index ]['series'] = $label;
				$series_index               = array_search( $key, array_map( 'strval', array_keys( $series ) ), true );
				$points[ $index ]['color']  = self::$palette[ ( false === $series_index ? count( $series ) : $series_index ) % count( self::$palette ) ];
				$series[ $key ]             = array(
					'label' => $label,
					'color' => $points[ $index ]['color'],
				);
				$values[]                   = (float) ( $point['value'] ?? 0 );
				$groups[ (string) ( $point['period'] ?? '' ) ][] = $index;
			}
			// A chart that hid negative bars below an implicit zero floor is the bug
			// this scale replaces: the band always contains zero, so a negative bar
			// hangs below the baseline instead of being drawn as its own opposite.
			$min = (float) min( 0, min( $values ) );
			$max = (float) max( 0, max( $values ) );
			if ( $max - $min <= 0 ) {
				$max = $min + 1; }
			$span     = $max - $min;
			$baseline = round( 205 - ( ( 0 - $min ) / $span ) * 180, 2 );
			$out      = '<figure class="ic-stats-chart"' . ( $id ? ' id="' . esc_attr( $id ) . '" tabindex="-1"' : '' ) . '><figcaption>' . $title . '</figcaption><svg viewBox="0 0 720 240" role="img" aria-label="' . esc_attr( $title ) . '"><title>' . $title . '</title><desc>' . esc_html__( 'Values are also available in the data table below.', 'ecommerce-product-catalog' ) . '</desc>';
			$out     .= self::ticks( $args, $min, $span, $format );
			$slot     = 680 / max( 1, count( $groups ) );
			$step     = (int) max( 1, ceil( count( $groups ) / max( 1, (int) ( $args['x_label_max'] ?? 12 ) ) ) );
			$column   = 0;
			foreach ( $groups as $period => $indexes ) {
				$width = max( 2, ( $slot - 8 ) / max( 1, count( $indexes ) ) );
				foreach ( array_values( $indexes ) as $offset => $index ) {
					$point = $points[ $index ];
					$value = (float) ( $point['value'] ?? 0 );
					$top   = round( 205 - ( ( $value - $min ) / $span ) * 180, 2 );
					$x     = round( 30 + ( $column * $slot ) + 4 + ( $offset * $width ), 2 );
					$text  = isset( $point['label'] ) && '' !== $point['label'] ? (string) $point['label'] : call_user_func( $format, $value );
					// A bar carries no axis label of its own, so the period is named in the
					// tooltip whenever the consumer supplied one. Points without a period
					// keep the legacy "Series Value" string so other integrations still read.
					$tip = '' !== (string) ( $point['period'] ?? '' )
						/* translators: 1: period label, 2: series name, 3: formatted value. */
						? sprintf( __( '%1$s - %2$s: %3$s', 'ecommerce-product-catalog' ), (string) $point['period'], (string) $point['series'], $text )
						: $point['series'] . ' ' . $text;
					$out .= '<rect x="' . esc_attr( $x ) . '" y="' . esc_attr( min( $top, $baseline ) ) . '" width="' . esc_attr( round( max( 2, $width - 2 ), 2 ) ) . '" height="' . esc_attr( round( max( 1, abs( $baseline - $top ) ), 2 ) ) . '" class="ic-stats-chart-bar" style="fill:' . esc_attr( $point['color'] ) . '"><title>' . esc_html( $tip ) . '</title></rect>';
					if ( $width >= ( strlen( $text ) * 5.5 ) ) {
						$out .= '<text class="ic-stats-chart-value" x="' . esc_attr( round( $x + ( $width / 2 ), 2 ) ) . '" y="' . esc_attr( $value < 0 ? round( $top + 11, 2 ) : round( $top - 4, 2 ) ) . '" text-anchor="middle">' . esc_html( $text ) . '</text>'; }
				}
				if ( '' !== (string) $period && 0 === $column % $step ) {
					$out .= '<text class="ic-stats-chart-label" x="' . esc_attr( round( 30 + ( $column * $slot ) + ( $slot / 2 ), 2 ) ) . '" y="233" text-anchor="middle">' . esc_html( (string) $period ) . '</text>'; }
				++$column;
			}
			if ( false !== ( $args['baseline'] ?? true ) ) {
				$out .= '<line class="ic-stats-chart-baseline" x1="30" x2="710" y1="' . esc_attr( $baseline ) . '" y2="' . esc_attr( $baseline ) . '" />'; }
			return $out . '</svg>' . self::legend( $series ) . self::table( $points, $format, $id ) . '</figure>';
		}
		/**
		 * Build the y-axis gridlines and their labels.
		 *
		 * @param array    $args   Chart arguments.
		 * @param float    $min    Lowest plotted value.
		 * @param float    $span   Plotted value span.
		 * @param callable $format Value formatter.
		 * @return string
		 */
		private static function ticks( $args, $min, $span, $format ) {
			$ticks = max( 2, min( 8, (int) ( $args['ticks'] ?? 5 ) ) );
			$out   = '';
			for ( $tick = 0; $tick < $ticks; $tick++ ) {
				$value = $min + ( $span * $tick / ( $ticks - 1 ) );
				$y     = round( 205 - ( ( $value - $min ) / $span ) * 180, 2 );
				$out  .= '<line class="ic-stats-chart-grid" x1="30" x2="710" y1="' . esc_attr( $y ) . '" y2="' . esc_attr( $y ) . '" /><text class="ic-stats-chart-tick" x="27" y="' . esc_attr( round( $y + 3, 2 ) ) . '" text-anchor="end">' . esc_html( call_user_func( $format, $value ) ) . '</text>';
			}
			return $out;
		}
		/**
		 * Build the legend markup.
		 *
		 * Rendered as HTML inside the single `figure` but outside the `svg`, so it
		 * wraps, translates and is reachable by a screen reader.
		 *
		 * @param array $series Series map.
		 * @return string
		 */
		private static function legend( $series ) {
			$out = '';
			foreach ( $series as $entry ) {
				$out .= '<li><span class="ic-stats-swatch" style="background:' . esc_attr( $entry['color'] ) . '"></span>' . esc_html( $entry['label'] ) . '</li>';
			}
			return $out ? '<ul class="ic-stats-chart-legend">' . $out . '</ul>' : '';
		}
		/**
		 * Build the accessible data table.
		 *
		 * @param array    $points Chart points.
		 * @param callable $format Value formatter.
		 * @param string   $chart_id Stable chart identifier.
		 * @return string
		 */
		private static function table( $points, $format, $chart_id = '' ) {
			$rows = '';
			foreach ( $points as $index => $point ) {
				$value = (float) ( $point['value'] ?? 0 );
				$text  = isset( $point['label'] ) && '' !== $point['label'] ? (string) $point['label'] : call_user_func( $format, $value );
				$rows .= '<tr' . ( $index >= 10 ? ' class="ic-stats-chart-overflow"' : '' ) . '><td>' . esc_html( $point['metric'] ?? '' ) . '</td><td><span class="ic-stats-swatch" style="background:' . esc_attr( $point['color'] ) . '"></span>' . esc_html( $point['series'] ) . '</td><td>' . esc_html( $point['period'] ?? '' ) . '</td><td>' . esc_html( $point['currency'] ?? '' ) . '</td><td>' . esc_html( $text ) . '</td></tr>';
			}
			$table_id = $chart_id ? $chart_id . '-data' : wp_unique_id( 'ic-stats-chart-data-' );
			$button   = count( $points ) > 10 ? '<button type="button" class="button ic-stats-chart-toggle" aria-expanded="false" aria-controls="' . esc_attr( $table_id ) . '" data-more="' . esc_attr__( 'Show more', 'ecommerce-product-catalog' ) . '" data-less="' . esc_attr__( 'Show fewer', 'ecommerce-product-catalog' ) . '">' . esc_html__( 'Show more', 'ecommerce-product-catalog' ) . '</button>' : '';
			return '<table id="' . esc_attr( $table_id ) . '" class="ic-stats-chart-data"><thead><tr><th>' . esc_html__( 'Metric', 'ecommerce-product-catalog' ) . '</th><th>' . esc_html__( 'Series', 'ecommerce-product-catalog' ) . '</th><th>' . esc_html__( 'Period', 'ecommerce-product-catalog' ) . '</th><th>' . esc_html__( 'Currency', 'ecommerce-product-catalog' ) . '</th><th>' . esc_html__( 'Value', 'ecommerce-product-catalog' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>' . $button;
		}
		/**
		 * Resolve the value formatter.
		 *
		 * The renderer never reaches into a consumer for formatting: a consumer that
		 * wants currency, percentage or average formatting passes a callable.
		 *
		 * @param array  $points Chart points.
		 * @param array  $args   Chart arguments.
		 * @param string $suffix Legacy value suffix.
		 * @return callable
		 */
		private static function formatter( $points, $args, $suffix ) {
			if ( isset( $args['format'] ) && is_callable( $args['format'] ) ) {
				$format = $args['format'];
				return static function ( $value ) use ( $format ) {
					return (string) call_user_func( $format, (float) $value );
				};
			}
			if ( isset( $args['decimals'] ) ) {
				$decimals = max( 0, min( 6, (int) $args['decimals'] ) );
			} else {
				$decimals = 0;
				foreach ( $points as $point ) {
					$value = (float) ( $point['value'] ?? 0 );
					if ( (float) (int) $value !== $value ) {
						$decimals = 2;
						break; }
				}
			}
			return static function ( $value ) use ( $decimals, $suffix ) {
				return number_format_i18n( (float) $value, $decimals ) . $suffix;
			};
		}
	}
}
