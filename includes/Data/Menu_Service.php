<?php
/**
 * Retrieve and normalize WordPress menus.
 *
 * @package WP_To_React\Data
 */

namespace WP_To_React\Data;

class Menu_Service {
	/**
	 * Build the menu payload for the API.
	 *
	 * @return array
	 */
	public function get_menus_response() {
		$registered_locations = get_registered_nav_menus();
		$location_map         = get_nav_menu_locations();
		$locations            = array();
		$seen_menu_ids        = array();

		foreach ( $registered_locations as $location => $label ) {
			$menu_id = isset( $location_map[ $location ] ) ? (int) $location_map[ $location ] : 0;

			if ( $menu_id ) {
				$seen_menu_ids[] = $menu_id;
			}

			$locations[ $location ] = array(
				'location' => $location,
				'label'    => $label,
				'menu'     => $menu_id ? $this->get_menu_data( $menu_id ) : null,
			);
		}

		$unassigned = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			if ( in_array( (int) $menu->term_id, $seen_menu_ids, true ) ) {
				continue;
			}

			$unassigned[] = $this->get_menu_data( (int) $menu->term_id );
		}

		return array(
			'locations'  => $locations,
			'unassigned' => $unassigned,
		);
	}

	/**
	 * Get a normalized menu object.
	 *
	 * @param int $menu_id Menu ID.
	 * @return array|null
	 */
	private function get_menu_data( $menu_id ) {
		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return null;
		}

		$items = wp_get_nav_menu_items(
			$menu_id,
			array(
				'update_post_term_cache' => false,
			)
		);

		return array(
			'id'    => (int) $menu->term_id,
			'name'  => $menu->name,
			'slug'  => $menu->slug,
			'items' => $this->build_tree( is_array( $items ) ? $items : array() ),
		);
	}

	/**
	 * Convert flat nav items into a nested tree.
	 *
	 * @param array $items Flat menu items.
	 * @return array
	 */
	private function build_tree( $items ) {
		$indexed = array();
		$tree    = array();

		foreach ( $items as $item ) {
			$indexed[ $item->ID ] = array(
				'id'          => (int) $item->ID,
				'parent_id'   => (int) $item->menu_item_parent,
				'title'       => $item->title,
				'url'         => $item->url,
				'target'      => $item->target,
				'type'        => $item->type,
				'object'      => $item->object,
				'object_id'   => (int) $item->object_id,
				'description' => $item->description,
				'classes'     => array_values( array_filter( (array) $item->classes ) ),
				'children'    => array(),
			);
		}

		foreach ( array_keys( $indexed ) as $item_id ) {
			$parent_id = $indexed[ $item_id ]['parent_id'];

			if ( $parent_id && isset( $indexed[ $parent_id ] ) ) {
				$indexed[ $parent_id ]['children'][] = &$indexed[ $item_id ];
			} else {
				$tree[] = &$indexed[ $item_id ];
			}
		}

		return $tree;
	}
}
