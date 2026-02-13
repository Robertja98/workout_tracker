<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Workout Tracker</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .auth-container {
            max-width: 450px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .auth-container h1 {
            margin-bottom: 10px;
            color: #333;
        }
        .auth-container .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .btn-auth {
            width: 100%;
            padding: 14px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-auth:hover {
            background: #45a049;
        }
        .btn-auth:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .error-list {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .error-list ul {
            margin: 0;
            padding-left: 20px;
            color: #c33;
        }
        .success-msg {
            background: #efe;
            border: 1px solid #cfc;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #363;
        }
        .auth-footer {
            margin-top: 20px;
            text-align: center;
            color: #666;
        }
        .auth-footer a {
            color: #4CAF50;
            text-decoration: none;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Create Account</h1>
        <p class="subtitle">Join Workout Tracker to start tracking your progress</p>
        
        <?php
        require_once __DIR__ . '/Auth.php';
        
        // Load config
        $configFile = __DIR__ . '/config.php';
        if (!file_exists($configFile)) {
            die('Configuration file not found. Please create auth/config.php from auth/config.example.php');
        }
        $config = require $configFile;
        
        $auth = new Auth($config);
        $errors = [];
        $success = false;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $csrfToken = $_POST['csrf_token'] ?? '';
            
            // Verify CSRF token
            if (!$auth->verifyCsrfToken($csrfToken)) {
                $errors[] = 'Invalid security token. Please try again.';
            } elseif ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match';
            } else {
                $result = $auth->register($username, $email, $password);
                
                if ($result['success']) {
                    $success = true;
                    if ($result['requires_verification']) {
                        $successMessage = 'Registration successful! Please check your email to verify your account.';
                    } else {
                        $successMessage = 'Registration successful! You can now <a href="login.php">login</a>.';
                    }
                } else {
                    $errors = $result['errors'] ?? ['Registration failed'];
                }
            }
        }
        
        $csrfToken = $auth->generateCsrfToken();
        ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-list">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-msg">
                <?= $successMessage ?>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        minlength="<?= $config['validation']['username_min_length'] ?>"
                        maxlength="<?= $config['validation']['username_max_length'] ?>"
                        pattern="[a-zA-Z0-9_]+"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        minlength="<?= $config['validation']['password_min_length'] ?>"
                    >
                    <div class="password-requirements">
                        Must be at least <?= $config['validation']['password_min_length'] ?> characters
                        <?php if ($config['validation']['password_require_uppercase']): ?>, include uppercase<?php endif; ?>
                        <?php if ($config['validation']['password_require_lowercase']): ?>, lowercase<?php endif; ?>
                        <?php if ($config['validation']['password_require_number']): ?>, number<?php endif; ?>
                        <?php if ($config['validation']['password_require_special']): ?>, and special character<?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                    >
                </div>
                
                <button type="submit" class="btn-auth">Create Account</button>
            </form>
        <?php endif; ?>
        
        <div class="auth-footer">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
