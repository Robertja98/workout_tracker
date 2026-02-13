<?php
/**
 * Authentication System
 * 
 * Provides user registration, login, session management, and security features
 * Follows 2026 security best practices
 */

require_once __DIR__ . '/CsvDataStore.php';

class Auth {
    private $store;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->store = new CsvDataStore($config);
        $this->initSession();
    }
    
    /**
     * Initialize secure session
     */
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $security = $this->config['security'];
            
            ini_set('session.cookie_httponly', $security['session_cookie_httponly'] ? 1 : 0);
            ini_set('session.cookie_secure', $security['session_cookie_secure'] ? 1 : 0);
            ini_set('session.cookie_samesite', $security['session_cookie_samesite']);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.sid_length', 48);
            ini_set('session.sid_bits_per_character', 6);
            
            session_name($security['session_name']);
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * Register a new user
     */
    public function register($username, $email, $password) {
        // Validate input
        $validation = $this->validateRegistration($username, $email, $password);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        // Check if user already exists
        if ($this->userExists($username, $email)) {
            return ['success' => false, 'errors' => ['Username or email already exists']];
        }
        
        // Hash password
        $passwordHash = $this->hashPassword($password);
        
        // Generate verification token
        $verificationToken = $this->config['app']['require_email_verification'] 
            ? bin2hex(random_bytes(32)) 
            : null;
        
        try {
            // Insert user
            $userId = $this->store->insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'is_verified' => $verificationToken ? '0' : '1',
                'is_active' => '1',
                'verification_token' => $verificationToken ?? '',
                'reset_token' => '',
                'reset_token_expires' => '',
                'failed_login_attempts' => '0',
                'locked_until' => '',
                'last_login' => '',
            ]);
            
            // Log registration
            $this->logActivity($userId, 'user_registered', json_encode([
                'username' => $username,
                'email' => $email,
            ]));
            
            return [
                'success' => true,
                'user_id' => $userId,
                'requires_verification' => $verificationToken !== null,
                'verification_token' => $verificationToken,
            ];
            
        } catch (Exception $e) {
            error_log('Registration failed: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed']];
        }
    }
    
    /**
     * Authenticate user login
     */
    public function login($usernameOrEmail, $password, $rememberMe = false) {
        $ip = $this->getIpAddress();
        
        // Check rate limiting
        if ($this->isRateLimited($usernameOrEmail, $ip)) {
            return [
                'success' => false,
                'error' => 'Too many login attempts. Please try again later.',
            ];
        }
        
        // Find user
        $user = $this->findUser($usernameOrEmail);
        
        // Log attempt
        $this->logLoginAttempt($usernameOrEmail, $ip, false);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check if account is locked
        if ($this->isAccountLocked($user)) {
            return ['success' => false, 'error' => 'Account is temporarily locked'];
        }
        
        // Verify password
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            $this->incrementFailedAttempts($user['id']);
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check if email verification is required
        if ($this->config['app']['require_email_verification'] && !$user['is_verified']) {
            return ['success' => false, 'error' => 'Please verify your email address'];
        }
        
        // Check if account is active
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Account is disabled'];
        }
        
        // Successful login
        $this->logLoginAttempt($usernameOrEmail, $ip, true);
        $this->resetFailedAttempts($user['id']);
        $this->updateLastLogin($user['id']);
        
        // Create session
        $sessionToken = $this->createSession($user['id'], $rememberMe);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['ip_address'] = $ip;
        
        // Log activity
        $this->logActivity($user['id'], 'user_login', 'Successful login');
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
            ],
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            // Delete session from storage
            $this->store->delete('sessions', ['session_token' => $_SESSION['session_token']]);
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'user_logout', 'User logged out');
        }
        
        // Clear session
        $_SESSION = [];
        session_destroy();
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        return ['success' => true];
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Verify session in storage
        $session = $this->store->fetchOne('sessions', [
            'session_token' => $_SESSION['session_token'],
            'user_id' => (string)$_SESSION['user_id']
        ]);
        
        if (!$session) {
            return false;
        }
        
        // Check if session is expired
        if (isset($session['expires_at']) && strtotime($session['expires_at']) <= time()) {
            return false;
        }
        
        // Check IP address match (optional, can be disabled for mobile users)
        // if ($session['ip_address'] !== $this->getIpAddress()) {
        //     return false;
        // }
        
        return true;
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $user = $this->store->fetchOne('users', ['id' => (string)$_SESSION['user_id']]);
        
        if ($user) {
            // Return only safe fields
            $user = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'created_at' => $user['created_at'] ?? '',
                'last_login' => $user['last_login'] ?? '',
            ];
        }
        
        return $user ?: null;
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        $user = $this->store->fetchOne('users', ['id' => (string)$userId]);
        
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        if (!$this->verifyPassword($oldPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        $validation = $this->validatePassword($newPassword);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        $newHash = $this->hashPassword($newPassword);
        $this->store->update('users', ['password_hash' => $newHash], ['id' => (string)$userId]);
        
        $this->logActivity($userId, 'password_changed', 'User changed password');
        
        return ['success' => true];
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes($this->config['security']['csrf_token_length']));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // ==================== Private Helper Methods ====================
    
    private function hashPassword($password) {
        return password_hash(
            $password,
            $this->config['security']['password_algo'],
            $this->config['security']['password_options']
        );
    }
    
    private function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    private function validateRegistration($username, $email, $password) {
        $errors = [];
        
        // Validate username
        $minLen = $this->config['validation']['username_min_length'];
        $maxLen = $this->config['validation']['username_max_length'];
        
        if (strlen($username) < $minLen || strlen($username) > $maxLen) {
            $errors[] = "Username must be between $minLen and $maxLen characters";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        // Validate password
        $passwordValidation = $this->validatePassword($password);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    private function validatePassword($password) {
        $errors = [];
        $rules = $this->config['validation'];
        
        if (strlen($password) < $rules['password_min_length']) {
            $errors[] = 'Password must be at least ' . $rules['password_min_length'] . ' characters';
        }
        
        if ($rules['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if ($rules['password_require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if ($rules['password_require_number'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if ($rules['password_require_special'] && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    private function userExists($username, $email) {
        $users = $this->store->fetchAll('users');
        foreach ($users as $user) {
            if ($user['username'] === $username || $user['email'] === $email) {
                return true;
            }
        }
        return false;
    }
    
    private function findUser($usernameOrEmail) {
        $users = $this->store->fetchAll('users');
        foreach ($users as $user) {
            if ($user['username'] === $usernameOrEmail || $user['email'] === $usernameOrEmail) {
                return $user;
            }
        }
        return null;
    }
    
    private function isRateLimited($usernameOrEmail, $ip) {
        $window = $this->config['security']['rate_limit_window'];
        $maxAttempts = $this->config['security']['max_login_attempts'];
        $cutoff = date('Y-m-d H:i:s', time() - $window);
        
        $attempts = $this->store->filter('login_attempts', function($attempt) use ($usernameOrEmail, $ip, $cutoff) {
            return ($attempt['username_or_email'] === $usernameOrEmail || $attempt['ip_address'] === $ip)
                && $attempt['success'] === '0'
                && $attempt['attempted_at'] > $cutoff;
        });
        
        return count($attempts) >= $maxAttempts;
    }
    
    private function isAccountLocked($user) {
        if (empty($user['locked_until'])) {
            return false;
        }
        
        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil > time()) {
            return true;
        }
        
        // Unlock account if lock period has passed
        $this->store->update('users', ['locked_until' => ''], ['id' => $user['id']]);
        return false;
    }
    
    private function incrementFailedAttempts($userId) {
        $user = $this->store->fetchOne('users', ['id' => (string)$userId]);
        $attempts = (int)($user['failed_login_attempts'] ?? 0) + 1;
        
        $updateData = ['failed_login_attempts' => (string)$attempts];
        
        // Lock account if max attempts reached
        if ($attempts >= $this->config['security']['max_login_attempts']) {
            $lockDuration = $this->config['security']['lockout_duration'];
            $updateData['locked_until'] = date('Y-m-d H:i:s', time() + $lockDuration);
        }
        
        $this->store->update('users', $updateData, ['id' => (string)$userId]);
    }
    
    private function resetFailedAttempts($userId) {
        $this->store->update('users', ['failed_login_attempts' => '0'], ['id' => (string)$userId]);
    }
    
    private function updateLastLogin($userId) {
        $this->store->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => (string)$userId]);
    }
    
    private function createSession($userId, $rememberMe = false) {
        $sessionToken = bin2hex(random_bytes(32));
        $lifetime = $rememberMe ? 30 * 24 * 3600 : $this->config['security']['session_lifetime'];
        
        $this->store->insert('sessions', [
            'user_id' => (string)$userId,
            'session_token' => $sessionToken,
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expires_at' => date('Y-m-d H:i:s', time() + $lifetime),
            'last_activity' => date('Y-m-d H:i:s'),
        ]);
        
        return $sessionToken;
    }
    
    private function logLoginAttempt($usernameOrEmail, $ip, $success) {
        $this->store->insert('login_attempts', [
            'username_or_email' => $usernameOrEmail,
            'ip_address' => $ip,
            'success' => $success ? '1' : '0',
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }
    
    private function logActivity($userId, $actionType, $actionDetails) {
        if (!$this->config['logging']['enabled']) {
            return;
        }
        
        $this->store->insert('activity_log', [
            'user_id' => (string)$userId,
            'action_type' => $actionType,
            'action_details' => $actionDetails,
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }
    
    private function getIpAddress() {
        // Handle proxy headers safely to prevent IP spoofing
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain multiple IPs (client, proxy1, proxy2)
            // Take the first IP (the client's IP) and validate it
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $clientIp = $ips[0];
            if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                return $clientIp;
            }
        }
        
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($userId) {
        $user = $this->store->fetchOne('users', ['id' => (string)$userId]);
        
        if (!$user) {
            return null;
        }
        
        $loginAttempts = $this->store->filter('login_attempts', function($attempt) use ($user) {
            return $attempt['username_or_email'] === $user['username'] && $attempt['success'] === '1';
        });
        
        $activities = $this->store->fetchAll('activity_log', ['user_id' => (string)$userId]);
        
        return [
            'member_since' => $user['created_at'] ?? '',
            'last_login' => $user['last_login'] ?? '',
            'total_logins' => count($loginAttempts),
            'total_activities' => count($activities),
        ];
    }
}
