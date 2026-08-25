# Application activity timeline

The **Parcours des candidatures** page provides a read-only chronological view of the persistent business events already stored by JobPilot for the selected application's offer.

## Included events

The timeline can display the V1 business event catalogue:

- offer import and source occurrence merge;
- preparation creation and update;
- successful submission;
- response, rejection or interview detected from Gmail;
- follow-up.

The current application status remains visible in the offer summary as context, outside the event list.

## Data integrity

The page does not create or infer historical records. It reads `GET /api/jobs/{id}/timeline`, which returns the 200 most recent append-only events for one canonical offer in reverse chronological order.

A transition for which JobPilot has no persisted source event remains absent from history. The interface explicitly states this limitation instead of deriving an event from application dates or Gmail messages.

## Safety and privacy

- read-only interface;
- no external request beyond the local timeline API;
- no Gmail synchronization or message sending;
- no connector, credential, quota or compliance-policy change;
- no Gmail body, subject or sender displayed in the timeline payload;
- no database migration.

## Rollback

The page now reads the persistent offer timeline exposed by `GET /api/jobs/{id}/timeline`. It displays only stored business events; application status is contextual information and is not synthesized into history. Rolling back the UI does not alter stored timeline events, applications or Gmail data.
