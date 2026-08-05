# Connector freshness webhook

JobPilot can send an external HTTPS webhook when one or more active connectors become `OVERDUE` or `STALE`.

The feature is disabled by default. It never contacts an external endpoint unless all of the following variables are configured:

```dotenv
CONNECTOR_ALERT_WEBHOOK_URL=https://hooks.example.com/jobpilot
CONNECTOR_ALERT_WEBHOOK_ALLOWED_HOST=hooks.example.com
CONNECTOR_ALERT_WEBHOOK_SECRET=replace-with-a-random-secret
```

## Safety rules

- HTTPS is mandatory.
- Only port 443 is accepted.
- The URL host must exactly match `CONNECTOR_ALERT_WEBHOOK_ALLOWED_HOST`.
- IP addresses, localhost, embedded credentials and redirects are rejected.
- The optional secret signs the exact JSON body with HMAC-SHA256 in `X-JobPilot-Signature`.
- A failed delivery is never recorded as sent.
- The same connector/status alert set is sent once, then suppressed until the state changes or all connectors recover.

## Scheduler

The scheduler runs:

```bash
php bin/console app:connectors:notify-freshness --interval=21600
```

When the webhook is not configured, the command exits successfully without a network call. This keeps existing installations backward-compatible.

## Payload

The JSON payload contains the event name, generation time, expected interval and only the connectors requiring attention. It does not include credentials, raw source records, CV data or Gmail content.

## Manual verification

Run the command inside the API container:

```bash
docker compose exec api php bin/console app:connectors:notify-freshness --interval=21600
```

Use a dedicated receiver endpoint and verify the `X-JobPilot-Signature` before accepting the alert.
