Workout Tracker - Summary

What was done
- Added per-user session switching in the top bar without leaving the page.
- Fixed link handling so user context is preserved without breaking paths.
- Implemented Session Mode drafts with a selector and new draft control.
- Persisted in-progress sessions per user, including set state and notes.
- Ensured End Session ends logging and saves + resets the session.
- Persisted rest timer so it continues across user switches.
- Added Session History on the Dashboard with user selector, view, and delete.

Site narrative
This site is a workout tracker designed for multiple users sharing one device. Each user can run their own sessions, log sets, track goals, and review progress. Session Mode focuses on in-workout flow with timers and quick logging, while the Dashboard summarizes trends, highlights, and session history. The user switcher keeps data separated so each profile has its own progress timeline.
