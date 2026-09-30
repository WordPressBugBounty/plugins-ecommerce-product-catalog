<?php
/**
 * Reusable, integration-neutral statistics administration primitives.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( ! class_exists( 'IC_Stats_Admin_Primitives' ) ) {
	/**
	 * Render reusable statistics administration components.
	 */
	class IC_Stats_Admin_Primitives {
		/**
		 * Render a KPI tile with an accessible value and optional status.
		 *
		 * @param string $label  KPI label.
		 * @param string $value  KPI value.
		 * @param string $status Optional status text.
		 * @return string
		 */
		public static function kpi( $label, $value, $status = '' ) {
			return '<article class="ic-stats-kpi"><h2>' . esc_html( $label ) . '</h2><strong>' . esc_html( $value ) . '</strong>' . ( $status ? '<p>' . esc_html( $status ) . '</p>' : '' ) . '</article>';
		}
		/**
		 * Render a safe metric enablement tile.
		 *
		 * @param string $metric      Metric key.
		 * @param bool   $enabled     Whether the metric is enabled.
		 * @param string $description Optional description.
		 * @param string $body        Optional tile body HTML.
		 * @return string
		 */
		public static function metric_tile( $metric, $enabled, $description = '', $body = '' ) {
			return '<div class="ic-stats-metric" data-metric="' . esc_attr( sanitize_key( $metric ) ) . '">' . ( $description ? '<p>' . esc_html( $description ) . '</p>' : '' ) . $body . '</div>';
		}
	}
}
