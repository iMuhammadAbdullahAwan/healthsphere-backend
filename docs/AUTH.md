# Authentication API Documentation

Complete guide for all authentication endpoints in the HealthSphere API.

## Base URL
```
http://your-domain.com/api/auth
```

---

## Endpoints

### 1. Register New User

Create a new user account.

**Endpoint:** `POST /api/auth/register`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "full_name": "John Doe"
}
```

**Validation Rules:**
- `email`: Required, valid email format, must be unique
- `password`: Required, minimum 8 characters, must contain:
  - At least one uppercase letter
  - At least one lowercase letter
  - At least one number
  - At least one special character (@$!%*?&#)
- `full_name`: Optional, 2-255 characters, letters and spaces only

**Success Response (201):**
```json
{
  "status": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "full_name": "John Doe",
      "role": "user",
      "profile_img": null,
      "created_at": "2026-01-17 10:30:00"
    },
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600
  }
}
```

**Error Responses:**
- `422`: Validation failed
- `500`: Server error

---

### 2. Login with Email & Password

Authenticate user with email and password.

**Endpoint:** `POST /api/auth/login`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "full_name": "John Doe",
      "role": "user",
      "profile_img": "uploads/profiles/1_123456.jpg"
    },
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600
  }
}
```

**Error Responses:**
- `400`: Missing credentials
- `401`: Invalid credentials
- `500`: Server error

---

### 3. Send OTP

Send one-time password to user's email for OTP-based authentication.

**Endpoint:** `POST /api/auth/send-otp`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "OTP sent to your email",
  "data": {
    "email": "user@example.com",
    "expires_in": 300
  }
}
```

**Error Responses:**
- `400`: Email required
- `404`: User not found
- `500`: Failed to send OTP

---

### 4. Verify OTP

Verify the OTP code sent to user's email.

**Endpoint:** `POST /api/auth/verify-otp`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "user@example.com",
  "otp": "123456"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "OTP verified successfully",
  "data": {
    "verified": true,
    "email": "user@example.com"
  }
}
```

**Error Responses:**
- `400`: Missing email or OTP
- `401`: Invalid or expired OTP
- `500`: Server error

---

### 5. Login with OTP

Complete login using verified OTP.

**Endpoint:** `POST /api/auth/login-otp`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "user@example.com",
  "otp": "123456"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "full_name": "John Doe",
      "role": "user"
    },
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600
  }
}
```

**Error Responses:**
- `400`: Missing credentials
- `401`: Invalid or expired OTP
- `404`: User not found
- `500`: Server error

---

### 6. Refresh Access Token

Get a new access token using refresh token.

**Endpoint:** `POST /api/auth/refresh`

**Authentication:** Not required (uses refresh token)

**Request Body:**
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Token refreshed successfully",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600
  }
}
```

**Error Responses:**
- `400`: Refresh token required
- `401`: Invalid or expired refresh token
- `500`: Server error

---

### 7. Logout

Invalidate current session and clear tokens.

**Endpoint:** `POST /api/auth/logout`

**Authentication:** Required (Bearer Token)

**Headers:**
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Request Body:** None required

**Success Response (200):**
```json
{
  "status": true,
  "message": "Logout successful",
  "data": null
}
```

**Error Responses:**
- `401`: Unauthorized (invalid or missing token)
- `500`: Server error

---

### 8. Forgot Password

Request password reset link via email.

**Endpoint:** `POST /api/auth/forgot-password`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Password reset link sent to your email",
  "data": {
    "email": "user@example.com"
  }
}
```

**Error Responses:**
- `400`: Email required
- `404`: User not found
- `500`: Failed to send email

---

### 9. Reset Password

Reset password using token from email.

**Endpoint:** `POST /api/auth/reset-password`

**Authentication:** Not required

**Request Body:**
```json
{
  "token": "reset_token_from_email",
  "password": "NewSecurePass123!",
  "password_confirm": "NewSecurePass123!"
}
```

**Validation Rules:**
- `token`: Required
- `password`: Required, minimum 8 characters, must contain uppercase, lowercase, number, and special character
- `password_confirm`: Required, must match password

**Success Response (200):**
```json
{
  "status": true,
  "message": "Password reset successful. You can now login with your new password.",
  "data": null
}
```

**Error Responses:**
- `400`: Invalid or expired token
- `422`: Validation failed (passwords don't match)
- `500`: Server error

---

## Authentication Flow

### Standard Login Flow
1. User calls `POST /api/auth/login` with email and password
2. Server validates credentials
3. Server returns access_token and refresh_token
4. Client stores tokens (cookies or localStorage)
5. Client includes access_token in Authorization header for protected routes

### OTP Login Flow
1. User calls `POST /api/auth/send-otp` with email
2. Server sends OTP to email
3. User calls `POST /api/auth/verify-otp` with email and OTP
4. User calls `POST /api/auth/login-otp` with verified credentials
5. Server returns access_token and refresh_token

### Token Refresh Flow
1. When access_token expires, client calls `POST /api/auth/refresh`
2. Server validates refresh_token
3. Server returns new access_token and refresh_token
4. Client updates stored tokens

### Password Reset Flow
1. User calls `POST /api/auth/forgot-password` with email
2. Server sends reset link to email
3. User clicks link (contains token)
4. User calls `POST /api/auth/reset-password` with token and new password
5. Password is updated

---

## Token Information

### Access Token
- **Type:** JWT (JSON Web Token)
- **Expiration:** 1 hour (3600 seconds)
- **Usage:** Include in Authorization header for all protected endpoints
- **Format:** `Authorization: Bearer <access_token>`

### Refresh Token
- **Type:** JWT (JSON Web Token)
- **Expiration:** 30 days
- **Usage:** Used to obtain new access tokens without re-authentication
- **Storage:** HTTP-only cookie (recommended) or secure storage

---

## Error Response Format

All error responses follow this structure:

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

## Security Recommendations

1. **Always use HTTPS** in production
2. **Store tokens securely:**
   - Use HTTP-only cookies for refresh tokens
   - Use secure storage for access tokens (not localStorage for sensitive apps)
3. **Implement rate limiting** on login endpoints
4. **Enable CORS** properly for your domain
5. **Validate all inputs** on client-side before sending
6. **Handle token expiration** gracefully on client-side
7. **Clear tokens on logout** completely
