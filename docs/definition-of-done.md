# Definition of Done

Une fonctionnalité est terminée uniquement lorsque les éléments pertinents ci-dessous sont satisfaits.

## Produit et métier

- Le problème utilisateur et la valeur attendue sont décrits.
- Les règles métier et cas limites sont explicites.
- Les critères d’acceptation sont testables.
- Le comportement en cas d’erreur ou d’indisponibilité est défini.
- Les actions automatiques et celles nécessitant une validation humaine sont distinguées.

## Architecture et code

- Le module propriétaire est identifié.
- Les dépendances respectent les frontières documentées.
- Une décision structurante possède un ADR.
- Le code est typé, lisible et sans duplication évitable.
- Les appels externes ont timeout, erreurs et stratégie de retry adaptée.
- Les opérations rejouables sont idempotentes.
- Les données sensibles ne sont ni exposées ni journalisées.

## Base de données

- La migration est cohérente et testée.
- Les contraintes protègent les invariants importants.
- Les index répondent à des requêtes identifiées.
- La stratégie de rollback ou de compatibilité est décrite.

## UX/UI

- Les états chargement, vide, succès, partiel et erreur sont couverts.
- Le message d’erreur indique une action possible.
- Les actions irréversibles sont confirmées.
- Le parcours clavier et les libellés accessibles sont vérifiés.
- L’interface mobile reste utilisable sur les écrans concernés.

## Tests

- Tests unitaires des règles modifiées.
- Tests d’intégration des frontières importantes.
- Test de non-régression pour chaque bug corrigé.
- Test de contrat pour tout nouveau connecteur.
- E2E uniquement sur les parcours critiques concernés.
- Aucun test CI ne dépend d’un service externe réel.

## DevOps et exploitation

- Logs et identifiants de corrélation suffisants.
- Métriques ou diagnostic disponibles pour les traitements asynchrones et connecteurs.
- Variables et secrets documentés.
- Healthcheck mis à jour lorsque nécessaire.
- Impacts de déploiement, migration et rollback documentés.

## Documentation

- Documentation utilisateur mise à jour.
- Documentation développeur mise à jour.
- Limites et hypothèses explicites.
- Exemple de configuration sans secret réel.

## Livraison

- CI entièrement verte.
- La PR décrit problème, solution, risques, tests et rollback.
- Aucun secret ou fichier généré n’est commité.
- La PR est mergeable.
- Le merge est effectué seulement après validation explicite du propriétaire du projet.
