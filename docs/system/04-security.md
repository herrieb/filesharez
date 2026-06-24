# Security & Access Control

## `config/packages/security.yaml` (in full)

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        main:
            lazy: true
            provider: app_user_provider
            custom_authenticators:
                - App\Security\LoginFormAuthenticator
            logout:
                path: app_logout
                target: app_login

    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/r/, roles: PUBLIC_ACCESS }
        - { path: ^/d/, roles: PUBLIC_ACCESS }
        - { path: ^/$, roles: PUBLIC_ACCESS }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: ROLE_USER }
```

## How this maps to the app

- **Authentication** is custom — `App\Security\LoginFormAuthenticator` handles the form login at `/login`. No LDAP, no OIDC, no API tokens. The user entity is loaded by email.
- **Password hashing** is `auto` — Symfony picks `auto` which means `bcrypt` for new hashes and accepts the older algorithms for legacy. Default cost is 10.
- **`ROLE_USER`** is the default for every account (assigned at user creation by the admin).
- **`ROLE_ADMIN`** is granted via the `roles` JSON field on `User`. Admins get everything: their own user-level features plus the `/admin/*` panel.
- **Public paths:** `/`, `/login`, `/r/*` (file-request anonymous upload), `/d/*` (transfer download). The first one is the marketing landing; `/login` and the public APIs are obvious.
- **Authenticated by default** — anything not matching the public patterns above requires `ROLE_USER`.

## Access matrix

| Path prefix | Required role | Why |
|---|---|---|
| `/admin/*` | `ROLE_ADMIN` | Admin panel |
| `/`, `/login` | `PUBLIC_ACCESS` | Public landing + login form |
| `/r/{token}/...` | `PUBLIC_ACCESS` | Anonymous uploader for file requests |
| `/d/{token}/...` | `PUBLIC_ACCESS` | Transfer download (token-based access) |
| Everything else | `ROLE_USER` | App + account + library + transfers |

## CSRF

- Symfony's CSRF protection is enabled globally in `config/packages/framework.yaml` (the default).
- Every form is rendered with `csrf_token` (form types that include `_token` automatically). Templates that build forms manually include the CSRF field.
- Two POST endpoints are **deliberately CSRF-exempt** because they're called from JavaScript using `fetch` with no form context:
  - `POST /library/share` (bulk share from the library browse view)
  - `POST /library/sources/{id}/item` (delete a library file)
  - `POST /library/sources/{id}/upload` (upload file)
  - `POST /library/sources/{id}/mkdir` (create folder)
  - `POST /library/sources/{id}/rescan` (rescan)
  - `POST /account/profile/theme/{id}/delete` (delete a custom theme)
  All of these require `ROLE_USER` (via the firewall + `IsGranted`), so a CSRF token wouldn't add real protection anyway — the session cookie is the auth.

## Rate limiting

`config/packages/rate_limiter.yaml` defines limits used by:
- `PublicUploadController` (anonymous `/r/{token}` uploads) — 10 attempts / minute per IP
- `LoginFormAuthenticator` — implicit via Symfony's built-in login_throttling (10 failures → 1-minute lockout)

## Tenant model (current state)

Today, the app is single-tenant. There is one global user pool. There is no concept of "company" or "organization" — every user is independent, and the library is a personal folder tree per user.

The [roadmap](../roadmap/00-tenancy-encryption.md) describes the planned migration to multi-tenant orgs with per-user encrypted library storage. That's a future build, not a current feature.

## Threat model (current state)

| Adversary | What they can do |
|---|---|
| Network attacker without credentials | Hit the public landing, login, or someone else's share link. Nothing else. |
| Logged-in user | Own data only. Cannot read others' transfers, library, or activity logs. |
| Network attacker with a valid share link | Download the file. The token is in the URL — anyone with it can use it. There is no per-recipient authentication. |
| Disk thief | All uploaded files (LocalStorage), all library contents, the database. **Library is not encrypted at rest** — see the roadmap. |
| Server admin | Everything. The DB, the filesystem, the application code. |
| Compromised PHP-FPM process | Everything. The session cookie is the only thing keeping you out. |

## What's already hardened

- `Location /storage { deny all; return 404; }` in nginx — no file is ever served from `/app/storage` over HTTP
- The raw transfer token is never stored in the DB — only `hash('sha256', $rawToken)`. The raw token lives only in the URL, in the session for logged-in owners, and in the email body.
- File names + paths in the library are sanitized (`preg_replace('/[^a-zA-Z0-9._ -]/', '_', $name)`) and the path is re-validated against `LibraryStorage::assertInsideSource` on every read/write
- Path traversal is blocked at the storage layer: `LibraryStorage::resolveUnderSource` does `realpath()` and a prefix check, and `assertInsideSource` enforces it
- CSRF is enabled by default for all forms
- `fastcgi_buffering off` is on so the server doesn't buffer large responses (e.g. ZIPs of large folders)
- Quota is enforced via `User::reserved_bytes` during a tus upload — you can't upload more than the cap even with parallel uploads

## What to harden next (see the roadmap)

- Tenant isolation on share links (block non-company users from using a company's link)
- At-rest encryption for the library (per-user AES-256-GCM)
- 2FA on user login
- Webhook signing (when webhooks are added)
