<?php
/**
 * WooCommerce ecosystem health probes.
 *
 * @package RevertShield
 */

namespace RevertShield\Health;

/**
 * Adds read-only WooCommerce customer-facing API health coverage when active.
 */
final class WooCommerceHealthAdapter implements HealthProbeAdapter {
	/**
	 * Whether WooCommerce is active in the current WordPress request.
	 *
	 * RevertShield intentionally avoids activating, loading, or otherwise
	 * bootstrapping WooCommerce itself. The adapter only applies when the active
	 * plugin has already exposed its normal runtime markers.
	 *
	 * @return bool
	 */
	public function is_applicable() {
		return defined( 'WC_VERSION' ) || class_exists( 'WooCommerce', false );
	}

	/**
	 * Return the bounded read-only WooCommerce Store API probe target.
	 *
	 * The public product collection endpoint is intentionally queried with the
	 * minimum page size so the check validates the customer-facing Store API
	 * without requesting order, customer, cart, checkout, or payment data.
	 *
	 * @return array<string,string>
	 */
	public function probe_targets() {
		if ( ! $this->is_applicable() ) {
			return array();
		}

		$target = add_query_arg(
			'per_page',
			1,
			rest_url( 'wc/store/v1/products' )
		);

		return array(
			'woocommerce_store_api_http' => $target,
		);
	}
}
