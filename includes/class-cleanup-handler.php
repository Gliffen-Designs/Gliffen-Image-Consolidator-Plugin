<?php
/**
 * Cleanup Handler Class
 * Manages cleanup and storage optimization of disabled image sizes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Cleanup_Handler {

	const PENDING_CLEANUP_FOLDER = 'gic-pending-cleanup';

	/**
	 * Initialize the cleanup handler
	 */
	public static function init() {
		add_action( 'wp_ajax_gic_move_to_cleanup', array( __CLASS__, 'ajax_move_to_cleanup' ) );
		add_action( 'wp_ajax_gic_delete_cleanup', array( __CLASS__, 'ajax_delete_cleanup' ) );
		add_action( 'wp_ajax_gic_cleanup_metadata', array( __CLASS__, 'ajax_cleanup_metadata' ) );
	}

	/**
	 * Calculate storage savings by analyzing disabled sizes
	 *
	 * @return array Storage data with sizes and potential savings
	 */
	public static function calculate_storage_savings() {
		global $wpdb;

		$size_mappings = GIC_Settings::get_size_mappings();
		if ( empty( $size_mappings ) ) {
			return array(
				'disabled_sizes'    => 0,
				'total_files'       => 0,
				'potential_savings' => 0,
				'details'           => array(),
			);
		}

		$uploads_dir = wp_upload_dir();
		$basedir = $uploads_dir['basedir'];

		$total_files = 0;
		$total_bytes = 0;
		$details = array();

		// For each disabled size, find all matching files
		foreach ( $size_mappings as $disabled_size => $replacement_size ) {
			$files_count = 0;
			$size_bytes = 0;

			// Search for all attachments
			$attachments = $wpdb->get_results(
				"SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment'"
			);

			foreach ( $attachments as $attachment ) {
				$metadata = wp_get_attachment_metadata( $attachment->ID );
				if ( ! $metadata || ! isset( $metadata['sizes'][ $disabled_size ] ) ) {
					continue;
				}

				$file_info = $metadata['sizes'][ $disabled_size ];
				$file_path = $basedir . '/' . dirname( $metadata['file'] ) . '/' . $file_info['file'];

				if ( file_exists( $file_path ) ) {
					$files_count++;
					$size_bytes += filesize( $file_path );
				}
			}

			// Always include all disabled sizes in details, even if count is 0
			$total_files += $files_count;
			$total_bytes += $size_bytes;
			$details[ $disabled_size ] = array(
				'file_count' => $files_count,
				'bytes'      => $size_bytes,
				'human'      => self::format_bytes( $size_bytes ),
			);
		}

		return array(
			'disabled_sizes'    => count( $size_mappings ),
			'total_files'       => $total_files,
			'potential_savings' => $total_bytes,
			'potential_savings_human' => self::format_bytes( $total_bytes ),
			'details'           => $details,
		);
	}

	/**
	 * Get pending cleanup statistics
	 *
	 * @return array Cleanup folder statistics
	 */
	public static function get_pending_cleanup_stats() {
		$uploads_dir = wp_upload_dir();
		$cleanup_path = $uploads_dir['basedir'] . '/' . self::PENDING_CLEANUP_FOLDER;

		if ( ! is_dir( $cleanup_path ) ) {
			return array(
				'exists'        => false,
				'file_count'    => 0,
				'total_bytes'   => 0,
				'total_human'   => '0 B',
			);
		}

		$files = glob( $cleanup_path . '/*' );
		if ( ! $files ) {
			$files = array();
		}

		$total_bytes = 0;
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$total_bytes += filesize( $file );
			}
		}

		return array(
			'exists'        => true,
			'file_count'    => count( $files ),
			'total_bytes'   => $total_bytes,
			'total_human'   => self::format_bytes( $total_bytes ),
		);
	}

	/**
	 * Move disabled size images to pending cleanup folder
	 *
	 * @return array Status with count and errors
	 */
	public static function move_disabled_sizes_to_cleanup() {
		global $wpdb;

		$size_mappings = GIC_Settings::get_size_mappings();
		if ( empty( $size_mappings ) ) {
			return array(
				'success'      => false,
				'message'      => 'No disabled sizes configured',
				'files_moved'  => 0,
				'errors'       => array(),
			);
		}

		$uploads_dir = wp_upload_dir();
		$basedir = $uploads_dir['basedir'];
		$cleanup_path = $basedir . '/' . self::PENDING_CLEANUP_FOLDER;

		// Create pending cleanup folder if it doesn't exist
		if ( ! is_dir( $cleanup_path ) ) {
			wp_mkdir_p( $cleanup_path );
		}

		$files_moved = 0;
		$errors = array();

		// For each disabled size, find and move all matching files
		foreach ( $size_mappings as $disabled_size => $replacement_size ) {
			// Search for all attachments
			$attachments = $wpdb->get_results(
				"SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment'"
			);

			foreach ( $attachments as $attachment ) {
				$metadata = wp_get_attachment_metadata( $attachment->ID );
				if ( ! $metadata || ! isset( $metadata['sizes'][ $disabled_size ] ) ) {
					continue;
				}

				$file_info = $metadata['sizes'][ $disabled_size ];
				$source_path = $basedir . '/' . dirname( $metadata['file'] ) . '/' . $file_info['file'];
				$dest_file = $disabled_size . '_' . $file_info['file'];
				$dest_path = $cleanup_path . '/' . $dest_file;

				// Skip if already moved or doesn't exist
				if ( ! file_exists( $source_path ) ) {
					continue;
				}

				// Skip if already in cleanup folder
				if ( file_exists( $dest_path ) ) {
					continue;
				}

				// Move the file
				if ( rename( $source_path, $dest_path ) ) {
					$files_moved++;
					GIC_Audit_Logger::log(
						'file_moved_to_cleanup',
						array(
							'attachment_id' => $attachment->ID,
							'size'          => $disabled_size,
							'filename'      => $file_info['file'],
						)
					);
				} else {
					$errors[] = 'Failed to move: ' . $source_path;
				}
			}
		}

		return array(
			'success'      => true,
			'message'      => sprintf( 'Moved %d files to pending cleanup', $files_moved ),
			'files_moved'  => $files_moved,
			'errors'       => $errors,
		);
	}

	/**
	 * Permanently delete all files in pending cleanup folder
	 *
	 * @return array Status with count and errors
	 */
	public static function permanently_delete_cleanup_files() {
		$uploads_dir = wp_upload_dir();
		$cleanup_path = $uploads_dir['basedir'] . '/' . self::PENDING_CLEANUP_FOLDER;

		if ( ! is_dir( $cleanup_path ) ) {
			return array(
				'success'      => false,
				'message'      => 'Pending cleanup folder does not exist',
				'files_deleted' => 0,
				'errors'       => array(),
			);
		}

		$files = glob( $cleanup_path . '/*' );
		if ( ! $files ) {
			$files = array();
		}

		$files_deleted = 0;
		$errors = array();

		foreach ( $files as $file ) {
			if ( is_file( $file ) && unlink( $file ) ) {
				$files_deleted++;
				GIC_Audit_Logger::log(
					'cleanup_file_deleted',
					array(
						'filename' => basename( $file ),
					)
				);
			} elseif ( is_file( $file ) ) {
				$errors[] = 'Failed to delete: ' . basename( $file );
			}
		}

		// Remove the folder if empty
		if ( empty( $errors ) && count( $files ) === $files_deleted ) {
			rmdir( $cleanup_path );
		}

		return array(
			'success'       => true,
			'message'       => sprintf( 'Permanently deleted %d files', $files_deleted ),
			'files_deleted' => $files_deleted,
			'errors'        => $errors,
		);
	}

	/**
	 * AJAX handler for moving to cleanup
	 */
	public static function ajax_move_to_cleanup() {
		// Verify nonce
		check_ajax_referer( 'gic_admin_nonce', 'nonce' );

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$result = self::move_disabled_sizes_to_cleanup();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for deleting cleanup files
	 */
	public static function ajax_delete_cleanup() {
		// Verify nonce
		check_ajax_referer( 'gic_admin_nonce', 'nonce' );

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$result = self::permanently_delete_cleanup_files();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Clean up attachment metadata by removing disabled size entries
	 * This ensures metadata matches disk state for compatibility with thumbnail regenerators
	 *
	 * @return array Status with count and errors
	 */
	public static function cleanup_attachment_metadata() {
		global $wpdb;

		$disabled_sizes = GIC_Settings::get_disabled_sizes();
		if ( empty( $disabled_sizes ) ) {
			return array(
				'success'          => false,
				'message'          => 'No disabled sizes configured',
				'metadata_cleaned' => 0,
				'errors'           => array(),
			);
		}

		$metadata_cleaned = 0;
		$errors = array();

		// Get all attachments
		$attachments = $wpdb->get_results(
			"SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment'"
		);

		foreach ( $attachments as $attachment ) {
			$metadata = wp_get_attachment_metadata( $attachment->ID );

			if ( ! $metadata || ! isset( $metadata['sizes'] ) ) {
				continue;
			}

			$cleaned = false;

			// Remove disabled sizes from metadata
			foreach ( $disabled_sizes as $disabled_size ) {
				if ( isset( $metadata['sizes'][ $disabled_size ] ) ) {
					unset( $metadata['sizes'][ $disabled_size ] );
					$cleaned = true;
				}
			}

			// Update metadata if changes were made
			if ( $cleaned ) {
				if ( wp_update_attachment_metadata( $attachment->ID, $metadata ) ) {
					$metadata_cleaned++;
					GIC_Audit_Logger::log(
						'metadata_cleaned',
						array(
							'attachment_id' => $attachment->ID,
						)
					);
				} else {
					$errors[] = 'Failed to update metadata for attachment ID: ' . $attachment->ID;
				}
			}
		}

		return array(
			'success'          => true,
			'message'          => sprintf( 'Cleaned metadata for %d attachments', $metadata_cleaned ),
			'metadata_cleaned' => $metadata_cleaned,
			'errors'           => $errors,
		);
	}

	/**
	 * AJAX handler for metadata cleanup
	 */
	public static function ajax_cleanup_metadata() {
		// Verify nonce
		check_ajax_referer( 'gic_admin_nonce', 'nonce' );

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$result = self::cleanup_attachment_metadata();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Format bytes to human readable format
	 *
	 * @param int $bytes Number of bytes
	 *
	 * @return string Formatted bytes
	 */
	private static function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= ( 1 << ( 10 * $pow ) );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
