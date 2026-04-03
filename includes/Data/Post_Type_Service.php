<?php
/**
 * Discover supported post types for the headless bridge.
 *
 * @package WP_To_React\Data
 */

namespace WP_To_React\Data;

class Post_Type_Service {
	/**
	 * Return supported public content types.
	 *
	 * @return array
	 */
	public function get_supported_post_types() {
		$objects = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$supported = array();

		foreach ( $objects as $post_type => $object ) {
			if ( in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' ), true ) ) {
				continue;
			}

			$supported[ $post_type ] = array(
				'name'          => $post_type,
				'label'         => $object->label,
				'singular_name' => isset( $object->labels->singular_name ) ? $object->labels->singular_name : $object->label,
				'has_archive'   => (bool) $object->has_archive,
				'hierarchical'  => (bool) $object->hierarchical,
				'rest_base'     => ! empty( $object->rest_base ) ? $object->rest_base : $post_type,
			);
		}

		return $supported;
	}

	/**
	 * Check whether a content type is supported.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function is_supported( $post_type ) {
		$supported = $this->get_supported_post_types();

		return isset( $supported[ $post_type ] );
	}

	/**
	 * Get one supported post type definition.
	 *
	 * @param string $post_type Post type name.
	 * @return array|null
	 */
	public function get_post_type( $post_type ) {
		$supported = $this->get_supported_post_types();

		return isset( $supported[ $post_type ] ) ? $supported[ $post_type ] : null;
	}
}
