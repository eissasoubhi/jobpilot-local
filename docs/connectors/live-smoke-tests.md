# Smoke tests réels des connecteurs

Les tests unitaires et les fixtures HTML de JobPilot ne contactent jamais les plateformes réelles. Pour détecter un changement de site, de flux, de réseau ou de configuration qui n'apparaît pas dans les fixtures, JobPilot fournit un smoke test **explicite hors CI**.

## Commande

Depuis l'environnement JobPilot :

```bash
docker compose exec -T api php bin/console app:connectors:smoke-test le-studio-tech --live
```

Pour une source personnalisée autorisée :

```bash
docker compose exec -T api php bin/console app:connectors:smoke-test custom-scraper-42 --live
```

L'option `--live` est obligatoire. Sans elle, la commande s'arrête avant tout appel réseau. En plus, la commande refuse systématiquement l'exécution lorsque `CI` ou `GITHUB_ACTIONS` est actif, même si `--live` a été fourni. Ces barrières empêchent un test ou workflow de contacter une source réelle par accident.

## Ce que le smoke test fait réellement

Le smoke test appelle le même pipeline que la synchronisation normale, avec le trigger `smoke` :

1. validation de l'état du connecteur ;
2. politique de collecte et configuration habituelles ;
3. requêtes réseau via le transport normal du connecteur ;
4. robots.txt, quotas, délais, circuit breaker et garde-fous SSRF lorsqu'ils s'appliquent ;
5. extraction et normalisation ;
6. déduplication canonique ;
7. filtre de profil ;
8. import/fusion des offres ;
9. historique `ConnectorSyncRun` et DLQ habituels.

Le paramètre `force=true` utilisé par le smoke test contourne uniquement la date de prochaine synchronisation. Il ne rend pas exécutable un connecteur désactivé, non configuré ou bloqué par sa politique de collecte.

**Le smoke test n'est pas un dry-run.** Une nouvelle offre valide peut être ajoutée au catalogue. Les offres déjà connues restent idempotentes grâce à l'identité source et à la déduplication canonique.

## Critères de succès

La commande retourne un code de sortie `0` seulement si :

- le connecteur a réellement été exécuté ;
- aucune erreur de collecte/import n'est remontée ;
- au moins une offre a été reçue ;
- au moins une issue canonique existe pour les offres reçues : nouvelle offre, fusion, occurrence connue ou rejet normal par le filtre profil.

Zéro résultat est considéré comme un échec de smoke par défaut, car cela peut révéler une rupture de parser ou de source.

Pour une source où zéro offre est réellement un état métier attendu :

```bash
docker compose exec -T api php bin/console app:connectors:smoke-test mon-connecteur --live --allow-zero
```

`--allow-zero` ne doit pas être utilisé pour masquer une source qui retournait habituellement des offres.

## Avant un smoke réel

Vérifier que :

- la source est active et configurée ;
- sa politique autorise encore la collecte utilisée ;
- pour une source personnalisée, l'autorisation utilisateur est toujours confirmée et la revue de conformité n'est pas expirée ;
- Browser/Playwright est disponible si le connecteur en dépend ;
- aucune connexion privée, cookie utilisateur ou contournement d'accès n'est nécessaire.

Les sources `EMAIL_OR_EXTENSION_ONLY`, `UNDER_REVIEW` ou autrement bloquées ne doivent pas être transformées en smoke HTTP/Browser pour contourner leur politique.

## Planification hors CI

Un smoke peut être planifié sur l'hôte JobPilot avec cron/systemd ou un ordonnanceur interne à l'infrastructure. Exemple hebdomadaire :

```cron
17 7 * * 1 cd /chemin/vers/jobpilot-local && docker compose exec -T api php bin/console app:connectors:smoke-test le-studio-tech --live >> var/log/connector-smoke.log 2>&1
```

La fréquence doit rester faible et cohérente avec les quotas de la source. Un smoke planifié ne remplace pas les synchronisations normales ni les alertes de fraîcheur.

## Interdiction en GitHub Actions

Cette commande ne doit pas être ajoutée au workflow GitHub Actions. La commande elle-même refuse aussi de démarrer si `CI` ou `GITHUB_ACTIONS` est actif. La CI doit continuer à utiliser :

- fixtures HTML locales ;
- `MockHttpClient` ;
- tests unitaires/contract tests ;
- worker Browser testé sans source réelle.

Le smoke réel est réservé à l'environnement JobPilot explicitement configuré et autorisé.

## Diagnostic

Chaque exécution apparaît dans l'historique Connecteurs avec `trigger=smoke`. En cas d'échec répété :

- consulter le run de synchronisation ;
- regarder la santé/qualité du connecteur ;
- vérifier une éventuelle DLQ `OPEN` ;
- comparer le HTML/flux réel avec une fixture anonymisée mise à jour ;
- désactiver la source ou le niveau Browser/IA concerné si nécessaire, conformément à l'ADR 0001.
