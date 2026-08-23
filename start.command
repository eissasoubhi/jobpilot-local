#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

JOBPOST_HOST="jobpost.test"
JOBPOST_URL="http://${JOBPOST_HOST}"
LOCALHOST_URL="http://localhost:3000"
HOST_CONFIGURED=false

if ! command -v docker >/dev/null 2>&1; then
  osascript -e 'display alert "Docker Desktop est requis" message "Installe et démarre Docker Desktop, puis relance JobPilot." as critical' 2>/dev/null || true
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  osascript -e 'display alert "Docker Desktop n’est pas démarré" message "Démarre Docker Desktop, puis relance JobPilot." as critical' 2>/dev/null || true
  exit 1
fi

if grep -Eq '(^|[[:space:]])jobpost\.test([[:space:]]|$)' /etc/hosts; then
  HOST_CONFIGURED=true
else
  echo "Configuration facultative du nom local ${JOBPOST_HOST}..."
  if osascript >/dev/null <<'APPLESCRIPT'
do shell script "printf '\n# JobPilot local\n127.0.0.1 jobpost.test\n' >> /etc/hosts" with administrator privileges
APPLESCRIPT
  then
    HOST_CONFIGURED=true
  else
    echo "Nom local non configuré ; JobPilot restera accessible via ${LOCALHOST_URL}."
    JOBPOST_URL="${LOCALHOST_URL}"
  fi
fi

if [ ! -f .env ]; then
  cp .env.example .env
  KEY="$(openssl rand -base64 32)"
  python3 - "$KEY" <<'PY'
from pathlib import Path
import sys
path = Path('.env')
content = path.read_text()
content = content.replace(
    'APP_ENCRYPTION_KEY=replace-with-base64-32-byte-key',
    'APP_ENCRYPTION_KEY=' + sys.argv[1],
)
path.write_text(content)
PY
fi

# Prefer jobpost.test when the host is configured, but retain the historical
# localhost URL when the user declines the optional system-level hosts change.
if [ "${HOST_CONFIGURED}" = true ]; then
  python3 <<'PY'
from pathlib import Path
path = Path('.env')
content = path.read_text()
old = 'WEB_URL=http://localhost:3000'
new = 'WEB_URL=http://jobpost.test'
if old in content:
    path.write_text(content.replace(old, new))
PY
else
  python3 <<'PY'
from pathlib import Path
path = Path('.env')
content = path.read_text()
preferred = 'WEB_URL=http://jobpost.test'
fallback = 'WEB_URL=http://localhost:3000'
if preferred in content:
    path.write_text(content.replace(preferred, fallback))
PY
fi

mkdir -p data/private

if [ ! -f data/private/.storage-migrated ]; then
  echo "Migration des documents privés vers data/private..."
  docker compose stop api scheduler >/dev/null 2>&1 || true
  docker compose --profile tools run --rm private-storage-migrator
  touch data/private/.storage-migrated
fi

# The scheduler command evolves with JobPilot. Recreate it on every explicit
# local start so a long-lived container cannot keep an older worker command
# after the repository has been updated.
docker compose up -d --remove-orphans --force-recreate scheduler
docker compose up -d --remove-orphans

printf 'Démarrage de JobPilot'
for _ in {1..90}; do
  if curl -fsS "${JOBPOST_URL}/api/health" >/dev/null 2>&1; then
    echo
    echo "Vérification des migrations de base de données..."
    if ! docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
      echo "Impossible d’appliquer les migrations. Consulte les logs avec : docker compose logs --tail=200 api"
      exit 1
    fi
    open "${JOBPOST_URL}"
    exit 0
  fi
  printf '.'
  sleep 2
done

echo
echo "JobPilot prend trop de temps à démarrer. Lance : docker compose logs --tail=200"
exit 1
