<?php
/**
 * Uninstall handler for Gliffen Image Consolidator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options
delete_option( 'gic_disabled_sizes' );
delete_option( 'gic_size_mappings' );
delete_option( 'gic_auto_delete_enabled' );
delete_option( 'gic_enable_logging' );
delete_option( 'gic_htaccess_configured' );
delete_option( 'gic_audit_log' );
