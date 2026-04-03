<?php
/**
 * Plugin settings screen.
 *
 * @package WP_To_React\Admin
 */

namespace WP_To_React\Admin;

use WP_To_React\Core\Settings;
use WP_To_React\Data\Post_Type_Service;
use WP_To_React\Frontend\Preview_Service;

class Settings_Page {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Starter export service.
	 *
	 * @var Starter_Export
	 */
	private $starter_export;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings Settings service.
	 * @param Starter_Export $starter_export Export service.
	 */
	public function __construct( Settings $settings, Starter_Export $starter_export ) {
		$this->settings       = $settings;
		$this->starter_export = $starter_export;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_runtime_notice' ) );
	}

	/**
	 * Register the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'PressBridge', 'pressbridge' ),
			__( 'PressBridge', 'pressbridge' ),
			'manage_options',
			Settings::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Display a runtime notice when the main handoff settings are incomplete.
	 *
	 * @return void
	 */
	public function maybe_show_runtime_notice() {
		if ( ! $this->is_settings_screen() ) {
			return;
		}

		if ( $this->settings->is_headless_enabled() && ! $this->settings->has_valid_frontend_url() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Headless mode is enabled, but the frontend app URL is not valid yet. WordPress will stay in safe fallback mode until the URL is fixed.', 'pressbridge' )
			);
		}
	}

	/**
	 * Render the settings page template.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage PressBridge settings.', 'pressbridge' ) );
		}

		$settings = $this->settings->get_all();
		$endpoints = array(
			'site'    => rest_url( 'pressbridge/v1/site' ),
			'menus'   => rest_url( 'pressbridge/v1/menus' ),
			'pages'   => rest_url( 'pressbridge/v1/pages' ),
			'posts'   => rest_url( 'pressbridge/v1/posts' ),
			'items'   => add_query_arg(
				array(
					'type' => 'page',
				),
				rest_url( 'pressbridge/v1/items' )
			),
			'content' => add_query_arg(
				array(
					'type' => 'page',
					'slug' => 'about',
				),
				rest_url( 'pressbridge/v1/content' )
			),
			'types'   => rest_url( 'pressbridge/v1/content-types' ),
			'resolve' => add_query_arg(
				array(
					'path' => '/about/',
				),
				rest_url( 'pressbridge/v1/resolve' )
			),
			'preview' => rest_url( 'pressbridge/v1/preview/{token}' ),
		);

		$download_supported = $this->starter_export->is_supported();
		$download_url       = $download_supported ? $this->starter_export->get_download_url() : '';
		$post_types         = array_values( ( new Post_Type_Service() )->get_supported_post_types() );
		$preview_ttl_minutes = (int) ceil( Preview_Service::TOKEN_TTL / 60 );
		$status_checks      = array(
			array(
				'label'   => __( 'Frontend URL configured', 'pressbridge' ),
				'healthy' => $this->settings->has_valid_frontend_url(),
				'detail'  => $this->settings->has_valid_frontend_url()
					? $this->settings->get_frontend_url()
					: __( 'Add a valid frontend URL before enabling redirects or frontend previews.', 'pressbridge' ),
			),
			array(
				'label'   => __( 'Public handoff ready', 'pressbridge' ),
				'healthy' => $this->settings->should_redirect_public_routes(),
				'detail'  => $this->settings->should_redirect_public_routes()
					? __( 'Public GET requests can be redirected to the React frontend.', 'pressbridge' )
					: __( 'WordPress remains in safe rendering mode until headless mode and redirect mode are both active with a valid frontend URL.', 'pressbridge' ),
			),
			array(
				'label'   => __( 'Preview handoff ready', 'pressbridge' ),
				'healthy' => $this->settings->has_valid_frontend_url(),
				'detail'  => $this->settings->has_valid_frontend_url()
					? sprintf(
						/* translators: %d: preview token lifetime in minutes. */
						__( 'Preview links can generate signed frontend preview URLs that expire after %d minutes.', 'pressbridge' ),
						$preview_ttl_minutes
					)
					: __( 'Preview links will stay on standard WordPress behavior until a frontend URL is configured.', 'pressbridge' ),
			),
			array(
				'label'   => __( 'Pretty permalinks', 'pressbridge' ),
				'healthy' => ! empty( get_option( 'permalink_structure' ) ),
				'detail'  => ! empty( get_option( 'permalink_structure' ) )
					? __( 'Route resolution will work best with your current permalink structure.', 'pressbridge' )
					: __( 'Plain permalinks still work, but route resolution is easier to reason about with pretty permalinks enabled.', 'pressbridge' ),
			),
			array(
				'label'   => __( 'Starter ZIP export', 'pressbridge' ),
				'healthy' => $download_supported,
				'detail'  => $download_supported
					? __( 'This server can generate the React starter archive directly from the plugin.', 'pressbridge' )
					: __( 'ZipArchive is missing, so starter export download is unavailable on this server.', 'pressbridge' ),
			),
		);

		include WTR_PLUGIN_DIR . 'templates/admin-settings-page.php';
	}

	/**
	 * Check whether the current screen is the plugin settings page.
	 *
	 * @return bool
	 */
	private function is_settings_screen() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return isset( $screen->id ) && 'settings_page_' . Settings::PAGE_SLUG === $screen->id;
	}
}
