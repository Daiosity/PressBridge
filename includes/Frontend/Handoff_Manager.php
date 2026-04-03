<?php
/**
 * Handle safe public route redirects to the React frontend.
 *
 * @package WP_To_React\Frontend
 */

namespace WP_To_React\Frontend;

use WP_To_React\Core\Path_Helper;
use WP_To_React\Core\Settings;

class Handoff_Manager {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

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
	public function __construct( Settings $settings, Path_Helper $path_helper ) {
		$this->settings    = $settings;
		$this->path_helper = $path_helper;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect_frontend_request' ), 0 );
	}

	/**
	 * Redirect eligible public requests to the configured frontend.
	 *
	 * @return void
	 */
	public function maybe_redirect_frontend_request() {
		if ( ! $this->settings->should_redirect_public_routes() ) {
			return;
		}

		if ( $this->should_bypass_request() ) {
			return;
		}

		$target_url = $this->build_target_url();

		if ( empty( $target_url ) || $this->is_same_request_host_as_frontend() ) {
			return;
		}

		$should_redirect = apply_filters( 'wtr_should_redirect_request', true, $target_url );

		if ( ! $should_redirect ) {
			return;
		}

		wp_redirect( $target_url, 302, 'PressBridge' );
		exit;
	}

	/**
	 * Identify requests that should always stay on WordPress.
	 *
	 * @return bool
	 */
	private function should_bypass_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		if ( is_feed() || is_preview() || is_trackback() || is_robots() || is_favicon() || is_embed() ) {
			return true;
		}

		if ( 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return true;
		}

		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build the frontend target URL from the current request.
	 *
	 * @return string
	 */
	private function build_target_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path        = $this->path_helper->get_request_path( $request_uri );
		$query       = wp_parse_url( $request_uri, PHP_URL_QUERY );
		$url  = $this->settings->build_frontend_url( $path );

		if ( ! empty( $query ) ) {
			$url .= '?' . $query;
		}

		return $url;
	}

	/**
	 * Prevent obvious redirect loops when the frontend URL matches the current host.
	 *
	 * @return bool
	 */
	private function is_same_request_host_as_frontend() {
		$frontend_parts = wp_parse_url( $this->settings->get_frontend_url() );
		$request_host   = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$request_scheme = is_ssl() ? 'https' : 'http';

		if ( empty( $frontend_parts['host'] ) || empty( $request_host ) ) {
			return false;
		}

		$frontend_host = $frontend_parts['host'];
		if ( ! empty( $frontend_parts['port'] ) ) {
			$frontend_host .= ':' . $frontend_parts['port'];
		}

		return $frontend_host === $request_host
			&& ! empty( $frontend_parts['scheme'] )
			&& $frontend_parts['scheme'] === $request_scheme;
	}
}
