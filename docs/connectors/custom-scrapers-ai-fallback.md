# Déclenchement du fallback IA des scrapers personnalisés

Cette tranche sépare la décision de déclencher l’IA de l’implémentation Gemini elle-même.

## Règles de déclenchement

Le fallback IA n’est autorisé que si toutes les conditions suivantes sont réunies :

- le budget d’appels IA de l’opération est supérieur à zéro ;
- le plafond d’appels n’est pas atteint ;
- l’extraction déterministe de la page n’a produit aucun candidat ;
- le détecteur recommande encore le mode HTTP ;
- la page contient au moins 300 caractères visibles ;
- la page contient au moins un signal métier d’offres : mot-clé d’emploi ou lien ressemblant à une offre.

Conséquences :

- la prévisualisation manuelle utilise un budget de zéro et ne doit donc jamais consommer Gemini ;
- une page déjà comprise par `JobPosting`, DOM ou liens déterministes ne consomme pas Gemini ;
- une page qui nécessite Browser/Playwright ne passe pas par ce fallback HTTP ;
- une page générique sans signaux d’emploi ne consomme pas Gemini ;
- la synchronisation automatique visera un plafond de deux tentatives IA par cycle.

## Coordinateur

`CustomScraperAiFallbackCoordinator` applique la politique avant d’appeler `CustomScraperAiExtractorInterface`.

Lorsque la politique refuse le fallback, les candidats déterministes reçus en entrée sont retournés sans modification. Lorsque la politique l’autorise, le coordinateur délègue à l’extracteur IA grounded livré dans la tranche précédente.

Le coordinateur ne décide ni de la fiabilité finale de l’extraction, ni du matching candidat. Les liens trouvés par IA doivent encore être enrichis par leur fiche détail puis passer par `CustomScraperOfferQualityEvaluator` avant toute importation.

## État de livraison

Cette tranche verrouille et teste les règles de déclenchement indépendamment du moteur de collecte principal. Le branchement dans `CustomScraperExtractionService` reste la prochaine étape, avec un budget cible de 0 appel en prévisualisation et 2 appels maximum en synchronisation.
