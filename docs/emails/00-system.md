# Emails

Three email templates. All rendered as plain HTML with inline CSS so they work in every mail client. Sent asynchronously via the Messenger async transport.

## The pipeline

```
Controller (or Service)              Messenger (async)             Mailer
  │                                    │                              │
  │ dispatch($message)                 │                              │
  │ ─────────────────────────────────> │                              │
  │                                    │ consume 'async'              │
  │                                    │ ─────────────────────────>   │
  │                                    │ MailerInterface->send()       │
  │                                    │   → MAILER_DSN               │
  │                                    │   → mailpit in dev,          │
  │                                    │   → real SMTP in prod        │
```

The retry strategy: 3 attempts with exponential backoff (1s, 2s, 4s) capped at 60s. After 3 failures the message goes to the `failed` transport for manual replay.

## Templates

### `templates/emails/transfer.html.twig`

Sent to the recipient when a transfer is created. Shows:
- Sender's name (if set)
- Sender's message (if set)
- Filename + size
- A big "Download File" CTA button linking to `/d/{token}`
- Expiry date and max-downloads below the CTA
- The FileShareZ wordmark

Subject: `"{senderName} sent you {filename}"` (or the default `"Someone sent you a file"`).

### `templates/emails/file_request_upload.html.twig`

Sent to the file-request owner when an anonymous uploader sends them files via `/r/{token}`. Shows:
- Uploader's name and email (if provided)
- Filename + size
- A "Download" CTA linking to the transfer

Subject: `"{senderName} uploaded a file via your file request"`.

### `templates/emails/download_notification.html.twig`

Sent to the transfer owner on the first successful download. Shows:
- Filename + size
- Downloader IP and user agent
- A "View transfer" CTA linking to `/transfers`

Subject: `"{downloaderName} downloaded {filename}"`.

## Message classes

### `App\Message\SendTransferEmail`

```php
class SendTransferEmail {
    public function __construct(
        public string $transferId,
        public string $recipientEmail,
        public string $rawToken,
        public ?string $message,
    ) {}
}
```

### `App\Message\SendFileRequestUploadEmail`

```php
class SendFileRequestUploadEmail {
    public function __construct(
        public string $transferId,
        public string $ownerEmail,
        public string $rawToken,
        public ?string $senderName,
        public ?string $senderEmail,
    ) {}
}
```

### `App\Message\SendDownloadNotificationEmail`

```php
class SendDownloadNotificationEmail {
    public function __construct(
        public string $transferId,
        public string $rawToken,
        public string $downloaderEmail,
        public ?string $downloaderName,
    ) {}
}
```

## Handlers

| Handler | Triggered by | Action |
|---|---|---|
| `SendTransferEmailHandler` | `TransferService::createFileTransfer` / `createTextTransfer` when `$recipientEmail` is set | Renders `emails/transfer.html.twig` and sends to `$recipientEmail` |
| `SendFileRequestUploadEmailHandler` | `PublicUploadController::handleUpload` after the upload is committed | Renders `emails/file_request_upload.html.twig` and sends to the file-request owner |
| `SendDownloadNotificationEmailHandler` | `DownloadController::downloadFile` on the first download (`$wasFirstDownload = $transfer->getDownloadCount() === 0`) | Renders `emails/download_notification.html.twig` and sends to the transfer owner |

All three handlers are `#[AsMessageHandler]` and depend on:
- `TransferRepository` (look up the transfer)
- `MailerInterface`
- `UrlGeneratorInterface` (build the `/d/{token}` URL)

## Where the messages are dispatched

| Source | Message |
|---|---|
| `TransferService::createFileTransfer`, `TransferService::createTextTransfer` | `SendTransferEmail` |
| `PublicUploadController::handleUpload` | `SendFileRequestUploadEmail` |
| `DownloadController::downloadFile` | `SendDownloadNotificationEmail` |
| `TransferController::resend` | `SendTransferEmail` |

## Inspecting sent email in dev

Open `http://localhost:8025` — mailpit shows every email the system sent, with the raw source, the HTML preview, and the headers.

## Design notes

- Inline CSS only — no `<style>` blocks (some clients strip them)
- Tables for layout — no flexbox/grid (more compatible)
- Single-column, max 600px wide
- High contrast — designed to look good in dark mode email clients without using a dark theme
- All CTAs are bulletproof buttons (VML for Outlook fallback isn't implemented — accept that Outlook will look slightly different)
- The wordmark is text + inline SVG, not an image (works in clients that block remote images)

## What's not here

- **No unsubscribe link** — emails are transactional, not marketing
- **No HTML email open tracking** — possible but considered privacy-intrusive
- **No localization** — templates are English-only
