<?php
/**
 * In-option log for admin. Only writes when WP_DEBUG is on.
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	const OPTION_KEY = 'personalization_api_log';
	const MAX_ENTRIES = 200;

	public static function log( $level, $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		$entry = array(
			'time'    => current_time( 'mysql' ),
			'level'   => sanitize_key( $level ),
			'message' => sanitize_text_field( $message ),
			'context' => $context,
		);
		$logs = get_option( self::OPTION_KEY, array() );
		array_unshift( $logs, $entry );
		$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		update_option( self::OPTION_KEY, $logs, false );
	}

	public static function error( $message, $context = array() ) {
		self::log( 'error', $message, $context );
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Personalization API] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	public static function warning( $message, $context = array() ) {
		self::log( 'warning', $message, $context );
	}

	public static function get_recent( $limit = 50 ) {
		$logs = get_option( self::OPTION_KEY, array() );
		return array_slice( $logs, 0, $limit );
	}

	public static function clear() {
		delete_option( self::OPTION_KEY );
	}
}
