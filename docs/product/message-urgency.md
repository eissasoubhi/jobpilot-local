# Priorité et urgence des messages

JobPilot calcule une priorité de traitement pour les messages Gmail déjà classés par l’Inbox intelligente. Cette priorité sert uniquement à organiser le travail de l’utilisateur : elle ne répond pas à un e-mail, ne modifie pas Gmail et ne déclenche aucune candidature.

## Niveaux

Chaque message reçoit à la lecture un niveau calculé :

- `URGENT` — action rapide fortement recommandée ;
- `PRIORITY` — message important à traiter avant les messages ordinaires ;
- `NORMAL` — aucune urgence métier détectée.

Le calcul n’est pas persisté. Il est recalculé depuis la catégorie, l’état traité, la date de réception et des signaux temporels explicites du sujet ou de l’aperçu. Une priorité peut donc disparaître immédiatement lorsque le message est marqué comme traité.

## Règles initiales

### Urgent par nature

- `INTERVIEW_REQUEST` : entretien ou rendez-vous à organiser ;
- `INFORMATION_REQUEST` : informations ou réponse demandées par le recruteur.

### Prioritaire par nature

- `RECRUITER_OPPORTUNITY` : proposition directe de poste ou de mission ;
- `APPLICATION_REPLY` : réponse liée à une candidature existante.

Ces messages deviennent urgents lorsqu’un signal temporel fort est détecté (`urgent`, `ASAP`, `aujourd’hui`, `demain`, `avant ce soir`, réponse sous 24 h, etc.) ou lorsqu’une action reste ouverte depuis suffisamment longtemps.

### Jamais promu uniquement par un mot temporel

Les catégories suivantes restent normales tant qu’elles ne nécessitent pas réellement d’action :

- `REJECTION` ;
- `APPLICATION_CONFIRMATION` ;
- `JOB_ALERT` ;
- `UNKNOWN`.

Une alerte contenant le mot « urgent » dans le titre d’une offre ne devient donc pas artificiellement une urgence utilisateur.

## Vieillissement

Pour un message `actionRequired=true` non traité :

- après 24 h, l’ancienneté renforce sa priorité ;
- après 48 h, l’ancienneté peut faire passer une proposition ou une réponse au niveau urgent.

JobPilot affiche explicitement cette raison. Il ne prétend pas savoir si le recruteur attend encore une réponse : il indique seulement que l’action reste ouverte dans JobPilot depuis cette durée.

## Explicabilité

L’API ajoute un objet `urgency` à chaque message :

```json
{
  "level": "URGENT",
  "label": "Urgent",
  "actionRequired": true,
  "reasons": [
    "Entretien ou rendez-vous à organiser."
  ],
  "recommendedAction": "Planifier ou répondre à l’entretien",
  "ageHours": 2
}
```

Aucun score opaque n’est exposé dans l’interface. Le niveau est toujours accompagné de raisons lisibles.

## Interface

La page **Messagerie** :

- affiche un résumé du nombre de messages urgents ;
- propose les filtres **Urgents uniquement**, **Urgents et prioritaires** et **Actions à traiter** ;
- trie les messages par urgence puis par date à niveau égal ;
- affiche les raisons et l’action recommandée ;
- met l’ouverture Gmail en action principale lorsque le message attend une réponse ;
- conserve l’action **Marquer comme traité**.

Les compteurs sont calculés sur la même vue d’Inbox et restent stables quand l’utilisateur change de filtre.

## Confidentialité

Le moteur n’ajoute aucun contenu d’e-mail à une nouvelle table et ne copie aucun corps ou extrait dans la Timeline. Les raisons de priorité sont des libellés métier génériques et ne reprennent pas le contenu privé du message.

La détection temporelle initiale inspecte uniquement le sujet et l’aperçu déjà stockés par l’Inbox. Le corps complet n’est pas utilisé pour éviter qu’une ancienne citation ou une signature provoque une fausse urgence.

## Limites

- aucune extraction de date limite exacte dans cette première version ;
- aucune notification push ou système native ;
- aucun envoi automatique de réponse ;
- l’état « traité » de JobPilot reste indépendant de l’état lu/non lu dans Gmail.
