<?php
/**
 * Simple error and debug logging for the plugin.
 *
 * @package Personalization_API
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 */
class Logger {

	const OPTION_KEY = 'personalization_api_log';
	const MAX_ENTRIES = 200;

	/**
	 * Log a message with level and optional context.
	 *
	 * @param string $level   'error', 'warning', 'info', 'debug'.
	 * @param string $message Message.
	 * @param array  $context Optional context (e.g. ['code' => 400]).
	 */
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

	/**
	 * Log error.
	 *
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'error', $message, $context );
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Personalization API] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log warning.
	 *
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function warning( $message, $context = array() ) {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Get recent log entries for admin.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public static function get_recent( $limit = 50 ) {
		$logs = get_option( self::OPTION_KEY, array() );
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear stored logs.
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
	}
}
