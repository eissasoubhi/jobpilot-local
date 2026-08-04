# ADR-0002 — DDD pragmatique et architecture hexagonale

- Statut : accepté
- Date : 2026-08-04

## Contexte

Les règles de matching, déduplication, préparation, rémunération et soumission doivent rester testables sans Gmail, Doctrine ou une plateforme précise. Une application excessive de DDD créerait cependant trop d’abstractions pour les écrans CRUD simples.

## Décision

Appliquer DDD et l’architecture hexagonale aux domaines où les règles et intégrations sont complexes : découverte, catalogue d’offres, matching, candidatures, messaging et CRM.

Utiliser :

- agrégats et value objects lorsque des invariants existent ;
- ports pour les bases, connecteurs, stockage, horloge et bus ;
- adaptateurs pour Doctrine, Gmail, API, scraping et fichiers ;
- services de domaine uniquement lorsqu’une règle n’appartient naturellement à aucun agrégat.

Les cas CRUD simples peuvent rester plus directs, sans créer artificiellement une classe par opération.

## Règles

- Le Domain ne dépend pas de Symfony, Doctrine ou HttpClient.
- Les entités Doctrine existantes seront migrées progressivement, pas dupliquées sans plan.
- Un DTO externe n’entre jamais directement dans le domaine.
- Un connecteur renvoie un modèle d’entrée normalisé.
- Les erreurs métier utilisent des exceptions ou résultats applicatifs explicites.

## Conséquences

Le code comporte davantage de contrats, mais les règles sont isolées, testables et indépendantes des plateformes.

## Alternatives rejetées

- Active Record généralisé : trop couplé à la persistance pour les workflows complexes.
- Clean Architecture appliquée uniformément : coût disproportionné sur les fonctionnalités simples.
