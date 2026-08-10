# Architecture Decision Records

Les ADR enregistrent les décisions d’architecture qui ont un impact durable sur JobPilot.

Un ADR doit expliquer :

- le contexte et le problème ;
- la décision retenue ;
- les alternatives rejetées ;
- les garde-fous ;
- les conséquences ;
- la stratégie de rollback lorsque c’est pertinent.

## Index

| ADR | Statut | Sujet |
| --- | --- | --- |
| [0001](./0001-job-acquisition-http-ai-browser.md) | Accepté | Acquisition des offres : flux/Gmail, HTTP déterministe, Gemini grounded et Browser Playwright isolé |

Les ADR acceptés ne sont pas réécrits silencieusement lorsque la décision change : une évolution majeure doit créer un nouvel ADR qui remplace explicitement l’ancien.
