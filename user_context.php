// Serve motivation reflection as JSON for AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_motivation_reflection') {
    header('Content-Type: application/json');
    $reflection = user_load_data('motivation_reflection.json', []);
    echo json_encode($reflection);
    exit;
}
<?php
function user_load_json($file, $default = []) {
    if (!file_exists($file)) {
        return $default;
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function user_save_json($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function user_users_file() {
    return __DIR__ . "/data/users.json";
}

function user_users_dir() {
    return __DIR__ . "/data/users";
}

function user_archived_dir() {
    return __DIR__ . "/data/users_archived";
}

function user_data_path($userId, $filename) {
    return user_users_dir() . "/" . $userId . "/" . $filename;
}

function user_is_archived($user) {
    return !empty($user['archivedAt']);
}

function user_delete_dir($dir) {
    if (!is_dir($dir)) {
        return true;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    return rmdir($dir);
}

function user_archive($userId) {
    $users = user_load_json(user_users_file(), []);
    if (!isset($users[$userId])) {
        return false;
    }
    $archiveDir = user_archived_dir();
    if (!is_dir($archiveDir)) {
        mkdir($archiveDir, 0777, true);
    }
    $sourceDir = user_users_dir() . "/" . $userId;
    $archivePath = $archiveDir . "/" . $userId . "_" . date('Ymd_His');
    if (is_dir($sourceDir)) {
        rename($sourceDir, $archivePath);
    }
    $users[$userId]['archivedAt'] = date('c');
    $users[$userId]['archivedPath'] = $archivePath;
    user_save_json(user_users_file(), $users);
    return true;
}

function user_delete($userId) {
    $users = user_load_json(user_users_file(), []);
    if (!isset($users[$userId])) {
        return false;
    }
    $sourceDir = user_users_dir() . "/" . $userId;
    user_delete_dir($sourceDir);
    unset($users[$userId]);
    user_save_json(user_users_file(), $users);
    return true;
}

function user_migrate_legacy($userId) {
    $legacyFiles = [
        'progress.json',
        'sessions.json',
        'goals.json',
        'goals_checkins.json',
        'goals_history.json'
    ];

    foreach ($legacyFiles as $fileName) {
        $legacyPath = __DIR__ . "/data/" . $fileName;
        $userPath = user_data_path($userId, $fileName);
        if (!file_exists($legacyPath) || file_exists($userPath)) {
            continue;
        }
        $data = user_load_json($legacyPath, $fileName === 'goals_checkins.json' || $fileName === 'goals_history.json' ? [] : []);
        user_save_json($userPath, $data);
    }
}

function user_bootstrap() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $usersFile = user_users_file();
    $users = user_load_json($usersFile, []);

    $activeUsers = array_filter($users, function ($user) {
        return !user_is_archived($user);
    });

    if (empty($users) || empty($activeUsers)) {
        $id = str_replace('.', '', uniqid('user_', true));
        $users[$id] = [
            'id' => $id,
            'name' => 'Default',
            'createdAt' => date('c')
        ];
        user_save_json($usersFile, $users);
        user_migrate_legacy($id);
    }

    if (!isset($_SESSION['current_user_id']) || !isset($users[$_SESSION['current_user_id']]) || user_is_archived($users[$_SESSION['current_user_id']])) {
        $activeUsers = array_filter($users, function ($user) {
            return !user_is_archived($user);
        });
        $firstId = array_key_first($activeUsers);
        $_SESSION['current_user_id'] = $firstId;
    }

    return $users;
}

function user_get_users() {
    return user_bootstrap();
}

function user_get_current_id() {
    return user_get_effective_id();
}

function user_get_current() {
    $users = user_bootstrap();
    $currentId = user_get_effective_id();
    return $currentId && isset($users[$currentId]) ? $users[$currentId] : null;
}

function user_set_current_id($userId) {
    $users = user_bootstrap();
    if (isset($users[$userId]) && !user_is_archived($users[$userId])) {
        $_SESSION['current_user_id'] = $userId;
        return true;
    }
    return false;
}

function user_load_data($filename, $default = []) {
    $userId = user_get_effective_id();
    if (!$userId) {
        return $default;
    }
    return user_load_json(user_data_path($userId, $filename), $default);
}

function user_save_data($filename, $data, $userIdOverride = null) {
    $userId = $userIdOverride ?: user_get_effective_id();
    if (!$userId) {
        return false;
    }
    user_save_json(user_data_path($userId, $filename), $data);
    return true;
}

function user_get_request_user_id() {
    $requestId = $_POST['user_id'] ?? $_GET['user'] ?? null;
    if (!$requestId) {
        return null;
    }
    return trim((string)$requestId);
}

function user_get_effective_id() {
    $users = user_bootstrap();
    $requestId = user_get_request_user_id();
    if ($requestId && isset($users[$requestId]) && !user_is_archived($users[$requestId])) {
        return $requestId;
    }
    return $_SESSION['current_user_id'] ?? null;
}

function user_load_data_for($userId, $filename, $default = []) {
    $users = user_bootstrap();
    if (!$userId || !isset($users[$userId]) || user_is_archived($users[$userId])) {
        return $default;
    }
    return user_load_json(user_data_path($userId, $filename), $default);
}

function user_save_data_for($userId, $filename, $data) {
    $users = user_bootstrap();
    if (!$userId || !isset($users[$userId]) || user_is_archived($users[$userId])) {
        return false;
    }
    user_save_json(user_data_path($userId, $filename), $data);
    return true;
}
?>
