# Schedules API Documentation

This file is a concise route-accurate reference for schedule endpoints.

For detailed frontend flow guidance, see `docs/SCHEDULES_API.md`.

## Base URL

`http://your-domain.com/api/schedules`

## Auth

All endpoints below require `Authorization: Bearer <token>`.

## Endpoints

- `GET /api/schedules`
- `GET /api/schedules/stats`
- `GET /api/schedules/{id}`
- `GET /api/schedules/{id}/logs`
- `POST /api/schedules`
- `PUT /api/schedules/{id}`
- `PATCH /api/schedules/{id}/status`
- `POST /api/schedules/{id}/cancel`
- `POST /api/schedules/{id}/uncancel`
- `POST /api/schedules/{id}/done`
- `POST /api/schedules/{id}/undone`
- `POST /api/schedules/logs/{logId}/complete`
- `POST /api/schedules/logs/{logId}/undo`
- `DELETE /api/schedules/{id}`

## Query Parameters

### List Schedules (`GET /api/schedules`)

- `page`, `per_page`
- `filter`: `today`, `upcoming`, `history`
- `type`, `status`, `start_date`, `end_date`
- `search` or `q`
- `days` (used with `filter=upcoming`)

### Schedule Stats (`GET /api/schedules/stats`)

- Default: type counts.
- Completion report mode: `type=completion` with optional `schedule_type`, `start_date`, `end_date`.

## Create/Update With Image

- For multipart uploads, use file field `image`.
- You can send:
- plain form fields directly, or
- `payload` form field with JSON string.
- Stored image path is relative: `uploads/schedules/<filename>` (file saved under `public/uploads/schedules/`).

## Status And Occurrence Actions

- `PATCH /{id}/status`: updates schedule status.
- `POST /{id}/cancel`:
- `scope=all` cancels parent schedule.
- `scope=one` archives one occurrence to history as `canceled` and removes it from active logs.
- `POST /{id}/uncancel`:
- `scope=all` re-activates schedule and restores archived canceled/completed occurrences.
- `scope=one` restores one occurrence to `pending`.
- `POST /{id}/done`:
- `scope=all` sets schedule to `completed`.
- `scope=one` archives one occurrence as `completed` and removes it from active logs.
- `POST /{id}/undone`:
- `scope=all` restores schedule to `active` and restores archived completed occurrences.
- `scope=one` restores one occurrence to `pending`.
- `POST /logs/{logId}/complete`: archives log as completed.
- `POST /logs/{logId}/undo`: restores log to pending (in-place or from history).
