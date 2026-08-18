# Monitoring et alertes

## Objectif

Détecter rapidement les pannes visibles par l’utilisateur, les synchronisations silencieusement cassées et les files asynchrones bloquées, sans transformer les logs en copie de données personnelles.

Ce document définit le minimum observable avant une première production stable.

## Signaux prioritaires

### Disponibilité

Surveiller séparément :

- disponibilité du frontend Next.js ;
- disponibilité de l’API Symfony ;
- accès PostgreSQL ;
- état RabbitMQ lorsque Messenger est activé ;
- accès au stockage de documents ;
- healthchecks des workers.

Une page frontend qui répond alors que l’API est indisponible ne doit pas être considérée comme un service sain.

### Connecteurs et synchronisations

Mesures par source :

- dernière synchronisation réussie ;
- durée ;
- nombre d’offres lues, créées, mises à jour, ignorées et rejetées ;
- retries ;
- erreurs par catégorie ;
- réponses HTTP 401, 403, 429 et 5xx ;
- taux d’extraction ;
- nombre anormalement nul d’offres ;
- circuit breaker ouvert ;
- quota restant lorsque disponible.

Une source qui retourne soudainement zéro résultat plusieurs fois doit produire un signal distinct d’une synchronisation saine à zéro changement.

### Candidatures

Surveiller :

- candidatures préparées ;
- envois réussis ;
- envois échoués ;
- candidatures bloquées par CV/document manquant ;
- délais anormaux dans une file d’envoi ;
- retries et dead letters.

Aucun contenu de lettre, CV ou réponse recruteur ne doit être placé dans les métriques.

### Gmail et messagerie

Surveiller :

- dernière synchronisation Gmail réussie ;
- messages récupérés ;
- messages classifiés ;
- messages laissés inconnus ;
- erreurs OAuth ;
- expiration/révocation du token ;
- backlog de traitement.

## Logs structurés

Format cible JSON avec champs techniques stables :

```json
{
  "timestamp": "...",
  "level": "info",
  "service": "api",
  "environment": "production",
  "correlation_id": "...",
  "sync_run_id": "...",
  "connector": "france-travail",
  "event": "connector.sync.completed",
  "duration_ms": 1240
}
```

Règles :

- ne jamais logger les tokens OAuth, API keys, cookies ou mots de passe ;
- ne pas logger le contenu brut des CV, lettres ou e-mails ;
- éviter les adresses e-mail, téléphones et noms lorsqu’un identifiant interne suffit ;
- appliquer une rétention bornée ;
- utiliser `correlation_id` pour relier API, worker et événement métier.

## Métriques minimales

### API

- requêtes par route ;
- latence p50/p95/p99 ;
- taux de 4xx et 5xx ;
- saturation des workers PHP ;
- erreurs Doctrine/connexion DB.

### Queue

- messages prêts ;
- messages en cours ;
- âge du plus ancien message ;
- retries ;
- dead letters ;
- débit de consommation.

### PostgreSQL

- connexions utilisées ;
- latence ;
- espace disque ;
- croissance de la base ;
- transactions longues ;
- échecs de sauvegarde.

### Infrastructure

- CPU ;
- mémoire ;
- disque ;
- espace Docker sur un runner self-hosted ;
- disponibilité des volumes ;
- erreurs de healthcheck.

## Alertes initiales

Déclencher une alerte exploitable lorsque :

- l’API ou le frontend échoue plusieurs checks consécutifs ;
- PostgreSQL n’est plus joignable ;
- une sauvegarde attendue est absente ou en échec ;
- une file critique ne se vide plus et l’âge du plus ancien message augmente ;
- une dead-letter queue reçoit de nouveaux messages ;
- un connecteur opérationnel échoue plusieurs synchronisations consécutives ;
- une source habituellement productive retourne zéro résultat de façon répétée ;
- des 401/403/429 répétés ouvrent le circuit breaker ;
- un envoi de candidature critique reste bloqué ;
- l’espace disque passe sous un seuil de sécurité.

Éviter les alertes sur un échec isolé récupéré automatiquement par retry.

## Dashboards

Prévoir au minimum trois vues :

1. **Produit** : offres découvertes, candidatures, réponses, entretiens, blocages.
2. **Connecteurs** : état, dernière sync, erreurs, quotas, extraction et volume par source.
3. **Plateforme** : API, workers, DB, queue, stockage, disque et sauvegardes.

Le dashboard produit n’est pas un remplacement du monitoring technique.

## Sentry et traces

Sentry ou équivalent doit recevoir les exceptions applicatives nettoyées des données personnelles et secrets.

OpenTelemetry devient prioritaire lorsque les traitements Messenger traversent plusieurs workers. Les traces doivent propager le même `correlation_id` et permettre de suivre :

```text
sync déclenchée → récupération → normalisation → déduplication → scoring → préparation
```

## Vérification avant mise en production

Avant chaque mise en production importante :

- vérifier que les dashboards reçoivent encore des données ;
- provoquer une erreur contrôlée en staging et confirmer sa visibilité ;
- vérifier les alertes de sauvegarde et de file ;
- confirmer qu’aucun secret ni contenu personnel n’apparaît dans les logs ;
- tester le lien entre une erreur et son `correlation_id`.
