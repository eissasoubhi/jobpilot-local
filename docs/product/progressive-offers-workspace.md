# Progressive Offers workspace and AI status

## Goal

The Offers workspace must remain useful while background work is running. A connector synchronization must not replace already synchronized local data with a full-page loading state.

## Progressive first paint

On the initial visit to `/offres`, JobPilot now performs the work in this order:

1. fetch the local `/jobs` catalog;
2. render those already synchronized offers immediately;
3. start loading application tracking in the background;
4. start the due connector synchronization in the background;
5. refresh the local catalog when synchronization finishes, without clearing the offers already on screen.

The global Offers loading indicator is therefore used only while the initial local catalog itself is unknown. Once local offers have been loaded, `syncing` no longer hides them.

The automatic-search card makes this state explicit with **Données locales affichées** or **Mise à jour en arrière-plan**. Application tracking can finish independently from the catalog.

This change does not alter connector scheduling, authentication, source quotas, robots/CAPTCHA policy, normalization or deduplication. It only changes when local UI reads are rendered.

## Sidebar organization

The sidebar is grouped by workflow instead of using one long flat list:

- Travail;
- Recherche;
- CRM & suivi;
- Analyse;
- Configuration.

**Configuration & clés API** is intentionally the first item in the Configuration group. On constrained desktop heights, the navigation area scrolls independently so configuration links stay reachable while the JobPilot footer and AI state remain visible.

## Persistent AI state

The sidebar exposes a compact read-only AI status card linked to `/parametres/integrations`.

It reads only the public `/settings/ai` payload and never receives an API key. The card shows:

- whether AI matching is active, disabled, missing a configured key, or temporarily unavailable;
- the active provider/model;
- RPM, TPM and daily quota usage percentages when quota information exists;
- a single **Quota max** percentage equal to the highest current percentage among RPM, TPM and RPD.

Using the highest percentage makes the limiting dimension visible: for example, 50% RPM, 10% TPM and 10% daily usage displays `Quota max 50%`.

The status refreshes periodically without reloading the page. Provider-side quota enforcement remains the responsibility of the quota manager introduced earlier; this sidebar is only an operational indicator and cannot bypass or increase limits.

## Safety boundary

No database migration, external application submission, connector policy, authentication flow or provider quota behavior is changed by this UI delivery.
