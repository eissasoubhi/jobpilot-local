# Preset de quota Gemini payant

JobPilot conserve ses garde-fous locaux même lorsque le projet Gemini utilise un tier payant.

## Source de vérité

Les limites Gemini ne sont pas universelles : elles dépendent du modèle, du projet, du tier et de l’état du compte. Google recommande de consulter les limites actives dans Google AI Studio.

Documentation officielle :

- https://ai.google.dev/gemini-api/docs/rate-limits
- https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite

Le 9 août 2026, la page **Gemini API Rate Limit** du projet Tier 1 utilisé avec JobPilot affichait pour **Gemini 3.5 Flash-Lite** :

- 4 000 RPM ;
- 4 000 000 TPM d’entrée ;
- 100 000 RPD.

Ces valeurs sont un instantané du projet courant, pas des constantes universelles. Le preset doit être revérifié si le modèle, le projet ou le tier change.

L’identifiant API stable correspondant est `gemini-3.5-flash-lite`.

## Preset Tier 1 — Gemini 3.5 Flash-Lite

La page `/parametres/integrations` expose un bouton **Appliquer les limites Tier 1 observées** uniquement lorsque le modèle actif est `gemini-3.5-flash-lite`.

Le preset enregistre les plafonds fournisseur observés :

- 4 000 RPM ;
- 4 000 000 TPM ;
- 100 000 RPD ;
- 20 % de quota utilisable par JobPilot.

Le `AiQuotaManager` applique ensuite ce garde-fou local. Les plafonds opérationnels réellement utilisables deviennent :

- 800 RPM ;
- 800 000 TPM ;
- 20 000 RPD.

Le choix de 20 % est volontaire. Le tier fournisseur est suffisamment élevé pour que JobPilot n’ait pas besoin d’en viser la limite. Cette réserve réduit le risque de boucle accidentelle et laisse de la capacité aux autres usages du même projet.

Le preset n’écrase ni le modèle, ni la clé API, ni l’état d’activation du matching. Il ne déclenche aucun appel Gemini à lui seul.

## Protection contre un mauvais modèle

Les quotas sont spécifiques au modèle. JobPilot refuse donc d’appliquer automatiquement ce preset si le modèle actif n’est pas exactement `gemini-3.5-flash-lite`.

En cas de passage à `gemini-3.6-flash`, `gemini-3.5-flash` ou à un autre modèle, il faut d’abord consulter AI Studio puis saisir les limites de ce modèle dans les champs RPM/TPM/RPD.

## Coût et sécurité

Le passage en payant ne désactive aucun garde-fou :

- cache IA avant quota et appel provider ;
- compteur local par provider + modèle ;
- garde-fou de débit ;
- limite quotidienne de requêtes ;
- fallback déterministe en cas de quota/erreur ;
- minimisation des données envoyées au provider.

Google applique aussi, selon le tier et l’état du compte, des limites de débit basées sur les dépenses. Ce mécanisme fournisseur ne remplace pas les garde-fous JobPilot.

Le pourcentage JobPilot est un garde-fou de débit, pas un budget monétaire. Pour un contrôle budgétaire strict, utiliser aussi les protections de facturation disponibles côté Google Cloud / AI Studio.

## Évolution

Si AI Studio affiche de nouvelles limites pour `gemini-3.5-flash-lite`, mettre à jour le preset et ses tests. Si le modèle actif change, créer ou saisir un profil spécifique au nouveau modèle plutôt que de réutiliser les chiffres d’un autre modèle.
