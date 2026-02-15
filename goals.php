<?php
require_once __DIR__ . "/user_context.php";
user_bootstrap();
$currentUser = user_get_current();
$activeUsers = array_filter(user_get_users(), function ($user) {
    return empty($user['archivedAt']);
});

function read_numeric_value($value) {
    $value = is_string($value) ? trim($value) : $value;
    if ($value === '' || $value === null) {
        return null;
    }
    return is_numeric($value) ? (float)$value : null;
}

function read_int_value($value) {
    $value = is_string($value) ? trim($value) : $value;
    if ($value === '' || $value === null) {
        return null;
    }
    return is_numeric($value) ? (int)$value : null;
}

function sanitize_reminder($cadence, $day) {
    $cadence = strtolower(trim((string)$cadence));
    $day = trim((string)$day);
    $validCadence = ['none', 'daily', 'weekly'];
    $validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    if (!in_array($cadence, $validCadence, true)) {
        $cadence = 'none';
    }
    if ($cadence !== 'weekly') {
        $day = '';
    } elseif (!in_array($day, $validDays, true)) {
        $day = 'Mon';
    }

    return ['cadence' => $cadence, 'day' => $day];
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_goals') {
        $weightStart = read_numeric_value($_POST['weight_start'] ?? null);
        $weightTarget = read_numeric_value($_POST['weight_target'] ?? null);
        $weightDeadline = trim((string)($_POST['weight_deadline'] ?? ''));
        if ($weightDeadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weightDeadline)) {
            $weightDeadline = '';
        }

        $bodyFatTarget = read_numeric_value($_POST['body_fat_target'] ?? null);
        $waistTarget = read_numeric_value($_POST['waist_target'] ?? null);
        $chestTarget = read_numeric_value($_POST['chest_target'] ?? null);
        $hipsTarget = read_numeric_value($_POST['hips_target'] ?? null);

        $sessionsPerWeek = read_int_value($_POST['sessions_per_week'] ?? null);

        $weightNote = trim((string)($_POST['weight_note'] ?? ''));
        $bodyNote = trim((string)($_POST['body_note'] ?? ''));
        $strengthNote = trim((string)($_POST['strength_note'] ?? ''));
        $frequencyNote = trim((string)($_POST['frequency_note'] ?? ''));

        $weightReminder = sanitize_reminder($_POST['weight_reminder'] ?? 'none', $_POST['weight_reminder_day'] ?? '');
        $bodyReminder = sanitize_reminder($_POST['body_reminder'] ?? 'none', $_POST['body_reminder_day'] ?? '');
        $strengthReminder = sanitize_reminder($_POST['strength_reminder'] ?? 'none', $_POST['strength_reminder_day'] ?? '');
        $frequencyReminder = sanitize_reminder($_POST['frequency_reminder'] ?? 'none', $_POST['frequency_reminder_day'] ?? '');

        $strengthTargets = [];
        $rawTargets = $_POST['strength'] ?? [];
        if (is_array($rawTargets)) {
            foreach ($rawTargets as $row) {
                $exercise = trim((string)($row['exercise'] ?? ''));
                $type = (string)($row['type'] ?? '1rm');
                $targetValue = read_numeric_value($row['target'] ?? null);
                if ($exercise === '' || $targetValue === null) {
                    continue;
                }
                if (!in_array($type, ['1rm', 'weight'], true)) {
                    $type = '1rm';
                }
                $strengthTargets[] = [
                    'exercise' => $exercise,
                    'type' => $type,
                    'target' => $targetValue
                ];
            }
        }

        $existingGoals = user_load_data('goals.json', []);
        if (!empty($existingGoals)) {
            $goalsHistory = user_load_data('goals_history.json', []);
            $goalsHistory[] = [
                'savedAt' => date('c'),
                'goals' => $existingGoals
            ];
            user_save_data('goals_history.json', $goalsHistory);
        }
        $goals = [
            'weight' => [
                'start' => $weightStart,
                'target' => $weightTarget,
                'deadline' => $weightDeadline
            ],
            'body' => [
                'bodyFatTarget' => $bodyFatTarget,
                'waistTarget' => $waistTarget,
                'chestTarget' => $chestTarget,
                'hipsTarget' => $hipsTarget
            ],
            'strength' => [
                'targets' => $strengthTargets
            ],
            'frequency' => [
                'sessionsPerWeek' => $sessionsPerWeek
            ],
            'notes' => [
                'weight' => [
                    'text' => $weightNote,
                    'reminder' => $weightReminder
                ],
                'body' => [
                    'text' => $bodyNote,
                    'reminder' => $bodyReminder
                ],
                'strength' => [
                    'text' => $strengthNote,
                    'reminder' => $strengthReminder
                ],
                'frequency' => [
                    'text' => $frequencyNote,
                    'reminder' => $frequencyReminder
                ]
            ],
            'createdAt' => $existingGoals['createdAt'] ?? date('c'),
            'updatedAt' => date('c')
        ];

        user_save_data('goals.json', $goals);
        $messages[] = 'Goals saved.';
    }

    if ($action === 'add_checkin') {
        $date = trim((string)($_POST['checkin_date'] ?? date('Y-m-d')));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $weight = read_numeric_value($_POST['checkin_weight'] ?? null);
        $bodyFat = read_numeric_value($_POST['checkin_body_fat'] ?? null);
        $waist = read_numeric_value($_POST['checkin_waist'] ?? null);
        $chest = read_numeric_value($_POST['checkin_chest'] ?? null);
        $hips = read_numeric_value($_POST['checkin_hips'] ?? null);

        if ($weight === null && $bodyFat === null && $waist === null && $chest === null && $hips === null) {
            $errors[] = 'Add at least one check-in value.';
        } else {
            $checkins = user_load_data('goals_checkins.json', []);
            $checkins[] = [
                'date' => $date,
                'weight' => $weight,
                'bodyFat' => $bodyFat,
                'waist' => $waist,
                'chest' => $chest,
                'hips' => $hips
            ];
            usort($checkins, function ($a, $b) {
                return strcmp($a['date'] ?? '', $b['date'] ?? '');
            });
            user_save_data('goals_checkins.json', $checkins);
            $messages[] = 'Check-in added.';
        }
    }
}

$goals = user_load_data('goals.json', []);
$checkins = user_load_data('goals_checkins.json', []);
$goalsHistory = user_load_data('goals_history.json', []);
$progress = user_load_data('progress.json', []);
$sessions = user_load_data('sessions.json', []);

usort($checkins, function ($a, $b) {
    return strcmp($a['date'] ?? '', $b['date'] ?? '');
});

usort($goalsHistory, function ($a, $b) {
    return strcmp($b['savedAt'] ?? '', $a['savedAt'] ?? '');
});

$latestCheckin = !empty($checkins) ? $checkins[count($checkins) - 1] : null;
$firstCheckin = !empty($checkins) ? $checkins[0] : null;

$weightGoal = $goals['weight'] ?? [];
$bodyGoal = $goals['body'] ?? [];
$strengthGoals = $goals['strength']['targets'] ?? [];
$frequencyGoal = $goals['frequency']['sessionsPerWeek'] ?? null;
$notes = $goals['notes'] ?? [];

$startWeight = $weightGoal['start'] ?? ($firstCheckin['weight'] ?? null);
$targetWeight = $weightGoal['target'] ?? null;
$currentWeight = $latestCheckin['weight'] ?? null;

$weightProgress = null;
if (is_numeric($startWeight) && is_numeric($targetWeight) && is_numeric($currentWeight)) {
    $startWeight = (float)$startWeight;
    $targetWeight = (float)$targetWeight;
    $currentWeight = (float)$currentWeight;
    if ($startWeight !== $targetWeight) {
        if ($targetWeight < $startWeight) {
            $weightProgress = ($startWeight - $currentWeight) / ($startWeight - $targetWeight);
        } else {
            $weightProgress = ($currentWeight - $startWeight) / ($targetWeight - $startWeight);
        }
        $weightProgress = max(0, min(1, $weightProgress));
    } else {
        $weightProgress = 1;
    }
}

$exerciseBests = [];
$exerciseLatest = [];
foreach ($progress as $entry) {
    $exercise = trim((string)($entry['exercise'] ?? ''));
    if ($exercise === '') {
        continue;
    }
    $timeValue = $entry['startTime'] ?? $entry['time'] ?? null;
    $timestamp = $timeValue ? strtotime($timeValue) : 0;
    if (!isset($exerciseLatest[$exercise]) || $timestamp > ($exerciseLatest[$exercise]['timestamp'] ?? 0)) {
        $exerciseLatest[$exercise] = [
            'timestamp' => $timestamp,
            'entry' => $entry
        ];
    }
}

foreach ($progress as $entry) {
    $exercise = trim((string)($entry['exercise'] ?? ''));
    if ($exercise === '') {
        continue;
    }
    $weight = $entry['weight'] ?? null;
    $reps = $entry['reps'] ?? null;
    if (is_numeric($weight)) {
        $weight = (float)$weight;
        if (!isset($exerciseBests[$exercise]['maxWeight']) || $weight > $exerciseBests[$exercise]['maxWeight']) {
            $exerciseBests[$exercise]['maxWeight'] = $weight;
        }
        $estimate = $weight;
        if (is_numeric($reps)) {
            $estimate = $weight * (1 + ((float)$reps / 30));
        }
        if (!isset($exerciseBests[$exercise]['max1rm']) || $estimate > $exerciseBests[$exercise]['max1rm']) {
            $exerciseBests[$exercise]['max1rm'] = $estimate;
        }
    }
}

$recentPRs = [];
foreach ($exerciseLatest as $exercise => $payload) {
    $entry = $payload['entry'] ?? [];
    $weight = $entry['weight'] ?? null;
    $reps = $entry['reps'] ?? null;
    $best = $exerciseBests[$exercise] ?? [];

    $latestWeight = is_numeric($weight) ? (float)$weight : null;
    $latest1rm = null;
    if (is_numeric($weight)) {
        $latest1rm = (float)$weight;
        if (is_numeric($reps)) {
            $latest1rm = (float)$weight * (1 + ((float)$reps / 30));
        }
    }

    $isWeightPr = $latestWeight !== null && isset($best['maxWeight']) && abs($latestWeight - $best['maxWeight']) < 0.01;
    $isOneRmPr = $latest1rm !== null && isset($best['max1rm']) && abs($latest1rm - $best['max1rm']) < 0.01;

    if ($isWeightPr || $isOneRmPr) {
        $recentPRs[] = [
            'exercise' => $exercise,
            'type' => $isOneRmPr ? '1RM' : 'Max Weight',
            'value' => $isOneRmPr ? $latest1rm : $latestWeight,
            'date' => $entry['startTime'] ?? $entry['time'] ?? null
        ];
    }
}

usort($recentPRs, function ($a, $b) {
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

$now = new DateTimeImmutable('now');
$startOfWeek = $now->modify('monday this week')->setTime(0, 0, 0);
$endOfWeek = $now->modify('sunday this week')->setTime(23, 59, 59);

$sessionDates = [];
$sourceEntries = !empty($sessions) ? $sessions : $progress;
foreach ($sourceEntries as $entry) {
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

$weeklySessions = count($sessionDates);
$frequencyProgress = null;
if (is_numeric($frequencyGoal) && $frequencyGoal > 0) {
    $frequencyProgress = min(1, $weeklySessions / (float)$frequencyGoal);
}

$weekLabels = [];
$weekCounts = [];
$entryDates = [];
foreach ($sourceEntries as $entry) {
    $timeValue = $entry['startTime'] ?? $entry['endTime'] ?? $entry['time'] ?? null;
    if (!$timeValue) {
        continue;
    }
    $timestamp = strtotime($timeValue);
    if ($timestamp === false) {
        continue;
    }
    $entryDates[] = [
        'timestamp' => $timestamp,
        'date' => date('Y-m-d', $timestamp)
    ];
}

for ($i = 7; $i >= 0; $i--) {
    $weekStart = $now->modify("-{$i} weeks")->modify('monday this week')->setTime(0, 0, 0);
    $weekEnd = $weekStart->modify('sunday this week')->setTime(23, 59, 59);
    $weekLabel = $weekStart->format('d M');

    $uniqueDays = [];
    foreach ($entryDates as $entry) {
        if ($entry['timestamp'] < $weekStart->getTimestamp() || $entry['timestamp'] > $weekEnd->getTimestamp()) {
            continue;
        }
        $uniqueDays[$entry['date']] = true;
    }

    $weekLabels[] = $weekLabel;
    $weekCounts[] = count($uniqueDays);
}

$strengthHit = false;
foreach ($strengthGoals as $target) {
    $exerciseName = $target['exercise'] ?? '';
    $goalType = $target['type'] ?? '1rm';
    $targetValue = $target['target'] ?? null;
    $best = $exerciseBests[$exerciseName] ?? [];
    $currentValue = $goalType === 'weight'
        ? ($best['maxWeight'] ?? null)
        : ($best['max1rm'] ?? null);
    if (is_numeric($targetValue) && is_numeric($currentValue) && (float)$currentValue >= (float)$targetValue) {
        $strengthHit = true;
        break;
    }
}

$weightHit = $weightProgress !== null && $weightProgress >= 1;
$frequencyHit = $frequencyProgress !== null && $frequencyProgress >= 1;
$goalHit = $weightHit || $frequencyHit || $strengthHit;

$recentCheckins = array_reverse(array_slice($checkins, -8));
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Goals</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="body-hub" data-user-id="<?= htmlspecialchars(user_get_current_id() ?? '') ?>">
    <header class="topbar">
        <div class="brand">Workout</div>
        <div class="welcome-banner" style="background:linear-gradient(90deg,#e6f7ee 60%,#fff7e6 100%);border-radius:18px;padding:18px 24px;margin:18px 0 0 0;box-shadow:0 2px 12px #ffd16622;">
            <h2 style="margin:0 0 6px 0;font-size:1.5em;color:#2e8b57;">Welcome back<?= $currentUser && !empty($currentUser['name']) ? ', ' . htmlspecialchars($currentUser['name']) : '' ?>!</h2>
            <div style="font-size:1.1em;color:#4bbf73;">Let’s build your best self. Your goals are about <b>you</b>—set intentions that excite and inspire.</div>
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
            $dir = rtrim(str_replace('goals.php', '', $script), '/');
            $base_url = $dir ? $dir . '/' : '/';
        }
        ?>
        <nav class="topnav">
            <div style="color:red;font-size:0.9em;">[DEBUG] base_url: <?= htmlspecialchars($base_url) ?></div>
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
                <a href="<?= $base_url ?>index.php" title="See your overall progress and recent activity">Progress Hub</a>
                <a href="<?= $base_url ?>session.php" title="Start and log a new workout session">Session Mode</a>
                <a href="<?= $base_url ?>dashboard.php" title="View charts and stats for your workouts">Dashboard</a>
                <a href="<?= $base_url ?>goals.php" class="active" title="Set and track your fitness goals">Goals</a>
                <a href="<?= $base_url ?>compare.php" title="Compare progress between users">Compare</a>
                <a href="<?= $base_url ?>users.php" title="Manage user profiles">Users</a>
                <a href="<?= $base_url ?>routines.php" title="Create and edit workout routines">Routines</a>
                <a href="<?= $base_url ?>tracked_sets.php" title="Browse all logged sets">Tracked Sets</a>
        </nav>
    </header>

    <main class="page">
        <section class="panel" id="goalPsychPanel">
            <h2 style="color:#4bbf73;">Identify Your Why</h2>
            <form id="whyForm" style="margin-bottom:1em;">
                <label for="whyInput" style="font-size:1.1em;color:#2e8b57;"><b>What motivates you to work out?</b></label><br>
                <input id="whyInput" name="why" type="text" style="width:90%;margin:0.5em 0;border:2px solid #4bbf73;background:#e6f7ee;" maxlength="120" placeholder="E.g. Feel stronger, reduce stress, be a role model...">
                <button class="btn btn-blue" type="submit">Save Motivation</button>
                <span id="whySavedMsg" style="color:#4bbf73;display:none;margin-left:1em;">Saved!</span>
            </form>
            <div id="whyDisplay" style="margin-bottom:1em;"></div>
                    <div style="font-size:0.98em;color:#6b7a8a;margin-bottom:0.5em;">Tip: Connecting your goals to your values makes them more powerful.</div>
        </section>
        <section class="panel" id="suggestionPanel">
            <h2 style="display:inline;color:#4f8cff;">Need a Nudge?</h2>
            <button id="toggleSuggestBtn" class="btn btn-small" style="margin-left:1em;background:#e3f0ff;color:#4f8cff;">Show</button>
            <div id="suggestionContent" style="display:none;margin-top:1em;"></div>
        </section>
        <section class="hero">
            <div>
                <p class="eyebrow">Targets & Trends</p>
                <h1 style="color:#2e8b57;">Your Goals</h1>
                <p class="lede">Set your targets and track progress across weight, strength, and consistency. Every step counts—celebrate your wins!</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-green" href="/session.php">Start Session</a>
                <a class="btn btn-blue" href="dashboard.php">Open Dashboard</a>
            </div>
        </section>

        <?php if ($goalHit): ?>
            <div class="celebrate-banner">
                <div>
                    <h3 style="color:#ffb700;">🎉 Goal Achieved!</h3>
                    <p class="affirm">You’ve reached a target—amazing work! Keep the momentum going and celebrate your progress.</p>
                </div>
                <span class="badge">Milestone</span>
            </div>
        <?php endif; ?>

        <?php foreach ($messages as $message): ?>
            <div class="notice success"><?= htmlspecialchars($message) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $error): ?>
            <div class="notice error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <section class="panel">
            <details class="collapsible" data-storage="goals_overview">
                <summary>
                    <div>
                        <h2>Progress Overview</h2>
                        <p class="muted">Automatic updates based on your tracked sets and latest check-in.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <div class="dashboard-grid">
                        <div class="subpanel">
                            <h3>Weight Progress</h3>
                            <p class="muted">Current: <?= $currentWeight !== null ? htmlspecialchars((string)$currentWeight) . ' lbs' : '-' ?></p>
                            <p class="muted">Target: <?= $targetWeight !== null ? htmlspecialchars((string)$targetWeight) . ' lbs' : '-' ?></p>
                            <?php if (!empty($notes['weight']['text'])): ?>
                                <p class="note">Note: <?= htmlspecialchars($notes['weight']['text']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($notes['weight']['reminder']['cadence']) && $notes['weight']['reminder']['cadence'] !== 'none'): ?>
                                <p class="note">Reminder: <?= htmlspecialchars(ucfirst($notes['weight']['reminder']['cadence'])) ?><?= $notes['weight']['reminder']['cadence'] === 'weekly' ? ' on ' . htmlspecialchars($notes['weight']['reminder']['day']) : '' ?></p>
                            <?php endif; ?>
                            <div class="goal-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $weightProgress !== null ? round($weightProgress * 100) : 0 ?>%"></div>
                                </div>
                                <span class="muted"><?= $weightProgress !== null ? round($weightProgress * 100) . '% to goal' : 'Set a start and target weight' ?></span>
                            </div>
                            <?php if (!empty($weightGoal['deadline'])): ?>
                                <p class="muted">Deadline: <?= htmlspecialchars($weightGoal['deadline']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="subpanel">
                            <h3>Weekly Frequency</h3>
                            <p class="muted">Sessions this week: <?= $weeklySessions ?></p>
                            <p class="muted">Goal: <?= $frequencyGoal !== null ? htmlspecialchars((string)$frequencyGoal) : '-' ?> sessions</p>
                            <?php if (!empty($notes['frequency']['text'])): ?>
                                <p class="note">Note: <?= htmlspecialchars($notes['frequency']['text']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($notes['frequency']['reminder']['cadence']) && $notes['frequency']['reminder']['cadence'] !== 'none'): ?>
                                <p class="note">Reminder: <?= htmlspecialchars(ucfirst($notes['frequency']['reminder']['cadence'])) ?><?= $notes['frequency']['reminder']['cadence'] === 'weekly' ? ' on ' . htmlspecialchars($notes['frequency']['reminder']['day']) : '' ?></p>
                            <?php endif; ?>
                            <div class="goal-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $frequencyProgress !== null ? round($frequencyProgress * 100) : 0 ?>%"></div>
                                </div>
                                <span class="muted"><?= $frequencyProgress !== null ? round($frequencyProgress * 100) . '% of weekly goal' : 'Set a sessions-per-week goal' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="subpanel">
                            <h3>Strength Targets</h3>
                            <?php if (!empty($notes['strength']['text'])): ?>
                                <p class="note">Note: <?= htmlspecialchars($notes['strength']['text']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($notes['strength']['reminder']['cadence']) && $notes['strength']['reminder']['cadence'] !== 'none'): ?>
                                <p class="note">Reminder: <?= htmlspecialchars(ucfirst($notes['strength']['reminder']['cadence'])) ?><?= $notes['strength']['reminder']['cadence'] === 'weekly' ? ' on ' . htmlspecialchars($notes['strength']['reminder']['day']) : '' ?></p>
                            <?php endif; ?>
                            <div class="table">
                                <div class="table-row table-head">
                                    <div>Exercise</div>
                                    <div>Current</div>
                                    <div>Target</div>
                                </div>
                                <?php if (empty($strengthGoals)): ?>
                                    <div class="table-row table-empty">Add strength targets to track PRs.</div>
                                <?php else: ?>
                                    <?php foreach ($strengthGoals as $target): ?>
                                        <?php
                                            $exerciseName = $target['exercise'] ?? '';
                                            $goalType = $target['type'] ?? '1rm';
                                            $targetValue = $target['target'] ?? null;
                                            $best = $exerciseBests[$exerciseName] ?? [];
                                            $currentValue = $goalType === 'weight'
                                                ? ($best['maxWeight'] ?? null)
                                                : ($best['max1rm'] ?? null);
                                            $latestEntry = $exerciseLatest[$exerciseName]['entry'] ?? null;
                                            $latestWeight = $latestEntry && is_numeric($latestEntry['weight'] ?? null)
                                                ? (float)$latestEntry['weight']
                                                : null;
                                            $latest1rm = null;
                                            if ($latestEntry && is_numeric($latestEntry['weight'] ?? null)) {
                                                $latest1rm = (float)$latestEntry['weight'];
                                                if (is_numeric($latestEntry['reps'] ?? null)) {
                                                    $latest1rm = (float)$latestEntry['weight'] * (1 + ((float)$latestEntry['reps'] / 30));
                                                }
                                            }
                                            $isRecentPr = false;
                                            if ($goalType === 'weight' && $latestWeight !== null && $currentValue !== null) {
                                                $isRecentPr = abs($latestWeight - (float)$currentValue) < 0.01;
                                            }
                                            if ($goalType === '1rm' && $latest1rm !== null && $currentValue !== null) {
                                                $isRecentPr = abs($latest1rm - (float)$currentValue) < 0.01;
                                            }
                                            $progressValue = null;
                                            if (is_numeric($targetValue) && is_numeric($currentValue) && (float)$targetValue > 0) {
                                                $progressValue = min(1, (float)$currentValue / (float)$targetValue);
                                            }
                                            $currentLabel = $currentValue !== null ? round($currentValue, 1) . ' lbs' : '-';
                                        ?>
                                        <div class="table-row">
                                            <div><?= htmlspecialchars($exerciseName) ?></div>
                                            <div>
                                                <?= $currentLabel ?>
                                                <?php if ($isRecentPr): ?>
                                                    <span class="badge">New PR</span>
                                                <?php endif; ?>
                                            </div>
                                            <div><?= $targetValue !== null ? round($targetValue, 1) . ' lbs' : '-' ?></div>
                                        </div>
                                        <div class="goal-progress">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $progressValue !== null ? round($progressValue * 100) : 0 ?>%"></div>
                                            </div>
                                            <span class="muted"><?= $progressValue !== null ? round($progressValue * 100) . '% to goal' : 'No data yet' ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="subpanel">
                            <h3>Body Metrics</h3>
                            <?php if (!empty($notes['body']['text'])): ?>
                                <p class="note">Note: <?= htmlspecialchars($notes['body']['text']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($notes['body']['reminder']['cadence']) && $notes['body']['reminder']['cadence'] !== 'none'): ?>
                                <p class="note">Reminder: <?= htmlspecialchars(ucfirst($notes['body']['reminder']['cadence'])) ?><?= $notes['body']['reminder']['cadence'] === 'weekly' ? ' on ' . htmlspecialchars($notes['body']['reminder']['day']) : '' ?></p>
                            <?php endif; ?>
                            <div class="table">
                                <div class="table-row table-head">
                                    <div>Metric</div>
                                    <div>Current</div>
                                    <div>Target</div>
                                </div>
                                <div class="table-row">
                                    <div>Body Fat %</div>
                                    <div><?= $latestCheckin['bodyFat'] ?? '-' ?></div>
                                    <div><?= $bodyGoal['bodyFatTarget'] ?? '-' ?></div>
                                </div>
                                <div class="table-row">
                                    <div>Waist (in)</div>
                                    <div><?= $latestCheckin['waist'] ?? '-' ?></div>
                                    <div><?= $bodyGoal['waistTarget'] ?? '-' ?></div>
                                </div>
                                <div class="table-row">
                                    <div>Chest (in)</div>
                                    <div><?= $latestCheckin['chest'] ?? '-' ?></div>
                                    <div><?= $bodyGoal['chestTarget'] ?? '-' ?></div>
                                </div>
                                <div class="table-row">
                                    <div>Hips (in)</div>
                                    <div><?= $latestCheckin['hips'] ?? '-' ?></div>
                                    <div><?= $bodyGoal['hipsTarget'] ?? '-' ?></div>
                                </div>
                            </div>
                            <p class="muted">Latest check-in: <?= $latestCheckin['date'] ?? '-' ?></p>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="subpanel">
                            <h3>Recent PRs</h3>
                            <?php if (empty($recentPRs)): ?>
                                <p class="muted">No PRs detected yet.</p>
                            <?php else: ?>
                                <div class="table">
                                    <div class="table-row table-head">
                                        <div>Exercise</div>
                                        <div>Type</div>
                                        <div>Value</div>
                                    </div>
                                    <?php foreach (array_slice($recentPRs, 0, 6) as $pr): ?>
                                        <div class="table-row">
                                            <div><?= htmlspecialchars($pr['exercise']) ?></div>
                                            <div><?= htmlspecialchars($pr['type']) ?></div>
                                            <div><?= round((float)$pr['value'], 1) ?> lbs</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="subpanel">
                            <h3>Goal History</h3>
                            <?php if (empty($goalsHistory)): ?>
                                <p class="muted">No saved goal history yet.</p>
                            <?php else: ?>
                                <div class="table">
                                    <div class="table-row table-head">
                                        <div>Saved</div>
                                        <div>Weight Target</div>
                                        <div>Weekly Sessions</div>
                                    </div>
                                    <?php foreach (array_slice($goalsHistory, 0, 6) as $history): ?>
                                        <?php
                                            $historyGoals = $history['goals'] ?? [];
                                            $historyWeight = $historyGoals['weight']['target'] ?? null;
                                            $historyFrequency = $historyGoals['frequency']['sessionsPerWeek'] ?? null;
                                        ?>
                                        <div class="table-row">
                                            <div><?= htmlspecialchars(date('d M Y', strtotime($history['savedAt'] ?? ''))) ?></div>
                                            <div><?= $historyWeight !== null ? $historyWeight . ' lbs' : '-' ?></div>
                                            <div><?= $historyFrequency !== null ? $historyFrequency . ' sessions' : '-' ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="goals_set">
                <summary>
                    <div>
                        <h2>Set Goals</h2>
                        <p class="muted">Update targets any time to keep your plan aligned.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <form method="POST" class="goal-form">
                        <input type="hidden" name="action" value="save_goals">
                        <div class="goal-grid">
                            <div class="control-card">
                                <h3>Weight Goal</h3>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Start Weight (lbs)</span>
                                        <input type="number" name="weight_start" step="0.1" value="<?= htmlspecialchars((string)($weightGoal['start'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Target Weight (lbs)</span>
                                        <input type="number" name="weight_target" step="0.1" value="<?= htmlspecialchars((string)($weightGoal['target'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Deadline</span>
                                        <input type="date" name="weight_deadline" value="<?= htmlspecialchars((string)($weightGoal['deadline'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Note</span>
                                        <textarea name="weight_note" rows="2" placeholder="Example: Cut to summer weight."><?= htmlspecialchars((string)($notes['weight']['text'] ?? '')) ?></textarea>
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Reminder</span>
                                        <select name="weight_reminder">
                                            <option value="none" <?= ($notes['weight']['reminder']['cadence'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
                                            <option value="daily" <?= ($notes['weight']['reminder']['cadence'] ?? 'none') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                            <option value="weekly" <?= ($notes['weight']['reminder']['cadence'] ?? 'none') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Weekly Day</span>
                                        <select name="weight_reminder_day">
                                            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                                                <option value="<?= $day ?>" <?= ($notes['weight']['reminder']['day'] ?? 'Mon') === $day ? 'selected' : '' ?>><?= $day ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </div>

                            <div class="control-card">
                                <h3>Body Metrics</h3>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Body Fat %</span>
                                        <input type="number" name="body_fat_target" step="0.1" value="<?= htmlspecialchars((string)($bodyGoal['bodyFatTarget'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Waist (in)</span>
                                        <input type="number" name="waist_target" step="0.1" value="<?= htmlspecialchars((string)($bodyGoal['waistTarget'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Chest (in)</span>
                                        <input type="number" name="chest_target" step="0.1" value="<?= htmlspecialchars((string)($bodyGoal['chestTarget'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Hips (in)</span>
                                        <input type="number" name="hips_target" step="0.1" value="<?= htmlspecialchars((string)($bodyGoal['hipsTarget'] ?? '')) ?>">
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Note</span>
                                        <textarea name="body_note" rows="2" placeholder="Example: Focus on mobility. "><?= htmlspecialchars((string)($notes['body']['text'] ?? '')) ?></textarea>
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Reminder</span>
                                        <select name="body_reminder">
                                            <option value="none" <?= ($notes['body']['reminder']['cadence'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
                                            <option value="daily" <?= ($notes['body']['reminder']['cadence'] ?? 'none') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                            <option value="weekly" <?= ($notes['body']['reminder']['cadence'] ?? 'none') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Weekly Day</span>
                                        <select name="body_reminder_day">
                                            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                                                <option value="<?= $day ?>" <?= ($notes['body']['reminder']['day'] ?? 'Mon') === $day ? 'selected' : '' ?>><?= $day ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </div>

                            <div class="control-card">
                                <h3>Workout Frequency</h3>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Sessions Per Week</span>
                                        <input type="number" name="sessions_per_week" min="1" step="1" value="<?= htmlspecialchars((string)($frequencyGoal ?? '')) ?>">
                                    </label>
                                </div>
                                <p class="muted">Counts unique training days this week.</p>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Note</span>
                                        <textarea name="frequency_note" rows="2" placeholder="Example: Two lifting days, one cardio."><?= htmlspecialchars((string)($notes['frequency']['text'] ?? '')) ?></textarea>
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Reminder</span>
                                        <select name="frequency_reminder">
                                            <option value="none" <?= ($notes['frequency']['reminder']['cadence'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
                                            <option value="daily" <?= ($notes['frequency']['reminder']['cadence'] ?? 'none') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                            <option value="weekly" <?= ($notes['frequency']['reminder']['cadence'] ?? 'none') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="field-row">
                                    <label class="field">
                                        <span>Weekly Day</span>
                                        <select name="frequency_reminder_day">
                                            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                                                <option value="<?= $day ?>" <?= ($notes['frequency']['reminder']['day'] ?? 'Mon') === $day ? 'selected' : '' ?>><?= $day ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="panel-header">
                            <h2>Strength Goals</h2>
                            <p class="muted">Track 1RM estimates or max weight goals by exercise.</p>
                        </div>
                        <div class="goal-note-row">
                            <label class="field">
                                <span>Note</span>
                                <textarea name="strength_note" rows="2" placeholder="Example: Focus on clean reps."><?= htmlspecialchars((string)($notes['strength']['text'] ?? '')) ?></textarea>
                            </label>
                            <label class="field">
                                <span>Reminder</span>
                                <select name="strength_reminder">
                                    <option value="none" <?= ($notes['strength']['reminder']['cadence'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
                                    <option value="daily" <?= ($notes['strength']['reminder']['cadence'] ?? 'none') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                    <option value="weekly" <?= ($notes['strength']['reminder']['cadence'] ?? 'none') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Weekly Day</span>
                                <select name="strength_reminder_day">
                                    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                                        <option value="<?= $day ?>" <?= ($notes['strength']['reminder']['day'] ?? 'Mon') === $day ? 'selected' : '' ?>><?= $day ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div id="strengthTargets" class="goal-rows">
                            <?php if (empty($strengthGoals)): ?>
                                <div class="goal-row">
                                    <input type="text" name="strength[0][exercise]" placeholder="Exercise">
                                    <select name="strength[0][type]">
                                        <option value="1rm">1RM Estimate</option>
                                        <option value="weight">Max Weight</option>
                                    </select>
                                    <input type="number" name="strength[0][target]" step="0.1" placeholder="Target lbs">
                                    <button type="button" class="btn btn-red btn-small remove-strength">Remove</button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($strengthGoals as $index => $target): ?>
                                    <div class="goal-row">
                                        <input type="text" name="strength[<?= (int)$index ?>][exercise]" value="<?= htmlspecialchars((string)($target['exercise'] ?? '')) ?>" placeholder="Exercise">
                                        <select name="strength[<?= (int)$index ?>][type]">
                                            <option value="1rm" <?= ($target['type'] ?? '1rm') === '1rm' ? 'selected' : '' ?>>1RM Estimate</option>
                                            <option value="weight" <?= ($target['type'] ?? '1rm') === 'weight' ? 'selected' : '' ?>>Max Weight</option>
                                        </select>
                                        <input type="number" name="strength[<?= (int)$index ?>][target]" step="0.1" value="<?= htmlspecialchars((string)($target['target'] ?? '')) ?>" placeholder="Target lbs">
                                        <button type="button" class="btn btn-red btn-small remove-strength">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="btn-row">
                            <button type="button" class="btn btn-blue" id="addStrengthRow">Add Strength Target</button>
                            <button type="submit" class="btn btn-green">Save Goals</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="goals_checkin">
                <summary>
                    <div>
                        <h2>Progress Check-in</h2>
                        <p class="muted">Log body metrics to keep your progress up to date.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <form method="POST" class="checkin-form">
                        <input type="hidden" name="action" value="add_checkin">
                        <div class="filter-grid">
                            <div class="control-input">
                                <label for="checkin_date">Date</label>
                                <input type="date" id="checkin_date" name="checkin_date" value="<?= htmlspecialchars($today) ?>">
                            </div>
                            <div class="control-input">
                                <label for="checkin_weight">Weight (lbs)</label>
                                <input type="number" id="checkin_weight" name="checkin_weight" step="0.1">
                            </div>
                            <div class="control-input">
                                <label for="checkin_body_fat">Body Fat %</label>
                                <input type="number" id="checkin_body_fat" name="checkin_body_fat" step="0.1">
                            </div>
                            <div class="control-input">
                                <label for="checkin_waist">Waist (in)</label>
                                <input type="number" id="checkin_waist" name="checkin_waist" step="0.1">
                            </div>
                            <div class="control-input">
                                <label for="checkin_chest">Chest (in)</label>
                                <input type="number" id="checkin_chest" name="checkin_chest" step="0.1">
                            </div>
                            <div class="control-input">
                                <label for="checkin_hips">Hips (in)</label>
                                <input type="number" id="checkin_hips" name="checkin_hips" step="0.1">
                            </div>
                        </div>
                        <div class="btn-row">
                            <button type="submit" class="btn btn-green">Add Check-in</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="goals_recent_checkins">
                <summary>
                    <div>
                        <h2>Recent Check-ins</h2>
                        <p class="muted">Latest eight check-ins for quick reference.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <?php if (empty($recentCheckins)): ?>
                        <p class="muted">No check-ins yet.</p>
                    <?php else: ?>
                        <div class="table">
                            <div class="table-row table-head">
                                <div>Date</div>
                                <div>Weight</div>
                                <div>Body Fat</div>
                                <div>Waist</div>
                                <div>Chest</div>
                                <div>Hips</div>
                            </div>
                            <?php foreach ($recentCheckins as $checkin): ?>
                                <div class="table-row">
                                    <div><?= htmlspecialchars((string)($checkin['date'] ?? '-')) ?></div>
                                    <div><?= $checkin['weight'] ?? '-' ?></div>
                                    <div><?= $checkin['bodyFat'] ?? '-' ?></div>
                                    <div><?= $checkin['waist'] ?? '-' ?></div>
                                    <div><?= $checkin['chest'] ?? '-' ?></div>
                                    <div><?= $checkin['hips'] ?? '-' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="goals_trends">
                <summary>
                    <div>
                        <h2>Check-in Trends</h2>
                        <p class="muted">Visualize weight and body metric changes over time.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <div class="goal-chart-grid">
                        <div class="chart-container">
                            <canvas id="weightTrendChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="bodyTrendChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="frequencyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </details>
        </section>
    </main>

<script src="collapsible.js"></script>
<script src="user_tab.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Psychological goal prompt
    function loadWhy() {
        let why = localStorage.getItem('userWhy')||'';
        if (why) document.getElementById('whyDisplay').innerHTML = `<b>Your motivation:</b> "${why.replace(/</g,'&lt;')}"`;
        else document.getElementById('whyDisplay').innerHTML = '';
    }
    document.getElementById('whyForm').onsubmit = function(e) {
        e.preventDefault();
        let val = document.getElementById('whyInput').value.trim();
        if (val) {
            localStorage.setItem('userWhy', val);
            document.getElementById('whySavedMsg').style.display = 'inline';
            loadWhy();
            setTimeout(()=>{document.getElementById('whySavedMsg').style.display='none';},2000);
        }
    };
    loadWhy();

    // Gentle suggestions logic
    const suggestionBtn = document.getElementById('toggleSuggestBtn');
    const suggestionContent = document.getElementById('suggestionContent');
    let suggestionsVisible = false;
    suggestionBtn.onclick = function() {
        suggestionsVisible = !suggestionsVisible;
        suggestionContent.style.display = suggestionsVisible ? 'block' : 'none';
        suggestionBtn.textContent = suggestionsVisible ? 'Hide' : 'Show';
        if (suggestionsVisible) showSuggestions();
    };
    function showSuggestions() {
        // Analyze progress for gentle suggestions
        let progress = [];
        try { progress = JSON.parse(localStorage.getItem('progressData')||'[]'); } catch(e) {}
        if (!progress.length) suggestionContent.innerHTML = '<p class="gentle">No workout data yet. Try logging a session to get personalized ideas!</p>';
        else {
            // Find under-trained muscle groups/exercises
            let counts = {};
            progress.forEach(p => { if (p.exercise) counts[p.exercise] = (counts[p.exercise]||0)+1; });
            let least = Object.entries(counts).sort((a,b)=>a[1]-b[1]);
            let msg = '';
            if (least.length > 1) {
                msg = `<b>Gentle nudge:</b> <span style='color:#4f8cff;'>Try a little more <b>${least[0][0]}</b></span> (only ${least[0][1]} set${least[0][1]===1?'':'s'}). Variety helps!`;
            } else {
                msg = '<span class="affirm">Keep up the balanced training! You’re doing great.</span>';
            }
            suggestionContent.innerHTML = `<p>${msg}</p><p class='gentle'>Suggestions are gentle and based on your logged sets.</p>`;
        }
    }
    // Save progress to localStorage for JS analysis
    try { localStorage.setItem('progressData', JSON.stringify(<?php echo json_encode($progress, JSON_PRETTY_PRINT); ?>)); } catch(e) {}
document.addEventListener('DOMContentLoaded', () => {
    const strengthTargets = document.getElementById('strengthTargets');
    const addStrengthRow = document.getElementById('addStrengthRow');

    if (!strengthTargets || !addStrengthRow) return;

    let rowIndex = strengthTargets.querySelectorAll('.goal-row').length;

    const updateRemoveButtons = () => {
        strengthTargets.querySelectorAll('.remove-strength').forEach(button => {
            button.addEventListener('click', () => {
                button.closest('.goal-row').remove();
            });
        });
    };

    addStrengthRow.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'goal-row';
        row.innerHTML = `
            <input type="text" name="strength[${rowIndex}][exercise]" placeholder="Exercise">
            <select name="strength[${rowIndex}][type]">
                <option value="1rm">1RM Estimate</option>
                <option value="weight">Max Weight</option>
            </select>
            <input type="number" name="strength[${rowIndex}][target]" step="0.1" placeholder="Target lbs">
            <button type="button" class="btn btn-red btn-small remove-strength">Remove</button>
        `;
        strengthTargets.appendChild(row);
        rowIndex += 1;
        updateRemoveButtons();
    });

    updateRemoveButtons();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const checkinData = <?php echo json_encode($checkins, JSON_PRETTY_PRINT); ?>;
    const frequencyLabels = <?php echo json_encode($weekLabels, JSON_PRETTY_PRINT); ?>;
    const frequencyCounts = <?php echo json_encode($weekCounts, JSON_PRETTY_PRINT); ?>;
    const hasCheckins = Array.isArray(checkinData) && checkinData.length > 0;
    if (hasCheckins) {
        const labels = checkinData.map(entry => entry.date || '-');
        const weights = checkinData.map(entry => entry.weight ?? null);
        const bodyFat = checkinData.map(entry => entry.bodyFat ?? null);
        const waist = checkinData.map(entry => entry.waist ?? null);
        const chest = checkinData.map(entry => entry.chest ?? null);
        const hips = checkinData.map(entry => entry.hips ?? null);

        const weightCtx = document.getElementById('weightTrendChart');
        if (weightCtx) {
            new Chart(weightCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Weight (lbs)',
                            data: weights,
                            borderColor: '#1f7a4f',
                            fill: false
                        }
                    ]
                },
                options: { responsive: true }
            });
        }

        const bodyCtx = document.getElementById('bodyTrendChart');
        if (bodyCtx) {
            new Chart(bodyCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Body Fat %',
                            data: bodyFat,
                            borderColor: '#2c64e5',
                            fill: false
                        },
                        {
                            label: 'Waist (in)',
                            data: waist,
                            borderColor: '#f4b66d',
                            fill: false
                        },
                        {
                            label: 'Chest (in)',
                            data: chest,
                            borderColor: '#8a5a00',
                            fill: false
                        },
                        {
                            label: 'Hips (in)',
                            data: hips,
                            borderColor: '#6b6b6b',
                            fill: false
                        }
                    ]
                },
                options: { responsive: true }
            });
        }
    }

    const frequencyCtx = document.getElementById('frequencyTrendChart');
    if (frequencyCtx) {
        new Chart(frequencyCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: frequencyLabels,
                datasets: [
                    {
                        label: 'Sessions per Week',
                        data: frequencyCounts,
                        backgroundColor: 'rgba(31, 122, 79, 0.35)',
                        borderColor: '#1f7a4f',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }
});
</script>
</body>
</html>
