
<?php
header('Content-Type: application/json');

try {
    $routinesFile = __DIR__ . "/data/routines.json";

    $rawInput = file_get_contents('php://input');
    if (!$rawInput) {
        http_response_code(400);
        echo json_encode(['error' => 'No input received.']);
        exit;
    }

    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON format.']);
        exit;
    }

    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Routine name is required.']);
        exit;
    }

    $name = trim(strip_tags((string)$input['name']));
    $exercises = isset($input['exercises']) && is_array($input['exercises']) ? $input['exercises'] : [];

    // Sanitize exercises
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

    $routines = file_exists($routinesFile) ? json_decode(file_get_contents($routinesFile), true) : [];

    $id = uniqid();
    $routines[$id] = [
        'name' => $name,
        'exercises' => $exercises,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if (file_put_contents($routinesFile, json_encode($routines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Routine saved successfully!',
            'id' => $id,
            'routine' => $routines[$id]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save routine. Check file permissions.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Routine save error: " . $e->getMessage(), 3, __DIR__ . "/error.log");
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
