# Workout

A simple PHP workout tracker with routines, progress tracking, and a dashboard.

## 🔐 Authentication System

This project now includes a **secure, modular authentication system** with:

- User registration and login
- Password security (Argon2ID hashing)
- Session management
- Rate limiting and account lockout
- Activity logging and audit trails
- CSRF protection
- 2026 security best practices
- **CSV-based storage (no database required!)**

### Quick Setup

1. Visit `http://localhost/Workout/auth/install.php` to check system requirements
2. CSV files are created automatically - no database setup needed!
3. Visit `http://localhost/Workout/auth/register.php` to create an account

For detailed setup instructions, see [auth/SETUP.md](auth/SETUP.md).

For full documentation and reusability guide, see [auth/README.md](auth/README.md).

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
