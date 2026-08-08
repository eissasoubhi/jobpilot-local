# Configuration et clés API

JobPilot expose une page locale **Configuration & clés API** sous `/parametres/integrations`.

## Gemini — provider de matching actif

Gemini reste le provider IA réellement utilisé pour le test V1. L’interface permet de modifier :

- l’activation du matching IA ;
- le modèle Gemini utilisé ;
- la clé API Gemini.

La configuration enregistrée depuis l’interface prend le dessus sur les variables d’environnement existantes :

```dotenv
AI_MATCHING_ENABLED=
GEMINI_MATCHING_MODEL=
GEMINI_API_KEY=
```

Le matcher résout la configuration effective à chaque analyse. Une modification depuis l’interface est donc utilisée par les prochains matchings sans redémarrage du conteneur. Les erreurs Gemini, quotas et réponses invalides continuent de basculer vers le matcher déterministe existant.

## Coffre partagé pour les autres APIs

La même page gère maintenant les configurations suivantes :

### Providers IA alternatifs

- OpenAI : modèle + clé API ;
- Mistral : modèle + clé API ;
- Anthropic : modèle + clé API.

Ces valeurs sont déjà stockées de manière sûre pour permettre une future bascule de provider, mais elles ne déclenchent pas encore d’appel vers ces fournisseurs. Chaque provider sera activé dans une livraison séparée avec son client API, ses tests de sortie structurée et son fallback.

### Connecteurs API actifs

- Adzuna : App ID + App key ;
- France Travail : Client ID + Client secret ;
- SmartRecruiters : identifiants d’entreprise + API token.

Ces trois connecteurs passent par des wrappers de configuration qui relisent le coffre au moment où leur état ou leur recherche est utilisé. Une modification depuis l’interface est donc disponible sans modifier le dépôt Git ni reconstruire les variables `.env`.

Les paramètres non secrets de fonctionnement restent gérés par l’environnement, par exemple le pays Adzuna, les endpoints/scope France Travail et les limites SmartRecruiters. Cette séparation évite qu’une page de gestion de secrets puisse modifier silencieusement les garde-fous de quota ou de conformité.

## Stockage des secrets

Les secrets saisis dans l’interface ne sont jamais enregistrés dans la base de données ni dans le dépôt Git.

Gemini conserve son fichier chiffré historique :

```text
var/private/ai-matching-config.enc
```

Les autres fournisseurs et connecteurs utilisent :

```text
var/private/external-integrations.enc
```

Les deux fichiers sont chiffrés avec `sodium_crypto_secretbox`, en utilisant `APP_ENCRYPTION_KEY` comme source de clé, et sont créés avec des permissions locales restrictives.

Les APIs de configuration ne retournent jamais la valeur d’un secret. Elles exposent uniquement :

- si la valeur effective est configurée ;
- si elle provient de l’interface, de l’environnement ou d’aucune source ;
- les champs non secrets nécessaires à l’administration, par exemple un modèle, un App ID ou un Client ID.

Un champ secret vide lors d’un enregistrement conserve le secret courant. Une action explicite est nécessaire pour supprimer un secret enregistré dans JobPilot.

## Priorité et fallback

L’ordre de résolution est :

1. valeur enregistrée depuis l’interface ;
2. variable `.env` correspondante ;
3. aucune valeur.

Supprimer un secret enregistré depuis l’interface restaure donc automatiquement la valeur d’environnement lorsqu’elle existe.

Les variables optionnelles de fallback pour les providers IA alternatifs sont :

```dotenv
OPENAI_API_KEY=
OPENAI_MATCHING_MODEL=
MISTRAL_API_KEY=
MISTRAL_MATCHING_MODEL=
ANTHROPIC_API_KEY=
ANTHROPIC_MATCHING_MODEL=
```

Les variables existantes Adzuna, France Travail et SmartRecruiters restent compatibles et conservent leur rôle de fallback.

## Sécurité et conformité

Cette interface ne modifie pas les politiques des connecteurs, les quotas, les endpoints réglementés, les règles robots/CAPTCHA ni les comportements de soumission de candidature.

Pendant les tests Gemini free tier, JobPilot conserve la minimisation introduite avec le matching IA : seuls les critères de matching et les champs nécessaires de l’offre sont envoyés. Le CV complet, le nom, les e-mails, Gmail et les identifiants des autres connecteurs ne sont pas transmis à Gemini.
