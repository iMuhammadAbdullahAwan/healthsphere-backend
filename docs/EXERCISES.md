# Exercise Logging API

The Exercise API allows users to log their physical activities and track their workout history.

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/exercises` | List all exercise logs (paginated) |
| POST | `/api/exercises` | Log a new exercise session |
| DELETE | `/api/exercises/{id}` | Delete an exercise log entry |

## Create Exercise Log
`POST /api/exercises`

Logs a new activity.

**Request Body (application/json):**
- `exercise_name`: (required) Name of the exercise
- `count`: (optional) e.g., "3 sets of 12", "50 reps"
- `duration_minutes`: (optional) Time spent in minutes
- `performed_at`: (required) YYYY-MM-DD HH:MM:SS
- `calories_burned`: (optional) Estimated calories
- `notes`: (optional) Additional info

**Example Request:**
```json
{
  "exercise_name": "Push Ups",
  "count": "3 sets of 20",
  "duration_minutes": 10,
  "performed_at": "2026-04-18 17:30:00",
  "notes": "Felt great!"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Exercise logged successfully",
  "data": {
    "id": 1,
    "exercise_name": "Push Ups",
    "count": "3 sets of 20",
    "duration_minutes": 10,
    "performed_at": "2026-04-18 17:30:00",
    "notes": "Felt great!",
    "created_at": "..."
  }
}
```

## Get Exercise History
`GET /api/exercises`

Returns a list of all logged exercises, most recent first. Supports search and pagination.

**Query Parameters:**
- `search`: (optional) Search by exercise name
- `page`: (optional) Page number (default: 1)
- `limit`: (optional) Items per page (default: 20)

**Response:**
```json
{
  "status": true,
  "message": "Exercise logs retrieved successfully",
  "data": {
    "logs": [
      {
        "id": 1,
        "exercise_name": "Push Ups",
        "count": "3 sets of 20",
        "duration_minutes": 10,
        "performed_at": "2026-04-18 17:30:00",
        "notes": "Felt great!",
        "created_at": "..."
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "limit": 20,
      "pages": 1
    }
  }
}
```
