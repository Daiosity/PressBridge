<?php
/**
 * Activation logic.
 *
 * @package WP_To_React\Core
 */

namespace WP_To_React\Core;

class Activator {
	/**
	 * Seed default options on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		$current = get_option( Settings::OPTION_NAME );

		if ( false === $current ) {
			add_option( Settings::OPTION_NAME, Settings::get_default_settings() );
			return;
		}

		update_option(
			Settings::OPTION_NAME,
			wp_parse_args( $current, Settings::get_default_settings() )
		);
	}
}
