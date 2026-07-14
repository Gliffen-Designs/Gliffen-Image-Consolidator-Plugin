<?php
/**
 * Image URL Handler Class
 * Intercepts image URL generation to use replacement sizes for disabled sizes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Image_URL_Handler {

	/**
	 * Initialize the image URL handler
	 */
	public static function init() {
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'replace_disabled_size_url' ), 10, 4 );
	}

	/**
	 * Replace disabled size URLs with replacement size URLs
	 *
	 * @param array|false $image          Image data array or false
	 * @param int         $attachment_id  Attachment ID
	 * @param string|int  $size           Size name or array
	 * @param bool        $icon           Whether to prefer an icon
	 *
	 * @return array|false Modified image data or original
	 */
	public static function replace_disabled_size_url( $image, $attachment_id, $size, $icon ) {
		// Skip if no image or icon is requested
		if ( ! $image || $icon ) {
			return $image;
		}

		// Size must be a string (not an array of dimensions)
		if ( ! is_string( $size ) ) {
			return $image;
		}

		// Get size mappings (disabled sizes mapped to replacements)
		$size_mappings = GIC_Settings::get_size_mappings();

		// If no mappings or this size isn't being consolidated, skip
		if ( empty( $size_mappings ) || ! isset( $size_mappings[ $size ] ) ) {
			return $image;
		}

		// Get the replacement size
		$replacement_size = $size_mappings[ $size ];

		// Get attachment metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! $metadata || ! isset( $metadata['sizes'][ $replacement_size ] ) ) {
			// Replacement size doesn't exist in metadata, fall back to original
			return $image;
		}

		// Build URL to replacement size
		$replacement_file = $metadata['sizes'][ $replacement_size ]['file'];
		$uploads = wp_get_upload_dir();

		// Extract date path from the original image URL (YYYY/MM)
		// The original $image[0] URL already has the correct path
		$original_url = $image[0];
		$pattern = '/uploads\/([0-9]{4}\/[0-9]{2})\//';
		preg_match( $pattern, $original_url, $matches );
		
		if ( ! isset( $matches[1] ) ) {
			// Couldn't extract date path, fall back to original
			return $image;
		}
		
		$attachment_date = $matches[1];
		$replacement_url = $uploads['baseurl'] . '/' . $attachment_date . '/' . $replacement_file;

		// Get dimensions of replacement size
		$width = isset( $metadata['sizes'][ $replacement_size ]['width'] ) ? $metadata['sizes'][ $replacement_size ]['width'] : 0;
		$height = isset( $metadata['sizes'][ $replacement_size ]['height'] ) ? $metadata['sizes'][ $replacement_size ]['height'] : 0;

		return array( $replacement_url, $width, $height, false );
	}
}
