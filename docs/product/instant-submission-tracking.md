# Instant submission tracking

When the user has actually submitted an application on the original platform, the **J’ai envoyé la candidature** action updates JobPilot immediately.

## Behaviour

- no browser confirmation dialog is displayed;
- the application status is changed to `SUBMITTED` through the existing application update endpoint;
- the submission timestamp is recorded by the existing backend behaviour;
- JobPilot shows an inline success notice after the update;
- this action only records tracking state and never submits anything to the external platform.

The external application remains a deliberate user action. JobPilot does not bypass authentication, CAPTCHA, platform controls, quotas or source policies.

## Why

The previous confirmation dialog added an unnecessary second click after the user had already explicitly selected **J’ai envoyé la candidature**. Removing it makes the tracking workflow faster while preserving the same backend state transition and external-submission boundaries.
