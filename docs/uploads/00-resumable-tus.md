# Resumable Uploads (tus)

The upload system uses the [tus protocol](https://tus.io/) — chunked, resumable, with per-chunk retry. Implemented server-side with `ankitpokhrel/tus-php` and client-side with `tus-js-client@4.3.1` (vendored to `public/vendor/tus/tus.min.js`, no CDN).

## Why tus

- Survives network drops — a 20 GB upload at 1 Gbps takes ~3 minutes; at 5% packet loss the average is much longer. Without resumability, a single dropped packet at minute 2 means restarting from byte 0.
- Streams bytes to a temp file as they arrive, no full-buffer
- Server-side quota reservation prevents overshoot even with parallel uploads

## Pipeline (end-to-end)

```
Browser                              App                          Worker (async)
  │                                   │                              │
  │  POST /upload/resumable           │                              │
  │  Upload-Length: 52428800         │                              │
  │  Upload-Metadata: filename X,... │                              │
  │ ─────────────────────────────────> │                              │
  │                                   │ UploadSessionService::create │
  │                                   │ reserveBytes(50MB)            │
  │                                   │ INSERT upload_sessions       │
  │ <───────────────────────────────   │                              │
  │  201 Created                       │                              │
  │  Location: /upload/resumable/abc  │                              │
  │  Upload-Offset: 0                 │                              │
  │                                   │                              │
  │  PATCH /upload/resumable/abc      │                              │
  │  Upload-Offset: 0                │                              │
  │  <5MB chunk>                      │                              │
  │ ─────────────────────────────────> │                              │
  │                                   │ fopen 'a'                   │
  │                                   │ fwrite chunk                 │
  │ <───────────────────────────────   │                              │
  │  204 No Content                   │                              │
  │  Upload-Offset: 5242880          │                              │
  │                                   │                              │
  │  ... 9 more PATCHes ...           │                              │
  │                                   │                              │
  │  POST /upload/resumable/abc/finalize                            │
  │ ─────────────────────────────────> │                              │
  │                                   │ mv var/tus/abc  → storage   │
  │                                   │ INSERT transfers + transfer_files           │
  │                                   │ releaseBytes(50MB)            │
  │                                   │ ──────async msg──────>         │
  │ <───────────────────────────────   │  SendTransferEmail            │
  │  200 { downloadUrl: "/d/..." }   │                              │
  │                                   │                              │
  │  Browser shows the success page   │                              │
  │                                   │                              │
  │                                   │ ─────async──────> SendTransferEmail (handler)
  │                                   │  mail to recipient            │
```

## Server: `ResumableUploadController`

Six routes, all under `/upload/resumable`, all `ROLE_USER`:

| Method | Path | Purpose |
|---|---|---|
| `OPTIONS` | `/upload/resumable` and `/upload/resumable/{id}` | tus capability discovery. Always 204. |
| `POST` | `/upload/resumable` | Create a session. Reads `Upload-Length` (required) and `Upload-Metadata` (key space-separated from value, both base64). Returns 201 + `Location` + `Upload-Offset: 0` + `Upload-Expires`. |
| `PATCH` | `/upload/resumable/{id}` | Append a chunk. Body is the chunk bytes (raw, not multipart). Validates `Upload-Offset` against the session's current `offset_bytes`. Writes to `var/tus/<id>` with `fopen('a')`. Returns 204 + new `Upload-Offset`. |
| `HEAD` | `/upload/resumable/{id}` | Returns 200 + `Upload-Offset` + `Upload-Length` so the client can resume after a network drop. |
| `DELETE` | `/upload/resumable/{id}` | Cancel. Releases reserved quota, removes the temp file, removes the row. Returns 204. |
| `POST` | `/upload/resumable/{id}/finalize` | Finalize. Creates the `Transfer` + `TransferFile`, dispatches `SendTransferEmail` if there's a recipient email. Returns 200 with `{transfer, downloadUrl}`. |

## Client: `tus-js-client`

Bundled at `public/vendor/tus/tus.min.js`. Loaded in `templates/transfer/upload.html.twig`:

```html
<script src="{{ asset('vendor/tus/tus.min.js') }}"></script>
```

The browser creates a tus upload per file:

```js
const upload = new tus.Upload(file, {
    endpoint: '/upload/resumable',
    chunkSize: 5 * 1024 * 1024,        // 5 MB
    retryDelays: [0, 1000, 3000, 5000, 10000],
    metadata: {
        filename: file.name,
        filetype: file.type,
        max_downloads: '1',
        expiry_days: '7',
        password: '',
        recipient_email: '',
        message: '',
    },
});

upload.on('progress', (bytesUploaded, bytesTotal) => {
    // update row UI
});

upload.on('success', () => {
    // call /upload/resumable/<id>/finalize, get the share URL
});

upload.start();
```

The per-row UI:
- A live progress bar per file
- A real-time speed indicator (computed from a rolling 1-second sample)
- A pause/resume button (uses `upload.abort(false)` / `upload.start()`)
- A cancel button (uses `upload.abort(true)` and DELETE)
- An aggregate bar across all in-flight uploads

The aggregate speed/ETA computation lives in the template's `<script>` block.

## Server-side: `UploadSessionService`

The orchestration layer.

- `create(User $user, int $sizeBytes, array $tusMetadata, ?string $mimeType = null): UploadSession` — creates a row, reserves quota, touches the temp file
- `getOwnedById(string $id, User $user): ?UploadSession` — auth-scoped lookup (returns null if the user doesn't own this session)
- `recordChunk(UploadSession $session, int $bytes): void` — bumps `offset_bytes`, refreshes `expires_at` to `now + 24h`
- `finalize(UploadSession $session): Transfer` — moves the temp file to LocalStorage via `StorageInterface::ingestFromPath`, creates a `Transfer` + `TransferFile`, dispatches `SendTransferEmail` if there's a recipient email, releases the reserved quota, removes the row
- `cancel(UploadSession $session): void` — releases reserved quota, removes temp file, removes row

## Quota reservation

`User::reserveBytes($bytes)` adds the upload size to `$user->reservedBytes`. The check `getQuotaRemaining()` (`= quota - usedStorage - reservedBytes`) is what blocks the `create` call from succeeding if the user would overshoot.

When the upload finalizes, the reservation is **consumed** (the file is now in `usedStorage`). When the upload is cancelled or expires, the reservation is **released**.

This means the user can have at most `quota - usedStorage` of in-flight uploads at any time. Two parallel 5 GB uploads against a 10 GB quota and 0 GB used = 5 GB reserved, 5 GB available = can fit one more 5 GB but not another. The second 5 GB upload gets rejected at the `create` step.

## Cleanup

`App\Message\CleanupExpiredUploads` runs every 15 minutes via the scheduler. `UploadCleanupService::cleanupExpired()` deletes every `UploadSession` with `expires_at < now()`, releases the reservation, removes the temp file.

The 24-hour TTL means: a user starts a 20 GB upload, loses connection, walks away. 24 hours later the temp file is gone and their quota is free.

## What's NOT here yet

- **No upload from the library browse view.** Library uploads use the regular multipart form (`/library/sources/{id}/upload`), not tus. The library isn't expected to handle 20 GB drops.
- **No chunked resumable library uploads.** The library is designed for medium-size personal files (the extension allowlist reflects this — no executables, max 20 GB).
- **No async thumbnail generation.** A future feature would be: on finalize, dispatch a `GenerateThumbnail` message that creates `/app/storage/previews/<filename>.jpg`. Not implemented.
