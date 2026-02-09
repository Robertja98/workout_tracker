# Workout

A simple PHP workout tracker with routines, progress tracking, and a dashboard.

## Run with XAMPP (Windows)

1. Open XAMPP Control Panel and start **Apache**.
2. Create a junction so Apache can serve the project folder:
   - Open PowerShell as a normal user.
   - Run:

```
mklink /J "C:\xampp\htdocs\Workout" "C:\Users\rober\OneDrive\Personal\Workout"
```

3. Visit: http://localhost/Workout/index.php

## Data files

- `data/routines.json` stores routines.
- `data/progress.json` stores tracked sets.
- `data/workouts.json` stores workout entries (JSON storage).

## API endpoints

All JSON endpoints return a consistent shape:

```
{ "success": true, "message": "...", "data": { ... } }
```

Errors return:

```
{ "success": false, "error": "..." }
```

Endpoints:
- `save_routine.php`
- `load_routine.php`
- `get_routine.php`
- `update_routine.php`
- `delete_routine.php`
- `track_progress.php`
