# Library — Current Architecture

The library feature lets each user register host-side folders ("sources") and share files from them. Files stay on disk — the app just indexes them and mints share links.

## High-level flow

```
┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│  /mnt/library    │    │ /app/storage      │    │  transfer_files  │
│  (host path,     │    │  /transfers/...   │    │  (Postgres)      │
│   bind-mounted)  │    │  (host path,      │    │                  │
│                  │    │   bind-mounted)   │    │                  │
│  The files       │    │  Files that       │    │  Metadata about  │
│  themselves.     │    │  users have       │    │  every share.    │
│  Read by         │    │  uploaded via     │    │                  │
│  LibraryStorage. │    │  the app.         │    │                  │
│                  │    │  Written by       │    │                  │
│                  │    │  LocalStorage.    │    │                  │
└──────────────────┘    └──────────────────┘    └──────────────────┘
```

`LibrarySource` and `LibraryItem` are the DB-side index. The actual files live on disk and are streamed directly to the recipient — the app never copies them.

## Components

### `src/Storage/LibraryStorage.php` — disk I/O

The class that actually reads files from disk and enforces path safety.

**Constructor:** `__construct(string $libraryPath = null, ?string $externalPath = null)` — registers two allowed roots on construction. `addAllowedRoot()` is called by `LibraryService::createSource` for any source the user registers.

**Public methods:**

- `isPathAllowed(string $absolutePath): bool` — checks against the registered roots
- `addAllowedRoot(string $path): void` — `realpath` + add to the list
- `scanRoot(string $rootPath): array` — one-level scan, kept for compatibility
- `scanRecursive(string $rootPath, int $maxDepth = 1, bool $includeHidden = true): array` — walks the tree to the given depth, skips symlinks
- `listDirectory(string $absolutePath, ?string $sourceRoot = null): array` — one-level listing
- `addDirectoryToZip(ZipStream $zip, string $absolutePath, string $prefixInZip = ''): void` — for the bulk-share ZIP pipeline
- `resolveUnderSource(string $sourceRoot, string $relativePath): string` — `realpath` + prefix check; throws on path escape
- `assertInsideSource(string $sourceRoot, string $absolutePath): void`
- `resolveWriteTarget(string $sourceRoot, string $relativePath): string` — pure path math, doesn't require the file to exist
- `writeStream(string $sourceRoot, string $relativePath, $sourceStream, int $maxBytes = -1): string` — streams bytes to a `.partial` temp file then renames atomically
- `mkdir(string $sourceRoot, string $relativePath): string` — recursive mkdir
- `remove(string $sourceRoot, string $absolutePath): void` — unlink files or rmdir folders, refuses to delete the source root
- `streamAsZip(string $absolutePath, $output, string $zipName): void` — streams a folder (or single file) as a ZIP
- `addFileToZip(ZipStream $zip, string $absoluteFile, string $entryName): void` — internal helper
- `mimeType(string $path): string` — `finfo_file` wrapper
- `dirSize(string $path): int`

**Path-safety invariants:**

1. `realpath()` is run on both the source root and the target before any access
2. The resolved target must equal the source root or start with `<root>/`
3. `..` and `.` segments in relative paths are rejected
4. `null bytes` in paths are rejected
5. `is_link()` is checked before any read/write so a symlink pointing outside the source is not followed

### `src/Service/LibraryService.php` — orchestration

The class the controllers call. Wraps `LibraryStorage` with:

- DB-side tracking (creates/updates `LibraryItem` rows as files are seen)
- File extension allowlist
- Filename sanitization
- Quota enforcement (only the per-user cap; no per-tenant cap yet)
- `Transfer` creation for share links
- The `Scan` flow (one-level then up to `scanDepth` deep)

The service has methods for every CRUD op: `createSource`, `rescanSource`, `deleteSource`, `browsePath`, `sharePath`, `shareSelection`, `uploadFile`, `createFolder`, `deleteItem`. See [services/00-overview.md](../services/00-overview.md) for the full list.

### `src/Service/LibraryStorageStreamer.php` — turns files into responses

Used by `DownloadController` (when a transfer is library-backed) and `LibraryController::ownerDownload`. Builds a `StreamedResponse` with the right `Content-Disposition` (attachment) and `Content-Type`. Directories get buffered into `php://temp` first to compute the size, then replayed — the [Z-encoding of `zipstream-php`](https://github.com/maennchen/ZipStream-PHP) handles this.

## The scan flow

When a user creates a library source, `LibraryService::createSource` does:

1. `realpath($userProvidedPath)` — bail if not a directory
2. `LibraryStorage::addAllowedRoot($realPath)` — register as an allowed root
3. Persist the `LibrarySource` row
4. `rescanSource($source)` — kick off the initial scan

`rescanSource` (with default depth 5):

1. Call `LibraryStorage::scanRecursive($sourcePath, 5)` to get a list of `['path', 'name', 'parent_path', 'relative_path', 'size_bytes', 'is_directory', 'mime_type']` entries
2. For each entry, upsert a `LibraryItem` row (update if `(source_id, path)` exists, insert if not)
3. Delete any `LibraryItem` rows for this source that no longer exist on disk
4. Update `source.itemCount` and `source.totalSizeBytes`
5. Set `source.lastScannedAt = now()`

`scanRecursive` skips symlinks, so a symlink farm pointing at unrelated parts of the disk won't pollute the index.

## The browse flow

When the user opens `/library/sources/{id}/browse?path=/some/dir`:

1. `LibraryStorage::resolveUnderSource($sourcePath, $relativePath)` — `realpath` + prefix check
2. `LibraryStorage::listDirectory($absolute, $sourcePath)` — one-level scan
3. For each child, `LibraryService::ensureItemForEntry()` — upsert the `LibraryItem` row
4. Look up any `SavedTransferToken` matching the path (so the owner can recover a previous share link)
5. Return everything to the controller, which renders the browse template

The browse template renders:
- A breadcrumb row
- A toolbar with: Up, Rescan, **Upload**, **New folder**, **Share current**
- For each child: an icon (folder/file), a name, a size, an "updated at" line, and a row of action buttons (Share, ZIP-share, Download, Preview if previewable, Delete)

## The share flow

When the user clicks Share:

1. `LibraryService::sharePath(source, '/foo/bar.pdf', user, ...)` or `shareSelection(source, ['/foo', '/bar.pdf'], user, ...)`
2. Resolve each path to a disk entry via `resolveEntryForShare()` — also creates a `LibraryItem` row if the path is past the eager-scan depth
3. Build a `Transfer` (with one `TransferFile` per path)
4. The `TransferFile.storedFilename` is the **absolute path** on disk
5. Build a `SavedTransferToken` so the owner can recover the link later
6. If a recipient email was provided, dispatch `SendTransferEmail`
7. Return the share URL (`/d/{token}`)

The download flow for a library-backed file is the same as a regular file — but the `TransferFile.storedFilename` is an absolute path, and `DownloadService::download` opens it via `LibraryStorage::readStream` (via the `LibraryStorageStreamer`).

## The owner-direct flow

When the owner clicks Download or Preview on a row in the browse view:

1. `LibraryController::ownerDownload(source, ?path=/foo/bar.pdf)` — checks `assertOwnsSource`, then `LibraryStorage::resolveUnderSource`
2. For files: `LibraryStorageStreamer::buildFileResponse(absolute, basename, filesize)` — sets `Content-Disposition: attachment`
3. For directories: streams a ZIP via `LibraryStorage::streamAsZip`
4. `LibraryAccessService::record(user, source, path, 'download', size, request)` — adds a row to `owner_access_logs`

For Preview:

1. `LibraryController::ownerPreview(source, ?path=/foo.png)` — same auth + path check
2. If directory: redirect to the download endpoint
3. If file: detect MIME via `finfo_file`, set `Content-Disposition: inline` for image/video/audio/pdf/text, otherwise `attachment`
4. `LibraryAccessService::record(user, source, path, 'preview', size, request)`

The owner can see their own activity at `/account/library-activity`.

## The upload + mkdir + delete flow

These are all in `LibraryController` and `LibraryService`. They use the new `LibraryStorage::writeStream`, `::mkdir`, `::remove` methods. See the [services doc](../services/00-overview.md) for the full method list and [the library smoke test](../../../../../tmp/library-test-final.sh) for end-to-end verification.

## The extension allowlist

`LibraryService::isExtensionAllowed()` keeps a hard-coded list of safe extensions:

```php
$allow = [
    'pdf','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp',
    'txt','md','csv','json','xml','yml','yaml','html','css',
    'zip','tar','gz','tgz','bz2','7z','rar','xz',
    'jpg','jpeg','png','gif','webp','svg','bmp','ico','tiff','heic',
    'mp3','wav','ogg','flac','m4a','aac','opus',
    'mp4','webm','mkv','mov','avi','m4v','wmv','flv',
    'iso','img','deb','rpm','apk','dmg','epub','mobi','azw','azw3',
];
```

Stripped of:
- `.php`, `.sh`, `.py`, `.js`, `.exe`, `.bat` — script executables
- Anything that could be served as active content
- Document types that can carry macros (`.docm`)

The allowlist is intentionally conservative. To add a new type, edit the array and document why.

## Path-safety: end-to-end

```
User clicks "Download" on /foo/bar.pdf
  ↓
LibraryController::ownerDownload('source-uuid', 'path=/foo/bar.pdf')
  ↓
LibraryStorage::resolveUnderSource('/mnt/library', '/foo/bar.pdf')
  → realpath('/mnt/library') = '/mnt/library'
  → realpath('/mnt/library/foo/bar.pdf') = '/mnt/library/foo/bar.pdf'
  → check '/mnt/library/foo/bar.pdf' starts with '/mnt/library/'
  → returns '/mnt/library/foo/bar.pdf'
  ↓
fopen('/mnt/library/foo/bar.pdf', 'rb') + fpassthru + headers
```

If the user submits `?path=/../etc/passwd`:
- `resolveWriteTarget` rejects `..` segments in the path math
- `resolveUnderSource` would `realpath` the target — `/mnt/library/../etc/passwd` realpaths to `/etc/passwd`, which doesn't start with `/mnt/library/`, so it throws
- Controller catches the throw and returns 404

If the user submits `?path=/etc/passwd`:
- The path is absolute (`/etc/passwd`)
- `realpath('/mnt/library/etc/passwd')` returns false
- The catch returns 404

The check is on the **resolved absolute path**, not the user-supplied string. That's why `..` traversal is blocked even though we only check the resolved path — `realpath` collapses the `..` and the prefix check then sees that the result escapes the source.

## What's missing (see the [roadmap](../../roadmap/00-tenancy-encryption.md))

- **At-rest encryption.** Library files are visible to anyone with disk access. A rebuild should treat this as a known limitation until the encryption feature is built.
- **Tenant isolation.** A user can register a folder anywhere on the host disk. The only check is the source root, not whether the user is allowed to access that path. There's no per-company ACL.
- **Share-link tenant enforcement.** Anyone with a `/d/{token}` URL can download. There's no per-company access check on the share.
- **Per-company / per-user encryption keys.** Each user gets a single hardcoded bcrypt password hash; the library mount is shared across all users.
