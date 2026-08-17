# Classement des offres : Match Score vs Priority Score

JobPilot distingue désormais deux questions différentes :

- **Match Score** : à quel point l'offre correspond au profil et aux critères de recherche ;
- **Priority Score** : quelle offre mérite d'être traitée en premier maintenant.

Le `Match Score` reste calculé par le moteur de matching existant. Le `Priority Score` est calculé dynamiquement à la lecture du catalogue afin que la fraîcheur et l'historique de conversion ne deviennent pas obsolètes en base.

## Formule V1

```text
priorityScore =
    0.60 × matchScore
  + 0.15 × freshnessScore
  + 0.10 × preferenceScore
  + 0.07 × compensationScore
  + 0.05 × confidenceScore
  + 0.03 × historicalScore
```

### Match — 60 %

Le score de correspondance existant reste le signal dominant. Une offre très récente mais médiocre ne doit pas passer automatiquement devant une excellente offre légèrement plus ancienne.

### Fraîcheur — 15 %

La fraîcheur utilise une décroissance continue avec une demi-vie de 72 heures :

```text
freshnessScore = 100 × 2 ^ (-ageHours / 72)
```

Il n'existe donc plus de rupture artificielle à 24 h, 72 h ou 7 jours pour l'ordre principal.

Une date de publication inconnue reçoit un score prudent de 35/100 au lieu d'être considérée comme fraîche simplement parce que l'offre vient d'être découverte.

### Préférences — 10 %

Le calcul utilise les informations déjà disponibles dans le profil :

- types de contrats acceptés ;
- localisations préférées ;
- préférence présentiel / hybride / remote.

Une préférence non configurée reste neutre et ne pénalise pas l'offre.

### Rémunération — 7 %

Pour les missions freelance, le score compare le TJM proposé/annoncé au TJM désiré. Pour les contrats salariés, il compare le salaire proposé/annoncé au salaire désiré. Une rémunération inconnue reste neutre.

Les filtres bloquants existants sur salaire/TJM restent prioritaires : une offre déjà `REJECTED_BY_FILTER` reçoit une priorité de 0.

### Confiance et qualité — 5 %

Quand Gemini fournit une confiance, JobPilot la combine à la qualité des données de l'offre. Sans analyse IA, la qualité des données (titre, description, contrat, lieu, date, source) sert de signal de confiance.

### Historique de conversion — 3 %

JobPilot réutilise les statistiques déjà calculées par source : taux de réponse et taux d'entretien. Le signal est volontairement faible et applique un lissage vers 50/100 tant que le nombre de candidatures envoyées est faible, afin d'éviter de sur-apprendre sur quelques exemples.

## Ordre de l'API et de la Review Queue

`GET /api/jobs` renvoie maintenant les offres triées par `priorityScore` décroissant, puis par `matchScore`, puis par date de publication en cas d'égalité.

Chaque offre contient également :

- `priorityScore` ;
- `priorityReasons` ;
- `priorityComponents` (`match`, `freshness`, `preferences`, `compensation`, `confidence`, `history`).

La Review Queue utilise déjà l'ordre de `GET /api/jobs`, elle hérite donc automatiquement de ce nouveau classement sans modifier le statut ou le contenu des candidatures.

## Évolution future

Quand l'historique personnel sera suffisamment riche, le signal historique pourra évoluer vers une estimation calibrée de la probabilité d'obtenir une réponse ou un entretien. Cette étape ne doit pas être introduite avant d'avoir assez d'observations pour éviter le sur-apprentissage.
