<?php
/**
 * Add "View in React" shortcuts in WordPress admin surfaces.
 *
 * @package WP_To_React\Admin
 */

namespace WP_To_React\Admin;

use WP_Admin_Bar;
use WP_Post;
use WP_To_React\Core\Path_Helper;
use WP_To_React\Core\Settings;
use WP_To_React\Data\Post_Type_Service;

class React_View_Links {
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
	 * @param Settings          $settings Settings service.
	 * @param Post_Type_Service $post_type_service Post type service.
	 */
	public function __construct( Settings $settings, Post_Type_Service $post_type_service, Path_Helper $path_helper ) {
		$this->settings          = $settings;
		$this->post_type_service = $post_type_service;
		$this->path_helper       = $path_helper;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 90 );
	}

	/**
	 * Add a "View in React" link for the current frontend route.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar object.
	 * @return void
	 */
	public function add_admin_bar_link( WP_Admin_Bar $admin_bar ) {
		if ( ! is_user_logged_in() || ! is_admin_bar_showing() || ! $this->settings->has_valid_frontend_url() || is_admin() ) {
			return;
		}

		$react_url = $this->get_current_frontend_url();

		if ( empty( $react_url ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'     => 'wtr-view-in-react',
			'title'  => __( 'View in React', 'pressbridge' ),
				'href'   => $react_url,
				'meta'   => array(
					'target' => '_blank',
			'title'  => __( 'Open this content in the React frontend', 'pressbridge' ),
				),
			)
		);
	}

	/**
	 * Get the React URL for the current frontend request.
	 *
	 * @return string
	 */
	private function get_current_frontend_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path        = $this->path_helper->get_request_path( $request_uri );
		$query       = wp_parse_url( $request_uri, PHP_URL_QUERY );
		$url  = $this->settings->build_frontend_url( $path );

		if ( empty( $url ) ) {
			return '';
		}

		if ( ! empty( $query ) ) {
			$url .= '?' . $query;
		}

		return $url;
	}

	/**
	 * Build the React frontend URL for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function get_frontend_url_for_post( WP_Post $post ) {
		return $this->settings->build_frontend_url( $this->path_helper->get_post_path( $post ) );
	}
}
