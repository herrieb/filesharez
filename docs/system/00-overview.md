# System Overview

## High-level architecture

```
                    ┌──────────────────────────────────────────────────┐
                    │                  nginx (port 4113)                │
                    │   proxy_pass → app:9000 (php-fpm)                │
                    │   client_max_body_size 20G                        │
                    └──────────────────────┬───────────────────────────┘
                                           │
              ┌────────────────────────────┼────────────────────────────┐
              │                            │                            │
              ▼                            ▼                            ▼
        ┌───────────┐               ┌────────────┐               ┌──────────────┐
        │   app    │               │   worker  │               │  scheduler   │
        │ php-fpm  │               │ messenger:│               │  messenger:  │
        │          │               │ consume    │               │  consume     │
        │  /web    │               │  async     │               │ scheduler_   │
        │ requests  │               │            │               │ default      │
        └────┬─────┘               └─────┬──────┘               └──────┬───────┘
             │                           │                           │
             │   all share a read/write  │                           │
             │   mount of ./var/         │                           │
             ▼                           ▼                           ▼
        ┌────────────────────────────────────────────────────────────────────┐
        │                      PostgreSQL 16  ·  Redis 7                     │
        │   filesharez DB, redis:6379 cache, redis stream 'messages' for     │
        │   messenger async, redis stream 'schedule' for scheduler.            │
        └────────────────────────────────────────────────────────────────────┘

        ┌─────────────┐
        │  mailpit    │   smtp://mailpit:1025, web UI on :8025
        └─────────────┘
```

## Tech stack

| Layer | Choice | Version |
|---|---|---|
| Runtime | PHP | 8.3 (alpine 3.x base image) |
| Framework | Symfony | 7.x |
| ORM | Doctrine ORM | 3.x |
| Database | PostgreSQL | 16 (alpine) |
| Cache / queue | Redis | 7 (alpine) |
| Resumable uploads | `ankitpokhrel/tus-php` | ^2.4 |
| ZIP streaming | `maennchen/zipstream-php` | ^3.0 |
| Mailer | Symfony Mailer | ^7.0 |
| Frontend | Twig + Tailwind (CDN) + Alpine.js (CDN) | n/a |
| Auth | Symfony Security bundle + custom `LoginFormAuthenticator` | n/a |
| File scanner | none (uses `DirectoryIterator` and `RecursiveDirectoryIterator`) | n/a |

There is no JavaScript bundler, no `package.json`, no `node_modules`. All
client-side logic is plain `<script>` blocks in Twig templates (and a single
self-hosted asset: `public/vendor/tus/tus.min.js`, vendored from npm
`tus-js-client@4.3.1`).

## What's in `composer.json`

### `require` (production)

| Package | Version |
|---|---|
| `ankitpokhrel/tus-php` | `^2.4` |
| `doctrine/doctrine-bundle` | `^2.12` |
| `doctrine/doctrine-migrations-bundle` | `^3.3` |
| `doctrine/orm` | `^3.0` |
| `easycorp/easyadmin-bundle` | `^4.0` |
| `maennchen/zipstream-php` | `^3.0` |
| `symfony/asset` | `^7.0` |
| `symfony/console` | `^7.0` |
| `symfony/doctrine-bridge` | `^7.0` |
| `symfony/dotenv` | `^7.0` |
| `symfony/expression-language` | `^7.0` |
| `symfony/flex` | `^2` |
| `symfony/form` | `^7.0` |
| `symfony/framework-bundle` | `^7.0` |
| `symfony/http-client` | `^7.0` |
| `symfony/lock` | `^7.0` |
| `symfony/mailer` | `^7.0` |
| `symfony/messenger` | `^7.0` |
| `symfony/monolog-bundle` | `^3.0` |
| `symfony/process` | `^7.0` |
| `symfony/property-access` | `^7.0` |
| `symfony/rate-limiter` | `^7.0` |
| `symfony/redis-messenger` | `^7.0` |
| `symfony/runtime` | `^7.0` |
| `symfony/scheduler` | `^7.0` |
| `symfony/security-bundle` | `^7.0` |
| `symfony/serializer` | `^7.0` |
| `symfony/twig-bundle` | `^7.0` |
| `symfony/ux-live-component` | `^2.0` |
| `symfony/ux-turbo` | `^2.0` |
| `symfony/validator` | `^7.0` |
| `symfony/yaml` | `^7.0` |
| `twig/extra-bundle` | `^3.0` |
| `twig/intl-extra` | `^3.0` |

### `require-dev`

| Package | Version |
|---|---|
| `symfony/maker-bundle` | `^1.0` |
| `symfony/debug-bundle` | `^7.0` |
| `symfony/web-profiler-bundle` | `^7.0` |
| `symfony/css-selector` | `^7.0` |
| `symfony/phpunit-bridge` | `^7.0` |

The `phpunit/phpunit` package itself is **not** installed. There are no automated
tests. All "testing" is done by curl-based smoke scripts in `/tmp/`. See
[maintenance/smoke-testing.md](../maintenance/smoke-testing.md) for the
recipes.

## Ports and access

| Service | Port | Notes |
|---|---|---|
| nginx (public) | `4113:80` | The whole app is served from here. `localhost:4113` in dev. |
| postgres | `5432:5432` | Internal, but exposed for local dev. `filesharez:filesharez` credentials. |
| redis | `6379:6379` | Same. |
| mailpit SMTP | `1025` | Outbound mail goes here. No auth. |
| mailpit web UI | `8025` | `http://localhost:8025` shows every email the system sent. |
