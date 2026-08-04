# Socle de production

## Objectif

Déployer JobPilot de manière fiable sans introduire Kubernetes avant qu’il soit justifié.

## Composants cibles

```text
Reverse proxy TLS
  ├── Next.js
  └── Symfony API

Symfony workers
RabbitMQ
PostgreSQL
Stockage objet S3-compatible
Redis optionnel
```

## Environnements

- `dev` : Docker Compose local, données de développement.
- `test` : environnement éphémère CI, aucun secret réel.
- `staging` : configuration proche de la production, comptes externes de test.
- `production` : images immuables, secrets gérés et sauvegardes actives.

## Déploiement

- Construire une image versionnée par commit.
- Exécuter les migrations comme étape contrôlée.
- Démarrer les nouveaux conteneurs et vérifier les healthchecks.
- Conserver une image précédente pour rollback.
- Ne jamais supprimer automatiquement les volumes lors d’un déploiement.

## Données

### PostgreSQL

- sauvegarde quotidienne ;
- rétention définie ;
- chiffrement au repos ;
- test de restauration planifié ;
- migrations testées sur base vide et copie représentative.

### Documents

Les CV et documents utilisent le système de fichiers local en développement et un stockage objet en production.

- noms internes non devinables ;
- accès privé ;
- durée de conservation définie ;
- checksum et type MIME validés ;
- sauvegarde ou versioning selon le fournisseur.

## Secrets

- jamais dans Git ;
- variables locales dans `.env` non versionné ;
- secret manager en production ;
- rotation des secrets et révocation documentées ;
- scopes OAuth minimaux.

## Observabilité

- logs JSON avec `correlation_id`, `sync_run_id` et `message_id` ;
- métriques de durée, succès, erreurs, retry et volume par connecteur ;
- suivi des files RabbitMQ et dead letters ;
- Sentry ou équivalent pour les exceptions ;
- OpenTelemetry lorsque les workers asynchrones sont introduits ;
- alertes sur synchronisations en échec, zéro résultat anormal et envois refusés.

## Sécurité

- TLS ;
- headers de sécurité ;
- dépendances et images scannées ;
- rate limiting sur les actions sensibles ;
- validation stricte des fichiers ;
- journal d’audit des envois ;
- aucune donnée personnelle dans les logs techniques.

## Critères avant première production

- sauvegarde et restauration testées ;
- procédure de rollback ;
- healthchecks fiables ;
- secrets séparés ;
- migrations validées ;
- alertes opérationnelles ;
- politique de rétention ;
- documentation d’incident minimale.
