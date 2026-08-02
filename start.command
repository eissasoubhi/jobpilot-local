#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

if ! command -v docker >/dev/null 2>&1; then
  osascript -e 'display alert "Docker Desktop est requis" message "Installe et démarre Docker Desktop, puis relance JobPilot." as critical' 2>/dev/null || true
  exit 1
fi

if [ ! -f .env ]; then
  cp .env.example .env
  KEY="$(openssl rand -base64 32)"
  python3 - "$KEY" <<'PY'
from pathlib import Path
import sys
p = Path('.env')
s = p.read_text()
s = s.replace('APP_ENCRYPTION_KEY=replace-with-base64-32-byte-key', 'APP_ENCRYPTION_KEY=' + sys.argv[1])
p.write_text(s)
PY
fi

docker compose up -d
printf 'Démarrage de JobPilot'
for _ in {1..60}; do
  if curl -fsS http://localhost:8080/api/health >/dev/null 2>&1 && curl -fsS http://localhost:3000 >/dev/null 2>&1; then
    echo
    open http://localhost:3000
    exit 0
  fi
  printf '.'
  sleep 2
done

echo
echo "JobPilot prend trop de temps à démarrer. Lance: docker compose logs"
exit 1
