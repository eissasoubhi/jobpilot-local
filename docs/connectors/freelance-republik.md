# Freelance Republik

## Decision

Reviewed on **2026-08-29**.

JobPilot classifies Freelance Republik as `EMAIL_OR_EXTENSION_ONLY` for background acquisition.

No scheduled HTTP scraper, browser scraper, private-session automation, undocumented endpoint, CAPTCHA bypass, proxy rotation, or stealth mode is permitted.

## Why

Freelance Republik currently exposes mission pages publicly on the web, so the source is technically visible without an authenticated session. That technical visibility does not by itself authorize automated collection.

The current Freelance Republik general terms explicitly protect the Site, Application, their contents and databases. They prohibit Members from reproducing, using, copying, publishing or otherwise exploiting protected elements without prior written authorization. They also state that extraction, integration, compilation or commercial use of information from accessible databases, as well as the use of software, robots, data-mining systems and other data-collection tools, is strictly prohibited.

Given those restrictions, JobPilot must not infer a right to run scheduled scraping from the fact that mission pages are public.

## Allowed JobPilot paths

Until Freelance Republik publishes a reusable official feed/API or grants written authorization applicable to JobPilot, the permitted acquisition paths are:

- recognized job-alert e-mails received by the user;
- voluntary browser-assisted import of a page the user has opened;
- a future official API/feed/partner channel, only after its terms, credentials and quotas are documented and implemented conservatively.

## References reviewed

- https://www.freelancerepublik.com/conditions-generales
- https://www.freelancerepublik.com/missions

## Re-review trigger

Re-open this decision only if Freelance Republik publishes an official candidate-facing jobs API/feed with reusable terms, or provides explicit written authorization for the intended collection mode.
