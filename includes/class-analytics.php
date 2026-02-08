<?php
/**
 * Impressions and clicks for personalized content. Stored in options.
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics {

	const OPTION_IMPRESSIONS = 'personalization_api_impressions';
	const OPTION_CLICKS      = 'personalization_api_clicks';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// Called when API returns recommendations
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

	public function record_click( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		$counts = get_option( self::OPTION_CLICKS, array() );
		$counts[ $post_id ] = isset( $counts[ $post_id ] ) ? $counts[ $post_id ] + 1 : 1;
		update_option( self::OPTION_CLICKS, $counts, false );
	}

	public function get_impressions( $post_id ) {
		$counts = get_option( self::OPTION_IMPRESSIONS, array() );
		return isset( $counts[ (int) $post_id ] ) ? (int) $counts[ (int) $post_id ] : 0;
	}

	public function get_clicks( $post_id ) {
		$counts = get_option( self::OPTION_CLICKS, array() );
		return isset( $counts[ (int) $post_id ] ) ? (int) $counts[ (int) $post_id ] : 0;
	}

	public function get_all_impressions() {
		return get_option( self::OPTION_IMPRESSIONS, array() );
	}

	public function get_all_clicks() {
		return get_option( self::OPTION_CLICKS, array() );
	}

	// Calculate CTR for a post by dividing the clicks by the impressions
	// Returns 0–1 or null if no impressions exists
	public function get_ctr( $post_id ) {
		$imp = $this->get_impressions( $post_id );
		if ( $imp <= 0 ) {
			return null;
		}
		return $this->get_clicks( $post_id ) / $imp;
	}

	private function __construct() {}
}
