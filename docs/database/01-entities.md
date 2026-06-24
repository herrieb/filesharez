# Every Entity

## `User` (`src/Entity/User.php`)

**Table:** `users`
**Repository:** `UserRepository`
**Implements:** `UserInterface`, `PasswordAuthenticatedUserInterface`
**Lifecycle:** `#[ORM\HasLifecycleCallbacks]` (sets `updatedAt` on `PreUpdate`)

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$email` | `string` | length 180, unique, used as the user identifier |
| `$password` | `string` | bcrypt-hashed |
| `$roles` | `array` (json) | Default `[]` — `ROLE_USER` added by `getRoles()` |
| `$name` | `string` | length 255 |
| `$quotaBytes` | `int` (bigint) | Default 10 GiB (`10737418240`) |
| `$reservedBytes` | `int` (bigint) | Default 0 — used by tus uploads |
| `$isActive` | `bool` | Default `true` — admin can disable |
| `$theme` | `string` | length 32, default `'longhorn'` |
| `$createdAt` | `\DateTimeImmutable` | |
| `$updatedAt` | `\DateTimeImmutable` | |
| `$transfers` | `Collection<Transfer>` | OneToMany, `orphanRemoval: true`, cascade: persist |
| `$fileRequests` | `Collection<FileRequest>` | OneToMany, `orphanRemoval: true` |

`getUserIdentifier()` returns email.

## `Transfer` (`src/Entity/Transfer.php`)

**Table:** `transfers`
**Repository:** `TransferRepository`
**Constants:** `DEFAULT_MAX_DOWNLOADS = 1`
**Lifecycle:** `#[ORM\HasLifecycleCallbacks]` (sets `updatedAt` on `PreUpdate`)

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$user` | `User` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$tokenHash` | `string` | length 255, unique — `hash('sha256', $rawToken)` |
| `$totalSizeBytes` | `int` (bigint) | Default 0 |
| `$downloadCount` | `int` | Default 0 |
| `$maxDownloads` | `int` | Default 1 (burn-after-read) |
| `$expiresAt` | `\DateTimeImmutable` | |
| `$passwordHash` | `?string` | length 255, nullable — `password_hash()` |
| `$message` | `?text` | nullable — personal message to recipient |
| `$recipientEmail` | `?string` | length 255, nullable |
| `$isRevoked` | `bool` | Default `false` |
| `$createdAt` | `\DateTimeImmutable` | |
| `$updatedAt` | `\DateTimeImmutable` | |
| `$files` | `Collection<TransferFile>` | OneToMany, `cascade: ['persist']`, `orphanRemoval: true` |
| `$downloadLogs` | `Collection<DownloadLog>` | OneToMany, `orphanRemoval: true` |
| `$fileRequest` | `?FileRequest` | ManyToOne, nullable, `onDelete: SET NULL` |
| `$isFromLibrary` | `bool` | Default `false` — true for library-backed shares |
| `$libraryItem` | `?LibraryItem` | ManyToOne, nullable, `onDelete: SET NULL` |
| `$senderName` | `?string` | length 255, nullable — for file requests |
| `$senderEmail` | `?string` | length 255, nullable — for file requests |
| `$rawToken` | `?string` | **not persisted** — runtime only, set by the controller after the entity is loaded |

## `TransferFile` (`src/Entity/TransferFile.php`)

**Table:** `transfer_files`
**Repository:** `TransferFileRepository`

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$transfer` | `Transfer` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$originalFilename` | `string` | length 255 |
| `$storedFilename` | `string` | length 255 — the path inside `/app/storage/transfers/...` or the absolute library path |
| `$mimeType` | `string` | length 255 |
| `$sizeBytes` | `int` (bigint) | |
| `$isText` | `bool` | Default `false` — true for text uploads |
| `$textContent` | `?text` | nullable — content of a text upload, used to render an inline text view |
| `$downloadCount` | `int` | Default 0 |
| `$createdAt` | `\DateTimeImmutable` | |

## `DownloadLog` (`src/Entity/DownloadLog.php`)

**Table:** `download_logs`
**Repository:** `DownloadLogRepository`

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$transfer` | `Transfer` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$transferFile` | `?TransferFile` | ManyToOne, nullable, `onDelete: SET NULL` |
| `$ipAddress` | `?string` | length 45 (IPv6-compatible) |
| `$userAgent` | `?string` | length 1024 |
| `$downloadedAt` | `\DateTimeImmutable` | |

## `FileRequest` (`src/Entity/FileRequest.php`)

**Table:** `file_requests`
**Repository:** `FileRequestRepository`
**Lifecycle:** `#[ORM\HasLifecycleCallbacks]`

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$user` | `User` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$tokenHash` | `string` | length 255, unique |
| `$name` | `string` | length 255 |
| `$description` | `?text` | nullable |
| `$maxFiles` | `int` | Default 10 |
| `$maxFileSizeBytes` | `int` (bigint) | Default 1 GiB |
| `$maxTotalSizeBytes` | `int` (bigint) | Default 5 GiB |
| `$passwordHash` | `?string` | length 255, nullable |
| `$isActive` | `bool` | Default `true` |
| `$expiresAt` | `\DateTimeImmutable` | |
| `$createdAt` | `\DateTimeImmutable` | |
| `$updatedAt` | `\DateTimeImmutable` | |
| `$transfers` | `Collection<Transfer>` | OneToMany, mappedBy `fileRequest` |
| `$rawToken` | `?string` | **not persisted** |

## `SavedTransferToken` (`src/Entity/SavedTransferToken.php`)

**Table:** `saved_transfer_tokens`
**Repository:** `SavedTransferTokenRepository`
**Unique Constraint:** `uniq_user_transfer (user_id, transfer_id)`

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$user` | `User` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$transfer` | `Transfer` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$rawToken` | `string` | length 255 — the actual token (not the hash) |
| `$label` | `?string` | length 255, nullable |
| `$relativePath` | `?string` | length 1024, nullable — for library items |
| `$createdAt` | `\DateTimeImmutable` | |

## `UploadSession` (`src/Entity/UploadSession.php`)

**Table:** `upload_sessions`
**Repository:** `UploadSessionRepository`

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, PK (a random hex) |
| `$user` | `User` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$originalFilename` | `string` | length 512 |
| `$mimeType` | `?string` | length 255, nullable |
| `$sizeBytes` | `int` (bigint) | Default 0 |
| `$offsetBytes` | `int` (bigint) | Default 0 |
| `$tempPath` | `string` | length 1024 — the path inside `var/tus/<id>` |
| `$metadata` | `?text` | nullable — JSON-encoded metadata (max_downloads, expiry_days, password, recipient_email, message) |
| `$expiresAt` | `\DateTimeImmutable` | Default `+24 hours` in constructor |
| `$createdAt` | `\DateTimeImmutable` | |
| `$updatedAt` | `\DateTimeImmutable` | |

## `LibrarySource` (`src/Entity/LibrarySource.php`)

**Table:** `library_sources`
**Repository:** `LibrarySourceRepository`

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$owner` | `User` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$name` | `string` | length 255 |
| `$path` | `string` | length 1024 — the absolute path on disk (realpath of whatever the user registered) |
| `$isActive` | `bool` | Default `true` |
| `$createdAt` | `\DateTimeImmutable` | |
| `$lastScannedAt` | `?\DateTimeImmutable` | nullable |
| `$itemCount` | `int` | Default 0 |
| `$totalSizeBytes` | `int` (bigint) | Default 0 |
| `$items` | `Collection<LibraryItem>` | OneToMany, `orphanRemoval: true` |

## `LibraryItem` (`src/Entity/LibraryItem.php`)

**Table:** `library_items`
**Repository:** `LibraryItemRepository`
**Indexes:** `idx_library_source_path` is now UNIQUE `(source_id, path)`, plus `idx_library_source_parent (source_id, parent_path)`
**Lifecycle:** `#[ORM\HasLifecycleCallbacks]` (sets `updatedAt` on `PreUpdate`)

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$source` | `LibrarySource` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$path` | `string` | length 1024 — absolute filesystem path |
| `$name` | `string` | length 512 — basename |
| `$parentPath` | `?string` | length 1024, nullable — directory the item is in |
| `$relativePath` | `?string` | length 1024, nullable — relative to the source root |
| `$mimeType` | `?string` | length 255, nullable |
| `$sizeBytes` | `int` (bigint) | Default 0 |
| `$isDirectory` | `bool` | Default `false` |
| `$createdAt` | `\DateTimeImmutable` | |
| `$updatedAt` | `\DateTimeImmutable` | |

## `OwnerAccessLog` (`src/Entity/OwnerAccessLog.php`)

**Table:** `owner_access_logs`
**Repository:** `OwnerAccessLogRepository`
**Indexes:** `idx_owner_log_user (user_id, created_at)`, `idx_owner_log_source (source_id, created_at)`, `idx_owner_log_created (created_at)`
**Constants:** `ACTION_DOWNLOAD = 'download'`, `ACTION_PREVIEW = 'preview'`, `ACTION_ZIP = 'zip'`

| Property | Type | Options |
|---|---|---|
| `$id` | `?string` | length 32, unique, PK |
| `$user` | `User` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$source` | `LibrarySource` | ManyToOne, `nullable: false`, `onDelete: CASCADE` |
| `$path` | `string` | length 1024 |
| `$action` | `string` | length 16 — one of the action constants |
| `$sizeBytes` | `?int` (bigint) | nullable |
| `$ipAddress` | `?string` | length 64, nullable |
| `$userAgent` | `?string` | length 255, nullable |
| `$createdAt` | `\DateTimeImmutable` | |
