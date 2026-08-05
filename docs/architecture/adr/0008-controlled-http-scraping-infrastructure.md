# ADR 0008 — Infrastructure HTTP de collecte contrôlée

- Statut : accepté
- Date : 2026-08-05

## Contexte

JobPilot doit pouvoir collecter certaines pages publiques rendues côté serveur sans transformer chaque connecteur en script réseau autonome. Une implémentation naïve exposerait le produit à des boucles de requêtes, à des indisponibilités de sources, à des fuites vers des adresses internes, à des ruptures de quotas et à des collectes non autorisées.

La politique de conformité introduite par l’ADR 0007 bloque déjà les sources sans décision explicite. Il manque une infrastructure commune qui applique également les limites techniques pendant l’exécution.

## Décision

Les futurs connecteurs `SCRAPING_HTTP` utilisent `ControlledHttpScrapingClient`. Ils ne doivent pas appeler directement `HttpClientInterface` pour récupérer les pages métier.

Le client commun applique :

- une politique de conformité obligatoire ;
- un User-Agent explicite ;
- des timeouts et une taille maximale de réponse ;
- un nombre maximal de requêtes par synchronisation ;
- un quota journalier persistant ;
- un délai minimal entre requêtes ;
- des retries bornés avec backoff exponentiel et prise en charge de `Retry-After` ;
- un circuit breaker après erreurs répétées, avec ouverture immédiate après `401` ou `403` ;
- la validation de chaque URL et de chaque redirection ;
- le blocage des hôtes locaux, adresses privées, identifiants dans l’URL et ports non HTTP ;
- les requêtes conditionnelles `ETag` et `Last-Modified` ;
- un cache privé minimal du contenu nécessaire à une réponse `304` ;
- la vérification et le cache de `robots.txt` lorsque la politique du connecteur l’exige.

Les redirections automatiques du client HTTP sont désactivées. JobPilot les suit lui-même, au maximum trois fois, afin de valider chaque destination avant l’appel réseau.

L’état technique est conservé dans `var/private/http-scraping` et ne contient ni cookie, ni jeton, ni donnée d’authentification.

## Conséquences

### Positives

- les garde-fous sont identiques pour tous les scrapers HTTP ;
- `--force` ne contourne ni la politique ni les quotas ;
- les tests peuvent utiliser `MockHttpClient` sans dépendre d’un site réel ;
- les pannes répétées ne sollicitent pas indéfiniment une source ;
- les réponses inchangées réduisent le volume transféré.

### Négatives

- le stockage fichier convient au monolithe local mais devra être remplacé par Redis ou PostgreSQL lors d’un déploiement multi-instance ;
- la vérification DNS complète contre les rebonds DNS nécessitera un proxy sortant ou une résolution contrôlée en production ;
- `robots.txt` reste une règle opérationnelle et ne remplace pas une autorisation contractuelle.

## Alternatives rejetées

### Laisser chaque connecteur configurer Symfony HttpClient

Rejeté : les limites divergeraient et seraient faciles à oublier.

### Utiliser Playwright pour toutes les sources

Rejeté : coût plus élevé, surface d’attaque plus large et complexité inutile pour des pages HTML statiques.

### Activer immédiatement une plateforme réelle

Rejeté : l’infrastructure doit être testée et fusionnée avant le choix d’une source pilote dont le canal est explicitement autorisé.

## Validation

Les tests couvrent le blocage par politique, les retries, le cache conditionnel, le quota journalier persistant, le circuit breaker, `robots.txt` et le rejet d’une redirection privée. Aucun test CI ne contacte un site externe.
