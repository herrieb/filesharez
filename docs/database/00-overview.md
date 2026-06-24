# Database — Overview

## Connection

`config/packages/doctrine.yaml` (excerpt):

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        controller_resolver:
            auto_mapping: false
        mappings:
            App:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
```

The migration config (`config/packages/doctrine_migrations.yaml`):

```yaml
doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
    enable_profiler: false
    transactional: true
```

All migrations live in `migrations/` and follow the `VersionYYYYMMDDHHMMSS.php` convention. They are applied by `php bin/console doctrine:migrations:migrate --no-interaction`.

## Every table, at a glance

| Table | Entity | Purpose |
|---|---|---|
| `users` | `User` | Authenticated accounts |
| `transfers` | `Transfer` | A share — one or more files + a token + a recipient |
| `transfer_files` | `TransferFile` | A file within a transfer (multi-file support) |
| `download_logs` | `DownloadLog` | Per-download audit log (IP, UA, timestamp) |
| `file_requests` | `FileRequest` | A "send me files" public form (anonymous uploader) |
| `saved_transfer_tokens` | `SavedTransferToken` | Owner's bookmark of a previously-shared link |
| `upload_sessions` | `UploadSession` | tus protocol session state |
| `library_sources` | `LibrarySource` | A folder the user has registered for sharing |
| `library_items` | `LibraryItem` | A file or folder inside a library source (DB index) |
| `owner_access_logs` | `OwnerAccessLog` | Owner's direct preview/download of their own library |

## Migration history (in order)

| # | File | Description |
|---|---|---|
| 1 | `Version20260519202612.php` | Migrate from single-file transfers to multi-file `transfer_files` table. Backfills existing single-file transfer rows into `transfer_files`. Drops single-file columns from `transfers`. Adds `transfers.total_size_bytes`, `download_logs.transfer_file_id`, `download_logs.index_transfer_file`. |
| 2 | `Version20260519211230.php` | Add `file_requests` table and transfer sender / file_request fields. Adds `transfers.sender_name`, `transfers.sender_email`, `transfers.file_request_id` (FK→file_requests SET NULL). |
| 3 | `Version20260602120000.php` | Add `library_sources` and `library_items` tables; mark transfers as library-backed. Adds `transfers.is_from_library` and `transfers.library_item_id`. |
| 4 | `Version20260602130000.php` | Add `saved_transfer_tokens` table so owners can recover share links. |
| 5 | `Version20260623100000.php` | Library folder navigation: `library_items.parent_path`, unique `(source_id, path)`, `saved_transfer_tokens.relative_path`. |
| 6 | `Version20260623140000.php` | Resumable uploads (tus): `upload_sessions` table + `users.reserved_bytes`. |
| 7 | `Version20260623150000.php` | User-selected theme: `users.theme VARCHAR(32) NOT NULL DEFAULT 'longhorn'`. |
| 8 | `Version20260624171614.php` | Owner library access log: `owner_access_logs` for owner-direct preview/download activity. |

## Migration order matters

Apply in order. The schema is forward-only — there's no schema downgrade. If you need to roll back, restore from a Postgres backup (you do take backups, right?).

## How to dump the schema as SQL

```bash
docker exec filesharez_postgres pg_dump --schema-only -U filesharez filesharez > schema.sql
```

This produces a clean, runnable SQL file you can diff against expected schema.
