
<?php
header('Content-Type: application/json');

$routinesFile = __DIR__ . "/data/routines.json";
$rawInput = file_get_contents('php://input');
$data = $rawInput ? json_decode($rawInput, true) : null;
$id = $data['id'] ?? '';

$routines = file_exists($routinesFile) ? json_decode(file_get_contents($routinesFile), true) : [];
if (!isset($routines[$id])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Routine not found']);
    exit;
}

$name = isset($data['name']) ? trim(strip_tags((string)$data['name'])) : '';
$exercises = isset($data['exercises']) && is_array($data['exercises']) ? $data['exercises'] : [];

function classify_exercise($name, $weight) {
    $label = strtolower($name);
    if (preg_match('/stretch|pose|cool-down|cat-cow/i', $label)) {
        return 'stretch';
    }
    if (preg_match('/machine|leg press|rowing|jump rope/i', $label)) {
        return 'equipment';
    }
    if ($weight > 0) {
        return 'weight';
    }
    return 'bodyweight';
}

$exercises = array_map(function($exercise) {
    $name = trim(strip_tags((string)($exercise['name'] ?? '')));
    $sets = (int)($exercise['sets'] ?? 0);
    $reps = (int)($exercise['reps'] ?? 0);
    $weight = (float)($exercise['weight'] ?? 0);
    return [
        'name' => $name,
        'sets' => $sets,
        'reps' => $reps,
        'weight' => $weight,
        'category' => classify_exercise($name, $weight)
    ];
}, $exercises);

$routines[$id]['name'] = $name;
$routines[$id]['exercises'] = $exercises;
$routines[$id]['timestamp'] = date('Y-m-d H:i:s');

if (file_put_contents($routinesFile, json_encode($routines, JSON_PRETTY_PRINT), LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update routine']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Routine updated successfully']);
