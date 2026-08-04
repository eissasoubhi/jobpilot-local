# Conventions de développement

## Général

- Code et identifiants techniques en anglais ; interface et documentation utilisateur en français.
- Méthodes courtes avec responsabilité claire.
- Pas d’abréviation métier ambiguë.
- Pas de commentaire décrivant simplement le code ; commenter la raison d’une décision non évidente.
- Pas de donnée sensible dans les logs, fixtures ou captures de test.

## Backend PHP

- `declare(strict_types=1);` dans les fichiers PHP.
- Types explicites pour les paramètres, retours et propriétés.
- DTO immuables lorsque possible.
- Value objects pour les concepts ayant validation ou comportement : source, e-mail, rémunération, score, identifiant externe.
- Les contrôleurs traduisent HTTP vers un cas d’utilisation ; ils ne portent pas les règles métier.
- Les repositories retournent des objets métier ou projections explicites.
- Les transactions sont définies au niveau du cas d’utilisation.
- Les appels réseau sont encapsulés dans des adaptateurs.
- Les dates métier utilisent une horloge injectable dans les tests.

## Commandes et queries

Nommage recommandé :

```text
SynchronizeConnectorCommand
SynchronizeConnectorHandler
ListJobOffersQuery
ListJobOffersHandler
```

Une commande ne retourne pas une vue complexe. Elle peut retourner un identifiant ou un résultat d’exécution minimal.

Une query ne modifie pas l’état métier et ne déclenche pas de synchronisation implicite.

## Connecteurs

- Un adaptateur par source et par mode de collecte.
- Aucun sélecteur HTML dans le domaine.
- Les réponses externes sont converties en DTO normalisés.
- Timeouts, quotas et erreurs sont explicites.
- Toute synchronisation est idempotente.
- Les payloads bruts ont une rétention définie.

## Frontend

- TypeScript strict.
- Composants orientés présentation ; les appels API et états complexes sont isolés.
- Pas d’état dupliqué pouvant être dérivé.
- Les formulaires affichent erreurs de champ et erreur globale.
- Tous les états sont conçus : chargement, vide, succès, partiel et erreur.
- Les boutons irréversibles demandent confirmation et indiquent le résultat.
- Navigation clavier et libellés accessibles obligatoires sur les parcours critiques.

## Base de données

- Migration dédiée par changement cohérent.
- Migration réversible lorsque techniquement raisonnable.
- Index documenté lorsqu’il répond à une requête connue.
- Contrainte unique pour les invariants qui doivent résister à la concurrence.
- JSONB réservé aux payloads variables ou bruts, pas aux concepts métier structurés.

## Git et PR

- Une branche par objectif.
- Une PR suffisamment petite pour être revue.
- Description avec problème, solution, risques, tests, migrations et rollback.
- Aucun merge sans CI verte et validation explicite.
