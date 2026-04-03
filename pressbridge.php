<?php
/**
 * Plugin Name: PressBridge
 * Plugin URI:  https://example.com/pressbridge
 * Description: Connect WordPress to modern frontends.
 * Version:     0.2.0
 * Author:      Codex
 * Text Domain: pressbridge
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTR_VERSION', '0.2.0' );
define( 'WTR_PLUGIN_FILE', __FILE__ );
define( 'WTR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WTR_PLUGIN_DIR . 'includes/Core/Autoloader.php';

\WP_To_React\Core\Autoloader::register();

register_activation_hook( WTR_PLUGIN_FILE, array( '\WP_To_React\Core\Activator', 'activate' ) );
register_deactivation_hook( WTR_PLUGIN_FILE, array( '\WP_To_React\Core\Deactivator', 'deactivate' ) );

if ( ! function_exists( 'wtr_plugin' ) ) {
	/**
	 * Boot the plugin singleton.
	 *
	 * @return \WP_To_React\Core\Plugin
	 */
	function wtr_plugin() {
		return \WP_To_React\Core\Plugin::instance();
	}
}

wtr_plugin();
