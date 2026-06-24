# Setup Runbook

From a clean machine to a working FileShareZ instance, in 10 minutes.

## Prerequisites

- A Linux host (or macOS / WSL2) with Docker + Docker Compose v2 installed
- At least one disk with enough free space for the storage mount (default `/mnt/data`)
- An open TCP port for nginx (default `4113`)

## 1. Clone the repo

```bash
git clone https://github.com/herrieb/filesharez.git
cd filesharez
```

## 2. Set up environment

```bash
cp .env.example .env
# Edit .env — set:
#   APP_SECRET  → a real 64-char hex string (php -r "echo bin2hex(random_bytes(32));")
#   LIBRARY_PATH → the absolute path on the host where library files live
#   LIBRARY_EXTERNAL_PATH → (optional) a second library path
```

Production note: also change `POSTGRES_PASSWORD` in `docker-compose.yml` and update `DATABASE_URL` in `.env` to match.

## 3. Create the storage directories

```bash
sudo mkdir -p /mnt/data/filesharez/{transfers,previews,tmp,quarantine}
sudo chown -R 1000:1000 /mnt/data/filesharez
```

The numeric uid (1000) matches the `appuser` uid in the Dockerfile. If you change it, change both.

For the library:

```bash
sudo mkdir -p /mnt/library
sudo chown -R 1000:1000 /mnt/library
```

For a secondary library mount:

```bash
sudo mkdir -p /mnt/external-library
sudo chown -R 1000:1000 /mnt/external-library
```

## 4. Start the stack

```bash
docker compose up -d --build
```

This starts: `app`, `nginx`, `postgres`, `redis`, `worker`, `scheduler`, `mailpit`. The `app` and `worker` containers build from the local Dockerfile (the first build takes ~3 min; subsequent builds are cached).

Wait for the `app` container to become healthy. Symfony's container build is lazy and the first request takes longer (cache warm).

## 5. Apply database migrations

```bash
docker exec filesharez_app php bin/console doctrine:migrations:migrate --no-interaction
```

You should see a list of 8 migrations being applied. The first time, this creates the entire schema.

## 6. Create the first admin user

There's no CLI command for this — you do it via the web UI:

1. Browse to `http://localhost:4113/login`
2. There's no "register" link. You need to bootstrap a user.

The recommended path is to use the CLI to create the user (with a bcrypt-hashed password) and then promote them:

```bash
# Generate a bcrypt hash for your password
HASH=$(php -r 'echo password_hash("your-password-here", PASSWORD_BCRYPT);')

# Insert the user
docker exec filesharez_postgres psql -U filesharez -d filesharez -c \
  "INSERT INTO users (id, email, password, roles, name, quota_bytes, reserved_bytes, is_active, theme, created_at, updated_at) \
   VALUES (encode(gen_random_bytes(16), 'hex'), 'admin@example.com', '$HASH', '[\"ROLE_USER\",\"ROLE_ADMIN\"]', 'Admin', 10737418240, 0, true, 'longhorn', NOW(), NOW());"
```

Now you can log in at `http://localhost:4113/login` as `admin@example.com`.

## 7. Verify

- `http://localhost:4113/` — public landing page
- `http://localhost:4113/login` — login form
- `http://localhost:4113/dashboard` — once logged in
- `http://localhost:4113/admin` — admin dashboard
- `http://localhost:8025/` — mailpit (every email the system sends)

Upload a test file, share it, copy the link, open it in an incognito window. You should see the download page. Click the button, the file streams.

## 8. Optional: add more users

From the admin dashboard, click "Create User". Set their name, email, quota, and (optionally) admin. They can log in immediately with no email verification (intentional — this is a private install, not a public service).

## 9. Optional: set up a library source

From `/library` → "Add Folder". Enter a name and the absolute path on the host (e.g. `/mnt/library`). The system rescans the folder immediately. The user can browse and share.

## 10. Optional: enable HTTPS

The app is HTTP-only by default. For production:

- Put nginx behind a reverse proxy (Traefik, Caddy, nginx-proxy-manager)
- Add `use_forwarded_for` to the firewall if your proxy sets `X-Forwarded-*` headers
- Set `MAILER_DSN` to a real SMTP relay
- Set `APP_ENV=prod` in `.env`

## Common gotchas

| Problem | Fix |
|---|---|
| `disk_free_space(/mnt/data)` returns 0 | The bind mount is wrong or the host path doesn't exist. Check `docker inspect filesharez_app` and `Mounts` |
| `permission denied` writing to `/app/storage/transfers/...` | The storage dir is owned by `root`. The entrypoint chowns to `appuser`, but only if the mount already exists. Run `sudo chown -R 1000:1000 /mnt/data/filesharez` on the host |
| Mailpit shows nothing | Check that the system actually dispatches emails (see logs) and that `MAILER_DSN` is reachable from the `app` and `worker` containers |
| `Library` button in the navbar is hidden | You're not logged in. Or your `roles` JSON in the DB is wrong |
| `Symfony\Component\HttpKernel\Exception\NotFoundHttpException` on every page | The `appuser` uid inside the container doesn't match the host user uid. Run `chown -R 1000:1000` on the host paths and `docker compose restart app` |
