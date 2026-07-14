<?php
/**
 * 404 Handler Class
 * Hooks into WordPress's 404 handling to serve replacement images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_404_Handler {

	/**
	 * Initialize the 404 handler
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_404' ), 1 );
	}

	/**
	 * Handle 404 requests for missing images
	 */
	public static function handle_404() {
		// Check if this is a 404
		if ( ! is_404() ) {
			return;
		}

		// Get the requested URI path
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		
		if ( empty( $request_uri ) ) {
			return;
		}

		// Check if it's an image request
		if ( ! self::is_image_request( $request_uri ) ) {
			return;
		}

		// Try to handle as a consolidation redirect
		$result = GIC_Image_Serve_Handler::handle_missing_image( $request_uri );

		if ( $result ) {
			exit;
		}
	}

	/**
	 * Check if the requested URI is for an image file
	 *
	 * @param string $uri The requested URI
	 *
	 * @return bool True if it's an image request
	 */
	private static function is_image_request( $uri ) {
		$pattern = '/uploads\/([0-9]{4}\/[0-9]{2})\/(.+)\.(jpg|jpeg|png|gif|webp)$/i';
		return preg_match( $pattern, $uri ) === 1;
	}
}
