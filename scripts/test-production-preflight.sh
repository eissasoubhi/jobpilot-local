#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
preflight="$repo_root/scripts/production-preflight.sh"
tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

valid_key="$(printf '12345678901234567890123456789012' | base64 | tr -d '\n')"
cat > "$tmp_dir/good.env" <<EOF_GOOD
POSTGRES_PASSWORD=strong-db-secret
APP_SECRET=strong-app-secret
APP_ENCRYPTION_KEY=$valid_key
JOBPILOT_BROWSER_WORKER_TOKEN=strong-browser-worker-token
WEB_URL=https://jobpilot.example.test
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://jobpilot.example.test/api/integrations/gmail/callback
GMAIL_SEARCH_QUERY=(job OR emploi) newer_than:30d
EOF_GOOD

bash "$preflight" "$tmp_dir/good.env" >/dev/null

cat > "$tmp_dir/bad.env" <<'EOF_BAD'
POSTGRES_PASSWORD=jobpilot
APP_SECRET=change-me
APP_ENCRYPTION_KEY=replace-with-base64-32-byte-key
JOBPILOT_BROWSER_WORKER_TOKEN=jobpilot-local-browser-worker-token-change-me
WEB_URL=http://localhost:3000
GOOGLE_CLIENT_ID=id-only
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/callback
EOF_BAD

if bash "$preflight" "$tmp_dir/bad.env" >"$tmp_dir/stdout" 2>"$tmp_dir/stderr"; then
  echo "expected unsafe production configuration to fail" >&2
  exit 1
fi

grep -q 'POSTGRES_PASSWORD' "$tmp_dir/stderr"
grep -q 'APP_SECRET' "$tmp_dir/stderr"
grep -q 'APP_ENCRYPTION_KEY' "$tmp_dir/stderr"
grep -q 'JOBPILOT_BROWSER_WORKER_TOKEN' "$tmp_dir/stderr"
grep -q 'WEB_URL' "$tmp_dir/stderr"
grep -q 'GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET' "$tmp_dir/stderr"
grep -q 'GOOGLE_REDIRECT_URI' "$tmp_dir/stderr"

if grep -q 'strong-db-secret\|strong-app-secret\|strong-browser-worker-token' "$tmp_dir/stdout" "$tmp_dir/stderr"; then
  echo "preflight output leaked a test secret" >&2
  exit 1
fi

echo "production preflight tests passed"
