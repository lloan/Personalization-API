<?php
/**
 * Simple transient-based cache for recommendation results.
 *
 * @package Personalization_API
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache
 */
class Cache {

	const GROUP   = 'personalization_api';
	const TTL     = 300; // 5 minutes
	const PREFIX  = 'rec_';

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
	 * Generate cache key from request attributes.
	 *
	 * @param array $attributes User attributes (industry, company_size, role).
	 * @param int   $per_page   Optional. Posts per page.
	 * @param int   $page       Optional. Page number.
	 * @return string
	 */
	public function key( $attributes, $per_page = 10, $page = 1 ) {
		ksort( $attributes );
		$parts = array();
		foreach ( $attributes as $k => $v ) {
			$parts[] = $k . '=' . ( is_string( $v ) ? $v : wp_json_encode( $v ) );
		}
		$parts[] = 'per_page=' . (int) $per_page;
		$parts[] = 'page=' . (int) $page;
		return self::PREFIX . md5( implode( '|', $parts ) );
	}

	/**
	 * Get cached recommendations.
	 *
	 * @param string $key Cache key.
	 * @return array|null Cached data or null.
	 */
	public function get( $key ) {
		$data = get_transient( $key );
		return false !== $data ? $data : null;
	}

	/**
	 * Set cached recommendations.
	 *
	 * @param string $key  Cache key.
	 * @param array  $data Data to cache.
	 * @param int    $ttl  TTL in seconds. Default from constant.
	 * @return bool
	 */
	public function set( $key, $data, $ttl = self::TTL ) {
		return set_transient( $key, $data, $ttl );
	}

	/**
	 * Invalidate cache (e.g. when a post's attributes change). Simple approach: no per-key invalidation; TTL handles freshness.
	 * For production you could store keys in an option and delete them here.
	 */
	public function invalidate_all() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_" . self::PREFIX . "%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_" . self::PREFIX . "%'" );
	}

	private function __construct() {}
}
