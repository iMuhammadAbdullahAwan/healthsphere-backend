# Device Guider API

The Device Guider API allows users to log and track readings from various health monitoring devices (e.g., Blood Glucose monitors, scales, blood pressure cuffs).

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/devices/readings` | List readings with search, filters, and pagination |
| POST | `/api/devices/readings` | Create a new device reading |
| GET | `/api/devices/readings/{id}` | Get specific reading details |
| DELETE | `/api/devices/readings/{id}` | Delete a reading |

---

## Create a Reading
`POST /api/devices/readings`

Logs a new reading from a device. Supports image upload via multipart/form-data.

**Request Body (multipart/form-data):**
- `device_name`: (required) Name of the device (e.g., "Blood Glucose Monitor")
- `reading_value`: (required) The numeric value of the reading
- `reading_unit`: (optional) Unit of measurement (e.g., "mg/dL", "kg")
- `device_image`: (optional, File) Photo of the machine reading
- `status`: (optional) `low`, `normal`, `high`. If not provided, the server calculates it based on the `device_name`.
- `recorded_at`: (optional) YYYY-MM-DD HH:mm:ss. Defaults to current time.

**Response:**
```json
{
  "status": true,
  "message": "Device reading saved successfully",
  "data": {
    "id": 1,
    "device_name": "Blood Glucose Monitor",
    "reading_value": 110,
    "reading_unit": "mg/dL",
    "status": "normal",
    "image_path": "uploads/devices/random_name.jpg",
    "recorded_at": "2026-04-25 11:30:00"
  }
}
```

---

## List Readings
`GET /api/devices/readings`

Returns a paginated list of the user's readings.

**Query Parameters:**
- `search`: Filter by device name (case-insensitive)
- `status`: Filter by status (`low`, `normal`, `high`)
- `start_date`: Filter by date (YYYY-MM-DD)
- `end_date`: Filter by date (YYYY-MM-DD)
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 20)

**Response:**
```json
{
  "status": true,
  "message": "Device readings retrieved successfully",
  "data": {
    "readings": [...],
    "pagination": {
      "total": 50,
      "page": 1,
      "limit": 20,
      "pages": 3
    }
  }
}
```

---

## Get Reading Details
`GET /api/devices/readings/{id}`

Returns detailed information for a single reading.

---

## Delete a Reading
`DELETE /api/devices/readings/{id}`

Soft-deletes the reading from the database and removes the associated image file from the server.
