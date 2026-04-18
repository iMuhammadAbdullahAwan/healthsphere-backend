# Step Tracking API

The Steps API manages hardware-recorded pedometer data and user tracking preferences.

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/steps/sessions` | List all step sessions |
| POST | `/api/steps/sessions` | Save a new step session |
| GET | `/api/steps/sessions/{id}` | Get session details |
| DELETE | `/api/steps/sessions/{id}` | Delete a session |
| GET | `/api/steps/tracking/status` | Get tracking toggle and goal |
| PATCH | `/api/steps/tracking` | Toggle tracking or update goal |

## Sessions

### Save Step Session
`POST /api/steps/sessions`

Saves a completed walking/running session recorded by the pedometer.

**Request Body (application/json):**
- `steps`: (required) Total steps
- `distanceKm`: (required) Distance in kilometers
- `durationSeconds`: (required) Duration in seconds
- `startedAt`: (required) Start timestamp
- `calories`: (optional) Calories burned (calculated automatically if omitted)

**Response:**
```json
{
  "status": true,
  "message": "Session saved successfully",
  "data": { "id": 45 }
}
```

## Tracking Preferences

### Get Tracking Status
`GET /api/steps/tracking/status`

Checks if background tracking is enabled and retrieves the daily step goal.

**Response:**
```json
{
  "status": true,
  "message": "Tracking status retrieved successfully",
  "data": {
    "enabled": true,
    "goal": 10000
  }
}
```

### Toggle Tracking
`PATCH /api/steps/tracking`

Enables/disables step tracking and updates the daily goal.

**Request Body (application/json):**
- `enabled`: (optional) boolean
- `goal`: (optional) integer

**Response:**
```json
{
  "status": true,
  "message": "Tracking updated successfully",
  "data": {
    "step_tracking_enabled": 1,
    "daily_step_goal": 12000
  }
}
```
