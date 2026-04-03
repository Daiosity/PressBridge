<?php
/**
 * Menu endpoints.
 *
 * @package WP_To_React\Api
 */

namespace WP_To_React\Api;

use WP_REST_Request;
use WP_To_React\Data\Menu_Service;

class Menu_Controller {
	/**
	 * Namespace.
	 */
	const NAMESPACE = 'pressbridge/v1';

	/**
	 * Menu service.
	 *
	 * @var Menu_Service
	 */
	private $menu_service;

	/**
	 * Constructor.
	 *
	 * @param Menu_Service $menu_service Menu service.
	 */
	public function __construct( Menu_Service $menu_service ) {
		$this->menu_service = $menu_service;
	}

	/**
	 * Register menu routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/menus',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_menus' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return menus grouped by location.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_menus( WP_REST_Request $request ) {
		return rest_ensure_response( $this->menu_service->get_menus_response() );
	}
}
