<?php
/**
 * RevertShield live WooCommerce Store API integration assertions.
 *
 * This test is executed only after the real WooCommerce plugin is installed
 * and activated in the latest WordPress runtime job.
 *
 * @package RevertShield
 */

use RevertShield\Health\WooCommerceHealthAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress did not bootstrap.' );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$adapter = new WooCommerceHealthAdapter();
$assert( true === $adapter->is_applicable(), 'Real WooCommerce activation was not detected by the health adapter.' );

$targets = $adapter->probe_targets();
$assert( isset( $targets['woocommerce_store_api_http'] ), 'Real WooCommerce adapter did not expose the Store API probe target.' );
$assert( false !== strpos( $targets['woocommerce_store_api_http'], '/wc/store/v1/products' ), 'WooCommerce Store API probe target does not use the public product collection endpoint.' );
$assert( false !== strpos( $targets['woocommerce_store_api_http'], 'per_page=1' ), 'WooCommerce Store API probe target is not bounded to one product.' );

if ( ! did_action( 'rest_api_init' ) ) {
	do_action( 'rest_api_init', rest_get_server() );
}

$request = new WP_REST_Request( 'GET', '/wc/store/v1/products' );
$request->set_param( 'per_page', 1 );
$response = rest_do_request( $request );

$assert( $response instanceof WP_REST_Response, 'WooCommerce Store API did not return a REST response.' );
$assert( 200 === $response->get_status(), 'WooCommerce Store API product collection did not return HTTP 200 in the live integration fixture.' );
$assert( is_array( $response->get_data() ), 'WooCommerce Store API product collection did not return an array payload.' );

WP_CLI::log( 'RevertShield live WooCommerce Store API integration assertions passed.' );
