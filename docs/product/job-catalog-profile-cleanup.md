# Selective profile cleanup

JobPilot provides a selective cleanup action in **Paramètres** to remove only offers that are already known, or can be safely confirmed, as outside the configured candidate profile.

## Deletion rules

An offer can be deleted when at least one of these conditions is true:

- its linked application is explicitly marked `IGNORED_NOT_MATCH`;
- its stored matching reasons contain `NO_MATCH` with at least 85% AI confidence and concrete mismatch evidence (score below the configured matching threshold, missing must-haves, or explicit conflicts);
- the existing `AiOfferIntakeFilter` returns the same high-confidence `NO_MATCH` decision when the stored analysis is not sufficient.

`MATCH`, `REVIEW`, low-confidence `NO_MATCH`, provider failures, quota exhaustion, disabled AI, and ambiguous results all fail open: the offer is kept.

## Protected application history

The cleanup is intentionally narrower than the full catalog reset. If an offer has any linked application status outside this disposable set, it is protected and not deleted:

- `DRAFT`
- `MISSING_CV`
- `READY_TO_SUBMIT`
- `IGNORED_NOT_MATCH`

This means submitted applications, pending submissions, interviews, recruiter replies, rejections, confirmations and future treated statuses remain in the database even if the offer would otherwise look outside the current profile.

## AI and quota behavior

The cleanup reuses persisted AI score reasons first. Only offers without a reusable safe rejection are passed to the existing AI intake filter. That filter already uses the configured Gemini cache and quota manager. If AI cannot run, the offer is kept.

The cleanup does not resynchronize sources and does not change connector enablement, authentication, robots.txt handling, source quotas, request delays or CAPTCHA policy.

## Concurrency and confirmation

The API requires the explicit confirmation token `CLEAN_NO_MATCH` and uses the same `job-search-sync.lock` as normal synchronization and full catalog reset. If synchronization is active, cleanup returns a busy response and deletes nothing.

The full **Supprimer et resynchroniser** action remains available separately for users who intentionally want to erase the entire catalog and linked application history.
