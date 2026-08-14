<?php
/**
 * Read-only WP-CLI commands.
 *
 * @package RevertShield
 */

namespace RevertShield\Cli;

use RevertShield\Health\HealthChecker;
use RevertShield\Health\ScheduledHealthCheck;
use RevertShield\Snapshot\SnapshotPinStore;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotVerifier;

/**
 * Exposes read-only operational inspection through WP-CLI.
 */
final class Commands {
	/** @var HealthChecker */
	private $health;

	/** @var SnapshotRepository */
	private $snapshots;

	/** @var SnapshotPinStore */
	private $pins;

	/**
	 * Constructor.
	 *
	 * @param HealthChecker      $health    Health checker.
	 * @param SnapshotRepository $snapshots Snapshot repository.
	 * @param SnapshotPinStore   $pins      Snapshot pin registry.
	 */
	public function __construct( HealthChecker $health, SnapshotRepository $snapshots, SnapshotPinStore $pins ) {
		$this->health    = $health;
		$this->snapshots = $snapshots;
		$this->pins      = $pins;
	}

	/**
	 * Register the command group when WP-CLI is active.
	 *
	 * @param HealthChecker      $health    Health checker.
	 * @param SnapshotRepository $snapshots Snapshot repository.
	 * @param SnapshotPinStore   $pins      Snapshot pin registry.
	 * @return void
	 */
	public static function register( HealthChecker $health, SnapshotRepository $snapshots, SnapshotPinStore $pins ) {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'revertshield', new self( $health, $snapshots, $pins ) );
	}

	/**
	 * Show a compact RevertShield operational status summary.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Supported: table, json, csv, yaml.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		unset( $args );
		$latest   = $this->health->latest();
		$schedule = ScheduledHealthCheck::status();
		$format   = $this->format( $assoc_args );
		$rows     = array(
			array(
				'field' => 'version',
				'value' => REVERTSHIELD_VERSION,
			),
			array(
				'field' => 'multisite',
				'value' => is_multisite() ? 'yes' : 'no',
			),
			array(
				'field' => 'latest_health',
				'value' => $latest && isset( $latest['status'] ) ? sanitize_key( $latest['status'] ) : 'none',
			),
			array(
				'field' => 'snapshots',
				'value' => (string) $this->snapshots->count(),
			),
			array(
				'field' => 'pinned_snapshots',
				'value' => (string) $this->pins->count(),
			),
			array(
				'field' => 'scheduled_health',
				'value' => ! empty( $schedule['enabled'] ) ? 'enabled' : 'disabled',
			),
			array(
				'field' => 'scheduled_health_hours',
				'value' => (string) absint( $schedule['interval'] ),
			),
		);

		\WP_CLI\Utils\format_items( $format, $rows, array( 'field', 'value' ) );
	}

	/**
	 * Show the latest persisted local health result without running a new check.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Supported: table, json, csv, yaml.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function health( $args, $assoc_args ) {
		unset( $args );
		$latest = $this->health->latest();
		if ( ! $latest ) {
			\WP_CLI::warning( __( 'No persisted RevertShield health result exists yet.', 'revertshield' ) );
			return;
		}

		$row = array(
			'status'      => isset( $latest['status'] ) ? sanitize_key( $latest['status'] ) : '',
			'http_code'   => isset( $latest['http_code'] ) ? absint( $latest['http_code'] ) : 0,
			'duration_ms' => isset( $latest['duration_ms'] ) ? absint( $latest['duration_ms'] ) : 0,
			'created_at'  => isset( $latest['created_at'] ) ? sanitize_text_field( $latest['created_at'] ) : '',
			'message'     => isset( $latest['message'] ) ? sanitize_text_field( $latest['message'] ) : '',
		);

		\WP_CLI\Utils\format_items( $this->format( $assoc_args ), array( $row ), array_keys( $row ) );
	}

	/**
	 * List recent snapshot metadata.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of snapshots to show. Default 20, maximum 100.
	 *
	 * [--format=<format>]
	 * : Output format. Supported: table, json, csv, yaml.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function snapshots( $args, $assoc_args ) {
		unset( $args );
		$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
		$limit = max( 1, min( 100, $limit ) );
		$rows  = array();

		foreach ( $this->snapshots->recent( $limit ) as $snapshot ) {
			$uuid   = isset( $snapshot['snapshot_uuid'] ) ? sanitize_text_field( $snapshot['snapshot_uuid'] ) : '';
			$rows[] = array(
				'snapshot_uuid'  => $uuid,
				'component_name' => isset( $snapshot['component_name'] ) ? sanitize_text_field( $snapshot['component_name'] ) : '',
				'state'          => isset( $snapshot['state'] ) ? sanitize_key( $snapshot['state'] ) : '',
				'size_bytes'     => isset( $snapshot['size_bytes'] ) ? absint( $snapshot['size_bytes'] ) : 0,
				'created_at'     => isset( $snapshot['created_at'] ) ? sanitize_text_field( $snapshot['created_at'] ) : '',
				'expires_at'     => isset( $snapshot['expires_at'] ) && null !== $snapshot['expires_at'] ? sanitize_text_field( $snapshot['expires_at'] ) : '',
				'pinned'         => $this->pins->is_pinned( $uuid ) ? 'yes' : 'no',
			);
		}

		\WP_CLI\Utils\format_items(
			$this->format( $assoc_args ),
			$rows,
			array( 'snapshot_uuid', 'component_name', 'state', 'size_bytes', 'created_at', 'expires_at', 'pinned' )
		);
	}

	/**
	 * Inspect one snapshot and optionally verify its stored objects.
	 *
	 * ## OPTIONS
	 *
	 * <snapshot_uuid>
	 * : Snapshot UUID to inspect.
	 *
	 * [--verify]
	 * : Perform read-only manifest and object integrity verification.
	 *
	 * [--format=<format>]
	 * : Output format. Supported: table, json, csv, yaml.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function snapshot( $args, $assoc_args ) {
		$uuid = isset( $args[0] ) ? sanitize_text_field( $args[0] ) : '';
		if ( '' === $uuid ) {
			\WP_CLI::error( __( 'A snapshot UUID is required.', 'revertshield' ) );
		}

		$snapshot = $this->snapshots->find( $uuid );
		if ( ! $snapshot ) {
			\WP_CLI::error( __( 'Snapshot not found.', 'revertshield' ) );
		}

		$row = array(
			'snapshot_uuid'  => sanitize_text_field( $snapshot['snapshot_uuid'] ),
			'component_type' => sanitize_key( $snapshot['component_type'] ),
			'component_name' => sanitize_text_field( $snapshot['component_name'] ),
			'state'          => sanitize_key( $snapshot['state'] ),
			'size_bytes'     => absint( $snapshot['size_bytes'] ),
			'created_at'     => sanitize_text_field( $snapshot['created_at'] ),
			'expires_at'     => isset( $snapshot['expires_at'] ) && null !== $snapshot['expires_at'] ? sanitize_text_field( $snapshot['expires_at'] ) : '',
			'pinned'         => $this->pins->is_pinned( $uuid ) ? 'yes' : 'no',
			'verified'       => 'not-run',
		);

		if ( isset( $assoc_args['verify'] ) ) {
			$result = ( new SnapshotVerifier( $this->snapshots ) )->verify( $uuid );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_code() . ': ' . $result->get_error_message() );
			}
			$row['verified'] = 'yes';
		}

		\WP_CLI\Utils\format_items( $this->format( $assoc_args ), array( $row ), array_keys( $row ) );
	}

	/**
	 * Sanitize a WP-CLI output format.
	 *
	 * @param array $assoc_args Associative command arguments.
	 * @return string
	 */
	private function format( $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? sanitize_key( $assoc_args['format'] ) : 'table';
		return in_array( $format, array( 'table', 'json', 'csv', 'yaml' ), true ) ? $format : 'table';
	}
}
