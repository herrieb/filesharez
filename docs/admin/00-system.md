# Admin

Admin lives behind the `ROLE_ADMIN` role. There's a single admin section at `/admin/*` plus a few admin actions on other pages (toggle user, delete file request).

## Routes

See [controllers/00-routes.md](../controllers/00-routes.md) for the full list. The admin ones are:

| Path | Method | Purpose |
|---|---|---|
| `/admin` | GET | Dashboard with totals |
| `/admin/users` | GET | List active users |
| `/admin/users/create` | GET/POST | Create a new user |
| `/admin/users/{id}/toggle` | GET | Toggle the `isActive` flag |
| `/admin/file-requests` | GET | List all file requests |
| `/admin/file-requests/{id}/toggle` | POST | Activate / deactivate |
| `/admin/file-requests/{id}/delete` | POST | Delete |

## How to create the first admin

There's no CLI command to create an admin — you do it via the database. The pattern:

1. Create a normal user via `/admin/users/create`
2. Promote them to admin:

```sql
UPDATE users SET roles = '["ROLE_USER","ROLE_ADMIN"]' WHERE email = 'admin@example.com';
```

The `User::getRoles()` method adds `ROLE_USER` if the array is empty, but if you want `ROLE_ADMIN` you have to set both explicitly.

## `AdminController::systemHealth`

The system health page (at `/admin/system-health`) gives you:

- Total transfers / active / expired / exhausted counts
- Total storage (sum of all `Transfer.totalSizeBytes`)
- User count
- A "Storage" card with **per-location disk breakdown**:
  - `LocalStorage` (uploads, previews, tmp, quarantine) — usually `/mnt/data` on the host, `/app/storage/transfers` in the container
  - `Library` (shared folders) — usually `/mnt/library` on the host, `/app/storage/...` in the container
- A progress bar showing the worst-case disk (the one closest to filling)
- DB size (via `pg_database_size()`)
- PHP and Symfony versions
- A per-user storage list (top users by `usedStorage`)

Each disk location has a status pill:
- **Critical** ≥ 90% used
- **Warning** ≥ 75% used
- **OK** below 75%

This replaced the original buggy code that showed the system root filesystem. The fix was: point `disk_total_space` at the real library path, and add the uploads mount as a second location.

## User management

`AdminController::users` lists all users with their active flag. The `toggleUser` action flips the flag. Disabled users cannot log in (the `LoginFormAuthenticator` checks `$user->isActive()`).

There's no "edit user quota" UI. To change a user's quota:

```sql
UPDATE users SET quota_bytes = 53687091200 WHERE email = 'user@example.com';   -- 50 GB
```

A future feature is a "Users" page in the admin section with edit forms.

## File request management

`AdminController::fileRequests` lists all file requests across all users. Each row has buttons to:

- Toggle active / inactive (`/admin/file-requests/{id}/toggle`)
- Delete (`/admin/file-requests/{id}/delete`)

These are admin overrides — a file request owner can only deactivate their own request, not delete it. Admins can do both.

## What the rebuild should keep

- The per-location disk breakdown on system health (the bug fix)
- The user toggle (active/inactive)
- File request management

## What the rebuild should add

- A real "edit user" page (quota, role, deactivation)
- An "impersonate user" button (with audit log entry)
- A "transfers" page that lists all transfers across all users with filters
- A "delete file request" confirmation modal (currently the form is a regular POST with no confirm — risky)
- An audit log section that shows admin actions (currently they go only to Monolog)
