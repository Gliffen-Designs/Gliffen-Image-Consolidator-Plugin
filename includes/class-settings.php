<?php
/**
 * Settings Class
 * Manages plugin settings and options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Settings {

	const PREFIX = 'gic_';

	/**
	 * Initialize settings
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_defaults' ) );
	}

	/**
	 * Ensure all default options exist
	 */
	public static function ensure_defaults() {
		$defaults = array(
			'disabled_sizes'        => array(),
			'size_mappings'         => array(),
			'auto_delete_enabled'   => false,
			'enable_logging'        => true,
			'htaccess_configured'   => false,
			'audit_log'             => array(),
		);

		foreach ( $defaults as $option => $default_value ) {
			if ( get_option( self::PREFIX . $option ) === false ) {
				add_option( self::PREFIX . $option, $default_value );
			}
		}
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		register_setting( 'gic_settings', self::PREFIX . 'disabled_sizes' );
		register_setting( 'gic_settings', self::PREFIX . 'size_mappings' );
		register_setting( 'gic_settings', self::PREFIX . 'auto_delete_enabled' );
		register_setting( 'gic_settings', self::PREFIX . 'enable_logging' );
		register_setting( 'gic_settings', self::PREFIX . 'htaccess_configured' );
		register_setting( 'gic_settings', self::PREFIX . 'audit_log' );
	}

	/**
	 * Get option value
	 *
	 * @param string $option Option name (without prefix)
	 * @param mixed  $default Default value
	 *
	 * @return mixed Option value
	 */
	public static function get_option( $option, $default = false ) {
		return get_option( self::PREFIX . $option, $default );
	}

	/**
	 * Update option value
	 *
	 * @param string $option Option name (without prefix)
	 * @param mixed  $value Option value
	 *
	 * @return bool Success status
	 */
	public static function update_option( $option, $value ) {
		return update_option( self::PREFIX . $option, $value );
	}

	/**
	 * Delete option
	 *
	 * @param string $option Option name (without prefix)
	 *
	 * @return bool Success status
	 */
	public static function delete_option( $option ) {
		return delete_option( self::PREFIX . $option );
	}

	/**
	 * Get all plugin settings
	 *
	 * @return array All settings
	 */
	public static function get_all_settings() {
		return array(
			'disabled_sizes'      => self::get_option( 'disabled_sizes', array() ),
			'size_mappings'       => self::get_option( 'size_mappings', array() ),
			'auto_delete_enabled' => self::get_option( 'auto_delete_enabled', false ),
			'enable_logging'      => self::get_option( 'enable_logging', true ),
			'htaccess_configured' => self::get_option( 'htaccess_configured', false ),
			'audit_log'           => self::get_option( 'audit_log', array() ),
		);
	}

	/**
	 * Get disabled sizes array
	 *
	 * @return array Disabled image sizes
	 */
	public static function get_disabled_sizes() {
		return (array) self::get_option( 'disabled_sizes', array() );
	}

	/**
	 * Get size mappings
	 *
	 * @return array Mappings: disabled_size => replacement_size
	 */
	public static function get_size_mappings() {
		return (array) self::get_option( 'size_mappings', array() );
	}

	/**
	 * Check if a size is disabled
	 *
	 * @param string $size_name Size name to check
	 *
	 * @return bool True if disabled
	 */
	public static function is_size_disabled( $size_name ) {
		$disabled = self::get_disabled_sizes();
		return in_array( $size_name, $disabled, true );
	}

	/**
	 * Get replacement for a disabled size
	 *
	 * @param string $size_name Disabled size name
	 *
	 * @return string|false Replacement size name or false
	 */
	public static function get_replacement_size( $size_name ) {
		$mappings = self::get_size_mappings();
		return isset( $mappings[ $size_name ] ) ? $mappings[ $size_name ] : false;
	}
}
