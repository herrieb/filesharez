# Controllers & Routes

Every controller extends `Symfony\Bundle\FrameworkBundle\Controller\AbstractController`. Authentication is by email (the User entity's `getUserIdentifier()`). The `IsGranted` attribute enforces the role on the class or the method.

## `AccountController` — `/account/*`

**File:** `src/Controller/AccountController.php`
**Prefix:** `/account`
**Guard:** `ROLE_USER` (class-level)
**Depends on:** `EntityManagerInterface`, `ThemeRegistry`, `ThemeStore`, `LibraryAccessService`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `index()` | GET | `/account` | `app_account` | Account landing page |
| `profile(Request)` | GET/POST | `/account/profile` | `app_account_profile` | Edit name, email, theme picker |
| `security(Request, UserPasswordHasherInterface)` | GET/POST | `/account/security` | `app_account_security` | Change password |
| `updateTheme(Request)` | POST | `/account/profile/theme` | `app_account_theme_update` | AJAX-aware theme switch — sets the user's `theme` column |
| `downloadTheme(string $id)` | GET | `/account/profile/theme/{id}/download` | `app_account_theme_download` | Download a theme as a zip (theme.xml + assets/) |
| `uploadTheme(Request)` | POST | `/account/profile/theme/upload` | `app_account_theme_upload` | Install a theme from a user-uploaded zip |
| `deleteTheme(string $id, Request)` | POST | `/account/profile/theme/{id}/delete` | `app_account_theme_delete` | Delete a user-installed theme (built-ins can't be deleted) |
| `libraryActivity()` | GET | `/account/library-activity` | `app_account_library_activity` | Owner-direct library access log |

## `AdminController` — `/admin/*`

**File:** `src/Controller/AdminController.php`
**Prefix:** `/admin`
**Guard:** `ROLE_ADMIN`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `dashboard(...)` | GET | `/admin` | `app_admin_dashboard` | Counts of users / transfers / file requests |
| `users(UserRepository)` | GET | `/admin/users` | `app_admin_users` | List active users |
| `createUser(...)` | GET/POST | `/admin/users/create` | `app_admin_user_create` | Create a new user (form + handler) |
| `toggleUser(...)` | GET | `/admin/users/{id}/toggle` | `app_admin_user_toggle` | Toggle user active flag |
| `fileRequests(FileRequestRepository)` | GET | `/admin/file-requests` | `app_admin_file_requests` | List all file requests |
| `toggleFileRequest(...)` | POST | `/admin/file-requests/{id}/toggle` | `app_admin_file_request_toggle` | Activate/deactivate |
| `deleteFileRequest(...)` | POST | `/admin/file-requests/{id}/delete` | `app_admin_file_request_delete` | Delete a file request |
| `systemHealth(...)` | GET | `/admin/system-health` | `app_admin_system_health` | Per-location disk breakdown, DB size, PHP/Symfony version, per-user storage |

`systemHealth` computes the disk usage by calling `disk_total_space`/`disk_free_space` on:
- `/app/storage/transfers` → the uploads storage mount (`/mnt/data` on the host)
- The library path from `app.library_path` parameter (default `/mnt/library`)

Status pills show **Critical** ≥ 90% / **Warning** ≥ 75% / **OK** below. The `Disk Usage` progress bar below the cards uses the library disk (which is the bottleneck) and re-uses the values from the cards.

## `DashboardController` — `/dashboard`

**File:** `src/Controller/DashboardController.php`
**Prefix:** `/dashboard`
**Guard:** `ROLE_USER`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `index(...)` | GET | `/dashboard` | `app_dashboard` | Quota meter, recent transfers, expiring-soon count, library usage, saved tokens |

## `DownloadController` — public, no role guard

**File:** `src/Controller/DownloadController.php`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `download(string $token, DownloadService)` | GET | `/d/{token}` | `app_download` | Render the download landing page (with password gate if needed) |
| `downloadFile(...)` | any | `/d/{token}/file/{fileId}` | `app_download_file` | Stream a single file; library-backed files go through `LibraryStorageStreamer` |
| `previewFile(...)` | GET | `/d/{token}/preview/{fileId}` | `app_download_preview` | Inline preview for image/video/audio/pdf/text |
| `textContent(...)` | GET | `/d/{token}/text/{fileId}` | `app_download_text` | JSON text content for `isText` files |
| `downloadZip(...)` | GET | `/d/{token}/zip` | `app_download_zip` | Stream the whole transfer as a `ZipStream` |

The first successful download dispatches `SendDownloadNotificationEmail` (the owner gets notified).

## `ErrorController` — renders 404 / 403 / 500

**File:** `src/Controller/ErrorController.php`

Wired as the `error_handler` in `config/services.yaml` via a custom `ErrorListener`. Picks the right template based on `HttpExceptionInterface::getStatusCode()`:
- 404 → `errors/404.html.twig`
- 403 → `errors/403.html.twig`
- Anything else → `errors/500.html.twig`

## `FileRequestController` — `/file-requests/*`

**File:** `src/Controller/FileRequestController.php`
**Prefix:** `/file-requests`
**Guard:** `ROLE_USER`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `list(Request)` | GET | `/file-requests` | `app_file_requests` | User's file requests with raw tokens from session |
| `showCreateForm()` | GET | `/file-requests/new` | `app_file_request_create` | Render create form |
| `create(Request)` | POST | `/file-requests/create` | `app_file_request_store` | Create a file request, store raw token in session |
| `deactivate(string $id)` | POST | `/file-requests/{id}/deactivate` | `app_file_request_deactivate` | Deactivate (JSON) |
| `activate(string $id)` | POST | `/file-requests/{id}/activate` | `app_file_request_activate` | Activate (JSON) |
| `delete(string $id)` | POST | `/file-requests/{id}/delete` | `app_file_request_delete` | Delete (admin or owner) |

## `LandingController` — `/`

**File:** `src/Controller/LandingController.php`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `index()` | GET | `/` | `app_landing` | Public marketing/landing page |

The landing is the only public page besides `/login`, `/d/*`, `/r/*`. It's a static page with no DB queries — all the marketing copy is in `templates/landing/index.html.twig`.

## `LibraryController` — `/library/*`

**File:** `src/Controller/LibraryController.php`
**Prefix:** `/library`
**Guard:** `ROLE_USER`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `index(Request)` | GET | `/library` | `app_library` | Library root — sources + top-level items |
| `browseSource(string $id, Request)` | GET | `/library/sources/{id}/browse` | `app_library_source_browse` | Browse a folder inside a source |
| `createSource(Request)` | POST | `/library/sources` | `app_library_source_create` | Register a new library source (JSON) |
| `rescan(string $id, Request)` | POST | `/library/sources/{id}/rescan` | `app_library_source_rescan` | Trigger a rescan |
| `deleteSource(string $id)` | POST | `/library/sources/{id}/delete` | `app_library_source_delete` | Delete source (JSON) |
| `share(string $id, Request)` | POST | `/library/items/{id}/share` | `app_library_item_share` | Legacy single-item share by LibraryItem id |
| `shareSelection(Request)` | POST | `/library/share` | `app_library_share` | Bulk share by sourceId + relativePaths[] |
| `uploadFile(string $id, Request)` | POST | `/library/sources/{id}/upload` | `app_library_upload` | Upload a file into a source folder (multipart) |
| `createFolder(string $id, Request)` | POST | `/library/sources/{id}/mkdir` | `app_library_mkdir` | Create a folder |
| `deleteItem(string $id, Request)` | DELETE/POST | `/library/sources/{id}/item` | `app_library_item_delete` | Delete a file or folder |
| `ownerDownload(string $id, Request)` | GET | `/library/sources/{id}/download` | `app_library_owner_download` | Owner-direct download (no token) — logs to `OwnerAccessLog` |
| `ownerPreview(string $id, Request)` | GET | `/library/sources/{id}/preview` | `app_library_owner_preview` | Owner-direct inline preview — logs to `OwnerAccessLog` |

## `PublicUploadController` — `/r/*` (anonymous)

**File:** `src/Controller/PublicUploadController.php`
**Rate-limited:** `public_upload` policy (10 / min / IP)

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `uploadPage(string $token)` | GET | `/r/{token}` | `app_file_request_upload` | Render the anonymous uploader page |
| `handleUpload(string $token, Request)` | POST | `/r/{token}/upload` | `app_file_request_submit` | Accept uploads, create a `Transfer` on the owner's quota, dispatch `SendFileRequestUploadEmail` |

The recipient's quota is **the owner's**. The anonymous uploader is consuming the owner's storage.

## `ResumableUploadController` — `/upload/resumable/*`

**File:** `src/Controller/ResumableUploadController.php`
**Prefix:** `/upload/resumable`
**Guard:** `ROLE_USER`

This is the tus-protocol implementation. The endpoints are NOT auto-discoverable — the client knows the URL from the page it loaded on.

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `options(string $id = null)` | OPTIONS | `/upload/resumable` and `/upload/resumable/{id}` | `app_upload_resumable_root`, `app_upload_resumable_options` | tus capability discovery |
| `create(Request)` | POST | `/upload/resumable` | `app_upload_resumable_create` | Create a session. Reads `Upload-Length` and `Upload-Metadata` headers, returns 201 + `Location` |
| `patch(string $id, Request)` | PATCH | `/upload/resumable/{id}` | `app_upload_resumable_patch` | Append a chunk. Body = chunk bytes. Validates `Upload-Offset` matches the session's current offset. Writes to `var/tus/<id>`. Returns 204 + new `Upload-Offset` |
| `head(string $id)` | HEAD | `/upload/resumable/{id}` | `app_upload_resumable_head` | Returns 200 + `Upload-Offset` / `Upload-Length` so a client can resume after a network drop |
| `delete(string $id)` | DELETE | `/upload/resumable/{id}` | `app_upload_resumable_delete` | Cancel — releases reserved quota, removes temp file, removes the row |
| `finalize(string $id, Request)` | POST | `/upload/resumable/{id}/finalize` | `app_upload_resumable_finalize` | Finalize. Returns 200 with `{transfer, downloadUrl}` JSON |

## `SecurityController` — `/login` + `/logout`

**File:** `src/Controller/SecurityController.php`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `login(AuthenticationUtils)` | GET | `/login` | `app_login` | Render the login form |
| `logout()` | any | `/logout` | `app_logout` | Handled by the firewall's `logout` listener (always throws) |

`App\Security\LoginFormAuthenticator` does the actual form-post validation. It lives in `src/Security/LoginFormAuthenticator.php`.

## `ThemeAssetController` — `/themes/*`

**File:** `src/Controller/ThemeAssetController.php`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `asset(string $id, string $file)` | GET | `/themes/{id}/{file}` | `app_theme_asset` | Serve static theme assets from `var/themes/<id>/assets/` with a strict path-traversal guard |

The guard is: `realpath($file)` must start with `realpath($assetsRoot) + '/'`. Any path that doesn't pass is a 404.

## `TransferController` — `/transfers/*`

**File:** `src/Controller/TransferController.php`
**Prefix:** `/transfers`
**Guard:** `ROLE_USER`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `list(...)` | GET | `/transfers` | `app_transfers` | User's transfers + saved tokens |
| `delete(string $id, ...)` | POST | `/transfers/{id}/delete` | `app_transfer_delete` | Delete a transfer (owner or admin) |
| `revoke(string $id, ...)` | POST | `/transfers/{id}/revoke` | `app_transfer_revoke` | Mark revoked (still in DB but canDownload returns false) |
| `resend(string $id, ...)` | POST | `/transfers/{id}/resend` | `app_transfer_resend` | Re-dispatch recipient email (regenerates token if lost) |
| `extend(string $id, ...)` | POST | `/transfers/{id}/extend` | `app_transfer_extend` | Add 7 days to expiry |

## `UploadController` — `/upload/*`

**File:** `src/Controller/UploadController.php`
**Prefix:** `/upload`
**Guard:** `ROLE_USER`

| Method | HTTP | Path | Route | Purpose |
|---|---|---|---|---|
| `index()` | GET | `/upload` | `app_upload` | Upload landing page (form + resumable endpoint URL) |
| `uploadFileLegacy()` | POST | `/upload/file` | `app_upload_file_legacy` | **Returns 410.** Legacy multipart endpoint, replaced by `/upload/resumable` |
| `uploadText(Request)` | POST | `/upload/text` | `app_upload_text` | Create a `Transfer` from a text snippet |

The text-upload path is still a regular `POST` form (no tus), because the text is a small payload and the user-visible UX is a single form submit, not a chunked upload. It reuses the entire transfer pipeline (token, expiry, download count, email notification).

## Full route table

For a complete list, run:

```bash
docker exec filesharez_app php bin/console debug:router
```

This dumps every route with method, path, and controller. Useful for debugging.
