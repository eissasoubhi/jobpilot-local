# Politique de rendu Browser des scrapers personnalisés

Le fait qu’une page fonctionne mieux avec JavaScript ne suffit pas à autoriser Playwright. Le rendu Browser est séparé derrière une politique explicite.

## Décision

`CustomScraperBrowserRenderPolicy` autorise le rendu uniquement si :

- le worker Browser est configuré ;
- l’autorisation de collecte de la source est toujours confirmée ;
- le contrôle `robots.txt` a déjà été validé par l’API ;
- et le mode de source l’autorise.

Règles de mode :

- `HTTP` : jamais de bascule Browser automatique ;
- `AUTO` : Browser uniquement lorsque le diagnostic recommande `BROWSER` ;
- `BROWSER` : Browser peut être utilisé même si le diagnostic HTTP était ambigu, mais seulement si les trois garde-fous précédents sont validés.

## Coordinateur

`CustomScraperBrowserRenderCoordinator` applique cette politique avant d’appeler `BrowserRenderClientInterface`.

Lorsque la politique refuse le rendu, aucun appel au worker n’est effectué. Lorsque la politique l’autorise, le coordinateur transmet seulement le code de source, l’URL, le domaine et les deux confirmations de sécurité déjà obtenues.

## État de livraison

Cette tranche teste la décision indépendamment du moteur de collecte. Elle ne rend pas encore les sources `BROWSER` synchronisables.

La prochaine intégration devra obtenir un véritable accord `robots.txt` depuis l’infrastructure HTTP existante avant d’appeler ce coordinateur, puis réutiliser l’extraction DOM, la pagination et le score de qualité sur le HTML rendu.
