<?php
/**
 * Export the React starter package.
 *
 * @package WP_To_React\Admin
 */

namespace WP_To_React\Admin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_To_React\Core\Settings;
use ZipArchive;

class Starter_Export {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_wtr_download_starter', array( $this, 'handle_download' ) );
	}

	/**
	 * Whether zipped starter exports are supported.
	 *
	 * @return bool
	 */
	public function is_supported() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Get the signed download URL.
	 *
	 * @return string
	 */
	public function get_download_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=wtr_download_starter' ),
			'wtr_download_starter'
		);
	}

	/**
	 * Stream the starter zip file to the browser.
	 *
	 * @return void
	 */
	public function handle_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export the starter app.', 'pressbridge' ) );
		}

		check_admin_referer( 'wtr_download_starter' );

		if ( ! $this->is_supported() ) {
			wp_die( esc_html__( 'ZipArchive is not available on this server.', 'pressbridge' ) );
		}

		$template_dir = WTR_PLUGIN_DIR . 'assets/starter';
		$temp_file    = wp_tempnam( 'lenviqa-starter.zip' );
		$zip          = new ZipArchive();
		$filename     = 'lenviqa-starter-' . gmdate( 'Ymd-His' ) . '.zip';

		if ( empty( $temp_file ) ) {
			wp_die( esc_html__( 'Unable to create a temporary file for the starter export.', 'pressbridge' ) );
		}

		if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Unable to generate the starter app archive.', 'pressbridge' ) );
		}

		$replacements = $this->get_replacements();
		$iterator     = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $template_dir, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			$source_path = $file->getPathname();
			$zip_path    = str_replace(
				array( $template_dir . DIRECTORY_SEPARATOR, '\\' ),
				array( '', '/' ),
				$source_path
			);

			if ( $this->is_text_file( $zip_path ) ) {
				$contents = file_get_contents( $source_path );

				if ( false === $contents ) {
					$zip->close();
					unlink( $temp_file );
					wp_die( esc_html__( 'Unable to read one of the starter template files.', 'pressbridge' ) );
				}

				$zip->addFromString( $zip_path, strtr( $contents, $replacements ) );
			} else {
				$zip->addFile( $source_path, $zip_path );
			}
		}

		$zip->addFromString(
			'src/config/wp-config.json',
			wp_json_encode( $this->get_runtime_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		$zip->close();

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $temp_file ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );

		readfile( $temp_file );
		unlink( $temp_file );
		exit;
	}

	/**
	 * Build token replacements for starter template files.
	 *
	 * @return array
	 */
	private function get_replacements() {
		return array(
			'__WTR_SITE_URL__'      => home_url( '/' ),
			'__WTR_FRONTEND_URL__'  => $this->settings->get_frontend_url(),
			'__WTR_API_BASE__'      => untrailingslashit( rest_url( 'pressbridge/v1' ) ),
			'__WTR_PLUGIN_NAME__'   => get_bloginfo( 'name' ),
			'__WTR_PREVIEW_ROUTE__' => rest_url( 'pressbridge/v1/preview' ),
		);
	}

	/**
	 * Runtime configuration added to the export package.
	 *
	 * @return array
	 */
	private function get_runtime_config() {
		return array(
			'siteUrl'      => home_url( '/' ),
			'apiBase'      => untrailingslashit( rest_url( 'pressbridge/v1' ) ),
			'frontendUrl'  => $this->settings->get_frontend_url(),
			'routeMode'    => $this->settings->get_route_handling_mode(),
			'headlessMode' => $this->settings->is_headless_enabled(),
		);
	}

	/**
	 * Determine whether a starter file should receive string replacement.
	 *
	 * @param string $path Relative zip path.
	 * @return bool
	 */
	private function is_text_file( $path ) {
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		if ( '.env.example' === substr( $path, -12 ) ) {
			return true;
		}

		return in_array( strtolower( $extension ), array( 'json', 'js', 'jsx', 'css', 'html', 'md', 'txt' ), true );
	}
}
