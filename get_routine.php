
<?php
header('Content-Type: application/json');

$routinesFile = __DIR__ . "/data/routines.json";
$rawInput = file_get_contents('php://input');
$data = $rawInput ? json_decode($rawInput, true) : [];
$id = $data['id'] ?? '';

$routines = file_exists($routinesFile) ? json_decode(file_get_contents($routinesFile), true) : [];
if (!isset($routines[$id])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Routine not found']);
    exit;
}

echo json_encode(['success' => true, 'data' => $routines[$id]]);
