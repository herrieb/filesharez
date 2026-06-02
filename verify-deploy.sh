#!/usr/bin/env bash
set -euo pipefail

BASE="http://localhost:4113"
COOKIE_JAR="/tmp/filesharez_test_cookies.txt"
PASS=0
FAIL=0

ok()   { echo "  [PASS] $1"; PASS=$((PASS+1)); }
fail() { echo "  [FAIL] $1"; FAIL=$((FAIL+1)); }

echo "=== FileShareZ Deployment Verification ==="
echo ""

# --- 1. All containers running ---
echo "--- Container Health ---"
for svc in app nginx postgres redis worker scheduler mailpit; do
  STATUS=$(docker-compose ps "$svc" 2>/dev/null | grep -i "up" || echo "")
  if [ -n "$STATUS" ]; then
    ok "$svc is running"
  else
    fail "$svc is NOT running (status: $STATUS)"
  fi
done
echo ""

# --- 2. PostgreSQL reachable ---
echo "--- Database ---"
if docker-compose exec -T postgres pg_isready -U filesharez >/dev/null 2>&1; then
  ok "PostgreSQL accepts connections"
else
  fail "PostgreSQL not accepting connections"
fi

TABLE_COUNT=$(docker-compose exec -T postgres psql -U filesharez -d filesharez -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'" 2>/dev/null | tr -d ' ')
if [ "$TABLE_COUNT" -ge 3 ]; then
  ok "Database has $TABLE_COUNT tables"
else
  fail "Database has only $TABLE_COUNT tables (expected >= 3)"
fi
echo ""

# --- 3. Redis reachable ---
echo "--- Redis ---"
PONG=$(docker-compose exec -T redis redis-cli ping 2>/dev/null | tr -d '\r\n')
if [ "$PONG" = "PONG" ]; then
  ok "Redis responds PONG"
else
  fail "Redis did not respond PONG (got: $PONG)"
fi
echo ""

# --- 4. Login page loads ---
echo "--- HTTP Endpoints ---"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/login")
if [ "$HTTP_CODE" = "200" ]; then
  ok "Login page returns 200"
else
  fail "Login page returns $HTTP_CODE (expected 200)"
fi

# --- 5. CSRF token present ---
CSRF=$(curl -s -c "$COOKIE_JAR" "$BASE/login" | grep -oP 'name="_csrf_token" value="\K[^"]+' || true)
if [ -n "$CSRF" ]; then
  ok "CSRF token found on login page"
else
  fail "CSRF token not found on login page"
fi

# --- 6. Login works ---
LOGIN_CODE=$(curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" -o /dev/null -w "%{http_code}" \
  -X POST "$BASE/login" \
  -d "email=admin@example.com&password=YourSecurePassword123!&_csrf_token=$CSRF" \
  -L)
if [ "$LOGIN_CODE" = "200" ]; then
  ok "Login succeeds and redirects to dashboard (200)"
else
  fail "Login returns $LOGIN_CODE (expected 200 after redirect)"
fi

# --- 7. Authenticated pages accessible ---
for PAGE in "/dashboard/" "/upload/" "/account/" "/account/profile" "/account/security" "/transfers/"; do
  CODE=$(curl -s -b "$COOKIE_JAR" -o /dev/null -w "%{http_code}" "$BASE$PAGE")
  if [ "$CODE" = "200" ]; then
    ok "GET $PAGE returns 200"
  else
    fail "GET $PAGE returns $CODE"
  fi
done

# --- 8. Admin page accessible ---
ADMIN_CODE=$(curl -s -b "$COOKIE_JAR" -o /dev/null -w "%{http_code}" "$BASE/admin/")
if [ "$ADMIN_CODE" = "200" ]; then
  ok "GET /admin/ returns 200 (admin user)"
else
  fail "GET /admin/ returns $ADMIN_CODE"
fi
echo ""

# --- 9. Text upload works ---
echo "--- Upload API ---"
TEXT_RESULT=$(curl -s -b "$COOKIE_JAR" -X POST "$BASE/upload/text" \
  -d "filename=deploy_test.txt&content=Hello+from+deploy+test&max_downloads=1&expiry_days=7&recipient_email=test@example.com")
TEXT_SUCCESS=$(echo "$TEXT_RESULT" | grep -o '"success":true' || true)
if [ -n "$TEXT_SUCCESS" ]; then
  ok "Text upload returns success"
  TEXT_TOKEN=$(echo "$TEXT_RESULT" | grep -oP '"token":"\K[^"]+')
else
  fail "Text upload failed: $TEXT_RESULT"
  TEXT_TOKEN=""
fi

# --- 10. File upload works ---
echo "test_file_content_12345" > /tmp/filesharez_upload_test.bin
FILE_RESULT=$(curl -s -b "$COOKIE_JAR" -X POST "$BASE/upload/file" \
  -F "files[]=@/tmp/filesharez_upload_test.bin" \
  -F "max_downloads=1" \
  -F "expiry_days=7" \
  -F "recipient_email=test@example.com")
FILE_SUCCESS=$(echo "$FILE_RESULT" | grep -o '"success":true' || true)
if [ -n "$FILE_SUCCESS" ]; then
    ok "File upload returns success"
    FILE_TOKEN=$(echo "$FILE_RESULT" | grep -oP '"token":"\K[^"]+')
    FILE_ID=$(echo "$FILE_RESULT" | grep -oP '"files":\[\{"id":"\K[^"]+')
else
    fail "File upload failed: $FILE_RESULT"
    FILE_TOKEN=""
    FILE_ID=""
fi
rm -f /tmp/filesharez_upload_test.bin
echo ""

# --- 11. Download page accessible ---
echo "--- Download ---"
if [ -n "$TEXT_TOKEN" ]; then
  DL_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/d/$TEXT_TOKEN")
  if [ "$DL_CODE" = "200" ]; then
    ok "Download page for text transfer returns 200"
  else
    fail "Download page returns $DL_CODE"
  fi
fi

if [ -n "$FILE_TOKEN" ]; then
  DL_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/d/$FILE_TOKEN")
  if [ "$DL_CODE" = "200" ]; then
    ok "Download page for file transfer returns 200"
  else
    fail "Download page for file transfer returns $DL_CODE"
  fi
fi

if [ -n "$FILE_TOKEN" ] && [ -n "$FILE_ID" ]; then
  FILE_DL_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/d/$FILE_TOKEN/file/$FILE_ID")
  if [ "$FILE_DL_CODE" = "200" ] || [ "$FILE_DL_CODE" = "302" ]; then
    ok "Per-file download endpoint works"
  else
    fail "Per-file download endpoint returns $FILE_DL_CODE"
  fi
fi
echo ""

# --- 12. Storage not directly accessible via nginx ---
echo "--- Security ---"
STORAGE_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/storage/transfers/")
if [ "$STORAGE_CODE" = "404" ]; then
  ok "/storage/ is blocked by nginx (404)"
else
  fail "/storage/ returns $STORAGE_CODE (expected 404)"
fi
echo ""

# --- 13. Mailpit running ---
echo "--- Mailpit ---"
MAILPIT_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8025" 2>/dev/null || echo "000")
if [ "$MAILPIT_CODE" != "000" ]; then
  ok "Mailpit web UI reachable on port 8025"
else
  fail "Mailpit web UI not reachable on port 8025"
fi
echo ""

# --- Summary ---
echo "==================================="
echo "  Results: $PASS passed, $FAIL failed"
echo "==================================="
if [ "$FAIL" -gt 0 ]; then
  echo "  Some checks failed. Review output above."
  exit 1
else
  echo "  All checks passed! FileShareZ is deployed."
fi

rm -f "$COOKIE_JAR"