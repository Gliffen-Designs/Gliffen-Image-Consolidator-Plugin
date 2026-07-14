# Apache Setup Guide

## Automatic Setup

The Gliffen Image Consolidator plugin automatically manages .htaccess configuration on **Apache servers with mod_rewrite enabled**.

### What Happens Automatically

1. **On Activation**: The plugin inserts rewrite rules into your `.htaccess` file
2. **On Deactivation**: The plugin removes its rules from `.htaccess`
3. **Backup**: Before modifying, the plugin creates a backup of your existing `.htaccess` as `.htaccess.backup-TIMESTAMP`

### Requirements

- Apache web server with `mod_rewrite` enabled
- `.htaccess` support enabled in your `<Directory>` configuration
- Write permission for the WordPress root directory (where `.htaccess` is located)

## Manual Setup (If Automatic Fails)

If the plugin cannot automatically update your `.htaccess` file, you can add the rules manually:

### Step 1: Access Your .htaccess File

1. Connect via FTP or SSH
2. Navigate to your WordPress root directory
3. Locate the `.htaccess` file (it may be hidden)

### Step 2: Add the Rewrite Rules

Add these lines to your `.htaccess` file (preferably near the top, before WordPress rules):

```apache
# BEGIN Gliffen Image Consolidator
<FilesMatch "\.(?:jpg|jpeg|png|gif|webp)$">
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /wp-content/plugins/gliffen-image-consolidator/includes/image-serve-handler.php?requested=%{REQUEST_URI} [L]
</FilesMatch>
# END Gliffen Image Consolidator
```

### Step 3: Save and Test

1. Save the `.htaccess` file
2. Visit a page with an image to verify it loads correctly
3. Check your browser's developer tools to ensure no 404 errors

## Troubleshooting

### Issue: .htaccess File Deleted After Plugin Activation

**Cause**: The plugin may have had permission issues.

**Solution**:
1. Check file permissions on your WordPress root directory
2. Ensure the web server user has write permissions
3. Contact your hosting provider if permissions are locked

### Issue: Rewrite Rules Not Working

**Cause**: Apache may not have `mod_rewrite` enabled.

**Solution**:
1. Contact your hosting provider to enable `mod_rewrite`
2. Check if you're using a hosting company that disables it
3. Verify mod_rewrite status by adding this to `.htaccess`:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
   </IfModule>
   ```

### Issue: 404 Errors on Images

**Cause**: The image serve handler may not be working correctly.

**Solution**:
1. Check server error logs for PHP errors
2. Ensure the `image-serve-handler.php` file exists
3. Verify the WordPress `wp-load.php` can be found from the handler

## Manual Cleanup

If you're removing the plugin without using the deactivate hook, you can manually remove the rules:

1. Open `.htaccess`
2. Find and delete the section between:
   ```
   # BEGIN Gliffen Image Consolidator
   # END Gliffen Image Consolidator
   ```
3. Save the file

## Testing

To verify the image consolidation is working:

1. Upload an image to WordPress
2. Configure a size to be disabled in the Image Consolidator settings
3. Disable that size and select a replacement
4. Save settings
5. Re-upload the same image or use an old image
6. Check that the disabled size file does NOT exist in `/wp-content/uploads/`
7. Request the disabled size image in your browser
8. Verify it loads from the replacement size

## Performance Notes

- The rewrite rules are **very lightweight** and have minimal performance impact
- The handler only executes for non-existent image files
- Caching headers ensure browsers cache the replacement images
- Only one PHP script is invoked per missing image request
