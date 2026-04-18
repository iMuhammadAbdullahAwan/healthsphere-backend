# Authentication API Documentation

Current auth endpoints and payloads for this backend.

## Base URL

`http://your-domain.com/api/auth`

## Public Endpoints

### Register

- Endpoint: `POST /api/auth/register`
- Auth: Not required
- Request JSON:

```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "full_name": "John Doe",
  "date_of_birth": "1990-05-15",
  "gender": "male",
  "phone": "+1234567890"
}
```

- Notes:
- `full_name` is required.
- `date_of_birth`, `gender`, and `phone` are optional.
- Success message: `User registered successfully`.
- Response includes: `user`, `access_token`, `refresh_token`, `token_type`, `expires_in`.

### Login

- Endpoint: `POST /api/auth/login`
- Auth: Not required
- Request JSON:

```json
{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

- Notes:
- Invalid password returns `403`.
- Response includes: `user`, `access_token`, `refresh_token`, `token_type`, `expires_in`.

### Send OTP

- Endpoint: `POST /api/auth/send-otp`
- Auth: Not required
- Request JSON:

```json
{
  "target": "user@example.com",
  "type": "login"
}
```

- Notes:
- `type` must be one of: `login`, `verification`, `reset`.

### Verify OTP

- Endpoint: `POST /api/auth/verify-otp`
- Auth: Not required
- Request JSON:

```json
{
  "target": "user@example.com",
  "code": "123456"
}
```

### Login with OTP

- Endpoint: `POST /api/auth/login-otp`
- Auth: Not required
- Request JSON:

```json
{
  "target": "user@example.com",
  "code": "123456"
}
```

- Response includes: `user`, `access_token`, `refresh_token`, `token_type`, `expires_in`.

### Refresh Access Token

- Endpoint: `POST /api/auth/refresh`
- Auth: Not required
- Token source: refresh cookie or request body.
- Request JSON (optional if cookie exists):

```json
{
  "refresh_token": "<refresh-token>"
}
```

- Notes:
- Response returns `access_token`, `token_type`, and `expires_in`.
- This endpoint does not return a new refresh token.

### Forgot Password

- Endpoint: `POST /api/auth/forgot-password`
- Auth: Not required
- Request JSON:

```json
{
  "email": "user@example.com"
}
```

- Notes:
- For security, success message is always generic: `If email exists, reset link will be sent`.

### Reset Password

- Endpoint: `POST /api/auth/reset-password`
- Auth: Not required
- Request JSON:

```json
{
  "token": "reset_token_from_email",
  "new_password": "NewSecurePass123!"
}
```

## Protected Endpoint

### Logout

- Endpoint: `POST /api/auth/logout`
- Auth: Required (Bearer token)
- Request body: none
- Behavior:
- Clears refresh token in database.
- Deletes `_healthsphere_access_token` and `_healthsphere_refresh_token` cookies.

## Token Defaults

- Access token expiry default: `3600` seconds.
- Refresh token expiry default: `172800` seconds (2 days).

## Error Format

```json
{
  "status": false,
  "message": "Error description",
  "data": {
    "errors": {
      "field": "message"
    }
  }
}
```
