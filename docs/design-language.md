# JobPilot visual language

JobPilot uses one design system with several task-specific visual languages. These are not separate themes. They share the same semantic tokens, UI primitives, accessibility rules, responsive behavior, and UX writing standards.

Principle: **Calm by default, powerful on demand.**

Product rule: **Less noise, more decision.** Every screen should make the next useful decision obvious before exposing supporting detail.

## Product-wide decision hierarchy

The following rules apply to every JobPilot surface, not only the Review Queue.

1. **Lead with the user's decision.** The first viewport should answer what this is, what matters now, and what the user can do next.
2. **One primary action per decision context.** Secondary, destructive, administrative, and navigation actions must remain available without competing visually with the primary action.
3. **Do not repeat status as decoration.** A status should have one canonical visible location per context. Repeating the same state in an eyebrow, badge, selector, card and footer adds noise rather than confidence.
4. **Summarize before expanding.** Long descriptions, AI evidence, diagnostics, generated text and secondary metadata start compact and expose full detail on demand.
5. **Surface blockers and attention points.** Readiness areas should answer: what is ready, what is missing, what is blocking, and what merely deserves attention.
6. **Prefer progressive disclosure to card accumulation.** Do not give every piece of information the same visual weight. Group secondary information behind details, tabs, drawers or expandable regions when that improves scanning.
7. **Keep technical implementation language out of the primary UI.** Users need the conclusion, evidence and recovery path. Raw provider names, AI pipeline wording, HTTP/API detail and diagnostics belong in secondary detail unless they are necessary to act.
8. **Errors explain impact and recovery.** Error states should say what failed, what remains safe or usable, and what the user can do next. Offer retry when retry is meaningful; keep technical detail expandable.

### Default information order

For decision-heavy screens, prefer this order unless the task requires another one:

1. identity and essential context;
2. compact decision summary;
3. blockers / points of attention;
4. primary action;
5. supporting evidence;
6. long-form or technical detail.

A page may use cards, rows, tabs or sections, but the information order should remain deliberate.

## Semantic color discipline

Color communicates meaning before decoration:

- **Green**: ready, successful, completed or clearly positive outcome.
- **Red / rose**: destructive action, failure, rejection or blocking error.
- **Amber**: warning, uncertainty, stale data or attention required.
- **Blue / indigo**: information, neutral active state and primary product action.
- **Neutral surfaces**: structure, metadata and secondary actions.

Do not color every status or button merely to differentiate it. Use text, hierarchy, iconography and position first; reserve semantic color for meaningful state.

## UX writing / Content Design contract

Every user-facing change receives a Content Designer / UX Writer pass before merge.

- Prefer short, concrete labels and familiar verbs.
- Write for the user's task, not for the implementation. Prefer `Analyse existante` to `Analyse IA réutilisée` when the AI provenance is not the decision itself.
- Prefer decision language: `Manques bloquants`, `À noter`, `Non renseignée`, `À raccourcir`, `Voir le détail`, `Réessayer`.
- Avoid exposing machine-style explanations such as `détecté par IA` repeatedly. State the evidence naturally and keep provenance available as supporting context.
- A label should not need a tooltip to explain its basic meaning.
- Destructive or consequential labels must describe the consequence clearly.
- Avoid duplicate wording when layout already communicates the context.
- Keep success copy factual; do not over-celebrate routine saves or background operations.

## 1. Core minimal / productivity

Use as the default everywhere: shell, navigation, forms, buttons, feedback, dialogs, settings and standard cards.

- strong hierarchy, little decoration;
- subtle borders and elevation;
- color reserved for meaning and action;
- keyboard and focus states remain visible;
- prefer shared primitives before page-local UI;
- secondary controls should visually recede from the next useful action.

## 2. Bento

Use for dashboards, KPI groups and high-level summaries only.

- group related signals into a small number of differently weighted modules;
- make the next useful action obvious;
- keep dense operational workflows out of Bento cards;
- responsive layouts may re-order or stack modules rather than shrink them blindly;
- do not give every metric the same size or accent when some metrics are merely supporting context.

Primary surfaces: dashboard, summary blocks, KPI overviews.

## 3. Data-dense

Use where the user repeatedly scans, filters, compares or acts on many records.

- compact rows with controlled density;
- status, score and primary action stay easy to locate;
- filtering, sorting and selection should not be hidden behind decorative UI;
- avoid card-per-row layouts when a list or table scans faster;
- keep repeated row actions quiet until hover/focus when discoverability remains sufficient;
- preserve a stable scan path across rows and avoid redundant status chips.

Primary surfaces: offers, applications, CRM, recruiter/contact lists, connector results.

## 4. Editorial

Use for reading-heavy detail views.

- controlled text measure and generous line-height;
- description body is visually separated from metadata, score and actions;
- progressive disclosure for secondary evidence;
- typography and spacing carry hierarchy before borders or effects;
- present a useful summary before long raw source text when the user is making a decision rather than reading for its own sake.

Primary surfaces: single offer detail, long descriptions, generated application content when reading is the main task.

## 5. AI-native / Aurora

Use only when the content or action is genuinely AI-powered: matching explanations, recommendations, generated assistance and AI usage/status.

- Aurora is an accent layer, never the global application background;
- always explain what the score/recommendation means;
- distinguish deterministic product rules from AI inference;
- expose useful evidence and user control;
- do not decorate ordinary actions with AI styling merely to make them feel premium;
- translate model output into product language before showing it as primary copy;
- keep confidence/provenance available without turning the screen into an AI debug log.

## Readiness pattern

Whenever JobPilot prepares something for a consequential action — an application, synchronization, message, export or connector operation — use a readiness summary when useful:

- **Ready**: complete and usable;
- **Attention**: usable but worth reviewing;
- **Blocking**: must be resolved before the action can safely continue;
- **Not provided**: absent but not necessarily blocking.

Do not label optional missing information as a blocker. The UI must distinguish required business constraints from useful-but-optional completeness.

## Error and recovery pattern

Prefer this structure for recoverable failures:

- a short title describing the failed capability;
- a plain-language message;
- impact or safe-state clarification when useful;
- a `Réessayer` action when retrying can actually help;
- expandable diagnostic detail only when technical information is available.

Do not dump raw backend/provider errors as the only user-facing explanation.

## Surface map

| Product area | Primary language | Supporting language |
| --- | --- | --- |
| Global shell / navigation | Core | — |
| Dashboard / KPIs | Bento | Core |
| Offers workspace | Data-dense | Core, AI-native for matching only |
| Review Queue | Data-dense | Editorial for mission reading, AI-native for matching evidence |
| Applications | Data-dense | Core |
| CRM / contacts / follow-ups | Data-dense | Core |
| Offer detail | Editorial | Core, AI-native for score explanation |
| Connectors / synchronization | Core | Data-dense for results |
| Reporting | Core | Bento for summary KPIs, data-dense for tables |
| Settings / profile / CV | Core | Editorial for long guidance only |
| AI usage / recommendations | AI-native | Core, data-dense for usage records |

## Product-wide review checklist

Before approving any user-facing change, verify:

- Can the main task and next useful action be understood within a few seconds?
- Is there exactly one visually dominant primary action in the current decision context?
- Are status and metadata shown once unless repetition has a concrete interaction reason?
- Are blockers distinct from warnings and optional missing information?
- Is long or technical content progressively disclosed where appropriate?
- Does every error explain impact/recovery rather than only failure?
- Has the copy received a Content Designer / UX Writer pass?
- Are semantic colors used consistently and not decoratively?
- Does the hierarchy still work with long real data and on narrow screens?

## Storybook contract

Storybook is the executable reference for the design system.

- Update or add a story whenever a shared visual component or reusable state materially changes.
- Stories must import the real application tokens and accessibility layer.
- Cover meaningful loading, empty, success, warning, error, selected, disabled and AI-specific states when relevant.
- Keep Storybook copy subject to the same UX writing pass as production copy.
- `npm run build-storybook` must remain green for design-system work.

The reference story `Design System/Core contracts/Visual language map` shows the intended relationship between the five languages.