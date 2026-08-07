# Instant submission tracking

When the user has actually submitted an application on the original platform, the **J’ai envoyé la candidature** action updates JobPilot immediately.

## Behaviour

- no browser confirmation dialog is displayed;
- the application status is changed to `SUBMITTED` through the existing application update endpoint;
- the submission timestamp is recorded by the existing backend behaviour;
- JobPilot shows an inline success notice after the update;
- this action only records tracking state and never submits anything to the external platform.

The external application remains a deliberate user action. JobPilot does not bypass authentication, CAPTCHA, platform controls, quotas or source policies.

## Offers workspace

The same tracking action is now available inside the Offers review drawer. After the user has actually submitted on the original source platform, **J’ai envoyé la candidature** records the `SUBMITTED` state without leaving Offers.

The drawer uses the existing application PATCH endpoint and sends the current prepared message, requested cover letter, compensation answer and confirmation reference together with the status transition. It performs no source-platform request.

The local card and drawer update from the returned application immediately, so the user sees the new tracking status without a page reload.

## Current migration boundary

The Applications page remains available as a fallback while editing and the rest of application tracking are progressively moved into Offers. It must not be removed until functional parity and end-to-end coverage are complete.

## Why

The previous confirmation dialog added an unnecessary second click after the user had already explicitly selected **J’ai envoyé la candidature**. Moving the same safe tracking action into Offers also removes a page navigation while preserving the existing backend state transition and external-submission boundaries.
