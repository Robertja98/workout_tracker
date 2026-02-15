<?php
require_once __DIR__ . "/user_context.php";
$currentUser = user_get_current();
$activeUsers = array_filter(user_get_users(), function ($user) {
    return empty($user['archivedAt']);
});
$users = array_filter(user_get_users(), function ($user) {
    return empty($user['archivedAt']);
});

function compare_total_volume($progress) {
    $total = 0;
    foreach ($progress as $entry) {
        $weight = $entry['weight'] ?? null;
        $reps = $entry['reps'] ?? null;
        if (is_numeric($weight) && is_numeric($reps)) {
            $total += ((float)$weight) * ((int)$reps);
        }
    }
    return $total;
}

function compare_avg_weight($progress) {
    $weights = [];
    foreach ($progress as $entry) {
        if (is_numeric($entry['weight'] ?? null)) {
            $weights[] = (float)$entry['weight'];
        }
    }
    if (!$weights) {
        return null;
    }
    return array_sum($weights) / count($weights);
}

function compare_sessions_this_week($sessions, $progress) {
    $now = new DateTimeImmutable('now');
    $startOfWeek = $now->modify('monday this week')->setTime(0, 0, 0);
    $endOfWeek = $now->modify('sunday this week')->setTime(23, 59, 59);
    $sessionDates = [];
    $entries = !empty($sessions) ? $sessions : $progress;
    foreach ($entries as $entry) {
        $timeValue = $entry['startTime'] ?? $entry['endTime'] ?? $entry['time'] ?? null;
        if (!$timeValue) {
            continue;
        }
        $timestamp = strtotime($timeValue);
        if ($timestamp === false) {
            continue;
        }
        $date = new DateTimeImmutable(date('Y-m-d', $timestamp));
        if ($date < $startOfWeek || $date > $endOfWeek) {
            continue;
        }
        $sessionDates[$date->format('Y-m-d')] = true;
    }
    return count($sessionDates);
}

function compare_last_session_date($sessions, $progress) {
    $entries = !empty($sessions) ? $sessions : $progress;
    $latest = null;
    foreach ($entries as $entry) {
        $timeValue = $entry['endTime'] ?? $entry['startTime'] ?? $entry['time'] ?? null;
        if (!$timeValue) {
            continue;
        }
        $timestamp = strtotime($timeValue);
        if ($timestamp === false) {
            continue;
        }
        if ($latest === null || $timestamp > $latest) {
            $latest = $timestamp;
        }
    }
    return $latest ? date('d M Y', $latest) : null;
}

function compare_pr_count($progress) {
    $exerciseSet = [];
    foreach ($progress as $entry) {
        $exercise = trim((string)($entry['exercise'] ?? ''));
        $weight = $entry['weight'] ?? null;
        if ($exercise === '' || !is_numeric($weight)) {
            continue;
        }
        $exerciseSet[$exercise] = true;
    }
    return count($exerciseSet);
}

function compare_weight_progress($goals, $checkins) {
    $weightGoal = $goals['weight'] ?? [];
    $startWeight = $weightGoal['start'] ?? null;
    $targetWeight = $weightGoal['target'] ?? null;
    $currentWeight = null;
    if (!empty($checkins)) {
        $last = $checkins[count($checkins) - 1];
        $currentWeight = $last['weight'] ?? null;
    }
    if (!is_numeric($startWeight) || !is_numeric($targetWeight) || !is_numeric($currentWeight)) {
        return null;
    }
    $startWeight = (float)$startWeight;
    $targetWeight = (float)$targetWeight;
    $currentWeight = (float)$currentWeight;
    if ($startWeight === $targetWeight) {
        return 1;
    }
    if ($targetWeight < $startWeight) {
        return max(0, min(1, ($startWeight - $currentWeight) / ($startWeight - $targetWeight)));
    }
    return max(0, min(1, ($currentWeight - $startWeight) / ($targetWeight - $startWeight)));
}

function compare_frequency_progress($goals, $weeklySessions) {
    $target = $goals['frequency']['sessionsPerWeek'] ?? null;
    if (!is_numeric($target) || (float)$target <= 0) {
        return null;
    }
    return min(1, $weeklySessions / (float)$target);
}

$rows = [];
foreach ($users as $userId => $user) {
    $progress = user_load_json(user_data_path($userId, 'progress.json'), []);
    $sessions = user_load_json(user_data_path($userId, 'sessions.json'), []);
    $goals = user_load_json(user_data_path($userId, 'goals.json'), []);
    $checkins = user_load_json(user_data_path($userId, 'goals_checkins.json'), []);

    $totalSets = count($progress);
    $totalVolume = compare_total_volume($progress);
    $avgWeight = compare_avg_weight($progress);
    $weeklySessions = compare_sessions_this_week($sessions, $progress);
    $lastSession = compare_last_session_date($sessions, $progress);
    $prCount = compare_pr_count($progress);
    $weightProgress = compare_weight_progress($goals, $checkins);
    $frequencyProgress = compare_frequency_progress($goals, $weeklySessions);

    $rows[] = [
        'name' => $user['name'] ?? 'User',
        'totalSets' => $totalSets,
        'totalVolume' => $totalVolume,
        'avgWeight' => $avgWeight,
        'weeklySessions' => $weeklySessions,
        'lastSession' => $lastSession,
        'prCount' => $prCount,
        'weightProgress' => $weightProgress,
        'frequencyProgress' => $frequencyProgress
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compare Metrics</title>
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
            $dir = rtrim(str_replace('compare.php', '', $script), '/');
            $base_url = $dir ? $dir . '/' : '/';
        }
        ?>
        <nav class="topnav">
            <div class="user-switch">
                <label class="user-switch-label" for="userSwitch">Active</label>
                <select id="userSwitch" class="user-switch-select" data-user-switch>
                    <?php foreach ($activeUsers as $id => $user): ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= $currentUser && ($currentUser['id'] ?? '') === $id ? 'selected' : '' ?> >
                            <?= htmlspecialchars($user['name'] ?? 'User') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="<?= $base_url ?>index.php" title="See your overall progress and recent activity">Progress Hub</a>
            <a href="<?= $base_url ?>session.php" title="Start and log a new workout session">Session Mode</a>
            <a href="<?= $base_url ?>dashboard.php" title="View charts and stats for your workouts">Dashboard</a>
            <a href="<?= $base_url ?>goals.php" title="Set and track your fitness goals">Goals</a>
            <a href="<?= $base_url ?>compare.php" class="active" title="Compare progress between users">Compare</a>
            <a href="<?= $base_url ?>users.php" title="Manage user profiles">Users</a>
            <a href="<?= $base_url ?>routines.php" title="Create and edit workout routines">Routines</a>
            <a href="<?= $base_url ?>tracked_sets.php" title="Browse all logged sets">Tracked Sets</a>
        </nav>
    </header>

    <main class="page">
        <section class="hero compact">
            <div>
                <p class="eyebrow">Multi-user</p>
                <h1>Compare Metrics</h1>
                <p class="lede">See how each profile is tracking across common goals.</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-blue" href="users.php">Manage Users</a>
            </div>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="compare_table">
                <summary>
                    <div>
                        <h2>Overview</h2>
                        <p class="muted">Totals, consistency, and goal progress side by side.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <?php if (empty($rows)): ?>
                        <p class="muted">Add users to see comparison data.</p>
                    <?php else: ?>
                        <div class="table">
                            <div class="table-row table-head">
                                <div>User</div>
                                <div>Total Sets</div>
                                <div>Total Volume</div>
                                <div>Avg Weight</div>
                                <div>Sessions This Week</div>
                                <div>Last Session</div>
                                <div>PR Count</div>
                                <div>Weight Progress</div>
                                <div>Frequency Progress</div>
                            </div>
                            <?php foreach ($rows as $row): ?>
                                <div class="table-row">
                                    <div><?= htmlspecialchars($row['name']) ?></div>
                                    <div><?= $row['totalSets'] ?></div>
                                    <div><?= number_format($row['totalVolume'], 0) ?></div>
                                    <div><?= $row['avgWeight'] !== null ? number_format($row['avgWeight'], 1) . ' lbs' : '-' ?></div>
                                    <div><?= $row['weeklySessions'] ?></div>
                                    <div><?= $row['lastSession'] ?? '-' ?></div>
                                    <div><?= $row['prCount'] ?></div>
                                    <div><?= $row['weightProgress'] !== null ? round($row['weightProgress'] * 100) . '%' : '-' ?></div>
                                    <div><?= $row['frequencyProgress'] !== null ? round($row['frequencyProgress'] * 100) . '%' : '-' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="compare_charts">
                <summary>
                    <div>
                        <h2>Charts</h2>
                        <p class="muted">Visual comparison of workload and goal progress.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <?php if (empty($rows)): ?>
                        <p class="muted">Add users to see chart data.</p>
                    <?php else: ?>
                        <div class="compare-chart-grid">
                            <div class="chart-container">
                                <canvas id="compareSetsChart"></canvas>
                            </div>
                            <div class="chart-container">
                                <canvas id="compareVolumeChart"></canvas>
                            </div>
                            <div class="chart-container">
                                <canvas id="compareSessionsChart"></canvas>
                            </div>
                            <div class="chart-container">
                                <canvas id="comparePrChart"></canvas>
                            </div>
                            <div class="chart-container">
                                <canvas id="compareGoalsChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>
    </main>

    <script src="collapsible.js"></script>
    <script src="user_tab.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const labels = <?php echo json_encode(array_column($rows, 'name'), JSON_PRETTY_PRINT); ?>;
            if (!labels || labels.length === 0) return;

            const totalSets = <?php echo json_encode(array_column($rows, 'totalSets'), JSON_PRETTY_PRINT); ?>;
            const totalVolume = <?php echo json_encode(array_column($rows, 'totalVolume'), JSON_PRETTY_PRINT); ?>;
            const weeklySessions = <?php echo json_encode(array_column($rows, 'weeklySessions'), JSON_PRETTY_PRINT); ?>;
            const prCount = <?php echo json_encode(array_column($rows, 'prCount'), JSON_PRETTY_PRINT); ?>;
            const weightProgress = <?php echo json_encode(array_map(function ($row) {
                return $row['weightProgress'] !== null ? round($row['weightProgress'] * 100, 1) : null;
            }, $rows), JSON_PRETTY_PRINT); ?>;
            const frequencyProgress = <?php echo json_encode(array_map(function ($row) {
                return $row['frequencyProgress'] !== null ? round($row['frequencyProgress'] * 100, 1) : null;
            }, $rows), JSON_PRETTY_PRINT); ?>;

            const setsCtx = document.getElementById('compareSetsChart');
            if (setsCtx) {
                new Chart(setsCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Total Sets',
                            data: totalSets,
                            backgroundColor: 'rgba(31, 122, 79, 0.35)',
                            borderColor: '#1f7a4f',
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true }
                });
            }

            const volumeCtx = document.getElementById('compareVolumeChart');
            if (volumeCtx) {
                new Chart(volumeCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Total Volume',
                            data: totalVolume,
                            backgroundColor: 'rgba(44, 100, 229, 0.35)',
                            borderColor: '#2c64e5',
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true }
                });
            }

            const sessionsCtx = document.getElementById('compareSessionsChart');
            if (sessionsCtx) {
                new Chart(sessionsCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Sessions This Week',
                            data: weeklySessions,
                            backgroundColor: 'rgba(244, 182, 109, 0.45)',
                            borderColor: '#f4b66d',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }

            const prCtx = document.getElementById('comparePrChart');
            if (prCtx) {
                new Chart(prCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'PR Count',
                            data: prCount,
                            backgroundColor: 'rgba(138, 90, 0, 0.35)',
                            borderColor: '#8a5a00',
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                });
            }

            const goalsCtx = document.getElementById('compareGoalsChart');
            if (goalsCtx) {
                new Chart(goalsCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Weight Progress %',
                                data: weightProgress,
                                borderColor: '#1f7a4f',
                                fill: false
                            },
                            {
                                label: 'Frequency Progress %',
                                data: frequencyProgress,
                                borderColor: '#2c64e5',
                                fill: false
                            }
                        ]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
                });
            }
        });
    </script>
</body>
</html>
