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
- shows a compact **Environment & profile** comparison before the long mission description;
- distinguishes the detected primary stack, technologies shared with the configured profile, missing must-haves and other missing technologies;
- reuses already-recorded Gemini decision/confidence/primary-stack metadata when the existing score came from AI, without making a new provider call merely to render Review Queue;
- falls back to a deterministic comparison based on configured `targetJobs`/`skills` and the actual offer text when reusable AI metadata is absent;
- keeps the job score and all available score reasons visible at the bottom of the card;
- keeps two primary decisions permanently visible at the bottom of the viewport: red `Ne correspond pas` and green `Envoyée`;
- persists `Ne correspond pas` as `IGNORED_NOT_MATCH` and `Envoyée` as `SUBMITTED`;
- immediately removes the current application from the slider after either primary decision succeeds and shows the next `READY_TO_SUBMIT` item;
- keeps source-platform, CV and manual status actions secondary inside the card;
- keeps `Précédente` and `Suivante` as compact secondary controls between the two primary decisions;
- supports `ArrowLeft` and `ArrowRight` keyboard navigation when focus is not inside an input, select, textarea, button or link;
- shows queue progress in the bottom decision bar;
- updates the local queue immediately when any persisted status changes;
- automatically keeps the next ready item selected when the current application leaves `READY_TO_SUBMIT`;
- wraps to the first remaining ready item when the decision was made on the last queue item.

The top of the page is intentionally compact: the oversized page description and large return action were replaced by a small title/count strip and a compact `← Offres` link. The saved vertical space belongs to the mission card.

The Review Queue intentionally does not render the prepared application message as the primary review content. That material remains available through the existing Offers/Application editing flows. The Review Queue is optimized for the decision about the job itself: mission context, matching quality, contract and next action.

## Technology comparison

The comparison is deliberately read-only and cheap to render. The application API computes it locally from the persisted job plus the current configured matching criteria.

When the persisted score reasons already contain Gemini metadata, JobPilot reuses:

- the AI decision and confidence;
- the primary stack detected by AI;
- the list of missing main prerequisites already recorded in the score explanation.

No Gemini request is triggered by opening Review Queue. The existing AI cache/quota pipeline is therefore not consumed merely for display.

When no reusable AI metadata exists, the comparison is labelled **Analyse locale**. The deterministic fallback detects a maintained technology vocabulary in the offer and profile criteria. Technologies explicitly present in the job title are treated as the strongest deterministic requirements; technologies found only elsewhere in the description are shown as softer gaps rather than being invented as mandatory requirements.

The candidate side is grounded only in configured `targetJobs` and `skills`. The comparison never claims that the candidate knows a technology which is absent from those configured criteria.

## Decision workflow

The expected fast path is:

1. read the mission, technology comparison and matching explanation;
2. if the application was submitted externally, click the green `Envoyée` decision;
3. otherwise, if the job does not fit the profile, click the red `Ne correspond pas` decision;
4. after the persisted status update succeeds, JobPilot automatically shows the next ready application.

`Précédente` and `Suivante` remain available for browsing but are visually secondary because they do not complete the review workflow.

## Status interaction

The manual status selector uses a two-step interaction. Choosing a value changes only the local selection; the persisted application badge does not change until `Appliquer` succeeds. This avoids showing an unsaved state as if it were already recorded.

The primary bottom decisions are explicit shortcuts for the two most frequent final Review Queue outcomes. Once either action succeeds, or any other saved status leaves `READY_TO_SUBMIT`, the application immediately leaves this slider.

## Safety and compatibility

This behavior adds no database schema, connector or external-submission behavior. The controls reuse the existing application PATCH contract and preserve the same user-driven tracking semantics. `Envoyée` records that the user already submitted the application; it does not submit to an external platform. Keyboard navigation never fires while the user is interacting with a form control. The technology comparison makes no external AI call and never bypasses the AI quota/cache layer. The change does not bypass authentication, CAPTCHA, quotas, robots/compliance policy or source restrictions. Existing Offers and Applications pages remain available.

## Follow-up slices

V1 can next add explicit queue prioritization and additional safe workflow actions after this decision surface is stable and tested in normal use.
