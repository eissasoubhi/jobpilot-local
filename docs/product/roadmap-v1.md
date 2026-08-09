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
(CV + message + rémunération + lettre de motivation)
    ↓
Offres / Review Queue
    ↓
Review / Postuler / Ignorer / Modifier / Marquer envoyée
    ↓
Suivi
(réponses Gmail + statut + relances + timeline)
```

La lettre de motivation est maintenant toujours préparée. Le fait qu’une offre exige explicitement une lettre reste une métadonnée distincte et ne conditionne plus son existence.

## Epic 1 — Unified Offers Workspace

### Objectif

Faire de `Offres` le workspace principal et supprimer progressivement la nécessité de naviguer vers `Candidatures`.

### Déjà livré

- préparation automatique de chaque offre valide ;
- aucune soumission automatique dans le flux normal ;
- action « J’ai envoyé la candidature » sans confirmation navigateur ;
- score et explication de matching visibles ;
- filtres par source et gestion multi-sources ;
- statut de candidature et éléments préparés visibles depuis Offres ;
- CV sélectionné, message, rémunération et lettre disponibles dans les flux de revue/édition ;
- `ReviewDrawer` en place pour examiner une offre sans perdre le contexte ;
- édition des éléments préparés depuis le workspace ;
- décisions `Envoyée` et `Ne correspond pas` persistées sans action externe silencieuse ;
- vues distinctes pour les offres à traiter, envoyées et ignorées.

### Plan d’action restant

1. ajouter un mécanisme `Undo` uniquement pour les transitions réellement réversibles et sûres ;
2. conserver `Candidatures` comme fallback tant que la parité fonctionnelle complète n’est pas explicitement validée ;
3. rediriger ou retirer `Candidatures` seulement après couverture fonctionnelle et E2E complète ;
4. ajouter `Archiver` uniquement après définition d’un statut métier clair et de sa récupération.

## Epic 2 — Review Queue

### Objectif

Permettre de traiter rapidement les nouvelles offres déjà préparées, une par une, sans navigation inutile.

### Déjà livré

- vue dédiée `/offres/review` ;
- queue limitée aux candidatures `READY_TO_SUBMIT` ;
- ordre identique à la page Offres pour les candidatures présentes dans la queue ;
- carte pleine page exploitant l’espace disponible avec mission, contrat, contexte, score et raisons ;
- comparaison environnement/profil : stack principale, technologies en commun, must-haves manquants et autres écarts ;
- réutilisation des métadonnées IA existantes sans nouvel appel Gemini pour afficher la comparaison ;
- deux décisions principales toujours visibles : `Ne correspond pas` et `Envoyée` ;
- `Ne correspond pas` persisté comme `IGNORED_NOT_MATCH` ;
- `Envoyée` persisté comme `SUBMITTED` sans soumission externe ;
- auto-avance vers la prochaine candidature prête après une décision ;
- `Précédente` / `Suivante` conservés comme navigation secondaire ;
- raccourcis clavier `ArrowLeft` / `ArrowRight` hors contrôles interactifs ;
- focus clavier transféré au titre de la nouvelle offre après navigation ou décision ;
- progression annoncée aux technologies d’assistance ;
- statut manuel conservé comme interaction en deux étapes (`sélection` puis `Appliquer`).

### Plan d’action restant

1. mesurer le temps moyen de review uniquement après définition d’événements métier horodatés fiables ;
2. ajouter une priorisation explicite seulement si l’ordre Offres actuel ne suffit plus dans l’usage réel ;
3. ajouter d’autres décisions rapides uniquement lorsqu’un statut métier associé est défini et réversible.

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

Ne jamais rater une opportunité sur les sources configurées et **exploiter systématiquement les job boards publics accessibles sans authentification lorsque la collecte automatisée y est autorisée**.

La priorité produit n’est plus limitée aux API. Un site qui expose publiquement ses offres doit être évalué explicitement comme candidat au scraping HTTP ou navigateur. En revanche, l’absence de login ne suffit pas à autoriser la réutilisation automatisée : robots, CGU, restrictions d’extraction et limites techniques restent des garde-fous obligatoires.

### Plan d’action prioritaire

1. auditer **toutes les plateformes de la matrice d’acquisition**, pas seulement quelques sources pilotes ;
2. pour chaque source, vérifier l’existence d’une liste d’offres publique accessible sans session et enregistrer la date de cette revue ;
3. préférer API/RSS lorsqu’un canal officiel couvre correctement les mêmes offres ;
4. sinon, développer un scraper HTTP statique lorsque les pages sont publiques, stables et que ce mode est autorisé ;
5. utiliser Playwright dans un worker isolé lorsque les offres publiques nécessitent un rendu JavaScript et que cette automatisation est autorisée ;
6. livrer **une plateforme par PR** avec parseur versionné, fixtures HTML locales, idempotence, quotas, délais, backoff, cache, circuit breaker et diagnostics de santé ;
7. faire passer chaque entrée `UNDER_REVIEW` vers une décision explicite : `API`, `RSS`, `SCRAPING_HTTP`, `SCRAPING_BROWSER`, `GMAIL/EXTENSION` ou `BLOCKED`, avec justification ;
8. conserver Gmail/extension/import assisté lorsqu’un scraper planifié n’est pas autorisé ;
9. ne jamais contourner login, cookies privés, CAPTCHA, 401/403/429, restrictions robots ou limitation contractuelle ;
10. continuer l’ajout progressif jusqu’à ce que **chaque plateforme listée** possède soit un connecteur exploitable, soit une raison documentée expliquant pourquoi la collecte automatisée n’est pas activée.

La liste de référence est `docs/connectors/acquisition-matrix.md` et comprend notamment LinkedIn, Malt, Free-Work, Apec, Collective.work, Crème de la Crème, FreelanceRepublik, Comet, Cherry Pick, LeHibou, Mindquest, WeLoveDevs, Sept Lieues, Jean-Michel.io, Welcome to the Jungle, Cadremploi, HelloWork, Jobijoba, EURES, Freelance-Informatique, Indeed, Adzuna, Kicklox, Talent.com, SmartRecruiters, GetYourJob, Le Studio Tech, Meteojob, Michael Page, France Travail et LesJeudis.

## Epic 7 — Productivité et UX

### Objectif

Rendre le workflow rapide, calme, évident et cohérent.

### Déjà livré

- navigation clavier dans la Review Queue ;
- focus conservé sur l’offre active après navigation/décision ;
- annonces accessibles de la progression ;
- barre de décision principale toujours visible dans la Review Queue ;
- affichage local des offres avant synchronisation arrière-plan.

### Plan d’action

1. filtres instantanés : source, score, remote/hybride/site, contrat, rémunération, date, envoyé/non envoyé, archivé ;
2. recherche globale lorsque les modèles de recherche sont stabilisés ;
3. bulk actions seulement pour des opérations sûres et réversibles ;
4. étendre les raccourcis clavier uniquement aux actions dont le risque d’erreur est maîtrisé ;
5. command palette après stabilisation de la navigation ;
6. poursuivre loading skeletons, états vides utiles, responsive et accessibilité ;
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
