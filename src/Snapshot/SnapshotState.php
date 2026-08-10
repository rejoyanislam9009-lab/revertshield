<?php
/**
 * Snapshot lifecycle states.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Central snapshot state identifiers.
 */
final class SnapshotState {
	const PREPARING = 'preparing';
	const READY     = 'ready';
	const FAILED    = 'failed';
	const CORRUPT   = 'corrupt';
	const EXPIRED   = 'expired';

	/**
	 * Return all valid states.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			self::PREPARING,
			self::READY,
			self::FAILED,
			self::CORRUPT,
			self::EXPIRED,
		);
	}
}
