# Talent.com — Publisher Job API planifiée

Décision revue le **11 août 2026** à partir des ressources officielles Talent.com dédiées aux publishers et aux intégrations.

## Décision

Talent.com est classé `PLANNED`, pas `OPERATIONAL`.

Le canal cible est le **Publisher Job API** officiel proposé aux publisher partners. Talent.com présente également des flux XML dans le cadre de ses intégrations partenaires, mais JobPilot ne doit pas confondre ces canaux de lecture/redistribution avec les flux XML utilisés par des ATS ou employeurs pour envoyer leurs propres offres vers Talent.com.

Aucun scraper HTTP ou Browser Talent.com n’est planifié.

## Références officielles

- <https://employers.talent.com/publishers>
- <https://www.talent.com/integrations>

## Conditions avant implémentation

Le connecteur ne deviendra exécutable qu’après obtention et validation de :

- l’accès Publisher applicable au compte JobPilot ;
- la documentation technique officielle ;
- les credentials nécessaires ;
- les quotas et limites de pagination ;
- les droits de réutilisation/affiliation applicables ;
- les règles de conservation et d’attribution éventuelles.

Une future PR opérationnelle devra ensuite ajouter authentification officielle, pagination bornée, timeouts, normalisation vers le catalogue canonique, déduplication, fixtures/mocks, diagnostics de santé et kill switch.

## Garde-fous

Tant que ces conditions ne sont pas remplies :

- aucun endpoint interne ou non documenté n’est utilisé ;
- aucune session privée n’est automatisée ;
- aucun scraping Talent.com n’est activé ;
- aucune requête Talent.com réelle n’est exécutée dans la CI.

Cette classification peut être réévaluée uniquement à partir d’un canal officiel ou d’un accord applicable au compte JobPilot.
