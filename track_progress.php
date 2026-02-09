
<?php
header('Content-Type: application/json');
require_once __DIR__ . "/user_context.php";
user_bootstrap();

$rawInput = file_get_contents('php://input');
$data = $rawInput ? json_decode($rawInput, true) : null;

if (!$data || !isset($data['exercise'], $data['set'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$exercise = trim(strip_tags((string)$data['exercise']));
$set = (int)$data['set'];
$weight = isset($data['weight']) && $data['weight'] !== '' ? (float)$data['weight'] : null;
$reps = isset($data['reps']) && $data['reps'] !== '' ? (int)$data['reps'] : null;
$effort = isset($data['effort']) && $data['effort'] !== '' ? (int)$data['effort'] : null;
$sessionId = isset($data['sessionId']) ? trim((string)$data['sessionId']) : null;
$routineId = isset($data['routineId']) ? trim((string)$data['routineId']) : null;
$routineName = isset($data['routineName']) ? trim((string)$data['routineName']) : null;
$startTime = isset($data['startTime']) ? trim((string)$data['startTime']) : null;
$endTime = isset($data['endTime']) ? trim((string)$data['endTime']) : null;
$duration = isset($data['duration']) && $data['duration'] !== '' ? (int)$data['duration'] : null;
$userId = isset($data['userId']) ? trim((string)$data['userId']) : null;

$progress = $userId ? user_load_data_for($userId, 'progress.json', []) : user_load_data('progress.json', []);

$updated = false;
if ($sessionId) {
    foreach ($progress as &$entry) {
        $entrySession = isset($entry['sessionId']) ? (string)$entry['sessionId'] : '';
        if (($entry['exercise'] ?? '') === $exercise && ($entry['set'] ?? null) === $set && $entrySession === $sessionId) {
            if ($weight !== null) {
                $entry['weight'] = $weight;
            }
            if ($reps !== null) {
                $entry['reps'] = $reps;
            }
            if ($effort !== null) {
                $entry['effort'] = $effort;
            }
            if ($startTime) {
                $entry['startTime'] = $startTime;
            }
            if ($endTime) {
                $entry['endTime'] = $endTime;
            }
            if ($duration !== null) {
                $entry['duration'] = $duration;
            }
            if ($routineId) {
                $entry['routineId'] = $routineId;
            }
            if ($routineName) {
                $entry['routineName'] = $routineName;
            }
            $updated = true;
            break;
        }
    }
}

if (!$updated) {
    $progress[] = [
        'sessionId' => $sessionId,
        'routineId' => $routineId,
        'routineName' => $routineName,
        'exercise' => $exercise,
        'set' => $set,
        'weight' => $weight !== null ? $weight : '-',
        'reps' => $reps,
        'effort' => $effort,
        'startTime' => $startTime ?: date('c'),
        'endTime' => $endTime,
        'duration' => $duration
    ];
}

if ($userId) {
    $saved = user_save_data_for($userId, 'progress.json', $progress);
} else {
    $saved = user_save_data('progress.json', $progress);
}

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save progress']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Progress saved']);
