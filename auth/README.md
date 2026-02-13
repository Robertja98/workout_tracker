# Modular Authentication System

A secure, modern, and reusable authentication system for PHP applications. Built with 2026 security best practices.

**No database required!** Uses CSV files for easy portability and setup.

## Features

✅ **User Registration & Login** - Secure user account creation and authentication  
✅ **CSV-Based Storage** - No MySQL/database required, perfect for simple deployments  
✅ **Password Security** - Argon2ID hashing with configurable parameters  
✅ **Session Management** - Secure session handling with CSV storage  
✅ **Rate Limiting** - Protection against brute force attacks  
✅ **Activity Logging** - Track user actions and security events  
✅ **CSRF Protection** - Built-in cross-site request forgery prevention  
✅ **Account Lockout** - Temporary locks after failed login attempts  
✅ **Security Headers** - Modern HTTP security headers  
✅ **Modular Design** - Easy to integrate into existing projects  

## Security Features (2026 Standards)

- **Argon2ID** password hashing (memory-hard, resistant to GPU attacks)
- **Prepared statements** for SQL injection prevention
- **CSRF tokens** on all forms
- **Rate limiting** on login attempts
- **Account lockout** after failed attempts
- **Secure session management** with httpOnly and SameSite cookies
- **Activity logging** for audit trails
- **Security headers** (CSP, X-Frame-Options, etc.)
- **Input validation** and sanitization
- **IP-based rate limiting**

## Quick Start

### 1. Copy the `auth` folder to your project

```bash
cp -r auth /path/to/your/project/
```

### 2. Set up the database

```sql
CREATE DATABASE your_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
mysql -u root -p your_db_name < auth/schema.sql
```

### 3. Configure the system

```bash
cd auth
cp config.example.php config.php
# Edit config.php with your database credentials
```

```bash
cd auth
cp config.example.php config.php
# Edit config.php to customize settings
```

### 4. Protect your pages

Add this to the top of any page that requires authentication:

```php
<?php
require_once __DIR__ . '/auth/middleware.php';
// Your protected page code here
?>
```

### 5. Add login/logout links

```php
<!-- Login link -->
<a href="/auth/login.php">Login</a>

<!-- Logout link -->
<a href="/auth/logout.php">Logout</a>

<!-- Registration link -->
<a href="/auth/register.php">Register</a>

<!-- Show current user -->
<?php if (auth_check()): ?>
    Welcome, <?= htmlspecialchars(auth_current_user()['username']) ?>
<?php endif; ?>
```

## File Structure

```
auth/
├── Auth.php                  # Main authentication class
├── CsvDataStore.php          # CSV data handler
├── config.php                # Configuration (DO NOT COMMIT)
├── config.example.php        # Configuration template
├── middleware.php            # Authentication middleware
├── security_headers.php      # HTTP security headers
├── login.php                 # Login page
├── register.php              # Registration page
├── logout.php                # Logout handler
├── data/                     # CSV data files (auto-created)
│   ├── users.csv
│   ├── sessions.csv
│   ├── login_attempts.csv
│   └── activity_log.csv
├── SETUP.md                  # Setup instructions
└── README.md                 # This file
```

## Usage Examples

### Check if user is authenticated

```php
<?php
require_once __DIR__ . '/auth/middleware.php';

if (auth_check()) {
    $user = auth_current_user();
    echo "Welcome, " . htmlspecialchars($user['username']);
}
?>
```

### Manual authentication check (without redirect)

```php
<?php
require_once __DIR__ . '/auth/Auth.php';

$config = require __DIR__ . '/auth/config.php';
$auth = new Auth($config);

if ($auth->isAuthenticated()) {
    $user = $auth->getCurrentUser();
    // User is logged in
} else {
    // User is not logged in
}
?>
```

### Register a user programmatically

```php
<?php
require_once __DIR__ . '/auth/Auth.php';

$config = require __DIR__ . '/auth/config.php';
$auth = new Auth($config);

$result = $auth->register('john_doe', 'john@example.com', 'SecurePass123!');

if ($result['success']) {
    echo "User registered successfully!";
} else {
    print_r($result['errors']);
}
?>
```

### Login programmatically

```php
<?php
require_once __DIR__ . '/auth/Auth.php';

$config = require __DIR__ . '/auth/config.php';
$auth = new Auth($config);

$result = $auth->login('john_doe', 'SecurePass123!');

if ($result['success']) {
    // Redirect to dashboard
    header('Location: /dashboard.php');
} else {
    echo $result['error'];
}
?>
```

### Get user statistics

```php
<?php
$stats = auth()->getUserStats($userId);

echo "Member since: " . $stats['member_since'];
echo "Last login: " . $stats['last_login'];
echo "Total logins: " . $stats['total_logins'];
?>
```

## Configuration Options

Edit `auth/config.php` to customize:

```php
return [
    'storage' => [
        'data_dir' => __DIR__ . '/data',  // Where CSV files are stored
    ],
    
    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'session_lifetime' => 86400, // 24 hours
    ],
    
    'validation' => [
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_number' => true,
        'password_require_special' => true,
    ],
];
```

## Database Tables

The system creates these tables:
 Files

The system creates these CSV files automatically in `auth/data/`:

- **users.csv** - User accounts and credentials
- **sessions.csv** - Active sessions
- **login_attempts.csv** - Login attempt tracking for rate limiting
- **activity_log.csv** - User activity audit trail

**Important:** Add `auth/data/` to your `.gitignore` to protect user data!
### 1. Preserve existing sessions

If you have existing session handling, you may need to merge it with the auth system.

### 2. Link to existing user data
you can read the user ID from the auth system and use it to filter your existing data.

### 3. Migrate existing users

If you have existing users in JSON/CSV file
If you have existing users, create them in the auth system:

```php
foreach ($existingUsers as $user) {
    $auth->register($user['username'], $user['email'], $temporaryPassword);
    // Send password reset email
}
```

## Reusing in Other Projects

This auth system is designed to be portable:

1. **Copy the `auth/` folder** to any PHP project
2. **CSV files are auto-created** on first use
3. **Update `config.php`** with project-specific settings
4. **Add middleware** to protected pages
5. **Customize the UI** by editing login/register pages

The system works independently and doesn't interfere with existing code.

## Advantages of CSV Storage

- ✅ **No database required** - Perfect for shared hosting
- ✅ **Easy to backup** - Just copy the data folder
- ✅ **Portable** - Move files between servers easily
- ✅ **Simple** - No SQL knowledge needed
- ✅ **Debuggable** - Open CSV files in Excel/text editor
- ✅ **Good for small to medium sites** - Thousands of users supported

## Performance Considerations

CSV storage works great for:
- Up to ~10,000 users
- Low to medium traffic sites
- Development and testing
- Simple deployments without database access

For very large scale (100k+ users), consider migrating to a database version.

## API Methods

### Auth Class

```php
// Registration
$auth->register($username, $email, $password)

// Login
$auth->login($usernameOrEmail, $password, $rememberMe)

// Logout
$auth->logout()

// Check authentication
$auth->isAuthenticated()

// Get current user
$auth->getCurrentUser()

// Change password
$auth->changePassword($userId, $oldPassword, $newPassword)

// CSRF protection
$auth->generateCsrfToken()
$auth->verifyCsrfToken($token)

// User stats
$auth->getUserStats($userId)
```

## Security Best Practices

When deploying to production:

1. ✅ Enable HTTPS and set `session_cookie_secure = true`
2. ✅ Use strong database passwords
3. ✅ Keep `config.php` out of version control
4. ✅ Enable security headers
5. ✅ Monitor activity logs regularly
6. ✅ Set up database backups
7. ✅ Use environment variables for sensitive config
8. ✅ Implement rate limiting at the web server level
9. ✅ Keep PHP and dependencies updated
10. ✅ Use a Web Application Firewall (WAF)

## Customization

### Customize login/register pages

Edit `auth/login.php` and `auth/register.php` to match your site's design.

### Add password reset

Create `auth/forgot_password.php` and `auth/reset_password.php`.

### Add email verification

1. Set `require_email_verification = true` in config
2. Create verification endpoint
3. Send verification emails in registration

### Add two-factor authentication

1. Install a 2FA library (e.g., `composer require sonata-project/google-authenticator`)
2. Add 2FA setup and verification logic
3. Update login flow to check 2FA

## Troubleshooting

### "Configuration file not found"

Create `auth/config.php` from `auth/config.example.php`.

### "Permission denied" errors

Make sure the web server can write to the `auth/data/` directory:

```bash
chmod 755 auth/data/
```

### "Too many login attempts"

Wait 15 minutes or manually edit `auth/data/login_attempts.csv` to remove old attempts.

### Session issues

Make sure session cookies are enabled and check cookie security settings.

## License

This authentication system is provided as-is for use in your projects. Modify as needed.

## Support

For issues specific to this auth system, check:

1. Database connection and credentials
2. PHP version (requires PHP 7.4+)
3. Session configuration
4. File permissions

## Contributing

To improve this auth system:

1. Add new features (2FA, OAuth, etc.)
2. Improve security measures
3. Add more comprehensive logging
4. Create additional helper functions

---

**Built with security and reusability in mind for 2026 and beyond.**
