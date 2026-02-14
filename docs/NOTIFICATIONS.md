# Notifications API Documentation

Complete guide for notification management endpoints in the HealthSphere API.

## Base URL
```
http://your-domain.com/api/notifications
```

## Authentication
All endpoints require JWT authentication via Bearer token.

**Headers:**
```
Authorization: Bearer <access_token>
Content-Type: application/json
```

---

## Endpoints

### 1. Get All Notifications

Retrieve all notifications for the authenticated user with optional filters.

**Endpoint:** `GET /api/notifications`

**Authentication:** Required

**Query Parameters:**
- `type` (optional): Filter by notification type
- `read` (optional): Filter by read status (`0` for unread, `1` for read)
- `limit` (optional): Number of results per page (default: 20)
- `page` (optional): Page number for pagination (default: 1)

**Request Examples:**
```
GET /api/notifications
GET /api/notifications?type=schedule_reminder
GET /api/notifications?read=0
GET /api/notifications?limit=10&page=2
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Notifications retrieved successfully",
  "data": [
    {
      "id": 15,
      "user_id": 1,
      "type": "schedule_reminder",
      "message": "Time to take your medicine: Morning Medication",
      "link": "/schedules/5",
      "related_id": 5,
      "is_read": 0,
      "created_by": 1,
      "created_at": "2026-01-17 09:00:00",
      "read_at": null
    },
    {
      "id": 14,
      "user_id": 1,
      "type": "system",
      "message": "Welcome to HealthSphere!",
      "link": null,
      "related_id": null,
      "is_read": 1,
      "created_by": null,
      "created_at": "2026-01-15 10:00:00",
      "read_at": "2026-01-15 11:30:00"
    }
  ]
}
```

**Error Responses:**
- `401`: Unauthorized
- `500`: Failed to retrieve notifications

---

### 2. Get Unread Count

Get the count of unread notifications for the authenticated user.

**Endpoint:** `GET /api/notifications/unread-count`

**Authentication:** Required

**Request Body:** None

**Success Response (200):**
```json
{
  "status": true,
  "message": "Unread count retrieved successfully",
  "data": {
    "count": 5
  }
}
```

**Error Responses:**
- `401`: Unauthorized
- `500`: Failed to retrieve unread count

---

### 3. Get Single Notification

Retrieve details of a specific notification.

**Endpoint:** `GET /api/notifications/{id}`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Notification ID

**Request Example:**
```
GET /api/notifications/15
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Notification retrieved successfully",
  "data": {
    "id": 15,
    "user_id": 1,
    "type": "schedule_reminder",
    "message": "Time to take your medicine: Morning Medication",
    "link": "/schedules/5",
    "related_id": 5,
    "is_read": 0,
    "created_by": 1,
    "created_at": "2026-01-17 09:00:00",
    "read_at": null
  }
}
```

**Error Responses:**
- `401`: Unauthorized
- `404`: Notification not found
- `500`: Server error

---

### 4. Mark Notification as Read

Mark a specific notification as read.

**Endpoint:** `PATCH /api/notifications/{id}/read`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Notification ID

**Request Body:** None required

**Request Example:**
```
PATCH /api/notifications/15/read
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Notification marked as read",
  "data": null
}
```

**Error Responses:**
- `400`: Failed to mark as read
- `401`: Unauthorized
- `404`: Notification not found
- `500`: Server error

---

### 5. Mark All Notifications as Read

Mark all notifications for the authenticated user as read.

**Endpoint:** `PATCH /api/notifications/mark-all-read`

**Authentication:** Required

**Request Body:** None required

**Success Response (200):**
```json
{
  "status": true,
  "message": "All notifications marked as read",
  "data": null
}
```

**Error Responses:**
- `400`: Failed to mark all as read
- `401`: Unauthorized
- `500`: Server error

---

### 6. Delete Notification

Delete a specific notification.

**Endpoint:** `DELETE /api/notifications/{id}`

**Authentication:** Required

**URL Parameters:**
- `id` (required): Notification ID

**Request Example:**
```
DELETE /api/notifications/15
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Notification deleted successfully",
  "data": null
}
```

**Error Responses:**
- `401`: Unauthorized
- `404`: Notification not found
- `500`: Failed to delete notification

---

## Notification Types

The system supports various notification types:

| Type | Description | Use Case |
|------|-------------|----------|
| `schedule_reminder` | Schedule/reminder notifications | Medicine, meal, water reminders |
| `system` | System announcements | Updates, maintenance, welcome messages |
| `alert` | Important alerts | Critical health alerts |
| `info` | Informational messages | Tips, suggestions |

---

## Notification Object Schema

```json
{
  "id": "integer - Unique notification ID",
  "user_id": "integer - ID of the user who receives the notification",
  "type": "string - Type of notification (schedule_reminder, system, etc.)",
  "message": "string - Notification message content",
  "link": "string|null - Optional link/URL for the notification",
  "related_id": "integer|null - Optional ID of related entity (e.g., schedule_id)",
  "is_read": "integer - Read status (0: unread, 1: read)",
  "created_by": "integer|null - ID of user who created the notification (null for system)",
  "created_at": "datetime - When notification was created",
  "read_at": "datetime|null - When notification was marked as read"
}
```

---

## Usage Examples

### Example 1: Get All Unread Notifications

```bash
curl -X GET "http://your-domain.com/api/notifications?read=0" \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Example 2: Get Unread Count

```bash
curl -X GET http://your-domain.com/api/notifications/unread-count \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Example 3: Mark Specific Notification as Read

```bash
curl -X PATCH http://your-domain.com/api/notifications/15/read \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Example 4: Mark All as Read

```bash
curl -X PATCH http://your-domain.com/api/notifications/mark-all-read \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Example 5: Delete Notification

```bash
curl -X DELETE http://your-domain.com/api/notifications/15 \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

---

## Real-time Notifications

### WebSocket Integration

The notification system supports real-time delivery via WebSocket connections.

**WebSocket Endpoint:**
```
ws://your-domain.com:8080
```

**Connection:**
```javascript
const ws = new WebSocket('ws://your-domain.com:8080');

ws.onopen = () => {
  // Send authentication
  ws.send(JSON.stringify({
    type: 'auth',
    token: 'your_access_token'
  }));
};

ws.onmessage = (event) => {
  const notification = JSON.parse(event.data);
  console.log('New notification:', notification);
  // Update UI with new notification
};
```

**Real-time Notification Message:**
```json
{
  "type": "notification",
  "data": {
    "id": 16,
    "message": "Time to drink water",
    "type": "schedule_reminder",
    "link": "/schedules/3",
    "created_at": "2026-01-17 10:00:00"
  }
}
```

---

## Pagination

When retrieving notifications, use pagination for better performance:

**Request:**
```
GET /api/notifications?page=2&limit=20
```

**Response includes all notifications for that page:**
```json
{
  "status": true,
  "message": "Notifications retrieved successfully",
  "data": [
    // Array of notifications
  ]
}
```

---

## Best Practices

1. **Poll for updates** if not using WebSocket (recommended interval: 30-60 seconds)
2. **Mark as read** when user views the notification
3. **Delete old notifications** periodically to keep the list manageable
4. **Show unread count** in app badge/icon for better UX
5. **Group notifications** by type or date on the client-side
6. **Cache notifications** on client to reduce API calls
7. **Use filters** to load only relevant notifications

---

## Notification Workflow

### Creating Notifications (System/Backend)

Notifications are typically created by the system or other backend processes:

```php
// Using the notification helper
notifyScheduleReminder(
    userId: 1,
    scheduleId: 5,
    scheduleType: 'medicine',
    title: 'Morning Medication',
    message: 'Time to take your medicine: Morning Medication'
);
```

### User Interaction Flow

1. **Receive notification** (real-time via WebSocket or polling)
2. **Display notification** in app notification center
3. **User clicks notification** → Navigate to `link` URL
4. **Mark as read** automatically or on user action
5. **User can delete** old or unwanted notifications

---

## Common Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Operation failed |
| 401 | Unauthorized - Invalid or missing authentication |
| 404 | Not Found - Notification doesn't exist or doesn't belong to user |
| 500 | Internal Server Error - Server-side error |

---

## Security Notes

1. **Users can only access their own notifications** - enforced by user_id check
2. **Cannot read other users' notifications** - strict authorization
3. **WebSocket requires authentication** - token-based auth
4. **Rate limiting recommended** - prevent notification spam
5. **XSS protection** - sanitize message content when displaying
