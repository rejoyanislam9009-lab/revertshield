<?php
/**
 * Optional ecosystem health-probe contract.
 *
 * @package RevertShield
 */

namespace RevertShield\Health;

/**
 * Supplies optional read-only HTTP health probes for an ecosystem integration.
 */
interface HealthProbeAdapter {
	/**
	 * Whether this adapter applies to the current site runtime.
	 *
	 * @return bool
	 */
	public function is_applicable();

	/**
	 * Return bounded HTTP probe targets keyed by stable probe identifier.
	 *
	 * @return array<string,string>
	 */
	public function probe_targets();
}
