# Vue d’ensemble de l’architecture

## État actuel

JobPilot est un monolithe local composé de :

- une API Symfony 8.1 en PHP 8.4 ;
- un frontend Next.js 16 / React 19 ;
- PostgreSQL 17 ;
- un scheduler Docker exécutant des commandes Symfony ;
- un volume privé pour les CV et jetons ;
- des intégrations Arbeitnow, Adzuna, Gmail et extension Chrome ;
- des tests PHPUnit, Vitest, Testing Library et Playwright.

Le code backend est encore principalement organisé par type technique (`Controller`, `Entity`, `Service`). Cette organisation reste adaptée au MVP mais deviendra difficile à maintenir avec plusieurs connecteurs, traitements asynchrones et domaines métier.

## Architecture cible

JobPilot évolue vers un **modular monolith**. Le déploiement reste unique, mais le code est séparé par domaine métier.

```text
api/src/
  Shared/
  CandidateProfile/
  JobDiscovery/
  JobCatalog/
  Matching/
  Candidacy/
  Messaging/
  RecruiterCRM/
  Reporting/
```

Chaque module peut utiliser :

```text
Domain/
Application/
Infrastructure/
UI/Http/
```

La migration sera progressive. Une fonctionnalité existante n’est déplacée que lorsqu’elle est modifiée ou lorsque le déplacement réduit un risque concret.

## Responsabilités des couches

### Domain

- règles métier ;
- agrégats, entités et value objects ;
- événements métier ;
- interfaces de repository nécessaires au domaine ;
- aucune dépendance à Symfony, Doctrine, Gmail ou un site externe.

### Application

- cas d’utilisation ;
- commandes, requêtes et handlers ;
- orchestration des ports ;
- transactions et autorisations applicatives ;
- aucune logique dépendante d’un framework externe.

### Infrastructure

- Doctrine et PostgreSQL ;
- connecteurs API, RSS, scraping, Gmail et extension ;
- stockage de fichiers ;
- RabbitMQ, Redis et services externes ;
- implémentation des ports.

### UI/Http

- contrôleurs et DTO HTTP ;
- validation d’entrée ;
- sérialisation ;
- traduction des erreurs applicatives en réponses HTTP.

## Architecture d’exécution cible

```text
Next.js
   |
Symfony HTTP API
   |
PostgreSQL ---- Outbox
   |              |
   |           RabbitMQ
   |              |
   +---------- Workers
                  |
          Connecteurs externes
```

Les requêtes utilisateur rapides restent synchrones. Les synchronisations, classifications, déduplications coûteuses, scoring en volume et envois sont traités par workers lorsque cela améliore la fiabilité.

## Règles d’évolution

- Pas de réécriture globale.
- Pas de dépendance directe entre infrastructures de deux modules.
- Les intégrations externes passent par des ports explicites.
- Une commande modifie l’état ; une query le lit.
- Les événements ne remplacent pas les appels synchrones simples.
- Les événements critiques utilisent une stratégie fiable, notamment un outbox avant RabbitMQ.
- Toute extraction en microservice nécessite un ADR distinct.
