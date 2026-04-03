<?php
/**
 * Register plugin REST routes.
 *
 * @package WP_To_React\Api
 */

namespace WP_To_React\Api;

use WP_To_React\Core\Path_Helper;
use WP_To_React\Core\Settings;
use WP_To_React\Data\Content_Mapper;
use WP_To_React\Data\Menu_Service;
use WP_To_React\Data\Post_Type_Service;
use WP_To_React\Frontend\Preview_Service;

class Rest_Router {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Preview service.
	 *
	 * @var Preview_Service
	 */
	private $preview_service;

	/**
	 * Shared path helper.
	 *
	 * @var Path_Helper
	 */
	private $path_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings Settings service.
	 * @param Preview_Service $preview_service Preview service.
	 */
	public function __construct( Settings $settings, Preview_Service $preview_service, Path_Helper $path_helper ) {
		$this->settings        = $settings;
		$this->preview_service = $preview_service;
		$this->path_helper     = $path_helper;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST controllers.
	 *
	 * @return void
	 */
	public function register_routes() {
		$post_type_service = new Post_Type_Service();

		$controllers = array(
			new Site_Controller( $this->settings, $post_type_service, $this->path_helper ),
			new Menu_Controller( new Menu_Service() ),
			new Content_Controller( new Content_Mapper( $this->path_helper ), $this->preview_service, $post_type_service, $this->path_helper ),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
