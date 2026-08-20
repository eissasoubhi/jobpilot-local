# JobPilot Autofill — matrice de compatibilité ATS

Cette matrice décrit la couverture automatisée de l’extension JobPilot Autofill. Elle ne constitue pas une garantie que chaque configuration personnalisée d’un ATS conservera indéfiniment les mêmes attributs ou widgets.

## Plateformes couvertes

| ATS | Détection | Champs profil | Questions dynamiques | Documents | Tests fixtures | Chromium |
| --- | --- | --- | --- | --- | --- | --- |
| SmartRecruiters | Oui | Oui | Revue / réponses réutilisables | Moteur documents générique | Oui | Oui |
| Greenhouse | Oui | Oui | Revue / réponses réutilisables | Moteur documents générique | Oui | Oui |
| Lever | Oui | Oui | Revue / réponses réutilisables | Moteur documents générique | Oui | Oui |
| Teamtailor | Oui, y compris marqueur domaine personnalisé | Oui | Revue / réponses réutilisables | Moteur documents générique | Oui | Oui |
| Recruitee | Oui, y compris formulaire embarqué | Oui | Revue / réponses réutilisables | Moteur documents générique | Oui | Oui |
| Workday | Oui via domaine et attributs fonctionnels | Oui | Revue / réponses réutilisables | Moteur documents générique | Oui | Oui |
| Ashby | Oui via domaine et chemins système | Oui | Revue / réponses réutilisables | Moteur documents générique | Oui | Oui |

## Invariants testés

Les tests de compatibilité valident le pipeline complet :

1. détection générique du formulaire ;
2. enrichissement par l’adaptateur ATS ;
3. résolution vers les clés canoniques du profil candidat ;
4. remplissage des champs vides avec un niveau de confiance suffisant ;
5. conservation d’une valeur déjà saisie par l’utilisateur ;
6. absence totale de soumission automatique du formulaire.

Les mêmes scénarios sont exercés en `Vitest/jsdom` et dans Chromium avec Playwright.

## Limites volontaires

- Les fixtures reproduisent les attributs fonctionnels et noms de champs supportés ; elles ne copient pas le DOM complet de sites tiers.
- Les classes CSS générées par React ou les styles internes des ATS ne sont pas utilisés comme contrat de compatibilité.
- Workday et Ashby peuvent être fortement personnalisés par le recruteur ; tout champ inconnu ou contradictoire reste en revue manuelle.
- Les questions sensibles, légales ou de rémunération ne sont jamais générées par IA.
- Une suggestion IA n’est jamais insérée sans action explicite de l’utilisateur.
- L’extension ne clique jamais sur le bouton d’envoi et ne soumet jamais automatiquement une candidature.

## Régression

Toute modification d’un adaptateur ATS doit conserver cette matrice verte. Lorsqu’un nouveau cas réel n’est pas reconnu, ajouter d’abord une fixture minimale reproduisant le champ ou widget concerné, puis corriger l’adaptateur sans élargir arbitrairement les permissions ou les règles de matching.
