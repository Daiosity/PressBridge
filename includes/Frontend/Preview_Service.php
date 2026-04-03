<?php
/**
 * Create signed preview links for cross-domain frontends.
 *
 * @package WP_To_React\Frontend
 */

namespace WP_To_React\Frontend;

use WP_Error;
use WP_Post;
use WP_To_React\Core\Path_Helper;
use WP_To_React\Core\Settings;
use WP_To_React\Data\Post_Type_Service;

class Preview_Service {
	/**
	 * Preview token expiration.
	 */
	const TOKEN_TTL = 900;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Supported post types.
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
	 * Register preview hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'preview_post_link', array( $this, 'filter_preview_link' ), 10, 2 );
	}

	/**
	 * Replace the default preview link with a frontend-ready preview URL.
	 *
	 * @param string  $preview_link Preview URL.
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function filter_preview_link( $preview_link, $post ) {
		if ( ! $post instanceof WP_Post || ! $this->settings->has_valid_frontend_url() ) {
			return $preview_link;
		}

		if ( ! $this->post_type_service->is_supported( $post->post_type ) ) {
			return $preview_link;
		}

		$preview_post = $this->get_preview_source_post( $post );
		$token        = $this->create_token(
			array(
				'post_id'     => (int) $post->ID,
				'revision_id' => $preview_post->ID !== $post->ID ? (int) $preview_post->ID : 0,
				'user_id'     => get_current_user_id(),
				'created_at'  => time(),
			)
		);
		$path  = $this->get_post_path( $post );

		if ( empty( $token ) ) {
			return $preview_link;
		}

		return $this->settings->build_frontend_url(
			$path,
			array(
				'wtr_preview'       => '1',
				'wtr_preview_token' => $token,
			)
		);
	}

	/**
	 * Resolve a preview token into preview metadata and content sources.
	 *
	 * @param string $token Preview token.
	 * @return array|WP_Error
	 */
	public function get_preview_data_from_token( $token ) {
		if ( ! preg_match( '/^[A-Za-z0-9]+$/', $token ) ) {
			return new WP_Error(
				'wtr_invalid_preview_token',
				__( 'The preview token format is invalid.', 'pressbridge' ),
				array( 'status' => 400 )
			);
		}

		$data = get_transient( $this->get_transient_key( $token ) );

		if ( empty( $data['post_id'] ) ) {
			return new WP_Error(
				'wtr_preview_expired',
				__( 'This preview link is missing or has expired.', 'pressbridge' ),
				array( 'status' => 404 )
			);
		}

		$post = get_post( (int) $data['post_id'] );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'wtr_preview_post_missing',
				__( 'The content for this preview link could not be found.', 'pressbridge' ),
				array( 'status' => 404 )
			);
		}

		$preview_post = $post;
		$source       = 'saved';

		if ( ! empty( $data['revision_id'] ) ) {
			$revision = get_post( (int) $data['revision_id'] );

			if ( $revision instanceof WP_Post && (int) $revision->post_parent === (int) $post->ID ) {
				$preview_post = $revision;
				$source       = 'autosave';
			}
		}

		$created_at = ! empty( $data['created_at'] ) ? (int) $data['created_at'] : time();
		$expires_at = $created_at + self::TOKEN_TTL;

		return array(
			'post'         => $post,
			'preview_post' => $preview_post,
			'source'       => $source,
			'created_at'   => $created_at,
			'expires_at'   => $expires_at,
			'expires_in'   => max( 0, $expires_at - time() ),
		);
	}

	/**
	 * Create and persist a temporary preview token.
	 *
	 * @param array $payload Preview payload.
	 * @return string
	 */
	private function create_token( array $payload ) {
		$token = wp_generate_password( 24, false, false );

		set_transient(
			$this->get_transient_key( $token ),
			$payload,
			self::TOKEN_TTL
		);

		return $token;
	}

	/**
	 * Build a transient key for a token.
	 *
	 * @param string $token Token value.
	 * @return string
	 */
	private function get_transient_key( $token ) {
		return 'wtr_preview_' . md5( (string) $token );
	}

	/**
	 * Get the frontend path for a post preview.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function get_post_path( WP_Post $post ) {
		return $this->path_helper->get_post_path( $post );
	}

	/**
	 * Get the most useful preview source for the current user.
	 *
	 * @param WP_Post $post Canonical post object.
	 * @return WP_Post
	 */
	private function get_preview_source_post( WP_Post $post ) {
		$current_user_id = get_current_user_id();

		if ( $current_user_id && function_exists( 'wp_get_post_autosave' ) ) {
			$autosave = wp_get_post_autosave( $post->ID, $current_user_id );

			if ( $autosave instanceof WP_Post ) {
				return $autosave;
			}
		}

		return $post;
	}
}
