# Docker Setup

## `docker-compose.yml` — every service

### `app` (the PHP-FPM worker that handles HTTP requests)

```yaml
app:
  build:
    context: .
    dockerfile: Dockerfile
  container_name: filesharez_app
  restart: unless-stopped
  volumes:
    - .:/var/www/html                                          # the project source
    - /mnt/data/filesharez/transfers:/app/storage/transfers    # persistent transfers
    - /mnt/data/filesharez/previews:/app/storage/previews
    - /mnt/data/filesharez/tmp:/app/storage/tmp
    - /mnt/data/filesharez/quarantine:/app/storage/quarantine
    - /home/twan/trnts/complete:/mnt/library                  # primary library source
    - /mnt/data/filesharez/external-library:/mnt/external-library   # secondary library source
  environment:
    APP_ENV: ${APP_ENV:-dev}
    APP_SECRET: ${APP_SECRET:-changeMeThisIsNotProductionSafe}
    DATABASE_URL: postgresql://filesharez:filesharez@postgres:5432/filesharez?serverVersion=16&charset=utf8
    REDIS_URL: redis://redis:6379
    MAILER_DSN: smtp://mailpit:1025
    STORAGE_DRIVER: ${STORAGE_DRIVER:-local}
    MAX_UPLOAD_SIZE: ${MAX_UPLOAD_SIZE:-20G}
    DEFAULT_EXPIRY_DAYS: ${DEFAULT_EXPIRY_DAYS:-7}
    DEFAULT_MAX_DOWNLOADS: ${DEFAULT_MAX_DOWNLOADS:-1}
    LIBRARY_PATH: ${LIBRARY_PATH:-/mnt/library}
    LIBRARY_EXTERNAL_PATH: ${LIBRARY_EXTERNAL_PATH:-/mnt/external-library}
```

The `app` container is the PHP-FPM worker. It serves HTTP via the nginx container in front of it.

### `nginx` (the public-facing reverse proxy)

```yaml
nginx:
  image: nginx:1.25-alpine
  container_name: filesharez_nginx
  restart: unless-stopped
  ports:
    - "4113:80"
  volumes:
    - .:/var/www/html
    - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
```

The default nginx config is in [`docker/nginx/default.conf`](../../docker/nginx/default.conf). Key settings:
- `client_max_body_size 20G` — match the upload ceiling
- `proxy_read_timeout 1800` and `fastcgi_read_timeout 1800` — long enough for a slow disk write
- `fastcgi_buffering off` — important for streaming, so the response isn't fully buffered
- `location /storage { deny all; return 404; }` — never serve storage directly
- `expires 1y` on static assets

### `postgres` (database)

```yaml
postgres:
  image: postgres:16-alpine
  container_name: filesharez_postgres
  restart: unless-stopped
  environment:
    POSTGRES_DB: filesharez
    POSTGRES_USER: filesharez
    POSTGRES_PASSWORD: filesharez
  volumes:
    - postgres_data:/var/lib/postgresql/data
  ports:
    - "5432:5432"
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U filesharez"]
    interval: 5s
    timeout: 5s
    retries: 5
```

Named volume `postgres_data` for persistence. Healthcheck waits for `pg_isready` before `app`/`worker`/`scheduler` start.

### `redis` (cache + messenger transport + scheduler transport)

```yaml
redis:
  image: redis:7-alpine
  container_name: filesharez_redis
  restart: unless-stopped
  ports:
    - "6379:6379"
  volumes:
    - redis_data:/data
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 5s
    timeout: 5s
    retries: 5
```

Used for:
- Symfony cache pool (`cache.app` etc.)
- `messages` Redis stream — async messenger transport
- `schedule` Redis stream — scheduler transport
- `failed` Redis stream — failed messenger transport

### `worker` (background message consumer)

```yaml
worker:
  build: { context: ., dockerfile: Dockerfile }
  container_name: filesharez_worker
  restart: unless-stopped
  command: php bin/console messenger:consume async --time-limit=3600 --memory-limit=512M
  volumes: [same as app]
  environment: [subset of app env]
```

Consumes from the `async` transport. Handles:
- `SendTransferEmail` (recipient gets the link)
- `SendFileRequestUploadEmail` (owner gets notified about a file uploaded via their request)
- `SendDownloadNotificationEmail` (owner gets notified of first download)

`--time-limit=3600` makes the worker restart every hour to avoid memory leaks. `--memory-limit=512M` triggers an early restart.

### `scheduler` (cron-equivalent)

```yaml
scheduler:
  build: { context: ., dockerfile: Dockerfile }
  container_name: filesharez_scheduler
  restart: unless-stopped
  command: php bin/console messenger:consume scheduler_default --time-limit=3600 --memory-limit=512M
  volumes: [same as app]
  environment: [subset of app env]
```

Consumes from the `scheduler_default` transport. Runs:
- `CleanupExpiredTransfers` every minute
- `CleanupExpiredUploads` every 15 minutes

See [scheduler.md](scheduler.md) for the schedule.

### `mailpit` (dev SMTP)

```yaml
mailpit:
  image: axllent/mailpit:latest
  container_name: filesharez_mailpit
  restart: unless-stopped
  ports:
    - "1025:1025"   # SMTP
    - "8025:8025"   # web UI
```

In dev, every email the system sends is caught by mailpit. Open `http://localhost:8025` to see them. In production, replace `MAILER_DSN` with a real SMTP relay.

## `Dockerfile`

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev \
    linux-headers git curl unzip postgresql-dev bash

RUN docker-php-ext-install intl gd zip mbstring opcache pdo_pgsql pcntl
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN adduser -D -u 1000 -G appuser appuser
RUN mkdir -p /app/storage/{transfers,previews,tmp,quarantine} /var/{cache,log,sessions} \
    && chown -R appuser:appuser /app /var
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
USER root
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
```

## Volumes — what is persisted

| Host path | Container path | What |
|---|---|---|
| `postgres_data` (named) | `/var/lib/postgresql/data` | Postgres data files |
| `redis_data` (named) | `/data` | redis RDB + AOF |
| `/mnt/data/filesharez/transfers` | `/app/storage/transfers` | Uploaded files (the actual bytes) |
| `/mnt/data/filesharez/previews` | `/app/storage/previews` | Image / video thumbnails |
| `/mnt/data/filesharez/tmp` | `/app/storage/tmp` | tus temp files (atomic renames) |
| `/mnt/data/filesharez/quarantine` | `/app/storage/quarantine` | Future virus scan quarantine (not yet wired) |
| `/home/twan/trnts/complete` | `/mnt/library` | Primary library source — files registered by users |
| `/mnt/data/filesharez/external-library` | `/mnt/external-library` | Secondary library source |

The `var/` directory inside the project (`/var/www/html/var/{cache,log,sessions}`)
holds ephemeral app data — symfony cache, logs, session files. On container
restart it gets wiped. In dev that's fine. In production you'd mount a
named volume here too.
