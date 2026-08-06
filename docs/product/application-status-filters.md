# Filtres de statut des candidatures

La page **Candidatures** permet de séparer les candidatures selon leur état de suivi sans modifier les données enregistrées.

## Accès rapides

Trois boutons sont toujours visibles :

- **Toutes les candidatures** ;
- **Prêtes à envoyer** (`READY_TO_SUBMIT`) ;
- **Envoyées** (`SUBMITTED`).

Chaque bouton affiche le nombre de candidatures correspondant au statut.

## Filtre complet

La liste déroulante permet aussi de sélectionner les autres états connus :

- envoi en cours ;
- échec de l’envoi ;
- CV manquant ;
- brouillon ;
- candidature confirmée ;
- réponse reçue ;
- informations demandées ;
- réponse recruteur ;
- entretien ;
- refus ;
- offre reçue.

Un statut inconnu renvoyé par l’API reste visible dans la liste au lieu d’être masqué.

## Comportement

Le filtrage est effectué uniquement dans le navigateur sur les candidatures déjà retournées par `GET /api/applications`.

Changer le filtre :

- ne modifie aucun statut ;
- ne déclenche aucun envoi ;
- ne supprime aucune candidature ;
- n’appelle pas une nouvelle API.

Lorsqu’aucune candidature ne correspond au filtre, la page affiche un état vide spécifique tout en conservant les contrôles de filtrage.
