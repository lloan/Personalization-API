<?php
/**
 * Transient cache for recommendation responses. 5 min TTL.
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cache {

	const GROUP   = 'personalization_api';
	const TTL     = 300; // 5 min
	const PREFIX  = 'rec_';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

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

	public function get( $key ) {
		$data = get_transient( $key );
		return false !== $data ? $data : null;
	}

	public function set( $key, $data, $ttl = self::TTL ) {
		return set_transient( $key, $data, $ttl );
	}

	// Full flush; could do per-key invalidation later
	public function invalidate_all() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_" . self::PREFIX . "%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_" . self::PREFIX . "%'" );
	}

	private function __construct() {}
}
