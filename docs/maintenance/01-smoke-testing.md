# Smoke Testing

The app has no automated test suite. All "tests" are curl-based smoke scripts in `/tmp/`. The ones used during the build process are documented here.

## Why no test suite

The build is iterative — features are added session-by-session. A proper test suite (PHPUnit + WebTestCase + DAMA\DoctrineTestBundle for transactional tests) is on the roadmap, but the current state is "smoke test the new feature, move on."

## Pattern

The standard pattern for a smoke test:

1. Login via the form to get a session cookie
2. POST to the new endpoint with auth
3. Verify the response status code
4. Verify the side effect (file on disk, DB row, etc.)

The cookie file (`/tmp/<feature>.txt`) is preserved between requests in the same script.

## A minimal example

```bash
#!/bin/bash
set -e
COOKIE=/tmp/example.txt
rm -f $COOKIE
BASE=http://127.0.0.1:9999

# Login
LOGIN=$(curl -s -c $COOKIE -b $COOKIE $BASE/login)
CSRF=$(echo "$LOGIN" | sed -n 's/.*name="_csrf_token" *value="\([^"]*\)".*/\1/p' | head -1)
curl -s -c $COOKIE -b $COOKIE -X POST $BASE/login \
  --data-urlencode "_csrf_token=$CSRF" \
  --data-urlencode "email=meyer@live.nl" \
  --data-urlencode "password=testpass123" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -L -o /dev/null -w "Login: %{http_code}\n"

# Test the new endpoint
curl -s -b $COOKIE -X POST "$BASE/some/endpoint" \
  -H "Content-Type: application/json" \
  -d '{"key":"value"}' \
  -w "Status: %{http_code}\n"
```

## The smoke tests from the build

These were each run during a specific feature build. They're preserved as historical artifacts in case the rebuild needs to re-verify.

### Library IO (upload, mkdir, delete)

`/tmp/library-test-final.sh` exercises:

- Login as `meyer@live.nl`
- Upload a `.txt` file to the library
- Create a folder
- Upload into the folder
- Delete the file (200)
- Delete the folder recursively (200)
- Delete the root file (200)
- Reject `.php` extension (400, "File type not allowed")
- Reject path traversal `?path=/../../etc` (400, "Invalid path")
- Reject delete-source-root (400, "Cannot delete a source root")
- Reject cross-source ID (404)

### Owner-direct download + preview

`/tmp/owner-test.sh` exercises:

- Upload test files (`hello.txt`, `test.pdf`, `test.png`, `test.zip`)
- Owner-direct download via `/library/sources/{id}/download?path=/hello.txt` (200, content matches)
- Owner-direct preview of `.png` (200, `Content-Disposition: inline`, `Cache-Control: private, max-age=3600`)
- Owner-direct preview of `.zip` (200, `Content-Disposition: attachment`)
- Owner-direct download of a folder (200, valid zip with all files)
- Path traversal rejected (400)
- Cross-source ID rejected (404)
- Activity log has all entries (`SELECT * FROM owner_access_logs`)
- Activity page `/account/library-activity` renders

### Library on external disk

`/tmp/ext-lib.sh` exercises:

- Verify mount: `ls -la /mnt/external-library` inside the app container
- Upload + download a test file on the external mount
- Confirm file lives on `/dev/sdb1` (the host 1.8 TB disk)

### Resumable upload (tus)

`/tmp/smoke.sh` exercises:

- Login
- `POST /upload/resumable` with `Upload-Length` and `Upload-Metadata` headers (201 + `Location` + `Upload-Offset: 0`)
- `HEAD /upload/resumable/{id}` (200 + `Upload-Offset: 0`)
- `PATCH /upload/resumable/{id}` with a 5 MB body (204 + `Upload-Offset: 5242880`)
- `POST /upload/resumable/{id}/finalize` (200 with `{transfer, downloadUrl}`)

### Theme picker

`/tmp/sidebar-test2.sh` exercises:

- Switch to each of the 6 taskbar themes (`longhorn`, `sunset`, `midori`, `crt`) via the API
- Verify each renders with the correct `data-theme` attribute and the right CSS variables
- Same for the 3 sidebar themes (`tokyo`, `aquarelle`, `brutalist`)
- Verify the layout class on `<html>` matches the theme's `layout` field

## What the rebuild should add

- A proper PHPUnit test suite under `tests/` with at least:
  - `Entity\UserTest.php` — test the password hash, the role defaults, the quota computation
  - `Service/TransferServiceTest.php` — test the expiry / max-downloads logic
  - `Service/LibraryServiceTest.php` — test the extension allowlist, the path sanitization
  - `Controller/DownloadControllerTest.php` — test the canDownload matrix
- DAMA\DoctrineTestBundle for transactional DB tests
- GitHub Actions CI: run migrations on a fresh postgres, run the test suite, build the Docker image

For now, smoke tests via curl work. The cost of having no automated tests is: every change risks regression, and a rebuild will be more work than necessary.
