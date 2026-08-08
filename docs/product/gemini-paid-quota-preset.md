# Preset de quota Gemini payant

JobPilot conserve ses garde-fous locaux même lorsque le projet Gemini utilise un tier payant.

## Source de vérité

Les limites Gemini ne sont pas universelles : elles dépendent du modèle, du projet, du tier et de l’état du compte. Google recommande de consulter les limites actives dans Google AI Studio.

Documentation officielle :

- https://ai.google.dev/gemini-api/docs/rate-limits
- https://ai.google.dev/gemini-api/docs/billing

Le 9 août 2026, la page **Gemini API Rate Limit** du projet Tier 1 utilisé avec JobPilot affichait pour **Gemini 3.5 Flash-Lite** :

- 4 000 RPM ;
- 4 000 000 TPM d’entrée ;
- 10 000 RPD.

Ces valeurs sont un instantané du projet courant, pas des constantes universelles. Le preset doit être revérifié si le modèle, le projet ou le tier change.

## Preset Tier 1 — Gemini 3.5 Flash-Lite

La page `/parametres/integrations` expose un bouton **Appliquer les limites Tier 1 observées** uniquement lorsque le modèle actif est `gemini-3.5-flash-lite`.

Le preset enregistre les plafonds fournisseur observés :

- 4 000 RPM ;
- 4 000 000 TPM ;
- 10 000 RPD ;
- 80 % de quota utilisable par JobPilot.

Le `AiQuotaManager` applique ensuite la marge existante. Les plafonds locaux réellement utilisables deviennent :

- 3 200 RPM ;
- 3 200 000 TPM ;
- 8 000 RPD.

Le preset n’écrase ni le modèle, ni la clé API, ni l’état d’activation du matching. Il ne déclenche aucun appel Gemini à lui seul.

## Protection contre un mauvais modèle

Les quotas sont spécifiques au modèle. JobPilot refuse donc d’appliquer automatiquement ce preset si le modèle actif n’est pas exactement `gemini-3.5-flash-lite`.

En cas de passage à `gemini-3.6-flash` ou à un autre modèle, il faut d’abord consulter AI Studio puis saisir les limites de ce modèle dans les champs RPM/TPM/RPD.

## Coût et sécurité

Le passage en payant ne désactive aucun garde-fou :

- cache IA avant quota et appel provider ;
- compteur local par provider + modèle ;
- marge de sécurité ;
- limite quotidienne de requêtes ;
- fallback déterministe en cas de quota/erreur ;
- minimisation des données envoyées au provider.

Le pourcentage de sécurité est un garde-fou de débit, pas un budget monétaire. Les limites de dépense et protections de facturation restent gérées côté projet Google. Pour un contrôle budgétaire strict, il faut également utiliser les protections de facturation disponibles dans Google Cloud / AI Studio.

## Évolution

Si AI Studio affiche de nouvelles limites pour `gemini-3.5-flash-lite`, mettre à jour le preset et ses tests. Si le modèle actif change, créer ou saisir un profil spécifique au nouveau modèle plutôt que de réutiliser les chiffres d’un autre modèle.
