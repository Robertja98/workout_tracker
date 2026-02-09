<?php
header('Content-Type: application/json');
require_once __DIR__ . "/user_context.php";
user_bootstrap();

$rawInput = file_get_contents('php://input');
$data = $rawInput ? json_decode($rawInput, true) : null;

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$required = ['id', 'routineName', 'startTime', 'endTime', 'duration', 'totalSets', 'totalVolume'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing field: ' . $field]);
        exit;
    }
}

$session = [
    'id' => trim((string)$data['id']),
    'routineId' => isset($data['routineId']) ? trim((string)$data['routineId']) : null,
    'routineName' => trim((string)$data['routineName']),
    'startTime' => trim((string)$data['startTime']),
    'endTime' => trim((string)$data['endTime']),
    'duration' => (int)$data['duration'],
    'totalSets' => (int)$data['totalSets'],
    'totalVolume' => (float)$data['totalVolume'],
    'notes' => isset($data['notes']) ? trim((string)$data['notes']) : ''
];

$userId = isset($data['userId']) ? trim((string)$data['userId']) : null;
$sessions = $userId ? user_load_data_for($userId, 'sessions.json', []) : user_load_data('sessions.json', []);

foreach ($sessions as $existing) {
    if (($existing['id'] ?? '') === $session['id']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Session already saved']);
        exit;
    }
}

$sessions[] = $session;

if ($userId) {
    $saved = user_save_data_for($userId, 'sessions.json', $sessions);
} else {
    $saved = user_save_data('sessions.json', $sessions);
}

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save session']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Session saved']);
