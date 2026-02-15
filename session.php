<?php
require_once __DIR__ . "/user_context.php";
user_bootstrap();
$currentUser = user_get_current();
$activeUsers = array_filter(user_get_users(), function ($user) {
    return empty($user['archivedAt']);
});

$routinesFile = __DIR__ . "/data/routines.json";
$routines = file_exists($routinesFile) ? json_decode(file_get_contents($routinesFile), true) : [];
$routines = is_array($routines) ? $routines : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Mode</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="body-session" data-user-id="<?= htmlspecialchars(user_get_current_id() ?? '') ?>">
    <header class="topbar">
        <button id="darkModeToggle" class="btn btn-small" style="float:right;margin:0.5em 1em 0 0;">🌙 Dark Mode</button>
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
            $dir = rtrim(str_replace('session.php', '', $script), '/');
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
                <a href="/Workout/index.php" title="See your overall progress and recent activity">Progress Hub</a>
                <a href="/Workout/session.php" class="active" title="Start and log a new workout session">Session Mode</a>
                <a href="/Workout/dashboard.php" title="View charts and stats for your workouts">Dashboard</a>
                <a href="/Workout/goals.php" title="Set and track your fitness goals">Goals</a>
                <a href="/Workout/compare.php" title="Compare progress between users">Compare</a>
                <a href="/Workout/users.php" title="Manage user profiles">Users</a>
                <a href="/Workout/routines.php" title="Create and edit workout routines">Routines</a>
                <a href="/Workout/tracked_sets.php" title="Browse all logged sets">Tracked Sets</a>
        </nav>
    </header>

    <main class="page">
        <!-- End-of-Session Popup -->
        <div id="sessionEndPopup" class="popup" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;">
            <div style="background:#fff;padding:2em 2.5em;border-radius:1em;max-width:95vw;width:400px;text-align:center;box-shadow:0 8px 32px #0002;position:relative;">
                <button id="closeSessionEndPopup" style="position:absolute;top:0.5em;right:0.5em;background:none;border:none;font-size:1.5em;cursor:pointer;">&times;</button>
                <div id="popupBadges" style="margin-bottom:1em;"></div>
                <h2 id="popupEncouragement">Awesome!</h2>
                <div id="popupSummary" style="margin:1em 0;"></div>
                <div id="popupComparison" style="color:#555;font-size:1em;"></div>
                <div id="popupQuote" style="margin-top:1.5em;font-style:italic;color:#888;"></div>
                <div style="margin-top:1.5em;">
                    <a href="dashboard.php" class="btn btn-blue">See Dashboard</a>
                    <a href="goals.php" class="btn btn-green">Check Goals</a>
                </div>
            </div>
        </div>
        <section class="hero">
            <div>
                <p class="eyebrow">Focus mode</p>
                <h1>Session Mode</h1>
                <p class="lede">Run a routine with timers, clean set logging, and a fast end-of-session recap.</p>
            </div>
            <div class="hero-actions">
                <label class="field">
                    <span>Session</span>
                    <select id="sessionDraftSelect">
                        <option value="">New session...</option>
                    </select>
                </label>
                <label class="field">
                    <span>Routine</span>
                    <select id="routineSelect">
                        <option value="">Select a routine...</option>
                        <?php foreach ($routines as $id => $routine): ?>
                            <option value="<?= htmlspecialchars((string)$id) ?>"><?= htmlspecialchars($routine['name'] ?? 'Routine') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="btn-row">
                    <button id="newDraftBtn" class="btn btn-blue" type="button">New Draft</button>
                    <button id="suggestRoutineBtn" class="btn btn-orange" type="button">Suggest Routine</button>
                    <button id="randomRoutineBtn" class="btn btn-purple" type="button">Random Routine</button>
                    <button id="startSessionBtn" class="btn btn-green">Start Session</button>
                    <button id="endSessionBtn" class="btn btn-red" disabled>End Session</button>
                </div>
                <p id="sessionStatus" class="muted session-status">No active session.</p>
            </div>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="session_metrics">
                <summary>
                    <div>
                        <h2>Session Metrics</h2>
                        <p class="muted">Track time, sets, volume, and rest without leaving the flow.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <div class="stat-grid">
                        <div class="stat-card">
                            <p>Elapsed</p>
                            <h3 id="elapsedTime">00:00</h3>
                        </div>
                        <div class="stat-card">
                            <p>Sets Logged</p>
                            <h3 id="setsLogged">0</h3>
                        </div>
                        <div class="stat-card">
                            <p>Total Volume</p>
                            <h3 id="volumeTotal">0</h3>
                        </div>
                        <div class="stat-card">
                            <p>Rest Timer</p>
                            <div class="rest-row">
                                <input type="number" id="restSeconds" min="10" value="60">
                                <button id="restStartBtn" class="btn btn-blue" type="button">Start</button>
                            </div>
                            <div id="restCountdown" class="muted">00:00</div>
                        </div>
                    </div>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="session_flow">
                <summary>
                    <div>
                        <h2>Workout Flow</h2>
                        <p class="muted">Tap a set to start, tap again to complete and log it.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <div id="routineBoard" class="routine-board empty">
                        <p>Select a routine to load your sets.</p>
                    </div>
                </div>
            </details>
        </section>

        <section class="panel">
            <details class="collapsible" data-storage="session_notes">
                <summary>
                    <div>
                        <h2>Session Notes</h2>
                        <p class="muted">Optional notes saved with the session.</p>
                    </div>
                </summary>
                <div class="collapsible-body">
                    <textarea id="sessionNotes" rows="4" placeholder="How did it feel? Any PRs, tweaks, or reminders..."></textarea>
                    <div class="btn-row">
                        <button id="saveSessionBtn" class="btn btn-blue" disabled>Save Session</button>
                    </div>
                </div>
            </details>
        </section>
    </main>

    <script src="collapsible.js"></script>
    <script src="user_tab.js"></script>
    <script>
    // Dark mode toggle
    const darkModeBtn = document.getElementById('darkModeToggle');
    function setDarkMode(on) {
        document.body.classList.toggle('dark-mode', on);
        localStorage.setItem('darkMode', on ? '1' : '0');
        darkModeBtn.textContent = on ? '☀️ Light Mode' : '🌙 Dark Mode';
    }
    darkModeBtn.onclick = () => setDarkMode(!document.body.classList.contains('dark-mode'));
    if (localStorage.getItem('darkMode') === '1') setDarkMode(true);
    // Routine suggestion/random logic
    document.getElementById('suggestRoutineBtn').onclick = function() {
        // Find least-used routine from localStorage sessionHistory
        let sessionHistory = [];
        try { sessionHistory = JSON.parse(localStorage.getItem('sessionHistory')||'[]'); } catch(e) {}
        let routineCounts = {};
        for (let id in routines) routineCounts[id] = 0;
        sessionHistory.forEach(s => {
            if (s.routineId && routineCounts.hasOwnProperty(s.routineId)) routineCounts[s.routineId]++;
        });
        let leastUsed = Object.entries(routineCounts).sort((a,b)=>a[1]-b[1])[0];
        if (leastUsed) {
            routineSelect.value = leastUsed[0];
            alert('Suggested routine: ' + (routines[leastUsed[0]].name||'Routine'));
        } else {
            alert('No routines found.');
        }
    };
    document.getElementById('randomRoutineBtn').onclick = function() {
        let keys = Object.keys(routines);
        if (!keys.length) { alert('No routines found.'); return; }
        let pick = keys[Math.floor(Math.random()*keys.length)];
        routineSelect.value = pick;
        alert('Random routine: ' + (routines[pick].name||'Routine'));
    };
        const routines = <?php echo json_encode($routines, JSON_PRETTY_PRINT); ?>;

        const sessionDraftSelect = document.getElementById('sessionDraftSelect');
        const routineSelect = document.getElementById('routineSelect');
        const routineBoard = document.getElementById('routineBoard');
        const startSessionBtn = document.getElementById('startSessionBtn');
        const endSessionBtn = document.getElementById('endSessionBtn');
        const saveSessionBtn = document.getElementById('saveSessionBtn');
        const newDraftBtn = document.getElementById('newDraftBtn');
        const sessionStatusEl = document.getElementById('sessionStatus');
        const elapsedTimeEl = document.getElementById('elapsedTime');
        const setsLoggedEl = document.getElementById('setsLogged');
        const volumeTotalEl = document.getElementById('volumeTotal');
        const restSecondsEl = document.getElementById('restSeconds');
        const restStartBtn = document.getElementById('restStartBtn');
        const restCountdownEl = document.getElementById('restCountdown');
        const sessionNotesEl = document.getElementById('sessionNotes');

        let sessionId = null;
        let routineId = null;
        let routineName = null;
        let sessionStart = null;
        let sessionEnd = null;
        let sessionTimer = null;
        let restTimer = null;
        let restTimerStartedAt = null;
        let restTimerDuration = 0;
        let totalSets = 0;
        let totalVolume = 0;
        let isSaving = false;
        let sessionEnded = false;
        let stateSaveTimer = null;

        function getPageUserId() {
            const bodyUserId = document.body ? document.body.dataset.userId : '';
            return bodyUserId || window.ACTIVE_USER_ID || 'default';
        }

        function getDraftsKey() {
            return `workout_session_drafts_${getPageUserId()}`;
        }

        function getActiveDraftKey() {
            return `workout_session_active_${getPageUserId()}`;
        }

        function loadDrafts() {
            const raw = sessionStorage.getItem(getDraftsKey());
            if (!raw) {
                return [];
            }
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function saveDrafts(drafts) {
            sessionStorage.setItem(getDraftsKey(), JSON.stringify(drafts));
        }

        function getActiveDraftId() {
            return sessionStorage.getItem(getActiveDraftKey());
        }

        function setActiveDraftId(draftId) {
            if (draftId) {
                sessionStorage.setItem(getActiveDraftKey(), draftId);
            } else {
                sessionStorage.removeItem(getActiveDraftKey());
            }
        }

        function buildDraftLabel(draft) {
            const name = draft?.state?.routineName || 'Session';
            const start = draft?.state?.sessionStart ? new Date(draft.state.sessionStart).toLocaleString() : '';
            return start ? `${name} - ${start}` : name;
        }

        function clearAllDrafts() {
            saveDrafts([]);
            setActiveDraftId(null);
            buildDraftOptions([], null);
            sessionDraftSelect.value = '';
        }

        function escapeSelector(value) {
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }
            return String(value).replace(/"/g, '\\"');
        }

        function collectRowState() {
            return Array.from(document.querySelectorAll('.set-row')).map(row => {
                const weightInput = row.querySelector('.weight-input');
                const repsInput = row.querySelector('.reps-input');
                const effortInput = row.querySelector('.effort-input');
                const statusEl = row.querySelector('.status');
                const button = row.querySelector('.set-action');
                return {
                    exercise: row.dataset.exercise,
                    set: row.dataset.set,
                    startedAt: row.dataset.startedAt || '',
                    weight: weightInput ? weightInput.value : '',
                    reps: repsInput ? repsInput.value : '',
                    effort: effortInput ? effortInput.value : '',
                    status: statusEl ? statusEl.textContent : '',
                    done: statusEl ? statusEl.classList.contains('done') : false,
                    buttonText: button ? button.textContent : '',
                    buttonDisabled: button ? button.disabled : false
                };
            });
        }

        function applyRowState(rows) {
            if (!Array.isArray(rows)) {
                return;
            }
            rows.forEach(state => {
                if (!state || !state.exercise || !state.set) {
                    return;
                }
                const selector = `.set-row[data-exercise="${escapeSelector(state.exercise)}"][data-set="${escapeSelector(state.set)}"]`;
                const row = document.querySelector(selector);
                if (!row) {
                    return;
                }
                row.dataset.startedAt = state.startedAt || '';
                const weightInput = row.querySelector('.weight-input');
                const repsInput = row.querySelector('.reps-input');
                const effortInput = row.querySelector('.effort-input');
                const statusEl = row.querySelector('.status');
                const button = row.querySelector('.set-action');
                if (weightInput && state.weight !== undefined) weightInput.value = state.weight;
                if (repsInput && state.reps !== undefined) repsInput.value = state.reps;
                if (effortInput && state.effort !== undefined) effortInput.value = state.effort;
                if (statusEl && state.status !== undefined) {
                    statusEl.textContent = state.status;
                    statusEl.classList.toggle('done', !!state.done);
                }
                if (button) {
                    button.textContent = state.buttonText || button.textContent;
                    button.disabled = !!state.buttonDisabled;
                }
            });
        }

        function buildDraftOptions(drafts, activeId) {
            sessionDraftSelect.innerHTML = '<option value="">New session...</option>';
            drafts.forEach(draft => {
                const option = document.createElement('option');
                option.value = draft.id;
                option.textContent = buildDraftLabel(draft);
                if (activeId && draft.id === activeId) {
                    option.selected = true;
                }
                sessionDraftSelect.appendChild(option);
            });
        }

        function loadDraftState(state) {
            if (!state) {
                return;
            }
            sessionId = state.sessionId || null;
            routineId = state.routineId || null;
            routineName = state.routineName || null;
            sessionStart = state.sessionStart ? new Date(state.sessionStart) : null;
            sessionEnd = state.sessionEnd ? new Date(state.sessionEnd) : null;
            sessionEnded = !!state.sessionEnded || !!sessionEnd;
            totalSets = Number.isFinite(state.totalSets) ? state.totalSets : 0;
            totalVolume = Number.isFinite(state.totalVolume) ? state.totalVolume : 0;
            sessionNotesEl.value = state.notes || '';
            restSecondsEl.value = state.restSeconds || restSecondsEl.value;
            if (restTimer) clearInterval(restTimer);
            restTimer = null;
            restTimerStartedAt = null;
            restTimerDuration = 0;
            if (state.restTimer && state.restTimer.duration) {
                restTimerDuration = state.restTimer.duration;
                if (state.restTimer.startedAt && state.restTimer.remaining > 0) {
                    const elapsedSinceSave = Math.floor((Date.now() - state.restTimer.startedAt) / 1000);
                    const remaining = Math.max(0, state.restTimer.remaining - elapsedSinceSave);
                    if (remaining > 0) {
                        restTimerStartedAt = Date.now() - (restTimerDuration - remaining) * 1000;
                        restCountdownEl.textContent = formatTime(remaining);
                        restTimer = setInterval(() => {
                            const elapsed = Math.floor((Date.now() - restTimerStartedAt) / 1000);
                            const nextRemaining = Math.max(0, restTimerDuration - elapsed);
                            restCountdownEl.textContent = formatTime(nextRemaining);
                            if (nextRemaining <= 0) {
                                clearInterval(restTimer);
                                restTimer = null;
                                restCountdownEl.textContent = 'Done';
                            }
                        }, 1000);
                    } else {
                        restCountdownEl.textContent = 'Done';
                    }
                }
            }

            if (routineId) {
                routineSelect.value = routineId;
                const routine = routines[routineId];
                if (routine) {
                    buildRoutineBoard(routine);
                }
            }

            applyRowState(state.rows);
            updateSessionStats();

            if (sessionStart) {
                startSessionBtn.disabled = true;
                routineSelect.disabled = true;
                endSessionBtn.disabled = !!sessionEnd;
                saveSessionBtn.disabled = sessionEnd ? totalSets === 0 : true;
                const endRef = sessionEnd ? sessionEnd.getTime() : Date.now();
                const elapsed = Math.max(0, Math.floor((endRef - sessionStart.getTime()) / 1000));
                elapsedTimeEl.textContent = formatTime(elapsed);
                if (!sessionEnd) {
                    startSessionTimer();
                }
            }

            if (sessionEnded) {
                routineBoard.querySelectorAll('.set-action').forEach(btn => {
                    btn.disabled = true;
                });
            }
            updateSessionStatus();
        }

        function resetSessionState() {
            if (sessionTimer) clearInterval(sessionTimer);
            if (restTimer) clearInterval(restTimer);
            restTimerStartedAt = null;
            restTimerDuration = 0;
            sessionId = null;
            routineId = null;
            routineName = null;
            sessionStart = null;
            sessionEnd = null;
            sessionEnded = false;
            totalSets = 0;
            totalVolume = 0;
            routineSelect.value = '';
            routineSelect.disabled = false;
            elapsedTimeEl.textContent = '00:00';
            restCountdownEl.textContent = '00:00';
            sessionNotesEl.value = '';
            routineBoard.classList.add('empty');
            routineBoard.innerHTML = '<p>Select a routine to load your sets.</p>';
            startSessionBtn.disabled = false;
            endSessionBtn.disabled = true;
            endSessionBtn.textContent = 'End Session';
            saveSessionBtn.disabled = true;
            updateSessionStats();
            updateSessionStatus();
        }

        function saveSessionState() {
            if (!sessionId) {
                return;
            }
            let restRemaining = 0;
            if (restTimerStartedAt && restTimerDuration > 0) {
                const elapsed = Math.floor((Date.now() - restTimerStartedAt) / 1000);
                restRemaining = Math.max(0, restTimerDuration - elapsed);
            }
            const state = {
                sessionId,
                routineId,
                routineName,
                sessionStart: sessionStart ? sessionStart.toISOString() : null,
                sessionEnd: sessionEnd ? sessionEnd.toISOString() : null,
                sessionEnded,
                totalSets,
                totalVolume,
                notes: sessionNotesEl.value || '',
                restSeconds: restSecondsEl.value || '',
                restTimer: {
                    startedAt: restTimerStartedAt,
                    duration: restTimerDuration,
                    remaining: restRemaining
                },
                rows: collectRowState()
            };

            const drafts = loadDrafts();
            const idx = drafts.findIndex(draft => draft.id === sessionId);
            const payload = {
                id: sessionId,
                updatedAt: new Date().toISOString(),
                state
            };
            if (idx >= 0) {
                drafts[idx] = payload;
            } else {
                drafts.unshift(payload);
            }
            saveDrafts(drafts);
            setActiveDraftId(sessionId);
            buildDraftOptions(drafts, sessionId);
        }

        function restoreSessionState() {
            const drafts = loadDrafts();
            const activeId = getActiveDraftId();
            buildDraftOptions(drafts, activeId);

            if (!drafts.length) {
                resetSessionState();
                return;
            }

            const targetId = activeId || drafts[0].id;
            const draft = drafts.find(item => item.id === targetId) || drafts[0];
            if (draft) {
                sessionDraftSelect.value = draft.id;
                setActiveDraftId(draft.id);
                loadDraftState(draft.state);
            }
        }

        function scheduleStateSave() {
            if (stateSaveTimer) {
                clearTimeout(stateSaveTimer);
            }
            stateSaveTimer = setTimeout(saveSessionState, 250);
        }

        function formatTime(totalSeconds) {
            const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
            const seconds = Math.floor(totalSeconds % 60).toString().padStart(2, '0');
            return `${minutes}:${seconds}`;
        }

        function updateSessionStats() {
            setsLoggedEl.textContent = totalSets.toString();
            volumeTotalEl.textContent = totalVolume.toFixed(0);
        }

        function updateSessionStatus() {
            if (!sessionStatusEl) {
                return;
            }
            if (!sessionStart) {
                sessionStatusEl.textContent = 'No active session.';
                return;
            }
            if (sessionEnded) {
                sessionStatusEl.textContent = 'Session ended. Ready to save.';
                return;
            }
            sessionStatusEl.textContent = 'Session in progress.';
        }

        function startSessionTimer() {
            if (sessionTimer) clearInterval(sessionTimer);
            sessionTimer = setInterval(() => {
                const elapsed = Math.floor((Date.now() - sessionStart.getTime()) / 1000);
                elapsedTimeEl.textContent = formatTime(elapsed);
            }, 1000);
        }

        function startRestTimer() {
            const seconds = parseInt(restSecondsEl.value, 10);
            if (!seconds || seconds <= 0) return;
            if (restTimer) clearInterval(restTimer);
            restTimerDuration = seconds;
            restTimerStartedAt = Date.now();
            restCountdownEl.textContent = formatTime(seconds);
            restTimer = setInterval(() => {
                const elapsed = Math.floor((Date.now() - restTimerStartedAt) / 1000);
                const remaining = Math.max(0, restTimerDuration - elapsed);
                restCountdownEl.textContent = formatTime(remaining);
                if (remaining <= 0) {
                    clearInterval(restTimer);
                    restTimer = null;
                    restCountdownEl.textContent = 'Done';
                }
            }, 1000);
        }

        function buildRoutineBoard(routine) {
            routineBoard.innerHTML = '';
            routineBoard.classList.remove('empty');
            const disableButtons = !sessionStart || sessionEnded;

            routine.exercises.forEach(exercise => {
                const section = document.createElement('div');
                section.className = 'exercise-card';

                const header = document.createElement('div');
                header.className = 'exercise-header';
                header.innerHTML = `
                    <div>
                        <h3>${exercise.name}</h3>
                        <p class="muted">${exercise.sets} sets x ${exercise.reps} reps</p>
                    </div>
                    <span class="tag">Target ${exercise.weight || 0} lbs</span>
                `;
                section.appendChild(header);

                const table = document.createElement('div');
                table.className = 'set-table';

                for (let i = 1; i <= exercise.sets; i++) {
                    const row = document.createElement('div');
                    row.className = 'set-row';
                    row.dataset.exercise = exercise.name;
                    row.dataset.set = i.toString();
                    row.dataset.startedAt = '';

                    row.innerHTML = `
                        <div class="set-cell">Set ${i}</div>
                        <div class="set-cell"><input type="number" min="0" step="0.5" value="${exercise.weight || 0}" class="weight-input"></div>
                        <div class="set-cell"><input type="number" min="1" value="${exercise.reps}" class="reps-input"></div>
                        <div class="set-cell">
                            <select class="effort-input">
                                <option value="">RPE</option>
                                ${Array.from({ length: 10 }, (_, idx) => `<option value="${idx + 1}">${idx + 1}</option>`).join('')}
                            </select>
                        </div>
                        <div class="set-cell">
                            <button class="btn btn-blue set-action" type="button" ${disableButtons ? 'disabled' : ''}>Start Set</button>
                        </div>
                        <div class="set-cell status">Ready</div>
                    `;
                    table.appendChild(row);
                }

                section.appendChild(table);
                routineBoard.appendChild(section);
            });
        }

        async function logSet(row) {
            const exercise = row.dataset.exercise;
            const setNumber = parseInt(row.dataset.set, 10);
            const weight = parseFloat(row.querySelector('.weight-input').value || '0');
            const reps = parseInt(row.querySelector('.reps-input').value || '0', 10);
            const effort = row.querySelector('.effort-input').value;
            const startTime = row.dataset.startedAt;
            const endTime = new Date().toISOString();
            const duration = Math.max(0, Math.round((new Date(endTime) - new Date(startTime)) / 1000));

            let result = null;
            try {
                const response = await fetch('track_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userId: window.ACTIVE_USER_ID || null,
                        sessionId,
                        routineId,
                        routineName,
                        exercise,
                        set: setNumber,
                        weight,
                        reps,
                        effort: effort ? parseInt(effort, 10) : null,
                        startTime,
                        endTime,
                        duration
                    })
                });

                result = await response.json();
            } catch (error) {
                alert('Failed to log set. Check your connection and try again.');
                return;
            }

            if (!result || !result.success) {
                alert(result?.error || 'Failed to log set.');
                return;
            }

            totalSets += 1;
            if (!Number.isNaN(weight) && !Number.isNaN(reps)) {
                totalVolume += weight * reps;
            }
            updateSessionStats();
            updateSessionStatus();

            const statusEl = row.querySelector('.status');
            statusEl.textContent = 'Logged';
            statusEl.classList.add('done');

            const button = row.querySelector('.set-action');
            button.disabled = true;
            button.textContent = 'Complete';

            saveSessionBtn.disabled = false;
            scheduleStateSave();
        }

        routineSelect.addEventListener('change', () => {
            const selectedId = routineSelect.value;
            routineId = selectedId;
            routineName = selectedId ? routines[selectedId]?.name : null;

            if (!selectedId) {
                routineBoard.classList.add('empty');
                routineBoard.innerHTML = '<p>Select a routine to load your sets.</p>';
                return;
            }

            const routine = routines[selectedId];
            if (routine) {
                buildRoutineBoard(routine);
            }
            scheduleStateSave();
        });

        sessionDraftSelect.addEventListener('change', () => {
            const selectedDraftId = sessionDraftSelect.value;
            if (!selectedDraftId) {
                setActiveDraftId(null);
                resetSessionState();
                return;
            }
            saveSessionState();
            const drafts = loadDrafts();
            const draft = drafts.find(item => item.id === selectedDraftId);
            if (!draft) {
                resetSessionState();
                return;
            }
            resetSessionState();
            sessionDraftSelect.value = selectedDraftId;
            setActiveDraftId(selectedDraftId);
            loadDraftState(draft.state);
        });

        newDraftBtn.addEventListener('click', () => {
            saveSessionState();
            setActiveDraftId(null);
            resetSessionState();
            sessionDraftSelect.value = '';
        });

        startSessionBtn.addEventListener('click', () => {
            if (!routineSelect.value) {
                alert('Select a routine to start.');
                return;
            }
            sessionId = `sess_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`;
            sessionStart = new Date();
            sessionEnd = null;
            sessionEnded = false;
            totalSets = 0;
            totalVolume = 0;
            updateSessionStats();
            elapsedTimeEl.textContent = '00:00';
            startSessionTimer();
            startSessionBtn.disabled = true;
            endSessionBtn.disabled = false;
            endSessionBtn.textContent = 'End Session';
            saveSessionBtn.disabled = true;
            routineSelect.disabled = true;

            const selectedRoutine = routines[routineSelect.value];
            if (selectedRoutine) {
                buildRoutineBoard(selectedRoutine);
            }

            routineBoard.querySelectorAll('.set-action').forEach(btn => {
                btn.disabled = false;
            });
            updateSessionStatus();
            saveSessionState();
            scheduleStateSave();
        });

        // Fun encouragements and quotes
        const encouragements = [
            "Crushed it!",
            "Beast mode: ON!",
            "You’re a legend!",
            "Workout wizardry!",
            "That was epic!",
            "You just leveled up!",
            "Flex appeal!",
            "You made those sets look easy!"
        ];
        const funQuotes = [
            "Sweat is just your fat crying.",
            "The only bad workout is the one you didn’t do.",
            "You don’t have to be extreme, just consistent.",
            "Strong today, stronger tomorrow.",
            "Champions train, losers complain.",
            "You’re one workout closer to your goal!"
        ];

        function showSessionEndPopup(summary, comparison, badgesHtml) {
            document.getElementById('popupEncouragement').textContent = encouragements[Math.floor(Math.random()*encouragements.length)];
            document.getElementById('popupSummary').innerHTML = summary;
            document.getElementById('popupComparison').textContent = comparison;
            document.getElementById('popupQuote').textContent = funQuotes[Math.floor(Math.random()*funQuotes.length)];
            document.getElementById('popupBadges').innerHTML = badgesHtml || '';

            // Goal reminder logic (simple, localStorage-based)
            let reminderHtml = '';
            try {
                // Get session goal from localStorage (set by user on goals page)
                let goals = JSON.parse(localStorage.getItem('userGoals')||'{}');
                let sessionsPerWeek = goals.frequency && goals.frequency.sessionsPerWeek ? parseInt(goals.frequency.sessionsPerWeek) : null;
                if (sessionsPerWeek) {
                    // Count sessions this week
                    let sessions = JSON.parse(localStorage.getItem('sessionHistory')||'[]');
                    let now = new Date();
                    let weekStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay());
                    let count = sessions.filter(s => {
                        let d = new Date(s.endTime||s.startTime);
                        return d >= weekStart && d <= now;
                    }).length;
                    if (count < sessionsPerWeek) {
                        reminderHtml = `<div style='margin:1em 0 0 0;color:#e65100;font-weight:bold;'>Only ${sessionsPerWeek-count} session${sessionsPerWeek-count===1?'':'s'} left to hit your weekly goal!</div>`;
                    } else {
                        reminderHtml = `<div style='margin:1em 0 0 0;color:#388e3c;font-weight:bold;'>You hit your weekly session goal!</div>`;
                    }
                }
            } catch(e) {}

            // Add session reflection prompt
            let reflectionHtml = `<div style='margin-top:1em;'>`
                + `<label for='sessionReflection'><b>How did you feel?</b></label><br>`
                + `<textarea id='sessionReflection' rows='2' style='width:90%;margin-top:0.5em;' placeholder='Quick reflection, PRs, tweaks, etc.'></textarea>`
                + `<button id='saveReflectionBtn' class='btn btn-blue' style='margin-top:0.5em;'>Save Reflection</button>`
                + `<div id='reflectionSavedMsg' style='color:green;display:none;margin-top:0.5em;'>Saved!</div>`
                + `</div>`;
            document.getElementById('popupSummary').insertAdjacentHTML('afterend', reminderHtml + reflectionHtml);
            document.getElementById('saveReflectionBtn').onclick = function() {
                let val = document.getElementById('sessionReflection').value.trim();
                if (val) {
                    let reflections = JSON.parse(localStorage.getItem('sessionReflections')||'[]');
                    reflections.push({date: (new Date()).toISOString(), text: val});
                    localStorage.setItem('sessionReflections', JSON.stringify(reflections));
                    document.getElementById('reflectionSavedMsg').style.display = 'block';
                }
            };
            document.getElementById('sessionEndPopup').style.display = 'flex';
        }
        document.getElementById('closeSessionEndPopup').onclick = function() {
            document.getElementById('sessionEndPopup').style.display = 'none';
        };

        // Flag to suppress alert when using encouragement popup
        let suppressSessionSavedAlert = false;

        endSessionBtn.addEventListener('click', () => {
            if (!sessionStart) {
                alert('No active session to end.');
                return;
            }
            sessionEnd = new Date();
            sessionEnded = true;
            if (sessionTimer) clearInterval(sessionTimer);
            endSessionBtn.disabled = true;
            endSessionBtn.textContent = 'Session Ended';
            saveSessionBtn.disabled = totalSets === 0;
            routineBoard.querySelectorAll('.set-action').forEach(btn => {
                btn.disabled = true;
            });
            updateSessionStatus();
            scheduleStateSave();
            clearAllDrafts();
            if (totalSets === 0) {
                resetSessionState();
                return;
            }
            // Build fun summary
            let sets = totalSets;
            let volume = totalVolume;
            let duration = Math.floor((sessionEnd-sessionStart)/1000);
            let min = Math.floor(duration/60), sec = duration%60;
            let summary = `<b>${sets}</b> sets, <b>${volume}</b> total volume<br>in <b>${min}m ${sec}s</b>`;

            // Personal bests and streaks
            let bests = JSON.parse(localStorage.getItem('personalBests')||'{}');
            let streak = JSON.parse(localStorage.getItem('workoutStreak')||'{}');
            let today = (new Date()).toISOString().slice(0,10);
            let badges = [];
            // Personal bests
            if (!bests.sets || sets > bests.sets) {
                bests.sets = sets;
                badges.push('<span class="badge" style="background:#ffd700;color:#222;padding:0.3em 0.7em;border-radius:1em;margin:0 0.2em;">New Most Sets!</span>');
            }
            if (!bests.volume || volume > bests.volume) {
                bests.volume = volume;
                badges.push('<span class="badge" style="background:#ff9800;color:#fff;padding:0.3em 0.7em;border-radius:1em;margin:0 0.2em;">New Volume Record!</span>');
            }
            if (!bests.duration || duration > bests.duration) {
                bests.duration = duration;
                badges.push('<span class="badge" style="background:#2196f3;color:#fff;padding:0.3em 0.7em;border-radius:1em;margin:0 0.2em;">Longest Session!</span>');
            }
            localStorage.setItem('personalBests', JSON.stringify(bests));

            // Streaks
            let lastDay = streak.lastDay || null;
            let count = streak.count || 0;
            if (lastDay === today) {
                // Already logged today
            } else if (lastDay && ((new Date(today) - new Date(lastDay)) === 86400000)) {
                count += 1;
                badges.push(`<span class="badge" style="background:#4caf50;color:#fff;padding:0.3em 0.7em;border-radius:1em;margin:0 0.2em;">${count} Day Streak!</span>`);
            } else {
                count = 1;
                if (lastDay) badges.push('<span class="badge" style="background:#607d8b;color:#fff;padding:0.3em 0.7em;border-radius:1em;margin:0 0.2em;">Streak Reset</span>');
            }
            streak.lastDay = today;
            streak.count = count;
            localStorage.setItem('workoutStreak', JSON.stringify(streak));

            // Compare to last session (if available)
            let comparison = '';
            try {
                let prev = JSON.parse(localStorage.getItem('lastSessionStats')||'null');
                if (prev && prev.sets !== undefined) {
                    let diffSets = sets - prev.sets;
                    let diffVol = volume - prev.volume;
                    let diffTime = duration - prev.duration;
                    let msg = [];
                    if (diffSets > 0) msg.push(`+${diffSets} sets!`);
                    else if (diffSets < 0) msg.push(`${-diffSets} fewer sets`);
                    if (diffVol > 0) msg.push(`+${diffVol} volume!`);
                    else if (diffVol < 0) msg.push(`${-diffVol} less volume`);
                    if (diffTime < 0) msg.push(`faster by ${Math.abs(diffTime)}s`);
                    else if (diffTime > 0) msg.push(`took ${diffTime}s longer`);
                    if (msg.length) comparison = `Compared to last: ${msg.join(', ')}`;
                    else comparison = "Matched your last session!";
                }
                localStorage.setItem('lastSessionStats', JSON.stringify({sets, volume, duration}));
            } catch(e) { /* ignore */ }

            // Save session, then show popup after save
            suppressSessionSavedAlert = true;
            handleSaveSession(true).then(() => {
                showSessionEndPopup(summary, comparison, badges.join(' '));
                suppressSessionSavedAlert = false;
            });
        });

        async function handleSaveSession(resetAfterSave) {
            if (!sessionStart || isSaving) return false;
            if (!sessionEnd) {
                sessionEnd = new Date();
                if (sessionTimer) clearInterval(sessionTimer);
                endSessionBtn.disabled = true;
            }
            const duration = Math.max(0, Math.round((sessionEnd - sessionStart) / 1000));

            isSaving = true;
            saveSessionBtn.disabled = true;

            let result = null;
            try {
                const response = await fetch('session_log.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userId: window.ACTIVE_USER_ID || null,
                        id: sessionId,
                        routineId,
                        routineName,
                        startTime: sessionStart.toISOString(),
                        endTime: sessionEnd.toISOString(),
                        duration,
                        totalSets,
                        totalVolume,
                        notes: sessionNotesEl.value.trim()
                    })
                });

                result = await response.json();
            } catch (error) {
                alert('Failed to save session. Check your connection and try again.');
                isSaving = false;
                saveSessionBtn.disabled = totalSets === 0;
                return false;
            }

            if (result && result.success) {
                if (!suppressSessionSavedAlert) alert('Session saved.');
                if (sessionId) {
                    const drafts = loadDrafts().filter(draft => draft.id !== sessionId);
                    saveDrafts(drafts);
                    setActiveDraftId(null);
                    buildDraftOptions(drafts, null);
                    sessionDraftSelect.value = '';
                }
                if (resetAfterSave) {
                    resetSessionState();
                }
                sessionId = null;
                isSaving = false;
                scheduleStateSave();
                return true;
            }

            alert(result?.error || 'Failed to save session.');
            saveSessionBtn.disabled = totalSets === 0;
            isSaving = false;
            scheduleStateSave();
            return false;
        }

        saveSessionBtn.addEventListener('click', async () => {
            await handleSaveSession(true);
        });

        restStartBtn.addEventListener('click', startRestTimer);

        routineBoard.addEventListener('input', scheduleStateSave);
        routineBoard.addEventListener('change', scheduleStateSave);
        sessionNotesEl.addEventListener('input', scheduleStateSave);
        restSecondsEl.addEventListener('change', scheduleStateSave);
        window.addEventListener('beforeunload', saveSessionState);
        window.addEventListener('workout:beforeUserSwitch', saveSessionState);

        routineBoard.addEventListener('click', (event) => {
            const button = event.target.closest('.set-action');
            if (!button) return;
            if (!sessionStart) {
                alert('Start the session first.');
                return;
            }
            if (sessionEnded) {
                alert('Session has ended. Start a new session to log more sets.');
                return;
            }

            const row = button.closest('.set-row');
            if (!row) return;

            if (!row.dataset.startedAt) {
                row.dataset.startedAt = new Date().toISOString();
                button.textContent = 'Complete Set';
                row.querySelector('.status').textContent = 'In progress';
                row.querySelector('.status').classList.remove('done');
                scheduleStateSave();
            } else {
                logSet(row);
            }
        });

        restoreSessionState();
        updateSessionStatus();
    </script>
</body>
</html>
