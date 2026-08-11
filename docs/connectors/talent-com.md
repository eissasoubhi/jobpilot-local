# Talent.com — canal Publisher officiel

## Décision

Décision revue le **11 août 2026**.

Talent.com ne doit pas être intégré à JobPilot par scraping planifié lorsqu’un canal officiel de redistribution est proposé aux éditeurs de sites d’emploi.

Talent.com présente officiellement un programme **Publisher** destiné aux job boards et publishers. Ce programme annonce notamment :

- un **self-serve Job API** pour compléter une recherche avec des offres Talent.com ;
- des **flux XML** ;
- des URLs d’affiliation et mécanismes de monétisation associés au trafic apporté.

Références officielles :

- <https://employers.talent.com/publishers>
- <https://www.talent.com/integrations>

JobPilot classe donc Talent.com en `PLANNED` avec les modes informatifs `API` et `XML`.

## Distinction importante

Les documentations XML d’intégration employeur/ATS servent principalement à **envoyer des offres vers Talent.com**. Elles ne doivent pas être confondues avec le canal Publisher permettant à un site tiers d’intégrer les offres Talent.com.

Pour JobPilot, la voie cible est le programme Publisher. Aucun endpoint interne du site candidat ne doit être utilisé comme substitut à cette intégration officielle.

## Conditions avant passage à `OPERATIONAL`

Talent.com ne pourra devenir une source exécutable qu’après obtention et revue de :

1. l’accès Publisher applicable à JobPilot ;
2. la documentation technique exacte de la Job API ou du flux accordé ;
3. les credentials éventuels ;
4. les quotas et règles de pagination ;
5. les conditions de conservation, déduplication et affichage des offres ;
6. les règles d’attribution et de redirection ;
7. les règles de monétisation/affiliation si elles s’appliquent au compte.

La PR opérationnelle devra ensuite ajouter un connecteur backend séparé avec timeout, quotas, normalisation canonique, déduplication, kill switch, diagnostics et tests par fixtures/mocks.

## Garde-fous actuels

Tant que cet accès n’est pas obtenu :

- aucun scraper HTTP Talent.com n’est planifié ;
- aucun Browser/Playwright Talent.com n’est planifié ;
- aucun endpoint privé ou non documenté n’est utilisé ;
- aucune CI ne contacte Talent.com ;
- la ligne `PLANNED` reste uniquement informative et n’expose aucun bouton d’activation.
