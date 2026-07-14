<?php
/**
 * Image Processor Class
 * Handles prevention of disabled image size generation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Image_Processor {

	/**
	 * Initialize image processor
	 */
	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'filter_attachment_metadata' ), 10, 2 );
		add_filter( 'intermediate_image_sizes_advanced', array( __CLASS__, 'filter_intermediate_sizes' ), 10, 2 );
	}

	/**
	 * Filter intermediate image sizes to exclude disabled ones
	 *
	 * @param array $sizes List of image sizes with parameters
	 * @param array $metadata Attachment metadata
	 *
	 * @return array Filtered sizes (disabled ones removed)
	 */
	public static function filter_intermediate_sizes( $sizes, $metadata ) {
		$disabled_sizes = GIC_Settings::get_disabled_sizes();

		if ( empty( $disabled_sizes ) ) {
			return $sizes;
		}

		$filtered_sizes = array();

		foreach ( $sizes as $size_name => $size_config ) {
			// Skip this size if it's in the disabled list
			if ( in_array( $size_name, $disabled_sizes, true ) ) {
				GIC_Audit_Logger::log( 'skipped_generation', array(
					'size' => $size_name,
					'width' => isset( $size_config['width'] ) ? $size_config['width'] : 0,
					'height' => isset( $size_config['height'] ) ? $size_config['height'] : 0,
				) );
				continue;
			}

			$filtered_sizes[ $size_name ] = $size_config;
		}

		return $filtered_sizes;
	}

	/**
	 * Filter attachment metadata generation
	 * Removes disabled sizes from metadata when new images are uploaded
	 *
	 * @param array $metadata Metadata array
	 * @param int   $attachment_id Attachment ID
	 *
	 * @return array Filtered metadata
	 */
	public static function filter_attachment_metadata( $metadata, $attachment_id ) {
		// WordPress will already have skipped generation of disabled sizes
		// due to our filter_intermediate_sizes hook

		if ( ! $metadata || ! isset( $metadata['sizes'] ) ) {
			return $metadata;
		}

		$disabled_sizes = GIC_Settings::get_disabled_sizes();

		if ( empty( $disabled_sizes ) ) {
			return $metadata;
		}

		// Remove disabled sizes from metadata to keep it clean
		foreach ( $disabled_sizes as $size_name ) {
			if ( isset( $metadata['sizes'][ $size_name ] ) ) {
				unset( $metadata['sizes'][ $size_name ] );
			}
		}

		return $metadata;
	}
}
