# Spécification fonctionnelle — JobPilot Local

## Objectif

Réduire le temps consacré à la recherche et à la préparation des candidatures, tout en conservant le contrôle de l’envoi final et en évitant les doubles positionnements freelance.

## Flux principal

1. Une offre est ajoutée manuellement, importée par l’extension ou trouvée dans une alerte Gmail.
2. L’application normalise son contenu et détecte sa langue.
3. Les exclusions strictes sont appliquées.
4. Un score de 0 à 100 est calculé.
5. Les offres sont triées par fraîcheur, puis par score.
6. À partir du seuil configuré, une candidature est préparée automatiquement.
7. Le CV français ou anglais adéquat est sélectionné sans être modifié.
8. Le message, la lettre et les réponses sont préparés.
9. Le formulaire est prérempli par l’extension.
10. L’utilisateur vérifie et réalise l’envoi final.
11. Les confirmations et réponses sont classées depuis Gmail.

## Règle TJM

- Plafond absolu configurable, initialement 520 €.
- Si une fourchette a un maximum inférieur ou égal à 500 €, proposer son maximum.
- Si le maximum dépasse 500 €, proposer le milieu de la fourchette, arrondi au multiple de 5, sans dépasser le plafond.
- Budget fixe sous le minimum : mission non éligible.
- Sans budget et réponse obligatoire : 500 € en Île-de-France, 480 € ailleurs et en full remote.
- Le TJM n’est jamais ajouté au CV et reste absent du message lorsque ce n’est pas utile.

## Multi-plateformes

Plusieurs annonces peuvent représenter la même opportunité. Les liens de découverte sont conservés, mais une redirection vers le même identifiant ATS ne doit déclencher qu’une seule soumission.

## Positionnements freelance

Un positionnement contient le client final, l’agence, le commercial, le descriptif, la référence d’appel d’offres, le TJM, la preuve d’accord par e-mail et son statut. Une référence identique bloque une seconde soumission. Sans référence, une comparaison client/intitulé/description produit une alerte de similarité.

## Sécurité

- Application liée uniquement à localhost.
- CV stockés dans un volume local non public, sauf route de téléchargement locale.
- Jeton Gmail chiffré avec libsodium.
- Scope Gmail en lecture seule.
- Aucun mot de passe de plateforme stocké.
- Aucun contournement CAPTCHA ou anti-bot.
