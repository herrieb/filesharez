#!/bin/sh
# Entrypoint: ensure cache/storage dirs are writable by appuser, then exec the CMD.

set -e

# Make sure appuser owns the project tree (for bind mounts that come from the host
# as root or another uid) and the storage dir. The php-fpm pool runs as appuser.
chown -R appuser:appuser /var/www/html/var 2>/dev/null || true
chown -R appuser:appuser /app/storage 2>/dev/null || true
chmod -R 775 /var/www/html/var /app/storage 2>/dev/null || true
mkdir -p /var/www/html/var/cache /var/www/html/var/log /var/www/html/var/sessions
chown -R appuser:appuser /var/www/html/var

# Ensure library root is readable by appuser
if [ -d "$LIBRARY_PATH" ]; then
    chmod -R a+rX "$LIBRARY_PATH" 2>/dev/null || true
fi

exec "$@"
