# Schedules API Documentation

Complete guide for schedule management endpoints in the HealthSphere API.

## Base URL
```
http://your-domain.com/api/schedules
```

## Authentication
All endpoints require JWT authentication via Bearer token.

**Headers:**
```
Authorization: Bearer <access_token>
Content-Type: application/json
```

---

## Schedule Types

The system supports 6 types of schedules:

| Type | Description | Required Details |
|------|-------------|------------------|
| `medicine` | Medication reminders | medicine_name |
| `food` | Meal reminders | meal_type |
| `water` | Hydration reminders | Optional |
| `running` | Exercise/activity reminders | activity_type |
| `sleep` | Sleep schedule | sleep_time, wake_time |
| `custom` | Custom reminders | label |

---

## Endpoints

### 1. Get All Schedules

Retrieve all schedules for the authenticated user with optional filters.

**Endpoint:** `GET /api/schedules`

**Authentication:** Required

**Query Parameters:**
- `type` (optional): Filter by schedule type (medicine, food, water, running, sleep, custom)
- `status` (optional): Filter by status (active, paused, completed)
- `start_date` (optional): Filter schedules from date (YYYY-MM-DD)
- `end_date` (optional): Filter schedules until date (YYYY-MM-DD)

**Request Examples:**
```
GET /api/schedules
GET /api/schedules?type=medicine
GET /api/schedules?status=active
GET /api/schedules?type=food&status=active
GET /api/schedules?start_date=2026-01-01&end_date=2026-01-31
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedules retrieved successfully",
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "schedule_type": "medicine",
      "title": "Morning Medication",
      "description": "Blood pressure medication",
      "start_date": "2026-01-10",
      "start_time": "08:00",
      "repeat_type": "daily",
      "repeat_days": null,
      "end_condition": "never",
      "end_date": null,
      "max_occurrences": null,
      "reminder_enabled": true,
      "reminder_mode": "notification",
      "voice_command_text": null,
      "status": "active",
      "medicine_details": {
        "medicine_name": "Lisinopril",
        "dosage_text": "10mg tablet",
        "medicine_image": null,
        "instructions": "Take with water"
      },
      "food_details": null,
      "water_details": null,
      "running_details": null,
      "sleep_details": null,
      "custom_details": null,
      "created_at": "2026-01-10 07:00:00",
      "updated_at": "2026-01-10 07:00:00"
    }
  ]
}
```

**Error Responses:**
- `401`: Unauthorized
- `500`: Failed to retrieve schedules

---

### 2. Get Today's Schedules

Get all active schedules for today.

**Endpoint:** `GET /api/schedules/today`

**Authentication:** Required

**Success Response (200):**
```json
{
  "status": true,
  "message": "Today's schedules retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Morning Medication",
      "start_time": "08:00",
      "schedule_type": "medicine",
      "status": "active"
    }
  ]
}
```

---

### 3. Get Upcoming Schedules

Get upcoming schedules for the next N days.

**Endpoint:** `GET /api/schedules/upcoming`

**Authentication:** Required

**Query Parameters:**
- `days` (optional): Number of days to look ahead (default: 7)

**Request Example:**
```
GET /api/schedules/upcoming?days=14
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Upcoming schedules retrieved successfully",
  "data": [
    {
      "id": 5,
      "title": "Evening Walk",
      "start_date": "2026-01-18",
      "start_time": "18:00",
      "schedule_type": "running"
    }
  ]
}
```

---

### 4. Get Schedule Statistics

Get count of active schedules grouped by type.

**Endpoint:** `GET /api/schedules/stats`

**Authentication:** Required

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule statistics retrieved successfully",
  "data": [
    {
      "schedule_type": "medicine",
      "count": 3
    },
    {
      "schedule_type": "food",
      "count": 2
    },
    {
      "schedule_type": "water",
      "count": 1
    }
  ]
}
```

---

### 5. Get Single Schedule

Retrieve details of a specific schedule.

**Endpoint:** `GET /api/schedules/{id}`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Schedule ID

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule retrieved successfully",
  "data": {
    "id": 1,
    "user_id": 1,
    "schedule_type": "medicine",
    "title": "Morning Medication",
    "description": "Blood pressure medication",
    "start_date": "2026-01-10",
    "start_time": "08:00",
    "repeat_type": "daily",
    "end_condition": "never",
    "reminder_enabled": true,
    "reminder_mode": "notification",
    "status": "active",
    "medicine_details": {
      "medicine_name": "Lisinopril",
      "dosage_text": "10mg tablet",
      "instructions": "Take with water"
    },
    "created_at": "2026-01-10 07:00:00",
    "updated_at": "2026-01-10 07:00:00"
  }
}
```

**Error Responses:**
- `400`: Schedule ID is required
- `401`: Unauthorized
- `404`: Schedule not found
- `500`: Server error

---

### 6. Create Schedule

Create a new schedule.

**Endpoint:** `POST /api/schedules`

**Authentication:** Required

**Request Body Examples:**

#### Medicine Schedule
```json
{
  "schedule_type": "medicine",
  "title": "Morning Medication",
  "description": "Blood pressure medication",
  "start_date": "2026-01-20",
  "start_time": "08:00",
  "repeat_type": "daily",
  "end_condition": "never",
  "reminder_enabled": true,
  "reminder_mode": "notification",
  "medicine_details": {
    "medicine_name": "Lisinopril",
    "dosage_text": "10mg tablet",
    "medicine_image": "https://example.com/med.jpg",
    "instructions": "Take with water, after breakfast"
  }
}
```

#### Food Schedule
```json
{
  "schedule_type": "food",
  "title": "Lunch Reminder",
  "start_date": "2026-01-20",
  "start_time": "12:30",
  "repeat_type": "daily",
  "end_condition": "never",
  "reminder_enabled": true,
  "reminder_mode": "notification",
  "food_details": {
    "meal_type": "lunch",
    "notes": "Healthy balanced meal"
  }
}
```

#### Water Schedule
```json
{
  "schedule_type": "water",
  "title": "Hydration Reminder",
  "start_date": "2026-01-20",
  "start_time": "09:00",
  "repeat_type": "custom_days",
  "repeat_days": [1, 2, 3, 4, 5],
  "end_condition": "never",
  "reminder_enabled": true,
  "water_details": {
    "amount_per_time": 250,
    "glasses_count": 8,
    "interval_minutes": 60
  }
}
```

#### Running Schedule
```json
{
  "schedule_type": "running",
  "title": "Morning Jog",
  "start_date": "2026-01-20",
  "start_time": "06:00",
  "repeat_type": "weekly",
  "repeat_days": [1, 3, 5],
  "end_condition": "after_occurrences",
  "max_occurrences": 30,
  "reminder_enabled": true,
  "running_details": {
    "activity_type": "jog",
    "duration_minutes": 30,
    "distance_km": 5,
    "location": "Central Park"
  }
}
```

#### Sleep Schedule
```json
{
  "schedule_type": "sleep",
  "title": "Sleep Reminder",
  "start_date": "2026-01-20",
  "start_time": "22:00",
  "repeat_type": "daily",
  "end_condition": "never",
  "reminder_enabled": true,
  "reminder_mode": "both",
  "voice_command_text": "Time to sleep for better health",
  "sleep_details": {
    "sleep_time": "22:00",
    "wake_time": "06:00",
    "target_duration_hours": 8
  }
}
```

#### Custom Schedule
```json
{
  "schedule_type": "custom",
  "title": "Daily Meditation",
  "start_date": "2026-01-20",
  "start_time": "07:00",
  "repeat_type": "daily",
  "end_condition": "on_date",
  "end_date": "2026-12-31",
  "reminder_enabled": true,
  "custom_details": {
    "label": "Meditation",
    "notes": "10 minutes of mindfulness"
  }
}
```

**Success Response (201):**
```json
{
  "status": true,
  "message": "Schedule created successfully",
  "data": {
    "id": 10,
    "user_id": 1,
    "schedule_type": "medicine",
    "title": "Morning Medication",
    // ... full schedule object
  }
}
```

**Error Responses:**
- `400`: Invalid JSON data or validation error
- `401`: Unauthorized
- `500`: Failed to create schedule

---

### 7. Update Schedule

Update an existing schedule.

**Endpoint:** `PUT /api/schedules/{id}`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Schedule ID

**Request Body:**
```json
{
  "title": "Updated Title",
  "start_time": "09:00",
  "reminder_mode": "both",
  "medicine_details": {
    "dosage_text": "20mg tablet"
  }
}
```

**Note:** You can update any field. Only provided fields will be updated.

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule updated successfully",
  "data": {
    "id": 1,
    // ... updated schedule object
  }
}
```

**Error Responses:**
- `400`: Schedule ID required or invalid data
- `401`: Unauthorized
- `404`: Schedule not found
- `500`: Failed to update schedule

---

### 8. Update Schedule Status

Change schedule status (pause, resume, complete).

**Endpoint:** `PATCH /api/schedules/{id}/status`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Schedule ID

**Request Body:**
```json
{
  "status": "paused"
}
```

**Valid Status Values:**
- `active` - Schedule is active
- `paused` - Schedule is temporarily paused
- `completed` - Schedule is completed

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule status updated successfully",
  "data": {
    "id": 1,
    "status": "paused"
    // ... full schedule object
  }
}
```

**Error Responses:**
- `400`: Status is required or invalid
- `401`: Unauthorized
- `404`: Schedule not found
- `500`: Failed to update status

---

### 9. Delete Schedule

Delete a schedule permanently.

**Endpoint:** `DELETE /api/schedules/{id}`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Schedule ID

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule deleted successfully",
  "data": null
}
```

**Error Responses:**
- `400`: Schedule ID required
- `401`: Unauthorized
- `404`: Schedule not found or already deleted
- `500`: Failed to delete schedule

---

### 10. Get Schedule Logs

Get completion history for a specific schedule.

**Endpoint:** `GET /api/schedules/{id}/logs`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Schedule ID

**Query Parameters:**
- `status` (optional): Filter by status (pending, completed, skipped, missed)
- `start_date` (optional): From date (YYYY-MM-DD)
- `end_date` (optional): To date (YYYY-MM-DD)

**Request Example:**
```
GET /api/schedules/1/logs?status=completed
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule logs retrieved successfully",
  "data": [
    {
      "id": 101,
      "schedule_id": 1,
      "user_id": 1,
      "scheduled_for": "2026-01-17 08:00:00",
      "completed_at": "2026-01-17 08:05:00",
      "status": "completed",
      "notes": "Took medication on time",
      "created_at": "2026-01-17 00:00:00",
      "updated_at": "2026-01-17 08:05:00"
    }
  ]
}
```

---

### 11. Mark Schedule Log as Completed

Mark a specific occurrence as completed.

**Endpoint:** `POST /api/schedules/logs/{logId}/complete`

**Authentication:** Required

**URL Parameters:**
- `logId` (required): Schedule log ID

**Request Body:**
```json
{
  "notes": "Took medication as scheduled"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule marked as completed",
  "data": {
    "id": 101,
    "schedule_id": 1,
    "status": "completed",
    "completed_at": "2026-01-17 08:05:00",
    "notes": "Took medication as scheduled"
  }
}
```

---

### 12. Get User History

Get completion history across all schedules.

**Endpoint:** `GET /api/schedules/history`

**Authentication:** Required

**Query Parameters:**
- `type` (optional): Filter by schedule type
- `status` (optional): Filter by log status
- `start_date` (optional): From date (YYYY-MM-DD)
- `end_date` (optional): To date (YYYY-MM-DD)

**Request Example:**
```
GET /api/schedules/history?type=medicine&status=completed
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Schedule history retrieved successfully",
  "data": [
    {
      "id": 101,
      "schedule_id": 1,
      "title": "Morning Medication",
      "schedule_type": "medicine",
      "scheduled_for": "2026-01-17 08:00:00",
      "completed_at": "2026-01-17 08:05:00",
      "status": "completed",
      "notes": "Completed on time"
    }
  ]
}
```

---

### 13. Get Completion Statistics

Get completion stats for the user.

**Endpoint:** `GET /api/schedules/completion-stats`

**Authentication:** Required

**Query Parameters:**
- `type` (optional): Filter by schedule type
- `start_date` (optional): From date (YYYY-MM-DD)
- `end_date` (optional): To date (YYYY-MM-DD)

**Request Example:**
```
GET /api/schedules/completion-stats?type=medicine
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Completion statistics retrieved successfully",
  "data": [
    {
      "status": "completed",
      "count": 25
    },
    {
      "status": "missed",
      "count": 2
    },
    {
      "status": "pending",
      "count": 5
    }
  ]
}
```

---

## Field Reference

### Common Fields (All Schedule Types)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| schedule_type | enum | Yes | medicine, food, water, running, sleep, custom |
| title | string | Yes | Schedule title (max 255 chars) |
| description | string | No | Additional description |
| start_date | date | Yes | Format: YYYY-MM-DD |
| start_time | time | Yes | Format: HH:mm (24-hour) |
| repeat_type | enum | Yes | once, daily, weekly, custom_days |
| repeat_days | array | No | Day numbers [1-7] for Mon-Sun |
| end_condition | enum | Yes | never, on_date, after_occurrences |
| end_date | date | No | Required if end_condition is on_date |
| max_occurrences | integer | No | Required if end_condition is after_occurrences |
| reminder_enabled | boolean | No | Default: true |
| reminder_mode | enum | No | notification, voice, both (default: notification) |
| voice_command_text | string | No | Custom voice reminder text |
| status | enum | No | active, paused, completed (default: active) |

### Type-Specific Fields

#### Medicine Details
```json
{
  "medicine_name": "string (required)",
  "dosage_text": "string (optional)",
  "medicine_image": "string URL (optional)",
  "instructions": "string (optional)"
}
```

#### Food Details
```json
{
  "meal_type": "breakfast|lunch|dinner|snack (required)",
  "notes": "string (optional)"
}
```

#### Water Details
```json
{
  "amount_per_time": "integer ml (optional)",
  "glasses_count": "integer (optional)",
  "interval_minutes": "integer (optional)"
}
```

#### Running Details
```json
{
  "activity_type": "walk|jog|run (required)",
  "duration_minutes": "integer (optional)",
  "distance_km": "number (optional)",
  "location": "string (optional)"
}
```

#### Sleep Details
```json
{
  "sleep_time": "time HH:mm (required)",
  "wake_time": "time HH:mm (required)",
  "target_duration_hours": "number (optional)"
}
```

#### Custom Details
```json
{
  "label": "string (required)",
  "notes": "string (optional)"
}
```

---

## Repeat Patterns

### Once
```json
{
  "repeat_type": "once",
  "end_condition": "never"
}
```

### Daily
```json
{
  "repeat_type": "daily",
  "end_condition": "after_occurrences",
  "max_occurrences": 30
}
```

### Weekly (Specific Days)
```json
{
  "repeat_type": "weekly",
  "repeat_days": [1, 3, 5],
  "end_condition": "on_date",
  "end_date": "2026-12-31"
}
```

### Custom Days
```json
{
  "repeat_type": "custom_days",
  "repeat_days": [1, 2, 3, 4, 5],
  "end_condition": "never"
}
```

**Day Numbers:**
- 1 = Monday
- 2 = Tuesday
- 3 = Wednesday
- 4 = Thursday
- 5 = Friday
- 6 = Saturday
- 7 = Sunday

---

## Common Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid input or validation error |
| 401 | Unauthorized - Invalid or missing authentication |
| 404 | Not Found - Schedule not found or doesn't belong to user |
| 500 | Internal Server Error - Server-side error |

---

## Best Practices

1. **Use appropriate schedule types** for better organization
2. **Set realistic repeat patterns** to avoid notification fatigue
3. **Use end conditions** to prevent infinite schedules
4. **Mark logs as completed** for accurate tracking
5. **Pause instead of delete** if temporarily stopping a schedule
6. **Use custom details** for unique reminder needs
7. **Enable voice mode** for accessibility
8. **Check completion stats** regularly for insights
