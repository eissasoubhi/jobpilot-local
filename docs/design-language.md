# JobPilot visual language

JobPilot uses one design system with several task-specific visual languages. These are not separate themes. They share the same semantic tokens, UI primitives, accessibility rules, responsive behavior, and UX writing standards.

Principle: **Calm by default, powerful on demand.**

## 1. Core minimal / productivity

Use as the default everywhere: shell, navigation, forms, buttons, feedback, dialogs, settings and standard cards.

- strong hierarchy, little decoration;
- subtle borders and elevation;
- color reserved for meaning and action;
- keyboard and focus states remain visible;
- prefer shared primitives before page-local UI.

## 2. Bento

Use for dashboards, KPI groups and high-level summaries only.

- group related signals into a small number of differently weighted modules;
- make the next useful action obvious;
- keep dense operational workflows out of Bento cards;
- responsive layouts may re-order or stack modules rather than shrink them blindly.

Primary surfaces: dashboard, summary blocks, KPI overviews.

## 3. Data-dense

Use where the user repeatedly scans, filters, compares or acts on many records.

- compact rows with controlled density;
- status, score and primary action stay easy to locate;
- filtering, sorting and selection should not be hidden behind decorative UI;
- avoid card-per-row layouts when a list or table scans faster.

Primary surfaces: offers, applications, CRM, recruiter/contact lists, connector results.

## 4. Editorial

Use for reading-heavy detail views.

- controlled text measure and generous line-height;
- description body is visually separated from metadata, score and actions;
- progressive disclosure for secondary evidence;
- typography and spacing carry hierarchy before borders or effects.

Primary surfaces: single offer detail, long descriptions, generated application content when reading is the main task.

## 5. AI-native / Aurora

Use only when the content or action is genuinely AI-powered: matching explanations, recommendations, generated assistance and AI usage/status.

- Aurora is an accent layer, never the global application background;
- always explain what the score/recommendation means;
- distinguish deterministic product rules from AI inference;
- expose useful evidence and user control;
- do not decorate ordinary actions with AI styling merely to make them feel premium.

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

## Storybook contract

Storybook is the executable reference for the design system.

- Update or add a story whenever a shared visual component or reusable state materially changes.
- Stories must import the real application tokens and accessibility layer.
- Cover meaningful loading, empty, success, warning, error, selected, disabled and AI-specific states when relevant.
- Keep Storybook copy subject to the same UX writing pass as production copy.
- `npm run build-storybook` must remain green for design-system work.

The reference story `Design System/Core contracts/Visual language map` shows the intended relationship between the five languages.
