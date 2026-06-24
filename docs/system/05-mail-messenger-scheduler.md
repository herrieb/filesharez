# Mail, Messenger, Scheduler

## Mail

`config/packages/mailer.yaml`:

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
```

`MAILER_DSN` defaults to `smtp://mailpit:1025` in dev. Every email goes to mailpit; open `http://localhost:8025` to see them.

In production, point `MAILER_DSN` at a real SMTP relay. Examples:

- `smtp://user:pass@smtp.sendgrid.net:587`
- `smtp://user:pass@email-smtp.eu-west-1.amazonaws.com:587` (AWS SES)
- `native://default` (Symfony's `symfony/mailer` with no DSN — uses the local MTA; only useful in containers where sendmail/postfix is configured)

There is no DKIM/SPF/DMARC setup in the app — that's the responsibility of the upstream MTA. The email templates render the same in dev and prod.

## Messenger

`config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        failure_transport: failed

        transports:
            async:
                dsn: '%env(REDIS_URL)%'
                options:
                    stream: messages
                    group: filesharez
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 60000

            failed:
                dsn: '%env(REDIS_URL)%'
                options:
                    stream: failed

            scheduler_default:
                dsn: '%env(REDIS_URL)%/schedule'

        routing:
            App\Message\SendTransferEmail: async
            App\Message\SendFileRequestUploadEmail: async
            App\Message\SendDownloadNotificationEmail: async
            App\Message\CleanupExpiredTransfers: async
```

### Transports

| Transport | Type | Used by |
|---|---|---|
| `async` | Redis stream `messages`, group `filesharez` | All `Send*Email` and the `CleanupExpiredTransfers` tick |
| `failed` | Redis stream `failed` | Where messages land after `max_retries: 3` retries are exhausted |
| `scheduler_default` | Redis stream `schedule` | The scheduler consumes from this; the cron tick dispatches here |

### Retry strategy

- 3 retries on the `async` transport
- Exponential backoff: 1s, 2s, 4s, 8s — capped at 60s
- After 3 retries the message goes to `failed` and stays there for manual inspection

### Failed-message triage

`php bin/console messenger:failed:show` and `:retry` are how you look at and replay failed messages. In production you'd set up a cron that does `:retry --force` once a day so transient blips auto-recover.

## Scheduler

`config/packages/scheduler.yaml`:

```yaml
framework:
    scheduler:
        enabled: true
```

`src/Schedule.php`:

```php
class Schedule implements ScheduleProviderInterface
{
    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::every('1 minute', new CleanupExpiredTransfers())
            )
            ->add(
                RecurringMessage::every('15 minutes', new CleanupExpiredUploads())
            );
    }
}
```

Two recurring tasks:

| Task | Frequency | What it does |
|---|---|---|
| `App\Message\CleanupExpiredTransfers` | every 1 min | Finds transfers where `expires_at < now` OR `download_count >= max_downloads` OR `is_revoked = true`. For each: deletes storage files (skips library-backed), removes the `Transfer` row, removes `LibraryItem` rows that no longer have any active transfer. |
| `App\Message\CleanupExpiredUploads` | every 15 min | Calls `UploadCleanupService::cleanupExpired()` which deletes `UploadSession` rows older than 24h, releases reserved quota, removes temp files. |

### Why two frequencies?

Transfers are user-facing — a download link that says "1 minute past expiry" is bad UX. Uploads are an internal resource — a 15-minute window of dead temp files doesn't hurt anyone.

### The `stateful` and `processOnlyLastMissedRun` flags

- `stateful` uses the cache adapter to remember when each task last ran, so a 30-minute container restart doesn't run 30 missed tasks at once
- `processOnlyLastMissedRun` means: if the scheduler has been off for 90 minutes, run the 1-minute task **once**, not 90 times. Same for the 15-minute task.

### How the scheduler runs

`docker-compose.yml` has a `scheduler` service that runs:

```sh
php bin/console messenger:consume scheduler_default --time-limit=3600 --memory-limit=512M
```

This consumes the `scheduler_default` transport. Symfony Scheduler dispatches `CleanupExpiredTransfers` and `CleanupExpiredUploads` onto this transport at the right times. The worker (also in docker-compose) then picks them up via the `async` transport and runs the handler.

If you want to run the scheduler by hand:

```bash
docker exec filesharez_scheduler php bin/console messenger:consume scheduler_default --time-limit=3600 --memory-limit=512M
```

## Cron jobs that don't exist

- No log rotation — `var/log/*.log` grows forever in the container. The entrypoint doesn't truncate
- No DB backup — Postgres data is on a named volume; back it up with `docker exec filesharez_postgres pg_dump -U filesharez filesharez > backup.sql`
- No SSL renewal — this is a reverse-proxy concern, not an app concern
