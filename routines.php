
<?php
require_once __DIR__ . "/user_context.php";
user_bootstrap();
$currentUser = user_get_current();

$routinesFile = __DIR__ . "/data/routines.json";
$exercisesFile = __DIR__ . "/data/exercises.json";

$routines = file_exists($routinesFile) ? json_decode(file_get_contents($routinesFile), true) : [];
$exercises = file_exists($exercisesFile) ? json_decode(file_get_contents($exercisesFile), true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Routines</title>
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
            $dir = rtrim(str_replace('routines.php', '', $script), '/');
            $base_url = $dir ? $dir . '/' : '/';
        }
        ?>
        <nav class="topnav">
            <a class="user-indicator" href="<?= $base_url ?>users.php">Active: <?= htmlspecialchars($currentUser['name'] ?? 'User') ?></a>
            <a href="<?= $base_url ?>index.php" title="See your overall progress and recent activity">Progress Hub</a>
            <a href="<?= $base_url ?>session.php" title="Start and log a new workout session">Session Mode</a>
            <a href="<?= $base_url ?>dashboard.php" title="View charts and stats for your workouts">Dashboard</a>
            <a href="<?= $base_url ?>goals.php" title="Set and track your fitness goals">Goals</a>
            <a href="<?= $base_url ?>compare.php" title="Compare progress between users">Compare</a>
            <a href="<?= $base_url ?>users.php" title="Manage user profiles">Users</a>
            <a href="<?= $base_url ?>routines.php" class="active" title="Create and edit workout routines">Routines</a>
            <a href="<?= $base_url ?>tracked_sets.php" title="Browse all logged sets">Tracked Sets</a>
                <a class="user-indicator" href="/Workout/users.php">Active: <?= htmlspecialchars($currentUser['name'] ?? 'User') ?></a>
                <a href="/Workout/index.php" title="See your overall progress and recent activity">Progress Hub</a>
                <a href="/Workout/session.php" title="Start and log a new workout session">Session Mode</a>
                <a href="/Workout/dashboard.php" title="View charts and stats for your workouts">Dashboard</a>
                <a href="/Workout/goals.php" title="Set and track your fitness goals">Goals</a>
                <a href="/Workout/compare.php" title="Compare progress between users">Compare</a>
                <a href="/Workout/users.php" title="Manage user profiles">Users</a>
                <a href="/Workout/routines.php" class="active" title="Create and edit workout routines">Routines</a>
                <a href="/Workout/tracked_sets.php" title="Browse all logged sets">Tracked Sets</a>
        </nav>
    </header>

    <main class="page">
        <section class="hero compact">
            <div>
                <p class="eyebrow">Plan builder</p>
                <h1>Routine Management</h1>
                <p class="lede">Create, adjust, and launch routines that match your training focus.</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-green" href="session.php">Start Session</a>
                <a class="btn btn-blue" href="dashboard.php">Open Dashboard</a>
            </div>
        </section>

        <section class="panel routines-section">
            <details class="collapsible" data-storage="routines_saved">
                <summary>
                    <div>
                        <h2>Saved Routines</h2>
                        <p class="muted">Pick one to load into the dashboard or session mode.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <?php if (empty($routines)): ?>
                        <p class="no-data">No routines saved yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr class="routine-row">
                                    <th>Name</th>
                                    <th>Exercises</th>
                                    <th>Timestamp</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routines as $id => $routine): ?>
                                <?php
                                    $groups = [
                                        'weight' => [],
                                        'equipment' => [],
                                        'bodyweight' => [],
                                        'stretch' => []
                                    ];
                                    foreach ($routine['exercises'] as $ex) {
                                        $category = $ex['category'] ?? null;
                                        if (!$category) {
                                            $category = ($ex['weight'] ?? 0) > 0 ? 'weight' : 'bodyweight';
                                        }
                                        if (!isset($groups[$category])) {
                                            $groups[$category] = [];
                                        }
                                        $groups[$category][] = $ex;
                                    }
                                    $totalExercises = count($routine['exercises']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="routine-name"><?= htmlspecialchars($routine['name']) ?></div>
                                        <div class="routine-meta">
                                            <span class="meta-pill">Total: <?= $totalExercises ?></span>
                                            <?php foreach ($groups as $label => $items): ?>
                                                <?php if (!empty($items)): ?>
                                                    <span class="meta-pill"><?= ucfirst($label) ?>: <?= count($items) ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="routine-groups">
                                            <?php foreach ($groups as $label => $items): ?>
                                                <?php if (!empty($items)): ?>
                                                    <div class="routine-group">
                                                        <h4><?= ucfirst($label) ?></h4>
                                                        <div class="pill-wrap">
                                                            <?php foreach ($items as $ex): ?>
                                                                <span class="exercise-pill"><?= htmlspecialchars($ex['name']) ?> (<?= $ex['sets'] ?>x<?= $ex['reps'] ?>)</span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($routine['timestamp']) ?></td>
                                    <td class="controls">
                                        <button onclick="loadRoutine('<?= $id ?>')" class="btn btn-green">Load</button>
                                        <button onclick="editRoutine('<?= $id ?>')" class="btn btn-blue">Edit</button>
                                        <button onclick="deleteRoutine('<?= $id ?>')" class="btn btn-red">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </details>
        </section>

        <section class="panel create-section">
            <details class="collapsible" data-storage="routines_create">
                <summary>
                    <div>
                        <h2>Create a New Routine</h2>
                        <p class="muted">Select exercises, set targets, and save.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <div id="editBanner" class="edit-banner" hidden>
                        Editing routine: <span id="editName"></span>
                    </div>
                    <div class="create-grid">
                        <div>
                            <h3 class="section-title">Routine Name</h3>
                            <div class="field-row">
                                <input type="text" id="routineName" placeholder="Enter routine name" required>
                            </div>

                            <h3 class="section-title">Select Exercises</h3>
                            <div class="field-row">
                                <input type="text" id="exerciseSearch" placeholder="Search exercises..." onkeyup="filterExercises()">
                            </div>

                            <div class="exercise-list">
                                <?php foreach ($exercises as $category => $exerciseList): ?>
                                    <details class="exercise-group" open>
                                        <summary><?= ucfirst($category) ?></summary>
                                        <div class="exercise-group-body">
                                            <?php foreach ($exerciseList as $exercise): ?>
                                                <label class="exercise-checkbox">
                                                    <input type="checkbox" value="<?= htmlspecialchars($exercise) ?>" onchange="toggleExerciseInputs(this)">
                                                    <?= htmlspecialchars($exercise) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <h3 class="section-title">Selected Exercises</h3>
                            <p class="muted section-subtitle">Adjust sets, reps, and weight before saving.</p>
                            <div id="selectedExercises" class="selected-exercises"></div>
                            <div class="btn-row">
                                <button id="saveRoutineBtn" onclick="saveRoutine()" class="btn btn-green">Save Routine</button>
                                <button id="updateRoutineBtn" onclick="updateRoutine()" class="btn btn-blue" style="display:none;">Update Routine</button>
                                <button id="cancelEditBtn" onclick="cancelEdit()" class="btn btn-red" style="display:none;">Cancel Edit</button>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        </section>
    </main>

<!-- ✅ JavaScript -->
<script src="collapsible.js"></script>
<script src="user_tab.js"></script>
<script>
function filterExercises() {
    const term = document.getElementById('exerciseSearch').value.toLowerCase();
    document.querySelectorAll('.exercise-checkbox').forEach(label => {
        label.style.display = label.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
}

window.currentEditId = null;

function getExerciseKey(name) {
    const base = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    return base || `exercise_${Date.now()}`;
}

function addExerciseInput(name, sets = 3, reps = 10, weight = 0) {
    const container = document.getElementById('selectedExercises');
    const key = getExerciseKey(name);
    if (document.getElementById('exercise-' + key)) return;

    const div = document.createElement('div');
    div.className = 'exercise-input';
    div.id = 'exercise-' + key;
    div.dataset.exerciseName = name;
    div.innerHTML = `
        <strong>${name}</strong>
        Sets: <input type="number" min="1" value="${sets}" class="sets">
        Reps: <input type="number" min="1" value="${reps}" class="reps">
        Weight: <input type="number" min="0" value="${weight}" class="weight"> lbs
    `;
    container.appendChild(div);
}

function toggleExerciseInputs(checkbox) {
    if (checkbox.checked) {
        addExerciseInput(checkbox.value);
    } else {
        const key = getExerciseKey(checkbox.value);
        document.getElementById('exercise-' + key)?.remove();
    }
}

function collectExercises() {
    return Array.from(document.querySelectorAll('#selectedExercises .exercise-input')).map(div => ({
        name: div.dataset.exerciseName || div.querySelector('strong').textContent,
        sets: parseInt(div.querySelector('.sets').value),
        reps: parseInt(div.querySelector('.reps').value),
        weight: parseFloat(div.querySelector('.weight').value)
    }));
}

function saveRoutine() {
    const name = document.getElementById('routineName').value.trim();
    if (!name) {
        alert("Please enter a routine name.");
        return;
    }

    const exercises = collectExercises();

    fetch('save_routine.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, exercises })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message || data.error);
        location.reload();
    })
    .catch(err => console.error('Error saving routine:', err));
}

function updateRoutine() {
    if (!window.currentEditId) return;
    const name = document.getElementById('routineName').value.trim();
    if (!name) {
        alert("Please enter a routine name.");
        return;
    }

    const exercises = collectExercises();

    fetch('update_routine.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: window.currentEditId, name, exercises })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message || data.error);
        location.reload();
    })
    .catch(err => console.error('Error updating routine:', err));
}

function cancelEdit() {
    window.currentEditId = null;
    document.getElementById('routineName').value = '';
    document.getElementById('selectedExercises').innerHTML = '';
    document.querySelectorAll('.exercise-checkbox input[type="checkbox"]').forEach(input => {
        input.checked = false;
    });
    document.querySelectorAll('.routine-row.editing').forEach(row => row.classList.remove('editing'));
    document.getElementById('editBanner').hidden = true;
    document.getElementById('saveRoutineBtn').style.display = '';
    document.getElementById('updateRoutineBtn').style.display = 'none';
    document.getElementById('cancelEditBtn').style.display = 'none';
}

function loadRoutine(id) {
    window.location.href = "dashboard.php?id=" + encodeURIComponent(id);
}

function editRoutine(id) {
    fetch('get_routine.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.data) {
            alert(data.error || 'Failed to load routine.');
            return;
        }

        window.currentEditId = id;
        const routine = data.data;

        document.getElementById('routineName').value = routine.name || '';
        document.getElementById('selectedExercises').innerHTML = '';
        document.querySelectorAll('.exercise-checkbox input[type="checkbox"]').forEach(input => {
            input.checked = false;
        });
        document.querySelectorAll('.exercise-group').forEach(group => {
            group.open = true;
        });
        document.querySelectorAll('.routine-row.editing').forEach(row => row.classList.remove('editing'));
        document.querySelector(`.routine-row[data-routine-id="${id}"]`)?.classList.add('editing');

        (routine.exercises || []).forEach(ex => {
            addExerciseInput(ex.name, ex.sets, ex.reps, ex.weight);
            document.querySelectorAll('.exercise-checkbox input[type="checkbox"]').forEach(input => {
                if (input.value === ex.name) {
                    input.checked = true;
                }
            });
        });

        document.getElementById('editBanner').hidden = false;
        document.getElementById('editName').textContent = routine.name || '';
        document.getElementById('saveRoutineBtn').style.display = 'none';
        document.getElementById('updateRoutineBtn').style.display = '';
        document.getElementById('cancelEditBtn').style.display = '';
    })
    .catch(err => console.error('Error loading routine:', err));
}

function deleteRoutine(id) {
    if (!confirm("Are you sure you want to delete this routine?")) return;
    fetch('delete_routine.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message || data.error);
        location.reload();
    })
    .catch(err => console.error('Error deleting routine:', err));
}
</script>
</body>
</html>
