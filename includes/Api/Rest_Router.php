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
		add_filter( 'rest_pre_serve_request', array( $this, 'send_frontend_cors_headers' ), 10, 4 );
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

	/**
	 * Send credential-friendly CORS headers for the configured frontend app.
	 *
	 * @param bool              $served  Whether the request has already been served.
	 * @param \WP_HTTP_Response $result  Result to send to the client.
	 * @param \WP_REST_Request  $request Request used to generate the response.
	 * @param \WP_REST_Server   $server  Server instance.
	 * @return bool
	 */
	public function send_frontend_cors_headers( $served, $result, $request, $server ) {
		if ( 0 !== strpos( $request->get_route(), '/' . Site_Controller::NAMESPACE . '/' ) ) {
			return $served;
		}

		$origin         = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		$frontend_url   = $this->settings->get_frontend_url();
		$frontend_parts = $frontend_url ? wp_parse_url( $frontend_url ) : array();

		if ( empty( $origin ) || empty( $frontend_parts['scheme'] ) || empty( $frontend_parts['host'] ) ) {
			return $served;
		}

		$allowed_origin = $frontend_parts['scheme'] . '://' . $frontend_parts['host'];

		if ( ! empty( $frontend_parts['port'] ) ) {
			$allowed_origin .= ':' . absint( $frontend_parts['port'] );
		}

		if ( untrailingslashit( $origin ) !== untrailingslashit( $allowed_origin ) ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: ' . $allowed_origin );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, X-WP-Nonce' );
		header( 'Vary: Origin', false );

		return $served;
	}
}
