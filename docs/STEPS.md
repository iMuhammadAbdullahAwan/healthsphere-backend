# Step Tracking API

The Steps API manages hardware-recorded pedometer data and user tracking preferences.

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/steps/sessions` | List all step sessions (with pagination) |
| POST | `/api/steps/sessions` | Save a new step session |
| GET | `/api/steps/sessions/{id}` | Get session details |
| DELETE | `/api/steps/sessions/{id}` | Delete a session |
| GET | `/api/steps/tracking/status` | Get tracking toggle and goal |
| PATCH | `/api/steps/tracking` | Toggle tracking or update goal |
| POST | `/api/steps/auto-session` | Create or update today's auto session |

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

### Get Step History
`GET /api/steps/sessions`

Returns a list of all recorded pedometer sessions.

**Query Parameters:**
- `page`: (optional) Page number
- `limit`: (optional) Items per page

**Response:**
```json
{
  "status": true,
  "message": "Step sessions retrieved successfully",
  "data": {
    "sessions": [
      { "id": 45, "steps": 8421, ... }
    ],
    "pagination": {
      "total": 100,
      "page": 1,
      "limit": 20,
      "pages": 5
    }
  }
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

## Auto Session (Daily Tracking)

### Auto Session Create/Update
`POST /api/steps/auto-session`

Creates a new step session for today or updates today's existing session. This endpoint is designed for continuous background tracking where the frontend periodically sends updated step data throughout the day.

**Important:**
- Only one auto session is created per day
- Subsequent calls update the same session for that day
- Tracking must be enabled for this endpoint to work
- Returns `is_new: true` on first call of the day, `is_new: false` on updates

**Request Body (application/json):**
- `steps`: (required) Current step count
- `distanceKm`: (required) Distance in kilometers
- `durationSeconds`: (required) Duration in seconds
- `calories`: (optional) Calories burned (calculated automatically if omitted: 0.04 kcal per step)

**Request Example:**
```json
{
  "steps": 1250,
  "distanceKm": 0.85,
  "durationSeconds": 900,
  "calories": 50
}
```

**Response (First Call - Session Created):**
```json
{
  "status": true,
  "message": "Auto session created",
  "code": 201,
  "data": {
    "id": 45,
    "message": "Auto session created successfully",
    "is_new": true,
    "user_id": 10,
    "steps": 1250,
    "distance_km": 0.85,
    "duration_seconds": 900,
    "calories": 50,
    "started_at": "2026-04-28 14:30:45"
  }
}
```

**Response (Subsequent Call - Session Updated):**
```json
{
  "status": true,
  "message": "Auto session updated",
  "code": 200,
  "data": {
    "id": 45,
    "message": "Auto session updated successfully",
    "is_new": false,
    "user_id": 10,
    "steps": 2500,
    "distance_km": 1.70,
    "duration_seconds": 1800,
    "calories": 100,
    "updated_at": "2026-04-28 14:45:30"
  }
}
```

**Error Responses:**

Tracking disabled:
```json
{
  "status": false,
  "message": "Step tracking is not enabled",
  "code": 403
}
```

Validation error:
```json
{
  "status": false,
  "message": "Validation failed",
  "code": 400,
  "errors": {
    "steps": "The steps field is required.",
    "distanceKm": "The distanceKm field is required."
  }
}
```

**Frontend Usage Example:**

```javascript
// Check if tracking is enabled
const statusResponse = await fetch('/api/steps/tracking/status', {
  headers: { 'Authorization': 'Bearer TOKEN' }
});
const { data: { enabled, goal } } = await statusResponse.json();

if (enabled) {
  // Update auto session every 30 seconds (or your preferred interval)
  setInterval(async () => {
    const stepsData = {
      steps: currentSteps,
      distanceKm: currentDistance,
      durationSeconds: currentDuration,
      calories: currentCalories
    };

    const response = await fetch('/api/steps/auto-session', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer TOKEN',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(stepsData)
    });
    
    const result = await response.json();
    if (result.data.is_new) {
      console.log('New session created');
    } else {
      console.log('Session updated');
    }
  }, 30000); // Every 30 seconds
}
```

**Notes:**
- The session date is determined by the server's current date (UTC)
- Only one session per calendar day is allowed
- All numeric values must be non-negative
- Calories are auto-calculated as `steps * 0.04` if not provided
- Each update extends the tracking duration for the day
