<?php
/**
 * Size Manager Class
 * Handles discovery and management of registered image sizes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Size_Manager {

	/**
	 * Initialize size manager
	 */
	public static function init() {
		add_action( 'wp_ajax_gic_get_sizes', array( __CLASS__, 'ajax_get_sizes' ) );
	}

	/**
	 * Get all registered image sizes with detailed info
	 *
	 * @return array Array of image sizes with metadata
	 */
	public static function get_all_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = array();

		// Get native WordPress sizes
		$native_sizes = array(
			'thumbnail' => array(
				'width'     => get_option( 'thumbnail_size_w' ),
				'height'    => get_option( 'thumbnail_size_h' ),
				'crop'      => (bool) get_option( 'thumbnail_crop' ),
				'source'    => 'WordPress Native',
			),
			'medium'    => array(
				'width'     => get_option( 'medium_size_w' ),
				'height'    => get_option( 'medium_size_h' ),
				'crop'      => false,
				'source'    => 'WordPress Native',
			),
			'medium_large' => array(
				'width'     => get_option( 'medium_large_size_w' ),
				'height'    => get_option( 'medium_large_size_h' ),
				'crop'      => false,
				'source'    => 'WordPress Native',
			),
			'large'     => array(
				'width'     => get_option( 'large_size_w' ),
				'height'    => get_option( 'large_size_h' ),
				'crop'      => false,
				'source'    => 'WordPress Native',
			),
		);

		// Filter out unset sizes and add to main array
		foreach ( $native_sizes as $name => $size ) {
			if ( $size['width'] || $size['height'] ) {
				$size['name'] = $name;
				$size['aspect_ratio'] = self::calculate_aspect_ratio( $size['width'], $size['height'] );
				$sizes[ $name ] = $size;
			}
		}

		// Get custom image sizes from theme and plugins
		if ( is_array( $_wp_additional_image_sizes ) && ! empty( $_wp_additional_image_sizes ) ) {
			foreach ( $_wp_additional_image_sizes as $name => $size ) {
				$source = self::detect_size_source( $name );
				
				$sizes[ $name ] = array(
					'name'          => $name,
					'width'         => $size['width'],
					'height'        => $size['height'],
					'crop'          => isset( $size['crop'] ) ? $size['crop'] : false,
					'aspect_ratio'  => self::calculate_aspect_ratio( $size['width'], $size['height'] ),
					'source'        => $source,
				);
			}
		}

		return $sizes;
	}

	/**
	 * Calculate aspect ratio from dimensions
	 *
	 * @param int $width Width
	 * @param int $height Height
	 *
	 * @return string Aspect ratio as string (e.g., "16:9")
	 */
	public static function calculate_aspect_ratio( $width, $height ) {
		if ( ! $width || ! $height ) {
			return 'N/A';
		}

		$gcd = self::gcd( (int) $width, (int) $height );
		$w = (int) $width / $gcd;
		$h = (int) $height / $gcd;

		return $w . ':' . $h;
	}

	/**
	 * Greatest Common Divisor helper
	 *
	 * @param int $a First number
	 * @param int $b Second number
	 *
	 * @return int GCD
	 */
	private static function gcd( $a, $b ) {
		return $b === 0 ? $a : self::gcd( $b, $a % $b );
	}

	/**
	 * Detect the source of an image size (theme, plugin, etc.)
	 *
	 * @param string $size_name Size name
	 *
	 * @return string Source description
	 */
	public static function detect_size_source( $size_name ) {
		// Check theme first
		$theme = wp_get_theme();
		$theme_supports = $theme->get( 'supports' );

		if ( is_array( $theme_supports ) && in_array( 'post-thumbnails', $theme_supports, true ) ) {
			// This is a simple check - could be more sophisticated
			return 'Theme: ' . $theme->get( 'Name' );
		}

		// Check active plugins
		$active_plugins = get_option( 'active_plugins', array() );
		foreach ( $active_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
			// This is a heuristic - in reality we'd need to trace back to where the size was registered
			// For now, return a generic plugin source
		}

		return 'Plugin/Theme';
	}

	/**
	 * AJAX: Get all sizes
	 */
	public static function ajax_get_sizes() {
		check_ajax_referer( 'gic_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$sizes = self::get_all_sizes();
		$disabled_sizes = GIC_Settings::get_disabled_sizes();
		$size_mappings = GIC_Settings::get_size_mappings();

		// Enrich sizes with disable/mapping info
		foreach ( $sizes as &$size ) {
			$size['is_disabled'] = in_array( $size['name'], $disabled_sizes, true );
			$size['replacement'] = isset( $size_mappings[ $size['name'] ] ) ? $size_mappings[ $size['name'] ] : '';
		}

		wp_send_json_success( $sizes );
	}

}
