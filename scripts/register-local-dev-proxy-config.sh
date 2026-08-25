#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <config-name.yml>" >&2
  exit 2
fi

CONFIG_NAME="$1"
CONFIG_VOLUME="${LOCAL_DEV_PROXY_CONFIG_VOLUME:-local-dev-proxy-config}"

if [[ ! "$CONFIG_NAME" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*\.ya?ml$ ]]; then
  echo "Invalid proxy config name: $CONFIG_NAME" >&2
  exit 2
fi

if ! docker volume inspect "$CONFIG_VOLUME" >/dev/null 2>&1; then
  docker volume create "$CONFIG_VOLUME" >/dev/null
fi

docker run --rm -i \
  --volume "$CONFIG_VOLUME:/config" \
  --env CONFIG_NAME="$CONFIG_NAME" \
  alpine:3.20 \
  sh -c '
    set -eu
    tmp="/config/${CONFIG_NAME}.tmp.$$"
    cat > "$tmp"
    mv "$tmp" "/config/$CONFIG_NAME"
  '
