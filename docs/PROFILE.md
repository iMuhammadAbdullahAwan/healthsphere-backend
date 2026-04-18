# User Profile API Documentation

Complete guide for user profile management endpoints in the HealthSphere API.

## Base URL
```
http://your-domain.com/api/users
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

### 1. Get User Profile

Retrieve the authenticated user's profile information.

**Endpoint:** `GET /api/users/profile`

**Authentication:** Required

**Request Body:** None

**Success Response (200):**
```json
{
  "status": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "full_name": "John Doe",
    "date_of_birth": "1990-05-15",
    "gender": "male",
    "phone": "+1234567890",
    "role": "user",
    "profile_img": "uploads/profiles/1_123456.jpg",
    "created_at": "2026-01-10 10:00:00",
    "last_login": "2026-01-17 08:30:00"
  }
}
```

**Error Responses:**
- `401`: Unauthorized (invalid or missing token)
- `404`: User not found
- `500`: Server error

---

### 2. Update User Profile

Update the authenticated user's profile information.

**Endpoint:** `PUT /api/users/profile`

**Authentication:** Required

**Request Body:**
```json
{
  "full_name": "John Smith",
  "date_of_birth": "1990-05-15",
  "gender": "male",
  "phone": "+1234567890"
}
```

**Field Details:**
- `full_name` (optional): 2-255 characters, letters and spaces only
- `date_of_birth` (optional): Format YYYY-MM-DD
- `gender` (optional): One of: `male`, `female`, `other`
- `phone` (optional): 10-20 characters, numbers, spaces, dashes, parentheses, and plus sign

**Validation Rules:**
```json
{
  "full_name": "min_length[2]|max_length[255]|alpha_space",
  "date_of_birth": "valid_date[Y-m-d]",
  "gender": "in_list[male,female,other]",
  "phone": "min_length[10]|max_length[20]|regex_match[/^\\+?[0-9\\s\\-\\(\\)]+$/]"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "full_name": "John Smith",
    "date_of_birth": "1990-05-15",
    "gender": "male",
    "phone": "+1234567890",
    "role": "user",
    "profile_img": "uploads/profiles/1_123456.jpg",
    "created_at": "2026-01-10 10:00:00",
    "last_login": "2026-01-17 08:30:00"
  }
}
```

**Error Responses:**
- `400`: No fields to update
- `401`: Unauthorized
- `422`: Validation failed
- `500`: Server error

**Example Validation Error (422):**
```json
{
  "status": false,
  "message": "Validation failed. Please check your input.",
  "data": {
    "errors": {
      "full_name": "Full name must be at least 2 characters.",
      "phone": "Phone number must be at least 10 characters."
    }
  }
}
```

---

### 3. Upload Profile Image

Upload or update the user's profile picture.

**Endpoint:** `POST /api/users/profile/image`

**Authentication:** Required

**Content-Type:** `multipart/form-data`

**Request Body (Form Data):**
```
profile_img: [File Upload]
```

**File Requirements:**
- **Allowed formats:** JPG, JPEG, PNG, GIF
- **Maximum size:** 2MB (2048 KB)
- **Maximum dimensions:** 4096x4096 pixels
- **Field name:** `profile_img`

**Success Response (200):**
```json
{
  "status": true,
  "message": "Profile image uploaded successfully",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "full_name": "John Doe",
    "profile_img": "uploads/profiles/1_1737123456.jpg",
    "role": "user"
  }
}
```

**Error Responses:**
- `400`: No valid image file uploaded
- `401`: Unauthorized
- `422`: Image validation failed
- `500`: Server error

**Example Validation Error (422):**
```json
{
  "status": false,
  "message": "Image validation failed. Please check your file and try again.",
  "data": {
    "errors": {
      "profile_img": "Image size cannot exceed 2MB."
    }
  }
}
```

---

### 4. Delete User Account

Permanently delete the authenticated user's account. Requires password confirmation.

**Endpoint:** `DELETE /api/users/account`

**Authentication:** Required

**Request Body:**
```json
{
  "password": "CurrentPassword123!"
}
```

**Field Details:**
- `password` (required): Current password for confirmation

**Success Response (200):**
```json
{
  "status": true,
  "message": "Account deleted successfully",
  "data": null
}
```

**Response also clears authentication cookies:**
- `_healthsphere_access_token`
- `_healthsphere_refresh_token`

**Error Responses:**
- `401`: Unauthorized
- `403`: Invalid password
- `422`: Validation failed
- `500`: Failed to delete account

**Example Error (403):**
```json
{
  "status": false,
  "message": "Invalid password",
  "data": null
}
```

---

## Usage Examples

### Example 1: Get Current User Profile

```bash
curl -X GET http://your-domain.com/api/users/profile \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Example 2: Update Profile

```bash
curl -X PUT http://your-domain.com/api/users/profile \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "John Smith",
    "phone": "+1234567890",
    "gender": "male"
  }'
```

### Example 3: Upload Profile Image

```bash
curl -X POST http://your-domain.com/api/users/profile/image \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  -F "profile_img=@/path/to/image.jpg"
```

### Example 4: Delete Account

```bash
curl -X DELETE http://your-domain.com/api/users/account \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  -H "Content-Type: application/json" \
  -d '{
    "password": "CurrentPassword123!"
  }'
```

---

## Profile Image Storage

Profile images are stored in the server's public directory:
```
public/uploads/profiles/
```

**Naming Convention:**
```
{user_id}_{timestamp}.{extension}
```

**Example:**
```
1_1737123456.jpg
```

**Accessing Images:**
The `profile_img` field in the response contains the relative path. To display:
```
http://your-domain.com/uploads/profiles/1_1737123456.jpg
```

---

## Field Constraints Summary

| Field | Type | Required | Min | Max | Pattern |
|-------|------|----------|-----|-----|---------|
| full_name | string | No | 2 | 255 | Letters and spaces only |
| date_of_birth | date | No | - | - | YYYY-MM-DD |
| gender | enum | No | - | - | male, female, other |
| phone | string | No | 10 | 20 | +digits, spaces, dashes, () |
| profile_img | file | No | - | 2MB | JPG, JPEG, PNG, GIF |
| password | string | Yes* | - | - | For account deletion |

*Required only for account deletion

---

## Security Notes

1. **Profile updates** are limited to the authenticated user's own profile
2. **Password confirmation** required for account deletion to prevent unauthorized deletion
3. **File upload validation** prevents malicious files
4. **Image size limits** prevent storage abuse
5. **Soft delete** - Account data may be retained for a period before permanent deletion
6. **Token invalidation** - All tokens are cleared upon account deletion

---

## Common Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid input data |
| 401 | Unauthorized - Invalid or missing authentication |
| 403 | Forbidden - Invalid password or insufficient permissions |
| 404 | Not Found - User profile not found |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Internal Server Error - Server-side error |
