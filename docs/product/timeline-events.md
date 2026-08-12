# Timeline métier V1

La timeline V1 conserve des événements métier horodatés et append-only liés à une offre canonique. Elle ne remplace pas les logs techniques et ne doit pas être alimentée par des retries, erreurs de transport ou détails d’implémentation.

## Événements supportés dans le socle

- `OFFER_IMPORTED`
- `SOURCE_OCCURRENCE_ADDED`
- `PREPARATION_CREATED`
- `PREPARATION_UPDATED`
- `APPLICATION_SUBMITTED`
- `RESPONSE_RECEIVED`
- `REJECTED`
- `INTERVIEW`
- `FOLLOW_UP`

Les résultats finaux comme une acceptation restent hors du catalogue tant que leur statut métier n’est pas défini explicitement.

## Modèle

Chaque événement conserve :

- l’offre canonique obligatoire ;
- la candidature associée lorsqu’elle existe ;
- le type métier ;
- la source de l’événement ;
- un payload JSON minimal et non sensible ;
- la date métier `occurredAt` ;
- la date d’enregistrement `recordedAt`.

Le modèle ne fournit aucun setter de mutation. Une correction future doit créer un nouvel événement plutôt que réécrire l’historique.

`JobTimelineRecorder` ajoute l’événement à l’unité de travail Doctrine sans appeler `flush()`. Le service appelant garde ainsi la frontière transactionnelle avec la transition métier qui produit l’événement.

## Étapes suivantes

Les intégrations seront livrées séparément, transition par transition, avec des tests garantissant qu’un événement n’est enregistré qu’après une transition métier réelle et idempotente. Les métriques temporelles ne devront utiliser cette timeline qu’une fois les événements nécessaires effectivement raccordés.
