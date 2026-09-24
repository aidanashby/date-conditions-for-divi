<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * The plugin stores nothing of its own. Its settings live in page content, where Divi ignores
 * them once the plugin is gone, so they're left alone. This removes the bookkeeping the bundled
 * Plugin Update Checker leaves behind: its `external_updates-<slug>` site option and its
 * `puc_cron_check_updates-<slug>` cron event.
 *
 * @package DCFD
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_option( 'external_updates-date-conditions-for-divi' );
wp_clear_scheduled_hook( 'puc_cron_check_updates-date-conditions-for-divi' );
