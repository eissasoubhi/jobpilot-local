# Matching IA expérimental

JobPilot peut utiliser une analyse sémantique IA pour le score de compatibilité d'une offre. Cette intégration est **désactivée par défaut** et conserve le moteur déterministe comme solution de repli.

## Premier fournisseur de test

Le premier fournisseur est Google Gemini avec le modèle stable :

```text
Gemini 3.5 Flash-Lite
gemini-3.5-flash-lite
```

L'intégration utilise l'API Gemini Interactions et demande une sortie JSON structurée. Le résultat contient notamment :

- le score de compatibilité de `0` à `100` ;
- une décision `MATCH`, `REVIEW` ou `NO_MATCH` ;
- la confiance du modèle ;
- le rôle principal demandé ;
- la stack principale et la stack secondaire ;
- les prérequis obligatoires et facultatifs ;
- les prérequis obligatoires manquants ;
- les conflits entre l'offre et le profil ;
- une explication courte du score.

Le but prioritaire est de comprendre le rôle réellement demandé dans la description complète, et non de compter seulement les mots-clés présents dans l'annonce.

## Activation locale

La clé Gemini est un secret local et ne doit jamais être commitée :

```dotenv
AI_MATCHING_ENABLED=1
GEMINI_API_KEY=your-local-api-key
GEMINI_MATCHING_MODEL=gemini-3.5-flash-lite
```

Sans clé, avec `AI_MATCHING_ENABLED=0`, lorsqu'un quota est atteint, si Gemini est indisponible ou si sa réponse est invalide, JobPilot utilise automatiquement `MatchingScoreService` avec le matching déterministe existant.

Les exclusions explicites configurées dans JobPilot restent prioritaires et sont vérifiées avant tout appel IA.

## Filtre IA pendant la synchronisation

Lorsque le matching IA est activé **et** qu'une clé effective est disponible, les nouvelles offres issues des connecteurs passent par un filtre de profil avant leur première persistance dans le catalogue.

L'ordre est volontairement :

1. recevoir l'offre depuis le connecteur ;
2. vérifier les occurrences/doublons et les fusions canoniques déjà connues ;
3. uniquement si une nouvelle offre canonique allait être créée, lancer l'analyse IA ;
4. ne pas enregistrer l'offre si Gemini retourne `NO_MATCH` avec une confiance d'au moins `85 %` et un signal concret de mismatch (score sous le seuil configuré, prérequis obligatoire manquant ou conflit explicite) ;
5. sinon conserver l'offre et poursuivre le traitement normal.

Cette règle est volontairement conservatrice : `MATCH`, `REVIEW`, les réponses peu confiantes et les réponses incohérentes restent dans JobPilot pour éviter les faux négatifs.

Le filtre est **fail-open**. Si Gemini n'est pas disponible, si le quota local est atteint, si le cache/quota manager échoue ou si l'API ne renvoie pas d'analyse exploitable, JobPilot conserve l'offre au lieu de la perdre. Le moteur déterministe continue ensuite à calculer son score normal.

Le résultat IA passe par le cache existant avant la réservation de quota. Une offre identique déjà analysée ne consomme donc pas un nouvel appel Gemini. Les doublons exacts ou les occurrences fusionnées sont également traités avant le filtre afin d'éviter des appels IA inutiles.

Les offres écartées par ce filtre ne créent ni `JobOffer`, ni occurrence source, ni candidature préparée. Seul le **compteur** `profileFiltered` est conservé dans le diagnostic de synchronisation ; le contenu de l'offre rejetée n'est pas stocké par ce mécanisme.

Les imports manuels et les offres ajoutées explicitement par l'utilisateur ne sont pas concernés par ce filtre de synchronisation.

## Données envoyées pendant cette phase de test

Pour limiter les données personnelles envoyées au free tier, le prompt Gemini reçoit uniquement :

### Critères candidat

- `targetJobs` ;
- `skills` ;
- `exclusions`.

### Offre

- titre ;
- description ;
- type de contrat ;
- localisation ;
- mode de travail.

Cette première intégration **n'envoie pas** à Gemini :

- le nom du candidat ;
- l'adresse e-mail ;
- le numéro de téléphone ;
- le CV complet ;
- les messages Gmail ;
- les identifiants des connecteurs.

L'envoi du CV ou des e-mails à un fournisseur IA devra faire l'objet d'une livraison et d'une décision de confidentialité séparées.

## Attention au free tier Gemini

Google indique actuellement que le contenu envoyé via le niveau gratuit de la Gemini Developer API peut être utilisé pour améliorer ses produits. Les essais gratuits doivent donc rester sur les données minimisées ci-dessus. Le passage à un niveau payant ou à un autre fournisseur sera évalué séparément avant d'étendre l'IA aux CV, e-mails ou autres données personnelles.

Les limites exactes de requêtes du free tier sont gérées par Google et peuvent évoluer. JobPilot ne suppose aucun quota fixe : une réponse de limitation ou une indisponibilité déclenche simplement le fallback déterministe.

## Architecture remplaçable

Le code métier dépend de `AiJobMatchAnalyzerInterface`, pas directement de Gemini. `GeminiJobMatchAnalyzer` est le premier adaptateur. Cette séparation permettra de comparer ou remplacer ultérieurement Gemini par OpenAI, Mistral, Anthropic, un modèle local ou un autre fournisseur sans réécrire le moteur de matching.

## Portée de cette livraison

Cette phase introduit le matching IA opt-in, son fallback et le filtre conservateur des **nouvelles offres synchronisées** avant persistance. Elle ne modifie pas :

- les règles d'authentification des sources ;
- les CAPTCHA, quotas ou règles de scraping ;
- l'envoi externe des candidatures ;
- le schéma de base de données.
