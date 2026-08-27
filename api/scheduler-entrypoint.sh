#!/bin/sh

set -u

heartbeat_path="${SCHEDULER_HEARTBEAT_PATH:-/tmp/jobpilot-scheduler-heartbeat}"
interval_seconds="${JOB_SYNC_INTERVAL_SECONDS:-21600}"

write_heartbeat() {
  heartbeat_dir=$(dirname "$heartbeat_path")
  heartbeat_tmp="${heartbeat_path}.tmp.$$"
  mkdir -p "$heartbeat_dir"
  printf '%s\n' "$(date +%s)" > "$heartbeat_tmp"
  mv "$heartbeat_tmp" "$heartbeat_path"
}

run_step() {
  step_name="$1"
  shift
  started_at=$(date +%s)

  "$@"
  exit_code=$?

  finished_at=$(date +%s)
  duration_seconds=$((finished_at - started_at))
  printf '{"event":"scheduler.step","step":"%s","exit_code":%d,"duration_seconds":%d}\n' \
    "$step_name" "$exit_code" "$duration_seconds" >&2

  return "$exit_code"
}

worker_loop() {
  while true; do
    run_step "jobs.sync-worker" php bin/console app:jobs:sync-worker
    worker_exit=$?
    if [ "$worker_exit" -ne 0 ]; then
      printf '{"event":"scheduler.worker.retry","exit_code":%d,"retry_in_seconds":1}\n' "$worker_exit" >&2
    fi
    sleep 1
  done
}

write_heartbeat
worker_loop &
worker_pid=$!
trap 'kill "$worker_pid" 2>/dev/null; wait "$worker_pid" 2>/dev/null' TERM INT EXIT

while true; do
  write_heartbeat
  cycle_failed=0

  run_step "jobs.repair-contaminated-gmail" php bin/console app:jobs:repair-contaminated-gmail --limit=50
  step_exit=$?
  if [ "$step_exit" -ne 0 ]; then cycle_failed=1; fi

  run_step "jobs.sync" php bin/console app:jobs:sync
  step_exit=$?
  if [ "$step_exit" -ne 0 ]; then cycle_failed=1; fi

  run_step "connectors.notify-freshness" php bin/console app:connectors:notify-freshness --interval="$interval_seconds"
  step_exit=$?
  if [ "$step_exit" -ne 0 ]; then cycle_failed=1; fi

  write_heartbeat
  printf '{"event":"scheduler.cycle.completed","failed":%s,"next_run_in_seconds":%s}\n' \
    "$cycle_failed" "$interval_seconds" >&2

  sleep "$interval_seconds"
done
