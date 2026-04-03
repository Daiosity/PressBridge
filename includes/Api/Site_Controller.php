<?php
/**
 * Site configuration endpoint.
 *
 * @package WP_To_React\Api
 */

namespace WP_To_React\Api;

use WP_To_React\Core\Path_Helper;
use WP_REST_Request;
use WP_To_React\Core\Settings;
use WP_To_React\Data\Post_Type_Service;

class Site_Controller {
	/**
	 * Namespace.
	 */
	const NAMESPACE = 'pressbridge/v1';

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Post type service.
	 *
	 * @var Post_Type_Service
	 */
	private $post_type_service;

	/**
	 * Shared path helper.
	 *
	 * @var Path_Helper
	 */
	private $path_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings, Post_Type_Service $post_type_service, Path_Helper $path_helper ) {
		$this->settings          = $settings;
		$this->post_type_service = $post_type_service;
		$this->path_helper       = $path_helper;
	}

	/**
	 * Register site config route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/site',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_site_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return frontend boot configuration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_site_config( WP_REST_Request $request ) {
		$front_page_id = (int) get_option( 'page_on_front' );
		$posts_page_id = (int) get_option( 'page_for_posts' );
		$data          = array(
			'name'                => get_bloginfo( 'name' ),
			'description'         => get_bloginfo( 'description' ),
			'language'            => get_bloginfo( 'language' ),
			'timezone'            => wp_timezone_string(),
			'site_url'            => site_url( '/' ),
			'home_url'            => home_url( '/' ),
			'api_base'            => rest_url( self::NAMESPACE ),
			'frontend_url'        => $this->settings->get_frontend_url(),
			'headless_mode'       => $this->settings->is_headless_enabled(),
			'route_handling_mode' => $this->settings->get_route_handling_mode(),
			'show_on_front'       => get_option( 'show_on_front', 'posts' ),
			'front_page_id'       => $front_page_id,
			'posts_page_id'       => $posts_page_id,
			'routes'              => array(
				'site'    => rest_url( self::NAMESPACE . '/site' ),
				'menus'   => rest_url( self::NAMESPACE . '/menus' ),
				'pages'   => rest_url( self::NAMESPACE . '/pages' ),
				'posts'   => rest_url( self::NAMESPACE . '/posts' ),
				'items'   => rest_url( self::NAMESPACE . '/items' ),
				'content' => rest_url( self::NAMESPACE . '/content' ),
				'types'   => rest_url( self::NAMESPACE . '/content-types' ),
				'resolve' => rest_url( self::NAMESPACE . '/resolve' ),
				'preview' => rest_url( self::NAMESPACE . '/preview' ),
			),
			'content_types'        => array_values( $this->post_type_service->get_supported_post_types() ),
		);

		if ( $front_page_id ) {
			$data['front_page_path'] = $this->get_post_path( $front_page_id );
		}

		if ( $posts_page_id ) {
			$data['posts_page_path'] = $this->get_post_path( $posts_page_id );
		}

		if ( $this->settings->is_debug_enabled() ) {
			$data['debug'] = array(
				'permalink_structure' => get_option( 'permalink_structure' ),
				'theme'               => wp_get_theme()->get( 'Name' ),
				'is_frontend_ready'   => $this->settings->has_valid_frontend_url(),
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Get the frontend path for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_post_path( $post_id ) {
		return $this->path_helper->get_post_path( $post_id );
	}
}
