
<?php
header('Content-Type: application/json');

$routinesFile = __DIR__ . "/data/routines.json";
$routines = file_exists($routinesFile) ? json_decode(file_get_contents($routinesFile), true) : [];
$rawInput = file_get_contents('php://input');
$data = $rawInput ? json_decode($rawInput, true) : [];
$id = $data['id'] ?? null;

if ($id !== null && isset($routines[$id])) {
    echo json_encode([
        'success' => true,
        'data' => [
            'name' => $routines[$id]['name'],
            'exercises' => $routines[$id]['exercises']
        ]
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Routine not found']);
}
