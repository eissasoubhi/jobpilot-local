# Review Queue — V1

## Goal

Provide a focused way to review already prepared, still-actionable applications one at a time without changing the underlying application workflow.

## First slice

The Review Queue is available at `/offres/review` and from the main navigation.

It currently:

- loads the existing JobPilot applications;
- keeps only applications that are still in the actionable Offers inbox;
- excludes applications already marked `SUBMITTED` or `IGNORED_NOT_MATCH`;
- shows one prepared application at a time;
- provides explicit `Précédente` and `Suivante` navigation;
- reuses the existing Offers review drawer for editing, tracking, source navigation and user decisions;
- updates the local queue immediately when an application status changes.

## Safety and compatibility

This slice adds no database schema, API contract, connector or external-submission behavior. It does not bypass authentication, CAPTCHA, quotas, robots/compliance policy or source restrictions. Existing Offers and Applications pages remain available.

## Follow-up slices

After this interaction model is stable, V1 can add automatic advancement after decisions, explicit queue prioritization, remaining safe actions such as archive, and finally keyboard shortcuts. Those behaviors are intentionally not bundled into this PR.
