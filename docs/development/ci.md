# Continuous integration

The normal pull-request CI is defined in `.github/workflows/ci.yml`.

It runs four independent validation jobs:

- backend tests and Symfony/Doctrine validation;
- frontend type-check, unit tests, and production build;
- Docker Compose configuration validation;
- Chromium end-to-end tests.

These jobs intentionally do not depend on the temporary audit-bundle automation. A failure to start one CI job must not prevent the other validation jobs from reporting their own result.

Normal CI targets the repository self-hosted runner through the labels `self-hosted` and `jobpilot`. The registered JobPilot runner is a Linux ARM64 machine with Docker available, which is required for PostgreSQL service containers. The runner must be online before a pull request can complete CI.

The audit-bundle mechanism lives in `.github/workflows/apply-bundle.yml` and is isolated from normal pull-request CI. It retains write access because its purpose is to apply and commit an explicitly prepared bundle on its dedicated branch. Normal CI only receives read access to repository contents.

A pull request is considered safe to merge only after every required normal CI check is green. Audit-bundle automation is not a substitute for backend, frontend, Compose, or browser tests.
