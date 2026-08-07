# JobPilot V1 — Personal ATS

## Statut

Roadmap stratégique active. Cette version est le cadre produit principal tant que les critères de sortie V1 ne sont pas atteints.

Le fichier `docs/product/roadmap.md` reste la vue opérationnelle de l’état courant. Ce document décrit la cible V1, l’ordre des Epics et les critères permettant de décider quand V1 est suffisamment complète.

## Vision

JobPilot V1 doit être un ATS personnel utilisable tous les jours pour découvrir, examiner, préparer, envoyer et suivre des candidatures sans perdre d’opportunité.

Le modèle mental principal est :

```text
Une offre = un espace de travail unique
```

Au niveau UX, l’offre est l’objet central. Les concepts métier backend restent séparés (`JobOffer`, `Application`, `Company`, `Recruiter`, `Message`, `Document`, événements de timeline, etc.) afin d’éviter une entité métier monolithique.

## North Star

Chaque évolution V1 doit améliorer au moins un de ces objectifs :

1. réduire le temps nécessaire pour postuler ;
2. améliorer la qualité des candidatures ;
3. augmenter le taux d’entretien ;
4. améliorer la visibilité sur la recherche d’emploi.

## Principes produit

- chaque offre valide est analysée et préparée automatiquement ;
- le score aide à décider mais ne bloque pas la préparation ;
- l’utilisateur garde toujours la décision finale ;
- aucune soumission externe silencieuse ;
- les actions fréquentes doivent tendre vers un clic ;
- les statuts sont mis à jour immédiatement lorsque cela est sûr ;
- éviter les modales de confirmation inutiles ;
- une page ne doit jamais laisser l’utilisateur sans prochaine action claire ;
- les sources, authentifications, CAPTCHA, quotas et conditions d’utilisation sont toujours respectés.

## Workflow cible V1

```text
Nouvelle offre
    ↓
Analyse automatique
    ↓
Préparation automatique
(CV + message + rémunération + lettre si explicitement demandée)
    ↓
Offres
    ↓
Review / Postuler / Ignorer / Archiver / Modifier / Marquer envoyée
    ↓
Suivi
(réponses Gmail + statut + relances + timeline)
```

## Epic 1 — Unified Offers Workspace

### Objectif

Faire de `Offres` le workspace principal et supprimer progressivement la nécessité de naviguer vers `Candidatures`.

### Déjà livré

- préparation automatique de chaque offre valide ;
- aucune soumission automatique dans le flux normal ;
- action « J’ai envoyé la candidature » sans confirmation navigateur ;
- score et explication de matching déjà visibles ;
- filtres par source et gestion multi-sources existants.

### Plan d’action

1. afficher sur chaque offre le statut de candidature et les éléments préparés ;
2. afficher CV sélectionné, message, rémunération et lettre lorsqu’elle est demandée ;
3. ajouter un `ReviewDrawer` latéral sans navigation de page ;
4. déplacer dans `Offres` les actions aujourd’hui disponibles dans `Candidatures` ;
5. permettre l’édition des éléments préparés depuis le workspace ;
6. fournir `Postuler`, `Ignorer`, `Archiver`, `Marquer envoyée` au même endroit ;
7. utiliser des mises à jour instantanées avec toast et `Undo` lorsqu’elles sont réversibles ;
8. conserver `Candidatures` comme fallback tant que la parité fonctionnelle n’est pas atteinte ;
9. rediriger ou retirer `Candidatures` seulement après couverture fonctionnelle et E2E complète.

## Epic 2 — Review Queue

### Objectif

Permettre de traiter rapidement les nouvelles offres déjà préparées, une par une, sans navigation inutile.

### Plan d’action

1. ajouter une vue ou un mode `Review Queue` ;
2. prioriser les offres nécessitant une décision ;
3. ouvrir automatiquement l’offre suivante après une décision ;
4. supporter `Next`, `Previous`, `Apply`, `Ignore`, `Archive` et `Mark sent` ;
5. ajouter ensuite les raccourcis clavier lorsque le comportement est stabilisé ;
6. mesurer le temps moyen de review d’une offre.

## Epic 3 — Timeline métier fiable

### Objectif

Construire une histoire horodatée fiable pour chaque opportunité afin de débloquer le suivi et les analytics.

### Événements cibles

- offre importée ;
- offre fusionnée / occurrence ajoutée ;
- préparation créée ;
- préparation modifiée ;
- candidature marquée envoyée ;
- réponse reçue ;
- refus ;
- entretien ;
- relance ;
- acceptation ou autre résultat final lorsque les statuts correspondants sont définis.

### Plan d’action

1. définir explicitement les événements métier à conserver ;
2. distinguer activité technique et événement métier ;
3. persister les événements de manière append-only ;
4. exposer une timeline par offre ;
5. associer Gmail aux événements pertinents ;
6. utiliser ces timestamps comme seule base des métriques temporelles.

## Epic 4 — Company & Recruiter CRM

### Objectif

Faire des sociétés et recruteurs des objets de contexte accessibles depuis une offre.

### Plan d’action

1. consolider la page société : offres, candidatures, recruteurs, notes et historique ;
2. consolider la fiche recruteur : société, e-mail, téléphone, LinkedIn si disponible, conversations, candidatures et notes ;
3. permettre la navigation directe depuis l’Offer Workspace ;
4. définir les règles de fusion manuelle des organisations avant toute implémentation destructive ;
5. conserver séparément les corrections manuelles et les valeurs sources.

## Epic 5 — Analytics fiables

### Objectif

Transformer le reporting existant en informations actionnables basées sur des événements fiables.

### Déjà livré

- conversion par source ;
- conversion par contrat ;
- conversion par mode de travail ;
- taux de candidature, réponse et entretien ;
- TJM et salaire proposés moyens ;
- métriques de matching.

### Plan d’action

1. temps moyen de réponse ;
2. temps entre découverte et candidature ;
3. taux d’entretien par source ;
4. efficacité des recruteurs ;
5. tendances de rémunération ;
6. technologies les plus demandées ;
7. taux d’acceptation uniquement après définition d’un statut fiable ;
8. relier chaque métrique à sa source de données et à sa définition métier.

## Epic 6 — Collecte autorisée et fiable

### Objectif

Ne jamais rater une opportunité sur les sources configurées sans contourner les règles d’accès.

### Plan d’action

1. continuer d’ajouter les API, RSS, Gmail et imports assistés autorisés ;
2. n’ajouter un scraper HTML qu’après validation explicite de la source et du mode de collecte ;
3. utiliser Playwright uniquement pour des pages publiques lorsque nécessaire et autorisé ;
4. conserver quotas, backoff, circuit breakers, santé et fraîcheur des connecteurs ;
5. ne jamais contourner login, CAPTCHA, 401/403/429, robots policy opérationnelle ou limitation contractuelle.

## Epic 7 — Productivité et UX

### Objectif

Rendre le workflow rapide, calme, évident et cohérent.

### Plan d’action

1. filtres instantanés : source, score, remote/hybride/site, contrat, rémunération, date, envoyé/non envoyé, archivé ;
2. recherche globale lorsque les modèles de recherche sont stabilisés ;
3. bulk actions seulement pour des opérations sûres et réversibles ;
4. raccourcis clavier après stabilisation des actions ;
5. command palette après stabilisation de la navigation ;
6. loading skeletons, états vides utiles, responsive et accessibilité ;
7. design system progressif (`PageHeader`, `OfferCard`, `ReviewDrawer`, `FilterBar`, `StatusBadge`, `ActionToolbar`, etc.) ;
8. introduire les bibliothèques frontend uniquement lorsqu’un besoin concret les justifie, sans refonte big-bang.

## Epic 8 — Production hardening

### Objectif

Rendre V1 exploitable quotidiennement avec une qualité de production.

### Plan d’action

1. images Docker immuables ;
2. stockage persistant et sauvegardes testées ;
3. gestion sûre des secrets et jetons OAuth ;
4. observabilité des synchronisations et traitements asynchrones ;
5. procédures de restauration et d’incident ;
6. qualité CI : lint, type-check, unit, intégration, E2E, migrations et Compose ;
7. accessibilité et performance mesurées sur les parcours principaux.

## Architecture frontend V1

La cible est une structure orientée fonctionnalités, introduite progressivement :

```text
features/
  offers/
  companies/
  recruiters/
  analytics/
  documents/
  shared/
```

Outils envisagés lorsque nécessaires : TanStack Query, TanStack Table, Tailwind/shadcn, React Hook Form + Zod, Framer Motion, cmdk et panneaux redimensionnables. Aucun de ces outils ne justifie à lui seul une migration globale.

## Definition of Done d’un PR V1

- un objectif clair et petit ;
- comportement métier explicite ;
- tests adaptés ;
- documentation mise à jour ;
- aucune régression connue ;
- backward compatibility sauf décision explicite ;
- aucune dégradation de conformité connecteur ;
- CI verte avant merge ;
- le projet est au moins aussi lisible après le PR qu’avant.

## Critères de sortie V1

V1 est considérée complète lorsque l’utilisateur peut confortablement :

- découvrir les offres automatiquement depuis les sources autorisées configurées ;
- examiner une offre depuis un workspace principal ;
- voir immédiatement ce qui est préparé et ce qui manque ;
- décider de postuler, ignorer ou archiver sans navigation inutile ;
- enregistrer une candidature envoyée en un geste ;
- suivre les réponses et entretiens ;
- utiliser une timeline fiable ;
- consulter des analytics utiles ;
- ne pas perdre une opportunité ou une relance ;
- utiliser JobPilot quotidiennement sans dépendre d’un tableur ou d’un outil de suivi parallèle.

## Explicitement différé vers V2/V3

- assistant IA contextuel omniprésent ;
- comparaison intelligente de plusieurs offres ;
- coaching d’entretien avancé ;
- conseiller de négociation ;
- networking, learning et certifications ;
- Career OS complet.
