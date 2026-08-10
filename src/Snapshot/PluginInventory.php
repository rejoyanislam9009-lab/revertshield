<?php
/**
 * Plugin snapshot inventory builder.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Builds a deterministic, checksummed inventory for an installed plugin.
 */
final class PluginInventory {
	/**
	 * Build a manifest for an installed plugin.
	 *
	 * This method inventories files only. It does not copy or restore files.
	 *
	 * @param string $plugin_file Plugin basename, for example akismet/akismet.php.
	 * @return SnapshotManifest|\WP_Error Manifest or an error.
	 */
	public function build( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = ltrim( wp_normalize_path( (string) $plugin_file ), '/' );
		$plugins     = get_plugins();

		if ( 0 !== validate_file( $plugin_file ) || ! isset( $plugins[ $plugin_file ] ) ) {
			return new \WP_Error(
				'revertshield_unknown_plugin',
				__( 'The requested plugin is not installed.', 'revertshield' )
			);
		}

		$plugins_root = realpath( WP_PLUGIN_DIR );
		if ( false === $plugins_root ) {
			return new \WP_Error(
				'revertshield_plugins_root_unavailable',
				__( 'RevertShield could not resolve the plugins directory.', 'revertshield' )
			);
		}

		$plugins_root = wp_normalize_path( $plugins_root );
		$plugin_data  = $plugins[ $plugin_file ];
		$relative_dir = dirname( $plugin_file );

		if ( '.' === $relative_dir ) {
			$inventory = $this->inventory_single_file( $plugin_file, $plugins_root );
		} else {
			$inventory = $this->inventory_directory( $relative_dir, $plugins_root );
		}

		if ( is_wp_error( $inventory ) ) {
			return $inventory;
		}

		return new SnapshotManifest(
			'plugin',
			$plugin_file,
			array(
				'name'         => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '',
				'version'      => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '',
				'text_domain'  => isset( $plugin_data['TextDomain'] ) ? $plugin_data['TextDomain'] : '',
				'requires_wp'  => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
				'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
			),
			$inventory['files'],
			$inventory['total_size']
		);
	}

	/**
	 * Inventory a plugin implemented as a single file in the plugins root.
	 *
	 * @param string $plugin_file  Plugin basename.
	 * @param string $plugins_root Canonical plugin root.
	 * @return array|\WP_Error Inventory or an error.
	 */
	private function inventory_single_file( $plugin_file, $plugins_root ) {
		$absolute = realpath( WP_PLUGIN_DIR . '/' . $plugin_file );
		if ( false === $absolute || ! is_file( $absolute ) ) {
			return new \WP_Error(
				'revertshield_plugin_file_unavailable',
				__( 'A plugin file could not be read for snapshot preparation.', 'revertshield' )
			);
		}

		$absolute = wp_normalize_path( $absolute );
		if ( ! $this->is_within_root( $absolute, $plugins_root ) ) {
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
	 * @param string $relative_dir Plugin directory relative to WP_PLUGIN_DIR.
	 * @param string $plugins_root Canonical plugin root.
	 * @return array|\WP_Error Inventory or an error.
	 */
	private function inventory_directory( $relative_dir, $plugins_root ) {
		$component_root = realpath( WP_PLUGIN_DIR . '/' . $relative_dir );
		if ( false === $component_root || ! is_dir( $component_root ) ) {
			return new \WP_Error(
				'revertshield_plugin_directory_unavailable',
				__( 'The plugin directory could not be read for snapshot preparation.', 'revertshield' )
			);
		}

		$component_root = wp_normalize_path( $component_root );
		if ( ! $this->is_within_root( $component_root, $plugins_root ) ) {
			return new \WP_Error(
				'revertshield_plugin_path_escape',
				__( 'The plugin directory escaped the expected plugins directory.', 'revertshield' )
			);
		}

		$max_files = (int) apply_filters( 'revertshield_snapshot_max_files', 50000, 'plugin' );
		$max_files = max( 1, min( 100000, $max_files ) );
		$files     = array();
		$total     = 0;

		try {
			$directory = new \RecursiveDirectoryIterator(
				$component_root,
				\FilesystemIterator::SKIP_DOTS
			);
			$iterator = new \RecursiveIteratorIterator( $directory );

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
		return 0 === strpos(
			wp_normalize_path( $path ),
			trailingslashit( wp_normalize_path( $root ) )
		);
	}
}
