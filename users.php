<?php
require_once __DIR__ . "/user_context.php";
$users = user_get_users();
$currentUser = user_get_current();
$messages = [];
$errors = [];
$activeUsers = array_filter($users, function ($user) {
    return empty($user['archivedAt']);
});
$archivedUsers = array_filter($users, function ($user) {
    return !empty($user['archivedAt']);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name = trim((string)($_POST['user_name'] ?? ''));
        if ($name === '') {
            $errors[] = 'User name is required.';
        } else {
            $id = str_replace('.', '', uniqid('user_', true));
            $users[$id] = [
                'id' => $id,
                'name' => $name,
                'createdAt' => date('c')
            ];
            user_save_json(user_users_file(), $users);
            user_set_current_id($id);
            $currentUser = user_get_current();
            $messages[] = 'User created and set as active.';
        }
    }

    if ($action === 'switch_user') {
        $targetId = $_POST['user_id'] ?? '';
        if (!user_set_current_id($targetId)) {
            $errors[] = 'Unable to switch users.';
        } else {
            $currentUser = user_get_current();
            $messages[] = 'Active user updated.';
        }
    }

    if ($action === 'archive_user') {
        $targetId = $_POST['user_id'] ?? '';
        if (count($activeUsers) <= 1) {
            $errors[] = 'You must keep at least one active user.';
        } elseif (!user_archive($targetId)) {
            $errors[] = 'Unable to archive user.';
        } else {
            if ($currentUser && ($currentUser['id'] ?? '') === $targetId) {
                $currentUser = user_get_current();
            }
            $messages[] = 'User archived.';
        }
        $users = user_get_users();
        $activeUsers = array_filter($users, function ($user) {
            return empty($user['archivedAt']);
        });
        $archivedUsers = array_filter($users, function ($user) {
            return !empty($user['archivedAt']);
        });
    }

    if ($action === 'delete_user') {
        $targetId = $_POST['user_id'] ?? '';
        if (count($activeUsers) <= 1) {
            $errors[] = 'You must keep at least one active user.';
        } elseif (!user_delete($targetId)) {
            $errors[] = 'Unable to delete user.';
        } else {
            $messages[] = 'User deleted.';
        }
        $users = user_get_users();
        $activeUsers = array_filter($users, function ($user) {
            return empty($user['archivedAt']);
        });
        $archivedUsers = array_filter($users, function ($user) {
            return !empty($user['archivedAt']);
        });
        $currentUser = user_get_current();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="body-hub" data-user-id="<?= htmlspecialchars($currentUser['id'] ?? '') ?>">
    <header class="topbar">
        <div class="brand">Workout</div>
        <nav class="topnav">
            <div class="user-switch">
                <label class="user-switch-label" for="userSwitch">Active</label>
                <select id="userSwitch" class="user-switch-select" data-user-switch>
                    <?php foreach ($activeUsers as $id => $user): ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= $currentUser && ($currentUser['id'] ?? '') === $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name'] ?? 'User') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            // Auto-detect base_url
            $base_url = '';
            if (file_exists(__DIR__ . '/auth/config.php')) {
                $config = require __DIR__ . '/auth/config.php';
                $base_url = $config['app']['base_url'] ?? '';
            }
            if (!$base_url) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? '';
                $script = $_SERVER['SCRIPT_NAME'] ?? '';
                $dir = rtrim(str_replace('users.php', '', $script), '/');
                $base_url = $dir ? $dir . '/' : '/';
            }
            ?>
            <nav class="topnav">
                    <a href="/Workout/index.php" title="See your overall progress and recent activity">Progress Hub</a>
                    <a href="/Workout/session.php" title="Start and log a new workout session">Session Mode</a>
                    <a href="/Workout/dashboard.php" title="View charts and stats for your workouts">Dashboard</a>
                    <a href="/Workout/goals.php" title="Set and track your fitness goals">Goals</a>
                    <a href="/Workout/compare.php" title="Compare progress between users">Compare</a>
                    <a href="/Workout/users.php" class="active" title="Manage user profiles">Users</a>
                    <a href="/Workout/routines.php" title="Create and edit workout routines">Routines</a>
                    <a href="/Workout/tracked_sets.php" title="Browse all logged sets">Tracked Sets</a>
        </nav>
    </header>

    <main class="page">
        <section class="hero compact">
            <div>
                <p class="eyebrow">Profiles</p>
                <h1>Users</h1>
                <p class="lede">Switch between profiles or add a new user. Results are isolated per user.</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-blue" href="compare.php">Compare Metrics</a>
            </div>
        </section>

        <?php foreach ($messages as $message): ?>
            <div class="notice success"><?= htmlspecialchars($message) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $error): ?>
            <div class="notice error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <section class="panel">
            <details class="collapsible" data-storage="users_list">
                <summary>
                    <div>
                        <h2>Active User</h2>
                        <p class="muted">Current profile: <?= htmlspecialchars($currentUser['name'] ?? 'Unknown') ?></p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <div class="table">
                        <div class="table-row table-head">
                            <div>Name</div>
                            <div>Created</div>
                            <div>Status</div>
                            <div>Action</div>
                        </div>
                        <?php foreach ($activeUsers as $id => $user): ?>
                            <div class="table-row">
                                <div><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                                <div><?= htmlspecialchars(date('d M Y', strtotime($user['createdAt'] ?? ''))) ?></div>
                                <div><?= $currentUser && ($currentUser['id'] ?? '') === $id ? 'Active' : '—' ?></div>
                                <div>
                                    <?php if (!$currentUser || ($currentUser['id'] ?? '') !== $id): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="switch_user">
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($id) ?>">
                                            <button type="submit" class="btn btn-blue btn-small">Switch</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge">Active</span>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Archive this user? You can restore later by moving their folder back manually.');">
                                        <input type="hidden" name="action" value="archive_user">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($id) ?>">
                                        <button type="submit" class="btn btn-blue btn-small">Archive</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($id) ?>">
                                        <button type="submit" class="btn btn-red btn-small">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        </section>

        <?php if (!empty($archivedUsers)): ?>
            <section class="panel">
                <details class="collapsible" data-storage="users_archived">
                    <summary>
                        <div>
                            <h2>Archived Users</h2>
                            <p class="muted">Archived profiles are removed from active views.</p>
                        </div>
                    </summary>
                    <div class="collapsible-body">
                        <div class="table">
                            <div class="table-row table-head">
                                <div>Name</div>
                                <div>Archived</div>
                                <div>Path</div>
                            </div>
                            <?php foreach ($archivedUsers as $user): ?>
                                <div class="table-row">
                                    <div><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                                    <div><?= htmlspecialchars(date('d M Y', strtotime($user['archivedAt'] ?? ''))) ?></div>
                                    <div><?= htmlspecialchars($user['archivedPath'] ?? '-') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            </section>
        <?php endif; ?>

        <section class="panel">
            <details class="collapsible" data-storage="users_add">
                <summary>
                    <div>
                        <h2>Add User</h2>
                        <p class="muted">Create a new profile with separate progress data.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <form method="POST" class="filter-grid">
                        <input type="hidden" name="action" value="add_user">
                        <div class="control-input">
                            <label for="user_name">Name</label>
                            <input type="text" id="user_name" name="user_name" placeholder="User name">
                        </div>
                        <div class="control-input">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-green">Create User</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>
    </main>

    <script src="collapsible.js"></script>
    <script src="user_tab.js"></script>
</body>
</html>
