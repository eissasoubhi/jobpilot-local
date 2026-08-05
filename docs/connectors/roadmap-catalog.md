# Planned connector catalog

This catalog distinguishes operational connectors from product-roadmap sources.

Roadmap entries are descriptive only. They are not registered in the backend, cannot be enabled, and cannot trigger synchronization. The frontend source of truth is `web/lib/connector-roadmap.ts`.

## Statuses

- `PLANNED`: an authorized technical channel is identified, but implementation or required access is still pending.
- `UNDER_REVIEW`: scheduled collection remains blocked until the source-specific technical and compliance review is complete.
- `EMAIL_OR_EXTENSION_ONLY`: JobPilot may use recognized Gmail alerts or a user-triggered browser import, but must not automate login or background scraping.

## Current roadmap

| Source | Status | Intended channel |
| --- | --- | --- |
| France Travail | `PLANNED` | Official API after access and credentials are validated |
| Free-Work | `EMAIL_OR_EXTENSION_ONLY` | Gmail alerts or user-triggered extension import |
| APEC | `UNDER_REVIEW` | Gmail alerts or user-triggered extension import while review is pending |
| HelloWork | `UNDER_REVIEW` | Gmail alerts or user-triggered extension import while review is pending |
| Welcome to the Jungle | `UNDER_REVIEW` | Gmail alerts or user-triggered extension import while review is pending |
| LinkedIn | `EMAIL_OR_EXTENSION_ONLY` | Gmail alerts or user-triggered extension import |
| Indeed | `EMAIL_OR_EXTENSION_ONLY` | Gmail alerts or user-triggered extension import |
| LesJeudis | `UNDER_REVIEW` | Gmail alerts or user-triggered extension import while review is pending |
| Le Hibou | `UNDER_REVIEW` | Gmail alerts or user-triggered extension import while review is pending |

## Rules

Adding a source to this catalog does not authorize collection. A real scheduled connector still requires its own policy, contract tests, operational limits, source documentation, and an explicit allowed or authorized-only compliance decision.

The catalog deliberately contains no secrets, API credentials, selectors, cookies, or instructions for bypassing authentication, CAPTCHA, quotas, or source restrictions.
