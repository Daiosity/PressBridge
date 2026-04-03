<?php
/**
 * Shared helpers for translating WordPress URLs and request paths into frontend paths.
 *
 * @package WP_To_React\Core
 */

namespace WP_To_React\Core;

use WP_Post;

class Path_Helper {
	/**
	 * Normalize an arbitrary path for frontend routing.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public function normalize_path( $path ) {
		$path = is_string( $path ) ? wp_unslash( $path ) : '/';
		$path = trim( $path );
		$path = '/' . ltrim( $path, '/' );

		return '/' === $path ? '/' : trailingslashit( $path );
	}

	/**
	 * Remove the site's home path prefix from a request path.
	 *
	 * @param string $path Request path.
	 * @return string
	 */
	public function strip_home_path_prefix( $path ) {
		$path      = is_string( $path ) ? $path : '/';
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( is_string( $home_path ) && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		return $this->normalize_path( $path );
	}

	/**
	 * Convert a permalink or URL into a frontend-relative path.
	 *
	 * @param string $url URL to normalize.
	 * @return string
	 */
	public function get_relative_path_from_url( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '/';
		}

		return $this->strip_home_path_prefix( $path );
	}

	/**
	 * Get the normalized frontend path for a post object or ID.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 * @return string
	 */
	public function get_post_path( $post ) {
		$permalink = get_permalink( $post );

		if ( empty( $permalink ) ) {
			return '/';
		}

		return $this->get_relative_path_from_url( $permalink );
	}

	/**
	 * Get a normalized frontend path from the current request URI.
	 *
	 * @param string $request_uri Request URI.
	 * @return string
	 */
	public function get_request_path( $request_uri ) {
		$path = wp_parse_url( (string) $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '/';
		}

		return $this->strip_home_path_prefix( $path );
	}
}
