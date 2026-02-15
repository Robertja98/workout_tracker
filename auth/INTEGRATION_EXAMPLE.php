<?php
/**
 * Example: Integration with Existing Workout User System
 * 
 * This file demonstrates how to link the authentication system
 * with the existing workout tracker's user context system.
 */

require_once __DIR__ . '/auth/Auth.php';

// Load auth config
$authConfig = require __DIR__ . '/auth/config.php';
$auth = new Auth($authConfig);

// Check if user is authenticated with the auth system
if (!$auth->isAuthenticated()) {
    $baseUrl = rtrim($authConfig['app']['base_url'], '/');
    header('Location: ' . $baseUrl . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get the authenticated user from auth system
$authUser = $auth->getCurrentUser();

/**
 * OPTION 1: Map auth users to workout users by username
 * 
 * If your workout users.json uses the same usernames as the auth system,
 * you can map them directly:
 */

// Load workout users
$workoutUsersFile = __DIR__ . '/data/users.json';
if (file_exists($workoutUsersFile)) {
    $workoutUsers = json_decode(file_get_contents($workoutUsersFile), true);
    
    // Find matching workout user by username
    $workoutUserId = null;
    foreach ($workoutUsers as $id => $user) {
        if ($user['username'] === $authUser['username'] || $user['email'] === $authUser['email']) {
            $workoutUserId = $id;
            break;
        }
    }
    
    if (!$workoutUserId) {
        // Create workout user if doesn't exist
        $workoutUserId = 'user_' . $authUser['id'];
        $workoutUsers[$workoutUserId] = [
            'id' => $workoutUserId,
            'name' => $authUser['username'],
            'username' => $authUser['username'],
            'email' => $authUser['email'],
            'auth_user_id' => $authUser['id'], // Link to auth system
            'createdAt' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($workoutUsersFile, json_encode($workoutUsers, JSON_PRETTY_PRINT));
    }
    
    // Set the workout user as active
    $_SESSION['workout_user_id'] = $workoutUserId;
}

/**
 * OPTION 2: Use auth user ID directly in workout data
 * 
 * Modify your workout data files to use auth user IDs:
 */

function getUserWorkoutData($authUserId, $filename) {
    $allDataFile = __DIR__ . '/data/' . $filename;
    $userDataFile = __DIR__ . '/data/users/' . $authUserId . '/' . $filename;
    
    // Use per-user data file if it exists
    if (file_exists($userDataFile)) {
        return json_decode(file_get_contents($userDataFile), true) ?: [];
    }
    
    // Otherwise use shared data (for backward compatibility)
    if (file_exists($allDataFile)) {
        return json_decode(file_get_contents($allDataFile), true) ?: [];
    }
    
    return [];
}

function saveUserWorkoutData($authUserId, $filename, $data) {
    $userDir = __DIR__ . '/data/users/' . $authUserId;
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }
    
    $userDataFile = $userDir . '/' . $filename;
    return file_put_contents($userDataFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Example usage:
$currentAuthUserId = $authUser['id'];
$userRoutines = getUserWorkoutData($currentAuthUserId, 'routines.json');
$userProgress = getUserWorkoutData($currentAuthUserId, 'progress.json');
$userSessions = getUserWorkoutData($currentAuthUserId, 'sessions.json');

/**
 * OPTION 3: CSV-based user data (recommended for this CSV auth system)
 * 
 * For consistency with the CSV auth system, store workout data in CSV too:
 */

// Example: User-specific workout data in CSV
function getUserWorkoutDataCsv($authUserId, $type) {
    $filepath = __DIR__ . '/data/workout_' . $type . '.csv';
    
    if (!file_exists($filepath)) {
        return [];
    }
    
    $userData = [];
    $fp = fopen($filepath, 'r');
    $headers = fgetcsv($fp);
    
    while (($row = fgetcsv($fp)) !== false) {
        $record = array_combine($headers, $row);
        if ($record['user_id'] === (string)$authUserId) {
            $userData[] = $record;
        }
    }
    
    fclose($fp);
    return $userData;
}

function saveWorkoutDataCsv($authUserId, $type, $data) {
    $filepath = __DIR__ . '/data/workout_' . $type . '.csv';
    $data['user_id'] = (string)$authUserId;
    $data['created_at'] = date('Y-m-d H:i:s');
    
    // Read existing data
    $allData = [];
    $headers = array_keys($data);
    
    if (file_exists($filepath)) {
        $fp = fopen($filepath, 'r');
        $headers = fgetcsv($fp);
        while (($row = fgetcsv($fp)) !== false) {
            $allData[] = array_combine($headers, $row);
        }
        fclose($fp);
    }
    
    // Add new record
    $allData[] = $data;
    
    // Write all data
    $fp = fopen($filepath, 'w');
    fputcsv($fp, $headers);
    foreach ($allData as $record) {
        $row = [];
        foreach ($headers as $header) {
            $row[] = $record[$header] ?? '';
        }
        fputcsv($fp, $row);
    }
    fclose($fp);
    
    return true;
}

// Example usage:
$currentAuthUserId = $authUser['id'];
$userRoutines = getUserWorkoutDataCsv($currentAuthUserId, 'routines');
$userProgress = getUserWorkoutDataCsv($currentAuthUserId, 'progress');

/**
 * Example: Helper to migrate user from workout users to auth system
 */
function migrateWorkoutUserToAuth($workoutUser, $temporaryPassword = null) {
    global $auth;
    
    $password = $temporaryPassword ?: bin2hex(random_bytes(8));
    
    $result = $auth->register(
        $workoutUser['username'] ?? $workoutUser['name'],
        $workoutUser['email'] ?? ($workoutUser['username'] . '@example.com'),
        $password
    );
    
    if ($result['success']) {
        return [
            'success' => true,
            'auth_user_id' => $result['user_id'],
            'temporary_password' => $password,
        ];
    }
    
    return $result;
}

?>
