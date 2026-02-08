<?php
/**
 * Post meta keys and registration for industry / company_size / role.
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Meta {

	const META_INDUSTRY     = '_personalization_industry';
	const META_COMPANY_SIZE = '_personalization_company_size';
	const META_ROLE         = '_personalization_role';

	const ATTR_INDUSTRY     = 'industry';
	const ATTR_COMPANY_SIZE = 'company_size';
	const ATTR_ROLE         = 'role';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_meta() {
		$post_meta_keys = array(
			self::META_INDUSTRY     => array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'  => __( 'Target industry for personalization', 'personalization-api' ),
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			),
			self::META_COMPANY_SIZE => array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'  => __( 'Target company size for personalization', 'personalization-api' ),
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			),
			self::META_ROLE => array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'  => __( 'Target role for personalization', 'personalization-api' ),
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			),
		);

		foreach ( $post_meta_keys as $meta_key => $args ) {
			register_post_meta(
				'post',
				$meta_key,
				array_merge(
					array(
						'sanitize_callback' => array( $this, 'sanitize_attribute' ),
					),
					$args
				)
			);
		}
	}

	public function sanitize_attribute( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}
		return sanitize_text_field( (string) $value );
	}

	// Empty = any string; could add allowed lists later
	public static function get_allowed_values() {
		return array(
			self::ATTR_INDUSTRY     => array(),
			self::ATTR_COMPANY_SIZE => array(),
			self::ATTR_ROLE         => array(),
		);
	}

	public static function meta_key_for( $attr ) {
		$map = array(
			self::ATTR_INDUSTRY     => self::META_INDUSTRY,
			self::ATTR_COMPANY_SIZE => self::META_COMPANY_SIZE,
			self::ATTR_ROLE         => self::META_ROLE,
		);
		return isset( $map[ $attr ] ) ? $map[ $attr ] : '';
	}

	public static function get_attribute_names() {
		return array( self::ATTR_INDUSTRY, self::ATTR_COMPANY_SIZE, self::ATTR_ROLE );
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}
}
