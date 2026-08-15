# Documentation JobPilot

Cette documentation est la référence produit, métier, technique et opérationnelle de JobPilot.

## Produit

- [Vision](product/vision.md)
- [Roadmap](product/roadmap.md)
- [Filtres de statut des candidatures](product/application-status-filters.md)
- [Priorité et urgence des messages](product/message-urgency.md)

## Architecture

- [Vue d’ensemble](architecture/overview.md)
- [Context map](architecture/context-map.md)
- [ADR](architecture/adr/)

## Catalogue d’offres

- [Offres canoniques et occurrences de sources](job-catalog/canonical-offers.md)

## Connecteurs

- [Contrat, exploitation et ajout d’une source](connectors/overview.md)
- [Matrice d’acquisition des plateformes](connectors/acquisition-matrix.md)
- [Santé des connecteurs et détection de rupture](connectors/health-monitoring.md)
- [Profils de qualité propres aux connecteurs](connectors/quality-profiles.md)
- [Infrastructure de scraping HTTP contrôlé](connectors/http-scraping.md)
- [France Travail via l’API Offres d’emploi v2](connectors/france-travail.md)
- [SmartRecruiters via la Posting API officielle](connectors/smartrecruiters.md)
- [Symfony Jobs via le flux RSS officiel](connectors/symfony-jobs.md)
- [Talent.com — Publisher Job API planifiée](connectors/talent-com.md)
- [Connecteur Gmail et Inbox intelligente](connectors/gmail.md)
- [Free-Work via Gmail et import assisté](connectors/free-work.md)

## Développement

- [Conventions](development/conventions.md)
- [Stratégie de tests](development/testing.md)

## Exploitation

- [Socle de production](operations/production-baseline.md)

## Qualité

- [Definition of Done](definition-of-done.md)

## Règles de maintenance

- Une fonctionnalité n’est pas terminée sans tests et documentation adaptés.
- Une décision structurante doit être documentée dans un ADR.
- Les documents décrivent l’état réellement livré. Les éléments futurs sont explicitement marqués comme cibles.
- Une PR ne modifie pas plusieurs domaines sans justification claire.
