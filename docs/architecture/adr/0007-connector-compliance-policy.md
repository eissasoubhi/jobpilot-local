# ADR 0007 — Politique de conformité des connecteurs

- Statut : accepté
- Date : 2026-08-05

## Contexte

JobPilot supporte plusieurs modes de collecte : API, Gmail, flux publics, extension, scraping HTTP et navigateur. Le fait qu’un adaptateur soit techniquement fonctionnel ne signifie pas que sa collecte automatique est autorisée.

Avant d’ajouter des scrapers, le registre doit distinguer clairement la configuration technique, le choix utilisateur d’activer une source et l’autorisation de lancer une collecte.

## Décision

Chaque connecteur planifié peut implémenter `GovernedJobSourceConnector` et fournir un `ConnectorPolicy`.

Les statuts reconnus sont :

```text
ALLOWED                  collecte automatique autorisée
AUTHORIZED_ONLY          collecte autorisée uniquement avec un accès ou consentement valide
EMAIL_OR_EXTENSION_ONLY  aucune collecte backend ; Gmail ou extension seulement
DISABLED                 collecte explicitement interdite
UNDER_REVIEW             collecte bloquée tant que la revue n’est pas terminée
```

La politique contient aussi, lorsqu’ils sont connus :

- la date de dernière revue ;
- une explication destinée à l’utilisateur ;
- le nombre maximal de requêtes par synchronisation ;
- le quota journalier ;
- le délai minimal entre requêtes ;
- l’obligation de respecter `robots.txt`.

Un connecteur qui n’implémente pas le contrat gouverné reçoit automatiquement le statut `UNDER_REVIEW`. C’est une règle **default deny**.

Le statut est synchronisé depuis le code vers `source_connector`, puis exposé dans l’API et la page **Connecteurs**. Ni `--force` ni le bouton de test ne peuvent contourner un statut bloquant.

## Conséquences

### Positives

- aucun nouveau scraper ne peut démarrer par oubli de revue ;
- l’utilisateur voit la différence entre « configuré » et « autorisé » ;
- les limites deviennent testables et exploitables par la future infrastructure HTTP ;
- la date et la justification de la décision restent traçables.

### Négatives

- chaque nouveau connecteur doit déclarer et maintenir sa politique ;
- une revue devenue obsolète ne se met pas à jour automatiquement ;
- cette étape ne fait pas encore respecter les quotas au niveau de chaque requête HTTP.

## Suite

La prochaine livraison utilisera cette politique dans un client HTTP contrôlé avec User-Agent explicite, timeouts, quota par synchronisation, délai minimal, backoff, circuit breaker et fixtures locales. Un premier scraper ne sera activé que pour une source dont le canal automatisé est explicitement autorisé.
