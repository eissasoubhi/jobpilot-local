# JobPilot V2 — AI-first ATS

## Statut

Roadmap stratégique future. V2 commence après stabilisation de la V1 Personal ATS et de ses données métier principales.

## Vision

JobPilot V2 transforme le Personal ATS en assistant IA contextuel. L’IA ne remplace jamais la décision humaine : elle explique, recommande, compare, prépare et aide à agir plus vite.

Le principe directeur est :

```text
AI is an assistant, not a replacement.
```

L’utilisateur garde toujours la validation finale pour postuler, répondre, relancer, négocier ou modifier ses documents.

## Prérequis V1

Avant d’activer les capacités V2, JobPilot doit disposer de données structurées et fiables :

- offres canoniques ;
- candidatures préparées et envoyées ;
- sociétés et recruteurs ;
- conversations Gmail associées ;
- timeline métier horodatée ;
- événements d’entretien et de réponse ;
- historique de rémunération et de matching ;
- documents approuvés par l’utilisateur.

## North Star V2

1. améliorer la qualité de décision avant candidature ;
2. réduire le temps nécessaire pour adapter une candidature ;
3. améliorer la préparation aux entretiens et négociations ;
4. rendre l’historique JobPilot exploitable sans copier-coller du contexte dans un chatbot externe.

## Epic 1 — AI context layer

### Objectif

Permettre à l’IA de comprendre automatiquement le contexte de l’objet actuellement consulté.

### Plan d’action

1. définir un contexte IA explicite et minimal par offre ;
2. inclure uniquement les données utiles : description, matching, société, recruteur, documents, historique, conversations liées et timeline ;
3. tracer les sources de contexte utilisées ;
4. empêcher l’invention de données absentes ;
5. protéger les données personnelles dans les logs et traces ;
6. permettre à l’utilisateur de voir quelles informations ont servi à une recommandation.

## Epic 2 — Explain & Decide

### Objectif

Transformer le score de matching en aide à la décision compréhensible.

### Capacités

- expliquer pourquoi le score vaut 72 %, 82 %, 87 %, etc. ;
- compétences fortes ;
- compétences manquantes ;
- éléments neutres ou incertains ;
- recommandation contextualisée ;
- signaler les critères bloquants réels séparément du score ;
- comparer deux offres ;
- expliquer les compromis entre salaire, stack, localisation, contrat et historique.

### Plan d’action

1. fiabiliser d’abord l’explication déterministe existante ;
2. ajouter une couche IA optionnelle au-dessus des faits structurés ;
3. afficher faits et recommandations séparément ;
4. ajouter des tests de non-invention sur les champs sensibles ;
5. permettre un feedback utilisateur sur la recommandation.

## Epic 3 — Application copilot

### Objectif

Permettre de modifier rapidement les éléments préparés sans repartir de zéro.

### Capacités

- « Réécris le message plus court » ;
- « Plus direct » ;
- « Plus chaleureux » ;
- « Génère une autre version » ;
- « Explique pourquoi tu as choisi ce CV » ;
- « Adapte la lettre uniquement parce que l’offre l’exige » ;
- « Compare cette candidature avec une précédente ».

### Garde-fous

- ne jamais modifier silencieusement un CV approuvé ;
- distinguer suggestion, brouillon et document validé ;
- ne jamais envoyer automatiquement le contenu généré ;
- conserver l’historique des versions utiles sans dupliquer inutilement les données.

## Epic 4 — Interview coach

### Objectif

Préparer un entretien à partir du contexte réel de l’offre et de l’historique utilisateur.

### Capacités

- questions techniques probables ;
- questions métier ;
- points du CV à mettre en avant ;
- écarts de compétences à préparer ;
- pitch contextualisé ;
- questions à poser au client ou recruteur ;
- préparation d’un entretien déjà planifié dans la timeline.

### Plan d’action

1. introduire une fiche de préparation d’entretien ;
2. générer les questions depuis l’offre, pas depuis un prompt générique ;
3. permettre de marquer les sujets à réviser ;
4. relier les résultats d’entretien à la timeline et aux analytics.

## Epic 5 — Salary & negotiation advisor

### Objectif

Aider à décider et négocier la rémunération en s’appuyant sur les données déjà disponibles dans JobPilot.

### Capacités

- expliquer la proposition TJM/salaire ;
- comparer avec l’historique personnel ;
- comparer plusieurs offres ;
- préparer une réponse de négociation ;
- signaler lorsqu’une donnée marché externe manque au lieu de l’inventer.

### Plan d’action

1. fiabiliser les données structurées de rémunération ;
2. séparer historique personnel et éventuelles données marché ;
3. ajouter des recommandations avec explication ;
4. exiger une validation utilisateur avant toute réponse envoyée.

## Epic 6 — Company & Recruiter intelligence

### Objectif

Utiliser le contexte CRM pour éviter les candidatures aveugles.

### Capacités

- résumer l’historique avec une société ;
- résumer les échanges avec un recruteur ;
- rappeler les candidatures précédentes ;
- afficher le temps de réponse historique ;
- signaler une relation déjà existante ;
- préparer une relance adaptée ;
- expliquer pourquoi une société peut être intéressante selon les données réellement présentes.

## Epic 7 — Follow-up assistant

### Objectif

Aider à déterminer quoi relancer et quand, sans envoyer silencieusement.

### Plan d’action

1. s’appuyer sur la timeline et les règles de relance V1 ;
2. proposer les candidatures nécessitant une action ;
3. générer un brouillon de relance ;
4. expliquer la raison de la relance ;
5. garder la décision et l’envoi sous contrôle utilisateur.

## Epic 8 — AI workspace

### Objectif

Ajouter un panneau IA contextuel dans le workspace courant.

Exemples de requêtes :

- « Pourquoi seulement 72 % ? » ;
- « Compare cette offre à l’autre » ;
- « Réécris l’e-mail » ;
- « Prépare mon entretien » ;
- « Résume cette société » ;
- « Ai-je déjà parlé à ce recruteur ? » ;
- « Dois-je négocier ? ».

### UX cible

- sidebar ou drawer contextualisé ;
- pas de copier-coller du contenu de l’offre ;
- contexte de l’offre courante chargé automatiquement ;
- actions proposées mais jamais exécutées sans intention utilisateur ;
- réponses reliées aux données sources lorsque possible.

## Epic 9 — AI quality & governance

### Objectif

Rendre les fonctions IA auditables, testables et sûres.

### Plan d’action

1. prompts versionnés ;
2. schémas de sortie structurés lorsque nécessaire ;
3. tests de régression sur fixtures ;
4. contrôle des hallucinations sur noms, salaires, contacts et technologies ;
5. observabilité coût / latence / erreurs ;
6. feature flags pour nouvelles capacités ;
7. fallback non-IA lorsque la capacité est indisponible ;
8. rétention minimale du contexte envoyé au fournisseur IA ;
9. documentation claire des limites.

## UX V2

V2 peut approfondir l’interface moderne amorcée en V1 :

- command palette ;
- raccourcis clavier étendus ;
- panneaux redimensionnables ;
- transitions subtiles ;
- actions IA directement dans le Review Drawer ;
- recherche globale enrichie ;
- suggestions contextuelles dans le Dashboard et la Review Queue.

## Architecture V2

Les capacités IA forment un domaine dédié et ne doivent pas contaminer la logique métier principale.

```text
AI/
  Context/
  MatchingExplanation/
  ApplicationCopilot/
  InterviewCoach/
  SalaryAdvisor/
  CompanySummary/
  FollowUpSuggestion/
```

Le domaine IA consomme des ports de lecture des autres domaines. Il ne devient pas leur source de vérité.

## Definition of Done d’une capacité IA

- besoin utilisateur clair ;
- données sources explicites ;
- sortie testable ;
- pas d’action externe silencieuse ;
- limites documentées ;
- fallback défini ;
- observabilité ;
- coûts et latence acceptables ;
- CI verte.

## Critères de sortie V2

V2 est considérée complète lorsque l’utilisateur peut :

- comprendre immédiatement pourquoi une offre correspond ou non ;
- demander des variantes de candidature sans copier-coller ;
- comparer des offres ;
- préparer un entretien depuis l’offre ;
- obtenir une aide de négociation ;
- obtenir le contexte d’une société ou d’un recruteur ;
- préparer des relances ;
- utiliser un assistant IA contextualisé dans JobPilot ;
- garder la décision finale sur toutes les actions importantes.

## Différé vers V3

- networking personnel ;
- certifications et learning plans ;
- objectifs de carrière long terme ;
- gestion globale de la carrière au-delà de la recherche active ;
- Career OS complet.
