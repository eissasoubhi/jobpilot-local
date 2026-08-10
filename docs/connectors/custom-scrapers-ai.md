# Interprétation IA des scrapers personnalisés

Ce composant est un fallback pour les pages HTTP publiques autorisées dont le DOM ne peut pas être interprété suffisamment par les extracteurs déterministes.

## Principe

L’IA ne remplace jamais `JobPosting`, les parseurs DOM ou les règles de pagination existantes. Elle sert uniquement à identifier des liens de fiches d’offres parmi des liens déjà présents dans la page.

Le contexte envoyé à Gemini est volontairement réduit :

- au maximum 20 000 caractères de texte visible ;
- les balises `script`, `style`, `template`, `header`, `footer` et le texte de navigation sont retirés du texte envoyé ;
- au maximum 120 liens HTTPS du même domaine ;
- aucun HTML brut n’est conservé dans le cache IA.

## Grounding obligatoire

Gemini ne peut pas inventer une URL exploitable par JobPilot.

Chaque `sourceUrl` renvoyée doit correspondre **exactement** à une URL de la liste `allowedAnchors` fournie au modèle. Le backend rejette ensuite toute URL qui :

- n’était pas présente dans cette liste ;
- appartient à un autre domaine ;
- a été inventée ou transformée par le modèle ;
- est dupliquée.

Une offre identifiée par IA reçoit `extractionMethod=AI_GROUNDED_LINK` et `needsDetailFetch=true`. Elle n’est donc pas considérée comme fiable par le seul fait d’avoir été trouvée par Gemini : la fiche détail doit encore être récupérée et évaluée par le garde-fou de qualité normal.

## Quotas et cache

Le fallback utilise la configuration Gemini existante de JobPilot :

- activation IA ;
- clé API ;
- modèle ;
- RPM ;
- TPM ;
- RPD ;
- pourcentage de quota utilisable.

`AiQuotaManager` réserve et réconcilie les tokens dans le même espace `provider + model` que le matching. Une extraction n’est pas lancée si le quota local n’autorise pas la requête.

Les résultats normalisés non vides sont mis en cache pendant 7 jours à partir d’une empreinte qui comprend le contexte public nettoyé, les liens autorisés, le modèle logique de réponse et la page source. Le cache ne contient pas le HTML brut.

## État de livraison

La première tranche fournit l’extracteur grounded, le cache et le contrôle de quota, mais **ne branche pas encore Gemini dans la synchronisation automatique**.

La tranche suivante devra déclencher ce fallback uniquement lorsque l’extraction déterministe d’une page HTTP ne produit aucun candidat exploitable, avec un nombre maximal d’appels IA par synchronisation.
