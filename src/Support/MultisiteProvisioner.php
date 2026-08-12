<?php
/**
 * Multisite site provisioning.
 *
 * @package RevertShield
 */

namespace RevertShield\Support;

use RevertShield\Core\Activator;

/**
 * Provisions RevertShield state when WordPress initializes a new Multisite site.
 */
final class MultisiteProvisioner {
	/**
	 * Register hooks when Multisite is active.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'wp_initialize_site', array( $this, 'initialize_site' ), 200, 1 );
	}

	/**
	 * Create site-scoped RevertShield schema, defaults, and schedules.
	 *
	 * WordPress has already initialized the new site's core tables when this
	 * callback runs. The blog switch ensures every RevertShield option, table,
	 * and cron event is created in the new site's own context.
	 *
	 * @param \WP_Site $new_site Newly initialized site.
	 * @return void
	 */
	public function initialize_site( $new_site ) {
		if ( ! $new_site instanceof \WP_Site || empty( $new_site->blog_id ) ) {
			return;
		}

		switch_to_blog( absint( $new_site->blog_id ) );

		try {
			Activator::activate();
		} finally {
			restore_current_blog();
		}
	}
}
