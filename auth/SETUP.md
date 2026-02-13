# Authentication System Setup Guide

## Initial Setup

### 1. No Database Required!

This authentication system uses **CSV files** for data storage. No MySQL or database setup needed!

### 2. Configure the System

The auth/config.php file is already created with default settings. The only thing you might want to customize is the data directory:

```php
'storage' => [
    'data_dir' => __DIR__ . '/data',  // CSV files stored here
],
```

CSV files will be automatically created in the `auth/data/` directory when you first use the system.

### 3. Fix File Permissions

The `auth/data/` directory must be writable by the web server.

**Windows (XAMPP):**
```batch
icacls "C:\xampp\htdocs\Workout\auth\data" /grant:r Everyone:(OI)(CI)F /T
attrib -R "C:\xampp\htdocs\Workout\auth\data" /S /D
```

**Linux/Mac:**
```bash
chmod 755 auth/data
chown www-data:www-data auth/data  # For Apache
```

**Test permissions:** Visit `http://localhost/Workout/auth/test_permissions.php`

### 4. Troubleshoot OneDrive Issues

If you get "Directory not writable" error and files are on OneDrive:

**Option A: Disable OneDrive sync (Quick fix)**
- Right-click `auth/data/` folder → **Always keep on this device**

**Option B: Move to non-OneDrive location (Recommended)**

Edit `auth/config.php`:
```php
'storage' => [
    'data_dir' => 'C:\\xampp\\htdocs\\workout_data',  // Use non-OneDrive path
],
```

**Option C: Use system temp directory**
```php
'storage' => [
    'data_dir' => sys_get_temp_dir() . '/workout_auth_data',
],
```

### 5. Test the Authentication System

1. Visit: http://localhost/Workout/auth/register.php
2. Create a test account
3. Login at: http://localhost/Workout/auth/login.php

### 6. Protect Your Pages

To require authentication for a page, add this at the top:

```php
<?php
require_once __DIR__ . '/auth/middleware.php';
// File Permissions Guide

### Windows Permissions

```batch
# Check current permissions
icacls "C:\path\to\auth\data"

# Grant full permissions
icacls "C:\path\to\auth\data" /grant:r Everyone:(OI)(CI)F /T

# Remove ReadOnly attribute
attrib -R "C:\path\to\auth\data" /S /D
```

### Linux/Mac Permissions

```bash
# Set directory permissions
chmod 755 auth/data
chmod 644 auth/data/*

# Set owner (for Apache)
chown -R www-data:www-data auth/data

# For Nginx
chown -R nobody:nobody auth/data
```

## Maintenance

### Clean Old Sessions & Logs

Run this script regularly:

```bash
php auth/maintenance.php
```

Or create a cron job:
```bash
0 2 * * * php /path/to/auth/maintenance.php
```

### Monitor Failed Login Attempts

Check the `auth/data/login_attempts.csv` file for patterns of failed logins.

### Backup User Data

```bash
# Windows
xcopy "auth\data" "backup\auth-data-%date%" /E /I

# Linux/Mac
cp -r auth/data backup/auth-data-$(date +%Y%m%d)
```
1. Get an SSL certificate (Let's Encrypt is free)
2. Update `session_cookie_secure` to `true` in config
3. Uncomment HSTS header in `security_headers.php`
4. Update `base_url` to use https://

### Email Verification (Optional)

To enable email verification:

1. Set `require_email_verification` to `true` in config
2. Implement email sending in the registration process
3. Create a verification endpoint

### Two-Factor Authentication (Optional)

To enable 2FA:

1. Set `enable_2fa` to `true` in config
2. Install a 2FA library (e.g., Google Authenticator)
3. Add 2FA setup and verification pages

## Maintenance

### Clean Old Sessions

Create a maintenance script to clean expired sessions:

```php
<?php
require_once __DIR__ . '/auth/CsvDataStore.php';
$config = require __DIR__ . '/auth/config.php';
$store = new CsvDataStore($config);

// Remove sessions older than their expiry
$store->cleanup('sessions', 'expires_at', date('Y-m-d H:i:s'));
?>
```

### Monitor Failed Login Attempts

Check the `auth/data/login_attempts.csv` file for patterns of failed logins.

## Next Steps

Now that authentication is set up, you can:

1. Integrate user-specific workout data
2. Add user profile pages
3. Add password reset functionality
4. Implement role-based access control
5. Add admin dashboard for user management
