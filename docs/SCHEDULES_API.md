# Schedules — Statuses & Endpoints (Frontend Reference)

This document describes schedule and schedule_log statuses, common scenarios (cancel, done/completed, undo), and the API endpoints the frontend should call.

**Statuses (schedules table)**
- **active**: Schedule is active and occurrences will be generated and reminders sent.
- **paused**: Schedule is temporarily paused; occurrences stay but reminders are not dispatched.
- **completed**: Schedule marked finished (e.g., end condition reached).
- **canceled**: Schedule cancelled entirely (master cancel).

**Statuses (schedule_logs table — occurrences)**
- **pending**: Occurrence scheduled and awaiting action or reminder.
- **completed**: User marked occurrence as done.
- **skipped**: User skipped this occurrence.
- **missed**: Occurrence passed without completion.
- **canceled**: Single occurrence canceled (day-level cancel).

**Common user flows & endpoints**

- **List schedules (with filters & pagination)**
  - GET /api/schedules
  - Query params: `page` (int), `per_page` (int), `filter` (today|upcoming|history), `type` (medicine|food|water|running|sleep|custom), `status` (active|paused|completed|canceled), `start_date` (YYYY-MM-DD), `end_date` (YYYY-MM-DD)
  - Example: `/api/schedules?filter=upcoming&days=7&page=1&per_page=20`
  - Search: add `q` to search by schedule title (e.g. `?q=morning`).

- **Get schedule stats (completion report)**
  - GET /api/schedules/stats?type=completion&schedule_type=medicine&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
  - Returns completion counts/rates for the given schedule_type and date range.

- **Get a single schedule**
  - GET /api/schedules/{id}

- **Create schedule**
  - POST /api/schedules
  - Body: JSON with schedule fields (see backend validation in `ScheduleModel`).

- **Update schedule**
  - PUT /api/schedules/{id}

- **Change schedule status (pause/resume/complete/cancel master)**
  - PATCH /api/schedules/{id}/status
  - Body: `{ "status": "paused" }` (allowed: `active`, `paused`, `completed`, `canceled`)

- **Cancel a single occurrence (today only or specific date)**
  - POST /api/schedules/{id}/cancel
  - Body examples:
    - Cancel only today: `{ "scope": "one", "date": "2026-03-28" }`
    - Cancel entire schedule (master cancel): `{ "scope": "all" }`
  - Behavior: when `scope=one`, controller finds the `schedule_logs` row where `DATE(scheduled_for) = date` and sets that occurrence's status to `canceled`. Parent schedule remains unchanged.

- **Uncancel (redo) a single occurrence or entire schedule**
  - POST /api/schedules/{id}/uncancel
  - Body examples:
    - Uncancel one day: `{ "scope": "one", "date": "2026-03-28" }`
    - Uncancel entire schedule: `{ "scope": "all" }` (sets schedule status back to `active`)

- **Mark an occurrence completed**
  - POST /api/schedules/logs/{logId}/complete
  - Body (optional): `{ "notes": "Completed on time" }`
  - Behavior: sets `schedule_logs.status = 'completed'` and `completed_at` timestamp.

- **Undo a completed occurrence (set back to pending)**
  - POST /api/schedules/logs/{logId}/undo
  - Behavior: controller sets the given log back to `status = 'pending'` and clears `completed_at`.

- **Delete a schedule**
  - DELETE /api/schedules/{id}

**Frontend behavior recommendations**
- For cancelling just today, call `POST /api/schedules/{id}/cancel` with `{ scope: 'one', date: 'YYYY-MM-DD' }` and update the UI row for that date to show `Canceled` (use log status).
- For undoing a completed occurrence (user tapped “undo” after marking done), call `POST /api/schedules/logs/{logId}/undo` and update the occurrence row to `Pending`.
- Show parent schedule status separately from occurrence status. Example: schedule card shows `Status: Active` while today's occurrence shows `Pending` / `Canceled` / `Completed`.
- When calling list endpoints, prefer `per_page` + `page` for large datasets to avoid pulling many JSON-encoded fields at once.

**Error handling & auth**
- All protected endpoints require `Authorization: Bearer <token>` header.
- Typical error codes: `401` (unauthenticated), `400` (bad request/validation), `404` (not found), `500` (server error). Handle and surface messages from API responses.

If you want, I can add Postman examples (body + sample responses) into the `postman/postman-collection.json` for the `cancel`, `uncancel`, and `undo` requests.

***
File: app/Config/Routes.php (routes referenced) — see this file for route mappings.

*** End Patch