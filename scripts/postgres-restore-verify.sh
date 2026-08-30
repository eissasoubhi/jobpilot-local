#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: scripts/postgres-restore-verify.sh BACKUP_FILE TARGET_DB

Restore a custom-format backup into a NEW isolated PostgreSQL database and run
basic integrity checks. TARGET_DB must end in `_restore_test` so this command
cannot overwrite the normal JobPilot database by accident.

Environment overrides:
  POSTGRES_USER  Database user (default: jobpilot)
USAGE
}

if [[ ${1:-} == "-h" || ${1:-} == "--help" ]]; then
  usage
  exit 0
fi

if [[ $# -ne 2 ]]; then
  usage >&2
  exit 64
fi

backup_file=$1
target_db=$2
db_user=${POSTGRES_USER:-jobpilot}

if [[ ! -f "$backup_file" ]]; then
  echo "Backup file not found: $backup_file" >&2
  exit 66
fi

if [[ ! "$target_db" =~ ^[A-Za-z0-9_]+_restore_test$ ]]; then
  echo "Refusing restore: TARGET_DB must contain only letters, digits or underscores and end in _restore_test." >&2
  exit 64
fi

if docker compose exec -T db psql --username="$db_user" --dbname=postgres \
  --tuples-only --no-align --command="SELECT 1 FROM pg_database WHERE datname = '$target_db'" | grep -qx 1; then
  echo "Refusing restore: target database already exists: $target_db" >&2
  exit 65
fi

docker compose exec -T db createdb --username="$db_user" "$target_db"

restore_ok=0
cleanup_on_failure() {
  if [[ $restore_ok -ne 1 ]]; then
    docker compose exec -T db dropdb --username="$db_user" --if-exists "$target_db" >/dev/null 2>&1 || true
  fi
}
trap cleanup_on_failure EXIT

docker compose exec -T db pg_restore \
  --username="$db_user" \
  --dbname="$target_db" \
  --no-owner \
  --no-privileges < "$backup_file"

docker compose exec -T db psql \
  --username="$db_user" \
  --dbname="$target_db" \
  --set=ON_ERROR_STOP=1 \
  --command="SELECT current_database(), current_user;" \
  --command="SELECT count(*) AS public_table_count FROM pg_tables WHERE schemaname = 'public';"

restore_ok=1
echo "Restore verification completed in isolated database: $target_db"
echo "The restored database was intentionally kept for manual inspection."
