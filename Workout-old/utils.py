
import os
import csv
from datetime import datetime

def initialize_csv(csv_file, headers):
    """Ensure the CSV file exists with the correct headers."""
    os.makedirs(os.path.dirname(csv_file), exist_ok=True)
    if not os.path.isfile(csv_file):
        try:
            with open(csv_file, mode="w", newline="", encoding="utf-8") as file:
                writer = csv.DictWriter(file, fieldnames=headers)
                writer.writeheader()
        except Exception as e:
            raise IOError(f"Failed to initialize CSV file: {e}")

def read_workouts(csv_file):
    """Read all workouts from the CSV file and return as a list of dicts."""
    workouts = []
    if os.path.isfile(csv_file):
        try:
            with open(csv_file, mode="r", encoding="utf-8") as file:
                reader = csv.DictReader(file)
                workouts = list(reader)
        except Exception as e:
            raise IOError(f"Failed to read workouts: {e}")
    return workouts

def add_workout(csv_file, headers, workout_data):
    """
    Add a workout entry to the CSV file.
    Validates data length, sanitizes input, and ensures numeric fields are correct.
    """
    if len(workout_data) != len(headers):
        raise ValueError("Workout data does not match headers.")

    # ✅ Sanitize all fields
    workout_data = [str(item).strip() for item in workout_data]

    # ✅ Validate numeric fields (sets, reps, weight)
    sets = workout_data[3]
    reps = workout_data[4]
    weight = workout_data[5].replace("lbs", "").strip() if workout_data[5] else ""

    if sets and not sets.isdigit():
        raise ValueError("Sets must be numeric.")
    if reps and not reps.isdigit():
        raise ValueError("Reps must be numeric.")
    if weight and not weight.replace(".", "").isdigit():
        raise ValueError("Weight must be numeric or empty.")

    # ✅ Append timestamp for better tracking
    # If you want to add a timestamp column, uncomment below:
    # workout_data.append(datetime.now().strftime("%H:%M:%S"))

    try:
        with open(csv_file, mode="a", newline="", encoding="utf-8") as file:
            writer = csv.DictWriter(file, fieldnames=headers)
            writer.writerow(dict(zip(headers, workout_data)))
    except Exception as e:
        raise IOError(f"Failed to write workout: {e}")
