# Application activity timeline

The **Parcours des candidatures** page provides a read-only chronological view of the events already stored by JobPilot for one application.

## Included events

The timeline can display:

- application creation;
- an authorized submission attempt;
- successful submission;
- a stored submission failure;
- Gmail messages already associated with the application;
- the current application status and its last update date.

Gmail events retain their existing category, sender, subject, action-required state and direct Gmail link when available.

## Data integrity

The page does not create or infer historical records. It combines existing dates from the application with Gmail messages that already reference the same application ID.

A manual status change for which JobPilot has no source event is shown only as the current status. The interface explicitly states this limitation instead of inventing an earlier transition date.

## Safety and privacy

- read-only interface;
- no external request beyond the existing local API calls;
- no Gmail synchronization or message sending;
- no connector, credential, quota or compliance-policy change;
- no message body displayed in the timeline;
- no database migration.

## Rollback

The feature is frontend-only. Rollback consists of removing the timeline page, its navigation entry and its helper/tests. Stored application and Gmail data are unaffected.
