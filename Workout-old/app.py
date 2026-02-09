
from flask import Flask, render_template, request, redirect, jsonify
from utils import initialize_csv, read_workouts, add_workout
import os
import json
from datetime import datetime

app = Flask(__name__)

# ---------------------------
# Configuration
DATA_FOLDER = "data"
CSV_FILE = os.path.join(DATA_FOLDER, "workouts.csv")
ROUTINES_FILE = os.path.join(DATA_FOLDER, "routines.json")
HEADERS = ["date", "workout_type", "exercise", "sets", "reps", "weight", "duration", "notes"]

os.makedirs(DATA_FOLDER, exist_ok=True)
initialize_csv(CSV_FILE, HEADERS)

# ---------------------------
@app.route("/")
def index():
    workouts = read_workouts(CSV_FILE)
    exercise_stats = {}
    for w in workouts:
        ex = w['exercise']
        weight_str = w['weight'].replace('lbs', '').strip() if w['weight'] else '0'
        weight = float(weight_str) if weight_str.replace('.', '').isdigit() else 0
        sets = int(w['sets']) if w['sets'].isdigit() else 0
        reps = int(w['reps']) if w['reps'].isdigit() else 0

        if ex not in exercise_stats:
            exercise_stats[ex] = {'weights': [], 'sets': [], 'reps': [], 'latest': None, 'previous': None,
                                  'trend': 'N/A', 'average_weight': 0, 'average_sets': 0, 'average_reps': 0}

        exercise_stats[ex]['weights'].append(weight)
        exercise_stats[ex]['sets'].append(sets)
        exercise_stats[ex]['reps'].append(reps)

    for ex, data in exercise_stats.items():
        weights = data['weights']
        sets_list = data['sets']
        reps_list = data['reps']
        data['latest'] = weights[-1] if weights else 0
        data['previous'] = weights[-2] if len(weights) > 1 else None
        data['average_weight'] = round(sum(weights) / len(weights), 1) if weights else 0
        data['average_sets'] = round(sum(sets_list) / len(sets_list), 1) if sets_list else 0
        data['average_reps'] = round(sum(reps_list) / len(reps_list), 1) if reps_list else 0
        if data['previous'] is not None:
            if data['latest'] > data['previous']:
                data['trend'] = "Increase"
            elif data['latest'] < data['previous']:
                data['trend'] = "Decrease"
            else:
                data['trend'] = "Same"

    return render_template("index.html", exercise_stats=exercise_stats)

# ---------------------------
@app.route("/dashboard")
def dashboard():
    return render_template("dashboard.html")

# ---------------------------
@app.route("/routines", methods=["GET"])
def routines_portal():
    routines = {}
    if os.path.exists(ROUTINES_FILE):
        with open(ROUTINES_FILE, "r") as f:
            routines = json.load(f)
    return render_template("routines.html", routines=routines)

# ---------------------------
@app.route("/save_routine", methods=["POST"])
def save_routine():
    try:
        routine = request.get_json()
        if not routine or "exercises" not in routine:
            return jsonify({"error": "Invalid routine data"}), 400

        routines = {}
        if os.path.exists(ROUTINES_FILE):
            with open(ROUTINES_FILE, "r") as f:
                routines = json.load(f)

        routine_id = str(len(routines) + 1)
        routine_name = routine.get("name", f"Routine {routine_id}")
        routine["name"] = routine_name
        routine["timestamp"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        routines[routine_id] = routine
        with open(ROUTINES_FILE, "w") as f:
            json.dump(routines, f)

        return jsonify({"message": "Routine saved!", "id": routine_id, "routines": routines}), 201
    except Exception as e:
        return jsonify({"error": f"Failed to save routine: {str(e)}"}), 500

@app.route("/list_routines", methods=["GET"])
def list_routines():
    if os.path.exists(ROUTINES_FILE):
        with open(ROUTINES_FILE, "r") as f:
            routines = json.load(f)
        return jsonify(routines)
    return jsonify({})

@app.route("/load_routine/<routine_id>", methods=["GET"])
def load_routine(routine_id):
    if os.path.exists(ROUTINES_FILE):
        with open(ROUTINES_FILE, "r") as f:
            routines = json.load(f)
        if routine_id in routines:
            return jsonify(routines[routine_id])
    return jsonify({"error": "Routine not found"}), 404

@app.route("/delete_routine/<routine_id>", methods=["DELETE"])
def delete_routine(routine_id):
    if os.path.exists(ROUTINES_FILE):
        with open(ROUTINES_FILE, "r") as f:
            routines = json.load(f)
        if routine_id in routines:
            del routines[routine_id]
            with open(ROUTINES_FILE, "w") as f:
                json.dump(routines, f)
            return jsonify({"message": "Routine deleted"}), 200
    return jsonify({"error": "Routine not found"}), 404

# ---------------------------
@app.route("/generate_routine", methods=["POST"])
def generate_routine():
    routine = {
        "exercises": [
            {"name": "Dumbbell Bench Press", "sets": 3, "reps": 10, "weight": "Moderate"},
            {"name": "Seated Row", "sets": 3, "reps": 12, "weight": "Moderate"},
            {"name": "Goblet Squats", "sets": 3, "reps": 10, "weight": "Light"},
            {"name": "Glute Bridges", "sets": 3, "reps": 12, "weight": "Bodyweight"},
            {"name": "Plank", "sets": 3, "reps": 30, "weight": "Bodyweight"}
        ]
    }
    return jsonify(routine)

# ---------------------------
if __name__ == "__main__":
    app.run(debug=True)
