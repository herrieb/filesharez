# Environment Variables

## `.env` (the actual file in this install)

```ini
APP_ENV=dev
APP_SECRET=changeMeThisIsNotProductionSafe
APP_SHARE_DIR=var/share

DATABASE_URL=postgresql://filesharez:filesharez@postgres:5432/filesharez?serverVersion=16&charset=utf8
REDIS_URL=redis://redis:6379
MAILER_DSN=smtp://mailpit:1025
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages

STORAGE_DRIVER=local
S3_BUCKET=
S3_REGION=
S3_KEY=
S3_SECRET=

MAX_UPLOAD_SIZE=20G
DEFAULT_EXPIRY_DAYS=7
DEFAULT_MAX_DOWNLOADS=1
LIBRARY_SCAN_DEPTH=5
LIBRARY_EXTERNAL_PATH=/mnt/external-library
THEME_DEFAULT=longhorn

DEFAULT_URI=http://localhost:4113

###> symfony/lock ###
LOCK_DSN=flock
###< symfony/lock ###
```

## `.env.example` (the template committed for new installs)

Same as above, plus `LIBRARY_PATH=/mnt/library` is listed but commented. New installs uncomment + set it to wherever the host library mount is.

## Every variable, with default and purpose

| Variable | Default | Purpose |
|---|---|---|
| `APP_ENV` | `dev` | Symfony environment. `prod` for production. |
| `APP_SECRET` | `changeMeThisIsNotProductionSafe` | Symfony secret — used for CSRF tokens, signed URLs, etc. Generate a real one with `php -r "echo bin2hex(random_bytes(32));"` |
| `APP_SHARE_DIR` | `var/share` | Legacy path inside the project for shared files. Currently unused — the LocalStorage now lives in `/app/storage/` |
| `DATABASE_URL` | `postgresql://filesharez:filesharez@postgres:5432/filesharez?serverVersion=16&charset=utf8` | Doctrine DSN |
| `REDIS_URL` | `redis://redis:6379` | Used by the cache adapter, Messenger async transport, and Scheduler transport |
| `MAILER_DSN` | `smtp://mailpit:1025` | Symfony mailer DSN. In dev, every email is caught by mailpit. In prod, point at your real SMTP relay (e.g. `smtp://user:pass@smtp.example.com:587`) |
| `MESSENGER_TRANSPORT_DSN` | `redis://redis:6379/messages` | Stream name for the async transport. Symfony takes the base `REDIS_URL` and appends `/messages` |
| `STORAGE_DRIVER` | `local` | Storage backend selector. Only `local` is implemented; `s3` is a stub with the env vars below (not actually wired up) |
| `S3_BUCKET` | *(empty)* | S3 bucket name (future use) |
| `S3_REGION` | *(empty)* | S3 region |
| `S3_KEY` | *(empty)* | S3 access key |
| `S3_SECRET` | *(empty)* | S3 secret key |
| `MAX_UPLOAD_SIZE` | `20G` | Per-file / per-bulk upload ceiling. Accepts `K`, `M`, `G` suffix. Parsed by `LibraryService::maxUploadSizeBytes()` |
| `DEFAULT_EXPIRY_DAYS` | `7` | Default `expires_at` for new transfers |
| `DEFAULT_MAX_DOWNLOADS` | `1` | Default `max_downloads` for new transfers (1 = burn-after-read) |
| `LIBRARY_SCAN_DEPTH` | `5` | How deep `LibraryService::rescanSource` walks the directory tree |
| `LIBRARY_PATH` | `/mnt/library` | Primary library source path (inside the container) |
| `LIBRARY_EXTERNAL_PATH` | `/mnt/external-library` | Secondary library source path |
| `THEME_DEFAULT` | `longhorn` | Default theme for new users / fallback when a saved theme id is no longer registered |
| `DEFAULT_URI` | `http://localhost:4113` | Used by some absolute URL generation defaults. `DEFAULT_URI` is not referenced in app code; kept for future use |
| `LOCK_DSN` | `flock` | Symfony lock store — file locking. Default is fine for dev; in production use `redis://redis:6379` for cross-host locks |

## Production-required values

| Variable | Production value |
|---|---|
| `APP_SECRET` | A real 64-char hex string |
| `MAILER_DSN` | Your real SMTP relay |
| `DATABASE_URL` | Set `?serverVersion=16&charset=utf8`; use a real strong password |
| `REDIS_URL` | Use a real password in production |
| `LIBRARY_PATH` / `LIBRARY_EXTERNAL_PATH` | Mount your real file storage (NFS, EBS, etc.) |
| `THEME_DEFAULT` | `longhorn` (or any other) |
