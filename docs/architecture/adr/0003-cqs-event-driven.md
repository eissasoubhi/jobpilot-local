# ADR-0003 — CQS et traitements event-driven

- Statut : accepté
- Date : 2026-08-04

## Contexte

Les futures synchronisations, classifications Gmail, déduplications, scorings et soumissions ne doivent pas bloquer les requêtes HTTP ni être couplées dans une boucle shell unique.

## Décision

Adopter CQS dans la couche Application :

- une Command représente une intention qui peut modifier l’état ;
- une Query retourne une vue sans effet métier ;
- les handlers restent petits et orchestrent les ports nécessaires.

Introduire progressivement Symfony Messenger et RabbitMQ pour les traitements indépendants ou longs.

Utiliser un Outbox Pattern avant de publier les événements critiques liés à une transaction PostgreSQL.

## Ce qui reste synchrone

- lecture d’une page ;
- validation d’un formulaire ;
- aperçu d’un e-mail ;
- opérations simples nécessitant un résultat immédiat.

## Ce qui devient asynchrone

- synchronisation d’une source ;
- traitement de lots d’offres ;
- classification Gmail ;
- scoring en volume ;
- envoi autorisé et traçable ;
- notifications et projections statistiques.

## Garanties attendues

- idempotence des handlers ;
- identifiant de corrélation ;
- retry limité avec backoff ;
- dead-letter queue ;
- journal d’exécution ;
- aucun double envoi après retry.

## CQRS complet

Deux modèles de données séparés ne sont pas retenus maintenant. Des projections dédiées pourront être ajoutées pour le dashboard et le reporting lorsque les requêtes le justifieront.
