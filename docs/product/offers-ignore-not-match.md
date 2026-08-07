# Offers — non-matching decision

V1 lets the user explicitly record that a reviewed offer does not match the profile even when automatic scoring considered it relevant.

## Behavior

- The Offers review drawer exposes `Ne correspond pas à mon profil`.
- The decision persists the local application status `IGNORED_NOT_MATCH` through the existing application PATCH endpoint.
- The offer immediately leaves the default `À traiter` inbox because that view excludes both submitted and explicitly ignored applications.
- `Ignorées` shows only offers explicitly recorded as `IGNORED_NOT_MATCH`, so the decision remains recoverable directly inside the Offers workspace.
- Existing source and job-status filters continue to apply inside the ignored view.
- No offer, source occurrence, prepared CV, message or other local data is deleted.
- The decision performs no external platform request and does not submit, withdraw or modify an application on a source website.

## Matching feedback boundary

`IGNORED_NOT_MATCH` is trustworthy manual feedback, but this V1 slice does not silently alter scoring rules or retrain matching. Any use of this feedback to change scoring must remain explicit, testable and traceable.

## Compatibility

No database migration or API contract change is required: application status is already persisted as a string and the existing PATCH workflow accepts local tracking states. The `Ignorées` view is derived only from persisted JobPilot data.
