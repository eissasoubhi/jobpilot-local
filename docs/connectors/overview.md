# Connecteurs de sources d’offres

## Objectif

Un connecteur transforme une source externe en offres normalisées comprises par JobPilot. La source peut être une API, un flux RSS, un scraper HTTP, un navigateur Playwright, Gmail ou l’extension Chrome.

Les connecteurs livrés dans cette version sont :

- `arbeitnow` — API, active par défaut ;
- `adzuna` — API, active lorsque les identifiants sont renseignés ;
- `gmail` — lecture des alertes et échanges de recrutement lorsque Gmail est connecté avec `gmail.readonly`.

## Contrat

Chaque connecteur implémente `JobSourceConnector` et fournit :

- un `code` stable, technique et unique ;
- un nom destiné à l’interface ;
- un mode de collecte ;
- son état de configuration ;
- un message expliquant une configuration manquante ;
- une opération de recherche retournant des payloads normalisés.

Le code stable ne doit jamais être dérivé d’un libellé traduit. Il sert aux commandes, aux URL, à l’identité des occurrences et à l’historique.

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
- volumes reçus, importés, fusionnés, déjà connus et échoués ;
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

Pour Gmail, les compteurs du registre concernent les offres extraites. La page **Messagerie** expose en complément les volumes de messages lus, associés et nécessitant une action.

## Catalogue canonique

Le payload d’un connecteur n’est plus enregistré directement comme une carte indépendante. Il devient une `JobSourceOccurrence`, puis JobPilot cherche l’offre canonique correspondante.

Les résultats possibles sont :

```text
imported    nouvelle offre canonique
merged      nouvelle source ajoutée à une offre existante
duplicates  occurrence source déjà connue
failed      occurrence invalide ou erreur
```

L’identité idempotente d’une occurrence reste :

```text
sourceCode + externalId
```

Pour rapprocher plusieurs plateformes, JobPilot utilise ensuite, dans l’ordre :

1. l’URL canonique ;
2. l’intitulé et l’entreprise normalisés ;
3. une similarité conservatrice incluant contrat, lieu et date.

La preuve du rapprochement, son score et ses raisons sont conservés sur l’occurrence. La stratégie complète est documentée dans [`../job-catalog/canonical-offers.md`](../job-catalog/canonical-offers.md).

## Interface

La page **Connecteurs** permet de :

- consulter l’état et la configuration ;
- activer ou désactiver une source ;
- lancer un test manuel ;
- distinguer nouvelles offres, sources fusionnées et occurrences connues ;
- consulter les vingt dernières exécutions.

La page **Offres** :

- affiche une seule carte par offre canonique ;
- montre toutes ses sources ;
- filtre sur n’importe quelle occurrence ;
- expose les liens et la méthode de rapprochement.

La page **Messagerie** permet d’exploiter les messages Gmail classés.

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
docker compose exec api php bin/console app:jobs:sync --force --connector=gmail
```

## Ajouter un connecteur

1. Implémenter `App\JobDiscovery\Domain\Connector\JobSourceConnector`.
2. Utiliser un code unique en minuscules.
3. Déclarer le mode réel de collecte.
4. Retourner des offres avec au minimum `externalId`, `title` et `description`.
5. Fournir une entreprise fiable lorsque la source la connaît : elle sécurise la fusion multi-sources.
6. Fournir l’URL la plus directe possible vers l’offre.
7. Fournir des tests unitaires avec réponses ou fixtures locales.
8. Documenter les variables d’environnement, quotas et limitations.
9. Ne jamais rendre la CI dépendante du site externe.

L’autoconfiguration Symfony ajoute automatiquement l’implémentation au registre. Le pipeline commun gère ensuite l’idempotence, la canonicalisation, le scoring, le CV et la préparation.

## Sécurité et conformité

Un connecteur ne doit pas :

- contourner une authentification ou un CAPTCHA ;
- réutiliser des cookies privés sans mécanisme explicitement prévu ;
- masquer l’automatisation ;
- contourner un quota ou une interdiction ;
- journaliser des secrets ou des données personnelles inutiles.

Le connecteur Gmail utilise des scopes minimaux, ne modifie aucun message dans Gmail et limite le nombre de résultats et de pages lus par synchronisation.
