# Review Queue — V1

## Goal

Provide a focused way to review applications that are genuinely ready to submit, one at a time, without changing the underlying application workflow.

## Current behavior

The Review Queue is available at `/offres/review` and from the main navigation.

It currently:

- loads the existing JobPilot applications;
- keeps **only** applications whose persisted status is `READY_TO_SUBMIT`;
- excludes every already-treated or non-ready status such as `SUBMITTED`, `INTERVIEW`, `REJECTED`, `OFFER_RECEIVED`, `RECRUITER_REPLIED`, `SUBMISSION_FAILED` and `IGNORED_NOT_MATCH`;
- shows one application at a time in a full-width decision card;
- keeps the complete job description visible immediately instead of showing the prepared application message as the main content;
- exposes title, company, location, work mode, source, application state and contract type at first glance;
- explicitly identifies `CDI` versus `Non-CDI` while also showing the original contract label;
- exposes CV/cover-letter/compensation readiness without occupying the page with the prepared message body;
- keeps the job score and all available score reasons visible at the bottom of the card;
- gives the source-platform action the strongest visual priority while keeping submitted/non-match/status controls compact;
- keeps `Précédente` and `Suivante` in a fixed bottom navigation bar that remains visible while the card scrolls;
- supports `ArrowLeft` and `ArrowRight` keyboard navigation when focus is not inside an input, select, textarea, button or link;
- shows queue progress and the keyboard hint in the bottom bar;
- updates the local queue immediately when a persisted status changes;
- automatically keeps the next ready item selected when the current application leaves `READY_TO_SUBMIT`;
- wraps to the first remaining ready item when the decision was made on the last queue item.

The top of the page is intentionally compact: the oversized page description and large return action were replaced by a small title/count strip and a compact `← Offres` link. The saved vertical space belongs to the mission card.

The Review Queue intentionally does not render the prepared application message as the primary review content. That material remains available through the existing Offers/Application editing flows. The Review Queue is optimized for the decision about the job itself: mission context, matching quality, contract and next action.

## Status interaction

The status selector uses a two-step interaction. Choosing a value changes only the local selection; the persisted application badge does not change until `Appliquer` succeeds. This avoids showing an unsaved state as if it were already recorded.

`J’ai envoyé` and `Ne correspond pas` remain direct explicit decisions because they are frequent Review Queue actions. Once one of those actions succeeds, or any other saved status leaves `READY_TO_SUBMIT`, the application immediately leaves this slider.

## Safety and compatibility

This behavior adds no database schema, API contract, connector or external-submission behavior. The inline controls reuse the existing application PATCH contract and preserve the same user-driven tracking semantics. Keyboard navigation never fires while the user is interacting with a form control. The change does not bypass authentication, CAPTCHA, quotas, robots/compliance policy or source restrictions. Existing Offers and Applications pages remain available.

## Follow-up slices

V1 can next add explicit queue prioritization and additional safe workflow actions after this decision surface is stable and tested in normal use.
