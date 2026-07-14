<?php
/**
 * Image Serve Handler Class
 * Serves replacement images for disabled sizes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Image_Serve_Handler {

	/**
	 * Handle missing image request - public wrapper that logs attempts
	 *
	 * @param string $requested_uri The originally requested URI
	 *
	 * @return bool|array False if no match found, array on success
	 */
	public static function handle_missing_image( $requested_uri = '' ) {
		if ( empty( $requested_uri ) ) {
			$requested_uri = isset( $_GET['requested'] ) ? sanitize_text_field( wp_unslash( $_GET['requested'] ) ) : '';
		}

		if ( empty( $requested_uri ) ) {
			return false;
		}

		// Parse the requested filename
		$parsed = self::parse_image_filename( $requested_uri );

		if ( ! $parsed ) {
			return false;
		}

		// Check if this size is disabled
		$disabled_sizes = GIC_Settings::get_disabled_sizes();
		$size_mappings = GIC_Settings::get_size_mappings();

		// Get the attachment ID from the filename
		$attachment_id = self::get_attachment_id_from_filename( $parsed['filename'], $parsed['date_path'] );
		
		if ( ! $attachment_id ) {
			// Fall back to original if we can't find the attachment
			$original_path = self::build_original_path( $parsed['date_path'], $parsed['filename'], $parsed['extension'] );
			
			if ( $original_path && file_exists( $original_path ) ) {
				return self::redirect_to_original( $parsed['date_path'], $parsed['filename'], $parsed['extension'] );
			}
			
			return false;
		}

		// Get attachment metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );
		
		if ( ! $metadata || ! isset( $metadata['sizes'] ) ) {
			// Fall back to original
			$original_path = self::build_original_path( $parsed['date_path'], $parsed['filename'], $parsed['extension'] );
			if ( $original_path && file_exists( $original_path ) ) {
				return self::redirect_to_original( $parsed['date_path'], $parsed['filename'], $parsed['extension'] );
			}
			
			return false;
		}

		// Try to find a replacement size that exists for this image
		$replacement_size = self::find_replacement_in_metadata( $parsed, $metadata, $size_mappings );

		if ( ! $replacement_size ) {
			// Fall back to original
			$original_path = self::build_original_path( $parsed['date_path'], $parsed['filename'], $parsed['extension'] );
			if ( $original_path && file_exists( $original_path ) ) {
				return self::redirect_to_original( $parsed['date_path'], $parsed['filename'], $parsed['extension'] );
			}
			
			return false;
		}

		// Build the redirect URL to the replacement
		return self::redirect_to_replacement_size( $parsed, $replacement_size );
	}

	/**
	 * Find attachment ID from base filename
	 *
	 * @param string $filename Base filename (without size suffix)
	 * @param string $date_path Date path (YYYY/MM)
	 *
	 * @return int|false Attachment ID or false
	 */
	public static function get_attachment_id_from_filename( $filename, $date_path ) {
		global $wpdb;

		// Search for attachment with this filename
		$query = $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_name = %s LIMIT 1",
			$filename
		);

		$result = $wpdb->get_var( $query );
		return $result ? (int) $result : false;
	}

	/**
	 * Find a replacement size that exists in attachment metadata
	 *
	 * @param array $parsed Parsed image info
	 * @param array $metadata Attachment metadata
	 * @param array $size_mappings Size mappings
	 *
	 * @return array|false Array with size_name and file, or false
	 */
	public static function find_replacement_in_metadata( $parsed, $metadata, $size_mappings ) {
		// Check if the requested dimensions match a disabled size
		$requested_size = $parsed['width'] . 'x' . $parsed['height'];

		// Look through disabled sizes for a match
		foreach ( $size_mappings as $disabled_name => $replacement_name ) {
			// Check if this disabled size matches the request
			if ( isset( $metadata['sizes'][ $disabled_name ] ) ) {
				$size_info = $metadata['sizes'][ $disabled_name ];
				
				// Check if dimensions match (either exact or just one dimension matches)
				if ( ( $size_info['width'] == $parsed['width'] && $size_info['height'] == $parsed['height'] ) ||
					 ( $size_info['width'] == $parsed['width'] ) ||
					 ( $size_info['height'] == $parsed['height'] ) ) {
					
					// This is the disabled size being requested, now find the replacement
					if ( isset( $metadata['sizes'][ $replacement_name ] ) ) {
						return array(
							'size_name' => $replacement_name,
							'file' => $metadata['sizes'][ $replacement_name ]['file'],
						);
					}
				}
			}
		}

		return false;
	}

	/**
	 * Redirect to original full-size image
	 *
	 * @param string $date_path Date path (YYYY/MM)
	 * @param string $filename Base filename
	 * @param string $extension File extension
	 *
	 * @return bool True on success
	 */
	public static function redirect_to_original( $date_path, $filename, $extension ) {
		$uploads_url = wp_get_upload_dir()['baseurl'];
		$original_url = $uploads_url . '/' . $date_path . '/' . $filename . '.' . $extension;

		header( 'HTTP/1.1 301 Moved Permanently' );
		header( 'Location: ' . $original_url );
		header( 'Cache-Control: public, max-age=31536000' );

		return true;
	}

	/**
	 * Redirect to replacement size found in metadata
	 *
	 * @param array $parsed Parsed image info
	 * @param array $replacement_size Replacement size info from metadata
	 *
	 * @return bool True on success
	 */
	public static function redirect_to_replacement_size( $parsed, $replacement_size ) {
		$uploads_url = wp_get_upload_dir()['baseurl'];
		$replacement_url = $uploads_url . '/' . $parsed['date_path'] . '/' . $replacement_size['file'];

		header( 'HTTP/1.1 301 Moved Permanently' );
		header( 'Location: ' . $replacement_url );
		header( 'Cache-Control: public, max-age=31536000' );

		return true;
	}

	/**
	 * Parse image filename from URI
	 *
	 * Expected format: /wp-content/uploads/YYYY/MM/filename-WIDTHxHEIGHT.ext
	 *
	 * @param string $uri Request URI
	 *
	 * @return array|false Parsed data or false if not matched
	 */
	public static function parse_image_filename( $uri ) {
		// Remove query string
		$uri = strtok( $uri, '?' );

		// Match WordPress uploads directory pattern
		$pattern = '/uploads\/([0-9]{4}\/[0-9]{2})\/(.+)-(\d+)x(\d+)\.(jpg|jpeg|png|gif|webp)$/i';

		if ( ! preg_match( $pattern, $uri, $matches ) ) {
			return false;
		}

		return array(
			'date_path'      => $matches[1],           // YYYY/MM
			'filename'       => $matches[2],           // base filename without size suffix
			'width'          => (int) $matches[3],     // width
			'height'         => (int) $matches[4],     // height
			'extension'      => strtolower( $matches[5] ),  // file extension
			'size_string'    => $matches[3] . 'x' . $matches[4], // WIDTHxHEIGHT
		);
	}

	/**
	 * Get all registered image sizes with dimensions
	 *
	 * @return array Sizes with width and height
	 */
	private static function get_all_registered_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = array(
			'thumbnail'      => array(
				'width'  => get_option( 'thumbnail_size_w' ),
				'height' => get_option( 'thumbnail_size_h' ),
			),
			'medium'         => array(
				'width'  => get_option( 'medium_size_w' ),
				'height' => get_option( 'medium_size_h' ),
			),
			'medium_large'   => array(
				'width'  => get_option( 'medium_large_size_w' ),
				'height' => get_option( 'medium_large_size_h' ),
			),
			'large'          => array(
				'width'  => get_option( 'large_size_w' ),
				'height' => get_option( 'large_size_h' ),
			),
		);

		if ( is_array( $_wp_additional_image_sizes ) ) {
			foreach ( $_wp_additional_image_sizes as $name => $config ) {
				$sizes[ $name ] = array(
					'width'  => $config['width'],
					'height' => $config['height'],
				);
			}
		}

		return $sizes;
	}

	/**
	 * Get dimensions for a named size (public static wrapper)
	 *
	 * @param string $size_name Size name
	 *
	 * @return array|false Dimensions array (width, height) or false
	 */
	public static function get_size_dimensions_static( $size_name ) {
		global $_wp_additional_image_sizes;

		$native_map = array(
			'thumbnail'     => array(
				'width'  => get_option( 'thumbnail_size_w' ),
				'height' => get_option( 'thumbnail_size_h' ),
			),
			'medium'        => array(
				'width'  => get_option( 'medium_size_w' ),
				'height' => get_option( 'medium_size_h' ),
			),
			'medium_large'  => array(
				'width'  => get_option( 'medium_large_size_w' ),
				'height' => get_option( 'medium_large_size_h' ),
			),
			'large'         => array(
				'width'  => get_option( 'large_size_w' ),
				'height' => get_option( 'large_size_h' ),
			),
		);

		if ( isset( $native_map[ $size_name ] ) ) {
			return $native_map[ $size_name ];
		}

		if ( is_array( $_wp_additional_image_sizes ) && isset( $_wp_additional_image_sizes[ $size_name ] ) ) {
			return array(
				'width'  => $_wp_additional_image_sizes[ $size_name ]['width'],
				'height' => $_wp_additional_image_sizes[ $size_name ]['height'],
			);
		}

		return false;
	}

	/**
	 * Get dimensions for a named size
	 *
	 * @param string $size_name Size name
	 *
	 * @return array|false Dimensions array (width, height) or false
	 */
	private static function get_size_dimensions( $size_name ) {
		return self::get_size_dimensions_static( $size_name );
	}

	/**
	 * Build path to original (full-size) image file
	 *
	 * @param string $date_path Date path (YYYY/MM)
	 * @param string $filename Base filename (without size suffix)
	 * @param string $extension File extension
	 *
	 * @return string|false Full file path or false
	 */
	public static function build_original_path( $date_path, $filename, $extension ) {
		$uploads_dir = wp_upload_dir();
		$basedir = $uploads_dir['basedir'];

		$original_file = $filename . '.' . $extension;
		$full_path = $basedir . '/' . $date_path . '/' . $original_file;

		return $full_path;
	}

	/**
	 * Build path to replacement image file
	 *
	 * @param string $date_path Date path (YYYY/MM)
	 * @param string $filename Base filename
	 * @param array  $size_dims Size dimensions
	 * @param string $extension File extension
	 *
	 * @return string|false Full file path or false
	 */
	public static function build_replacement_path( $date_path, $filename, $size_dims, $extension = 'jpg' ) {
		$uploads_dir = wp_upload_dir();
		$basedir = $uploads_dir['basedir'];

		if ( ! $size_dims || ! isset( $size_dims['width'] ) || ! isset( $size_dims['height'] ) ) {
			return false;
		}

		$size_string = $size_dims['width'] . 'x' . $size_dims['height'];
		$replacement_file = $filename . '-' . $size_string . '.' . $extension;

		// basedir already contains full path, no need for /uploads/
		$full_path = $basedir . '/' . $date_path . '/' . $replacement_file;

		return $full_path;
	}

	/**
	 * Serve a file with appropriate headers
	 *
	 * @param string $file_path Path to file to serve
	 *
	 * @return bool True on success
	 */
	public static function serve_file( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		$file_size = filesize( $file_path );
		$file_ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		// Determine MIME type
		$mime_types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);

		$mime_type = isset( $mime_types[ $file_ext ] ) ? $mime_types[ $file_ext ] : 'image/jpeg';

		// Set headers for file serving
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . $file_size );
		header( 'Cache-Control: public, max-age=31536000' ); // 1 year cache
		header( 'Pragma: public' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + 31536000 ) );

		// Serve the file
		readfile( $file_path );

		return true;
	}
}
