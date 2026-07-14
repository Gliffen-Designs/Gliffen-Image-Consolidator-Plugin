<?php
/**
 * Audit Logger Class
 * Tracks all consolidation actions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Audit_Logger {

	const MAX_LOGS = 1000; // Prevent unbounded growth

	/**
	 * Initialize audit logger
	 */
	public static function init() {
		add_action( 'gic_deactivate', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Log an action
	 *
	 * @param string $action Action type (skipped_generation, size_disabled, size_enabled, etc.)
	 * @param array  $data Additional data to log
	 *
	 * @return bool Success status
	 */
	public static function log( $action, $data = array() ) {
		// Check if logging is enabled
		if ( ! GIC_Settings::get_option( 'enable_logging', true ) ) {
			return false;
		}

		$logs = self::get_logs();

		// Create log entry
		$entry = array(
			'timestamp'  => current_time( 'mysql' ),
			'action'     => sanitize_text_field( $action ),
			'user_id'    => get_current_user_id(),
			'data'       => $data,
		);

		// Add to beginning of array (most recent first)
		array_unshift( $logs, $entry );

		// Trim to max logs
		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, 0, self::MAX_LOGS );
		}

		// Save logs
		return GIC_Settings::update_option( 'audit_log', $logs );
	}

	/**
	 * Get all audit logs
	 *
	 * @param int $limit Number of logs to return (0 = all)
	 *
	 * @return array Audit log entries
	 */
	public static function get_logs( $limit = 0 ) {
		$logs = (array) GIC_Settings::get_option( 'audit_log', array() );

		if ( $limit > 0 ) {
			$logs = array_slice( $logs, 0, $limit );
		}

		return $logs;
	}

	/**
	 * Get logs for a specific action
	 *
	 * @param string $action Action type to filter by
	 * @param int    $limit Number of logs to return
	 *
	 * @return array Filtered log entries
	 */
	public static function get_logs_by_action( $action, $limit = 100 ) {
		$logs = self::get_logs();
		$filtered = array();

		foreach ( $logs as $entry ) {
			if ( isset( $entry['action'] ) && $entry['action'] === $action ) {
				$filtered[] = $entry;
				if ( count( $filtered ) >= $limit ) {
					break;
				}
			}
		}

		return $filtered;
	}

	/**
	 * Clear all audit logs
	 *
	 * @return bool Success status
	 */
	public static function clear_logs() {
		return GIC_Settings::update_option( 'audit_log', array() );
	}

	/**
	 * Cleanup on deactivation
	 */
	public static function cleanup() {
		// Optionally clear logs on deactivation
		// For now, we'll keep them for debugging purposes
	}

	/**
	 * Get audit log statistics
	 *
	 * @return array Statistics about logged actions
	 */
	public static function get_stats() {
		$logs = self::get_logs();
		$stats = array(
			'total_entries' => count( $logs ),
			'by_action' => array(),
			'by_user' => array(),
			'date_range' => array(),
		);

		if ( empty( $logs ) ) {
			return $stats;
		}

		foreach ( $logs as $entry ) {
			// Count by action
			if ( isset( $entry['action'] ) ) {
				$action = $entry['action'];
				$stats['by_action'][ $action ] = ( isset( $stats['by_action'][ $action ] ) ? $stats['by_action'][ $action ] : 0 ) + 1;
			}

			// Count by user
			if ( isset( $entry['user_id'] ) ) {
				$user_id = $entry['user_id'];
				$stats['by_user'][ $user_id ] = ( isset( $stats['by_user'][ $user_id ] ) ? $stats['by_user'][ $user_id ] : 0 ) + 1;
			}

			// Track date range
			if ( isset( $entry['timestamp'] ) ) {
				if ( empty( $stats['date_range']['oldest'] ) ) {
					$stats['date_range']['oldest'] = $entry['timestamp'];
				}
				$stats['date_range']['newest'] = $entry['timestamp'];
			}
		}

		return $stats;
	}
}
