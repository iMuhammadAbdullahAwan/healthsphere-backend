# HealthSphere API Documentation

Complete API documentation for the HealthSphere backend system.

## 📚 Documentation

This documentation covers all available API endpoints organized by feature:

### Feature Documentation

- **[Authentication](AUTH.md)** - User registration, login, OTP, password reset
- **[User Profile](PROFILE.md)** - Profile management, updates, image upload
- **[Notifications](NOTIFICATIONS.md)** - Notification management and real-time updates
- **[Schedules](SCHEDULES.md)** - Complete schedule management system
- **[Food & Nutrition](FOOD.md)** - AI analysis and food logging
- **[Step Tracking](STEPS.md)** - Pedometer sessions and tracking goals
- **[Exercise Logging](EXERCISES.md)** - Manual activity tracking and history
- **[Schedules Frontend Reference](SCHEDULES_API.md)** - Schedule status flows and advanced actions

### Testing

- **[Postman Collection](../postman/postman-collection.json)** - Import this JSON file into Postman for instant API testing

---

## 🚀 Quick Start

### 1. Import Postman Collection

1. Open Postman
2. Click **Import**
3. Select `postman/postman-collection.json`
4. Update the `base_url` variable to your server URL

### 2. Setup Environment Variables

The collection includes these variables:
- `base_url` - Your API base URL (default: `http://localhost/healthsphere-backend`)
- `access_token` - Auto-populated after login
- `refresh_token` - Auto-populated after login
- `user_id` - Auto-populated after login

### 3. Authentication Flow

1. Use **Register New User** or **Login with Email & Password**
2. Tokens are automatically saved to collection variables
3. All subsequent requests use the `access_token` automatically

---

## 📖 API Overview

### Base URL
```
http://your-domain.com/api
```

### Authentication
Most endpoints require JWT authentication via Bearer token:
```
Authorization: Bearer <access_token>
```

### Response Format
All API responses follow this structure:

**Success Response:**
```json
{
  "status": true,
  "message": "Operation successful",
  "data": { /* response data */ }
}
```

**Error Response:**
```json
{
  "status": false,
  "message": "Error description",
  "data": {
    "errors": {
      "field_name": "Specific error message"
    }
  }
}
```

---

## 📋 Endpoint Summary

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | Login with email & password |
| POST | `/api/auth/send-otp` | Send OTP to email |
| POST | `/api/auth/verify-otp` | Verify OTP code |
| POST | `/api/auth/login-otp` | Login with OTP |
| POST | `/api/auth/refresh` | Refresh access token |
| POST | `/api/auth/forgot-password` | Request password reset |
| POST | `/api/auth/reset-password` | Reset password with token |
| POST | `/api/auth/logout` | Logout user (protected) |

### User Profile (Protected)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/users/profile` | Get user profile |
| PUT | `/api/users/profile` | Update user profile |
| POST | `/api/users/profile/image` | Upload profile image |
| DELETE | `/api/users/account` | Delete user account |

### Notifications (Protected)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/notifications` | Get all notifications |
| GET | `/api/notifications/unread-count` | Get unread count |
| GET | `/api/notifications/{id}` | Get single notification |
| PATCH | `/api/notifications/{id}/read` | Mark as read |
| PATCH | `/api/notifications/mark-all-read` | Mark all as read |
| DELETE | `/api/notifications/{id}` | Delete notification |

### Schedules (Protected)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/schedules` | Get all schedules |
| GET | `/api/schedules/stats` | Get schedule statistics |
| GET | `/api/schedules/{id}` | Get single schedule |
| GET | `/api/schedules/{id}/logs` | Get schedule logs |
| POST | `/api/schedules` | Create new schedule |
| POST | `/api/schedules/logs/{id}/complete` | Mark log as completed |
| PUT | `/api/schedules/{id}` | Update schedule |
| PATCH | `/api/schedules/{id}/status` | Update schedule status |
| POST | `/api/schedules/{id}/cancel` | Cancel schedule or one occurrence |
| POST | `/api/schedules/{id}/uncancel` | Uncancel schedule or one occurrence |
| POST | `/api/schedules/{id}/done` | Mark done (schedule or one occurrence) |
| POST | `/api/schedules/{id}/undone` | Undo done (schedule or one occurrence) |
| POST | `/api/schedules/logs/{id}/undo` | Undo a completed log |
| DELETE | `/api/schedules/{id}` | Delete schedule |

---

## 🔐 Security

### Best Practices

1. **Always use HTTPS in production**
2. **Store tokens securely:**
   - Use HTTP-only cookies for refresh tokens
   - Never store tokens in localStorage for sensitive data
3. **Implement rate limiting** on authentication endpoints
4. **Validate all inputs** on both client and server
5. **Handle token expiration** gracefully
6. **Clear tokens completely on logout**
7. **Use strong passwords** (min 8 chars, uppercase, lowercase, number, special char)

### Token Lifecycle

- **Access Token:** 1 hour expiration
- **Refresh Token:** 30 days expiration
- **Auto-refresh:** Use refresh token to get new access token before expiration

---

## 📊 Status Codes

| Code | Description |
|------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Invalid or missing authentication |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Internal Server Error - Server-side error |

---

## 🧪 Testing Guide

### Using Postman

1. **Import Collection**
   - File → Import → Select `postman-collection.json`

2. **Set Environment**
   - Update `base_url` to your server URL

3. **Test Authentication**
   - Run "Register New User" or "Login with Email & Password"
   - Tokens are auto-saved to variables

4. **Test Protected Endpoints**
   - All requests now include the `access_token` automatically
   - Try "Get Profile", "Get All Schedules", etc.

### Testing Schedule Types

The collection includes pre-configured examples for all 6 schedule types:
- Medicine Schedule
- Food Schedule
- Water Schedule
- Running Schedule
- Sleep Schedule
- Custom Schedule

Try creating each type to understand the different field requirements.

---

## 🌐 Real-time Features

### WebSocket Support

Notifications support real-time delivery via WebSocket:

**Endpoint:** `ws://your-domain.com:8080`

**Example Connection:**
```javascript
const ws = new WebSocket('ws://your-domain.com:8080');

ws.onopen = () => {
  ws.send(JSON.stringify({
    type: 'auth',
    token: 'your_access_token'
  }));
};

ws.onmessage = (event) => {
  const notification = JSON.parse(event.data);
  // Handle new notification
};
```

---

## 📦 Database Migrations

Before using the API, run migrations:

```bash
php spark migrate
```

This creates the required database tables:
- `users` - User accounts
- `otps` - One-time passwords
- `notifications` - User notifications
- `notification_users` - Notification recipients
- `schedules` - Schedule definitions
- `schedule_logs` - Completion tracking

---

## 🛠️ Development Setup

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd healthsphere-backend
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Configure Environment**
   ```bash
   cp example.env .env
   # Edit .env with your database credentials
   ```

4. **Run Migrations**
   ```bash
   php spark migrate
   ```

5. **Start Development Server**
   ```bash
   php spark serve
   ```

---

## 📝 Version History

### v1.0.0 (Current)
- ✅ User authentication (email/password and OTP)
- ✅ User profile management
- ✅ Real-time notifications
- ✅ Schedule management (6 types)
- ✅ Completion tracking
- ✅ Statistics and analytics

---

## 🤝 Support

For questions or issues:
1. Check the specific feature documentation
2. Review the Postman collection examples
3. Verify your authentication token is valid
4. Check server logs for detailed error messages

---

## 📄 License

This documentation is part of the HealthSphere project.

---

**Last Updated:** January 17, 2026
**API Version:** 1.0.0
