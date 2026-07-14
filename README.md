# Gliffen Image Consolidator

**Version:** 0.1.0 (Phase 2 Complete)  
**Status:** Early Development  
**License:** GPL v2 or later

## Description

Consolidate WordPress image sizes to reduce disk bloat by disabling unnecessary image sizes and serving alternative sizes instead. This plugin helps reduce storage usage and image upload processing time.

## Features (Phase 1 + 2)

- ✅ **Image Size Discovery**: Display all registered WordPress image sizes with metadata
  - Shows dimensions, aspect ratio, source (theme/plugin/WordPress native)
  - Lists all image sizes in a sortable table
  
- ✅ **Admin Interface**: Simple management UI
  - Check/uncheck image sizes to disable
  - Select replacement size for each disabled size
  - Save settings with AJAX
  
- ✅ **Settings Management**: Persist configurations
  - Store disabled sizes list
  - Store size mappings (disabled → replacement)
  - Database-backed options

- ✅ **Image Generation Prevention** (Phase 2)
  - Automatically skips generation of disabled image sizes
  - Logs all skipped sizes for audit trail
  - Prevents unnecessary storage usage

- ✅ **Web Server Interception** (Phase 2)
  - Requests for disabled image sizes are intercepted
  - Replacement images are served transparently
  - User never knows original image doesn't exist
  - Works with Apache (automatic .htaccess) and Nginx (manual config)

- ✅ **Audit Logging** (Phase 2)
  - Logs all image generation skips
  - Tracks consolidation configuration changes
  - View statistics on logged actions

- ✅ **Server Configuration Management** (Phase 2)
  - Automatic .htaccess rule injection (Apache)
  - Automatic rule removal on deactivation
  - Backup of original .htaccess files
  - Documentation for manual Nginx setup

## Installation & Server Setup

### WordPress Installation

1. Clone or download this plugin to `/wp-content/plugins/gliffen-image-consolidator/`
2. Activate the plugin from WordPress admin
3. Navigate to Tools → Image Consolidator

## What Happens When You Consolidate

1. **Image Upload**: When you upload a new image, disabled sizes are NOT generated
   - This saves storage space immediately
   - Reduces upload processing time
   - Fewer files created

2. **Image Request**: When WordPress or a plugin requests a disabled image size:
   - The web server intercepts the request
   - The image-serve-handler looks up the replacement mapping
   - The replacement image is served transparently
   - User sees the image with no visible difference

3. **Existing Images**: Old images with disabled sizes already created:
   - Continue to work as before
   - Can be manually cleaned up (Phase 3)
   - Or left as-is with no negative impact

### Example Workflow

1. You have 5 image sizes registered
2. You don't use 2 of them but other plugins depend on them
3. Configure those 2 sizes as disabled with suitable replacements
4. Save settings
5. New images uploaded going forward skip those 2 sizes
6. Storage usage drops by ~40% (depending on your setup)
7. Images still work everywhere because replacements are served
## Usage

### Basic Workflow

1. **Review Image Sizes**: Go to Tools → Image Consolidator
2. **Select Sizes to Disable**: Check the boxes next to image sizes you want to disable
3. **Choose Replacements**: Select a replacement size from the dropdown for each disabled size
4. **Save Settings**: Click "Save Settings" button
2 Complete - Most Core Features Working**

- ✅ Image generation prevention fully functional
- ✅ Web server interception working (Apache + Nginx)
- ✅ Audit logging operational
- ⚠️ File cleanup utilities not yet implemented (Phase 3)
- ⚠️ Enhanced admin features coming (Phase 3
2. Select `thumbnail (300x300)` from the replacement dropdown
3. Click "Save Settings"

Now, when WordPress tries to generate the `thumbnail-200x200` size, it won't be created. When code requests that size, it will be served from the `thumbnail` (300x300) instead.

## Important Notes

⚠️ **Phase 2 Complete - Most Core Features Working**

- ✅ Image generation prevention fully functional
- ✅ Web server interception working (Apache + Nginx)
- ✅ Audit logging operational
- ⚠️ File cleanup utilities not yet implemented (Phase 3)
- ⚠️ Enhanced admin features coming (Phase 3)

## Architecture

### Phase 1-2 Components

```
gliffen-image-consolidator/
├── gliffen-image-consolidator.php       Main plugin file
├── README.md                            Documentation
├── uninstall.php                        Cleanup on uninstall
├── docs/
│   ├── apache-setup.md                  Apache configuration guide
│   └── nginx-setup.md                   Nginx configuration guide
├── admin/
│   ├── class-admin-page.php             Admin UI & menu
│   ├── css/admin.css                    Admin styles
│   └── js/admin.js                      Admin interactions
└── includes/
    ├── class-settings.php               Settings management
    ├── class-size-manager.php           Size discovery & AJAX
    ├── class-image-processor.php        Prevents image generation
    ├── class-image-serve-handler.php    Serves replacement images
    ├── class-htaccess-manager.php       Manages .htaccess rules
    ├── class-audit-logger.php           Tracks all actions
    └── image-serve-handler.php          Web server entry point
```

### Class Overview

**GIC_Settings** - Manages plugin options
- Persists disabled sizes and mappings
- Provides getter/setter methods
- Ensures defaults on activation

**GIC_Size_Manager** - Discovers registered sizes
- Finds all WordPress image sizes
- Calculates aspect ratios
- Detects size source
- Handles AJAX requests

**GIC_Image_Processor** - Prevents generation
- Filters `intermediate_image_sizes_advanced`
- Skips generation of disabled sizes
- Logs each skip to audit trail

**GIC_Image_Serve_Handler** - Serves replacements
- Intercepts requests for missing images
- Parses filename to extract size info
- Looks up replacement mapping
- Serves file with proper headers

**GIC_Htaccess_Manager** - Manages .htaccess
- Injects rewrite rules on activation
- Removes rules on deactivation
- Creates backups before modifying
- Provides Nginx config examples

**GIC_Audit_Logger** - Logs all actions
- Records image generation skips
- Tracks configuration changes
- Provides statistics and reporting

**GIC_Admin_Page** - Admin interface
- Renders settings UI
- Manages user interactions
- Displays image size inventory

## Roadmap

- **Phase 1**: ✅ Done - Core infrastructure, UI, settings
- **Phase 2**: ✅ Done - Image prevention, web server interception, audit logging
- **Phase 3**: Bulk image cleanup tools, enhanced admin features
- **Phase 4**: Optimization, caching, advanced analytics

## Advanced Usage

### Viewing Audit Logs

Access logs programmatically in development:

```php
// Get all logs
$logs = GIC_Audit_Logger::get_logs();

// Get logs for specific action
$skips = GIC_Audit_Logger::get_logs_by_action( 'skipped_generation', 50 );

// Get statistics
$stats = GIC_Audit_Logger::get_stats();
```

### Checking Settings

```php
// Check if size is disabled
if ( GIC_Settings::is_size_disabled( 'thumbnail-200x200' ) ) {
    $replacement = GIC_Settings::get_replacement_size( 'thumbnail-200x200' );
}
```

### API for Developers

The plugin provides WordPress hooks for extension:

```php
// Log custom action
GIC_Audit_Logger::log( 'custom_action', array(
    'user_id' => get_current_user_id(),
    'message' => 'Custom event',
) );

// Check if mod_rewrite available
if ( GIC_Htaccess_Manager::has_mod_rewrite() ) {
    // Do something Apache-specific
}
```

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher

## Support & Issues

For support, please contact Gliffen at https://gliffen.com

## License

This plugin is licensed under the GNU General Public License v2 or later.
