# filesharez

## Architecture & Product Blueprint for a Symfony-Based "WeTransfer-like" File Sharing Platform

You want a modern, secure, stylish, production-grade file transfer platform inspired by WeTransfer, built with the latest version of Symfony and fully containerized with Docker.

This document is written as a full implementation instruction set for OpenCode / AI-assisted development.

### 1. Core Product Vision

A premium-looking private file transfer platform with:

- Authenticated users only
- No public registrations
- Admin-created accounts only
- Beautiful animated UI
- Drag & drop uploads
- Real-time upload progress
- Expiring downloads
- Download limits
- Tokenized secure download links
- Optional text-to-file sharing
- Email delivery
- Automatic cleanup
- Audit/security logging
- Dockerized deployment
- Production-ready architecture

The overall feeling should be:

- Minimal
- Elegant
- Dark luxury aesthetic
- Smooth animations
- Extremely clean UI

Inspired by:

- WeTransfer
- Linear
- Notion
- Vercel
- Raycast

### 2. Port Selection (4113)

Port 4113 is generally safe and usually unused on most systems.

It is NOT a reserved/common service port.

Recommended setup:

| Service             | Port        |
| ------------------- | ----------- |
| Symfony App         | 4113        |
| PostgreSQL          | internal only |
| Redis               | internal only |
| Mailpit             | 8025        |
| Nginx Reverse Proxy | 80/443      |

Use:

```yaml
ports:
  - "4113:80"
```

inside Docker for local/dev mode.

### 3. Technology Stack

**Backend**

- PHP 8.3+
- Symfony 7.x (latest stable)
- API Platform optional
- Doctrine ORM
- Symfony Security
- Symfony Messenger
- Symfony Mailer
- Symfony Scheduler
- Symfony UX Turbo
- Symfony UX LiveComponent

**Frontend**

- Twig
- Stimulus
- Alpine.js (optional)
- TailwindCSS
- GSAP animations
- Dropzone.js OR Uppy.js
- Axios

**Database**

- PostgreSQL 16

**Queue / Background Jobs**

- Redis
- Symfony Messenger

**Storage**

Two modes:

- **Local Storage** — For self-hosted installs.

- **S3-Compatible Storage** — Support:
  - MinIO
  - AWS S3
  - Cloudflare R2
  - Wasabi

Abstract storage layer from day one.

### 4. Recommended Architecture

**Containers**

- /nginx
- /php-fpm
- /postgres
- /redis
- /mailpit
- /worker
- /scheduler

### 5. Recommended Project Structure

```
/src
  /Controller
  /Entity
  /Repository
  /Security
  /Service
  /Storage
  /Message
  /MessageHandler
  /EventSubscriber
  /Scheduler
  /Mailer
  /Form
  /DTO

/templates
/assets
/public
/config
/docker
```

### 6. Main Features

#### A. Authentication

Users MUST login.

No anonymous uploads.

**Roles**

- ROLE_ADMIN
- ROLE_USER

**Admin abilities**

- Create users
- Disable users
- Reset passwords
- View transfers
- Delete transfers
- Force-expire transfers
- View logs
- View storage usage

**User abilities**

- Upload files
- Upload text snippets
- Edit own account
- Change password
- Create transfer links
- Send transfer emails
- Manage own transfers

### 7. Transfer System

**Transfer Types**

**File Upload** — Standard upload.

**Text Upload** — User enters:

- filename
- content

System generates:

- filename.txt

Stores it exactly like uploaded files.

**VERY IMPORTANT:** Text uploads should use the same pipeline as regular uploads.

That means:

- same expiration
- same download limit
- same token generation
- same cleanup system

### 8. Download URL Format

Required:

```
/d/{token}
```

Token requirements:

- minimum 64 chars
- cryptographically secure
- URL-safe

Recommended:

```php
bin2hex(random_bytes(48))
```

This gives 96 chars.

Store:

- hashed token in database
- never raw token

Recommended:

```php
hash('sha256', $token)
```

### 9. Download Rules

Each transfer has:

| Field           | Description        |
| --------------- | ------------------ |
| expires_at      | expiration datetime |
| max_downloads   | allowed downloads  |
| download_count  | current count      |

Default:

```
max_downloads = 1
```

Download disabled when:

- expired
- limit exceeded
- manually revoked

### 10. Auto Cleanup

**CRITICAL FEATURE.**

Expired or exhausted files must be deleted automatically.

**Scheduler**

Use Symfony Scheduler + Messenger.

Run every minute:

```bash
php bin/console app:cleanup-expired-transfers
```

Cleanup must:

- delete DB records
- delete physical files
- delete generated txt files
- remove thumbnails/previews
- clear orphaned chunks

### 11. Upload System

**Requirements**

- Drag & drop
- Multi-file upload
- Huge files support
- Chunked uploads
- Resume support
- Progress bar
- Live speed indicator
- ETA display

Use:

**Uppy.js** strongly recommended

Why:

- resumable uploads
- elegant UI
- stable
- chunk support

### 12. Upload Progress UI

Must show:

- percentage
- upload speed
- remaining time
- file size
- success state
- processing state

Animations:

- smooth
- glassmorphism
- neon accent glow

### 13. Email Delivery Feature

When uploading, user can optionally enter:

- recipient email
- subject
- message

System sends:

- beautiful branded email
- secure download link

### 14. Email Design

Email design MUST match site branding.

**Style**

- dark background
- soft gradients
- elegant typography
- centered card
- rounded corners
- large CTA button

**Email CTA**

- Download File

**Email Footer**

Include:

- expiry date
- max downloads
- sender name
- file size

### 15. Suggested Branding Style

**Theme** — Dark premium aesthetic.

**Colors**

| Color                 | Hex       |
| --------------------- | --------- |
| Primary Background    | #0B1020  |
| Secondary Background  | #111827  |
| Accent                | #7C3AED  |
| Accent Hover          | #8B5CF6  |
| Success               | #10B981  |
| Danger                | #EF4444  |

### 16. Typography

Use:

- Inter
- Geist
- Space Grotesk

Large spacing. Minimal clutter.

### 17. UI/UX Principles

**General Feel**

Every page should feel:

- cinematic
- smooth
- premium
- modern SaaS

**Effects**

- blur backgrounds
- subtle gradients
- floating cards
- micro animations
- soft shadows
- hover transitions

### 18. Dashboard Layout

**Sidebar**

Contains:

- Dashboard
- Upload
- Transfers
- Account
- Admin (admins only)

**Main Dashboard**

Widgets:

- active transfers
- downloads remaining
- storage usage
- expiring soon
- recent activity

### 19. Upload Page Design

**Hero Section** — Large upload card centered.

**Drag Zone** — Animated border pulse.

When dragging:

- border glows
- background shifts

**Upload Queue** — Each file card shows:

- icon
- filename
- progress
- size
- status

### 20. Transfer Page

After upload success, show:

- copy link button
- QR code
- expiration
- remaining downloads

Buttons:

- Copy
- Email Again
- Delete
- Extend Expiry

### 21. Download Page Design

URL:

```
/d/{token}
```

**Download Page Shows**

- filename
- size
- uploader
- expiry
- remaining downloads

**Download Button** — Large centered CTA.

After limit exceeded: show elegant disabled state.

### 22. Suggested Additional Features

**A. Password Protected Downloads**

- Optional transfer password.
- Store hashed.

**B. Virus Scanning**

Use:

- ClamAV container
- Scan uploads asynchronously.
- Prevent downloads until clean.

**C. File Preview**

Preview:

- images
- pdf
- text

**D. Thumbnail Generation**

Generate previews for:

- images
- videos

**E. Transfer Tags**

- Allow tagging transfers.

**F. Favorites**

- Pin important transfers.

**G. Storage Quotas**

- Per-user quotas.

Example:

- 10 GB
- 50 GB
- Unlimited

**H. Audit Logs**

Track:

- login
- uploads
- downloads
- deletions
- admin actions

**I. Rate Limiting**

- Prevent abuse.
- Use Symfony RateLimiter

**J. 2FA**

- Use TOTP.
- Recommended.

**K. Device Sessions**

- Allow users to revoke sessions.

**L. Signed URLs**

- Optional HMAC signing.

**M. Direct S3 Streaming**

- Avoid PHP bottlenecks.

**N. Transfer Analytics**

Track:

- downloads
- opens
- geolocation
- browsers

**O. Webhooks**

Events:

- upload_complete
- file_downloaded
- transfer_expired

### 23. Database Design

**User Entity**

- id
- email
- password
- roles
- name
- quota_bytes
- is_active
- created_at
- updated_at

**Transfer Entity**

- id
- user_id
- token_hash
- original_filename
- stored_filename
- mime_type
- size_bytes
- download_count
- max_downloads
- expires_at
- password_hash
- message
- recipient_email
- created_at

**DownloadLog Entity**

- id
- transfer_id
- ip_address
- user_agent
- downloaded_at

### 24. Security Requirements

**Mandatory**

- CSRF protection
- strict validation
- MIME validation
- filename sanitization
- upload size limits
- secure headers
- CSP headers
- rate limiting
- brute force protection

**NEVER TRUST:**

- filename
- extension
- mime type

### 25. Recommended File Storage Structure

```
/storage
  /2026
    /05
      /19
        /uuid
```

Never expose storage directly.

Always serve via controller or signed URL.

### 26. Docker Compose Blueprint

Services:

```yaml
services:
  app:
  nginx:
  postgres:
  redis:
  worker:
  scheduler:
  mailpit:
```

Optional:

```yaml
  clamav:
  minio:
```

### 27. Nginx Recommendations

**Upload Limits**

```nginx
client_max_body_size 20G;
```

**Timeouts**

Increase:

- proxy_read_timeout
- proxy_connect_timeout

### 28. Background Processing

Use Messenger for:

- email sending
- virus scanning
- cleanup
- thumbnail generation
- analytics

Never do heavy processing during request cycle.

### 29. Recommended Symfony Bundles

- VichUploaderBundle
- FlysystemBundle
- EasyAdminBundle
- Scheb2FABundle
- Symfonycasts ResetPasswordBundle

### 30. Admin Panel

Use: **EasyAdmin**

Admin pages:

- users
- transfers
- logs
- storage
- settings

### 31. API Possibilities

Future API:

```
POST   /api/transfers
GET    /api/transfers
DELETE /api/transfers/{id}
```

Use token auth.

### 32. Performance Optimizations

Use:

- Redis caching
- OPcache
- HTTP caching
- Brotli compression
- CDN support

### 33. Recommended Future Features

- **Teams** — Shared organization uploads.
- **Branding** — Custom logos/colors.
- **White-label support** — Per-domain themes.
- **Desktop App** — Electron/Tauri uploader.
- **Mobile App** — Flutter app.

### 34. Suggested UX Flow

**Upload Flow**

Login → Upload → Configure rules → Send → Copy link

**Download Flow**

Open URL → Validate → Show file info → Download → Increment counter → Auto-delete if exhausted

### 35. Error Pages

Must be beautifully designed.

Custom:

- 404
- expired link
- download limit reached
- virus detected
- maintenance mode

### 36. Logging

Use Monolog.

Separate channels:

- security
- uploads
- downloads
- cleanup
- mail

### 37. Suggested Environment Variables

```env
APP_ENV=prod
APP_SECRET=
DATABASE_URL=
REDIS_URL=

STORAGE_DRIVER=local
S3_BUCKET=
S3_REGION=
S3_KEY=
S3_SECRET=

MAILER_DSN=

MAX_UPLOAD_SIZE=20G
DEFAULT_EXPIRY_DAYS=7
DEFAULT_MAX_DOWNLOADS=1
```

### 38. Styling Details (VERY IMPORTANT)

**Entire Site Feel**

Imagine:

- futuristic SaaS
- luxury dashboard
- cinematic gradients
- glass panels
- subtle motion

Use:

**Backgrounds** — Layered gradients:

```css
background:
  radial-gradient(...),
  linear-gradient(...);
```

**Cards**

```css
backdrop-filter: blur(16px);
border: 1px solid rgba(255,255,255,0.08);
```

**Buttons** — Rounded:

```css
border-radius: 16px;
```

Glow hover effect.

### 39. Animation Guidelines

Use **GSAP**

Animations:

- fade in
- slide up
- staggered lists
- upload pulse
- success burst

**Avoid**

- excessive bouncing
- cartoonish motion

### 40. Accessibility

Support:

- keyboard navigation
- high contrast
- screen readers
- focus states

### 41. Production Deployment Recommendations

Use:

- Traefik OR Nginx Proxy Manager
- HTTPS via Let's Encrypt
- nightly DB backups
- S3 backups

### 42. Monitoring

Recommended:

- Sentry
- Prometheus
- Grafana

### 43. Suggested Internal Services

**TransferService**

Handles:

- transfer creation
- validation
- cleanup

**StorageService**

Handles:

- uploads
- deletes
- streams

**DownloadService**

Handles:

- validations
- counters
- token checks

### 44. Very Important Security Recommendation

Never expose real filenames in URLs.

Never expose storage paths.

Always:

- stream downloads
- validate token server-side

### 45. Final Recommended Premium Features

- **Optional Burn-After-Read** — Delete immediately after first successful download.
- **Email Open Tracking** — Track when recipient opened email.
- **Temporary Share Page Themes** — Per-transfer background.
- **Download Notifications** — Uploader receives notification when downloaded.
- **Expiring QR Codes** — For mobile transfers.

### 46. Final Recommended Build Order

**Phase 1**

- auth
- upload
- download
- expiration
- cleanup

**Phase 2**

- email delivery
- admin panel
- quotas
- analytics

**Phase 3**

- S3
- virus scanning
- previews
- API

**Phase 4**

- teams
- branding
- apps

### 47. Recommended Name Ideas

- FluxSend
- NoirTransfer
- ShadowDrop
- TransferVault
- DriftSend
- NebulaSend
- QuantumDrop
- VelvetTransfer

### 48. Final Architecture Recommendation

This should be treated as:

a premium enterprise-grade transfer platform, not a simple upload script.

Build for:

- scalability
- security
- maintainability
- asynchronous processing
- cloud compatibility

The biggest architectural wins will come from:

- chunked uploads
- background processing
- abstract storage layer
- aggressive cleanup automation
- polished UX
- premium visual design
- strong security model