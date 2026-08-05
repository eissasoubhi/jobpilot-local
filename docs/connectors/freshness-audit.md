# Connector synchronization freshness

## Purpose

Extraction health and scheduler health are different signals. A connector may have produced valid offers during its last run while the scheduler has silently stopped running it afterwards.

JobPilot provides a deterministic freshness audit for the persisted connector registry.

## Command

```bash
docker compose exec api php bin/console app:connectors:audit-freshness
```

The default expected interval is six hours. A different interval can be supplied for diagnostics:

```bash
docker compose exec api php bin/console app:connectors:audit-freshness --interval=3600
```

The command returns a non-zero exit code when an active connector requires attention, so it can be used by a health check, cron job, or external monitoring system.

## Machine-readable output

Monitoring systems can request JSON without parsing the human-readable table:

```bash
docker compose exec api php bin/console app:connectors:audit-freshness --format=json
```

The report contains:

- generation date;
- effective interval;
- global `OK` or `ALERT` status;
- connector and alert counts;
- one structured freshness record per connector, including status, timestamps, overdue duration and reason.

The exit code remains authoritative: `0` means no active connector is alerting and `1` means at least one requires attention. Unsupported formats return the Symfony invalid-command exit code.

## States

```text
INACTIVE      disabled, incomplete, or blocked by policy
NEVER_SYNCED  active but no completed synchronization exists
FRESH         still inside the expected interval
DUE           late, but within one additional interval
OVERDUE       at least one complete interval was missed
STALE         several complete intervals were missed
```

`NEVER_SYNCED`, `OVERDUE`, and `STALE` return an alert. `DUE` is deliberately tolerated to avoid false alarms while a scheduler pass is starting.

The expected interval is never allowed below fifteen minutes, matching the synchronization scheduler guardrail.

## Safety boundary

The audit is read-only. It does not force a synchronization, enable a connector, bypass a compliance policy, or contact an external source. It only evaluates timestamps already stored in PostgreSQL.

## Operational use

A production monitor can execute the command after the scheduler's normal grace period. A failure should trigger inspection of:

1. the scheduler container or process;
2. the connector's enabled and configured state;
3. its compliance policy;
4. the latest synchronization history and error;
5. database connectivity and worker availability.

Freshness monitoring complements parser health, field completeness, quotas, and circuit breakers; it does not replace them.
