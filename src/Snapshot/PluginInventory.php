<?php
/**
 * Plugin snapshot inventory builder.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

use RevertShield\Support\SiteContext;

/**
 * Builds a deterministic, checksummed inventory for an installed plugin.
 */
final class PluginInventory {
	/** @var PluginSourceLocator */
	private $locator;

	/** @var SiteContext */
	private $site_context;

	/**
	 * Constructor.
	 *
	 * @param PluginSourceLocator|null $locator      Optional source locator.
	 * @param SiteContext|null         $site_context Optional site context.
	 */
	public function __construct( PluginSourceLocator $locator = null, SiteContext $site_context = null ) {
		$this->locator      = $locator ? $locator : new PluginSourceLocator();
		$this->site_context = $site_context ? $site_context : new SiteContext();
	}

	/**
	 * Build a manifest for an installed plugin.
	 *
	 * This method inventories files only. It does not copy or restore files.
	 *
	 * @param string $plugin_file Plugin basename, for example akismet/akismet.php.
	 * @return SnapshotManifest|\WP_Error Manifest or an error.
	 */
	public function build( $plugin_file ) {
		$component = $this->locator->locate( $plugin_file );
		if ( is_wp_error( $component ) ) {
			return $component;
		}

		if ( $component['single_file'] ) {
			$inventory = $this->inventory_single_file(
				$component['plugin_file'],
				$component['component_root']
			);
		} else {
			$inventory = $this->inventory_directory( $component['component_root'] );
		}

		if ( is_wp_error( $inventory ) ) {
			return $inventory;
		}

		$plugin_data = $component['plugin_data'];
		$metadata    = array(
			'name'         => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '',
			'version'      => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '',
			'text_domain'  => isset( $plugin_data['TextDomain'] ) ? $plugin_data['TextDomain'] : '',
			'requires_wp'  => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
		);
		$metadata    = array_merge( $metadata, $this->site_context->snapshot_manifest_metadata() );

		return new SnapshotManifest(
			'plugin',
			$component['plugin_file'],
			$metadata,
			$inventory['files'],
			$inventory['total_size']
		);
	}

	/**
	 * Inventory a plugin implemented as a single file in the plugins root.
	 *
	 * @param string $plugin_file    Plugin basename.
	 * @param string $component_root Canonical plugins root.
	 * @return array|\WP_Error Inventory or an error.
	 */
	private function inventory_single_file( $plugin_file, $component_root ) {
		$absolute = realpath( WP_PLUGIN_DIR . '/' . $plugin_file );
		if ( false === $absolute || ! is_file( $absolute ) ) {
			return new \WP_Error(
				'revertshield_plugin_file_unavailable',
				__( 'A plugin file could not be read for snapshot preparation.', 'revertshield' )
			);
		}

		$absolute = wp_normalize_path( $absolute );
		if ( ! $this->is_within_root( $absolute, $component_root ) ) {
			return new \WP_Error(
				'revertshield_plugin_path_escape',
				__( 'A plugin file escaped the expected plugins directory.', 'revertshield' )
			);
		}

		$file = $this->file_entry( $absolute, basename( $plugin_file ) );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		return array(
			'files'      => array( $file ),
			'total_size' => $file['size'],
		);
	}

	/**
	 * Inventory all regular files below one installed plugin directory.
	 *
	 * @param string $component_root Canonical plugin component root.
	 * @return array|\WP_Error Inventory or an error.
	 */
	private function inventory_directory( $component_root ) {
		$max_files = (int) apply_filters( 'revertshield_snapshot_max_files', 50000, 'plugin' );
		$max_files = max( 1, min( 100000, $max_files ) );
		$files     = array();
		$total     = 0;

		try {
			$directory = new \RecursiveDirectoryIterator(
				$component_root,
				\FilesystemIterator::SKIP_DOTS
			);
			$iterator  = new \RecursiveIteratorIterator( $directory );

			foreach ( $iterator as $item ) {
				if ( $item->isLink() ) {
					return new \WP_Error(
						'revertshield_plugin_symlink_unsupported',
						__( 'Plugin symlinks are not supported by the snapshot engine yet.', 'revertshield' )
					);
				}

				if ( ! $item->isFile() ) {
					continue;
				}

				if ( count( $files ) >= $max_files ) {
					return new \WP_Error(
						'revertshield_snapshot_file_limit',
						__( 'The plugin contains more files than the configured snapshot safety limit.', 'revertshield' )
					);
				}

				$absolute = realpath( $item->getPathname() );
				if ( false === $absolute ) {
					return new \WP_Error(
						'revertshield_plugin_file_unavailable',
						__( 'A plugin file could not be resolved for snapshot preparation.', 'revertshield' )
					);
				}

				$absolute = wp_normalize_path( $absolute );
				if ( ! $this->is_within_root( $absolute, $component_root ) ) {
					return new \WP_Error(
						'revertshield_plugin_path_escape',
						__( 'A plugin file escaped the expected component directory.', 'revertshield' )
					);
				}

				$relative = ltrim( substr( $absolute, strlen( $component_root ) ), '/' );
				$file     = $this->file_entry( $absolute, $relative );
				if ( is_wp_error( $file ) ) {
					return $file;
				}

				$files[] = $file;
				$total  += $file['size'];
			}
		} catch ( \UnexpectedValueException $exception ) {
			return new \WP_Error(
				'revertshield_plugin_inventory_failed',
				sanitize_text_field( $exception->getMessage() )
			);
		}

		return array(
			'files'      => $files,
			'total_size' => max( 0, (int) $total ),
		);
	}

	/**
	 * Build one checksummed file entry.
	 *
	 * @param string $absolute Absolute file path.
	 * @param string $relative Relative component path.
	 * @return array|\WP_Error File entry or an error.
	 */
	private function file_entry( $absolute, $relative ) {
		$hash = hash_file( 'sha256', $absolute );
		$size = filesize( $absolute );

		if ( false === $hash || false === $size ) {
			return new \WP_Error(
				'revertshield_plugin_file_unreadable',
				__( 'A plugin file could not be checksummed for snapshot preparation.', 'revertshield' )
			);
		}

		return array(
			'path'   => ltrim( wp_normalize_path( $relative ), '/' ),
			'sha256' => $hash,
			'size'   => max( 0, (int) $size ),
		);
	}

	/**
	 * Check that a canonical path remains inside a canonical root.
	 *
	 * @param string $path Canonical candidate path.
	 * @param string $root Canonical root path.
	 * @return bool
	 */
	private function is_within_root( $path, $root ) {
		$path = wp_normalize_path( $path );
		$root = untrailingslashit( wp_normalize_path( $root ) );

		return 0 === strpos( $path, trailingslashit( $root ) );
	}
}
