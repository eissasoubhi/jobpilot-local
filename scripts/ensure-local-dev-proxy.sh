#!/usr/bin/env bash
set -euo pipefail

PROXY_NETWORK="${LOCAL_DEV_PROXY_NETWORK:-local-dev-proxy}"
PROXY_CONTAINER="${LOCAL_DEV_PROXY_CONTAINER:-local-dev-proxy}"
PROXY_IMAGE="${LOCAL_DEV_PROXY_IMAGE:-traefik:v3.5}"
PROXY_LABEL="local.dev.proxy=traefik-v1"

if ! docker network inspect "$PROXY_NETWORK" >/dev/null 2>&1; then
  echo "Creating shared Docker network $PROXY_NETWORK..."
  docker network create "$PROXY_NETWORK" >/dev/null
fi

uses_host_port_80() {
  local container_id="$1"
  docker port "$container_id" 2>/dev/null \
    | grep -Eq -- '-> (127\.0\.0\.1|0\.0\.0\.0|\[::\]):80$'
}

# One-time migration from the old per-project port-80 setup. Only containers
# that belong to the known JobPilot/MPC services are removed automatically.
for container_id in $(docker ps -q); do
  container_name="$(docker inspect -f '{{.Name}}' "$container_id" | sed 's#^/##')"
  if [[ "$container_name" == "$PROXY_CONTAINER" ]] || ! uses_host_port_80 "$container_id"; then
    continue
  fi

  compose_project="$(docker inspect -f '{{ index .Config.Labels "com.docker.compose.project" }}' "$container_id" 2>/dev/null || true)"
  compose_service="$(docker inspect -f '{{ index .Config.Labels "com.docker.compose.service" }}' "$container_id" 2>/dev/null || true)"

  case "$compose_project:$compose_service" in
    jobpilot:web|jobpilot-local:web|mpc:local_proxy)
      echo "Removing legacy port-80 container $container_name..."
      docker rm -f "$container_id" >/dev/null
      ;;
    *)
      echo "Port 80 is already used by container $container_name."
      echo "Stop that container (or change its host port), then start the project again."
      exit 1
      ;;
  esac
done

if docker container inspect "$PROXY_CONTAINER" >/dev/null 2>&1; then
  proxy_kind="$(docker inspect -f '{{ index .Config.Labels "local.dev.proxy" }}' "$PROXY_CONTAINER" 2>/dev/null || true)"
  if [[ "$proxy_kind" != "traefik-v1" ]]; then
    echo "A container named $PROXY_CONTAINER already exists but is not the managed local proxy."
    echo "Rename/remove it, or set LOCAL_DEV_PROXY_CONTAINER to another name."
    exit 1
  fi

  if ! docker inspect -f '{{json .NetworkSettings.Networks}}' "$PROXY_CONTAINER" | grep -Fq "\"$PROXY_NETWORK\""; then
    docker network connect "$PROXY_NETWORK" "$PROXY_CONTAINER"
  fi

  if [[ "$(docker inspect -f '{{.State.Running}}' "$PROXY_CONTAINER")" != "true" ]]; then
    echo "Starting shared local reverse proxy..."
    docker start "$PROXY_CONTAINER" >/dev/null
  fi

  exit 0
fi

echo "Starting shared local reverse proxy on 127.0.0.1:80..."
docker run -d \
  --name "$PROXY_CONTAINER" \
  --restart unless-stopped \
  --network "$PROXY_NETWORK" \
  --publish 127.0.0.1:80:80 \
  --volume /var/run/docker.sock:/var/run/docker.sock:ro \
  --label "$PROXY_LABEL" \
  "$PROXY_IMAGE" \
  --providers.docker=true \
  --providers.docker.exposedbydefault=false \
  --providers.docker.network="$PROXY_NETWORK" \
  --entrypoints.web.address=:80 \
  --api.dashboard=false \
  --log.level=WARN >/dev/null
