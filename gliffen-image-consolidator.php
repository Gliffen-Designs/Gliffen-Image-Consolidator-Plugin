<?php
/**
 * Plugin Name: Gliffen Image Consolidator
 * Plugin URI: https://github.com/Gliffen-Designs/Gliffen-Image-Consolidator-Plugin
 * Description: Consolidate WordPress image sizes to reduce disk bloat by disabling unnecessary sizes and serving alternatives
 * Version: 0.1.0
 * Author: Gliffen
 * Author URI: https://gliffen.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gliffen-image-consolidator
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );



// Require all necessary classes
require_once GIC_PLUGIN_DIR . 'includes/class-settings.php';
require_once GIC_PLUGIN_DIR . 'includes/class-size-manager.php';
require_once GIC_PLUGIN_DIR . 'includes/class-audit-logger.php';
require_once GIC_PLUGIN_DIR . 'includes/class-image-processor.php';
require_once GIC_PLUGIN_DIR . 'includes/class-image-url-handler.php';
require_once GIC_PLUGIN_DIR . 'includes/class-image-serve-handler.php';
require_once GIC_PLUGIN_DIR . 'includes/class-404-handler.php';
require_once GIC_PLUGIN_DIR . 'includes/class-cleanup-handler.php';
require_once GIC_PLUGIN_DIR . 'admin/class-admin-page.php';
require_once GIC_PLUGIN_DIR . 'includes/class-update-checker.php';


/**
 * Initialize the plugin
 */
function gic_init() {
	// Initialize settings
	GIC_Settings::init();
	GIC_Audit_Logger::init();

	// Initialize image processor (prevents generation of disabled sizes)
	GIC_Image_Processor::init();

	// Initialize image URL handler (replaces disabled size URLs with replacement URLs)
	GIC_Image_URL_Handler::init();

	// Initialize 404 handler (redirects to replacement images for edge cases)
	GIC_404_Handler::init();

	// Initialize cleanup handler (manages cleanup operations)
	GIC_Cleanup_Handler::init();

	// Initialize size manager
	GIC_Size_Manager::init();

	// Initialize GitHub update checker
    GIC_Update_Checker::init();

	// Initialize admin page (only in admin)
	if ( is_admin() ) {
		GIC_Admin_Page::init();
	}
}
add_action( 'plugins_loaded', 'gic_init' );

/**
 * Get plugin version from the registered plugin header.
 */
function gliffen_image_consolidator_get_version() {
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'));
    $version = !empty($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';

    return $version;
}


/**
 * Activation hook
 */
function gic_activate() {
	// Ensure all default options exist
	GIC_Settings::ensure_defaults();
}
register_activation_hook( __FILE__, 'gic_activate' );

/**
 * Deactivation hook
 */
function gic_deactivate() {
	// No cleanup needed - settings are preserved for reactivation
}
register_deactivation_hook( __FILE__, 'gic_deactivate' );

