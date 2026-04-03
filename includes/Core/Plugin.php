<?php
/**
 * Main plugin bootstrapper.
 *
 * @package WP_To_React\Core
 */

namespace WP_To_React\Core;

use WP_To_React\Admin\Preview_Assistant;
use WP_To_React\Admin\React_View_Links;
use WP_To_React\Admin\Settings_Page;
use WP_To_React\Admin\Starter_Export;
use WP_To_React\Api\Rest_Router;
use WP_To_React\Data\Post_Type_Service;
use WP_To_React\Frontend\Handoff_Manager;
use WP_To_React\Frontend\Preview_Service;

class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Registered components.
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Get the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set up dependencies and hooks.
	 */
	private function __construct() {
		$settings          = new Settings();
		$path_helper       = new Path_Helper();
		$post_type_service = new Post_Type_Service();
		$preview           = new Preview_Service( $settings, $post_type_service, $path_helper );
		$starter_export    = new Starter_Export( $settings );
		$rest_router       = new Rest_Router( $settings, $preview, $path_helper );
		$settings_page     = new Settings_Page( $settings, $starter_export );
		$react_view_links  = new React_View_Links( $settings, $post_type_service, $path_helper );
		$preview_assistant = new Preview_Assistant( $settings, $post_type_service, $react_view_links );
		$handoff           = new Handoff_Manager( $settings, $path_helper );

		$this->components = array(
			$settings,
			$path_helper,
			$post_type_service,
			$preview,
			$starter_export,
			$rest_router,
			$settings_page,
			$preview_assistant,
			$react_view_links,
			$handoff,
		);

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'register_hooks' ) ) {
				$component->register_hooks();
			}
		}
	}
}
