
<?php
require_once __DIR__ . "/auth/middleware.php";
require_once __DIR__ . "/user_context.php";
user_bootstrap();
$currentUser = user_get_current();

$progress = user_load_data('progress.json', []);
$sessions = user_load_data('sessions.json', []);

function entry_timestamp($entry) {
    $time = $entry['startTime'] ?? $entry['time'] ?? null;
    return $time ? strtotime($time) : 0;
}

$totalSets = count($progress);
$totalVolume = 0;
foreach ($progress as $entry) {
    $weight = $entry['weight'] ?? null;
    $reps = $entry['reps'] ?? null;
    if (is_numeric($weight) && is_numeric($reps)) {
        $totalVolume += ((float)$weight) * ((int)$reps);
    }
}

usort($sessions, function ($a, $b) {
    $timeA = strtotime($a['endTime'] ?? $a['startTime'] ?? '') ?: 0;
    $timeB = strtotime($b['endTime'] ?? $b['startTime'] ?? '') ?: 0;
    return $timeB <=> $timeA;
});

usort($progress, function ($a, $b) {
    return entry_timestamp($b) <=> entry_timestamp($a);
});

$recentSessions = array_slice($sessions, 0, 5);
$recentSets = array_slice($progress, 0, 10);
$lastSession = $recentSessions[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Progress Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="body-hub" data-user-id="<?= htmlspecialchars(user_get_current_id() ?? '') ?>">
    <header class="topbar">
        <div class="brand">Workout</div>
        <nav class="topnav">
            <?php if (auth_check()): ?>
                <span class="user-indicator">👤 <?= htmlspecialchars(auth_current_user()['username']) ?></span>
                <a href="index.php" class="active">Progress Hub</a>
                <a href="session.php">Session Mode</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="goals.php">Goals</a>
                <a href="compare.php">Compare</a>
                <a href="users.php">Users</a>
                <a href="routines.php">Routines</a>
                <a href="tracked_sets.php">Tracked Sets</a>
                <a href="/auth/logout.php" class="logout-link">Logout</a>
            <?php else: ?>
                <span class="user-indicator">Not logged in</span>
                <a href="/auth/login.php" class="btn-small">Login</a>
                <a href="/auth/register.php" class="btn-small">Register</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="page">
        <section class="hero">
            <div>
                <p class="eyebrow">Progress at a glance</p>
                <h1>Progress Hub</h1>
                <p class="lede">See your sessions, momentum, and most recent sets in one view.</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-green" href="session.php">Start Session</a>
                <a class="btn btn-blue" href="routines.php">Manage Routines</a>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <p>Total Sessions</p>
                <h3><?= count($sessions) ?></h3>
            </div>
            <div class="stat-card">
                <p>Total Sets Logged</p>
                <h3><?= $totalSets ?></h3>
            </div>
            <div class="stat-card">
                <p>Total Volume</p>
                <h3><?= number_format($totalVolume, 0) ?></h3>
            </div>
            <div class="stat-card">
                <p>Last Session</p>
                <h3><?= $lastSession ? date('d M Y', strtotime($lastSession['endTime'] ?? $lastSession['startTime'])) : '—' ?></h3>
                <span class="muted"><?= $lastSession ? htmlspecialchars($lastSession['routineName'] ?? '') : 'No sessions yet' ?></span>
            </div>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="index_recent_sessions">
                <summary>
                    <div>
                        <h2>Recent Sessions</h2>
                        <p class="muted">Quick recap of the last workouts.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <?php if (empty($recentSessions)): ?>
                        <p class="muted">No sessions logged yet.</p>
                    <?php else: ?>
                        <div class="table">
                            <div class="table-row table-head">
                                <div>Date</div>
                                <div>Routine</div>
                                <div>Duration</div>
                                <div>Sets</div>
                                <div>Volume</div>
                            </div>
                            <?php foreach ($recentSessions as $session): ?>
                                <div class="table-row">
                                    <div><?= date('d M Y', strtotime($session['endTime'] ?? $session['startTime'])) ?></div>
                                    <div><?= htmlspecialchars($session['routineName'] ?? '-') ?></div>
                                    <div><?= isset($session['duration']) ? gmdate('i:s', (int)$session['duration']) : '-' ?></div>
                                    <div><?= $session['totalSets'] ?? '-' ?></div>
                                    <div><?= isset($session['totalVolume']) ? number_format((float)$session['totalVolume'], 0) : '-' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="index_recent_sets">
                <summary>
                    <div>
                        <h2>Recent Sets</h2>
                        <p class="muted">Filter and scan your latest sets.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <div class="filter">
                        <input type="text" id="setSearch" placeholder="Filter by exercise...">
                    </div>
                    <?php if (empty($recentSets)): ?>
                        <p class="muted">No tracked sets available yet.</p>
                    <?php else: ?>
                        <div class="table" id="setsTable">
                            <div class="table-row table-head">
                                <div>Time</div>
                                <div>Exercise</div>
                                <div>Set</div>
                                <div>Weight</div>
                                <div>Reps</div>
                                <div>Effort</div>
                                <div>Duration</div>
                            </div>
                            <?php foreach ($recentSets as $set): ?>
                                <?php
                                    $timeValue = $set['startTime'] ?? $set['time'] ?? null;
                                    $weightValue = $set['weight'] ?? '-';
                                    $weightLabel = is_numeric($weightValue) ? $weightValue . ' lbs' : $weightValue;
                                ?>
                                <div class="table-row" data-exercise="<?= htmlspecialchars(strtolower($set['exercise'] ?? '')) ?>">
                                    <div><?= $timeValue ? date('d M H:i', strtotime($timeValue)) : '-' ?></div>
                                    <div><?= htmlspecialchars($set['exercise'] ?? '-') ?></div>
                                    <div><?= $set['set'] ?? '-' ?></div>
                                    <div><?= $weightLabel ?></div>
                                    <div><?= $set['reps'] ?? '-' ?></div>
                                    <div><?= $set['effort'] ?? '-' ?></div>
                                    <div><?= isset($set['duration']) ? $set['duration'] . 's' : '-' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>
    </main>

    <script src="collapsible.js"></script>
    <script src="user_tab.js"></script>
    <script>
        const setSearch = document.getElementById('setSearch');
        if (setSearch) {
            setSearch.addEventListener('input', (event) => {
                const term = event.target.value.trim().toLowerCase();
                document.querySelectorAll('#setsTable .table-row[data-exercise]').forEach(row => {
                    const match = row.dataset.exercise.includes(term);
                    row.style.display = match ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>
