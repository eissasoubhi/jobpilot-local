# ADR-0001 — Modular monolith

- Statut : accepté
- Date : 2026-08-04

## Contexte

JobPilot doit intégrer plusieurs sources, Gmail, scraping, matching, candidatures et reporting. Le MVP actuel reste déployé localement et ne justifie pas la complexité réseau, opérationnelle et transactionnelle de microservices.

## Décision

Conserver un seul déploiement Symfony et une seule base PostgreSQL, tout en organisant le code en modules métier aux frontières explicites.

Les modules exposent leurs cas d’utilisation publics. Les dépendances aux frameworks et services externes restent dans Infrastructure.

## Conséquences positives

- transactions simples ;
- démarrage local facile ;
- moins d’infrastructure ;
- frontières métier visibles ;
- extraction future possible si un besoin réel apparaît.

## Risques

- frontières contournées par des accès directs aux entités ou tables ;
- dossier Shared surchargé ;
- couplage caché par le conteneur Symfony.

## Mesures

- règles de dépendances documentées ;
- revues de PR par module ;
- tests d’architecture à introduire ;
- ADR obligatoire avant extraction en microservice.

## Alternatives rejetées

### Microservices immédiats

Rejetés faute de besoins distincts de charge, disponibilité ou équipes autonomes.

### Conserver uniquement une organisation par couches techniques

Rejeté car l’ajout de connecteurs et workflows rendrait les dossiers Controller/Service/Entity difficiles à comprendre.
