# Offers review drawer

The Offers workspace provides an in-place review drawer for each prepared application.

## Goal

Review and adjust the important context of an opportunity without leaving the Offers page.

## Available context

The drawer displays data already loaded for the offer and its prepared application:

- title, company, location, work mode and contract type;
- matching score and existing score reasons;
- full offer description;
- selected CV and its existing local download link;
- prepared application message;
- requested cover letter when present;
- compensation answer when present;
- confirmation/reference used for local tracking;
- the original source-platform link when available.

## Editing prepared material

The review drawer reuses the existing application PATCH endpoint already used by the Applications page. The user can edit and persist:

- the prepared message;
- the requested cover letter;
- the compensation answer;
- the confirmation/reference field.

Saving keeps the current application status unchanged and updates only JobPilot. It performs no source-platform request and never submits a candidature externally.

## Tracking status

The same drawer now exposes the existing manual tracking states already available on the Applications page:

- ready to submit;
- automatic submission failed;
- submitted;
- recruiter replied;
- interview;
- rejected;
- offer received.

Changing the selection is local until `Enregistrer le statut` is clicked. Saving reuses the existing application PATCH endpoint with the current prepared fields and performs no external request. `SUBMISSION_PENDING` remains locked because the existing workflow treats it as an in-flight state that must not be manually overwritten.

## Interaction

- `Examiner` opens the drawer without a page navigation;
- `Fermer`, the backdrop, or the `Escape` key closes it;
- the drawer is exposed as an accessible modal dialog;
- `Enregistrer les modifications` persists the current prepared fields through the existing application endpoint;
- `Enregistrer le statut` persists the selected local tracking state;
- `J’ai envoyé la candidature` records the existing `SUBMITTED` tracking transition only after the user has actually submitted externally;
- applying on the external platform remains a deliberate user action through the existing source link.

## Migration boundary

This is an incremental V1 Unified Offers Workspace step. Prepared-material editing, explicit submitted tracking and the existing manual tracking statuses are now available in Offers. The Applications page remains as a fallback until the remaining workflow controls have functional parity and equivalent end-to-end coverage.

## Safety

This change does not alter connectors, source policies, authentication, CAPTCHA handling, quotas, scoring, preparation rules, database schema or external submission behavior.
