#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

JOBPOST_HOST="jobpost.test"
JOBPOST_URL="http://${JOBPOST_HOST}"

if ! command -v docker >/dev/null 2>&1; then
  osascript -e 'display alert "Docker Desktop est requis" message "Installe et démarre Docker Desktop, puis relance JobPilot." as critical' 2>/dev/null || true
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  osascript -e 'display alert "Docker Desktop n’est pas démarré" message "Démarre Docker Desktop, puis relance JobPilot." as critical' 2>/dev/null || true
  exit 1
fi

if ! grep -Eq '(^|[[:space:]])jobpost\.test([[:space:]]|$)' /etc/hosts; then
  echo "Configuration du nom local ${JOBPOST_HOST}..."
  if ! osascript >/dev/null <<'APPLESCRIPT'
do shell script "printf '\n# JobPilot local\n127.0.0.1 jobpost.test\n' >> /etc/hosts" with administrator privileges
APPLESCRIPT
  then
    echo "Impossible d'ajouter ${JOBPOST_HOST} à /etc/hosts. Relance start.command et autorise la modification système."
    exit 1
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

# Migrate the previous local default without overwriting a custom WEB_URL.
python3 <<'PY'
from pathlib import Path
path = Path('.env')
content = path.read_text()
old = 'WEB_URL=http://localhost:3000'
new = 'WEB_URL=http://jobpost.test'
if old in content:
    path.write_text(content.replace(old, new))
PY

mkdir -p data/private

if [ ! -f data/private/.storage-migrated ]; then
  echo "Migration des documents privés vers data/private..."
  docker compose stop api scheduler >/dev/null 2>&1 || true
  docker compose --profile tools run --rm private-storage-migrator
  touch data/private/.storage-migrated
fi

docker compose up -d --remove-orphans

printf 'Démarrage de JobPilot'
for _ in {1..90}; do
  if curl -fsS "${JOBPOST_URL}/api/health" >/dev/null 2>&1; then
    echo
    open "${JOBPOST_URL}"
    exit 0
  fi
  printf '.'
  sleep 2
done

echo
echo "JobPilot prend trop de temps à démarrer. Lance : docker compose logs --tail=200"
exit 1
