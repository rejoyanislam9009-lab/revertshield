<?php
/**
 * Snapshot manifest value object.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Immutable representation of a component snapshot inventory.
 */
final class SnapshotManifest {
	const FORMAT_VERSION = 1;

	/** @var string */
	private $component_type;

	/** @var string */
	private $component_name;

	/** @var array */
	private $metadata;

	/** @var array */
	private $files;

	/** @var int */
	private $total_size;

	/** @var string */
	private $created_at;

	/**
	 * Constructor.
	 *
	 * @param string $component_type Component type.
	 * @param string $component_name Component identifier.
	 * @param array  $metadata       Sanitized component metadata.
	 * @param array  $files          File inventory.
	 * @param int    $total_size     Total bytes represented by the inventory.
	 */
	public function __construct( $component_type, $component_name, array $metadata, array $files, $total_size ) {
		$this->component_type = sanitize_key( $component_type );
		$this->component_name = sanitize_text_field( $component_name );
		$this->metadata       = $this->sanitize_metadata( $metadata );
		$this->files          = $this->normalize_files( $files );
		$this->total_size     = max( 0, (int) $total_size );
		$this->created_at     = current_time( 'mysql', true );
	}

	/**
	 * Export manifest data.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'format_version' => self::FORMAT_VERSION,
			'component_type' => $this->component_type,
			'component_name' => $this->component_name,
			'metadata'       => $this->metadata,
			'files'          => $this->files,
			'file_count'     => count( $this->files ),
			'total_size'     => $this->total_size,
			'created_at'     => $this->created_at,
		);
	}

	/**
	 * Encode the manifest for durable storage.
	 *
	 * @return string|false JSON string or false on encoding failure.
	 */
	public function to_json() {
		return wp_json_encode( $this->to_array(), JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Return the represented byte count.
	 *
	 * @return int
	 */
	public function total_size() {
		return $this->total_size;
	}

	/**
	 * Normalize file inventory entries and sort them deterministically.
	 *
	 * @param array $files File inventory.
	 * @return array
	 */
	private function normalize_files( array $files ) {
		$normalized = array();

		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['path'] ) || empty( $file['sha256'] ) ) {
				continue;
			}

			$path   = ltrim( wp_normalize_path( (string) $file['path'] ), '/' );
			$sha256 = strtolower( sanitize_text_field( (string) $file['sha256'] ) );

			if ( '' === $path || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
				continue;
			}

			$normalized[] = array(
				'path'   => $path,
				'sha256' => $sha256,
				'size'   => isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : 0,
			);
		}

		usort(
			$normalized,
			static function ( $left, $right ) {
				return strcmp( $left['path'], $right['path'] );
			}
		);

		return $normalized;
	}

	/**
	 * Sanitize metadata without allowing nested arbitrary objects.
	 *
	 * @param array $metadata Metadata values.
	 * @return array
	 */
	private function sanitize_metadata( array $metadata ) {
		$clean = array();

		foreach ( $metadata as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || is_array( $value ) || is_object( $value ) || is_resource( $value ) ) {
				continue;
			}

			$clean[ $key ] = sanitize_text_field( (string) $value );
		}

		return $clean;
	}
}
