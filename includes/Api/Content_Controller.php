<?php
/**
 * Content endpoints for pages, posts, single content, path resolution, and previews.
 *
 * @package WP_To_React\Api
 */

namespace WP_To_React\Api;

use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_To_React\Core\Path_Helper;
use WP_To_React\Data\Content_Mapper;
use WP_To_React\Data\Post_Type_Service;
use WP_To_React\Frontend\Preview_Service;

class Content_Controller {
	/**
	 * Namespace.
	 */
	const NAMESPACE = 'pressbridge/v1';

	/**
	 * Content mapper.
	 *
	 * @var Content_Mapper
	 */
	private $mapper;

	/**
	 * Preview service.
	 *
	 * @var Preview_Service
	 */
	private $preview_service;

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
	 * @param Content_Mapper  $mapper Mapper service.
	 * @param Preview_Service $preview_service Preview service.
	 */
	public function __construct( Content_Mapper $mapper, Preview_Service $preview_service, Post_Type_Service $post_type_service, Path_Helper $path_helper ) {
		$this->mapper            = $mapper;
		$this->preview_service   = $preview_service;
		$this->post_type_service = $post_type_service;
		$this->path_helper       = $path_helper;
	}

	/**
	 * Register all content routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/pages',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pages' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_posts' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_content_by_slug' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( $this, 'validate_content_type' ),
					),
					'slug' => array(
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_slug_path' ),
						'validate_callback' => array( $this, 'validate_slug_path' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/items',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => array_merge(
					$this->get_collection_args(),
					array(
						'type' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => array( $this, 'validate_content_type' ),
						),
					)
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content-types',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_content_types' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/resolve',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'resolve_content' ),
				'permission_callback' => '__return_true',
				'args'                => array_merge(
					$this->get_collection_args(),
					array(
						'path' => array(
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_path' ),
						),
					)
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/preview/(?P<token>[A-Za-z0-9]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_preview_content' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return published pages.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_pages( WP_REST_Request $request ) {
		return $this->get_collection_response( 'page', $request );
	}

	/**
	 * Return published posts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_posts( WP_REST_Request $request ) {
		return $this->get_collection_response( 'post', $request );
	}

	/**
	 * Return a collection for any supported public content type.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ) {
		return $this->get_collection_response( $request->get_param( 'type' ), $request );
	}

	/**
	 * Return a single page or post by slug.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_content_by_slug( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		$slug = (string) $request->get_param( 'slug' );
		$post = $this->find_post_by_slug( $type, $slug );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'wtr_content_not_found',
					__( 'Content not found for the requested type and slug.', 'pressbridge' ),
				array( 'status' => 404 )
			);
		}

		$data               = $this->mapper->map_post(
			$post,
			array(
				'include_blocks' => true,
			)
		);
		$data['route_type'] = 'singular';

		return rest_ensure_response( $data );
	}

	/**
	 * Resolve a frontend path into a WordPress entity.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function resolve_content( WP_REST_Request $request ) {
		$path    = $this->sanitize_path( $request->get_param( 'path' ) );
		$archive = $this->resolve_archive_route( $path, $request );

		if ( is_array( $archive ) ) {
			return rest_ensure_response( $archive );
		}

		$post = $this->resolve_singular_route( $path );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'wtr_route_not_found',
					__( 'No page or post matches the requested path.', 'pressbridge' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return new WP_Error(
				'wtr_route_not_public',
					__( 'The requested route is not available publicly.', 'pressbridge' ),
				array( 'status' => 404 )
			);
		}

		$data               = $this->mapper->map_post(
			$post,
			array(
				'include_blocks' => true,
			)
		);
		$data['route_type'] = 'singular';

		return rest_ensure_response( $data );
	}

	/**
	 * Return content for a signed preview token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_preview_content( WP_REST_Request $request ) {
		$preview = $this->preview_service->get_preview_data_from_token( (string) $request['token'] );

		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		if ( ! $this->post_type_service->is_supported( $preview['post']->post_type ) ) {
			return new WP_Error(
				'wtr_preview_post_type_unsupported',
					__( 'This preview link points to a content type that is no longer supported by the bridge.', 'pressbridge' ),
				array( 'status' => 404 )
			);
		}

		$data               = $this->mapper->map_post(
			$preview['post'],
			array(
				'is_preview'     => true,
				'preview_post'   => $preview['preview_post'],
				'include_blocks' => true,
			)
		);
		$data['is_preview'] = true;
		$data['route_type'] = 'singular';
		$data['preview']    = array(
			'source'            => $preview['source'],
			'source_label'      => 'autosave' === $preview['source']
					? __( 'Showing unpublished editor changes from WordPress.', 'pressbridge' )
					: __( 'Showing the latest saved WordPress snapshot for this entry.', 'pressbridge' ),
			'token_expires_at'  => gmdate( 'c', (int) $preview['expires_at'] ),
			'token_expires_in'  => (int) $preview['expires_in'],
			'canonical_path'    => $data['path'],
			'canonical_url'     => $data['link'],
		);

		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Return supported public post types.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_content_types( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'items' => array_values( $this->post_type_service->get_supported_post_types() ),
			)
		);
	}

	/**
	 * Validate supported content types.
	 *
	 * @param string $value Route value.
	 * @return bool
	 */
	public function validate_content_type( $value ) {
		return $this->post_type_service->is_supported( $value );
	}

	/**
	 * Normalize a request path.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public function sanitize_path( $path ) {
		return $this->path_helper->strip_home_path_prefix( $path );
	}

	/**
	 * Normalize a slug or nested slug path.
	 *
	 * @param string $slug Raw slug.
	 * @return string
	 */
	public function sanitize_slug_path( $slug ) {
		$slug = is_string( $slug ) ? wp_unslash( $slug ) : '';
		$slug = trim( $slug );
		$slug = trim( $slug, '/' );

		if ( '' === $slug ) {
			return '';
		}

		$segments = array_filter(
			array_map( 'sanitize_title', explode( '/', $slug ) ),
			'strlen'
		);

		return implode( '/', $segments );
	}

	/**
	 * Validate a slug or nested slug path.
	 *
	 * @param string $slug Sanitized slug.
	 * @return bool
	 */
	public function validate_slug_path( $slug ) {
		return is_string( $slug ) && '' !== trim( $slug );
	}

	/**
	 * Validate positive collection integers.
	 *
	 * @param mixed $value Request value.
	 * @return bool
	 */
	public function validate_positive_integer( $value ) {
		return is_numeric( $value ) && (int) $value >= 1;
	}

	/**
	 * Fetch a collection of published content.
	 *
	 * @param string          $post_type Post type.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	private function get_collection_response( $post_type, WP_REST_Request $request ) {
		$per_page = min( max( 1, (int) $request->get_param( 'per_page' ) ), 100 );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			's'                      => $search,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
			'no_found_rows'          => false,
		);

		if ( 'page' === $post_type ) {
			$query_args['orderby'] = array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			);
			$query_args['order']   = 'ASC';
		} else {
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
		}

		$query = new WP_Query( $query_args );
		$items = array_map( array( $this->mapper, 'map_post' ), $query->posts );

		$response = rest_ensure_response(
			array(
				'items'       => $items,
				'post_type'   => $post_type,
				'currentPage' => $page,
				'perPage'     => $per_page,
			)
		);

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Resolve a singular route using permalink resolution with a safe hierarchical fallback.
	 *
	 * @param string $path Normalized route path.
	 * @return WP_Post|null
	 */
	private function resolve_singular_route( $path ) {
		$post_id = url_to_postid( home_url( $path ) );

		if ( ! $post_id && '/' === $path && 'page' === get_option( 'show_on_front' ) ) {
			$post_id = (int) get_option( 'page_on_front' );
		}

		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof WP_Post ) {
				return $post;
			}
		}

		$slug_path = trim( (string) $path, '/' );

		if ( '' === $slug_path ) {
			return null;
		}

		foreach ( $this->post_type_service->get_supported_post_types() as $post_type => $definition ) {
			if ( empty( $definition['hierarchical'] ) ) {
				continue;
			}

			$post = get_page_by_path( $slug_path, OBJECT, $post_type );

			if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Resolve archive-like routes such as the posts page or CPT archives.
	 *
	 * @param string          $path Normalized request path.
	 * @param WP_REST_Request $request Request object.
	 * @return array|null
	 */
	private function resolve_archive_route( $path, WP_REST_Request $request ) {
		if ( $this->is_posts_archive_path( $path ) ) {
			$posts_page_id = (int) get_option( 'page_for_posts' );
		$title         = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'pressbridge' );
			$description   = $posts_page_id ? get_post_field( 'post_excerpt', $posts_page_id ) : '';

			return $this->build_archive_response(
				'post',
				$path,
				$request,
				array(
					'title'           => $title,
					'description'     => $description,
					'is_posts_page'   => true,
					'archive_page_id' => $posts_page_id,
				)
			);
		}

		foreach ( $this->post_type_service->get_supported_post_types() as $post_type => $definition ) {
			if ( 'post' === $post_type || empty( $definition['has_archive'] ) ) {
				continue;
			}

			$archive_link = get_post_type_archive_link( $post_type );
			$archive_path = $archive_link ? $this->sanitize_path( wp_parse_url( $archive_link, PHP_URL_PATH ) ) : '';

			if ( empty( $archive_path ) || $archive_path !== $path ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post_type );
			$title            = $post_type_object && ! empty( $post_type_object->labels->name ) ? $post_type_object->labels->name : $definition['label'];
			$description      = $post_type_object && ! empty( $post_type_object->description ) ? $post_type_object->description : '';

			return $this->build_archive_response(
				$post_type,
				$path,
				$request,
				array(
					'title'         => $title,
					'description'   => $description,
					'is_posts_page' => false,
				)
			);
		}

		return null;
	}

	/**
	 * Build a normalized archive response for frontend routing.
	 *
	 * @param string          $post_type Post type name.
	 * @param string          $path Normalized path.
	 * @param WP_REST_Request $request Request object.
	 * @param array           $context Archive context.
	 * @return array
	 */
	private function build_archive_response( $post_type, $path, WP_REST_Request $request, array $context = array() ) {
		$per_page         = min( max( 1, (int) $request->get_param( 'per_page' ) ), 100 );
		$page             = max( 1, (int) $request->get_param( 'page' ) );
		$search           = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$post_type_object = get_post_type_object( $post_type );

		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				's'                      => $search,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
				'no_found_rows'          => false,
			)
		);

		return array(
			'route_type'      => 'archive',
			'archive_type'    => 'post_type',
			'post_type'       => $post_type,
			'post_type_label' => $post_type_object ? $post_type_object->label : $post_type,
			'title'           => ! empty( $context['title'] ) ? $context['title'] : ( $post_type_object ? $post_type_object->label : $post_type ),
			'description'     => ! empty( $context['description'] ) ? wp_strip_all_tags( $context['description'] ) : '',
			'path'            => $path,
			'link'            => home_url( $path ),
			'items'           => array_map( array( $this->mapper, 'map_post' ), $query->posts ),
			'currentPage'     => $page,
			'perPage'         => $per_page,
			'totalItems'      => (int) $query->found_posts,
			'totalPages'      => (int) $query->max_num_pages,
			'search'          => $search,
			'is_posts_page'   => ! empty( $context['is_posts_page'] ),
			'archive_page_id' => ! empty( $context['archive_page_id'] ) ? (int) $context['archive_page_id'] : 0,
		);
	}

	/**
	 * Resolve content by type and slug/path.
	 *
	 * @param string $type Content type.
	 * @param string $slug Slug or page path.
	 * @return WP_Post|null
	 */
	private function find_post_by_slug( $type, $slug ) {
		$post_type_object = get_post_type_object( $type );

		if ( $post_type_object && ! empty( $post_type_object->hierarchical ) ) {
			$page = get_page_by_path( $slug, OBJECT, $type );

			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				return $page;
			}

			return null;
		}

		$query = new WP_Query(
			array(
				'name'                   => $slug,
				'post_type'              => $type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return ! empty( $query->posts[0] ) ? $query->posts[0] : null;
	}

	/**
	 * Determine whether a path should resolve as the posts archive.
	 *
	 * @param string $path Normalized path.
	 * @return bool
	 */
	private function is_posts_archive_path( $path ) {
		if ( '/' === $path && 'posts' === get_option( 'show_on_front', 'posts' ) ) {
			return true;
		}

		$posts_page_id = (int) get_option( 'page_for_posts' );

		if ( ! $posts_page_id ) {
			return false;
		}

		return $this->sanitize_path( $this->get_post_path( $posts_page_id ) ) === $path;
	}

	/**
	 * Get the normalized frontend path for a post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_post_path( $post_id ) {
		return $this->path_helper->get_post_path( $post_id );
	}

	/**
	 * Shared collection args.
	 *
	 * @return array
	 */
	private function get_collection_args() {
		return array(
			'page' => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => array( $this, 'validate_positive_integer' ),
			),
			'per_page' => array(
				'default'           => 10,
				'sanitize_callback' => 'absint',
				'validate_callback' => array( $this, 'validate_positive_integer' ),
			),
			'search' => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
