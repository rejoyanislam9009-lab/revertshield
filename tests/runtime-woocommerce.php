<?php
/**
 * RevertShield WooCommerce health-adapter runtime assertions.
 *
 * Run with: wp eval-file tests/runtime-woocommerce.php
 *
 * @package RevertShield
 */

use RevertShield\Health\HealthChecker;
use RevertShield\Health\WooCommerceHealthAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress did not bootstrap.' );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$homepage_target = home_url( '/' );
$rest_target     = rest_url();
$woo_target      = add_query_arg( 'per_page', 1, rest_url( 'wc/store/v1/products' ) );

$success_mock = static function ( $preempt, $parsed_args, $url ) use ( $homepage_target, $rest_target, $woo_target ) {
	unset( $parsed_args );

	if ( ! in_array( $url, array( $homepage_target, $rest_target, $woo_target ), true ) ) {
		return $preempt;
	}

	return array(
		'headers'  => array(),
		'body'     => 'ok',
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $success_mock, 10, 3 );

$baseline_health = ( new HealthChecker( array() ) )->run_site_check();
$assert( 'pass' === $baseline_health['status'], 'Baseline health suite did not pass under the deterministic HTTP fixture.' );
$assert( 2 === count( $baseline_health['probes'] ), 'A site without ecosystem adapters did not keep the two required core probes.' );
$assert( ! isset( $baseline_health['probes']['woocommerce_store_api_http'] ), 'Baseline health unexpectedly included the WooCommerce probe.' );

if ( ! defined( 'WC_VERSION' ) && ! class_exists( 'WooCommerce', false ) ) {
	final class RevertShield_WooCommerce_Runtime_Marker {}
	class_alias( 'RevertShield_WooCommerce_Runtime_Marker', 'WooCommerce' );
}

$woo_adapter = new WooCommerceHealthAdapter();
$assert( true === $woo_adapter->is_applicable(), 'WooCommerce adapter did not detect an active WooCommerce runtime marker.' );
$woo_targets = $woo_adapter->probe_targets();
$assert( isset( $woo_targets['woocommerce_store_api_http'] ), 'WooCommerce adapter did not expose its Store API probe.' );
$assert( $woo_target === $woo_targets['woocommerce_store_api_http'], 'WooCommerce adapter Store API target is unexpected.' );

$woo_health = ( new HealthChecker( array( $woo_adapter ) ) )->run_site_check();
$assert( 'pass' === $woo_health['status'], 'WooCommerce-aware health suite did not pass under the deterministic HTTP fixture.' );
$assert( 3 === count( $woo_health['probes'] ), 'WooCommerce-aware health suite did not execute the two core probes plus the Store API probe.' );
$assert( isset( $woo_health['probes']['woocommerce_store_api_http'] ), 'WooCommerce Store API probe is missing from the aggregate result.' );
$assert( empty( $woo_health['failed_probes'] ), 'WooCommerce-aware health suite reported an unexpected failed probe.' );

remove_filter( 'pre_http_request', $success_mock, 10 );

$failure_mock = static function ( $preempt, $parsed_args, $url ) use ( $homepage_target, $rest_target, $woo_target ) {
	unset( $parsed_args );

	if ( ! in_array( $url, array( $homepage_target, $rest_target, $woo_target ), true ) ) {
		return $preempt;
	}

	$code = $woo_target === $url ? 503 : 200;

	return array(
		'headers'  => array(),
		'body'     => '',
		'response' => array(
			'code'    => $code,
			'message' => 503 === $code ? 'Service Unavailable' : 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $failure_mock, 10, 3 );

$failed_health = ( new HealthChecker( array( $woo_adapter ) ) )->run_site_check();
remove_filter( 'pre_http_request', $failure_mock, 10 );

$assert( 'fail' === $failed_health['status'], 'WooCommerce Store API failure did not fail the aggregate site-health decision.' );
$assert( in_array( 'woocommerce_store_api_http', $failed_health['failed_probes'], true ), 'WooCommerce Store API failure was not identified in failed probes.' );
$assert( 503 === (int) $failed_health['probes']['woocommerce_store_api_http']['http_code'], 'WooCommerce Store API failure code was not retained.' );

WP_CLI::log( 'RevertShield WooCommerce health-adapter runtime assertions passed.' );
