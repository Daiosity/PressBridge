<?php
/**
 * Validate PressBridge install/activation behavior inside a real WordPress runtime.
 *
 * Usage:
 * php validate-install-runtime.php C:\path\to\wp-root pressbridge/pressbridge.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

function pressbridge_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$wp_root     = $argv[1] ?? '';
$plugin_slug = $argv[2] ?? 'pressbridge/pressbridge.php';

pressbridge_assert( ! empty( $wp_root ), 'Missing WordPress root path argument.' );

$wp_load = rtrim( $wp_root, "\\/" ) . DIRECTORY_SEPARATOR . 'wp-load.php';
pressbridge_assert( file_exists( $wp_load ), "Could not find wp-load.php at {$wp_load}" );

require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugin_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $plugin_slug );
pressbridge_assert( file_exists( $plugin_file ), "Could not find plugin bootstrap at {$plugin_file}" );

$admin_user = get_user_by( 'id', 1 );
if ( $admin_user ) {
	wp_set_current_user( (int) $admin_user->ID );
}

$original_active        = is_plugin_active( $plugin_slug );
$original_settings      = get_option( 'wtr_settings', '__missing__' );
$original_site_settings = get_site_option( 'wtr_settings', '__missing__' );

try {
	deactivate_plugins( $plugin_slug, true );
	delete_option( 'wtr_settings' );
	delete_site_option( 'wtr_settings' );

	$activation_result = activate_plugin( $plugin_slug, '', false, false );
	if ( is_wp_error( $activation_result ) ) {
		throw new RuntimeException( $activation_result->get_error_message() );
	}

	pressbridge_assert( is_plugin_active( $plugin_slug ), 'Plugin did not remain active after activation.' );

	$defaults = get_option( 'wtr_settings', '__missing__' );
	pressbridge_assert( is_array( $defaults ), 'Activation did not seed wtr_settings as an array.' );
	pressbridge_assert( array_key_exists( 'headless_mode', $defaults ) && false === $defaults['headless_mode'], 'Activation default headless_mode is incorrect.' );
	pressbridge_assert( array_key_exists( 'frontend_url', $defaults ) && '' === $defaults['frontend_url'], 'Activation default frontend_url is incorrect.' );
	pressbridge_assert( array_key_exists( 'route_handling_mode', $defaults ) && 'wordpress' === $defaults['route_handling_mode'], 'Activation default route_handling_mode is incorrect.' );
	pressbridge_assert( array_key_exists( 'enable_debug', $defaults ) && false === $defaults['enable_debug'], 'Activation default enable_debug is incorrect.' );

	do_action( 'admin_init' );
	do_action( 'admin_menu' );

	pressbridge_assert( function_exists( 'wtr_plugin' ), 'Plugin bootstrap function wtr_plugin() is not available after activation.' );
	pressbridge_assert( class_exists( '\WP_To_React\Admin\Starter_Export' ), 'Starter_Export class is unavailable after activation.' );
	pressbridge_assert( false !== has_action( 'admin_post_wtr_download_starter' ), 'Starter export action is not registered.' );
	pressbridge_assert( defined( 'WTR_VERSION' ), 'WTR_VERSION is not defined after activation.' );
	pressbridge_assert( defined( 'WTR_PLUGIN_DIR' ), 'WTR_PLUGIN_DIR is not defined after activation.' );

	deactivate_plugins( $plugin_slug, true );
	pressbridge_assert( ! is_plugin_active( $plugin_slug ), 'Plugin remained active after deactivation.' );
	pressbridge_assert( is_array( get_option( 'wtr_settings', '__missing__' ) ), 'Settings disappeared after deactivation.' );

	define( 'WP_UNINSTALL_PLUGIN', $plugin_file );
	include $plugin_file ? dirname( $plugin_file ) . DIRECTORY_SEPARATOR . 'uninstall.php' : '';

	pressbridge_assert( '__missing__' === get_option( 'wtr_settings', '__missing__' ), 'Uninstall routine did not remove wtr_settings.' );
	pressbridge_assert( '__missing__' === get_site_option( 'wtr_settings', '__missing__' ), 'Uninstall routine did not remove multisite wtr_settings.' );

	if ( '__missing__' !== $original_settings ) {
		update_option( 'wtr_settings', $original_settings );
	}

	if ( '__missing__' !== $original_site_settings ) {
		update_site_option( 'wtr_settings', $original_site_settings );
	}

	if ( $original_active ) {
		$restore_result = activate_plugin( $plugin_slug, '', false, false );
		if ( is_wp_error( $restore_result ) ) {
			throw new RuntimeException( 'Failed to restore original plugin active state: ' . $restore_result->get_error_message() );
		}
	}

	echo wp_json_encode(
		array(
			'plugin'             => $plugin_slug,
			'activation_defaults' => $defaults,
			'starter_export_hook' => has_action( 'admin_post_wtr_download_starter' ),
			'uninstall_cleanup'   => true,
			'restored_active'     => $original_active,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . PHP_EOL;
} catch ( Throwable $exception ) {
	if ( '__missing__' !== $original_settings ) {
		update_option( 'wtr_settings', $original_settings );
	} else {
		delete_option( 'wtr_settings' );
	}

	if ( '__missing__' !== $original_site_settings ) {
		update_site_option( 'wtr_settings', $original_site_settings );
	} else {
		delete_site_option( 'wtr_settings' );
	}

	if ( $original_active && ! is_plugin_active( $plugin_slug ) ) {
		activate_plugin( $plugin_slug, '', false, false );
	} elseif ( ! $original_active && is_plugin_active( $plugin_slug ) ) {
		deactivate_plugins( $plugin_slug, true );
	}

	fwrite( STDERR, $exception->getMessage() . PHP_EOL );
	exit( 1 );
}
