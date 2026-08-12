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

## Producteurs raccordés

La mise à jour manuelle d’une candidature vers `SUBMITTED` produit `APPLICATION_SUBMITTED` dans la même unité de travail que le changement de statut. Une nouvelle modification d’une candidature déjà `SUBMITTED` ne produit pas de doublon. L’événement utilise la date `submittedAt` comme date métier et conserve le statut précédent dans son payload.

Les nouveaux messages Gmail associés à une candidature produisent maintenant un événement uniquement lorsque Doctrine observe dans la même transaction un vrai changement de statut :

- `APPLICATION_REPLY` et `INFORMATION_REQUEST` → `RESPONSE_RECEIVED` ;
- `REJECTION` → `REJECTED` ;
- `INTERVIEW_REQUEST` → `INTERVIEW`.

Ces événements utilisent la date de réception du mail comme `occurredAt`, la source `gmail-inbox`, la catégorie Gmail et le statut précédent dans un payload minimal. Un deuxième mail qui laisse la candidature dans le même statut ne produit pas de doublon. `APPLICATION_CONFIRMATION` continue à mettre à jour la candidature mais ne crée aucun événement, car aucun type métier correspondant n’est défini dans le catalogue V1.

L’envoi Gmail automatique reste volontairement un raccordement séparé : l’e-mail est un effet externe irréversible et son traitement doit éviter qu’un incident d’écriture de timeline transforme un message réellement envoyé en faux échec de soumission.

## Étapes suivantes

Les autres intégrations seront livrées séparément, transition par transition, avec des tests garantissant qu’un événement n’est enregistré qu’après une transition métier réelle et idempotente. Les métriques temporelles ne devront utiliser cette timeline qu’une fois les événements nécessaires effectivement raccordés.
