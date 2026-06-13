#!/usr/bin/env sh
set -eu

BASE_URL="${BASE_URL:-http://localhost:8080}"
COOKIE_JAR="$(mktemp)"
LOGIN_HTML="$(curl -fsS -c "$COOKIE_JAR" "$BASE_URL/login")"
CSRF_TOKEN="$(printf '%s' "$LOGIN_HTML" | sed -n 's/.*name="_csrf_token" value="\([^"]*\)".*/\1/p' | head -n 1)"

if [ -z "$CSRF_TOKEN" ]; then
  echo "CSRF token not found" >&2
  exit 1
fi

curl -fsS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -d "email=user@wdpai.com" \
  -d "password=wdpai123" \
  -d "_csrf_token=$CSRF_TOKEN" \
  "$BASE_URL/login" >/dev/null

for path in /dashboard /planer /session /atlas /history; do
  curl -fsS -b "$COOKIE_JAR" "$BASE_URL$path" >/dev/null
done

ADMIN_CODE="$(curl -sS -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" "$BASE_URL/admin/users")"
if [ "$ADMIN_CODE" != "403" ]; then
  echo "Expected 403 for /admin/users, got $ADMIN_CODE" >&2
  exit 1
fi

echo "Smoke tests passed"
