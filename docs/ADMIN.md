# Admin Delegation Flow

This document defines role scope and delegation flow for `super_admin` and `user_admin`.

## Role Model

- `super_admin`:
- Full platform access.
- Can manage `user_admin` and `user` accounts.
- Can assign and unassign users to user_admin accounts.
- Can view platform-wide analytics.

- `user_admin`:
- Is also a normal user account.
- Can use regular user APIs for their own account.
- Can manage only assigned users (role `user`).
- Can view analytics only for assigned users.
- Can act on behalf of assigned users for all user features.

## Assignment Endpoints (Super Admin)

Super admin APIs are separated under `/api/super-admin/*`.

- `GET /api/super-admin/user-admins`
- Lists all user_admin accounts with assigned user counts.

- `GET /api/super-admin/users/unassigned`
- Lists users that are not assigned to any user_admin.

- `PUT /api/super-admin/users/{id}/assign`
- Body:

```json
{
  "user_admin_id": 12,
  "promote_to_user_admin": true
}
```

- Notes:
- `{id}` is the target `user` id.
- `user_admin_id` should point to a user with role `user_admin`.
- If `user_admin_id` points to a regular `user`, set `promote_to_user_admin=true` to auto-promote before assignment.

- `DELETE /api/super-admin/users/{id}/assign`
- Notes:
- Unassigns target `user` from current user_admin.

## User Assignment Endpoint (User)

- `GET /api/users/assignment`
- Returns current authenticated user's assignment details, including assigned user_admin profile if available.

- `PUT /api/users/assignment`
- Body: `{ "user_admin_id": 12 }`
- Lets a role `user` account assign itself to a user_admin.

- `DELETE /api/users/assignment`
- Lets a role `user` account unassign itself from current user_admin.

## Scoped Admin Endpoints

- `GET /api/admin/users`
- `user_admin`: returns only assigned users with role `user`.

- `GET /api/admin/analytics`
- `user_admin`: assigned-user metrics only.

- `GET /api/admin/users/{id}/profile`
- Get managed user profile.

- `PUT /api/admin/users/{id}/profile`
- Update managed user profile fields (`full_name`, `date_of_birth`, `gender`, `phone`).

- `POST /api/admin/users/{id}/profile/image`
- Upload managed user profile image (`profile_img` form-data field).

- `DELETE /api/admin/users/{id}`
- `user_admin`: only assigned users with role `user`.

## Super Admin Full-System Endpoints

- `GET /api/super-admin/analytics` (full platform stats)
- `GET /api/super-admin/users` (full platform user list)
- `GET /api/super-admin/users/{id}/profile`
- `PUT /api/super-admin/users/{id}/profile`
- `POST /api/super-admin/users/{id}/profile/image`
- `PUT /api/super-admin/users/{id}/role`
- `PUT /api/super-admin/users/{id}/assign`
- `DELETE /api/super-admin/users/{id}/assign`
- `DELETE /api/super-admin/users/{id}`

## Act On Behalf Flow (User Admin)

To execute regular user features on behalf of an assigned user, include this header in any protected API request:

`X-Act-As-User-Id: <assigned_user_id>`

Example:

```bash
curl -X GET http://your-domain.com/api/users/profile \
  -H "Authorization: Bearer <user_admin_access_token>" \
  -H "X-Act-As-User-Id: 55"
```

Behavior:
- Backend validates actor role (`user_admin` or `super_admin`).
- For `user_admin`, target user must be assigned to that admin.
- Target must be role `user`.
- On success, request runs as the target user context.

## Clear Separation (No Confusion)

- Self profile endpoints under `/api/users/*` are for the current account only.
- Managed profile endpoints under `/api/admin/users/{id}/profile*` are for managing other users.
- Delegated requests are blocked on self profile/assignment mutation endpoints to avoid accidental cross-user updates.

## Error Cases

- `403`: target user not assigned to current user_admin.
- `422`: target is not a role `user` account.
- `404`: target user not found.
- `400`: invalid `X-Act-As-User-Id` header value.
