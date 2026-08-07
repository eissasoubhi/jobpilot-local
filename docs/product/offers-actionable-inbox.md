# Actionable Offers inbox

The default `Offres` view is an actionable inbox for opportunities that still need a decision or action.

## V1 behavior

- `À traiter` is the default view.
- Applications already recorded as `SUBMITTED` are excluded from this default inbox.
- `Envoyées` shows only offers whose application is currently `SUBMITTED`.
- Existing source and job-status filters continue to apply inside each inbox view.
- Marking an application submitted from the Offers review drawer updates the parent workspace immediately, so the offer leaves `À traiter` without a reload.
- Reloading keeps the same classification because the view derives from the persisted application status returned by `/applications`.

## Scope boundary

This PR handles only the submitted/not-submitted split requested for V1. Ignored and archived decisions are separate follow-up slices so their product states and recovery behavior can stay explicit and testable.

## Safety

The inbox filter performs no external request and does not submit, delete, archive or mutate source-platform data. It only classifies already loaded JobPilot data. External application submission remains an explicit user action on the original platform.
