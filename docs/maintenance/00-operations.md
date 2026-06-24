# Maintenance

Operational recipes for a running FileShareZ instance.

## Health checks

```bash
# Are all containers up?
docker ps --format 'table {{.Names}}\t{{.Status}}'

# Quick HTTP check
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:4113/

# Database reachable from app?
docker exec filesharez_app php bin/console doctrine:query:dql 'SELECT 1' --quiet
```

## Logs

```bash
# Symfony app log (stdout of the app container)
docker logs --tail 200 filesharez_app

# Worker (messenger)
docker logs --tail 200 filesharez_worker

# Scheduler
docker logs --tail 200 filesharez_scheduler

# nginx
docker logs --tail 200 filesharez_nginx
```

Symfony logs are at `var/log/dev.log` (or `var/log/prod.log` in production) inside the container. They're in Monolog's default line format.

## Database

### Backup

```bash
# Plain SQL dump
docker exec filesharez_postgres pg_dump -U filesharez filesharez > backup-$(date +%Y%m%d-%H%M%S).sql

# Compressed
docker exec filesharez_postgres pg_dump -U filesharez filesharez | gzip > backup-$(date +%Y%m%d-%H%M%S).sql.gz
```

### Restore

```bash
# Drop and recreate
docker exec filesharez_postgres dropdb -U filesharez filesharez
docker exec filesharez_postgres createdb -U filesharez filesharez

# Restore
cat backup-20260624-1200.sql | docker exec -i filesharez_postgres psql -U filesharez filesharez
```

### Inspect

```bash
# Sizes of all tables
docker exec filesharez_postgres psql -U filesharez -d filesharez -c "
SELECT relname, pg_size_pretty(pg_total_relation_size(oid)) AS size
FROM pg_class WHERE relkind = 'r' AND relnamespace = 'public'::regnamespace
ORDER BY pg_total_relation_size(oid) DESC;
"

# Active transfers
docker exec filesharez_postgres psql -U filesharez -d filesharez -c "
SELECT id, original_filename, download_count, max_downloads, expires_at, is_revoked
FROM transfers
WHERE expires_at > NOW() AND is_revoked = false AND download_count < max_downloads
ORDER BY created_at DESC LIMIT 20;
"

# Users with most storage
docker exec filesharez_postgres psql -U filesharez -d filesharez -c "
SELECT u.email, u.quota_bytes, u.reserved_bytes,
       COALESCE(SUM(t.total_size_bytes), 0) AS used_storage
FROM users u
LEFT JOIN transfers t ON t.user_id = u.id
GROUP BY u.id
ORDER BY used_storage DESC LIMIT 20;
"
```

## Disk space

```bash
# Container-internal view
docker exec filesharez_app df -h

# Host view of the storage volume
df -h /mnt/data/filesharez
df -h /mnt/library
```

The system health page at `/admin/system-health` shows this in a per-location breakdown.

## Reset a user's password

There's no admin UI for this. From the database:

```bash
HASH=$(docker exec filesharez_app php -r 'echo password_hash("new-password", PASSWORD_BCRYPT);')
docker exec filesharez_postgres psql -U filesharez -d filesharez -c \
  "UPDATE users SET password = '$HASH' WHERE email = 'user@example.com';"
```

The user can log in with the new password immediately. Their existing sessions are invalidated because the password hash changed.

## Re-scan a library source

Either click "Rescan" in the UI, or:

```bash
# Via the API (requires authentication)
COOKIE=/tmp/c.txt
curl -s -c $COOKIE -b $COOKIE -X POST \
  "http://localhost:4113/library/sources/<source-id>/rescan" \
  -d "depth=5" -H "Content-Type: application/x-www-form-urlencoded"
```

Or directly in the database — there's no shortcut, the scan has to run.

## Failed messages

```bash
# Show all failed messages
docker exec filesharez_app php bin/console messenger:failed:show

# Retry all
docker exec filesharez_app php bin/console messenger:failed:retry --force

# Remove (without retrying)
docker exec filesharez_app php bin/console messenger:failed:remove
```

## Manually trigger a cleanup

```bash
# Expired transfers
docker exec filesharez_app php bin/console messenger:dispatch-message 'App\Message\CleanupExpiredTransfers'

# Expired uploads
docker exec filesharez_app php bin/console messenger:dispatch-message 'App\Message\CleanupExpiredUploads'
```

## Adding a user manually

```bash
HASH=$(docker exec filesharez_app php -r 'echo password_hash("user-password", PASSWORD_BCRYPT);')
docker exec filesharez_postgres psql -U filesharez -d filesharez -c \
  "INSERT INTO users (id, email, password, roles, name, quota_bytes, reserved_bytes, is_active, theme, created_at, updated_at) \
   VALUES (encode(gen_random_bytes(16), 'hex'), 'newuser@example.com', '$HASH', '[\"ROLE_USER\"]', 'New User', 10737418240, 0, true, 'longhorn', NOW(), NOW());"
```

## Theme debugging

```bash
# List all themes (built-in + custom)
docker exec filesharez_app ls -la var/themes/

# Force-reload the registry after a manual edit
docker exec filesharez_app rm -rf var/cache/dev
docker exec filesharez_app php bin/console cache:warmup --env=dev
```

## Library source debugging

```bash
# List all sources
docker exec filesharez_postgres psql -U filesharez -d filesharez -c \
  "SELECT id, name, path, is_active, item_count, total_size_bytes FROM library_sources;"

# Force-add an allowed root (if a source was registered before the container was restarted)
docker exec filesharez_app php -r "
require 'vendor/autoload.php';
\$kernel = new App\Kernel('dev', true);
\$kernel->boot();
\$storage = \$kernel->getContainer()->get(App\Storage\LibraryStorage::class);
\$storage->addAllowedRoot('/mnt/library');
echo 'OK';
"
```

## Upgrading

```bash
# Pull new code
git pull

# Rebuild containers (PHP / Composer changes)
docker compose build app worker scheduler
docker compose up -d app worker scheduler

# Apply any new migrations
docker exec filesharez_app php bin/console doctrine:migrations:migrate --no-interaction

# Clear cache
docker exec filesharez_app php bin/console cache:clear --env=prod

# Restart the worker (to pick up new code)
docker compose restart worker scheduler
```

## Performance tips

- For 100+ concurrent users, raise `pm.max_children` in `docker/php/www.conf` and `memory_limit` in `docker/php/custom.ini`
- For large libraries, increase `LIBRARY_SCAN_DEPTH` cautiously — every rescan walks the tree
- The PHP-FPM pool runs as `appuser` (uid 1000). Make sure your storage mount is owned by the same uid; otherwise the entrypoint's `chown` will be slow

## When things go wrong

| Symptom | First thing to check |
|---|---|
| Page returns 500 | `docker logs filesharez_app --tail 200` |
| File won't upload | `disk_free_space` on the storage mount; `php -r "echo ini_get('upload_max_filesize');"` |
| Emails not sent | `MAILER_DSN` reachable from `app` and `worker`; queue depth in Redis; `messenger:failed:show` |
| Library browse is slow | Library size; `LIBRARY_SCAN_DEPTH`; the database indexes on `library_items (source_id, path)` |
| nginx 502 | `docker logs filesharez_nginx --tail 50`; the `app` container may have crashed |
| Quota wrong | `users.reserved_bytes` may be stuck (e.g. an upload crashed before completion). Reset: `UPDATE users SET reserved_bytes = 0 WHERE id = '<id>';` |
