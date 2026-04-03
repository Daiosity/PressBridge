<?php
/**
 * Plugin uninstall routine.
 *
 * @package WP_To_React
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wtr_settings' );
delete_site_option( 'wtr_settings' );
