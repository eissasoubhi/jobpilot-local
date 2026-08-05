# External connector alert webhook

JobPilot can send a webhook when the existing connector freshness audit reports one or more active connectors in an alert state.

The feature is disabled by default. It does not enable a connector, contact a job source, or change collection permissions.

## Configuration

Set the following variables in the project `.env` file:

```dotenv
CONNECTOR_ALERT_WEBHOOK_URL=https://hooks.example.com/jobpilot
CONNECTOR_ALERT_WEBHOOK_ALLOWED_HOST=hooks.example.com
CONNECTOR_ALERT_WEBHOOK_SECRET=replace-with-a-long-random-secret
```

`CONNECTOR_ALERT_WEBHOOK_URL` must use HTTPS, cannot contain credentials, cannot use a non-standard port, and cannot target an IP address or `localhost`.

`CONNECTOR_ALERT_WEBHOOK_ALLOWED_HOST` is mandatory when a URL is configured. It must exactly match the URL hostname. Redirects are disabled.

`CONNECTOR_ALERT_WEBHOOK_SECRET` is optional but recommended. When present, JobPilot signs the exact JSON request body with HMAC-SHA256 and sends the result in:

```text
X-JobPilot-Signature: sha256=<hex digest>
```

The receiver should calculate the same HMAC over the raw request body and compare it with a constant-time comparison.

## Scheduler behavior

After each scheduled offer synchronization, the scheduler runs:

```bash
php bin/console app:connectors:notify-freshness --interval=21600
```

The command uses the same freshness analysis as `app:connectors:audit-freshness`.

A fingerprint containing the alerting connector codes and statuses is stored under the private project volume. JobPilot sends only when that fingerprint changes. A recovery clears the fingerprint, so a later recurrence can generate a new notification. A failed webhook request is never marked as sent.

This state store is suitable for the current single scheduler instance. A future multi-instance deployment must move notification deduplication to PostgreSQL or Redis.

## Payload

```json
{
  "event": "connector.freshness.alert",
  "generatedAt": "2026-08-05T20:00:00+00:00",
  "intervalSeconds": 21600,
  "alertCount": 1,
  "connectors": [
    {
      "code": "symfony-jobs",
      "name": "Symfony Jobs",
      "status": "STALE",
      "lastSyncedAt": "2026-08-04T10:00:00+00:00",
      "nextExpectedAt": "2026-08-04T16:00:00+00:00",
      "overdueBySeconds": 43200,
      "reason": "The connector has missed several synchronization intervals."
    }
  ]
}
```

The payload contains operational connector diagnostics only. It contains no offer content, CV, Gmail message, OAuth token, credential, or webhook secret.

## Manual verification

With a configured test endpoint:

```bash
docker compose exec api php bin/console app:connectors:notify-freshness
```

Possible successful outcomes are: webhook disabled, no active alert, alert state already notified, or webhook sent. Invalid endpoint configuration and non-2xx responses return a non-zero command exit code.
