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

## Phase 4 — Collecte multi-mode

- Scraping HTTP contrôlé.
- Premier scraper pilote avec fixtures HTML.
- Worker navigateur Playwright isolé lorsque nécessaire.
- Quotas, backoff, circuit breaker et détection de rupture.
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
