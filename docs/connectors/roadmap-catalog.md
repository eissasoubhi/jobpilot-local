# Connector roadmap catalog

This catalog distinguishes operational connectors from product-roadmap sources.

Roadmap entries are descriptive only. They are not registered in the backend, cannot be enabled, and cannot trigger synchronization. The frontend source of truth is `web/lib/connector-roadmap.ts`.

The table below is covered by a parity test. Any change to a source code, status, or intended mode must be made in the frontend source first, then reflected here in the same pull request.

## Statuses

- `OPERATIONAL`: the connector is registered in the backend and can run when its required configuration is present.
- `PLANNED`: an authorized technical channel is identified, but implementation or required access is still pending.
- `UNDER_REVIEW`: scheduled collection remains blocked until the source-specific technical and compliance review is complete.
- `EMAIL_OR_EXTENSION_ONLY`: JobPilot may use recognized Gmail alerts or a user-triggered browser import, but must not automate login or background scraping.

## Current roadmap

<!-- connector-roadmap:start -->
| Source | Code | Status | Intended modes |
| --- | --- | --- | --- |
| LinkedIn | `linkedin` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Malt | `malt` | `UNDER_REVIEW` | — |
| Free-Work | `free-work` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Apec | `apec` | `PLANNED` | `XML`, `GMAIL`, `EXTENSION` |
| Collective.work | `collective-work` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Crème de la Crème | `creme-de-la-creme` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| FreelanceRepublik | `freelance-republik` | `UNDER_REVIEW` | — |
| Comet | `comet` | `UNDER_REVIEW` | — |
| Cherry Pick | `cherry-pick` | `UNDER_REVIEW` | — |
| LeHibou | `le-hibou` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Mindquest | `mindquest` | `UNDER_REVIEW` | — |
| WeLoveDevs | `we-love-devs` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Sept Lieues | `sept-lieues` | `UNDER_REVIEW` | — |
| Jean-Michel.io | `jean-michel` | `UNDER_REVIEW` | — |
| Welcome to the Jungle | `welcome-to-the-jungle` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Cadremploi | `cadremploi` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| HelloWork | `hellowork` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Jobijoba | `jobijoba` | `PLANNED` | `API` |
| EURES | `eures` | `PLANNED` | `API` |
| Freelance-Informatique | `freelance-informatique` | `UNDER_REVIEW` | — |
| Indeed | `indeed` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Adzuna | `adzuna` | `OPERATIONAL` | `API` |
| Kicklox | `kicklox` | `UNDER_REVIEW` | — |
| Talent.com | `talent-com` | `PLANNED` | `API` |
| SmartRecruiters | `smartrecruiters` | `OPERATIONAL` | `API` |
| GetYourJob | `getyourjob` | `UNDER_REVIEW` | — |
| Le Studio Tech | `le-studio-tech` | `OPERATIONAL` | `SCRAPING_HTTP` |
| Meteojob | `meteojob` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
| Michael Page | `michael-page` | `UNDER_REVIEW` | — |
| France Travail | `france-travail` | `OPERATIONAL` | `API` |
| LesJeudis | `lesjeudis` | `EMAIL_OR_EXTENSION_ONLY` | `GMAIL`, `EXTENSION` |
<!-- connector-roadmap:end -->

## Rules

Adding a source to this catalog does not authorize collection. A real scheduled connector still requires its own policy, contract tests, operational limits, source documentation, and an explicit allowed or authorized-only compliance decision.

The catalog deliberately contains no secrets, API credentials, selectors, cookies, or instructions for bypassing authentication, CAPTCHA, quotas, or source restrictions.
