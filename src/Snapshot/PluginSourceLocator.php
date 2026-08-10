<?php
/**
 * Installed plugin source resolution.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Resolves an installed plugin to a canonical, WordPress-owned source root.
 */
final class PluginSourceLocator {
	/**
	 * Resolve an installed plugin.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return array|\WP_Error Component information or an error.
	 */
	public function locate( $plugin_file ) {
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

		$plugins_root = untrailingslashit( wp_normalize_path( $plugins_root ) );
		$relative_dir = dirname( $plugin_file );
		$single_file  = '.' === $relative_dir;

		if ( $single_file ) {
			$component_root = $plugins_root;
		} else {
			$resolved = realpath( WP_PLUGIN_DIR . '/' . $relative_dir );
			if ( false === $resolved || ! is_dir( $resolved ) ) {
				return new \WP_Error(
					'revertshield_plugin_directory_unavailable',
					__( 'The plugin directory could not be resolved.', 'revertshield' )
				);
			}

			$component_root = untrailingslashit( wp_normalize_path( $resolved ) );
			if ( ! $this->is_within_root( $component_root, $plugins_root ) ) {
				return new \WP_Error(
					'revertshield_plugin_path_escape',
					__( 'The plugin directory escaped the expected plugins directory.', 'revertshield' )
				);
			}
		}

		return array(
			'plugin_file'    => $plugin_file,
			'plugin_data'    => $plugins[ $plugin_file ],
			'plugins_root'   => $plugins_root,
			'component_root' => $component_root,
			'single_file'    => $single_file,
		);
	}

	/**
	 * Check that a canonical path remains within a canonical root.
	 *
	 * @param string $path Candidate path.
	 * @param string $root Root path.
	 * @return bool
	 */
	private function is_within_root( $path, $root ) {
		$path = untrailingslashit( wp_normalize_path( $path ) );
		$root = untrailingslashit( wp_normalize_path( $root ) );

		return $path === $root || 0 === strpos( $path, trailingslashit( $root ) );
	}
}
