#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

docker compose up -d --remove-orphans

printf 'Attente de JobPilot'
for _ in {1..90}; do
  if curl -fsS http://localhost:3000/api/health >/dev/null 2>&1; then
    echo
    break
  fi
  printf '.'
  sleep 2
done

echo "Tests backend"
docker compose exec -T api php bin/phpunit

echo "Tests frontend"
docker compose exec -T web npm run typecheck
docker compose exec -T web npm run test:unit

echo "Tests end-to-end Chromium"
if command -v npm >/dev/null 2>&1; then
  (cd web && npm install --no-audit --no-fund && npx playwright install chromium && npm run test:e2e)
else
  echo "Node.js/npm est requis sur le Mac pour exécuter Playwright."
  exit 1
fi
