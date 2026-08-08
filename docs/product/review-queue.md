# Review Queue — V1

## Goal

Provide a focused way to review already prepared, still-actionable applications one at a time without changing the underlying application workflow.

## Current behavior

The Review Queue is available at `/offres/review` and from the main navigation.

It currently:

- loads the existing JobPilot applications;
- keeps only applications that are still in the actionable Offers inbox;
- excludes applications already marked `SUBMITTED` or `IGNORED_NOT_MATCH`;
- shows one prepared application at a time;
- provides explicit `Précédente` and `Suivante` navigation;
- reuses the existing Offers review drawer for editing, tracking, source navigation and user decisions;
- updates the local queue immediately when an application status changes;
- automatically keeps the next actionable item selected when the current application leaves the queue after a completed decision;
- wraps to the first remaining actionable item when the decision was made on the last queue item.

A preparation edit or tracking update that leaves the application actionable does not advance the queue. Automatic advancement is tied only to a persisted status update that removes the current application from the actionable inbox, such as `SUBMITTED` or `IGNORED_NOT_MATCH`.

Automatic advancement is local Review Queue UI behavior: it never submits to, mutates, or navigates an external source on the user's behalf.

## Safety and compatibility

This behavior adds no database schema, API contract, connector or external-submission behavior. It does not bypass authentication, CAPTCHA, quotas, robots/compliance policy or source restrictions. Existing Offers and Applications pages remain available.

## Follow-up slices

V1 can next add explicit queue prioritization, remaining safe actions such as archive, and keyboard shortcuts only after those behaviors are stable and testable.
