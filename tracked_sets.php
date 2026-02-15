
<?php
require_once __DIR__ . "/user_context.php";
user_bootstrap();
$currentUser = user_get_current();
$activeUsers = array_filter(user_get_users(), function ($user) {
    return empty($user['archivedAt']);
});

$progress = user_load_data('progress.json', []);

// Search filter
$search = trim(filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?? '');

// Filtered results
if ($search !== '') {
    $progress = array_filter($progress, function ($p) use ($search) {
        return stripos($p['exercise'] ?? '', $search) !== false ||
               stripos($p['effort'] ?? '', $search) !== false;
    });
}

// Pagination setup
$itemsPerPage = 10;
$totalItems = count($progress);
$totalPages = ceil($totalItems / $itemsPerPage);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$startIndex = ($page - 1) * $itemsPerPage;
$paginatedProgress = array_slice($progress, $startIndex, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tracked Sets</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="body-hub" data-user-id="<?= htmlspecialchars(user_get_current_id() ?? '') ?>">
    <header class="topbar">
        <div class="brand">Workout</div>
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
            $dir = rtrim(str_replace('tracked_sets.php', '', $script), '/');
            $base_url = $dir ? $dir . '/' : '/';
        }
        ?>
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
            <a href="<?= $base_url ?>index.php" title="See your overall progress and recent activity">Progress Hub</a>
            <a href="<?= $base_url ?>session.php" title="Start and log a new workout session">Session Mode</a>
            <a href="<?= $base_url ?>dashboard.php" title="View charts and stats for your workouts">Dashboard</a>
            <a href="<?= $base_url ?>goals.php" title="Set and track your fitness goals">Goals</a>
            <a href="<?= $base_url ?>compare.php" title="Compare progress between users">Compare</a>
            <a href="<?= $base_url ?>users.php" title="Manage user profiles">Users</a>
            <a href="<?= $base_url ?>routines.php" title="Create and edit workout routines">Routines</a>
            <a href="<?= $base_url ?>tracked_sets.php" class="active" title="Browse all logged sets">Tracked Sets</a>
        </nav>
    </header>

    <main class="page">
        <section class="hero compact">
            <div>
                <p class="eyebrow">History</p>
                <h1>Tracked Sets</h1>
                <p class="lede">Search and review every recorded set.</p>
            </div>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="tracked_sets_search">
                <summary>
                    <div>
                        <h2>Search</h2>
                        <p class="muted">Search and paginate every tracked set.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <form method="GET" class="search-bar">
                        <input type="text" name="search" placeholder="Search by exercise or effort..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-blue">Search</button>
                    </form>

                    <?php if (empty($progress)): ?>
                        <p class="muted">No tracked sets available yet.</p>
                    <?php else: ?>
                        <div class="table">
                            <div class="table-row table-head">
                                <div>Exercise</div>
                                <div>Set</div>
                                <div>Weight</div>
                                <div>Reps</div>
                                <div>Duration</div>
                                <div>Effort</div>
                                <div>Time</div>
                            </div>
                            <?php foreach ($paginatedProgress as $p): ?>
                                <?php
                                    $timeValue = $p['startTime'] ?? $p['time'] ?? null;
                                    $weightValue = $p['weight'] ?? '-';
                                    $weightLabel = is_numeric($weightValue) ? $weightValue . ' lbs' : $weightValue;
                                ?>
                                <div class="table-row">
                                    <div><?= htmlspecialchars($p['exercise'] ?? '-') ?></div>
                                    <div><?= $p['set'] ?? '-' ?></div>
                                    <div><?= $weightLabel ?></div>
                                    <div><?= $p['reps'] ?? '-' ?></div>
                                    <div><?= $p['duration'] ?? '-' ?></div>
                                    <div><?= $p['effort'] ?? '-' ?></div>
                                    <div><?= $timeValue ? date('d M Y H:i', strtotime($timeValue)) : '-' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i === $page ? 'active' : '' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>
    </main>

    <script src="collapsible.js"></script>
    <script src="user_tab.js"></script>
</body>
</html>
