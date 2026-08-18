# Public repository safety

JobPilot is designed so that the repository can be public while real candidate data and credentials stay local.

## What may be committed

- application source code;
- documentation;
- `.env.example` with empty or example values only;
- `initial-data/profile.json` containing a fictional demo profile;
- generic bootstrap settings;
- tests and fixtures containing fictional data.

## What must stay private

Never commit real candidate data, CVs, recruiter messages, API tokens, OAuth credentials, cookies, browser sessions, generated applications, database dumps, or production `.env` files.

Use `data/private/` and local environment files for machine-specific or user-specific values. The repository `.gitignore` excludes runtime-private files under `data/private/` as well as `.env` files other than `.env.example`.

## GitHub Actions

Standard CI jobs are expected to run on ephemeral GitHub-hosted Linux runners. Workflows must not depend on persistent files, services, credentials, or caches from a personal self-hosted machine.

Live source smoke tests must remain bounded and must respect the source policy documented by JobPilot. Public visibility does not grant permission to bypass authentication, robots rules, rate limits, CAPTCHAs, terms of use, or other access controls.

## Before changing an existing private repository to public

Sanitizing the current working tree is not enough if personal or secret data was committed previously. Audit and, where necessary, rewrite Git history and old branches/tags before changing repository visibility. Rotate any credential that was ever committed, even if it was later deleted.
