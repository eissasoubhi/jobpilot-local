#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-.env.production}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: production env file not found: $ENV_FILE" >&2
  exit 2
fi

declare -A env_values=()
while IFS= read -r raw_line || [[ -n "$raw_line" ]]; do
  line="${raw_line%$'\r'}"
  [[ -z "${line//[[:space:]]/}" ]] && continue
  [[ "$line" =~ ^[[:space:]]*# ]] && continue
  if [[ "$line" != *=* ]]; then
    echo "ERROR: invalid env line (expected KEY=VALUE)" >&2
    exit 2
  fi
  key="${line%%=*}"
  value="${line#*=}"
  key="${key#${key%%[![:space:]]*}}"
  key="${key%${key##*[![:space:]]}}"
  if [[ ! "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then
    echo "ERROR: invalid env key" >&2
    exit 2
  fi
  if [[ ${#value} -ge 2 && "$value" == '"'*'"' ]]; then
    value="${value:1:${#value}-2}"
  elif [[ ${#value} -ge 2 && "$value" == "'"*"'" ]]; then
    value="${value:1:${#value}-2}"
  fi
  env_values["$key"]="$value"
done < "$ENV_FILE"

errors=0
fail() {
  printf 'ERROR: %s\n' "$1" >&2
  errors=$((errors + 1))
}

require_non_placeholder() {
  local key="$1"
  shift
  local value="${env_values[$key]-}"
  if [[ -z "$value" ]]; then
    fail "$key must be set"
    return
  fi
  local forbidden
  for forbidden in "$@"; do
    if [[ "$value" == "$forbidden" ]]; then
      fail "$key must not use the development/default placeholder"
      return
    fi
  done
}

require_non_placeholder POSTGRES_PASSWORD jobpilot change-me
require_non_placeholder APP_SECRET change-me
require_non_placeholder APP_ENCRYPTION_KEY replace-with-base64-32-byte-key
if [[ -n "${env_values[APP_ENCRYPTION_KEY]-}" && "${env_values[APP_ENCRYPTION_KEY]}" != "replace-with-base64-32-byte-key" ]]; then
  decoded_size="$(printf '%s' "${env_values[APP_ENCRYPTION_KEY]}" | base64 -d 2>/dev/null | wc -c | tr -d '[:space:]' || true)"
  if [[ "$decoded_size" != "32" ]]; then
    fail "APP_ENCRYPTION_KEY must be valid base64 encoding exactly 32 bytes"
  fi
fi
require_non_placeholder JOBPILOT_BROWSER_WORKER_TOKEN jobpilot-local-browser-worker-token-change-me replace-with-random-token

web_url="${env_values[WEB_URL]-}"
if [[ ! "$web_url" =~ ^https://[^[:space:]]+$ ]]; then
  fail "WEB_URL must be an https:// URL in production"
fi

google_client_id="${env_values[GOOGLE_CLIENT_ID]-}"
google_client_secret="${env_values[GOOGLE_CLIENT_SECRET]-}"
google_redirect_uri="${env_values[GOOGLE_REDIRECT_URI]-}"
if [[ -n "$google_client_id" || -n "$google_client_secret" ]]; then
  if [[ -z "$google_client_id" || -z "$google_client_secret" ]]; then
    fail "GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET must be configured together"
  fi
  if [[ ! "$google_redirect_uri" =~ ^https://[^[:space:]]+$ ]]; then
    fail "GOOGLE_REDIRECT_URI must be an https:// URL when Gmail OAuth is enabled"
  fi
fi

if (( errors > 0 )); then
  echo "Production preflight failed with $errors configuration error(s)." >&2
  exit 1
fi

echo "Production preflight passed. Secret values were not printed."
