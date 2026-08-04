# Connecteurs de sources d’offres

## Objectif

Un connecteur transforme une source externe en offres normalisées comprises par JobPilot. La source peut être une API, un flux RSS, un scraper HTTP, un navigateur Playwright, Gmail ou l’extension Chrome.

Les connecteurs livrés dans cette version sont :

- `arbeitnow` — API, active par défaut ;
- `adzuna` — API, active lorsque les identifiants sont renseignés.

## Contrat

Chaque connecteur implémente `JobSourceConnector` et fournit :

- un `code` stable, technique et unique ;
- un nom destiné à l’interface ;
- un mode de collecte ;
- son état de configuration ;
- un message expliquant une configuration manquante ;
- une opération de recherche retournant des payloads normalisés.

Le code stable ne doit jamais être dérivé d’un libellé traduit. Il sert aux commandes, aux URL et à l’historique.

## Modes disponibles

```text
API
RSS
SCRAPING_HTTP
SCRAPING_BROWSER
GMAIL
EXTENSION
MANUAL
```

Le mode décrit la manière de collecter les données. Il ne change pas le modèle d’offre produit par le connecteur.

## Registre persistant

La table `source_connector` contient l’état opérationnel de chaque connecteur :

- activé ou désactivé ;
- configuré ou incomplet ;
- dernière synchronisation ;
- dernière réussite ;
- prochain lancement estimé ;
- volumes reçus, importés, dupliqués et échoués ;
- dernière erreur.

Les définitions techniques sont resynchronisées depuis le code. Le choix utilisateur `enabled` reste conservé en base.

## Historique

Chaque exécution crée une ligne `connector_sync_run` avec :

- le connecteur ;
- le déclencheur (`scheduled`, `page-load`, `manual` ou `cli`) ;
- les dates de début et de fin ;
- le statut ;
- les compteurs ;
- l’erreur éventuelle ;
- quelques détails de diagnostic non sensibles.

Une exécution désactivée ou non configurée est ignorée et ne crée pas de faux historique.

## Interface

La page **Connecteurs** permet de :

- consulter l’état et la configuration ;
- activer ou désactiver une source ;
- lancer un test manuel ;
- consulter les vingt dernières exécutions.

La page **Offres** permet de filtrer les offres par source.

## Commandes

Synchroniser toutes les sources arrivées à échéance :

```bash
docker compose exec api php bin/console app:jobs:sync
```

Forcer toutes les sources actives :

```bash
docker compose exec api php bin/console app:jobs:sync --force
```

Forcer une seule source :

```bash
docker compose exec api php bin/console app:jobs:sync --force --connector=arbeitnow
```

## Ajouter un connecteur

1. Implémenter `App\JobDiscovery\Domain\Connector\JobSourceConnector`.
2. Utiliser un code unique en minuscules.
3. Déclarer le mode réel de collecte.
4. Retourner des offres avec au minimum `externalId`, `title` et `description`.
5. Fournir des tests unitaires avec réponses ou fixtures locales.
6. Documenter les variables d’environnement, quotas et limitations.
7. Ne jamais rendre la CI dépendante du site externe.

L’autoconfiguration Symfony ajoute automatiquement l’implémentation au registre.

## Déduplication actuelle

La déduplication reste fondée sur le couple :

```text
source + externalId
```

La fusion canonique d’une même offre présente sur plusieurs plateformes est un chantier ultérieur.

## Sécurité et conformité

Un connecteur ne doit pas :

- contourner une authentification ou un CAPTCHA ;
- réutiliser des cookies privés sans mécanisme explicitement prévu ;
- masquer l’automatisation ;
- contourner un quota ou une interdiction ;
- journaliser des secrets ou des données personnelles inutiles.
