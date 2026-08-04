# Context map

## CandidateProfile

Responsable du profil, des préférences, règles de rémunération, compétences, langues et disponibilité.

Fournit aux autres modules une vue stable du candidat. Il ne connaît pas les plateformes ni les candidatures.

## JobDiscovery

Responsable de la collecte : API, RSS, scraping HTTP, scraping navigateur, Gmail et extension.

Produit des enregistrements bruts et normalisés. Il ne calcule pas le score final et ne décide pas d’envoyer une candidature.

## JobCatalog

Responsable des offres canoniques, occurrences de sources, provenance, fraîcheur, archivage et déduplication.

Consomme les résultats de `JobDiscovery`.

## Matching

Responsable du score, de son explication, des exclusions, de la langue, de la sélection recommandée du CV et des règles de rémunération proposées.

Consomme `CandidateProfile` et `JobCatalog` sans modifier leurs agrégats.

## Candidacy

Responsable de la préparation, validation, soumission, état de candidature, suivi et prévention des doubles envois.

Consomme les résultats de `Matching` et demande les actions d’envoi à `Messaging`.

## Messaging

Responsable de Gmail, des messages entrants, des messages sortants, des pièces jointes, de la classification initiale et de la traçabilité technique.

Ne décide pas seul de l’état métier d’une candidature. Il publie des résultats que `Candidacy` interprète.

## RecruiterCRM

Responsable des recruteurs, sociétés, échanges, notes, relances et relations avec les offres ou candidatures.

## Reporting

Construit les indicateurs et vues de lecture. Il ne porte pas les règles transactionnelles des autres domaines.

## Shared

Contient uniquement les concepts réellement partagés : identifiants, horloge, pagination, bus, résultat d’opération et primitives techniques communes.

`Shared` ne doit pas devenir un dossier de services sans propriétaire.

## Sens des dépendances

```text
CandidateProfile ----+
                     +--> Matching --> Candidacy --> Messaging
JobDiscovery --> JobCatalog ---+             |
       |                        |             +--> RecruiterCRM
       +------------------------+------------------> Reporting
```

Les échanges inter-contextes se font par :

- contrats d’application synchrones lorsque la réponse immédiate est nécessaire ;
- événements lorsque le traitement est indépendant ou asynchrone ;
- projections de lecture pour le reporting.

Aucun module ne doit modifier directement les tables métier d’un autre module sans passer par son cas d’utilisation public.
