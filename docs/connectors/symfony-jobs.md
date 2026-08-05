# Symfony Jobs via le flux RSS officiel

## Décision

JobPilot utilise le flux de syndication officiellement exposé par la page **Symfony Jobs** au lieu de parcourir les pages HTML du job board.

La page publique affiche un lien **Jobs RSS** vers :

```text
https://symfony.com/jobs.rss
```

Ce canal est destiné à la lecture automatisée par des agrégateurs. Le connecteur est donc déclaré :

```text
mode : RSS
conformité : ALLOWED
revue : 2026-08-05
```

Cette décision ne constitue pas une autorisation générale de scraper les autres pages de `symfony.com`.

## Fonctionnement

Le connecteur `symfony-jobs` :

1. récupère un seul flux par synchronisation normale ;
2. passe par le client HTTP contrôlé de JobPilot ;
3. accepte les formats RSS 2.0 et Atom ;
4. transforme chaque entrée en occurrence canonique ;
5. réutilise le `guid` ou l’identifiant Atom pour garantir l’idempotence ;
6. conserve l’URL directe de l’offre ;
7. détecte le contrat, le télétravail, la langue et les rémunérations lisibles dans le flux.

Les fourchettes journalières sont enregistrées comme TJM. Les fourchettes annuelles sont enregistrées comme salaire. Une rémunération mensuelle ou horaire reste dans la description afin d’éviter une conversion inventée.

## Limites de collecte

La politique actuelle autorise au maximum :

```text
4 requêtes réseau par synchronisation
16 requêtes réseau par jour
1 seconde entre deux requêtes
```

La limite par synchronisation couvre l’appel principal, les retries bornés et une éventuelle redirection. Le scheduler normal n’utilise généralement qu’une requête toutes les six heures.

Le cache conditionnel `ETag` et `Last-Modified`, le quota, le circuit breaker, les timeouts et la validation des redirections sont fournis par l’infrastructure HTTP commune.

## Configuration

Le connecteur est actif par défaut :

```dotenv
SYMFONY_JOBS_RSS_ENABLED=1
```

Pour le désactiver :

```dotenv
SYMFONY_JOBS_RSS_ENABLED=0
```

Après une modification de la variable, recréer les services API et scheduler :

```bash
docker compose up -d --build --force-recreate api scheduler
```

## Synchronisation manuelle

```bash
docker compose exec api php bin/console app:jobs:sync --force --connector=symfony-jobs
```

La page **Connecteurs** expose ensuite le statut, les quotas, la date de revue, les compteurs d’import et les erreurs éventuelles.

## Tests

La CI ne contacte jamais Symfony. Elle utilise :

- une fixture RSS locale proche du flux attendu ;
- une fixture Atom pour vérifier le parseur générique ;
- un `MockHttpClient` Symfony ;
- un stockage temporaire isolé pour le quota et le cache.

Les tests couvrent le titre, l’entreprise, le contrat, le mode de travail, la localisation, la langue, le salaire, le TJM et le comportement désactivé.

## Revue future

La politique doit être réexaminée lorsque :

- le lien RSS disparaît de la page officielle ;
- le format du flux change ;
- les conditions d’utilisation applicables évoluent ;
- le connecteur reçoit plusieurs erreurs `401`, `403` ou `429` ;
- l’extraction tombe soudainement à zéro offre alors que le flux contient des entrées.
