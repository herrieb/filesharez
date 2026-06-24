# 0. Multi-tenant Orgs + Per-User Encrypted Library

## Goals

1. **Multi-tenant.** There is a single "host" company (one row in the new `companies` table) and N child companies under it. A user belongs to exactly one company. A super admin manages the host; company admins manage their own company.
2. **Two-tier visibility.** A library file is either `private` (visible only to its owner) or `company` (visible to every user in the company).
3. **Two-tier quota.** Each user has a per-user quota, each company has a per-company quota. Uploads are blocked when either cap would be exceeded.
4. **Encrypted at rest.** Files are stored on disk as AES-256-GCM ciphertext. Filenames and metadata are encrypted too. A disk read of the storage volume reveals nothing without the user's DEK.
5. **No data loss in migration.** The existing `Completed` and `External Library` sources keep their data — they're migrated into the new per-user encrypted store.

## Current state (what this replaces)

- `User` is a flat list — no company, no tenant
- `LibrarySource` is per-user, points to a host path — no encryption
- Quota is per-user only — `User::quotaBytes` / `User::reservedBytes`
- Library browsing is single-tenant — `LibraryService::browsePath` doesn't filter by visibility
- Sharing via `Transfer` is public-link — `DownloadController` doesn't check tenant context

## Data model

### New tables

#### `companies`

| Column | Type | Notes |
|---|---|---|
| `id` | VARCHAR(32) PK | random hex |
| `name` | VARCHAR(255) | |
| `parent_id` | VARCHAR(32) NULL | FK to `companies.id`. `NULL` = the host company. Exactly one row has `parent_id IS NULL`. |
| `quota_bytes` | BIGINT | default 107374182400 (100 GB) |
| `is_active` | BOOLEAN | default true |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

Indexes: unique on `name`; index on `parent_id`.

#### `user_keys` (per-user DEK)

| Column | Type | Notes |
|---|---|---|
| `user_id` | VARCHAR(32) PK, FK to `users.id` CASCADE | One DEK per user |
| `dek_wrapped` | TEXT | The DEK encrypted with the KEK derived from the user's password |
| `kek_version` | INTEGER | default 1 — bumped when the password is rotated |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

The `dek_wrapped` field uses `sodium_crypto_secretbox` with a nonce stored alongside it. The KEK is derived from the password via `sodium_crypto_pwhash` (Argon2id, interactive limits). The KEK never touches disk.

#### `company_keys` (per-company DEK)

Same shape as `user_keys` but the KEK is derived from a per-company "library secret" (a 32-byte random) that the host super-admin enters when creating the company. The library secret itself is wrapped with the host super-admin's password-derived KEK and stored in a separate `company_secrets` table.

#### `company_secrets`

| Column | Type | Notes |
|---|---|---|
| `company_id` | VARCHAR(32) PK, FK to `companies.id` CASCADE | |
| `library_secret_wrapped` | TEXT | The 32-byte library secret wrapped with the host super-admin's KEK |
| `created_at` | TIMESTAMP | |
| `rotated_at` | TIMESTAMP | last time the secret was rotated |

#### `company_dek_grants`

When a user is added to a company, the company DEK is re-wrapped with the user's password-derived KEK and stored here.

| Column | Type | Notes |
|---|---|---|
| `company_id` | VARCHAR(32) FK to `companies.id` CASCADE | |
| `user_id` | VARCHAR(32) FK to `users.id` CASCADE | |
| `wrapped_company_dek` | TEXT | |
| `created_at` | TIMESTAMP | |

Unique constraint: `(company_id, user_id)`.

When a user is removed from a company: DELETE the grant row. The user's cached company DEK in memory is purged at next request.

#### `library_files` (the new file table)

Replaces `library_items`. Same general purpose, with the new visibility and tenant columns.

| Column | Type | Notes |
|---|---|---|
| `id` | VARCHAR(32) PK | |
| `owner_user_id` | VARCHAR(32) FK to `users.id` CASCADE | |
| `company_id` | VARCHAR(32) FK to `companies.id` CASCADE | denormalized for fast filtering |
| `visibility` | VARCHAR(16) | `private` or `company` |
| `parent_path` | VARCHAR(1024) NULL | directory this file is in (within the source). NULL for files at the source root. |
| `encrypted_path` | VARCHAR(1024) | the on-disk path of the .enc file (relative to `var/lib/<user_id>/content/<source_id>/`) |
| `encrypted_meta` | JSONB | encrypted JSON containing: `original_filename`, `original_mime`, `size_bytes`, `mtime`, `share_keys[]` |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

Indexes: `(owner_user_id)`, `(company_id, visibility)`, `(parent_path)`.

A new `library_sources` table — much smaller:

| Column | Type | Notes |
|---|---|---|
| `id` | VARCHAR(32) PK | |
| `user_id` | VARCHAR(32) FK to `users.id` CASCADE | The "owner" of the source — same as before |
| `company_id` | VARCHAR(32) FK to `companies.id` CASCADE | |
| `name` | VARCHAR(255) | user-chosen name, e.g. "Completed" or "External Library" |
| `created_at` | TIMESTAMP | |
| `last_scanned_at` | TIMESTAMP | |

The source no longer has a `path` column — the path on disk is computed as `var/lib/<user_id>/content/<source_id>/`. The new design has zero per-source physical path config; it's all under the user's encrypted root.

#### `users` (extend)

Add columns to the existing `users` table:

| Column | Type | Notes |
|---|---|---|
| `company_id` | VARCHAR(32) FK to `companies.id` SET NULL | default NULL during migration |
| `is_super_admin` | BOOLEAN | default false; only one user has this true (the host super admin) |
| `dek_kek_version` | INTEGER | default 1 — bump on password change to force re-wrapping the DEK |

Migrate existing users: assign them to a single default company (the "legacy" company or the host company, depending on whether you want a single back-compat company or merge everyone into the host).

### Existing tables (no schema change, but with new behavior)

- `users` — gains `company_id`, `is_super_admin`, `dek_kek_version`. Existing role logic still works; `ROLE_ADMIN` becomes a permission level that can be scoped to a company in a future iteration.
- `transfers` — no change. Shares are still token-based, still public-link. Tenant context is *advisory* (shown in the download page, not enforced on the link). Tightening share-link tenant enforcement is feature #2.

## Encryption design

### Algorithms

- **Key derivation (KEK from password):** `sodium_crypto_pwhash` with `SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE` and `SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE`. Argon2id under the hood.
- **DEK storage (wrap a 32-byte random key with the KEK):** `sodium_crypto_secretbox` with `sodium_crypto_secretbox_NONCEBYTES` random nonce prepended to the ciphertext. The KEK is derived at every request, used, then thrown away.
- **File content:** `sodium_crypto_aead_aes256gcm_encrypt(plaintext, AD, nonce, key)` with `AD = '<random_id>|<source_id>|<owner_user_id>'` so a ciphertext can't be moved between sources/users.
- **Per-chunk nonce:** 12 bytes, derived deterministically as `random_id XOR chunk_index` so a verifier can compute it from file + chunk without storing a separate nonce table.
- **Chunks:** 64 KB each. Each chunk is a separate AEAD call. This means we can decrypt a range without reading the whole file.
- **Filenames / metadata:** also AEAD-encrypted, stored as a JSONB blob in the `encrypted_meta` column. The whole `meta.json` is one AEAD ciphertext, because metadata is small and a single AEAD call is cheap.

### Storage layout

```
var/lib/library/
    <user_id>/
        metadata.json                       (encrypted user prefs, key version)
        keys/
            dek.wrapped                     (DEK wrapped with user's KEK)
        content/
            <source_id>/
                <random_id>.enc             (encrypted file payload, chunked)
                <random_id>.meta.json       (encrypted metadata blob)
        trash/                              (soft-deleted; purged by scheduler)
```

### File operations

`App\Storage\EncryptedLibraryStorage` — a new class, sibling of the current `LibraryStorage`. Same role, different internals.

- `listDirectory(user, source, $relativePath)` — DB query, decrypt names, return
- `resolveAbsolutePath(user, source, $relativePath)` — `var/lib/<user>/content/<source>/<id>.enc` for files; the directory structure is a DB-only concept (no on-disk directories for folders)
- `readStream(user, source, $relativePath, $range)` — opens the .enc, runs AEAD verify per chunk, returns a streaming resource
- `writeStream(user, source, $relativePath, $sourceStream)` — generates a random ID, encrypts in chunks, writes `<id>.enc` + `<id>.meta.json`, creates a `LibraryFile` row
- `mkdir(user, source, $relativePath)` — creates a `LibraryFile` row of kind `directory` (no on-disk content, just a name + parent_path)
- `remove(user, source, $relativePath)` — moves the .enc to `trash/`, marks the row deleted
- `browse(user, source, $relativePath)` — service-layer wrapper

### Sharing

The share flow becomes:

1. **Owner shares a private file:** generate a per-share random key. Re-encrypt the file's content (or a single chunk) with that share key. The share key is wrapped twice:
   - Once for the recipient (if registered) — stored in a `share_grants` row
   - Once for the public link — the user provides a passphrase that's in the URL or on the page
2. **Owner shares a company file:** same flow but using the *company* DEK (unwrapped via the user's `company_dek_grants` row) to re-wrap with the share key.
3. **Recipient downloads:** they have a session (so the server can unwrap the recipient copy of the share key) OR they have the passphrase (server decrypts with the public-link copy). The server decrypts with the share key, streams the plaintext.

**The server CAN decrypt shares** — it has the share key, stored in the DB. This is the "trusted server" model. A future hardening step is to make shares truly end-to-end (the share key is delivered out-of-band, never stored server-side). Out of scope for v1.

## Quota enforcement

```
function effective_user_quota(user):
    company = user.company
    company_used = sum of transfers' totalSizeBytes where user.company_id == company.id
                  (capped at "live" transfers; completed ones don't count)
    company_remaining = company.quotaBytes - company_used
    user_remaining = user.quotaBytes - user.usedStorage - user.reservedBytes
    return min(user_remaining, company_remaining)
```

At upload create-time: `if fileSize > effective_user_quota(uploader) → 400`. At finalize: re-check. If the company has been filled by a different user's upload that finished first, the upload fails. This is a rare race; the user can retry.

## Migration plan

The migration is a one-shot command:

```bash
php bin/console app:library:migrate-encrypt
```

What it does:

1. **Seed the new tables:**
   - Create the host company (one row, `parent_id = NULL`)
   - Create a "default" company for any user whose `users.company_id` is NULL
   - Set `users.is_super_admin = false` for everyone
   - For one user (the one installing the system), set `is_super_admin = true`
2. **Per-user DEK:**
   - For each user, generate a DEK
   - Wrap it with their current password-derived KEK
   - Insert into `user_keys`
3. **Per-company DEK:**
   - For each company, generate a DEK + a library secret
   - Wrap the library secret with the host super-admin's KEK
   - Wrap the company DEK with the library secret
   - Insert into `company_keys` + `company_secrets`
   - For each user in the company, create a `company_dek_grants` row
4. **Migrate files:**
   - For each existing `LibrarySource`:
     - Create a new `library_sources` row under the new design (no `path` column)
     - For each `LibraryItem` in the old source:
       - Read the file from disk
       - Generate a random `id`
       - Encrypt with the **owner's** DEK (or the **company's** DEK, depending on the visibility flag we set for migrated data — proposal: migrated files default to `private` if the user was the sole owner of the source, `company` if there was an "External Library" tagged as shared)
       - Write to `var/lib/library/<user_id>/content/<source_id>/<id>.enc` + `meta.json`
       - Create a `LibraryFile` row
       - Delete the original on disk
5. **Verify:** spot-decrypt 1% of migrated files. Report any failures.
6. **Drop the old tables** (rename them to `library_sources_old` / `library_items_old` for 30 days, then drop).

The migration is idempotent — re-running it skips files that have already been migrated (checked by a hash of the original path + size + mtime).

## Out of scope (explicitly)

- True end-to-end encryption for shares (the share key is server-held in v1)
- Cross-company file sharing (in v1, a private file can only be shared with users in the same company)
- Per-source ACL (a user can only see sources they own, or sources their company has marked company-wide)
- Multi-company users (a user belongs to exactly one company)
- Tenant enforcement on share links (a `/d/{token}` link works for anyone who has it)
- Per-source encryption keys (in v1, all files in a source share the same DEK)

## Threat model (the encryption v1 protects against)

| Adversary | What they can do |
|---|---|
| Disk thief | Sees only ciphertext. Filenames, sizes, directory structure are all encrypted. |
| DB dump (no admin password) | The DB has only `random_id` paths, encrypted metadata blobs, and user IDs. The DEK is wrapped with the user's KEK, which they don't have. |
| Compromised PHP process with the user's session | Has the unwrapped DEK in memory. **Can decrypt everything for that user.** This is the limit of the threat model. |
| Compromised PHP process without the user's session | Same as DB dump — useless. |
| Server admin | Everything. The DEK is wrapped, but the admin can reset the user's password and re-wrap with their own KEK. This is acceptable for a self-hosted install; in a hosted SaaS you'd use a hardware security module. |

## What this does NOT protect against

- The user themselves (they can decrypt their own data)
- The host super admin (they can reset any user's password)
- A compromised PHP process that has the user's session cookie

## Build order

1. **Schema migration** (new tables, new columns) — does not break existing code
2. **Per-user DEK generation** — new service `EncryptionService` with `deriveKeyFromPassword`, `wrapDek`, `unwrapDek`. Generate DEKs for every existing user on first login (transparent migration)
3. **Per-company DEK** — wrapped to the host super-admin's KEK; grants for each user
4. **Encrypted `LibraryStorage`** — new class, drop-in for the current one
5. **Visibility filtering** — `LibraryService::browsePath` accepts a `User`, returns only `company` files in their company + their own `private` files
6. **Quota service** — `QuotaService::effectiveUserQuota($user)` that the upload pipeline consults
7. **File request quota** — same effective quota
8. **UI** — new "Company library" tab in the browse view
9. **Migration command** — one-shot script that moves existing data
10. **Audit** — every file move is logged in `owner_access_logs` (already exists) + every share goes to `download_logs`
11. **DAMA\DoctrineTestBundle + test coverage** — encrypted operations need verification
12. **Documentation** — update this doc to reflect the new actual state

Estimated: 4 weeks of focused work, 1 engineer. The encryption primitives (sodium) are 1 day. The schema and migration are 1 week. The runtime path is 2 weeks. The UI and audit are 1 week.

## Open questions

These need answers before build start:

1. **What's the canonical company for users who exist before the migration?** Either a "Legacy Imports Inc." company, or the host company itself. My recommendation: the host company, so the existing "Completed" and "External Library" sources become "the host's library."
2. **How does a host super admin get created on first run?** Either an install-time CLI command, or an env var like `INITIAL_SUPER_ADMIN_EMAIL`. My recommendation: env var; the installer creates a one-shot link to set the password.
3. **What happens to `quotaBytes` on a user who has more data than the company quota?** Migration rolls back per-user — refuses to migrate any file that would push the company over its cap. The user sees an error in `/admin/system-health` explaining the gap.
4. **What about the `app.library_path` env var and `/mnt/library` mount?** They become unused. The new design puts everything under `var/lib/library/`. The mount can stay for compatibility (you'd point it at a backup target) or be removed.
