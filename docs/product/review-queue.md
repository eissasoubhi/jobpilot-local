# Review Queue — V1

## Goal

Provide a focused way to review already prepared, still-actionable applications one at a time without changing the underlying application workflow.

## Current behavior

The Review Queue is available at `/offres/review` and from the main navigation.

It currently:

- loads the existing JobPilot applications;
- keeps only applications that are still in the actionable Offers inbox;
- excludes applications already marked `SUBMITTED` or `IGNORED_NOT_MATCH`;
- shows one application at a time in a full-width decision card;
- keeps the complete job description visible immediately instead of showing the prepared application message as the main content;
- exposes title, company, location, work mode, source, application state and contract type at first glance;
- explicitly identifies `CDI` versus `Non-CDI` while also showing the original contract label;
- exposes CV/cover-letter/compensation readiness without occupying the page with the prepared message body;
- keeps the job score and all available score reasons visible at the bottom of the card;
- exposes the main review actions inline: `Ne correspond pas à mon profil`, local tracking status, explicit submitted tracking, CV and source-platform links;
- provides `Précédente` and `Suivante` inside a sticky slider-style navigation bar with queue progress;
- updates the local queue immediately when an application status changes;
- automatically keeps the next actionable item selected when the current application leaves the queue after a completed decision;
- wraps to the first remaining actionable item when the decision was made on the last queue item.

The Review Queue intentionally does not render the prepared application message as the primary review content. That material remains available through the existing Offers/Application editing flows. The Review Queue is optimized for the decision about the job itself: mission context, matching quality, contract and next action.

A tracking update that leaves the application actionable does not advance the queue. Automatic advancement is tied only to a persisted status update that removes the current application from the actionable inbox, such as `SUBMITTED` or `IGNORED_NOT_MATCH`.

Automatic advancement is local Review Queue UI behavior: it never submits to, mutates, or navigates an external source on the user's behalf.

## Safety and compatibility

This behavior adds no database schema, API contract, connector or external-submission behavior. The inline controls reuse the existing application PATCH contract and preserve the same user-driven tracking semantics. It does not bypass authentication, CAPTCHA, quotas, robots/compliance policy or source restrictions. Existing Offers and Applications pages remain available.

## Follow-up slices

V1 can next add explicit queue prioritization, keyboard shortcuts and additional safe workflow actions only after those behaviors are stable and testable.
