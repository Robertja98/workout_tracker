
<?php
require_once __DIR__ . "/user_context.php";
user_bootstrap();
$currentUser = user_get_current();
$activeUsers = array_filter(user_get_users(), function ($user) {
    return empty($user['archivedAt']);
});

$messages = [];
$errors = [];

$historyUserId = $_GET['history_user'] ?? ($currentUser['id'] ?? '');
if (!$historyUserId || !isset($activeUsers[$historyUserId])) {
    $historyUserId = $currentUser['id'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_session') {
        $targetUserId = $_POST['history_user'] ?? '';
        $sessionId = $_POST['session_id'] ?? '';
        if (!$targetUserId || !isset($activeUsers[$targetUserId])) {
            $errors[] = 'Invalid user for session delete.';
        } elseif (!$sessionId) {
            $errors[] = 'Missing session id.';
        } else {
            $sessions = user_load_data_for($targetUserId, 'sessions.json', []);
            $updated = array_values(array_filter($sessions, function ($session) use ($sessionId) {
                return ($session['id'] ?? '') !== $sessionId;
            }));
            if (count($sessions) === count($updated)) {
                $errors[] = 'Session not found.';
            } elseif (!user_save_data_for($targetUserId, 'sessions.json', $updated)) {
                $errors[] = 'Failed to delete session.';
            } else {
                $messages[] = 'Session deleted.';
            }
        }
        $historyUserId = $targetUserId ?: $historyUserId;
    }
}

$progress = user_load_data('progress.json', []);
$historySessions = $historyUserId ? user_load_data_for($historyUserId, 'sessions.json', []) : [];
usort($historySessions, function ($a, $b) {
    $aTime = strtotime($a['startTime'] ?? '') ?: 0;
    $bTime = strtotime($b['startTime'] ?? '') ?: 0;
    return $bTime <=> $aTime;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workout Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .fade-in { opacity: 0; transition: opacity 0.5s ease; }
        .fade-in.visible { opacity: 1; }
    </style>
</head>
<body class="body-hub" data-user-id="<?= htmlspecialchars(user_get_current_id() ?? '') ?>">
    <header class="topbar">
        <button id="darkModeToggle" class="btn btn-small" style="float:right;margin:0.5em 1em 0 0;">🌙 Dark Mode</button>
        <div class="brand">Workout</div>
        <?php
        // Auto-detect base_url
        $base_url = '';
        if (file_exists(__DIR__ . '/auth/config.php')) {
            $config = require __DIR__ . '/auth/config.php';
            $base_url = $config['app']['base_url'] ?? '';
                    // Ensure base_url ends with a single slash
                    if ($base_url && substr($base_url, -1) !== '/') {
                        $base_url .= '/';
                    }
        }
        if (!$base_url) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $dir = rtrim(str_replace('dashboard.php', '', $script), '/');
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
            <a href="<?= $base_url ?>dashboard.php" class="active" title="View charts and stats for your workouts">Dashboard</a>
            <a href="<?= $base_url ?>goals.php" title="Set and track your fitness goals">Goals</a>
            <a href="<?= $base_url ?>compare.php" title="Compare progress between users">Compare</a>
            <a href="<?= $base_url ?>users.php" title="Manage user profiles">Users</a>
            <a href="<?= $base_url ?>routines.php" title="Create and edit workout routines">Routines</a>
            <a href="<?= $base_url ?>tracked_sets.php" title="Browse all logged sets">Tracked Sets</a>
        </nav>
    </header>

    <main class="page">
        <section class="panel" id="bestsStreaksPanel">
            <h2>Personal Bests & Streaks</h2>
            <div id="bestsStreaksContent" style="display:flex;gap:2em;flex-wrap:wrap;align-items:center;"></div>
        </section>
        <section class="panel" id="exportPanel">
            <h2>Export / Share Progress</h2>
            <button id="exportCsvBtn" class="btn btn-blue">Export CSV</button>
            <button id="exportJsonBtn" class="btn btn-green">Export JSON</button>
            <span id="exportStatus" style="margin-left:1em;color:#388e3c;"></span>
        </section>
        <section class="panel" id="reflectionsPanel">
            <h2>Session Reflections</h2>
            <div id="reflectionsContent" style="max-width:600px;"></div>
        </section>
        <section class="hero compact">
            <div>
                <p class="eyebrow">Insights</p>
                <h1>Workout Dashboard</h1>
                <p class="lede">Charts and tables that summarize your training trends.</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-green" href="/session.php">Start Session</a>
                <a class="btn btn-blue" href="routines.php">Manage Routines</a>
            </div>
        </section>

        <section class="panel">
            <details class="collapsible" id="historyDetails" data-storage="dashboard_history" open>
                <summary>
                    <div>
                        <h2>Session History</h2>
                        <p class="muted">Review logged sessions and recall details.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <form method="GET" class="filter-grid">
                        <div class="control-input">
                            <label for="history_user">User</label>
                            <select id="history_user" name="history_user">
                                <?php foreach ($activeUsers as $id => $user): ?>
                                    <option value="<?= htmlspecialchars($id) ?>" <?= $historyUserId === $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['name'] ?? 'User') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="control-input">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-blue">Load</button>
                        </div>
                    </form>

                    <?php foreach ($messages as $message): ?>
                        <div class="notice success"><?= htmlspecialchars($message) ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="notice error"><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>

                    <?php if (empty($historySessions)): ?>
                        <p class="muted">No saved sessions for this user yet.</p>
                    <?php else: ?>
                        <div class="table">
                            <div class="table-row table-head">
                                <div>Date</div>
                                <div>Routine</div>
                                <div>Duration</div>
                                <div>Total Sets</div>
                                <div>Total Volume</div>
                                <div>Actions</div>
                            </div>
                            <?php foreach ($historySessions as $session): ?>
                                <?php
                                    $startTime = $session['startTime'] ?? '';
                                    $duration = (int)($session['duration'] ?? 0);
                                    $minutes = floor($duration / 60);
                                    $seconds = $duration % 60;
                                    $durationLabel = sprintf('%02d:%02d', $minutes, $seconds);
                                ?>
                                <div class="table-row">
                                    <div><?= $startTime ? date('d M Y H:i', strtotime($startTime)) : '-' ?></div>
                                    <div><?= htmlspecialchars($session['routineName'] ?? '-') ?></div>
                                    <div><?= $durationLabel ?></div>
                                    <div><?= (int)($session['totalSets'] ?? 0) ?></div>
                                    <div><?= number_format((float)($session['totalVolume'] ?? 0), 0) ?></div>
                                    <div>
                                        <details class="inline-details">
                                            <summary class="btn btn-blue btn-small">View</summary>
                                            <div class="inline-details-body">
                                                <p><strong>Start:</strong> <?= $startTime ? htmlspecialchars($startTime) : '-' ?></p>
                                                <p><strong>End:</strong> <?= !empty($session['endTime']) ? htmlspecialchars($session['endTime']) : '-' ?></p>
                                                <p><strong>Routine ID:</strong> <?= htmlspecialchars($session['routineId'] ?? '-') ?></p>
                                                <p><strong>Notes:</strong> <?= htmlspecialchars($session['notes'] ?? '') ?: '—' ?></p>
                                            </div>
                                        </details>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this session?');">
                                            <input type="hidden" name="action" value="delete_session">
                                            <input type="hidden" name="session_id" value="<?= htmlspecialchars($session['id'] ?? '') ?>">
                                            <input type="hidden" name="history_user" value="<?= htmlspecialchars($historyUserId) ?>">
                                            <button type="submit" class="btn btn-red btn-small">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>

        <?php if (empty($progress)): ?>
            <p class="no-data">No progress data available yet.</p>
        <?php else: ?>
            <section class="summary-cards">
                <?php
                $totalSets = count($progress);
                $weights = array_filter(array_column($progress, 'weight'), 'is_numeric');
                $avgWeight = count($weights) ? array_sum($weights) / count($weights) : 0;
                $exerciseCounts = array_count_values(array_column($progress, 'exercise'));
                $mostFrequent = !empty($exerciseCounts) ? array_keys($exerciseCounts, max($exerciseCounts))[0] : '-';
                ?>
                <div class="card">
                    <p class="card-label">Total Sets</p>
                    <p class="card-value" id="metricTotalSets"><?= $totalSets ?></p>
                </div>
                <div class="card">
                    <p class="card-label">Average Weight</p>
                    <p class="card-value" id="metricAvgWeight"><?= round($avgWeight, 1) ?> lbs</p>
                </div>
                <div class="card">
                    <p class="card-label">Most Frequent</p>
                    <p class="card-value" id="metricMostFrequent"><?= htmlspecialchars($mostFrequent) ?></p>
                </div>
            </section>

            <section class="panel filter-panel">
                <details class="collapsible" id="filtersDetails" data-storage="dashboard_filters">
                    <summary>
                        <div>
                            <h2>Filters</h2>
                            <p class="muted">Dial in time range, exercise focus, and effort range.</p>
                        </div>
                    </summary>
                    <div class="collapsible-body">
                        <div class="filter-grid">
                            <div class="control-input">
                                <label for="filterFrom">From</label>
                                <input type="date" id="filterFrom">
                            </div>
                            <div class="control-input">
                                <label for="filterTo">To</label>
                                <input type="date" id="filterTo">
                            </div>
                            <div class="control-input">
                                <label for="filterExercise">Exercise</label>
                                <select id="filterExercise">
                                    <option value="">All Exercises</option>
                                </select>
                            </div>
                            <div class="control-input">
                                <label for="filterMinWeight">Min Weight (lbs)</label>
                                <input type="number" id="filterMinWeight" min="0" step="1" placeholder="Any">
                            </div>
                            <div class="control-input">
                                <label for="filterMinEffort">Min Effort</label>
                                <input type="number" id="filterMinEffort" min="1" max="10" step="1" placeholder="Any">
                            </div>
                            <div class="control-input">
                                <label for="filterMaxEffort">Max Effort</label>
                                <input type="number" id="filterMaxEffort" min="1" max="10" step="1" placeholder="Any">
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button class="btn btn-blue" id="clearFilters" type="button">Clear Filters</button>
                        </div>
                    </div>
                </details>
            </section>

            <section class="panel">
                <details class="collapsible" id="highlightsDetails" data-storage="dashboard_highlights">
                    <summary>
                        <div>
                            <h2>Highlights</h2>
                            <p class="muted">Top exercises and effort signals for the selected range.</p>
                        </div>
                    </summary>
                    <div class="collapsible-body">
                        <div class="dashboard-grid">
                            <div class="subpanel">
                                <h3>Top Exercises</h3>
                                <div class="table compact-table" id="topExercisesList">
                                    <div class="table-row table-head">
                                        <div>Exercise</div>
                                        <div>Sets</div>
                                    </div>
                                </div>
                            </div>
                            <div class="subpanel">
                                <h3>Effort Snapshot</h3>
                                <div class="mini-stats">
                                    <div class="mini-stat">
                                        <h4>Average Effort</h4>
                                        <p id="metricAvgEffort">-</p>
                                    </div>
                                    <div class="mini-stat">
                                        <h4>Peak Effort</h4>
                                        <p id="metricPeakEffort">-</p>
                                    </div>
                                    <div class="mini-stat">
                                        <h4>Last Effort</h4>
                                        <p id="metricLastEffort">-</p>
                                    </div>
                                    <div class="mini-stat">
                                        <h4>Effort Sets</h4>
                                        <p id="metricEffortSets">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>
            </section>

            <section class="panel progress-section">
                <details class="collapsible" id="trackedSetsDetails" data-storage="dashboard_tracked_sets">
                    <summary>
                        <div>
                            <h2>Tracked Sets</h2>
                            <p class="muted">Live records from this dashboard and session mode.</p>
                        </div>
                        <span class="pill" id="trackedSetsCount"><?= count($progress) ?> sets</span>
                    </summary>
                    <div class="collapsible-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>Exercise</th>
                                    <th>Set</th>
                                    <th>Weight (lbs)</th>
                                    <th>Reps</th>
                                    <th>Duration (s)</th>
                                    <th>Effort</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="progressTableBody">
                                <?php foreach ($progress as $p): ?>
                                <?php
                                    $timeValue = $p['startTime'] ?? $p['time'] ?? null;
                                    $weightValue = $p['weight'] ?? '-';
                                    $weightLabel = is_numeric($weightValue) ? $weightValue . ' lbs' : $weightValue;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['exercise']) ?></td>
                                    <td><?= $p['set'] ?></td>
                                    <td><?= $weightLabel ?></td>
                                    <td><?= $p['reps'] ?? '-' ?></td>
                                    <td><?= isset($p['duration']) ? $p['duration'] : '-' ?></td>
                                    <td><?= $p['effort'] ?: '-' ?></td>
                                    <td><?= $timeValue ? date('d M Y H:i', strtotime($timeValue)) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>

            <section class="panel charts-section">
                <details class="collapsible" id="chartsDetails" data-storage="dashboard_charts">
                    <summary>
                        <div>
                            <h2>Visual Progress</h2>
                            <p class="muted">Trends across frequency, load, and effort.</p>
                        </div>
                    </summary>
                    <div class="collapsible-body">
                        <div class="chart-container">
                            <canvas id="setsPerWeekChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="volumePerWeekChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="avgEffortChart"></canvas>
                        </div>
                    </div>
                </details>
            </section>
        <?php endif; ?>

    <script>
    // Display personal bests and streaks from localStorage
    function formatDuration(seconds) {
        const min = Math.floor(seconds/60), sec = seconds%60;
        return `${min}m ${sec}s`;
    }
    function showBestsAndStreaks() {
        const bests = JSON.parse(localStorage.getItem('personalBests')||'{}');
        const streak = JSON.parse(localStorage.getItem('workoutStreak')||'{}');
        let html = '';
        html += `<div><strong>Most Sets:</strong> ${bests.sets||'—'}</div>`;
        html += `<div><strong>Highest Volume:</strong> ${bests.volume||'—'}</div>`;
        html += `<div><strong>Longest Session:</strong> ${bests.duration ? formatDuration(bests.duration) : '—'}</div>`;
        html += `<div><strong>Current Streak:</strong> ${streak.count||0} day${streak.count==1?'':'s'}</div>`;
        document.getElementById('bestsStreaksContent').innerHTML = html;
    }
    showBestsAndStreaks();
    // Show session reflections
    function showReflections() {
        let reflections = [];
        try { reflections = JSON.parse(localStorage.getItem('sessionReflections')||'[]'); } catch(e) {}
        let html = '';
        if (!reflections.length) html = '<p class="muted">No reflections yet.</p>';
        else html = '<ul style="padding-left:1.2em;">' + reflections.slice(-10).reverse().map(r => `<li><b>${r.date.slice(0,10)}</b>: ${r.text.replace(/</g,'&lt;')}</li>`).join('') + '</ul>';
        document.getElementById('reflectionsContent').innerHTML = html;
    }
    showReflections();
    // Progress Graphs
    function groupByWeek(data, valueFn) {
        const weekMap = {};
        data.forEach(entry => {
            const d = normalizeTime(entry);
            if (!d) return;
            // Get ISO week string
            const y = d.getFullYear();
            const onejan = new Date(d.getFullYear(),0,1);
            const week = Math.ceil((((d - onejan) / 86400000) + onejan.getDay()+1)/7);
            const key = `${y}-W${week.toString().padStart(2,'0')}`;
            if (!weekMap[key]) weekMap[key] = [];
            weekMap[key].push(entry);
        });
        // Sort by week
        const sorted = Object.entries(weekMap).sort(([a], [b]) => a.localeCompare(b));
        return sorted.map(([week, entries]) => ({week, value: valueFn(entries)}));
    }

    function renderProgressCharts() {
        // Sets per week
        const setsData = groupByWeek(progressData, entries => entries.length);
        const setsLabels = setsData.map(d => d.week);
        const setsCounts = setsData.map(d => d.value);
        new Chart(document.getElementById('setsPerWeekChart').getContext('2d'), {
            type: 'bar',
            data: { labels: setsLabels, datasets: [{ label: 'Sets per Week', data: setsCounts, backgroundColor: '#2196f3' }] },
            options: { plugins: { legend: { display: false } } }
        });
        // Volume per week
        const volumeData = groupByWeek(progressData, entries => entries.reduce((sum,e) => sum + (parseFloat(e.weight||0)*parseInt(e.reps||0)||0),0));
        const volumeLabels = volumeData.map(d => d.week);
        const volumeCounts = volumeData.map(d => d.value);
        new Chart(document.getElementById('volumePerWeekChart').getContext('2d'), {
            type: 'bar',
            data: { labels: volumeLabels, datasets: [{ label: 'Volume per Week', data: volumeCounts, backgroundColor: '#4caf50' }] },
            options: { plugins: { legend: { display: false } } }
        });
        // Average effort per week
        const effortData = groupByWeek(progressData, entries => {
            const efforts = entries.map(e => parseInt(e.effort||0)).filter(Boolean);
            return efforts.length ? (efforts.reduce((a,b)=>a+b,0)/efforts.length).toFixed(2) : 0;
        });
        const effortLabels = effortData.map(d => d.week);
        const effortCounts = effortData.map(d => d.value);
        new Chart(document.getElementById('avgEffortChart').getContext('2d'), {
            type: 'line',
            data: { labels: effortLabels, datasets: [{ label: 'Avg Effort', data: effortCounts, borderColor: '#ff9800', backgroundColor: 'rgba(255,152,0,0.1)', fill: true }] },
            options: { plugins: { legend: { display: false } } }
        });
    }
    if (window.Chart) renderProgressCharts();
    // Dark mode toggle
    const darkModeBtn = document.getElementById('darkModeToggle');
    function setDarkMode(on) {
        document.body.classList.toggle('dark-mode', on);
        localStorage.setItem('darkMode', on ? '1' : '0');
        darkModeBtn.textContent = on ? '☀️ Light Mode' : '🌙 Dark Mode';
    }
    darkModeBtn.onclick = () => setDarkMode(!document.body.classList.contains('dark-mode'));
    if (localStorage.getItem('darkMode') === '1') setDarkMode(true);

    // Export/share logic
    function downloadFile(filename, content, type) {
        const blob = new Blob([content], {type});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 100);
    }
    document.getElementById('exportCsvBtn').onclick = function() {
        let rows = [Object.keys(progressData[0]||{}).join(',')];
        for (let row of progressData) {
            rows.push(Object.values(row).map(v => '"'+String(v).replace(/"/g,'""')+'"').join(','));
        }
        downloadFile('workout_progress.csv', rows.join('\n'), 'text/csv');
        document.getElementById('exportStatus').textContent = 'CSV downloaded!';
        setTimeout(()=>{document.getElementById('exportStatus').textContent='';},2000);
    };
    document.getElementById('exportJsonBtn').onclick = function() {
        downloadFile('workout_progress.json', JSON.stringify(progressData,null,2), 'application/json');
        document.getElementById('exportStatus').textContent = 'JSON downloaded!';
        setTimeout(()=>{document.getElementById('exportStatus').textContent='';},2000);
    };
    </script>
    </main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const progressData = <?php echo json_encode($progress, JSON_PRETTY_PRINT); ?>;
    const filterFrom = document.getElementById('filterFrom');
    const filterTo = document.getElementById('filterTo');
    const filterExercise = document.getElementById('filterExercise');
    const filterMinWeight = document.getElementById('filterMinWeight');
    const filterMinEffort = document.getElementById('filterMinEffort');
    const filterMaxEffort = document.getElementById('filterMaxEffort');
    const clearFilters = document.getElementById('clearFilters');
    const progressTableBody = document.getElementById('progressTableBody');
    const topExercisesList = document.getElementById('topExercisesList');

    const metricTotalSets = document.getElementById('metricTotalSets');
    const metricAvgWeight = document.getElementById('metricAvgWeight');
    const metricMostFrequent = document.getElementById('metricMostFrequent');
    const metricAvgEffort = document.getElementById('metricAvgEffort');
    const metricPeakEffort = document.getElementById('metricPeakEffort');
    const metricLastEffort = document.getElementById('metricLastEffort');
    const metricEffortSets = document.getElementById('metricEffortSets');
    const trackedSetsCount = document.getElementById('trackedSetsCount');

    let topExercisesChart = null;
    let weightChart = null;
    let effortChart = null;

    const normalizeTime = (entry) => {
        const timeValue = entry.startTime || entry.time || null;
        if (!timeValue) return null;
        const parsed = new Date(timeValue);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const formatDate = (date) => {
        if (!date) return '-';
        return new Intl.DateTimeFormat('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    };

    const formatWeight = (value) => {
        const weight = parseFloat(value);
        if (Number.isFinite(weight)) {
            return `${weight} lbs`;
        }
        return value ?? '-';
    };

    const populateExerciseOptions = () => {
        const exercises = Array.from(new Set(progressData.map(entry => entry.exercise).filter(Boolean))).sort();
        exercises.forEach(exercise => {
            const option = document.createElement('option');
            option.value = exercise;
            option.textContent = exercise;
            filterExercise.appendChild(option);
        });
    };

    const filterData = () => {
        const fromDate = filterFrom.value ? new Date(`${filterFrom.value}T00:00:00`) : null;
        const toDate = filterTo.value ? new Date(`${filterTo.value}T23:59:59`) : null;
        const minWeight = parseFloat(filterMinWeight.value);
        const minEffort = parseInt(filterMinEffort.value, 10);
        const maxEffort = parseInt(filterMaxEffort.value, 10);
        const exerciseValue = filterExercise.value;

        return progressData.filter(entry => {
            const entryTime = normalizeTime(entry);
            if (fromDate && (!entryTime || entryTime < fromDate)) return false;
            if (toDate && (!entryTime || entryTime > toDate)) return false;

            if (exerciseValue && entry.exercise !== exerciseValue) return false;

            const weight = parseFloat(entry.weight);
            if (Number.isFinite(minWeight) && (!Number.isFinite(weight) || weight < minWeight)) return false;

            const effort = parseInt(entry.effort, 10);
            if (Number.isFinite(minEffort) && (!Number.isFinite(effort) || effort < minEffort)) return false;
            if (Number.isFinite(maxEffort) && (!Number.isFinite(effort) || effort > maxEffort)) return false;

            return true;
        });
    };

    const getTopExercises = (data, limit = 8) => {
        const counts = {};
        data.forEach(entry => {
            const exercise = entry.exercise || 'Unknown';
            counts[exercise] = (counts[exercise] || 0) + 1;
        });
        return Object.entries(counts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, limit)
            .map(([label, value]) => ({ label, value }));
    };

    const updateSummaryCards = (data) => {
        metricTotalSets.textContent = data.length;
        const weights = data.map(entry => parseFloat(entry.weight)).filter(weight => Number.isFinite(weight));
        const avgWeight = weights.length ? (weights.reduce((sum, value) => sum + value, 0) / weights.length) : 0;
        metricAvgWeight.textContent = `${avgWeight.toFixed(1)} lbs`;

        const topExercises = getTopExercises(data, 1);
        metricMostFrequent.textContent = topExercises.length ? topExercises[0].label : '-';
    };

    const renderTopExercises = (data) => {
        const topExercises = getTopExercises(data, 6);
        topExercisesList.querySelectorAll('.table-row:not(.table-head)').forEach(row => row.remove());

        if (!topExercises.length) {
            const emptyRow = document.createElement('div');
            emptyRow.className = 'table-row table-empty';
            emptyRow.textContent = 'No exercises match these filters.';
            topExercisesList.appendChild(emptyRow);
            return;
        }

        topExercises.forEach(item => {
            const row = document.createElement('div');
            row.className = 'table-row';
            row.innerHTML = `<div>${item.label}</div><div>${item.value}</div>`;
            topExercisesList.appendChild(row);
        });
    };

    const renderEffortSnapshot = (data) => {
        const effortEntries = data
            .map(entry => {
                const effort = parseInt(entry.effort, 10);
                return Number.isFinite(effort) ? { effort, time: normalizeTime(entry) } : null;
            })
            .filter(Boolean);

        if (!effortEntries.length) {
            metricAvgEffort.textContent = '-';
            metricPeakEffort.textContent = '-';
            metricLastEffort.textContent = '-';
            metricEffortSets.textContent = '0';
            return;
        }

        const total = effortEntries.reduce((sum, item) => sum + item.effort, 0);
        const avg = total / effortEntries.length;
        const peak = Math.max(...effortEntries.map(item => item.effort));
        const last = effortEntries
            .filter(item => item.time)
            .sort((a, b) => b.time - a.time)[0];

        metricAvgEffort.textContent = avg.toFixed(1);
        metricPeakEffort.textContent = peak;
        metricLastEffort.textContent = last ? last.effort : '-';
        metricEffortSets.textContent = effortEntries.length;
    };

    const renderTable = (data) => {
        progressTableBody.innerHTML = '';

        if (!data.length) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'table-empty';
            emptyRow.innerHTML = '<td colspan="7">No sets match these filters.</td>';
            progressTableBody.appendChild(emptyRow);
            return;
        }

        data.forEach(entry => {
            const row = document.createElement('tr');
            const timeValue = normalizeTime(entry);
            row.innerHTML = `
                <td>${entry.exercise || '-'}</td>
                <td>${entry.set ?? '-'}</td>
                <td>${formatWeight(entry.weight)}</td>
                <td>${entry.reps ?? '-'}</td>
                <td>${entry.duration ?? '-'}</td>
                <td>${entry.effort ?? '-'}</td>
                <td>${formatDate(timeValue)}</td>
            `;
            progressTableBody.appendChild(row);
        });
    };

    const renderCharts = (data) => {
        const topExercises = getTopExercises(data, 8);
        const topLabels = topExercises.map(item => item.label);
        const topValues = topExercises.map(item => item.value);

        const weightTotals = {};
        const weightCounts = {};
        data.forEach(entry => {
            const weight = parseFloat(entry.weight);
            const exercise = entry.exercise || 'Unknown';
            if (Number.isFinite(weight)) {
                weightTotals[exercise] = (weightTotals[exercise] || 0) + weight;
                weightCounts[exercise] = (weightCounts[exercise] || 0) + 1;
            }
        });

        const weightData = topLabels.map(label => {
            const total = weightTotals[label] || 0;
            const count = weightCounts[label] || 0;
            return count ? (total / count) : 0;
        });

        const effortPoints = [];
        data.forEach(entry => {
            const effort = parseInt(entry.effort, 10);
            const timeValue = normalizeTime(entry);
            if (Number.isFinite(effort) && timeValue) {
                effortPoints.push({
                    label: `${entry.exercise || 'Unknown'} Set ${entry.set ?? ''}`.trim(),
                    effort,
                    time: timeValue.getTime()
                });
            }
        });

        effortPoints.sort((a, b) => a.time - b.time);
        const recentEffort = effortPoints.slice(-20);
        const effortLabels = recentEffort.map(point => point.label);
        const effortData = recentEffort.map(point => point.effort);

        if (topExercisesChart) topExercisesChart.destroy();
        if (weightChart) weightChart.destroy();
        if (effortChart) effortChart.destroy();

        topExercisesChart = new Chart(document.getElementById('topExercisesChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{ label: 'Top Exercises', data: topValues, backgroundColor: '#1f7a4f' }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        weightChart = new Chart(document.getElementById('weightChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: topLabels,
                datasets: [{ label: 'Average Weight', data: weightData, borderColor: '#2c64e5', fill: false }]
            },
            options: { responsive: true }
        });

        effortChart = new Chart(document.getElementById('effortChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: effortLabels,
                datasets: [{ label: 'Effort Trend', data: effortData, borderColor: '#f4b66d', fill: false }]
            },
            options: { responsive: true }
        });
    };

    const refreshDashboard = () => {
        const filtered = filterData();
        updateSummaryCards(filtered);
        renderTopExercises(filtered);
        renderEffortSnapshot(filtered);
        renderTable(filtered);
        renderCharts(filtered);
        trackedSetsCount.textContent = `${filtered.length} sets`;
    };

    [filterFrom, filterTo, filterExercise, filterMinWeight, filterMinEffort, filterMaxEffort].forEach(input => {
        input.addEventListener('input', refreshDashboard);
        input.addEventListener('change', refreshDashboard);
    });

    clearFilters.addEventListener('click', () => {
        filterFrom.value = '';
        filterTo.value = '';
        filterExercise.value = '';
        filterMinWeight.value = '';
        filterMinEffort.value = '';
        filterMaxEffort.value = '';
        refreshDashboard();
    });

    populateExerciseOptions();
    refreshDashboard();
});
</script>

<script src="collapsible.js"></script>
<script src="user_tab.js"></script>
</body>
</html>
