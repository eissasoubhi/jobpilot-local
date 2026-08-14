# Review Queue — fraîcheur et indisponibilité

Cette livraison ajoute deux informations de décision à la Review Queue sans effectuer de vérification externe supplémentaire.

- L’ancienneté utilise `publishedAt` lorsqu’une source fournit une date de publication fiable.
- Lorsque cette date manque, l’interface affiche explicitement l’ancienneté de `discoveredAt` avec le libellé **Détectée**, et non **Publiée**.
- Une offre publiée depuis au moins sept jours reçoit l’indicateur **Offre ancienne**. Aucun statut n’est modifié automatiquement.
- Le bouton **Offre indisponible** demande confirmation puis enregistre localement `OFFER_UNAVAILABLE` sur la candidature et `UNAVAILABLE` sur l’offre.
- Cette action est distincte de `IGNORED_NOT_MATCH` et ne contacte pas la plateforme source.
- Une candidature déjà envoyée ne peut pas être reclassée par cet endpoint.

Le contrat complet de la Review Queue est documenté dans `docs/product/review-queue.md`.
