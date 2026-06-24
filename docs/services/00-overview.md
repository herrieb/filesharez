# Services

All services live in `src/Service/`. They're plain PHP classes, autowired from the container, no interface required unless they have a swap-in/test-double reason.

## `DownloadService` — handles every download path

**File:** `src/Service/DownloadService.php`

**Constructor:**

```php
__construct(
    TransferRepository    $transferRepository,
    DownloadLogRepository $downloadLogRepository,
    EntityManagerInterface $entityManager,
    StorageInterface      $storage,
    LibraryStorage       $libraryStorage,
)
```

**Public methods:**

- `findByToken(string $token): ?Transfer` — looks up by `hash('sha256', $token) === $transfer->tokenHash`
- `canDownload(Transfer $transfer, ?string $password = null): array` — returns `['allowed' => bool, 'reason' => 'expired'|'exhausted'|'revoked'|'password'|null]`
- `download(Transfer $transfer, Request $request, ?TransferFile $transferFile = null): DownloadResult` — increments `downloadCount`, opens a stream from `StorageInterface` (regular) or `LibraryStorage` (library-backed), returns a DTO
- `getDownloadLogs(Transfer $transfer): array` — all `DownloadLog` rows for a transfer
- `readStream(TransferFile $transferFile): mixed` — opens the file as a stream
- `streamZip(Transfer $transfer, Request $request): void` — streams a `ZipStream` for multi-file transfers

## `DownloadResult` — DTO for the download pipeline

**File:** `src/Service/DownloadResult.php`

`final readonly class DownloadResult { public function __construct(public readonly mixed $stream, public readonly string $filename, public readonly string $mimeType, public readonly int $size) {} }`

## `TransferService` — creates and manages transfers

**File:** `src/Service/TransferService.php`

**Constructor:**

```php
__construct(
    TransferRepository              $transferRepository,
    SavedTransferTokenRepository    $savedTokenRepository,
    EntityManagerInterface          $entityManager,
    StorageInterface                $storage,
    MessageBusInterface             $messageBus,
    int                             $defaultExpiryDays,
    int                             $defaultMaxDownloads,
)
```

**Public methods:**

- `createFileTransfer(User $user, array $files, ?int $maxDownloads, ?int $expiryDays, ?string $password, ?string $recipientEmail, ?string $message): Transfer`
- `createTextTransfer(User $user, string $filename, string $content, ?int $maxDownloads, ?int $expiryDays, ?string $password, ?string $recipientEmail, ?string $message): Transfer` — text uploads are stored as `filename.txt` and use the same pipeline as file uploads (including a `DownloadLog` per recipient and the same expiry rules)
- `addFileToTransfer(Transfer $transfer, UploadedFile $file): TransferFile`
- `deleteTransfer(Transfer $transfer): void` — skips storage delete for library-backed files
- `revokeTransfer(Transfer $transfer): void`
- `extendExpiry(Transfer $transfer, int $days): void`
- `resendEmail(Transfer $transfer): void` — regenerates the raw token if the controller lost it
- `flushChanges(Transfer $transfer): void`

## `FileRequestService` — file request CRUD

**File:** `src/Service/FileRequestService.php`

**Constructor:**

```php
__construct(
    FileRequestRepository    $fileRequestRepository,
    EntityManagerInterface   $entityManager,
)
```

**Public methods:**

- `createRequest(User $user, string $name, ?string $description, int $maxFiles = 10, int $maxFileSizeBytes = 1073741824, int $maxTotalSizeBytes = 5368709120, ?string $password, ?int $expiryDays): FileRequest`
- `findByToken(string $token): ?FileRequest`
- `deactivateRequest(FileRequest $request): void`
- `activateRequest(FileRequest $request): void`
- `deleteRequest(FileRequest $request): void`

## `LibraryService` — the entire library feature

**File:** `src/Service/LibraryService.php`

The biggest service. Owns source create/rescan/delete, folder browsing, share-link generation, file uploads, mkdir, delete. Sanitizes filenames, validates extension allowlist, enforces `MAX_UPLOAD_SIZE`.

**Constructor:**

```php
__construct(
    LibrarySourceRepository       $sourceRepository,
    LibraryItemRepository         $itemRepository,
    SavedTransferTokenRepository   $savedTokenRepository,
    EntityManagerInterface         $entityManager,
    LibraryStorage                 $libraryStorage,
    MessageBusInterface            $messageBus,
    int                            $defaultExpiryDays,
    int                            $defaultMaxDownloads,
    int                            $scanDepth,
    string                         $maxUploadSize,   // e.g. "20G", parsed internally
)
```

**Public methods (all the library entry points):**

- `createSource(User $owner, string $name, string $path): LibrarySource`
- `rescanSource(LibrarySource $source, ?int $depth = null): int` — returns the count of items found
- `deleteSource(LibrarySource $source): void`
- `assertOwnsSource(LibrarySource $source, User $user, bool $adminAllowed = false): void` — throws if `$user` is not the source owner
- `browsePath(LibrarySource $source, string $relativePath, User $user, bool $includeHidden = true, int $page = 1, int $perPage = 200): array` — returns the full browse state (children, breadcrumb, current item, saved token, pagination)
- `ensureItemForEntry(LibrarySource $source, array $entry): LibraryItem` — upsert: create a `LibraryItem` row if it doesn't exist, update if it does
- `buildBreadcrumb(LibrarySource $source, string $relativePath): array`
- `sharePath(LibrarySource $source, string $relativePath, User $owner, ?int $maxDownloads, ?int $expiryDays, ?string $password, ?string $recipientEmail, ?string $message): Transfer` — share a single path
- `shareSelection(LibrarySource $source, array $relativePaths, User $owner, ...): Transfer` — share multiple paths in one go
- `uploadFile(LibrarySource $source, User $owner, UploadedFile $file, string $relativeFolder = '/'): string` — returns the absolute path
- `createFolder(LibrarySource $source, User $owner, string $relativePath, string $name): string`
- `deleteItem(LibrarySource $source, User $owner, string $relativePath): void`
- `shareItem(LibraryItem $item, User $owner, ...): Transfer` — legacy compat shim that resolves to `sharePath`

**Path safety:** the `LibraryStorage` it delegates to enforces all of the real path checks (`resolveUnderSource`, `assertInsideSource`, `realpath` prefix). `LibraryService` is the orchestration layer.

**Extension allowlist:** `isExtensionAllowed()` keeps a hard-coded list of safe file extensions. No executables. Documented in [`library/01-current-architecture.md`](../library/01-current-architecture.md).

## `LibraryAccessService` — owner-direct access logging

**File:** `src/Service/LibraryAccessService.php`

**Constructor:**

```php
__construct(
    OwnerAccessLogRepository $logRepository,
    EntityManagerInterface   $entityManager,
)
```

**Public methods:**

- `record(User $user, LibrarySource $source, string $path, string $action, ?int $sizeBytes = null, ?Request $request = null): void` — persists a row
- `recentForUser(User $user, int $limit = 100): array` — returns `OwnerAccessLog[]`

Used by `LibraryController::ownerDownload` and `LibraryController::ownerPreview`.

## `LibraryStorageStreamer` — turns a library file into a `StreamedResponse`

**File:** `src/Service/LibraryStorageStreamer.php`

**Constructor:**

```php
__construct(LibraryStorage $libraryStorage)
```

**Public methods:**

- `buildFileResponse(string $absolutePath, string $filename, ?int $approxSize = null): StreamedResponse`

Used for:
- Library-backed single-file downloads via `/d/{token}/file/{fileId}` (the controller calls this when `Transfer::isFromLibrary()` is true)
- Owner-direct downloads via `/library/sources/{id}/download`
- A directory gets streamed as a ZIP using `LibraryStorage::streamAsZip` directly

## `UploadSessionService` — tus protocol + finalize

**File:** `src/Service/UploadSessionService.php`

**Constants:** `SESSION_TTL_HOURS = 24`, `TUS_TEMP_DIR = 'var/tus'`

**Constructor:**

```php
__construct(
    UploadSessionRepository $sessionRepository,
    EntityManagerInterface  $entityManager,
    MessageBusInterface     $messageBus,
    LoggerInterface         $logger,
    StorageInterface        $storage,
    string                  $projectDir,
)
```

The constructor instantiates a `TusPhp\Tus\Server` with file cache and `$projectDir.'/var/tus'` as the upload dir, and `setApiPath('/upload/resumable')`. (The API path is set on the tus server object but the actual tus protocol is implemented manually in `ResumableUploadController` so this is partly vestigial.)

**Public methods:**

- `getTusServer(): TusServer`
- `create(User $user, int $sizeBytes, array $tusMetadata, ?string $mimeType = null): UploadSession` — reserves quota via `$user->reserveBytes($sizeBytes)`, creates the row, touches the temp file
- `getOwnedById(string $id, User $user): ?UploadSession` — auth-scoped lookup
- `cancel(UploadSession $session): void` — releases quota, removes temp file, removes row
- `recordChunk(UploadSession $session, int $bytes): void` — bumps `offset_bytes`, refreshes `expires_at`
- `finalize(UploadSession $session): Transfer` — moves the temp file into LocalStorage via `StorageInterface::ingestFromPath`, creates a `Transfer` + `TransferFile`, releases the reserved quota, dispatches `SendTransferEmail` if there's a recipient
- `ensureTempDirExists(): void`

## `UploadCleanupService` — runs the `var/tus` cleanup tick

**File:** `src/Service/UploadCleanupService.php`

**Constructor:**

```php
__construct(
    UploadSessionRepository $sessionRepository,
    EntityManagerInterface  $entityManager,
    LoggerInterface         $logger,
)
```

**Public methods:**

- `cleanupExpired(): int` — deletes every `UploadSession` with `expires_at < now()`, releases the reserved quota, removes the temp file. Returns the count.

## Services that don't exist (yet)

There's no `QuotaService` — quota is computed inline at the call sites. The [roadmap](../roadmap/00-tenancy-encryption.md) describes extracting it when we add per-company quotas.

There's no `NotificationService` — emails are dispatched directly via the message bus from each controller or service. There's no `AuditService` — admin actions are logged via Monolog only.

There's no `WebSocketService`, no `PusherService` — the real-time activity feed on the dashboard polls the database every 30 seconds. That's fine for 10s of users; it'll need redesign for 100s.
