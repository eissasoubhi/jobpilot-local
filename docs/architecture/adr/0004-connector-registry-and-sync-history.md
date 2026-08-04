# ADR 0004 — Registre commun et historique persistant des connecteurs

- Statut : accepté
- Date : 2026-08-04

## Contexte

Arbeitnow et Adzuna étaient injectés comme fournisseurs techniques dans un service unique. L’état de synchronisation global était stocké dans un fichier JSON. Cette solution ne permettait pas de piloter indépendamment de nombreuses sources, de distinguer API et scraping, ni de conserver un historique exploitable.

JobPilot doit accueillir des API, flux RSS, scrapers HTTP, workers navigateur, Gmail et l’extension Chrome.

## Décision

Nous introduisons :

- un port commun `JobSourceConnector` ;
- un code stable et unique par connecteur ;
- un `ConnectorMode` explicite ;
- un registre en mémoire construit par l’injection de dépendances Symfony ;
- une projection persistante `SourceConnector` pour l’état opérationnel ;
- une entité `ConnectorSyncRun` pour chaque exécution ;
- des commandes permettant de synchroniser toutes les sources ou une source précise.

Le registre en mémoire reste la source de vérité sur les capacités techniques. La base conserve les choix et l’historique d’exploitation.

## Conséquences positives

- ajout d’un connecteur sans modifier le service central ;
- état et erreurs visibles par source ;
- activation et désactivation indépendantes ;
- historique utile au support et aux statistiques ;
- base commune pour le scraping ;
- tests de contrat possibles.

## Conséquences négatives

- deux nouvelles tables ;
- synchronisation entre définition en code et état en base ;
- davantage de cas d’état à gérer dans l’interface ;
- la déduplication multi-sources n’est pas résolue par cette décision.

## Alternatives rejetées

### Un fichier JSON par source

Simple localement, mais fragile pour les écritures concurrentes, les requêtes, l’historique et une future production multi-worker.

### Une table spécifique à chaque plateforme

Produit un couplage fort, des migrations répétitives et une interface difficile à généraliser.

### Microservice par connecteur

Disproportionné au volume et au stade actuel du produit. Les connecteurs restent des adaptateurs du modular monolith. Un worker spécialisé pourra être extrait plus tard pour Playwright.

## Règles

- le code d’un connecteur est immuable ;
- les payloads sont normalisés avant le traitement métier ;
- une exécution est traçable et idempotente ;
- une source non configurée ou désactivée n’est pas appelée ;
- aucun secret ne doit apparaître dans l’historique ;
- l’accès réseau réel n’est jamais requis par la CI.
