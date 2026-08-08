# Preset de quota Gemini payant

JobPilot conserve ses garde-fous locaux même lorsque le projet Gemini utilise un tier payant.

## Pourquoi un preset local

Les limites Gemini ne sont pas universelles : elles dépendent du modèle, du projet, du tier et de l’état du compte. Google recommande de consulter les limites actives dans Google AI Studio. JobPilot ne doit donc pas présenter un chiffre codé en dur comme s’il s’agissait du quota contractuel Google.

Documentation officielle :

- https://ai.google.dev/gemini-api/docs/rate-limits
- https://ai.google.dev/gemini-api/docs/billing

## Profil « Payant équilibré »

La page `/parametres/integrations` expose un bouton **Appliquer le profil payant recommandé**.

Ce preset enregistre dans la configuration locale :

- 60 RPM ;
- 1 000 000 TPM d’entrée ;
- 2 000 RPD ;
- 80 % de marge utilisable.

Le `AiQuotaManager` applique ensuite la marge existante. Les plafonds réellement utilisables par JobPilot deviennent donc :

- 48 RPM ;
- 800 000 TPM ;
- 1 600 RPD.

Ces valeurs sont une politique JobPilot destinée à augmenter le débit par rapport au profil historique de test gratuit tout en conservant une limite quotidienne. Elles ne remplacent pas les limites Google.

## Source de vérité

Si Google AI Studio affiche pour le modèle/projet actif une limite inférieure à l’une des valeurs du preset, la valeur AI Studio doit être recopiée dans les champs RPM/TPM/RPD de JobPilot.

Une limite Google plus basse peut provoquer un `429 RESOURCE_EXHAUSTED` même si le compteur local JobPilot autorise encore l’appel. Le fallback déterministe existant reste alors disponible.

## Coût et sécurité

Le passage en payant ne désactive aucun garde-fou :

- cache IA avant quota et appel provider ;
- compteur local par provider + modèle ;
- marge de sécurité ;
- limite quotidienne de requêtes ;
- fallback déterministe en cas de quota/erreur ;
- minimisation des données envoyées au provider.

Le preset ne constitue pas un budget monétaire. Les plafonds de dépense et les limites basées sur les dépenses restent gérés côté compte/projet Google. Pour un contrôle budgétaire strict, il faut également configurer les protections de facturation disponibles côté Google.
