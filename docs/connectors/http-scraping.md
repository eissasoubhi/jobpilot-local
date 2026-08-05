# Scraping HTTP contrôlé

## Périmètre

Cette infrastructure sert uniquement aux pages publiques rendues côté serveur et aux sources dont la collecte automatisée a été explicitement autorisée dans leur `ConnectorPolicy`.

Elle ne doit pas être utilisée pour :

- se connecter à un compte privé ;
- réutiliser des cookies de navigateur ;
- contourner un CAPTCHA ou une protection anti-bot ;
- masquer l’automatisation ;
- ignorer une interdiction contractuelle ;
- rendre une page JavaScript complexe. Dans ce dernier cas, une décision séparée est nécessaire avant un worker Playwright.

## Classes principales

```text
ControlledHttpScrapingClient
HttpScrapingRequest
HttpScrapingResult
HttpScrapingStateStore
RobotsTxtGuard
```

Un connecteur HTTP construit un `HttpScrapingRequest` avec :

- son code stable ;
- l’URL publique à récupérer ;
- sa politique de conformité ;
- un timeout borné ;
- un nombre de retries borné ;
- une limite de taille de réponse ;
- un User-Agent explicite.

Le connecteur reçoit ensuite un `HttpScrapingResult` contenant l’URL finale validée, le statut, le corps, les en-têtes, le nombre réel de requêtes et l’indication d’un contenu repris du cache après `304`.

## Garde-fous appliqués

### Avant la requête

- statut de conformité autorisant la collecte ;
- URL HTTP ou HTTPS valide ;
- aucun identifiant dans l’URL ;
- ports limités à `80` et `443` ;
- blocage de `localhost`, des suffixes internes et des IP privées ou réservées ;
- circuit breaker fermé ;
- quota de synchronisation et quota journalier disponibles ;
- contrôle de `robots.txt` lorsque la politique l’impose ;
- respect du délai minimal depuis la requête précédente.

### Pendant la requête

- User-Agent JobPilot explicite ;
- timeout strict ;
- redirections automatiques désactivées ;
- validation de chaque redirection, au maximum trois ;
- retries seulement pour `408`, `425`, `429`, `500`, `502`, `503` et `504` ;
- backoff exponentiel ou `Retry-After` borné ;
- taille de réponse limitée.

### Après la requête

- remise à zéro du compteur d’échecs après succès ;
- cache privé de `ETag`, `Last-Modified` et du corps nécessaire à un futur `304` ;
- ouverture du circuit après trois échecs consécutifs ;
- ouverture immédiate pendant une heure après `401` ou `403`.

## Stockage local

Les fichiers sont écrits sous :

```text
api/var/private/http-scraping/state
api/var/private/http-scraping/robots
```

Ils contiennent uniquement :

- compteurs de requêtes ;
- horodatage de la dernière requête ;
- état du circuit breaker ;
- validateurs HTTP ;
- corps de réponse public mis en cache ;
- cache de `robots.txt`.

Aucun cookie, mot de passe, jeton OAuth ou en-tête d’authentification ne doit y être écrit.

## Règles pour un nouveau connecteur

1. documenter l’autorisation et la date de revue ;
2. déclarer `ConnectorMode::SCRAPING_HTTP` ;
3. implémenter `GovernedJobSourceConnector` ;
4. définir des limites non nulles adaptées à la source ;
5. utiliser exclusivement `ControlledHttpScrapingClient` pour les pages métier ;
6. parser des fixtures HTML anonymisées en test ;
7. rendre l’import idempotent par `sourceCode + externalId` ;
8. ne jamais contacter le site réel depuis la CI ;
9. exposer les erreurs dans l’historique du connecteur sans enregistrer de données sensibles ;
10. garder le connecteur désactivé tant que sa politique n’est pas `ALLOWED` ou `AUTHORIZED_ONLY` avec les prérequis satisfaits.

## Tests obligatoires d’un connecteur concret

- page de liste nominale ;
- page de détail nominale lorsque nécessaire ;
- champ manquant ;
- HTML modifié ou sélecteur cassé ;
- pagination et limite de requêtes ;
- idempotence ;
- réponse `429` ;
- réponse vide inhabituelle ;
- absence totale d’appel réseau externe en CI.

## Limite actuelle

Le stockage des quotas et du circuit breaker est local au conteneur API. Il est adapté à JobPilot local et à une seule instance. Un déploiement horizontal devra déplacer cet état vers Redis ou PostgreSQL avant d’activer plusieurs workers concurrents.
