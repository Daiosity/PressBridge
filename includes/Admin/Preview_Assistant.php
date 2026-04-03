<?php
/**
 * Preview guidance for editors using the headless bridge.
 *
 * @package WP_To_React\Admin
 */

namespace WP_To_React\Admin;

use WP_Post;
use WP_To_React\Core\Settings;
use WP_To_React\Data\Post_Type_Service;
use WP_To_React\Frontend\Preview_Service;

class Preview_Assistant {
	/**
	 * Editor script handle.
	 */
	const SCRIPT_HANDLE = 'wtr-editor-preview-assistant';

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
	 * React view link helper.
	 *
	 * @var React_View_Links
	 */
	private $react_view_links;

	/**
	 * Constructor.
	 *
	 * @param Settings          $settings Settings service.
	 * @param Post_Type_Service $post_type_service Post type service.
	 * @param React_View_Links  $react_view_links React view link helper.
	 */
	public function __construct( Settings $settings, Post_Type_Service $post_type_service, React_View_Links $react_view_links ) {
		$this->settings          = $settings;
		$this->post_type_service = $post_type_service;
		$this->react_view_links  = $react_view_links;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Show preview guidance on supported editor screens.
	 *
	 * @return void
	 */
	public function maybe_render_notice() {
		if ( ! $this->is_supported_editor_screen() ) {
			return;
		}

		if ( $this->is_block_editor_screen() ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG );

		if ( $this->settings->has_valid_frontend_url() ) {
			printf(
				'<div class="notice notice-info"><p>%1$s</p><p>%2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html(
					sprintf(
						/* translators: %d: preview token lifetime in minutes. */
						__( 'Preview opens in your React frontend with a signed link that expires after %d minutes.', 'pressbridge' ),
						(int) ceil( Preview_Service::TOKEN_TTL / 60 )
					)
				),
				esc_html__( 'Logged-in editors still stay on WordPress for normal browsing, so public handoff remains safe while you edit.', 'pressbridge' ),
				esc_url( $settings_url ),
				esc_html__( 'Review bridge settings', 'pressbridge' )
			);

			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p><a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'PressBridge is active for this content type, but frontend previews are inactive until a valid frontend app URL is configured.', 'pressbridge' ),
			esc_url( $settings_url ),
			esc_html__( 'Add the frontend app URL', 'pressbridge' )
		);
	}

	/**
	 * Add preview guidance inside the block editor sidebar.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		if ( ! $this->is_supported_editor_screen() || ! $this->is_block_editor_screen() ) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			WTR_PLUGIN_URL . 'assets/js/editor-preview-assistant.js',
			array( 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins', 'wp-components' ),
			WTR_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wtrPreviewAssistant',
			$this->get_editor_config()
		);

		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Whether the current admin screen is a supported post editor.
	 *
	 * @return bool
	 */
	private function is_supported_editor_screen() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( empty( $screen->base ) || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return false;
		}

		if ( empty( $screen->post_type ) ) {
			return false;
		}

		return $this->post_type_service->is_supported( $screen->post_type );
	}

	/**
	 * Whether the current editor screen is Gutenberg.
	 *
	 * @return bool
	 */
	private function is_block_editor_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}

	/**
	 * Build client-side config for the block editor preview helper.
	 *
	 * @return array
	 */
	private function get_editor_config() {
		$settings_url = admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG );

		if ( $this->settings->has_valid_frontend_url() ) {
			$react_url = $this->get_current_editor_react_url();

			return array(
				'enabled'    => true,
				'heading'    => __( 'React preview is ready', 'pressbridge' ),
				'message'    => sprintf(
					/* translators: %d: preview token lifetime in minutes. */
					__( 'Preview opens in your React frontend with a signed link that expires after %d minutes.', 'pressbridge' ),
					(int) ceil( Preview_Service::TOKEN_TTL / 60 )
				),
				'secondary'  => __( 'Logged-in editors still stay on WordPress for normal browsing, so public handoff stays safe while you edit.', 'pressbridge' ),
				'linkUrl'    => $react_url ? $react_url : $settings_url,
				'linkLabel'  => $react_url ? __( 'Open published route in React', 'pressbridge' ) : __( 'Review bridge settings', 'pressbridge' ),
				'linkTarget' => $react_url ? '_blank' : '_self',
			);
		}

		return array(
			'enabled'    => true,
			'heading'    => __( 'Frontend preview needs setup', 'pressbridge' ),
			'message'    => __( 'PressBridge is active for this content type, but frontend previews stay inactive until a valid frontend app URL is configured.', 'pressbridge' ),
			'secondary'  => __( 'Add your local or deployed React URL in the plugin settings, then use Preview again.', 'pressbridge' ),
			'linkUrl'    => $settings_url,
			'linkLabel'  => __( 'Add the frontend app URL', 'pressbridge' ),
			'linkTarget' => '_self',
		);
	}

	/**
	 * Build a React view URL for the post currently being edited.
	 *
	 * @return string
	 */
	private function get_current_editor_react_url() {
		$post = $this->get_current_editor_post();

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		return $this->react_view_links->get_frontend_url_for_post( $post );
	}

	/**
	 * Get the current post being edited.
	 *
	 * @return WP_Post|null
	 */
	private function get_current_editor_post() {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		return $post instanceof WP_Post ? $post : null;
	}
}
