<?php
/**
 * Statistics framework bootstrap.
 *
 * @package IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
$ic_stats_path = __DIR__;
require_once $ic_stats_path . '/class-ic-stats-manager.php';
require_once $ic_stats_path . '/class-ic-stats-identity-provider.php';
require_once $ic_stats_path . '/functions.php';
require_once $ic_stats_path . '/class-ic-stats-schema.php';
require_once $ic_stats_path . '/class-ic-stats-query.php';
require_once $ic_stats_path . '/class-ic-stats-chart.php';
require_once $ic_stats_path . '/class-ic-stats-transaction.php';
require_once $ic_stats_path . '/class-ic-stats-admin-primitives.php';
require_once $ic_stats_path . '/class-ic-stats-privacy.php';
