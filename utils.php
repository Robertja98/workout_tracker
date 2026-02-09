
<?php
// utils.php

/**
 * Ensure the JSON file exists.
 */
function initialize_workouts($jsonFile) {
    $dir = dirname($jsonFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (!file_exists($jsonFile)) {
        if (file_put_contents($jsonFile, json_encode([], JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new Exception("Failed to initialize workouts file.");
        }
    }
}

/**
 * Read all workouts from the JSON file and return as an array.
 */
function read_workouts($jsonFile) {
    if (!file_exists($jsonFile)) {
        return [];
    }
    $data = json_decode(file_get_contents($jsonFile), true);
    return is_array($data) ? $data : [];
}

/**
 * Add a workout entry to the JSON file.
 * If headers are provided, map indexed data to keys.
 */
function add_workout($jsonFile, $headers, $workoutData) {
    $entry = $workoutData;
    if (is_array($headers) && count($headers) === count($workoutData)) {
        $entry = array_combine($headers, $workoutData);
    }

    if (!is_array($entry)) {
        throw new Exception("Workout data is invalid.");
    }

    $entry = array_map('trim', $entry);
    $sets = $entry['sets'] ?? ($entry[3] ?? '');
    $reps = $entry['reps'] ?? ($entry[4] ?? '');
    $weight = $entry['weight'] ?? ($entry[5] ?? '');
    $weight = is_string($weight) ? str_replace("lbs", "", trim($weight)) : $weight;

    if ($sets !== '' && !is_numeric($sets)) {
        throw new Exception("Sets must be numeric.");
    }
    if ($reps !== '' && !is_numeric($reps)) {
        throw new Exception("Reps must be numeric.");
    }
    if ($weight !== '' && !is_numeric($weight)) {
        throw new Exception("Weight must be numeric or empty.");
    }

    $workouts = read_workouts($jsonFile);
    $workouts[] = $entry;

    if (file_put_contents($jsonFile, json_encode($workouts, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new Exception("Failed to write workout.");
    }
}
?>
