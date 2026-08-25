#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

JOBPILOT_HOST="jobpilot.test"
JOBPILOT_URL="http://${JOBPILOT_HOST}"
LOCALHOST_URL="http://localhost:3000"
HOST_CONFIGURED=false
PROXY_NETWORK="${LOCAL_DEV_PROXY_NETWORK:-local-dev-proxy}"

if ! command -v docker >/dev/null 2>&1; then
  osascript -e 'display alert "Docker Desktop est requis" message "Installe et démarre Docker Desktop, puis relance JobPilot." as critical' 2>/dev/null || true
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  osascript -e 'display alert "Docker Desktop n’est pas démarré" message "Démarre Docker Desktop, puis relance JobPilot." as critical' 2>/dev/null || true
  exit 1
fi

if grep -Eq '(^|[[:space:]])jobpilot\.test([[:space:]]|$)' /etc/hosts; then
  HOST_CONFIGURED=true
else
  echo "Configuration facultative du nom local ${JOBPILOT_HOST}..."
  if osascript >/dev/null <<'APPLESCRIPT'
do shell script "printf '\n# JobPilot local\n127.0.0.1 jobpilot.test jobpost.test\n' >> /etc/hosts" with administrator privileges
APPLESCRIPT
  then
    HOST_CONFIGURED=true
  else
    echo "Nom local non configuré ; JobPilot restera accessible via ${LOCALHOST_URL}."
    JOBPILOT_URL="${LOCALHOST_URL}"
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

# Prefer jobpilot.test when the host is configured, but retain the historical
# localhost URL when the user declines the optional system-level hosts change.
if [ "${HOST_CONFIGURED}" = true ]; then
  python3 <<'PY'
from pathlib import Path
path = Path('.env')
content = path.read_text()
for old in ('WEB_URL=http://localhost:3000', 'WEB_URL=http://jobpost.test'):
    content = content.replace(old, 'WEB_URL=http://jobpilot.test')
path.write_text(content)
PY
else
  python3 <<'PY'
from pathlib import Path
path = Path('.env')
content = path.read_text()
fallback = 'WEB_URL=http://localhost:3000'
for preferred in ('WEB_URL=http://jobpilot.test', 'WEB_URL=http://jobpost.test'):
    content = content.replace(preferred, fallback)
path.write_text(content)
PY
fi

mkdir -p data/private

# Port 80 belongs to one global local reverse proxy shared by local projects.
# The helper also removes the legacy JobPilot/MPC containers that used to bind
# port 80 directly during the one-time migration to this setup.
bash scripts/ensure-local-dev-proxy.sh

if [ ! -f data/private/.storage-migrated ]; then
  echo "Migration des documents privés vers data/private..."
  docker compose stop api scheduler >/dev/null 2>&1 || true
  docker compose --profile tools run --rm private-storage-migrator
  touch data/private/.storage-migrated
fi

# Explicit local startup is also the update boundary after a git pull. Recreate
# every long-lived application process so Next, Symfony and the async worker all
# execute the checked-out source instead of keeping an old runtime alive.
docker compose up -d --remove-orphans --force-recreate api scheduler web
docker compose up -d --remove-orphans

WEB_CONTAINER_ID="$(docker compose ps -q web)"
if [ -z "$WEB_CONTAINER_ID" ]; then
  echo "Impossible de trouver le conteneur web JobPilot."
  exit 1
fi

docker network connect --alias jobpilot-web "$PROXY_NETWORK" "$WEB_CONTAINER_ID"

cat <<'YAML' | bash scripts/register-local-dev-proxy-config.sh jobpilot.yml
http:
  routers:
    jobpilot-web:
      entryPoints:
        - web
      rule: "Host(`jobpilot.test`) || Host(`jobpost.test`)"
      service: jobpilot-web
  services:
    jobpilot-web:
      loadBalancer:
        servers:
          - url: "http://jobpilot-web:3000"
YAML

printf 'Démarrage de JobPilot'
for _ in {1..90}; do
  # /api/health only proves that HTTP routing works. Also require a small
  # database-backed endpoint so the browser is not opened on a shell whose API
  # requests will stay pending indefinitely.
  if curl --max-time 5 -fsS "${JOBPILOT_URL}/api/health" >/dev/null 2>&1 \
    && curl --max-time 5 -fsS "${JOBPILOT_URL}/api/settings/ai" >/dev/null 2>&1; then
    echo
    echo "Vérification des migrations de base de données..."
    if ! docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
      echo "Impossible d’appliquer les migrations. Consulte les logs avec : docker compose logs --tail=200 api"
      exit 1
    fi
    open "${JOBPILOT_URL}"
    exit 0
  fi
  printf '.'
  sleep 2
done

echo
echo "JobPilot répond au healthcheck mais l’application n’est pas complètement prête."
echo "Vérifie l’API, le frontend et le proxy partagé avec :"
echo "  docker compose ps"
echo "  docker compose logs --tail=200 api web scheduler"
echo "  docker logs --tail=100 ${LOCAL_DEV_PROXY_CONTAINER:-local-dev-proxy}"
exit 1
