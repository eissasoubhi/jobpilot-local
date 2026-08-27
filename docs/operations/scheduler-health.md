# Santé du scheduler

Le conteneur `scheduler` doit rester vivant après un échec ponctuel de synchronisation, mais un orchestrateur doit pouvoir distinguer un scheduler réellement actif d'un simple processus shell encore présent.

## Heartbeat de progression

Le scheduler écrit atomiquement un timestamp Unix dans `SCHEDULER_HEARTBEAT_PATH` (par défaut `/tmp/jobpilot-scheduler-heartbeat`) :

- au démarrage ;
- au début de chaque cycle planifié ;
- à la fin de chaque cycle, même si une commande du cycle a échoué.

Le heartbeat ne contient aucun token, URL privée, contenu d'e-mail, CV, lettre ou autre donnée personnelle.

Le healthcheck considère le scheduler sain tant que l'âge du heartbeat ne dépasse pas :

```text
JOB_SYNC_INTERVAL_SECONDS + SCHEDULER_HEARTBEAT_GRACE_SECONDS
```

La marge vaut `300` secondes par défaut. Elle absorbe le temps normal de démarrage et d'exécution autour de l'intervalle planifié sans masquer un scheduler durablement bloqué.

## Échecs des commandes

Chaque commande planifiée produit une ligne technique structurée avec :

- le nom de l'étape ;
- son code de sortie ;
- sa durée en secondes.

Un échec ponctuel n'arrête pas la boucle. Il reste cependant visible dans les logs et le résumé du cycle indique si au moins une étape a échoué. Le worker de synchronisation de fond journalise aussi son code de sortie avant de retenter une seconde plus tard.

Cette politique est différente de la santé d'un connecteur individuel :

- **conteneur scheduler healthy** : la boucle progresse encore ;
- **fraîcheur scheduler** : le heartbeat est récent par rapport à l'intervalle configuré ;
- **santé connecteur** : `HEALTHY`, `WATCH`, `DEGRADED`, `BROKEN`, etc., calculée depuis les synchronisations réelles.

Un scheduler peut donc rester `healthy` après un échec de connecteur correctement observé. À l'inverse, un scheduler bloqué devient `unhealthy` lorsque son heartbeat dépasse le seuil de fraîcheur.

## Diagnostic

Pour inspecter le scheduler en exploitation :

```sh
docker compose ps scheduler
docker compose logs scheduler --tail=200
docker compose exec scheduler php ./scheduler-healthcheck.php
```

Le healthcheck retourne :

- `0` si le heartbeat est frais ;
- `1` s'il manque, est invalide ou trop ancien ;
- `2` si la configuration de l'intervalle ou de la marge est invalide.

Le heartbeat est éphémère et local au conteneur. Il ne sert pas de stockage métier et ne doit pas être sauvegardé.
