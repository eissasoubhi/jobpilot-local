# Connecteurs de sources d’offres

## Objectif

Un connecteur transforme une source externe en offres normalisées comprises par JobPilot. La source peut être une API, un flux RSS, un scraper HTTP autorisé, un navigateur Playwright autorisé, Gmail ou l’extension Chrome.

Les connecteurs et canaux livrés dans cette version sont :

- `arbeitnow` — API, active par défaut ;
- `adzuna` — API, active lorsque les identifiants sont renseignés ;
- `symfony-jobs` — flux RSS officiel du job board Symfony, actif par défaut ;
- `gmail` — lecture des alertes et échanges de recrutement lorsque Gmail est connecté avec `gmail.readonly` ;
- extension Chrome — import déclenché par l’utilisateur depuis une page d’offre visible, avec prise en charge structurée de Free-Work et du balisage `JobPosting`.

Symfony Jobs est lu uniquement à travers le lien **Jobs RSS** exposé par le site officiel. Voir [`symfony-jobs.md`](symfony-jobs.md).

Free-Work n’est pas interrogé automatiquement par le backend. Les offres sont récupérées depuis les alertes Gmail ou importées par l’utilisateur avec l’extension. Voir [`free-work.md`](free-work.md).

## Contrat

Chaque connecteur planifié implémente `JobSourceConnector` et fournit :

- un `code` stable, technique et unique ;
- un nom destiné à l’interface ;
- un mode de collecte ;
- son état de configuration ;
- un message expliquant une configuration manquante ;
- une opération de recherche retournant des payloads normalisés.

Un connecteur utilisable automatiquement implémente aussi `GovernedJobSourceConnector` et fournit un `ConnectorPolicy`. En l’absence de cette politique explicite, JobPilot classe la source `UNDER_REVIEW` et bloque toute synchronisation, y compris forcée.

Lorsqu’une extraction dépend d’une logique de parsing versionnée, le connecteur implémente aussi `VersionedJobSourceConnector`. La version est conservée dans les diagnostics de synchronisation afin de relier une rupture éventuelle à la logique d’extraction utilisée.

Le code stable ne doit jamais être dérivé d’un libellé traduit. Il sert aux commandes, aux URL, à l’identité des occurrences et à l’historique.

L’extension n’est pas un crawler planifié : elle transmet une seule page ouverte volontairement par l’utilisateur au pipeline canonique.

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

## Politique de conformité

La configuration technique et l’autorisation de collecter sont deux choses différentes. Chaque connecteur planifié déclare l’un des statuts suivants :

```text
ALLOWED                  collecte automatique autorisée
AUTHORIZED_ONLY          accès, clé ou consentement utilisateur requis
EMAIL_OR_EXTENSION_ONLY  backend bloqué ; Gmail ou extension seulement
DISABLED                 collecte explicitement interdite
UNDER_REVIEW             collecte bloquée en attendant une revue
```

La politique peut aussi préciser :

- la date de dernière revue ;
- la justification de la décision ;
- le nombre maximal de requêtes par synchronisation ;
- un quota journalier ;
- un délai minimal entre requêtes ;
- le respect de `robots.txt`.

`--force` accélère uniquement une source déjà autorisée. Il ne contourne jamais `EMAIL_OR_EXTENSION_ONLY`, `DISABLED` ou `UNDER_REVIEW`.

La décision d’architecture est détaillée dans [`../architecture/adr/0007-connector-compliance-policy.md`](../architecture/adr/0007-connector-compliance-policy.md).

## Registre persistant

La table `source_connector` contient l’état opérationnel de chaque connecteur planifié :

- activé ou désactivé ;
- configuré ou incomplet ;
- politique de conformité et date de revue ;
- limites déclarées de collecte ;
- dernière synchronisation ;
- dernière réussite ;
- prochain lancement estimé ;
- volumes reçus, importés, fusionnés, déjà connus et échoués ;
- dernière erreur.

Les définitions techniques et la politique sont resynchronisées depuis le code. Le choix utilisateur `enabled` reste conservé en base.

## Historique

Chaque exécution planifiée crée une ligne `connector_sync_run` avec :

- le connecteur ;
- le déclencheur (`scheduled`, `page-load`, `manual` ou `cli`) ;
- les dates de début et de fin ;
- le statut ;
- les compteurs ;
- l’erreur éventuelle ;
- la version du parseur lorsqu’elle existe ;
- le taux de normalisation et l’indicateur de résultat vide ;
- quelques détails de diagnostic non sensibles.

Une exécution désactivée, non configurée ou bloquée par sa politique est ignorée et ne crée pas de faux historique.

Pour Gmail, les compteurs du registre concernent les offres extraites. La page **Messagerie** expose en complément les volumes de messages lus, associés et nécessitant une action.

## Santé d’extraction

JobPilot analyse les six dernières synchronisations terminées de chaque connecteur. Le diagnostic distingue :

```text
NO_DATA
HEALTHY
WATCH
DEGRADED
BROKEN
```

Une synchronisation vide n’est pas immédiatement considérée comme une rupture. Une référence positive antérieure est nécessaire avant d’émettre une alerte. Deux résultats vides consécutifs signalent une dégradation ; trois signalent une rupture probable. Le taux de normalisation déclenche également une alerte lorsqu’il passe sous 90 %, puis une rupture probable sous 50 %.

Le détail des règles et limites est documenté dans [`health-monitoring.md`](health-monitoring.md) et l’ADR [`../architecture/adr/0010-connector-parser-health.md`](../architecture/adr/0010-connector-parser-health.md).

## Catalogue canonique

Le payload d’un connecteur ou de l’extension n’est plus enregistré directement comme une carte indépendante. Il devient une `JobSourceOccurrence`, puis JobPilot cherche l’offre canonique correspondante.

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

- consulter l’état, la configuration et l’autorisation ;
- voir la version du parseur et la santé d’extraction ;
- consulter le taux de normalisation, la référence positive et les synchronisations vides ;
- voir la date de revue et les limites de collecte ;
- activer ou désactiver une source planifiée ;
- lancer un test manuel lorsque la politique l’autorise ;
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

Forcer toutes les sources actives et autorisées :

```bash
docker compose exec api php bin/console app:jobs:sync --force
```

Forcer une seule source autorisée :

```bash
docker compose exec api php bin/console app:jobs:sync --force --connector=arbeitnow
docker compose exec api php bin/console app:jobs:sync --force --connector=symfony-jobs
docker compose exec api php bin/console app:jobs:sync --force --connector=gmail
```

Free-Work n’apparaît pas dans ces commandes, car aucun scraper planifié n’est activé pour cette plateforme.

## Ajouter un connecteur

1. Vérifier d’abord l’API officielle, le RSS, les alertes e-mail ou l’import assisté.
2. Examiner les conditions d’utilisation, les mentions légales et `robots.txt` avant tout scraper.
3. Implémenter `App\JobDiscovery\Domain\Connector\JobSourceConnector`.
4. Implémenter `GovernedJobSourceConnector` et déclarer une politique explicite avant toute synchronisation.
5. Implémenter `VersionedJobSourceConnector` lorsque la source dépend d’un parseur évolutif.
6. Utiliser un code unique en minuscules.
7. Déclarer le mode réel de collecte.
8. Retourner des offres avec au minimum `externalId`, `title` et `description`.
9. Fournir une entreprise fiable lorsque la source la connaît : elle sécurise la fusion multi-sources.
10. Fournir l’URL la plus directe possible vers l’offre.
11. Fournir des tests unitaires avec réponses ou fixtures locales.
12. Tester les résultats vides, les champs manquants et les erreurs de normalisation.
13. Documenter les variables d’environnement, quotas, date de revue et limitations.
14. Ne jamais rendre la CI dépendante du site externe.

L’autoconfiguration Symfony ajoute automatiquement l’implémentation au registre. Le pipeline commun gère ensuite l’idempotence, la canonicalisation, le scoring, le CV et la préparation.

## Sécurité et conformité

Un connecteur ne doit pas :

- contourner une authentification ou un CAPTCHA ;
- réutiliser des cookies privés sans mécanisme explicitement prévu ;
- masquer l’automatisation ;
- contourner un quota ou une interdiction ;
- aspirer une source dont les conditions réservent ou interdisent l’extraction sans autorisation ;
- journaliser des secrets ou des données personnelles inutiles.

La disponibilité publique d’une page n’est pas, à elle seule, une autorisation de collecte automatisée. `robots.txt` est un garde-fou technique, pas un remplacement de la revue contractuelle. Lorsqu’un flux RSS ou une API officielle est proposé, ce canal est préféré au scraping HTML.

Le connecteur Gmail utilise des scopes minimaux, ne modifie aucun message dans Gmail et limite le nombre de résultats et de pages lus par synchronisation. Le connecteur Symfony Jobs ne lit qu’un flux officiel. L’extension agit uniquement à la demande de l’utilisateur sur l’onglet actif.
