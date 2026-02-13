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

### 3. Test the Authentication System

1. Visit: http://localhost/Workout/auth/register.php
2. Create a test account
3. Login at: http://localhost/Workout/auth/login.php

### 4. Protect Your Pages

To require authentication for a page, add this at the top:

```php
<?php
require_once __DIR__ . '/auth/middleware.php';
// Rest of your page code
?>
```

## Security Checklist

- [ ] Set proper file permissions on auth/data/ directory (755)
- [ ] Add auth/data/ to .gitignore to protect user data
- [ ] Set `session_cookie_secure` to `true` when using HTTPS
- [ ] Update `admin_email` in config
- [ ] Enable HTTPS in production
- [ ] Uncomment HSTS header in `security_headers.php` when using HTTPS
- [ ] Review and adjust Content Security Policy as needed
- [ ] Set up regular backups of auth/data/ directory
- [ ] Monitor `activity_log.csv` for suspicious activity

## Production Considerations

### Enable HTTPS

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
