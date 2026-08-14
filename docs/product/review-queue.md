# Review Queue — V1

## Goal

Provide a focused way to review applications that are genuinely ready to submit, one at a time, without changing the underlying application workflow.

## Current behavior

The Review Queue is available at `/offres/review` and from the main navigation.

It currently:

- loads the existing JobPilot applications;
- keeps **only** applications whose persisted status is `READY_TO_SUBMIT`;
- excludes every already-treated or non-ready status such as `SUBMITTED`, `INTERVIEW`, `REJECTED`, `OFFER_RECEIVED`, `RECRUITER_REPLIED`, `SUBMISSION_FAILED`, `OFFER_UNAVAILABLE` and `IGNORED_NOT_MATCH`;
- shows one application at a time in a full-width decision card;
- keeps the complete job description visible immediately instead of showing the prepared application message as the main content;
- exposes title, company, location, work mode, source, application state and contract type at first glance;
- shows how long ago the offer was published when the source provides `publishedAt`;
- falls back to **Détectée il y a…** when only JobPilot's discovery timestamp is known, so discovery time is never presented as a publication date;
- exposes the exact publication/discovery timestamp as the tooltip of the relative-age label;
- flags offers published at least seven days ago with an **Offre ancienne** warning;
- explicitly identifies `CDI` versus `Non-CDI` while also showing the original contract label;
- exposes CV/cover-letter/compensation readiness without occupying the page with the prepared message body;
- shows a compact **Environment & profile** comparison before the long mission description;
- distinguishes the detected primary stack, technologies shared with the configured profile, missing must-haves and other missing technologies;
- reuses already-recorded Gemini decision/confidence/primary-stack metadata when the existing score came from AI, without making a new provider call merely to render Review Queue;
- falls back to a deterministic comparison based on configured `targetJobs`/`skills` and the actual offer text when reusable AI metadata is absent;
- keeps the job score and all available score reasons visible at the bottom of the card;
- keeps two primary decisions permanently visible at the bottom of the viewport: red `Ne correspond pas` and green `Envoyée`;
- persists `Ne correspond pas` as `IGNORED_NOT_MATCH` and `Envoyée` as `SUBMITTED`;
- exposes **Offre indisponible** as a separate secondary action next to the source-platform action;
- after explicit confirmation, persists the application as `OFFER_UNAVAILABLE` and the job offer as `UNAVAILABLE`;
- immediately removes an unavailable offer from the Review Queue without incorrectly classifying it as a profile mismatch;
- immediately removes the current application from the slider after either primary decision succeeds and shows the next `READY_TO_SUBMIT` item;
- keeps source-platform, availability, CV and manual status actions secondary inside the card;
- keeps `Précédente` and `Suivante` as compact secondary controls between the two primary decisions;
- supports `ArrowLeft` and `ArrowRight` keyboard navigation when focus is not inside an input, select, textarea, button or link;
- shows queue progress in the bottom decision bar;
- updates the local queue immediately when any persisted status changes;
- automatically keeps the next ready item selected when the current application leaves `READY_TO_SUBMIT`;
- wraps to the first remaining ready item when the decision was made on the last queue item.

The top of the page is intentionally compact: the oversized page description and large return action were replaced by a small title/count strip and a compact `← Offres` link. The saved vertical space belongs to the mission card.

The Review Queue intentionally does not render the prepared application message as the primary review content. That material remains available through the existing Offers/Application editing flows. The Review Queue is optimized for the decision about the job itself: mission context, matching quality, contract, freshness and next action.

## Offer freshness

JobPilot uses the source publication timestamp when it is available. The relative label is intentionally human-readable, for example:

- `Publiée il y a 35 min`;
- `Publiée il y a 5 h`;
- `Publiée il y a 3 jours`;
- `Publiée il y a 2 semaines`.

An offer at least seven days old receives the warning **Offre ancienne**. This warning is informational and does not reject or remove the offer automatically.

Some sources do not expose a trustworthy publication date. In that case, JobPilot uses the persisted `discoveredAt` timestamp and displays **Détectée il y a…**. It never silently substitutes discovery time for publication time. If neither timestamp can be used, the interface displays **Publication inconnue**.

## Unavailable-offer workflow

**Offre indisponible** is deliberately separate from **Ne correspond pas**:

- `IGNORED_NOT_MATCH` means the user evaluated the offer and decided it does not fit the profile;
- `OFFER_UNAVAILABLE` means the user could no longer access/apply to the offer;
- the associated job receives `UNAVAILABLE` so other local views can distinguish it from a rejected matching decision.

The action is placed next to **Ouvrir la plateforme**, because the expected workflow is to verify the source and then report that the posting has disappeared. A confirmation is required before persistence.

The action only records local state. JobPilot does not send anything back to the job board, delete the source occurrence or claim that every occurrence of a multi-source canonical offer has disappeared. Future connector health work may independently verify source availability, but this manual action does not perform an automatic HTTP availability check.

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

1. read the publication age, mission, technology comparison and matching explanation;
2. optionally open the source platform to verify the offer;
3. if the source says the offer is gone, click **Offre indisponible** and confirm;
4. if the application was submitted externally, click the green `Envoyée` decision;
5. otherwise, if the job does not fit the profile, click the red `Ne correspond pas` decision;
6. after the persisted status update succeeds, JobPilot automatically shows the next ready application.

`Précédente` and `Suivante` remain available for browsing but are visually secondary because they do not complete the review workflow.

## Status interaction

The manual status selector uses a two-step interaction. Choosing a value changes only the local selection; the persisted application badge does not change until `Appliquer` succeeds. This avoids showing an unsaved state as if it were already recorded.

The primary bottom decisions are explicit shortcuts for the two most frequent final Review Queue outcomes. The unavailable action is separate because it describes source availability, not matching quality or submission. Once any of these saved statuses leaves `READY_TO_SUBMIT`, the application immediately leaves this slider.

## Safety and compatibility

This behavior adds no database schema, connector or external-submission behavior. Publication age uses timestamps already returned by the job API. The unavailable action uses string statuses already supported by the existing entities and therefore needs no migration.

`Envoyée` records that the user already submitted the application; it does not submit to an external platform. **Offre indisponible** records a user-confirmed local observation; it neither calls nor modifies the external platform. Keyboard navigation never fires while the user is interacting with a form control. The technology comparison makes no external AI call and never bypasses the AI quota/cache layer. The change does not bypass authentication, CAPTCHA, quotas, robots/compliance policy or source restrictions. Existing Offers and Applications pages remain available.

## Follow-up slices

V1 can next add explicit queue prioritization and safe automated availability hints from connectors that provide an official status field, while keeping any destructive or externally observable action user-controlled.
