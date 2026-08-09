# Préparation des lettres de motivation

Chaque candidature préparée par JobPilot contient une lettre de motivation non vide, même lorsque l'offre ne demande pas explicitement de lettre.

## Principe V1

La génération par défaut est déterministe et fondée uniquement sur des informations connues par JobPilot :

- intitulé du poste ;
- entreprise lorsqu'elle est connue ;
- langue de l'offre ;
- nombre d'années d'expérience enregistré dans le profil ;
- disponibilité enregistrée ;
- nom du candidat lorsqu'il est renseigné.

La lettre ne transforme jamais une technologie simplement mentionnée dans l'offre en compétence du candidat. Une offre qui demande Java, Rust, Kubernetes ou AWS ne suffit donc pas à faire apparaître ces technologies comme expérience maîtrisée dans la lettre.

## Langue

- offre `fr` : lettre en français ;
- offre `en` : lettre en anglais.

Le détecteur historique `coverLetterRequired` reste disponible comme métadonnée pour indiquer si la plateforme ou l'offre exige explicitement une lettre, mais il ne contrôle plus l'existence de la lettre préparée.

## Résilience

Cette génération de base ne dépend d'aucun fournisseur IA. Une panne Gemini, une clé absente ou un quota atteint ne peut donc pas empêcher la préparation d'une lettre de motivation.

Une amélioration IA future pourra enrichir le texte uniquement derrière une abstraction dédiée et avec des données de profil/CV explicitement autorisées, sans inventer de compétences, d'expériences, de clients, de dates ou de réalisations.

## Soumission

Cette fonctionnalité ne modifie pas la politique de soumission externe. La lettre reste éditable dans les flux de revue existants avant toute action externe.
