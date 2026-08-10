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
- `AUTO` : le chemin HTTP + fallback IA reste prioritaire et Browser ne prend le relais que lorsque le diagnostic indique qu’un rendu navigateur est nécessaire ;
- `BROWSER` : Browser peut être utilisé directement, mais uniquement après les mêmes contrôles d’autorisation et de `robots.txt`.

## Chaîne de récupération

`BrowserAwareCustomScraperJobConnector` est le connecteur dynamique final exposé au registre. Il réutilise les chemins existants au lieu de créer un pipeline parallèle :

1. extraction HTTP déterministe ;
2. fallback Gemini grounded lorsque la politique IA l’autorise ;
3. récupération Browser uniquement lorsque le mode et le diagnostic l’exigent ;
4. extraction du HTML rendu avec les mêmes extracteurs génériques ;
5. enrichissement des fiches détail ;
6. passage obligatoire par `CustomScraperOfferQualityEvaluator` ;
7. seules les offres fiables rejoignent le pipeline canonique JobPilot.

Avant chaque rendu de page liste ou de fiche détail, `CustomScraperBrowserRecoveryService` exécute un préflight via l’infrastructure HTTP contrôlée. Une interdiction `robots.txt`, une révocation d’autorisation ou une erreur de préflight empêche l’appel au worker.

## Limites Browser

Le chemin Browser est volontairement plus conservateur que le scraping HTTP :

- 3 pages de liste maximum par synchronisation ;
- 10 fiches détail maximum ;
- pagination uniquement via un `nextUrl` HTTPS du même domaine détecté par le moteur générique ;
- arrêt sur boucle, erreur de préflight ou erreur de rendu ;
- HTML rendu limité à 3 Mo côté worker et revalidé côté API ;
- worker désactivé tant que `BROWSER_WORKER_URL` et `JOBPILOT_BROWSER_WORKER_TOKEN` ne sont pas configurés.

## Frontière de sécurité

Le worker reste read-only : aucun login automatisé, aucun cookie/session privée injectée, aucun clic ou formulaire, aucun CAPTCHA bypass, aucun mode stealth, aucune rotation de proxy et aucun contournement de contrôle d’accès. Les navigations principales restent sur le domaine HTTPS autorisé et les destinations privées/locales sont refusées.

## État de livraison

Le chemin Browser est maintenant câblé dans la synchronisation des sources personnalisées. Il ne devient réellement actif en environnement d’exécution qu’après déploiement du worker Playwright et configuration de son URL/token internes.
