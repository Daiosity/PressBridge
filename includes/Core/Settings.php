<?php
/**
 * Plugin settings model and sanitization.
 *
 * @package WP_To_React\Core
 */

namespace WP_To_React\Core;

class Settings {
	/**
	 * Option key.
	 */
	const OPTION_NAME = 'wtr_settings';

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'pressbridge';

	/**
	 * Allowed route handling modes.
	 *
	 * @var array
	 */
	private $route_modes = array(
		'redirect'  => 'Redirect public frontend requests to React',
		'wordpress' => 'Keep WordPress rendering public pages',
	);

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the settings option.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wtr_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'headless_mode'       => false,
			'frontend_url'        => '',
			'route_handling_mode' => 'wordpress',
			'enable_debug'        => false,
		);
	}

	/**
	 * Get every stored setting merged with defaults.
	 *
	 * @return array
	 */
	public function get_all() {
		if ( null === $this->settings ) {
			$stored         = get_option( self::OPTION_NAME, array() );
			$this->settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::get_default_settings() );
		}

		return $this->settings;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Get route mode labels.
	 *
	 * @return array
	 */
	public function get_route_modes() {
		return $this->route_modes;
	}

	/**
	 * Sanitize the full settings payload.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		$route_mode = isset( $input['route_handling_mode'] ) ? sanitize_key( $input['route_handling_mode'] ) : 'wordpress';
		if ( ! array_key_exists( $route_mode, $this->route_modes ) ) {
			$route_mode = 'wordpress';
		}

		$frontend_url = '';
		if ( ! empty( $input['frontend_url'] ) ) {
			$frontend_url = $this->sanitize_frontend_url( wp_unslash( $input['frontend_url'] ) );
		}

		if ( ! empty( $input['frontend_url'] ) && empty( $frontend_url ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'wtr_invalid_frontend_url',
				__( 'The frontend app URL must be a valid http or https URL, such as http://localhost:5173 or https://frontend.example.com.', 'pressbridge' ),
				'error'
			);
		}

		if ( ! empty( $input['headless_mode'] ) && empty( $frontend_url ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'wtr_headless_missing_frontend',
				__( 'Headless mode was saved, but no valid frontend URL is configured. Public redirects and preview links will stay inactive until the URL is fixed.', 'pressbridge' ),
				'warning'
			);
		}

		$this->settings = array(
			'headless_mode'       => ! empty( $input['headless_mode'] ),
			'frontend_url'        => $frontend_url,
			'route_handling_mode' => $route_mode,
			'enable_debug'        => ! empty( $input['enable_debug'] ),
		);

		return $this->settings;
	}

	/**
	 * Normalize and validate the frontend URL.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function sanitize_frontend_url( $url ) {
		$url   = trim( $url );
		$url   = esc_url_raw( $url );
		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		if ( ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
			return '';
		}

		$normalized = $parts['scheme'] . '://' . $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$normalized .= ':' . absint( $parts['port'] );
		}

		if ( ! empty( $parts['path'] ) && '/' !== $parts['path'] ) {
			$normalized .= '/' . ltrim( untrailingslashit( $parts['path'] ), '/' );
		}

		return untrailingslashit( $normalized );
	}

	/**
	 * Whether headless mode is enabled.
	 *
	 * @return bool
	 */
	public function is_headless_enabled() {
		return (bool) $this->get( 'headless_mode', false );
	}

	/**
	 * Whether debug output is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_enabled() {
		return (bool) $this->get( 'enable_debug', false );
	}

	/**
	 * Whether a valid frontend URL exists.
	 *
	 * @return bool
	 */
	public function has_valid_frontend_url() {
		return ! empty( $this->get_frontend_url() );
	}

	/**
	 * Get the frontend URL.
	 *
	 * @return string
	 */
	public function get_frontend_url() {
		return (string) $this->get( 'frontend_url', '' );
	}

	/**
	 * Get the configured route handling mode.
	 *
	 * @return string
	 */
	public function get_route_handling_mode() {
		return (string) $this->get( 'route_handling_mode', 'wordpress' );
	}

	/**
	 * Whether public requests should be redirected.
	 *
	 * @return bool
	 */
	public function should_redirect_public_routes() {
		return $this->is_headless_enabled()
			&& $this->has_valid_frontend_url()
			&& 'redirect' === $this->get_route_handling_mode();
	}

	/**
	 * Build a frontend URL from a path and query args.
	 *
	 * @param string $path Relative path.
	 * @param array  $query_args Query string params.
	 * @return string
	 */
	public function build_frontend_url( $path = '/', $query_args = array() ) {
		if ( ! $this->has_valid_frontend_url() ) {
			return '';
		}

		$path = '/' . ltrim( (string) $path, '/' );
		$url  = untrailingslashit( $this->get_frontend_url() ) . $path;

		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}

		return $url;
	}
}
