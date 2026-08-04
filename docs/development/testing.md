# Stratégie de tests

## Pyramide

### Tests de domaine

Rapides, sans Symfony, base de données ni réseau. Ils couvrent :

- règles de matching ;
- calculs de rémunération ;
- transitions d’état ;
- déduplication ;
- idempotence ;
- génération de messages ;
- invariants des value objects.

### Tests d’application

Ils valident les commandes et queries avec des ports simulés ou des adaptateurs en mémoire.

### Tests d’intégration

Ils utilisent les vraies briques nécessaires :

- PostgreSQL pour repositories, contraintes et concurrence ;
- conteneur Symfony pour contrôleurs et configuration ;
- faux serveur HTTP ou `MockHttpClient` pour les APIs ;
- stockage temporaire pour les documents.

### Tests de contrat des connecteurs

Chaque connecteur doit vérifier :

- normalisation des champs ;
- pagination ;
- comportement sur données manquantes ;
- quota, timeout et retry ;
- déduplication et idempotence ;
- erreur lorsque le format externe change.

Les scrapers utilisent des fixtures HTML locales anonymisées. La CI ne dépend jamais d’un site réel.

### Tests frontend

Vitest et Testing Library couvrent la logique de composants, les formulaires et états d’erreur.

### Tests E2E

Playwright couvre seulement les parcours critiques :

- profil et CV ;
- import et préparation d’une offre ;
- aperçu et envoi de test Gmail simulé ;
- candidature guidée ;
- filtres et diagnostics des connecteurs ;
- migrations fonctionnelles majeures.

## Règles anti-flakiness

- Pas d’attente arbitraire lorsque l’on peut attendre un état observable.
- Données isolées par test.
- Horloge contrôlable.
- Aucun appel externe réel dans la CI.
- Sélecteurs accessibles ou `data-testid` réservé aux éléments sans rôle stable.
- Une correction de bug ajoute un test qui échouait avant le correctif.

## CI minimale

```text
Composer validation
Symfony configuration
Doctrine migrations on empty database
Doctrine schema validation
PHPUnit
TypeScript type-check
ESLint
Vitest
Next.js build
Docker Compose validation
Playwright Chromium
```

Les étapes PHPStan, style de code, scan de dépendances et contrôle d’architecture seront ajoutées dans une PR dédiée afin de traiter les écarts existants sans masquer les régressions.

## Tests manuels

Un plan manuel reste nécessaire pour :

- OAuth Google réel ;
- réception d’un e-mail réel ;
- smoke tests planifiés des plateformes ;
- restauration de sauvegarde ;
- vérification des permissions et quotas externes.

Les résultats manuels doivent être consignés dans la PR ou le runbook correspondant.
