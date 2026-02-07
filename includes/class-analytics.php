<?php
/**
 * Basic analytics for personalization (impressions, optional clicks).
 *
 * @package Personalization_API
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Analytics
 */
class Analytics {

	const OPTION_IMPRESSIONS = 'personalization_api_impressions';
	const OPTION_CLICKS      = 'personalization_api_clicks';

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
	 * Record impression for post IDs (when they are returned by the API).
	 *
	 * @param int[] $post_ids Post IDs.
	 */
	public function record_impressions( $post_ids ) {
		$counts = get_option( self::OPTION_IMPRESSIONS, array() );
		foreach ( $post_ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$counts[ $id ] = isset( $counts[ $id ] ) ? $counts[ $id ] + 1 : 1;
			}
		}
		update_option( self::OPTION_IMPRESSIONS, $counts, false );
	}

	/**
	 * Record a click (call from front-end or a tracked link). Optional.
	 *
	 * @param int $post_id Post ID.
	 */
	public function record_click( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		$counts = get_option( self::OPTION_CLICKS, array() );
		$counts[ $post_id ] = isset( $counts[ $post_id ] ) ? $counts[ $post_id ] + 1 : 1;
		update_option( self::OPTION_CLICKS, $counts, false );
	}

	/**
	 * Get impression count for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function get_impressions( $post_id ) {
		$counts = get_option( self::OPTION_IMPRESSIONS, array() );
		return isset( $counts[ (int) $post_id ] ) ? (int) $counts[ (int) $post_id ] : 0;
	}

	/**
	 * Get click count for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function get_clicks( $post_id ) {
		$counts = get_option( self::OPTION_CLICKS, array() );
		return isset( $counts[ (int) $post_id ] ) ? (int) $counts[ (int) $post_id ] : 0;
	}

	/**
	 * Get all impressions (post_id => count).
	 *
	 * @return array
	 */
	public function get_all_impressions() {
		return get_option( self::OPTION_IMPRESSIONS, array() );
	}

	/**
	 * Get all clicks (post_id => count).
	 *
	 * @return array
	 */
	public function get_all_clicks() {
		return get_option( self::OPTION_CLICKS, array() );
	}

	/**
	 * Get effectiveness (CTR) for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return float|null CTR 0-1 or null if no impressions.
	 */
	public function get_ctr( $post_id ) {
		$imp = $this->get_impressions( $post_id );
		if ( $imp <= 0 ) {
			return null;
		}
		return $this->get_clicks( $post_id ) / $imp;
	}

	private function __construct() {}
}
