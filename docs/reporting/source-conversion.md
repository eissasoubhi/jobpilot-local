# Conversion par source et type de contrat

La page **Conversion** fournit un reporting en lecture seule sur les canaux d’acquisition et les types de contrat des offres.

## Mesures

Pour chaque groupe, JobPilot affiche :

- le nombre d’offres ;
- le nombre de candidatures préparées ;
- le nombre de candidatures considérées comme envoyées ;
- les réponses, entretiens et refus ;
- le taux de candidature par offre ;
- le taux de réponse et le taux d’entretien après envoi ;
- le TJM proposé moyen et le nombre d’offres qui possèdent cette proposition ;
- le salaire annuel brut proposé moyen et le nombre d’offres qui possèdent cette proposition.

Une candidature est considérée comme envoyée lorsqu’elle possède une date de soumission ou un statut aval confirmant l’envoi. Les réponses comprennent les demandes d’information, réponses générales, entretiens et refus.

Les moyennes de rémunération utilisent uniquement les champs structurés `proposedTjm` et `proposedSalary` déjà calculés et enregistrés sur les offres. Une offre sans proposition structurée est exclue de la moyenne. JobPilot ne tente pas d’interpréter le texte libre d’une candidature ou d’une annonce pour compléter ces chiffres.

## Attribution par source

Une offre canonique peut être présente sur plusieurs sources. Dans ce cas, son offre, sa candidature éventuelle et ses propositions de rémunération sont attribuées à chacune de ces sources. Les lignes par source servent à comparer les canaux ; elles ne doivent pas être additionnées pour reconstruire le total canonique.

## Attribution par type de contrat

Chaque offre canonique est attribuée une seule fois à la valeur structurée de son champ `contractType`. Les valeurs vides sont regroupées sous **Non renseigné**. Aucune normalisation métier risquée n’est appliquée : des libellés différents restent des groupes différents.

## Limites

- aucun historique de changement de statut n’est inventé ; seul l’état courant est utilisé ;
- aucune causalité commerciale n’est déduite lorsqu’une offre possède plusieurs sources ;
- les moyennes ne comparent pas automatiquement des TJM freelances à des salaires salariés ;
- aucune donnée externe n’est collectée par ce reporting ;
- aucune migration ni modification des connecteurs n’est nécessaire.

Endpoint : `GET /api/reporting/source-conversion`.
