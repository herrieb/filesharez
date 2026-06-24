# Nginx, PHP-FPM, and entrypoint

## `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;

    client_max_body_size 20G;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass app:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;

        fastcgi_read_timeout 1800;
        fastcgi_send_timeout 1800;
        proxy_read_timeout 1800;
        proxy_connect_timeout 1800;
        proxy_send_timeout 1800;

        fastcgi_buffering off;
    }

    location ~ \.php$ {
        return 404;
    }

    location /storage {
        deny all;
        return 404;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2|woff|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    error_log /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
}
```

Key notes:

- `client_max_body_size 20G` must match `upload_max_filesize` in php.ini
- `fastcgi_buffering off` is critical for streaming downloads — without it, nginx buffers the entire response before sending
- `location /storage { deny all }` is a hard guarantee that no part of `/app/storage` is ever reachable from the public web
- `expires 1y` on static assets — there's a `try_files =404` so nginx only serves them when the file actually exists

## `docker/php/custom.ini`

```ini
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.save_comments=1
opcache.jit=1255
opcache.jit_buffer_size=64M

realpath_cache_size=4096K
realpath_cache_ttl=600

upload_max_filesize=20G
post_max_size=20G
max_execution_time=1800
max_input_time=600
memory_limit=2048M
upload_tmp_dir=/app/storage/tmp
```

OPcache JIT is on (`opcache.jit=1255`) with a 64 MB JIT buffer. `validate_timestamps=1` keeps dev cycle fast (no `cache:clear` needed). In production set `validate_timestamps=0` and let OPcache invalidate via the full cache rebuild.

`upload_tmp_dir` points at the storage tmp mount, so even PHP's own temp file handling lands on the persistent volume. Without this, large uploads could fill `/tmp` (the alpine tmpfs) and crash.

## `docker/php/www.conf`

```ini
[www]
listen = 9000
user = appuser
group = appuser
pm = dynamic
pm.max_children = 20
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

PHP-FPM runs as `appuser` (uid 1000) — never root. `pm = dynamic` with `pm.max_children = 20`. For a system that serves 20 concurrent downloads + a few uploads, this is fine. For production with 100+ concurrent users, raise `pm.max_children` to 50 and bump `memory_limit` in `custom.ini` accordingly.

## `docker/php/entrypoint.sh`

```sh
#!/bin/sh
set -e

chown -R appuser:appuser /var/www/html/var 2>/dev/null || true
chown -R appuser:appuser /app/storage 2>/dev/null || true
chmod -R 775 /var/www/html/var /app/storage 2>/dev/null || true
mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/var/sessions
chown -R appuser:appuser /var/www/html/var

if [ -d "$LIBRARY_PATH" ]; then
    chmod -R a+rX "$LIBRARY_PATH" 2>/dev/null || true
fi

exec "$@"
```

Runs on every container start. Fixes ownership of the cache and storage volumes (which might be bind-mounted from a different uid on the host), and ensures the library mount is world-readable so appuser can scan it.

The `chmod -R a+rX` is what enables a recently-added feature: **uploads and deletes from inside the library**. If the host directory was mode `755` owned by `twan:appuser`, appuser would be able to *read* it but not *write* to it. The entrypoint widens the permissions so the PHP-FPM worker can write.

## Production notes

- Replace `client_max_body_size 20G` with your actual `MAX_UPLOAD_SIZE`
- Replace `fastcgi_read_timeout 1800` with a sane value (1800s = 30 min; the longest single upload at 20 Gbps takes ~14s, but on a 1 Gbps link it's 160s)
- Mount `/var/www/html/var` to a named volume in production
- Replace the `app` container's `user: appuser` with a real user (uid 1000 in the Dockerfile is fine, but the entrypoint chown will be slow on a large `var/` dir)
- Replace `mailpit` with a real SMTP relay via `MAILER_DSN`
- Add a proper `redis.conf` mounted as a volume (alpine's default is fine for dev, but set `maxmemory-policy allkeys-lru` in production)
