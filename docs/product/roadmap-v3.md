# JobPilot V3 — Career OS

## Statut

Vision stratégique long terme. V3 conserve les idées de produit au-delà du Personal ATS et de l’AI-first ATS afin de pouvoir les revisiter lorsque V1 et V2 sont réellement stabilisées.

V3 n’est pas un backlog d’exécution immédiat.

## Vision

JobPilot V3 devient un système personnel de pilotage de carrière, pas seulement un outil de recherche d’emploi.

```text
Opportunités
    ↓
Candidatures
    ↓
Recruteurs & sociétés
    ↓
Entretiens
    ↓
Négociation
    ↓
Historique de carrière
    ↓
Learning / networking / objectifs
    ↓
Analytics et décisions futures
```

L’objectif est de connecter les données de carrière dans un produit cohérent, sans transformer JobPilot en collection de fonctionnalités indépendantes.

## North Star V3

1. aider à prendre de meilleures décisions de carrière dans la durée ;
2. préserver et exploiter l’historique professionnel utile ;
3. relier recherche d’emploi, networking, apprentissage, rémunération et objectifs ;
4. permettre à l’IA d’aider avec un contexte long terme, sans décider à la place de l’utilisateur.

## Principe de cohérence

Toutes les capacités doivent renforcer le même graphe produit :

```text
Offres → Sociétés → Recruteurs → Conversations → Candidatures → Entretiens → Résultats → Analytics
```

Puis, en V3 :

```text
Résultats → Compétences → Learning → Objectifs → Opportunités futures
```

## Epic 1 — Career profile & goals

### Objectif

Dépasser le simple profil de candidature pour représenter les objectifs de carrière.

### Idées à conserver

- postes cibles à court, moyen et long terme ;
- technologies souhaitées ;
- secteurs privilégiés ;
- contraintes de mobilité et de mode de travail ;
- objectifs de rémunération ;
- trajectoires possibles ;
- objectifs annuels ;
- préférences explicites et révisables.

### Plan d’action futur

1. distinguer préférences de recherche actuelles et objectifs long terme ;
2. versionner les changements importants de stratégie ;
3. relier décisions passées et résultats obtenus ;
4. permettre à l’utilisateur de voir comment ses critères évoluent.

## Epic 2 — Skills & learning

### Objectif

Transformer les données du marché et des candidatures en plan d’apprentissage personnel.

### Idées à conserver

- compétences fréquemment manquantes ;
- technologies demandées dans les offres intéressantes ;
- compétences corrélées à de meilleurs salaires ou entretiens ;
- certifications pertinentes ;
- learning plans ;
- sujets de révision avant entretien ;
- suivi des compétences acquises.

### Plan d’action futur

1. construire un modèle explicite de compétences ;
2. distinguer compétence déclarée, détectée, validée et à apprendre ;
3. relier gaps de matching et objectifs ;
4. proposer des plans d’apprentissage avec validation utilisateur ;
5. mesurer l’évolution dans le temps.

## Epic 3 — Networking CRM

### Objectif

Étendre le CRM sociétés/recruteurs à un réseau professionnel plus large.

### Idées à conserver

- recruteurs ;
- anciens collègues ;
- clients ;
- managers ;
- contacts de communautés ;
- historique des interactions ;
- notes ;
- rappels de prise de contact ;
- contexte de relation ;
- opportunités liées au réseau.

### Garde-fous

- aucune collecte de données privées non autorisée ;
- aucune automatisation de messages non sollicités ;
- pas de scraping de profils privés ;
- contrôle utilisateur sur les contacts et rappels.

## Epic 4 — Career documents

### Objectif

Centraliser les documents professionnels dans un historique cohérent.

### Idées à conserver

- versions approuvées de CV ;
- lettres ;
- pitchs ;
- profils professionnels ;
- portfolios ;
- documents de négociation ;
- preuves de certifications ;
- historique des documents réellement utilisés.

### Plan d’action futur

1. introduire une notion de version approuvée ;
2. relier chaque candidature aux documents utilisés ;
3. éviter toute modification silencieuse ;
4. permettre d’analyser les documents associés aux meilleurs résultats.

## Epic 5 — Salary history & negotiation intelligence

### Objectif

Construire une vision long terme de la rémunération et des négociations.

### Idées à conserver

- historique salaires/TJM ;
- propositions reçues ;
- propositions faites ;
- négociations ;
- résultats ;
- comparaison par rôle, secteur, localisation et période ;
- objectifs de rémunération ;
- tendances personnelles.

### Plan d’action futur

1. séparer données personnelles et éventuelles données marché ;
2. conserver les sources et périodes ;
3. expliquer les recommandations ;
4. ne jamais inventer de benchmark externe absent.

## Epic 6 — Career analytics

### Objectif

Passer d’analytics de recherche d’emploi à des analytics de carrière.

### Idées à conserver

- évolution du salaire/TJM ;
- technologies les plus valorisées ;
- sources d’opportunités les plus efficaces ;
- sociétés et recruteurs avec lesquels les interactions sont les plus positives ;
- taux d’entretien selon type de rôle ;
- temps de recherche entre missions ;
- efficacité des stratégies de candidature ;
- évolution des compétences ;
- tendances de marché si une source autorisée et fiable existe.

Les insights doivent rester explicables et ne pas présenter une corrélation comme une causalité.

## Epic 7 — AI Career Assistant

### Objectif

Étendre l’assistant V2 au contexte de carrière long terme.

### Exemples d’usage

- « Quelle trajectoire semble la plus cohérente avec mon historique ? » ;
- « Quelles compétences devrais-je prioriser ? » ;
- « Compare mes opportunités actuelles à mes objectifs » ;
- « Prépare mon plan d’apprentissage du trimestre » ;
- « Quels contacts devrais-je relancer ? » ;
- « Comment a évolué ma rémunération ? » ;
- « Quels types d’offres donnent les meilleurs résultats pour moi ? ».

### Garde-fous

- recommandations explicables ;
- sources de données visibles ;
- pas de décision silencieuse ;
- pas de message ou candidature externe sans intention utilisateur ;
- distinction claire entre fait, inférence et suggestion.

## Epic 8 — Personal activity timeline

### Objectif

Unifier les événements importants de carrière dans un feed chronologique.

### Idées à conserver

- offres importées ;
- candidatures ;
- réponses ;
- entretiens ;
- relances ;
- nouvelles relations ;
- formations ;
- certifications ;
- changements d’objectif ;
- négociations ;
- nouvelles missions ou postes ;
- résultats majeurs.

La timeline doit être un journal métier, pas un dump de logs techniques.

## Epic 9 — Platform scale & performance

### Objectif

Préserver une UX fluide même avec un historique très important.

### Cibles à revisiter

- 100 000 offres ;
- 20 000 candidatures ;
- 10 000 contacts/recruteurs ;
- plusieurs années de timeline.

### Leviers envisagés

- pagination ;
- virtualisation ;
- lazy loading ;
- cache serveur ;
- background refresh ;
- index de recherche ;
- projections de lecture dédiées lorsque nécessaires ;
- archivage et rétention explicites.

Ces optimisations ne doivent être introduites qu’avec des mesures réelles.

## Epic 10 — Open-source & ecosystem quality

### Objectif

Permettre au projet d’accueillir des contributeurs sans perdre sa cohérence.

### Idées à conserver

- `CONTRIBUTING.md` ;
- ADRs ;
- diagrammes d’architecture ;
- templates d’issues et PR ;
- GitHub Projects et milestones ;
- labels et Epics ;
- releases automatisées ;
- changelog ;
- Dependabot/Renovate ;
- CodeQL ;
- contrôles accessibilité ;
- performance budgets ;
- documentation par domaine.

## Architecture V3

La vision modulaire reste un monolithe modulaire tant qu’aucun besoin opérationnel ne justifie une extraction.

```text
Offers
Companies
Recruiters
Applications
Documents
Messaging
Interviews
Analytics
AI
Integrations
CareerProfile
Learning
Networking
Settings
```

Chaque domaine possède ses règles, API, tests, documentation et contrats. Les dépendances inter-domaines doivent rester explicites.

## UX V3

Le produit doit rester cohérent malgré l’élargissement du périmètre :

- Dashboard orienté « What should I do now? » ;
- workspaces contextuels ;
- recherche globale ;
- command palette ;
- keyboard-first lorsque pertinent ;
- actions immédiates et réversibles ;
- timeline globale ;
- assistant IA conscient du contexte courant ;
- aucune multiplication de pages sans nécessité.

## Ce que V3 ne doit pas devenir

- un réseau social ;
- un scraper universel contournant les plateformes ;
- un bot envoyant des messages ou candidatures sans contrôle ;
- un LMS complet ;
- un ERP RH ;
- une collection de features IA indépendantes ;
- une architecture microservices sans justification.

## Critères de sortie V3

V3 est considérée cohérente lorsque JobPilot peut aider l’utilisateur à gérer dans un seul système :

- opportunités ;
- candidatures ;
- sociétés et recruteurs ;
- entretiens ;
- suivis ;
- documents ;
- rémunération ;
- networking ;
- compétences et learning ;
- objectifs de carrière ;
- analytics ;
- assistance IA contextuelle.

La valeur doit provenir des connexions entre ces données, pas de la quantité de fonctionnalités.

## Règle de réactivation

Aucune Epic V3 ne doit être promue dans la roadmap active simplement parce qu’elle est intéressante. Pour la réactiver, documenter :

1. le problème utilisateur observé ;
2. pourquoi V1/V2 ne le résout pas ;
3. les données déjà disponibles ;
4. le bénéfice attendu ;
5. le plus petit PR sûr permettant de tester l’idée.
