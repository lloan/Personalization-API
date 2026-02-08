<?php
/**
 * REST routes for recommendations and record-click.
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class REST_API {

	const NAMESPACE = 'personalization-api/v1';
	const OPTION_API_KEY = 'personalization_api_key';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/recommendations',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_recommendations' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_collection_params(),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/record-click',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_click' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function check_permission( $request ) {
		if ( is_user_logged_in() && current_user_can( 'read' ) ) {
			return true;
		}

		$api_key = $this->get_api_key_from_request( $request );
		if ( $api_key ) {
			$stored = get_option( self::OPTION_API_KEY, '' );
			if ( $stored && hash_equals( (string) $stored, (string) $api_key ) ) {
				return true;
			}
		}

		Logger::warning( 'REST API unauthorized access attempt', array( 'route' => $request->get_route() ) );
		return new \WP_Error(
			'rest_forbidden',
			__( 'Invalid or missing API key.', 'personalization-api' ),
			array( 'status' => 401 )
		);
	}

	private function get_api_key_from_request( $request ) {
		$header = $request->get_header( 'X-API-Key' );
		if ( $header ) {
			return $header;
		}
		return $request->get_param( 'api_key' ) ?: '';
	}

	public function get_collection_params() {
		return array(
			'industry'     => array(
				'description'       => __( 'User industry attribute', 'personalization-api' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'company_size' => array(
				'description'       => __( 'User company size attribute', 'personalization-api' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'role'         => array(
				'description'       => __( 'User role attribute', 'personalization-api' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'per_page'     => array(
				'description'       => __( 'Number of posts to return', 'personalization-api' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 50,
				'sanitize_callback' => 'absint',
			),
			'page'         => array(
				'description'       => __( 'Page number', 'personalization-api' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
		);
	}

	public function get_recommendations( $request ) {
		$industry     = $request->get_param( 'industry' );
		$company_size = $request->get_param( 'company_size' );
		$role         = $request->get_param( 'role' );
		$per_page     = (int) $request->get_param( 'per_page' );
		$page         = (int) $request->get_param( 'page' );

		$attributes = array_filter( array(
			'industry'     => $industry ?: '',
			'company_size' => $company_size ?: '',
			'role'         => $role ?: '',
		) );

		$cache = Cache::instance();
		$key   = $cache->key( $attributes, $per_page, $page );
		$cached = $cache->get( $key );
		if ( null !== $cached ) {
			$this->record_impressions( $cached );
			return rest_ensure_response( $cached );
		}

		$result = $this->compute_recommendations( $attributes, $per_page, $page );
		if ( is_wp_error( $result ) ) {
			Logger::error( 'Recommendations computation failed', array( 'error' => $result->get_error_message() ) );
			return $result;
		}

		$cache->set( $key, $result );
		$this->record_impressions( $result );
		return rest_ensure_response( $result );
	}

	private function compute_recommendations( $attributes, $per_page, $page ) {
		global $wpdb;

		$post_meta = Post_Meta::instance();
		$meta_keys = array(
			Post_Meta::META_INDUSTRY,
			Post_Meta::META_COMPANY_SIZE,
			Post_Meta::META_ROLE,
		);

		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key IN ({$placeholders})
				WHERE p.post_status = 'publish' AND p.post_type = 'post'",
				...$meta_keys
			)
		);

		if ( empty( $ids ) ) {
			return array(
				'posts'    => array(),
				'total'    => 0,
				'page'     => $page,
				'per_page' => $per_page,
			);
		}

		$num_attrs = 0;
		foreach ( array( 'industry', 'company_size', 'role' ) as $attr ) {
			if ( isset( $attributes[ $attr ] ) && '' !== trim( (string) $attributes[ $attr ] ) ) {
				$num_attrs++;
			}
		}

		$offset = ( $page - 1 ) * $per_page;
		$scored = array();

		if ( $num_attrs === 0 ) {
			// No filters — just recent posts that have any targeting set
			$query = new \WP_Query( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post__in'       => array_map( 'intval', $ids ),
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'fields'         => 'ids',
			) );
			$total = $query->found_posts;
			foreach ( $query->posts as $id ) {
				$scored[] = array( 'id' => (int) $id, 'score' => 0.0 );
			}
		} else {
			foreach ( $ids as $id ) {
				$score = $this->match_score( (int) $id, $attributes );
				if ( $score >= 0 ) {
					$scored[] = array( 'id' => (int) $id, 'score' => $score );
				}
			}
			usort( $scored, function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			} );
			$total = count( $scored );
			$scored = array_slice( $scored, $offset, $per_page );
		}

		$posts = array();
		foreach ( $scored as $item ) {
			$post = get_post( $item['id'] );
			if ( ! $post ) {
				continue;
			}
			$posts[] = array(
				'id'          => $post->ID,
				'title'       => get_the_title( $post ),
				'excerpt'     => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( $post->post_content, 25 ),
				'url'         => get_permalink( $post ),
				'match_score' => round( $item['score'], 2 ),
			);
		}

		return array(
			'posts'    => $posts,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	// 0.0–1.0, or we could return -1 to exclude (not used that way currently)
	private function match_score( $post_id, $attributes ) {
		$post_meta = Post_Meta::instance();
		$matched = 0;
		$compared = 0;
		$post_has_any = false;

		foreach ( array( 'industry', 'company_size', 'role' ) as $attr ) {
			$user_val = isset( $attributes[ $attr ] ) ? trim( (string) $attributes[ $attr ] ) : '';
			$meta_key = $post_meta->meta_key_for( $attr );
			$meta_val = get_post_meta( $post_id, $meta_key, true );
			$meta_val = is_string( $meta_val ) ? trim( $meta_val ) : '';

			if ( '' !== $meta_val ) {
				$post_has_any = true;
			}
			if ( '' === $user_val ) {
				continue;
			}
			$compared++;
			$meta_vals = array_map( 'trim', explode( ',', $meta_val ) );
			$user_vals = array_map( 'trim', explode( ',', $user_val ) );
			$overlap = array_intersect( array_map( 'strtolower', $meta_vals ), array_map( 'strtolower', $user_vals ) );
			if ( count( $overlap ) > 0 ) {
				$matched++;
			}
		}

		if ( $compared === 0 ) {
			return $post_has_any ? 0.0 : 0.0;
		}
		return $matched / $compared;
	}

	public function record_click( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( get_post_status( $post_id ) !== 'publish' ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Invalid post.' ), 400 );
		}
		Analytics::instance()->record_click( $post_id );
		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	private function record_impressions( $response ) {
		if ( empty( $response['posts'] ) || ! is_array( $response['posts'] ) ) {
			return;
		}
		Analytics::instance()->record_impressions( wp_list_pluck( $response['posts'], 'id' ) );
	}

	public static function generate_api_key() {
		return bin2hex( random_bytes( 32 ) );
	}
}
