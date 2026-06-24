# FileShareZ — Rebuild Reference

> A complete, single-source rebuild reference for the current state of FileShareZ.
> Written to be re-readable from scratch — pair it with `git clone` and the
> [Setup Runbook](setup/00-runbook.md) at the end and you can rebuild the system
> from a clean machine.

---

## What this is

A premium-looking, private file-transfer platform built with Symfony 7. Inspired by
WeTransfer + Linear + Vercel. Every user is created by an admin. Every download link
is tokenized, expiring, and download-limited. Uploads are resumable (tus protocol).
The library feature lets each user point at server-side folders and share files from
them, or upload new files into them. The whole UI is themeable — 9 built-in themes
plus a custom-theme uploader. There is a roadmap for the next architectural leap:
multi-tenant orgs + per-user encrypted library.

## What this is not

A marketing page, a user manual, or a release blog. The roadmap is the last section.

## How this is organized

```
docs/
├── README.md                  ← you are here (index + structure)
├── system/                    ← infra, env, docker, nginx, security, mail, scheduler
├── database/                  ← every entity, every migration, every index
├── services/                  ← every service in src/Service/
├── controllers/               ← every controller and every route
├── library/                   ← current library architecture
├── themes/                    ← the 9-theme system + custom themes
├── uploads/                   ← resumable uploads (tus) + text uploads
├── admin/                     ← admin dashboard, system health
├── emails/                    ← email templates + message handlers
├── setup/                     ← runbook to bring up a fresh instance
├── maintenance/               ← operational recipes
└── roadmap/                   ← what to build next (tenancy, encryption, etc.)
```

## TL;DR for the rebuilder

- **Stack:** PHP 8.3 · Symfony 7 · PostgreSQL 16 · Redis 7 · ankitpokhrel/tus-php 2.x · maennchen/zipstream-php 3.x · Tailwind via CDN · Alpine.js via CDN · no JS bundler
- **Layout:** 7 Docker services (app, nginx, postgres, redis, worker, scheduler, mailpit)
- **Storage:** Local filesystem under `/app/storage/{transfers,previews,tmp,quarantine}`; library sources are user-mounted host paths
- **Auth:** custom `LoginFormAuthenticator`, single firewall, no public registration
- **Roles:** `ROLE_USER` (default), `ROLE_ADMIN` (set in `User::$roles`); tenant-style super-admin is in the roadmap
- **API surface:** mostly HTML form posts + a few JSON endpoints. The whole app is server-rendered Twig.

## What changed recently (since the original blueprint)

The repo's `README.md` is the original pre-implementation blueprint (still useful
for *intent*, less useful for *what's actually in the code now*). This rebuild doc
is the source of truth for the current state. Highlights of what was added
beyond the original blueprint:

- **9 themes** with CSS variable system + custom theme upload/download as zip
- **Resumable uploads** via tus (`/upload/resumable`)
- **Library 2.0:** folder navigation, bulk-share, file upload + mkdir + delete, owner-direct preview/download with activity log
- **File requests** (`/r/{token}`): anonymous uploaders send files into a user's quota
- **Owner-direct library access** (`/library/sources/{id}/download` and `/preview`) — owner can preview/download their own files without minting a share token
- **SavedTransferTokens:** the owner can recover a previously-shared link by path
- **3 layout modes** on the app shell: `taskbar` (default, all 6 longhorn/sunset/midori/rose-gold/crt/aurora themes) and `sidebar` (Tokyo, Aquarelle, Brutalist themes)
- **System health page** with per-location disk breakdown (corrected from the original `disk_total_space('/')` bug)
- **Multi-location library support** — the original `LibrarySource` was registered for a single hard-coded path; the current code supports `app.library_path` and `app.library_external_path` and treats them as two allowed roots
