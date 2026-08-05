# Roadmap

Cette roadmap privilégie des livraisons petites, testables et réversibles.

## Phase 0 — Fondation — livrée

- Documenter l’état actuel et la cible.
- Définir les ADR, conventions et Definition of Done.
- Corriger les écarts entre comportement et documentation.
- Renforcer progressivement l’analyse statique et la CI.

## Phase 1 — Framework de connecteurs — livrée

- Contrat commun de connecteur.
- Registre des sources et capacités.
- Historique des synchronisations (`sync_run`).
- Diagnostic et page Connecteurs.
- Migration d’Arbeitnow et Adzuna.
- Filtre par source dans Offres.

## Phase 2 — Gmail intelligent — livrée

- Synchronisation planifiée et visible.
- Classification des alertes, propositions, réponses, refus et entretiens.
- Association aux offres et candidatures.
- Inbox intelligente et erreurs traçables.

## Phase 3 — Offre canonique — livrée

- Séparer offre canonique et occurrences de sources.
- Déduplication multi-sources déterministe et conservatrice.
- Historique de première et dernière apparition.
- Compteurs distincts pour import, fusion et occurrence connue.
- Badges, liens et filtre multi-sources dans l’interface.

## Phase 4 — Collecte multi-mode — en cours

### Fondation HTTP — livrée

- Client HTTP commun soumis à la politique de conformité.
- User-Agent, timeouts, taille maximale et redirections contrôlées.
- Quotas par synchronisation et par jour.
- Délai minimal, retries, backoff et `Retry-After`.
- Circuit breaker et arrêt après refus d’accès.
- Cache conditionnel `ETag` / `Last-Modified`.
- Vérification de `robots.txt` selon la politique.
- Tests entièrement locaux avec `MockHttpClient`.

### Premier canal syndiqué autorisé — livré

- Connecteur `symfony-jobs` fondé sur le lien RSS officiel du job board Symfony.
- Parseur commun RSS 2.0 et Atom.
- Extraction du contrat, du mode de travail, du lieu, de la langue, du salaire et du TJM.
- Identité idempotente par `guid` ou identifiant Atom.
- Quotas, retries, cache et circuit breaker fournis par le transport HTTP contrôlé.
- Fixtures locales ; aucun appel au site réel dans la CI.

### Santé et monitoring des connecteurs — livrés

- Contrat optionnel de versionnement des parseurs.
- Version du parseur conservée dans les diagnostics de synchronisation.
- Taux de normalisation et détection des résultats vides.
- Référence calculée depuis les six dernières exécutions.
- États `NO_DATA`, `HEALTHY`, `WATCH`, `DEGRADED` et `BROKEN`.
- Mesure de la complétude de chaque champ obligatoire et recommandé.
- Règles de qualité par connecteur avec fallback commun.
- Audit de fraîcheur du scheduler avec états `FRESH`, `DUE`, `OVERDUE` et `STALE`.
- Sortie JSON exploitable par un système de supervision externe.
- Visibilité complète dans la page Connecteurs et son historique.

### Prochaines livraisons

- Premier scraper HTML pilote uniquement après identification d’une source qui autorise explicitement ce mode de collecte.
- Notification externe configurable pour les ruptures confirmées.
- Worker navigateur Playwright isolé lorsque nécessaire.
- Ajout progressif des plateformes disposant d’un canal autorisé.

## Phase 5 — Candidature et CRM

- Pipeline complet des candidatures.
- CRM recruteurs et sociétés.
- Tâches de relance.
- Lettre de motivation séparée uniquement lorsqu’elle est demandée.

## Phase 6 — Reporting

- Conversion par source.
- Temps moyen de réponse.
- Taux d’entretien et d’acceptation.
- TJM et salaire proposés.
- Qualité du matching et corrections manuelles.

## Phase 7 — Production

- Images Docker immuables.
- PostgreSQL sauvegardé ou managé.
- RabbitMQ et workers séparés.
- Stockage objet pour les documents.
- Observabilité, alertes, restauration et procédure d’incident.

## Hors périmètre sans décision explicite

- Contournement de CAPTCHA ou de contrôle d’accès.
- Automatisation d’un login privé non autorisé.
- Rotation de proxys pour contourner des limitations.
- Microservices ou Kubernetes sans besoin opérationnel démontré.
