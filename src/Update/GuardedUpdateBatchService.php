<?php
/**
 * Guarded plugin update batch execution.
 *
 * @package RevertShield
 */

namespace RevertShield\Update;

use RevertShield\Ledger\ChangeRepository;

/**
 * Runs bounded guarded updates sequentially and pauses on the first problem.
 */
final class GuardedUpdateBatchService {
	/** @var GuardedPluginUpdateService */
	private $updates;

	/** @var ChangeRepository */
	private $ledger;

	/**
	 * Constructor.
	 *
	 * @param GuardedPluginUpdateService|null $updates Optional single-update service.
	 * @param ChangeRepository|null           $ledger  Optional change ledger.
	 */
	public function __construct( GuardedPluginUpdateService $updates = null, ChangeRepository $ledger = null ) {
		$this->updates = $updates ? $updates : new GuardedPluginUpdateService();
		$this->ledger  = $ledger ? $ledger : new ChangeRepository();
	}

	/**
	 * Execute a bounded sequence of guarded plugin updates.
	 *
	 * @param array $items Each item must contain plugin_file and snapshot_uuid.
	 * @return array|\WP_Error Batch result or validation error.
	 */
	public function execute( array $items ) {
		$items = array_values( array_slice( $items, 0, 20 ) );
		if ( empty( $items ) ) {
			return new \WP_Error(
				'revertshield_guarded_batch_empty',
				__( 'Select at least one eligible guarded update.', 'revertshield' )
			);
		}

		$batch_uuid = wp_generate_uuid4();
		$results    = array();

		$this->ledger->record(
			'guarded_batch_started',
			'plugin_batch',
			$batch_uuid,
			array( 'requested_count' => count( $items ) ),
			'guarded_update'
		);

		foreach ( $items as $item ) {
			$plugin_file   = isset( $item['plugin_file'] ) ? sanitize_text_field( (string) $item['plugin_file'] ) : '';
			$snapshot_uuid = isset( $item['snapshot_uuid'] ) ? sanitize_text_field( (string) $item['snapshot_uuid'] ) : '';

			if ( '' === $plugin_file || '' === $snapshot_uuid ) {
				return $this->pause(
					$batch_uuid,
					$results,
					$plugin_file,
					'revertshield_guarded_batch_item_invalid',
					false,
					''
				);
			}

			$result = $this->updates->execute( $plugin_file, $snapshot_uuid );
			if ( is_wp_error( $result ) ) {
				return $this->pause(
					$batch_uuid,
					$results,
					$plugin_file,
					$result->get_error_code(),
					false,
					''
				);
			}

			$results[] = $result;

			if ( 'pass' !== $result['health_status'] ) {
				return $this->pause(
					$batch_uuid,
					$results,
					$plugin_file,
					'revertshield_guarded_batch_unhealthy',
					! empty( $result['recovery_recommended'] ),
					! empty( $result['recovery_recommended'] ) ? $snapshot_uuid : ''
				);
			}
		}

		$this->ledger->record(
			'guarded_batch_completed',
			'plugin_batch',
			$batch_uuid,
			array( 'completed_count' => count( $results ) ),
			'guarded_update'
		);

		return array(
			'batch_uuid'           => $batch_uuid,
			'status'               => 'complete',
			'completed_count'      => count( $results ),
			'results'              => $results,
			'error_code'           => '',
			'recovery_recommended' => false,
			'recovery_snapshot'    => '',
		);
	}

	/**
	 * Record and return a normalized paused-batch result.
	 *
	 * @param string $batch_uuid            Batch UUID.
	 * @param array  $results               Completed results.
	 * @param string $plugin_file           Plugin that caused the pause.
	 * @param string $error_code            Normalized reason.
	 * @param bool   $recovery_recommended  Whether recovery should be reviewed.
	 * @param string $recovery_snapshot     Recommended snapshot UUID.
	 * @return array
	 */
	private function pause( $batch_uuid, array $results, $plugin_file, $error_code, $recovery_recommended, $recovery_snapshot ) {
		$this->ledger->record(
			'guarded_batch_paused',
			'plugin_batch',
			$batch_uuid,
			array(
				'completed_count'      => count( $results ),
				'paused_plugin'        => sanitize_text_field( $plugin_file ),
				'error_code'           => sanitize_key( $error_code ),
				'recovery_recommended' => $recovery_recommended ? 1 : 0,
			),
			'guarded_update'
		);

		return array(
			'batch_uuid'           => $batch_uuid,
			'status'               => 'paused',
			'completed_count'      => count( $results ),
			'results'              => $results,
			'error_code'           => sanitize_key( $error_code ),
			'paused_plugin'        => sanitize_text_field( $plugin_file ),
			'recovery_recommended' => (bool) $recovery_recommended,
			'recovery_snapshot'    => sanitize_text_field( $recovery_snapshot ),
		);
	}
}
