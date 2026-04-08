<?php
/**
 * Convert WordPress posts into React-friendly objects.
 *
 * @package WP_To_React\Data
 */

namespace WP_To_React\Data;

use WP_To_React\Core\Path_Helper;
use WP_Post;

class Content_Mapper {
	/**
	 * Shortcodes that should prefer server-rendered HTML over block translation.
	 *
	 * @var array<string, string>
	 */
	private const SHORTCODE_COMPATIBILITY_TAGS = array(
		'products'              => 'woocommerce',
		'product_page'          => 'woocommerce',
		'product_category'      => 'woocommerce',
		'product_categories'    => 'woocommerce',
		'sale_products'         => 'woocommerce',
		'best_selling_products' => 'woocommerce',
		'recent_products'       => 'woocommerce',
		'featured_products'     => 'woocommerce',
		'top_rated_products'    => 'woocommerce',
		'woocommerce_cart'      => 'woocommerce',
		'woocommerce_checkout'  => 'woocommerce',
		'woocommerce_my_account'=> 'woocommerce',
		'add_to_cart'           => 'woocommerce',
	);

	/**
	 * Shared path helper.
	 *
	 * @var Path_Helper
	 */
	private $path_helper;

	/**
	 * Constructor.
	 *
	 * @param Path_Helper $path_helper Shared path helper.
	 */
	public function __construct( Path_Helper $path_helper ) {
		$this->path_helper = $path_helper;
	}

	/**
	 * Map a post into a normalized structure.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $context Mapping context.
	 * @return array
	 */
	public function map_post( WP_Post $post, $context = array() ) {
		$post_type_object = get_post_type_object( $post->post_type );
		$preview_post     = ! empty( $context['preview_post'] ) && $context['preview_post'] instanceof WP_Post
			? $context['preview_post']
			: $post;
		$include_blocks   = ! empty( $context['include_blocks'] );
		$title            = $this->get_render_title( $post, $preview_post );
		$excerpt          = $this->get_render_excerpt( $post, $preview_post );
		$content          = $this->render_content( $post, $preview_post->post_content );
		$blocks           = $include_blocks ? $this->get_render_blocks( $post, $preview_post->post_content ) : array();
		$compatibility    = $this->get_content_compatibility( $preview_post->post_content );
		$modified_at      = $preview_post->post_modified_gmt ? $preview_post->post_modified_gmt : $preview_post->post_modified;

		$data = array(
			'id'             => (int) $post->ID,
			'post_type'      => $post->post_type,
			'post_type_label'=> $post_type_object ? $post_type_object->label : $post->post_type,
			'status'         => $post->post_status,
			'slug'           => $post->post_name,
			'path'           => $this->get_relative_path( $post ),
			'title'          => $title,
			'excerpt'        => $excerpt,
			'content'        => $content,
			'featured_image' => $this->get_featured_image( $post ),
			'terms'          => $this->get_terms( $post ),
			'parent_id'      => (int) $post->post_parent,
			'menu_order'     => (int) $post->menu_order,
			'author'         => array(
				'id'   => (int) $post->post_author,
				'name' => get_the_author_meta( 'display_name', $post->post_author ),
			),
			'published_at'   => mysql_to_rfc3339( $post->post_date_gmt ? $post->post_date_gmt : $post->post_date ),
			'modified_at'    => mysql_to_rfc3339( $modified_at ),
			'link'           => get_permalink( $post ),
			'is_front_page'  => (int) get_option( 'page_on_front' ) === (int) $post->ID,
			'is_posts_page'  => (int) get_option( 'page_for_posts' ) === (int) $post->ID,
			'is_preview'     => ! empty( $context['is_preview'] ),
			'render_mode'    => $compatibility['render_mode'],
			'compatibility'  => $compatibility,
		);

		if ( $include_blocks ) {
			$data['blocks'] = $blocks;
		}

		return apply_filters( 'wtr_mapped_post', $data, $post, $context );
	}

	/**
	 * Detect content that should prefer a server-rendered compatibility path.
	 *
	 * @param string $content Raw post content.
	 * @return array
	 */
	private function get_content_compatibility( $content ) {
		$shortcodes        = $this->detect_compatible_shortcodes( (string) $content );
		$sources           = array_values(
			array_unique(
				array_map(
					static function ( $shortcode ) {
						return self::SHORTCODE_COMPATIBILITY_TAGS[ $shortcode ] ?? 'generic';
					},
					$shortcodes
				)
			)
		);
		$is_shortcode_page = ! empty( $shortcodes );
		$is_woo_page       = in_array( 'woocommerce', $sources, true );

		return array(
			'render_mode'                    => $is_shortcode_page ? 'html' : 'blocks',
			'shortcodes'                     => $shortcodes,
			'sources'                        => $sources,
			'is_shortcode_content'           => $is_shortcode_page,
			'is_woocommerce_shortcode_page'  => $is_woo_page,
		);
	}

	/**
	 * Detect approved shortcode tags present in raw content.
	 *
	 * @param string $content Raw post content.
	 * @return array
	 */
	private function detect_compatible_shortcodes( $content ) {
		if ( '' === trim( $content ) ) {
			return array();
		}

		$tags = array_keys( self::SHORTCODE_COMPATIBILITY_TAGS );

		if ( empty( $tags ) ) {
			return array();
		}

		$pattern = get_shortcode_regex( $tags );

		if ( empty( $pattern ) || ! preg_match_all( '/' . $pattern . '/', $content, $matches ) ) {
			return array();
		}

		$detected = array();

		foreach ( $matches[2] as $tag ) {
			$tag = sanitize_key( $tag );

			if ( isset( self::SHORTCODE_COMPATIBILITY_TAGS[ $tag ] ) && shortcode_exists( $tag ) ) {
				$detected[] = $tag;
			}
		}

		return array_values( array_unique( $detected ) );
	}

	/**
	 * Render post content with the current post context.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function render_content( WP_Post $post, $content = null ) {
		$raw_content    = null === $content ? $post->post_content : $content;
		$compatibility  = $this->get_content_compatibility( $raw_content );

		if ( ! empty( $compatibility['is_woocommerce_shortcode_page'] ) ) {
			return $this->render_woocommerce_content( $post, $raw_content );
		}

		return $this->with_post_context(
			$post,
			static function () use ( $raw_content ) {
				return apply_filters( 'the_content', $raw_content );
			}
		);
	}

	/**
	 * Render WooCommerce shortcode content inside a frontend-compatible context.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $content Raw post content.
	 * @return string
	 */
	private function render_woocommerce_content( WP_Post $post, $content ) {
		return $this->with_post_context(
			$post,
			function () use ( $content ) {
				$restore = $this->bootstrap_woocommerce_rendering();
				$output  = apply_filters( 'the_content', $content );

				if ( '' === trim( wp_strip_all_tags( (string) $output ) ) ) {
					$output = do_shortcode( $content );
				}

				if ( is_callable( $restore ) ) {
					$restore();
				}

				return (string) $output;
			}
		);
	}

	/**
	 * Render post excerpt with the current post context.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function render_excerpt( WP_Post $post, $excerpt = null ) {
		if ( null === $excerpt ) {
			$excerpt = $this->with_post_context(
				$post,
				static function () use ( $post ) {
					return get_the_excerpt( $post );
				}
			);
		}

		return html_entity_decode(
			wp_strip_all_tags( (string) $excerpt ),
			ENT_QUOTES,
			get_bloginfo( 'charset' )
		);
	}

	/**
	 * Parse and normalize Gutenberg blocks for frontend rendering.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $content Raw post content.
	 * @return array
	 */
	private function get_render_blocks( WP_Post $post, $content ) {
		$parsed_blocks = parse_blocks( (string) $content );

		if ( empty( $parsed_blocks ) || ! is_array( $parsed_blocks ) ) {
			return array();
		}

		return $this->map_blocks( $post, $parsed_blocks );
	}

	/**
	 * Normalize a parsed block tree.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $blocks Parsed blocks.
	 * @return array
	 */
	private function map_blocks( WP_Post $post, array $blocks ) {
		$mapped_blocks = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$mapped_block = $this->map_block( $post, $block );

			if ( ! empty( $mapped_block ) ) {
				$mapped_blocks[] = $mapped_block;
			}
		}

		return $mapped_blocks;
	}

	/**
	 * Normalize a single parsed block.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $block Parsed block data.
	 * @return array
	 */
	private function map_block( WP_Post $post, array $block ) {
		$inner_blocks = ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $this->map_blocks( $post, $block['innerBlocks'] )
			: array();
		$inner_html   = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
		$rendered     = $this->render_block_markup( $post, $block );
		$raw_markup   = trim( preg_replace( '/<!--[\s\S]*?-->/', '', $rendered . $inner_html ) );

		if ( empty( $inner_blocks ) && '' === $raw_markup ) {
			return array();
		}

		return array(
			'name'          => ! empty( $block['blockName'] ) ? (string) $block['blockName'] : 'core/freeform',
			'attrs'         => $this->sanitize_block_value(
				$this->normalize_block_attrs(
					! empty( $block['blockName'] ) ? (string) $block['blockName'] : 'core/freeform',
					isset( $block['attrs'] ) ? $block['attrs'] : array(),
					$inner_html,
					$rendered
				)
			),
			'inner_html'    => $inner_html,
			'rendered_html' => $rendered,
			'inner_blocks'  => $inner_blocks,
		);
	}

	/**
	 * Enrich parsed block attrs with values Gutenberg often leaves in saved markup.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attrs Parsed attrs.
	 * @param string $inner_html Saved inner HTML.
	 * @param string $rendered Rendered block HTML.
	 * @return array
	 */
	private function normalize_block_attrs( $block_name, $attrs, $inner_html, $rendered ) {
		$attrs = is_array( $attrs ) ? $attrs : array();
		$html  = ! empty( $rendered ) ? $rendered : $inner_html;

		if ( empty( $html ) ) {
			return $attrs;
		}

		switch ( $block_name ) {
			case 'core/image':
				if ( empty( $attrs['url'] ) ) {
					$attrs['url'] = $this->match_markup_attribute( $html, 'img', 'src' );
				}

				if ( empty( $attrs['alt'] ) ) {
					$attrs['alt'] = $this->match_markup_attribute( $html, 'img', 'alt' );
				}

				if ( empty( $attrs['caption'] ) ) {
					$attrs['caption'] = $this->match_markup_fragment( $html, 'figcaption' );
				}
				break;

			case 'core/button':
				if ( empty( $attrs['url'] ) ) {
					$attrs['url'] = $this->match_markup_attribute( $html, 'a', 'href' );
				}

				if ( empty( $attrs['linkTarget'] ) ) {
					$attrs['linkTarget'] = $this->match_markup_attribute( $html, 'a', 'target' );
				}

				if ( empty( $attrs['text'] ) ) {
					$attrs['text'] = wp_strip_all_tags( $this->match_markup_fragment( $html, 'a' ) );
				}
				break;

			case 'core/cover':
				if ( empty( $attrs['url'] ) ) {
					$attrs['url'] = $this->match_markup_attribute( $html, 'img', 'src', 'wp-block-cover__image-background' );
				}
				break;
		}

		return $attrs;
	}

	/**
	 * Extract an attribute from the first matching HTML element in block markup.
	 *
	 * @param string $html Markup.
	 * @param string $tag Tag name.
	 * @param string $attribute Attribute name.
	 * @param string $required_class Optional class name to require on the element.
	 * @return string
	 */
	private function match_markup_attribute( $html, $tag, $attribute, $required_class = '' ) {
		if ( empty( $html ) ) {
			return '';
		}

		$tag_pattern = preg_quote( $tag, '/' );
		$attribute_pattern = preg_quote( $attribute, '/' );
		$class_filter = '';

		if ( '' !== $required_class ) {
			$class_filter = '(?=[^>]*class=["\'][^"\']*' . preg_quote( $required_class, '/' ) . '[^"\']*["\'])';
		}

		if ( preg_match( '/<' . $tag_pattern . '\b' . $class_filter . '[^>]*\b' . $attribute_pattern . '=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		return '';
	}

	/**
	 * Extract the inner HTML of the first matching element in block markup.
	 *
	 * @param string $html Markup.
	 * @param string $tag Tag name.
	 * @return string
	 */
	private function match_markup_fragment( $html, $tag ) {
		if ( empty( $html ) ) {
			return '';
		}

		$tag_pattern = preg_quote( $tag, '/' );

		if ( preg_match( '/<' . $tag_pattern . '\b[^>]*>(.*?)<\/' . $tag_pattern . '>/is', $html, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Render fallback HTML for a parsed block.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $block Parsed block data.
	 * @return string
	 */
	private function render_block_markup( WP_Post $post, array $block ) {
		return (string) $this->with_post_context(
			$post,
			static function () use ( $block ) {
				return render_block( $block );
			}
		);
	}

	/**
	 * Bootstrap WooCommerce session and cart state so frontend shortcodes can render in REST requests.
	 *
	 * @return callable|null
	 */
	private function bootstrap_woocommerce_rendering() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return null;
		}

		$rest_filter = static function () {
			return false;
		};

		add_filter( 'woocommerce_is_rest_api_request', $rest_filter, 0 );

		try {
			if ( method_exists( WC(), 'frontend_includes' ) ) {
				WC()->frontend_includes();
			}

			if ( method_exists( WC(), 'initialize_session' ) ) {
				WC()->initialize_session();
			}

			if ( method_exists( WC(), 'initialize_cart' ) ) {
				WC()->initialize_cart();
			}

			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}

			if ( WC()->cart && method_exists( WC()->cart, 'get_cart' ) ) {
				WC()->cart->get_cart();
			}
		} catch ( \Throwable $error ) {
			remove_filter( 'woocommerce_is_rest_api_request', $rest_filter, 0 );
			return null;
		}

		return static function () use ( $rest_filter ) {
			remove_filter( 'woocommerce_is_rest_api_request', $rest_filter, 0 );
		};
	}

	/**
	 * Recursively sanitize block values for REST responses.
	 *
	 * @param mixed $value Block value.
	 * @return mixed
	 */
	private function sanitize_block_value( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $item ) {
				$sanitized[ $key ] = $this->sanitize_block_value( $item );
			}

			return $sanitized;
		}

		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}

		return (string) $value;
	}

	/**
	 * Temporarily switch the global post for filters that rely on it.
	 *
	 * @param WP_Post  $post Post object.
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	private function with_post_context( WP_Post $post, callable $callback ) {
		$previous_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$result = $callback();

		wp_reset_postdata();

		if ( $previous_post instanceof WP_Post ) {
			$GLOBALS['post'] = $previous_post;
			setup_postdata( $previous_post );
		} else {
			unset( $GLOBALS['post'] );
		}

		return $result;
	}

	/**
	 * Get a frontend-friendly relative path from the permalink.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function get_relative_path( WP_Post $post ) {
		return $this->path_helper->get_post_path( $post );
	}

	/**
	 * Get structured featured image data.
	 *
	 * @param WP_Post $post Post object.
	 * @return array|null
	 */
	private function get_featured_image( WP_Post $post ) {
		$thumbnail_id = get_post_thumbnail_id( $post );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$full = wp_get_attachment_image_src( $thumbnail_id, 'full' );

		if ( empty( $full[0] ) ) {
			return null;
		}

		return array(
			'id'     => (int) $thumbnail_id,
			'url'    => $full[0],
			'width'  => isset( $full[1] ) ? (int) $full[1] : 0,
			'height' => isset( $full[2] ) ? (int) $full[2] : 0,
			'alt'    => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Get taxonomy terms grouped by taxonomy name.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function get_terms( WP_Post $post ) {
		$output     = array();
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->public ) ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy->name );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			$output[ $taxonomy->name ] = array_map(
				static function ( $term ) {
					return array(
						'id'       => (int) $term->term_id,
						'name'     => $term->name,
						'slug'     => $term->slug,
						'link'     => get_term_link( $term ),
						'taxonomy' => $term->taxonomy,
					);
				},
				$terms
			);
		}

		return $output;
	}

	/**
	 * Determine the best title to expose for a mapped post.
	 *
	 * @param WP_Post $post Canonical post.
	 * @param WP_Post $preview_post Preview source post.
	 * @return string
	 */
	private function get_render_title( WP_Post $post, WP_Post $preview_post ) {
		$title = $preview_post->post_title;

		if ( '' === trim( (string) $title ) ) {
			$title = get_the_title( $post );
		}

		return html_entity_decode( (string) $title, ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * Determine the best excerpt to expose for a mapped post.
	 *
	 * @param WP_Post $post Canonical post.
	 * @param WP_Post $preview_post Preview source post.
	 * @return string
	 */
	private function get_render_excerpt( WP_Post $post, WP_Post $preview_post ) {
		if ( '' !== trim( (string) $preview_post->post_excerpt ) ) {
			return $this->render_excerpt( $post, $preview_post->post_excerpt );
		}

		return $this->render_excerpt( $post );
	}
}
