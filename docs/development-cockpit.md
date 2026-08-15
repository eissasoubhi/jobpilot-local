# Development Cockpit

`Aissa Development Cockpit` is the cross-repository GitHub Project used as the live source of truth for projects developed with ChatGPT.

## Tracked repositories

- `eissasoubhi/PRTruth`
- `eissasoubhi/mpc`
- `eissasoubhi/binnace-bot`
- `eissasoubhi/ai-saas-factory`
- `eissasoubhi/jobpilot-local`

The public `eissasoubhi/jobpilot` Pages repository is intentionally excluded because `jobpilot-local` is the active development repository.

## Status model

1. `Backlog`
2. `Todo`
3. `In Progress`
4. `PR Open`
5. `CI / Review`
6. `Blocked`
7. `Done`

Open issues default to `Todo`. Open pull requests are classified from draft/CI state. Closed issues and merged/closed pull requests become `Done` automatically.

The labels `status:backlog`, `status:in-progress` and `status:blocked` are created automatically in every tracked repository.

## ChatGPT tracking metadata

Issues and pull requests can expose optional hidden metadata in their body. The sync workflow reads these markers and updates the corresponding Project fields.

```html
<!-- cockpit:status=In Progress -->
<!-- cockpit:chantier=Release & distribution -->
<!-- cockpit:next=Create and validate the v0.1.0 release -->
<!-- cockpit:progress=70 -->
```

`status` must be one of the seven statuses above. `progress` must be between 0 and 100. If `progress` is omitted, issue task-list completion is used when a Markdown checklist exists.

This lets ChatGPT update GitHub Issues/PRs through its normal GitHub integration while the scheduled workflow propagates the state into GitHub Projects.

## One-time activation

GitHub's repository `GITHUB_TOKEN` cannot access user Projects. Create a classic personal access token with these scopes:

- `repo`
- `project`

Store it only as the `DEVELOPMENT_COCKPIT_TOKEN` Actions secret in `eissasoubhi/jobpilot-local`. Never commit or paste the token into this repository.

After the secret exists, manually run **Development Cockpit Sync** once from the Actions tab. The workflow then runs every 15 minutes on the self-hosted runner.

The first successful run creates the private `Aissa Development Cockpit` Project, links all tracked repositories, creates its fields/views/status options, creates the tracking labels and imports all currently open Issues and pull requests.

## Views

The bootstrap creates:

- `Current Work` — global board
- `All Work` — global table
- `Pull Requests` — PR-only table
- one board per tracked repository

## Adding a future ChatGPT project

Add the repository full name to `.github/development-cockpit.json`. The next successful sync links the repository, creates its tracking labels and imports its open work.

## Safety

- Project is private because it aggregates private repositories.
- No application secrets are read or copied into the Project.
- No pull request is merged by this workflow.
- No issue is closed by this workflow.
- CI state is read only to derive the visual status.
- Real work remains represented by GitHub Issues and pull requests; the Project is a synchronized view, not a second roadmap database.
