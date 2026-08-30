#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: scripts/postgres-backup.sh OUTPUT_DIR

Create a PostgreSQL custom-format backup from the local Docker Compose `db`
service plus a SHA-256 checksum. The script never deletes or restores data.

Environment overrides:
  POSTGRES_DB    Database name (default: jobpilot)
  POSTGRES_USER  Database user (default: jobpilot)
USAGE
}

if [[ ${1:-} == "-h" || ${1:-} == "--help" ]]; then
  usage
  exit 0
fi

if [[ $# -ne 1 ]]; then
  usage >&2
  exit 64
fi

output_dir=$1
db_name=${POSTGRES_DB:-jobpilot}
db_user=${POSTGRES_USER:-jobpilot}

timestamp=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p "$output_dir"
backup_path="$output_dir/${db_name}-${timestamp}.dump"
checksum_path="${backup_path}.sha256"
tmp_path="${backup_path}.tmp"

cleanup() {
  rm -f "$tmp_path"
}
trap cleanup EXIT

docker compose exec -T db pg_dump \
  --username="$db_user" \
  --dbname="$db_name" \
  --format=custom \
  --no-owner \
  --no-privileges > "$tmp_path"

if [[ ! -s "$tmp_path" ]]; then
  echo "Backup failed: pg_dump produced an empty file." >&2
  exit 1
fi

mv "$tmp_path" "$backup_path"
sha256sum "$backup_path" > "$checksum_path"

echo "Backup: $backup_path"
echo "Checksum: $checksum_path"
