<?php
/**
 * Plugin Name:       Date Conditions for Divi
 * Description:       Filter Divi 5 Loops by ACF date fields compared with the current date and time, and show a Divi Library item when a Loop is empty.
 * Version:           0.1.4
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            aidanashby
 * Plugin URI:        https://github.com/aidanashby/date-conditions-for-divi
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Update URI:        https://github.com/aidanashby/date-conditions-for-divi
 *
 * @package DCFD
 */

namespace DCFD;

defined( 'ABSPATH' ) || exit;

const VERSION  = '0.1.4';
const MIN_DIVI = '5.8.1';

require_once __DIR__ . '/includes/rules.php';
require_once __DIR__ . '/includes/fields.php';
require_once __DIR__ . '/includes/query.php';
require_once __DIR__ . '/includes/empty-state.php';
require_once __DIR__ . '/includes/rest.php';

/**
 * Updates from GitHub releases, via the bundled Plugin Update Checker (MIT). It checks the
 * latest release on api.github.com about twice a day and offers the attached zip as a normal
 * WordPress plugin update. Every release needs the plugin zip attached as an asset.
 * Its stored data is removed in uninstall.php.
 */
function init_updater(): void {
	$loader = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $loader ) ) {
		return;
	}
	require_once $loader;

	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/aidanashby/date-conditions-for-divi/',
		__FILE__,
		'date-conditions-for-divi'
	);
	$checker->getVcsApi()->enableReleaseAssets();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init_updater' );

/**
 * Divi 5 at the tested baseline or later. The hooks this plugin uses only exist there;
 * on anything else they never fire, so the plugin is inert rather than broken.
 */
function divi_supported(): bool {
	return defined( 'ET_BUILDER_PRODUCT_VERSION' ) && version_compare( ET_BUILDER_PRODUCT_VERSION, MIN_DIVI, '>=' );
}

/**
 * Admin notice when Divi 5 or ACF is missing.
 */
function admin_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$problems = [];
	if ( ! divi_supported() ) {
		$problems[] = sprintf( 'needs Divi %s or later', MIN_DIVI );
	}
	if ( ! acf_active() ) {
		$problems[] = 'needs Advanced Custom Fields (or SCF) to be active; date rules are ignored until it is';
	}

	if ( $problems ) {
		printf(
			'<div class="notice notice-warning"><p><strong>Date Conditions for Divi</strong> %s.</p></div>',
			esc_html( implode( ', and ', $problems ) )
		);
	}
}
add_action( 'admin_notices', __NAMESPACE__ . '\admin_notice' );

/**
 * Load the builder script into the Visual Builder app window.
 */
function enqueue_builder_script(): void {
	if ( ! divi_supported() || ! class_exists( '\ET\Builder\VisualBuilder\Assets\PackageBuildManager' ) ) {
		return;
	}

	$file = __DIR__ . '/assets/builder.js';

	\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
		[
			'name'    => 'dcfd-builder',
			'version' => VERSION . '.' . filemtime( $file ),
			'script'  => [
				'src'                => plugins_url( 'assets/builder.js', __FILE__ ),
				'deps'               => [ 'lodash', 'divi-vendor-wp-hooks' ],
				'enqueue_top_window' => false,
				'enqueue_app_window' => true,
				'args'               => [ 'in_footer' => false ],
				// Exposed as window.DcfdBuilderData via wp_localize_script.
				'data_app_window'    => [
					'restUrl'   => esc_url_raw( rest_url( 'dcfd/v1/' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'acfActive' => acf_active(),
				],
			],
		]
	);
}
add_action( 'divi_visual_builder_assets_before_enqueue_scripts', __NAMESPACE__ . '\enqueue_builder_script' );
