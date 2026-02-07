<?php
/**
 * Post meta for personalization attributes.
 *
 * @package Personalization_API
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post_Meta
 */
class Post_Meta {

	const META_INDUSTRY     = '_personalization_industry';
	const META_COMPANY_SIZE = '_personalization_company_size';
	const META_ROLE         = '_personalization_role';

	const ATTR_INDUSTRY     = 'industry';
	const ATTR_COMPANY_SIZE = 'company_size';
	const ATTR_ROLE         = 'role';

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register post meta with REST and sanitization.
	 */
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

	/**
	 * Sanitize a single attribute value (comma-separated or single).
	 *
	 * @param mixed $value Raw value.
	 * @return string Sanitized string.
	 */
	public function sanitize_attribute( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Get allowed attribute values for validation (optional predefined list).
	 *
	 * @return array Keys are attribute names, values are arrays of allowed options (empty = any string).
	 */
	public static function get_allowed_values() {
		return array(
			self::ATTR_INDUSTRY     => array(), // Any string; could restrict e.g. ['technology','finance','healthcare']
			self::ATTR_COMPANY_SIZE => array(),
			self::ATTR_ROLE         => array(),
		);
	}

	/**
	 * Get meta key for an attribute name.
	 *
	 * @param string $attr Attribute name (industry, company_size, role).
	 * @return string Meta key or empty.
	 */
	public static function meta_key_for( $attr ) {
		$map = array(
			self::ATTR_INDUSTRY     => self::META_INDUSTRY,
			self::ATTR_COMPANY_SIZE => self::META_COMPANY_SIZE,
			self::ATTR_ROLE         => self::META_ROLE,
		);
		return isset( $map[ $attr ] ) ? $map[ $attr ] : '';
	}

	/**
	 * Get attribute names.
	 *
	 * @return array
	 */
	public static function get_attribute_names() {
		return array( self::ATTR_INDUSTRY, self::ATTR_COMPANY_SIZE, self::ATTR_ROLE );
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}
}
