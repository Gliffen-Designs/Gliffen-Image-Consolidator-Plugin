# Nginx Setup Guide

## Overview

Gliffen Image Consolidator uses `.htaccess` for **Apache servers**. Nginx users must configure equivalent rewrite rules manually in their Nginx configuration file.

## Automatic .htaccess

The plugin automatically creates `.htaccess` rules on Apache. If you're using Nginx, you can safely ignore Apache-related warnings about .htaccess.

## Nginx Configuration

### Step 1: Access Your Nginx Configuration

Nginx configuration is typically located at:
- `/etc/nginx/nginx.conf`
- `/etc/nginx/sites-available/your-domain.com`
- `/etc/nginx/conf.d/your-domain.conf`

Your hosting provider's control panel may provide a way to edit this.

### Step 2: Add the Rewrite Rule

Add this rule to your `server { }` block:

```nginx
# Gliffen Image Consolidator - Serve replacement images for disabled sizes
location ~* "\.(?:jpg|jpeg|png|gif|webp)$" {
    try_files $uri /wp-content/plugins/gliffen-image-consolidator/includes/image-serve-handler.php?requested=$uri;
}
```

**Important**: Place this rule BEFORE any generic PHP handler rules.

### Example Configuration

```nginx
server {
    listen 80;
    server_name example.com;
    root /home/user/public_html;

    # Gliffen Image Consolidator
    location ~* "\.(?:jpg|jpeg|png|gif|webp)$" {
        try_files $uri /wp-content/plugins/gliffen-image-consolidator/includes/image-serve-handler.php?requested=$uri;
    }

    # WordPress PHP handler
    location ~ \.php$ {
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$args;
    }
}
```

### Step 3: Test the Configuration

After adding the rules:

```bash
# Test Nginx configuration
sudo nginx -t

# If OK, reload Nginx
sudo systemctl reload nginx
# or
sudo service nginx reload
```

### Step 4: Verify

1. Visit a page with images
2. Check browser developer tools (F12) for any 404 errors
3. Verify images load correctly

## Managed Hosting (cPanel, Plesk, etc.)

If you use managed hosting with a control panel:

### cPanel (EasyApache)

1. Log into cPanel
2. Go to **File Manager** or **Zone Editor**
3. Edit the Nginx configuration through your host's interface
4. Add the rewrite rule above
5. Restart Nginx

### Plesk

1. Log into Plesk
2. Go to **Domains** → Your Domain
3. Go to **Nginx Settings**
4. Add the rules to the "Nginx Configuration" section

### Other Providers

Contact your hosting provider's support team with this configuration and they can add it for you.

## Troubleshooting

### Issue: Images Not Loading After Adding Rule

**Solution**:
1. Verify rule syntax with `sudo nginx -t`
2. Check that file path is correct
3. Ensure plugin directory exists at `/wp-content/plugins/gliffen-image-consolidator/`
4. Clear your browser cache
5. Check Nginx error log: `/var/log/nginx/error.log`

### Issue: PHP Errors in Handler

**Check**:
1. Is PHP-FPM running?
2. Are permissions correct on the handler file?
3. Check error logs at `/var/log/php-fpm.log` or similar

### Issue: Too Many Redirects

**Cause**: Rule is catching the handler file itself.

**Solution**: The `try_files` directive should prevent this, but verify your location block order isn't incorrect.

## Performance

The Nginx configuration is:
- **Fast**: Native Nginx file checking with `try_files`
- **Efficient**: No external redirects
- **Transparent**: User sees original requested URL

## Reverting Changes

To remove the Image Consolidator configuration:

1. Edit your Nginx config file
2. Delete the entire `location ~* "\.(?:jpg|jpeg|png|gif|webp)$"` block
3. Test: `sudo nginx -t`
4. Reload: `sudo systemctl reload nginx`

## Need Help?

If you need assistance with Nginx configuration, contact:
- Your hosting provider's support team
- Nginx documentation: https://nginx.org/en/docs/
- Gliffen support at https://gliffen.com
