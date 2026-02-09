
<?php
header('Content-Type: application/json');

try {
    $routinesFile = __DIR__ . "/data/routines.json";

    // Read JSON input
    $rawInput = file_get_contents('php://input');
    if (!$rawInput) {
        http_response_code(400);
        echo json_encode(['error' => 'No input received.']);
        exit;
    }

    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON format.']);
        exit;
    }

    $id = $data['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Routine ID is required.']);
        exit;
    }

    // Load routines
    $routines = file_exists($routinesFile) ? json_decode(file_get_contents($routinesFile), true) : [];

    if (!isset($routines[$id])) {
        http_response_code(404);
        echo json_encode(['error' => 'Routine not found.']);
        exit;
    }

    // Delete routine
    unset($routines[$id]);

    if (file_put_contents($routinesFile, json_encode($routines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Routine deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete routine. Check file permissions.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Delete routine error: " . $e->getMessage(), 3, __DIR__ . "/error.log");
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
