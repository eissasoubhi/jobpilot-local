# JobPilot agent instructions

These instructions apply to the whole repository. They are the default operating rules for Léa and any compatible coding agent working on JobPilot.

## Product mission

JobPilot is a local-first job-search workspace that discovers and normalizes job offers, ranks them against the candidate profile, prepares application material, tracks applications and recruiter conversations, and exposes auditable synchronization diagnostics.

Optimize for correctness, user control, privacy, source compliance, explainability, and production readiness before feature volume.

## Expert roles

Use the repository expert roles as mandatory review lenses when their domain is involved:

- **Product Design Expert** — `docs/agents/product-design-expert.md`: invoke for design-system, UX, UI, accessibility, responsive behavior, interaction, user-facing information architecture, and visual consistency work. For #246 and any substantial user-facing change, perform the product/UX review before implementation and review the resulting implementation again before merge.
- **SEO & AI Search Expert** — `docs/agents/seo-ai-search-expert.md`: invoke for any intentionally public/indexable surface, public information architecture, metadata, structured data, performance affecting discoverability, content intended for organic acquisition, or search/answer-engine visibility. This includes classic SEO plus AEO/GEO/AI Search Optimization. Never make private workspace or candidate data public for SEO.

These are expert perspectives, not permission to bypass product, privacy, accessibility, source-compliance, test, or manual gates. When both apply (for example a future public landing page), perform both reviews.

## Autonomous development workflow

1. Before changing code, inspect current `main`, open pull requests, relevant issues, recent GitHub Actions runs, and the code touched by the proposed lane.
2. Work on at most three isolated safe lanes at once. Lanes must not edit the same behavior or create competing implementations.
3. Advance an existing PR before opening a duplicate. If `main` moved, synchronize or rebuild the branch before merge.
4. Keep incomplete, risky, stale, or manually blocked work as draft.
5. Never weaken acceptance criteria or tests merely to make CI green. Fix the product or fix an incorrect test expectation with evidence.
6. Merge only when the PR is complete, synchronized with current `main`, and all four required gates are green on the exact head:
   - Backend tests
   - Frontend tests/build
   - Docker Compose configuration
   - Chromium end-to-end tests
7. After a merge advances `main`, re-check active branches for drift before merging the next lane.
8. Report only meaningful merges, material findings, or blockers that require user action. Otherwise continue without noisy status messages.

## Branch and PR discipline

- Use one concern per branch/PR.
- Prefer small reversible PRs over broad rewrites.
- Do not mix product behavior, unrelated refactors, branding changes, and infrastructure changes unless they are inseparable.
- PR descriptions must state the problem, behavior change, safety implications, tests, and remaining manual gates.
- Do not merge a PR just because it is technically mergeable by GitHub.
- Do not force-push shared work unless rebuilding a stale branch is clearly safer; preserve the useful diff only.

## Safety invariants

### External applications

- Never submit an external job application merely because a score is high.
- Preserve explicit user control and existing submission safety checks.
- Do not introduce hidden auto-submit paths.
- Never retrograde submitted, in-flight, interview, follow-up, or other terminal/progressed applications during reconciliation.

### Source/platform compliance

- Prefer official APIs, public feeds, authorized email flows, and assisted browser import.
- Respect connector compliance status and robots/policy controls already modeled by the product.
- Never bypass authentication, CAPTCHA, rate limits, anti-bot protections, access controls, or platform restrictions.
- Do not add stealth automation, proxy rotation intended to evade limits, credential harvesting, or private-cookie reuse.
- Repeated 401/403/429 conditions must remain visible and safely handled rather than bypassed.

### Secrets and privacy

- Never commit API keys, OAuth tokens, credentials, personal mail contents, or other secrets.
- Use minimum required OAuth scopes and preserve token encryption/redaction behavior.
- Avoid sensitive content in technical logs.
- Preserve browser/macOS security protections; never disable quarantine or equivalent protections to satisfy a privacy feature.
- Manual platform-specific privacy gates remain mandatory when an issue explicitly requires them.

## Ranking invariants

The ranking engine must keep hard eligibility filters separate from soft ranking gaps.

- A role-defining mandatory primary technology outside the candidate profile is a hard rejection when evidence is strong.
- Strong evidence includes title technologies, explicit AI missing must-haves, and strong non-optional role/requirement evidence in the description.
- Slash (`/`) and ampersand (`&`) in a title describe stack composition by default, not alternatives.
- Alternatives are scoped to the same missing technology. Example: `Angular or React` can satisfy the frontend requirement but must never waive a separate mandatory Java backend gap.
- `Java or PHP` is eligible when the candidate supports PHP.
- Optional, nice-to-have, legacy, secondary, database, cloud, infrastructure, and tooling gaps remain ranking gaps unless a separate explicit business rule says otherwise.
- Hard rejection must set/reconcile `REJECTED_BY_FILTER`, remove still-pending applications from Review Queue, and prevent preparation/automatic submission.
- Pending eligibility reconciliation may inspect the whole active Review Queue.
- The persisted score recalculation command remains limited to the rolling one-month window unless an explicitly approved issue changes that contract.
- Preserve adaptive reaction ranking behavior and deterministic ordering/tie-breakers.
- Preserve remote/hybrid/on-site and location semantics already covered by regression fixtures.

## Review Queue invariants

- Preserve the 30-day Review Queue eligibility rule.
- Preserve immediate decision UX with Undo.
- `Déjà postulé` records an external application without triggering an external submission and remains Undo-compatible.
- Pending applications rejected by a hard filter must disappear from Review Queue; progressed/terminal applications must not be retrograded.
- Offers and Review Queue must retain the same canonical business ordering where designed to do so.

## Synchronization and connector UX

- Manual selective synchronization must execute only the explicitly selected eligible connectors without changing their global enabled state.
- Selected connector scope must survive refresh/navigation for the active run.
- Progress and result rows must represent only the connectors actually targeted.
- Disabled, unconfigured, or policy-blocked connectors may remain visible but must not become selectable/runnable through a shortcut.
- Retry-after-partial-run must relaunch only failed connectors and must not replay successful ones.
- Keep Gmail diagnostics explicit: messages found, already known/handled, imported, failed, offers extracted, and action-required associations where available.
- A `0 offers` result must not hide useful email-processing diagnostics.
- Prefer compact result summaries with expandable technical detail.

## Inbox invariants

- Platform alerts/digests are not recruiter opportunities.
- Human informational acknowledgements are not actionable merely because they mention recruitment context.
- `À traiter` means user action is currently required, not simply unread.
- A user reply resolves the prior actionable thread state; a new actionable recruiter response may re-open it.
- Manual mark-as-handled behavior must remain possible and persistent.
- Classification should prefer deterministic evidence such as sender/domain, headers, threading/directionality, and known platform patterns before broad keyword heuristics.

## Branding

The current product name remains **JobPilot** until a dedicated branding decision is explicitly approved. Do not opportunistically rename UI text, domains, package metadata, event names, repository paths, or docs as part of unrelated PRs.

When a rename is approved, handle it as a dedicated migration with an inventory of user-visible branding, local domains, extension metadata, documentation, environment/config references, event names, compatibility aliases, and rollback considerations.

## Required validation commands

Mirror CI locally when practical.

Backend database commands below are valid only after explicitly configuring a dedicated isolated test database. Never run test migrations, bootstrap, schema validation, fixture cleanup, or PHPUnit with the real/local development or production database.

### Backend

```bash
cd api
composer install --no-interaction --prefer-dist --no-progress
php bin/console lint:yaml --parse-tags config
php bin/console lint:container
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
php bin/phpunit
```

### Frontend

```bash
cd web
npm install --no-audit --no-fund
npm run typecheck
npm run test:unit
npm run build
```

### Docker Compose

```bash
docker compose -f docker-compose.yml config --quiet
docker compose -f docker-compose.yml -f docker-compose.override.yml config --quiet
```

### Chromium/E2E

Use the repository CI/runtime contract and `npm run test:e2e` in the isolated Playwright browser environment. Do not replace browser E2E evidence with unit tests for merge approval.

## Test database isolation

- Automated tests, integration tests, fixture/bootstrap routines, and E2E tests must never read from, write to, migrate, truncate, reset, seed, or clean the user's real/local development database or any production database.
- Use a dedicated isolated database for every test run (`jobpilot_test`, `jobpilot_e2e`, or an equivalent disposable test database).
- Before any test command that can mutate data or schema, verify that the effective `DATABASE_URL` points to the dedicated test database. Never rely on an implicit fallback to the development database.
- If test-database isolation cannot be proven, fail/stop the test run rather than continuing against another database.
- Test setup and teardown may clean only data inside the isolated test database. Never implement broad cleanup that could reach real user data.
- Running the HTTP application with `APP_ENV=dev` for E2E compatibility is acceptable only when its `DATABASE_URL` is explicitly pinned to the isolated E2E database.
- CI is the reference isolation model: backend tests use `jobpilot_test`; Chromium/E2E uses `jobpilot_e2e`.

## Data and migrations

- Prefer backward-compatible changes.
- Migrations must be deterministic and safe against existing local data.
- Do not silently destroy user profile, CV, application, recruiter, message, score, or synchronization history.
- Maintenance/reconciliation commands must be idempotent where practical and must document their scope.

## Architecture and quality

- Keep domain/business rules out of UI components when they belong in backend/domain services.
- Prefer typed contracts and reusable components over duplicated ad-hoc logic.
- Maintain the modular-monolith direction; do not introduce distributed infrastructure without a demonstrated operational need.
- Preserve observability and auditable reasons for ranking, filtering, synchronization, and message classification.
- Add regression tests for every real production/local bug fixed.
- Avoid fragile snapshots and broad keyword heuristics when deterministic fixtures can prove the behavior.

## Manual gates

Automation does not override an issue that requires physical/manual evidence. Example: downloaded-file provenance on real Chromium/macOS must be validated in Finder if the acceptance criteria require it. Keep the PR draft/blocked until that evidence exists, then resynchronize with current `main` and rerun all four CI gates.

## Scheduler/autonomy behavior

On each autonomous development cycle:

1. Inspect `main`, active PRs/issues, and recent CI.
2. Fix actionable failures in existing lanes first.
3. Select up to three independent lanes by priority and risk.
4. Apply the Product Design Expert and/or SEO & AI Search Expert review whenever the selected lane falls within those domains.
5. Avoid overlapping edits and duplicate PRs.
6. Keep safety/manual gates intact.
7. Merge only fully validated synchronized work.
8. Re-evaluate priorities after every merge because `main` has changed.

If no safe actionable work exists, do not fabricate work or weaken a blocker. Record the real blocker and wait for the required external/user evidence.
