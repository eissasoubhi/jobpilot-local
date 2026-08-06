# Reporting des candidatures

La page **Reporting** fournit une première vue locale et en lecture seule de la conversion des candidatures.

## Données utilisées

Les indicateurs sont calculés dans le navigateur à partir de `GET /api/applications` :

- candidatures préparées ;
- candidatures envoyées ;
- entretiens actuellement enregistrés ;
- refus actuellement enregistrés ;
- répartition par source principale de l’offre.

Une candidature est considérée comme envoyée lorsqu’une date `submittedAt` existe ou lorsque son statut implique déjà un envoi (`SUBMITTED`, `APPLICATION_CONFIRMED`, `INTERVIEW`, `OFFER_RECEIVED`, `REJECTED`).

## Limites d’interprétation

- le reporting ne reconstruit pas les anciens changements de statut ;
- une réponse, un entretien ou un refus absent de JobPilot n’est jamais déduit ;
- la source affichée est la première occurrence de source de l’offre, avec repli sur le champ source historique ;
- les taux sont descriptifs et ne déclenchent aucune action automatique.

## Sécurité et rollback

Cette livraison est frontend uniquement. Elle n’ajoute aucune migration, écriture, synchronisation, requête externe ou modification de connecteur. Le rollback consiste à retirer la page, son helper et l’entrée de navigation.
