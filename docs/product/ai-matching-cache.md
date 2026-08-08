# Cache du matching IA

## Objectif

JobPilot évite de rappeler un fournisseur IA lorsqu'une analyse de matching identique a déjà été calculée récemment.

Le cache est consulté **avant** le quota manager. Un cache hit ne réserve donc aucune requête et ne consomme aucun RPM, TPM ou RPD supplémentaire côté JobPilot.

## Identité d'une analyse

Une entrée n'est réutilisée que lorsque les éléments qui influencent réellement le résultat sont identiques :

- fournisseur IA ;
- modèle ;
- version du prompt / schéma de réponse ;
- critères candidat envoyés au matching : `targetJobs`, `skills`, `exclusions` ;
- champs d'offre envoyés au matching : titre, description, contrat, localisation et mode de travail.

`GeminiJobMatchAnalyzer` calcule une empreinte SHA-256 à partir du prompt exact, de la version du cache et du schéma structuré. Le fournisseur et le modèle font ensuite partie de la clé du cache.

Ainsi, une modification de l'offre, du profil de matching, du modèle, du prompt ou du schéma invalide naturellement l'entrée concernée.

## Stockage local

Le cache est conservé dans le volume privé local :

```text
var/private/ai-matching-cache.json
```

Le fichier ne contient ni clé API ni prompt brut. Il contient uniquement :

- une clé SHA-256 ;
- la date de création ;
- la réponse structurée déjà validée (`score`, rôle, stacks, prérequis, conflits et explication).

Les accès concurrents au fichier sont protégés par un verrou local.

## Durée et taille

Pour limiter la dérive et la croissance du stockage :

- une entrée expire après 30 jours ;
- le cache conserve au maximum 5 000 entrées ;
- les entrées les plus anciennes sont supprimées lorsque la limite est dépassée.

## Ordre d'exécution

Pour une analyse Gemini active :

1. calculer l'empreinte ;
2. rechercher une analyse valide dans le cache ;
3. si elle existe, la retourner immédiatement ;
4. sinon, vérifier/réserver les quotas RPM, TPM et RPD ;
5. appeler Gemini ;
6. réconcilier les tokens réellement consommés ;
7. mettre en cache uniquement une réponse structurée valide.

Un échec de lecture ou d'écriture du cache ne bloque pas le matching : le cache est une optimisation. JobPilot continue alors avec le quota manager et le fallback déterministe existants.

## Sécurité et portée

Cette fonctionnalité ne change pas les politiques des connecteurs, l'authentification, les CAPTCHA, robots, quotas des sources ni l'envoi externe des candidatures. Elle réduit uniquement les appels IA redondants et leur consommation de tokens.
