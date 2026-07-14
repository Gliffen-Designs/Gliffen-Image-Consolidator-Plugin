# Image Size Consolidator Plugin - Development Roadmap

## Project Overview

A WordPress plugin that consolidates registered image sizes to reduce website bloat. Instead of WordPress generating every registered image size for every upload, this plugin allows administrators to:
- Specify which image sizes should NOT be created during upload
- Map unused image sizes to existing, already-generated sizes
- Intelligently serve the replacement image when the original is requested

**Status**: Pre-development (Planning Phase)

---

## Problem Statement

WordPress installations often accumulate numerous registered image sizes from the theme and various plugins. Each image upload triggers generation of ALL registered sizes, resulting in:
- Excessive disk space usage
- Slower image upload/processing
- Unnecessary storage of duplicate or near-duplicate images
- Performance degradation on servers with limited resources

**Solution**: Allow selective disabling of image size creation while maintaining site functionality by serving alternative images when disabled sizes are requested.

---

## Core Features (MVP)

### Phase 1: Discovery & Management UI
- [ ] **Image Size Inventory**: Display all registered WordPress image sizes with metadata
  - Dimensions (width x height)
  - Aspect ratio
  - Which themes/plugins registered each size
  - Actual disk usage per size (if possible)
  - Last modified date
  
- [ ] **Admin Interface**: Manage image size consolidation
  - List view of all registered sizes
  - Toggle to enable/disable creation for each size
  - Dropdown to select replacement size from available alternatives
  - Visual indicators for:
    - Which sizes are native WordPress
    - Which sizes are from themes
    - Which sizes are from other plugins
    - Which sizes are currently disabled

### Phase 2: Image Processing Hooks
- [ ] **Prevent Size Generation**: Hook into WordPress image generation to skip disabled sizes
  - Hook: `wp_generate_attachment_metadata` filter
  - Skip intermediate image generation for disabled sizes
  - Log skipped sizes for audit trail

- [ ] **Image Serving/Fallback**: Web server intercepts missing image files
  - .htaccess `!-f` rule detects non-existent files
  - Route to PHP handler which looks up replacement mapping
  - Serve replacement image directly (completely transparent to user)
  - Maintains original URL in browser (no redirects)

### Phase 3: Database & Settings
- [ ] **Settings Storage**: Persist consolidation mappings
  - Option: `image_size_consolidator_disabled_sizes` (JSON array)
  - Option: `image_size_consolidator_mappings` (JSON object with size => replacement mapping)
  - Version tracking for settings schema

- [ ] **Migration Support**: Handle existing images
  - Option to delete already-created files for disabled sizes (optional cleanup)
  - Generate missing replacement sizes if needed
  - Audit log of deleted/migrated files

---

## Technical Architecture

### Class Structure

```
image-size-consolidator/
├── image-size-consolidator.php (Main plugin file)
├── admin/
│   ├── class-admin-page.php (Settings UI)
│   └── css/admin.css
├── includes/
│   ├── class-size-manager.php (Discover & manage sizes)
│   ├── class-settings.php (Settings management)
│   ├── class-image-processor.php (Prevent image generation)
│   ├── class-size-detector.php (Identify which plugin registered each size)
│   ├── class-audit-logger.php (Track consolidation changes)
│   ├── class-image-serve-handler.php (Web server file serving)
│   ├── class-htaccess-manager.php (Manage .htaccess rules)
│   └── image-serve-handler.php (Entry point for web server)
├── uninstall.php (Cleanup on uninstall)
└── languages/
    └── image-size-consolidator.pot
```

### Database Schema

**WordPress Options (JSON serialized)**

```php
// All disabled image sizes
option: 'isc_disabled_sizes'
value: ['thumbnail-200x200', 'medium-400x300', 'custom-gallery-100x100']

// Consolidation mappings: disabled => replacement
option: 'isc_size_mappings'
value: {
  'thumbnail-200x200': 'thumbnail',
  'medium-400x300': 'medium',
  'custom-gallery-100x100': 'medium'
}

// Audit trail of changes
option: 'isc_audit_log'
value: [
  {
    timestamp: '2026-06-03 14:30:00',
    action: 'disable_size',
    size: 'thumbnail-200x200',
    replaced_with: 'thumbnail',
    user_id: 1
  }
]

// Settings
option: 'isc_auto_delete_files' // bool - auto-delete disabled size files
option: 'isc_enable_logging' // bool - enable audit logging
```

### Key Hooks & Filters

**Preventing Image Generation:**
```php
// Hook into intermediate image metadata generation
add_filter('wp_generate_attachment_metadata', 'isc_filter_intermediate_sizes');

// Modify sizes to generate
add_filter('intermediate_image_sizes_advanced', 'isc_exclude_disabled_sizes');
```

**Web Server Interception (.htaccess):**
```apache
# Intercept requests for non-existent image files
<FilesMatch "\.(?:jpg|jpeg|png|gif|webp)$">
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /wp-content/plugins/image-size-consolidator/includes/image-serve-handler.php?requested=%{REQUEST_URI} [L]
</FilesMatch>
```

**Image Serving Handler:**
```php
// Lightweight PHP handler only runs for non-existent files
require_once 'class-image-serve-handler.php';
ISC_Image_Serve_Handler::handle_missing_image();
```

**Admin Hooks:**
```php
add_action('admin_menu', 'isc_add_admin_menu');
add_action('admin_enqueue_scripts', 'isc_enqueue_scripts');
add_action('wp_ajax_isc_get_sizes', 'isc_ajax_get_sizes');
add_action('wp_ajax_isc_save_mapping', 'isc_ajax_save_mapping');
add_action('wp_ajax_isc_cleanup_files', 'isc_ajax_cleanup_files');
```

---

## Implementation Phases

### Phase 1: Foundation (Week 1-2)
**Goal**: Core infrastructure & discovery

- [ ] Create plugin structure and main file
- [ ] Build `class-settings.php` for option management
- [ ] Build `class-size-manager.php` to discover all registered sizes
  - Query `$GLOBALS['_wp_intermediate_sizes']`
  - Query theme's `add_theme_support('post-thumbnails')` definitions
  - Parse plugin headers to identify source
- [ ] Create settings UI stub (basic form)
- [ ] Setup AJAX endpoints for getting sizes

**Deliverables:**
- Working plugin that loads
- Can list all registered image sizes
- Can save/retrieve mappings from database

---

### Phase 2: Web Server Interception & Processing (Week 3-4)
**Goal**: Prevent generation and setup transparent file serving

- [ ] Implement `class-image-processor.php`
  - Filter `wp_generate_attachment_metadata` to skip disabled sizes
  - Implement smart logging of skipped sizes
  
- [ ] Create `class-image-serve-handler.php`
  - Parse requested image filename from URL
  - Extract size dimensions from filename pattern
  - Look up replacement mapping (cached)
  - Serve replacement file directly with proper headers
  - Handle missing replacement scenarios

- [ ] Create `class-htaccess-manager.php`
  - Generate .htaccess rewrite rules on activation
  - Inject `!-f` interception rules
  - Backup existing .htaccess before modification
  - Restore on deactivation
  - Provide Nginx equivalent in documentation
  
- [ ] Create `image-serve-handler.php` entry point
  - Lightweight script called by .htaccess
  - Initializes WordPress minimally (if needed for functions)
  - Routes to `ISC_Image_Serve_Handler`
  
- [ ] Create `class-audit-logger.php`
  - Log all consolidation actions
  - Track file deletions
  - Track mapping changes

**Deliverables:**
- Images no longer generate disabled sizes
- Missing image files transparently served from replacements
- No WordPress filter complexity
- .htaccess rules automatically managed
- Audit trail of all actions

---

### Phase 3: Management & Cleanup (Week 5-6)
**Goal**: Administrative controls and maintenance

- [ ] Enhanced admin UI
  - Consolidated settings page layout
  - Visual mapping manager (drag-and-drop or select)
  - Size statistics (disk usage, file counts)
  - Preview of replacements
  
- [ ] Cleanup utilities
  - Batch delete files for disabled sizes
  - Progress indicator for large operations
  - Validation checks before cleanup
  - Dry-run option
  
- [ ] Settings page additions
  - Toggle auto-delete on disable
  - Logging preferences
  - Cache clearing options

**Deliverables:**
- Full-featured admin interface
- Safe cleanup tools
- Comprehensive logging

---

### Phase 4: Optimization & Polish (Week 7-8)
**Goal**: Performance and robustness

- [ ] Caching improvements
  - Cache registered sizes list (refresh on plugin activation)
  - Cache mapping lookups
  - Cache aspect ratio calculations
  
- [ ] Performance optimization
  - Lazy-load admin stats
  - Optimize audit log storage
  - Archive old logs
  
- [ ] Documentation
  - Code documentation
  - User guide
  - Troubleshooting section
  
- [ ] Testing
  - Manual testing on various WordPress versions
  - Multisite compatibility
  - Testing with popular plugins (Elementor, ACF, etc.)

**Deliverables:**
- Optimized, performant plugin
- Complete documentation
- Tested and verified

---

## Design Decisions

### 1. **Integration Point: WebP Converter vs Standalone Plugin**
**Current Thinking**: Build as separate plugin initially
- **Pros**: Focused scope, can be used independently, easier to maintain
- **Cons**: Potential duplication of some utility code
- **Decision**: Build standalone, potentially integrate later if both become core to image management

### 2. **Image Size Source Detection**
**Approach**: Parse plugin headers and theme data
- Use `WP_Theme::get_file_data()`
- Use `get_plugins()`
- Hardcode known WordPress native sizes
- **Trade-off**: May not catch programmatically registered sizes from unknown sources

### 3. **Image File Serving Strategy**
**Approach**: Web server-first, transparent file serving
- Use `.htaccess` `!-f` (not-a-file) flag to intercept requests for missing image files
- No redirects - directly serve replacement from PHP handler
- File lookup: exact filename pattern match → replacement mapping
- Original URL preserved in browser (completely transparent)
- Works universally (CSS, JS, REST API, hardcoded links)
- **Trade-off**: Requires .htaccess; Nginx requires separate configuration

### 4. **File Deletion Policy**
**Options**:
- **A**: Never auto-delete (admin manually cleans)
- **B**: Auto-delete immediately when disabled (risky)
- **C**: Auto-delete on-demand with confirmation (chosen)
- **Decision**: Option C with dry-run support

### 5. **Backward Compatibility**
- Support WordPress 6.0+ initially
- Test for multisite issues
- Ensure compatibility with image optimization plugins
- Handle cases where images were already generated

### 6. **Server Requirements**
- **Apache**: .htaccess support required (standard)
- **Nginx**: Requires separate `try_files` configuration (documented for users)
- **Shared Hosting**: Supported if .htaccess is enabled (common)
- **Managed WordPress**: May require manual nginx config (noted in docs)

---

## Potential Challenges & Mitigations

| Challenge | Risk | Mitigation |
|-----------|------|-----------|
| Themes/plugins relying on specific sizes | High | UI warning about size dependencies, audit log |
| Image quality loss from upscaling | Medium | Show replacement size dimensions, warn on small replacements |
| Existing files not cleaned up | Medium | Separate cleanup tool with dry-run, clear documentation |
| Nginx compatibility | Medium | Provide nginx config example, document in setup guide |
| .htaccess conflicts | Medium | Backup existing .htaccess before modification, restore on deactivation |
| CDN caching | Low | Test with common CDNs, document cache behavior |
| Multisite complexity | Medium | Test thoroughly; test .htaccess in multisite subdirectories |
| Aspect ratio mismatches | Low | Show replacement aspect ratio in UI, document this |
| Image filename parsing edge cases | Low | Comprehensive regex testing, fallback to 404 if parse fails |

---

## Future Enhancements (Post-MVP)

- [ ] **Integration with WebP Converter**: Consolidate both plugins' image processing
- [ ] **Smart Mapping Suggestions**: AI recommendation based on aspect ratio similarity
- [ ] **Disk Usage Analytics**: Dashboard showing space saved
- [ ] **Granular Plugin Detection**: Identify which plugins request which sizes during rendering
- [ ] **Dynamic Size Creation**: Create sizes on-demand for specific images
- [ ] **REST API**: Manage consolidation via REST endpoints
- [ ] **Scheduled Cleanup**: Background task for file cleanup
- [ ] **Size Performance Metrics**: Track rendering performance impact
- [ ] **Pro Version**: Advanced features (better analytics, more automation)

---

## Files to Create

```
image-size-consolidator/
├── image-size-consolidator.php ............. Main plugin file
├── admin/
│   ├── class-admin-page.php ............... Admin UI
│   ├── js/
│   │   └── admin.js ....................... Interactive UI
│   └── css/
│       └── admin.css ....................... Admin styles
├── includes/
│   ├── class-settings.php ................. Settings management
│   ├── class-size-manager.php ............. Discover & manage sizes
│   ├── class-image-processor.php .......... Image processing hooks
│   ├── class-size-detector.php ............ Detect size sources
│   ├── class-audit-logger.php ............. Audit trail
│   ├── class-image-serve-handler.php ...... File serving logic
│   ├── class-htaccess-manager.php ......... .htaccess management
│   └── image-serve-handler.php ............ Web server entry point
├── uninstall.php .......................... Cleanup on uninstall
├── languages/
│   └── image-size-consolidator.pot ........ Translation template
├── README.md .............................. User documentation
├── docs/
│   ├── nginx-setup.md ..................... Nginx configuration guide
│   └── apache-setup.md .................... Apache configuration guide
└── ROADMAP.md ............................. This file
```

---

## Testing Checklist

- [ ] WordPress 6.0 - 6.x compatibility
- [ ] Multisite installation
- [ ] Images with various formats (jpg, png, gif, webp)
- [ ] Very large images
- [ ] Very small images  
- [ ] Custom aspect ratios
- [ ] Elementor integration
- [ ] ACF integration
- [ ] Gutenberg block images
- [ ] REST API usage
- [ ] Admin image list views
- [ ] Frontend rendering (various theme layouts)
- [ ] Performance with 1000+ images
- [ ] File cleanup operations
- [ ] Audit log integrity
- [ ] Plugin deactivation/reactivation

---

## Success Criteria

- [x] Clear technical roadmap established
- [ ] MVP features implemented and tested
- [ ] Documentation complete
- [ ] No reported conflicts with major plugins
- [ ] File storage reduced by at least 30% in typical installations
- [ ] No noticeable performance impact
- [ ] Admin can safely manage consolidation
- [ ] Audit trail trustworthy and complete

---

## Questions to Resolve

1. **Scope**: Should this handle thumbnails specifically, or all image sizes? This should handle all image sizes. It is more liekly to be used at the larger image sizes with soft cropping as this will have the bigest impact on storage. 
2. **Aspect Ratio**: How strictly should we match aspect ratios for replacements? replacements are to the deisgnated size. if an adminmake the mistake of selecting a size that is a different aspect ratio and that ratio is important to the layout, it should break the design and the admin will know to fix their configruation
3. **Responsiveness**: How should we handle srcset and picture elements? Alternative sizes that acutally exist can be set. I suspect there will need to be some 404 intercept handling for image sizes that are suppose to be there but are not. 
4. **Plugins**: Which popular plugins should we test compatibility with first?
5. **Integration**: After MVP, should this merge into WebP Converter plugin? not yet
6. **Server Interception**: Web server intercepts missing image files
   - .htaccess `!-f` rule catches requests for non-existent files
   - Lightweight PHP handler looks up replacement mapping
   - Directly serves replacement file (no redirects)
   - Browser never knows the image is from a different URL
   - Apache/standard hosting supported; Nginx requires separate config 
7. **UX**: Would drag-and-drop mapping be better than dropdowns? no
8. **Analytics**: Should we track which sizes are actually being used? no neccessary

---

## Version History

- **v0.1.0** (Planned): Initial roadmap and planning document
