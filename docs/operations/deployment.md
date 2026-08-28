# Déploiement production

## Objectif

Déployer JobPilot de façon répétable, observable et réversible, sans toucher aux données de développement ni dépendre d’un fournisseur d’infrastructure particulier.

Ce runbook complète [le socle de production](production-baseline.md), [la sauvegarde/restauration](backup-restore.md), [le monitoring](monitoring.md) et [la réponse aux incidents](incident-response.md).

## Pré-requis

Avant tout déploiement :

- la révision à déployer correspond à un commit identifié et testé ;
- Backend, Frontend, Docker Compose et Chromium/E2E sont verts sur ce commit ;
- les images sont immuables et taguées avec le SHA du commit ;
- les secrets de production sont fournis par le gestionnaire de secrets, jamais par Git ;
- PostgreSQL dispose d’une sauvegarde récente et d’une procédure de restauration vérifiée ;
- les migrations ont été validées sur une base de test isolée et, pour un changement sensible, en staging ;
- l’image de la version précédente reste disponible pour rollback ;
- les alertes critiques ne sont pas déjà en état dégradé avant le changement.

## Ordre de déploiement

### 1. Geler l’artefact

Construire et publier les images à partir du commit validé. Ne pas reconstruire une image avec le même tag après validation.

Artefacts attendus :

- frontend Next.js ;
- API Symfony ;
- workers Symfony lorsque séparés ;
- scheduler/worker associé lorsque déployé comme composant distinct.

Conserver le SHA Git et le digest d’image dans les métadonnées de déploiement.

### 2. Vérifier la configuration

Avant de démarrer les nouveaux conteneurs :

- vérifier les URLs publiques et le reverse proxy TLS ;
- vérifier la connexion PostgreSQL, RabbitMQ et stockage objet ;
- vérifier les variables OAuth/Gmail sans afficher leur valeur ;
- vérifier les quotas et kill switches des connecteurs ;
- vérifier que les environnements de staging et production utilisent des bases distinctes ;
- vérifier qu’aucune configuration de test n’autorise un appel vers une base dev réelle.

### 3. Appliquer les migrations

Les migrations doivent être une étape explicite du déploiement, avant la bascule applicative.

- exécuter une seule instance de migration ;
- interrompre le déploiement si une migration échoue ;
- ne jamais supprimer automatiquement les volumes ou recréer la base ;
- pour une migration destructive ou difficilement réversible, disposer d’un plan de restauration testé avant exécution.

### 4. Démarrer l’API et le frontend

Démarrer la nouvelle API puis le frontend, sans retirer immédiatement la version précédente si l’infrastructure permet une bascule progressive.

Vérifier au minimum :

- l’endpoint de santé API répond en succès et confirme l’accès PostgreSQL ;
- le frontend démarre avec son build de production ;
- les endpoints essentiels répondent sans erreur 5xx ;
- aucune erreur répétitive n’apparaît dans les logs au démarrage.

### 5. Démarrer les workers et le scheduler

Démarrer ensuite les workers asynchrones et le scheduler.

Contrôles :

- le worker reste vivant après démarrage ;
- la file RabbitMQ ne croît pas anormalement ;
- aucune boucle de retry rapide n’apparaît ;
- le heartbeat/freshness du scheduler est visible ;
- les connecteurs désactivés ou limités restent désactivés ;
- aucun connecteur ne contourne CAPTCHA, authentification ou contrôle d’accès.

## Smoke tests après bascule

Effectuer des vérifications qui n’envoient pas de candidature externe et ne modifient pas de données réelles non nécessaires :

1. ouvrir le frontend et charger les écrans principaux ;
2. vérifier `/api/health` ;
3. vérifier que la page Connecteurs charge son état et son historique ;
4. vérifier la fraîcheur scheduler et les diagnostics de synchronisation ;
5. vérifier que Gmail affiche son état sans forcer un envoi ;
6. vérifier qu’une opération de lecture d’offre/candidature fonctionne ;
7. vérifier les métriques, logs et alertes après la bascule.

Les tests d’envoi Gmail réels restent des actions manuelles explicites vers une adresse contrôlée et ne doivent jamais être utilisés comme smoke test automatique de déploiement.

## Critères de validation

Le déploiement est accepté lorsque :

- healthchecks API et frontend sont stables ;
- PostgreSQL est joignable ;
- workers et scheduler sont sains ;
- aucune hausse anormale de 5xx, retry, dead letters ou échecs de synchronisation n’est observée ;
- les écrans V1 critiques restent utilisables ;
- les alertes de monitoring sont revenues à un état nominal.

## Rollback

Déclencher un rollback si :

- le healthcheck échoue de manière persistante ;
- une migration empêche le démarrage ;
- le taux d’erreur augmente nettement ;
- les workers consomment ou republient les messages de façon anormale ;
- un défaut menace l’intégrité des données ou les garde-fous de synchronisation/envoi.

Procédure :

1. arrêter ou désactiver les workers/schedulers concernés pour limiter les écritures ;
2. rebascule vers l’image précédente connue comme saine ;
3. vérifier les healthchecks ;
4. si le schéma de données est incompatible avec l’ancienne version, suivre le runbook [backup-restore.md](backup-restore.md) plutôt que d’improviser une inversion SQL ;
5. conserver les logs, `correlation_id`, `sync_run_id` et identifiants de messages utiles au diagnostic ;
6. ouvrir le processus d’incident décrit dans [incident-response.md](incident-response.md) si l’impact est significatif.

## Après déploiement

Après une fenêtre d’observation suffisante :

- confirmer la fraîcheur des synchronisations planifiées ;
- vérifier les files RabbitMQ et les dead letters ;
- vérifier l’absence d’erreurs récurrentes ;
- enregistrer le commit et les digests effectivement déployés ;
- documenter toute action manuelle réalisée ;
- programmer un exercice de rollback/restauration si le déploiement a introduit un changement d’infrastructure ou de stockage.

## Staging

Toute modification importante de migrations, stockage, OAuth, scheduler, workers ou réseau doit être répétée en staging avec la même procédure avant production.

Le staging utilise ses propres secrets et données de test. Il ne doit jamais pointer vers la base PostgreSQL de production ou de développement local.
