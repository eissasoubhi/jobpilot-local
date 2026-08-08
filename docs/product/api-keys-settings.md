# Configuration et clés API

JobPilot expose une page locale **Configuration & clés API** sous `/parametres/integrations`.

## Premier provider géré : Gemini

La première livraison permet de modifier depuis l’interface :

- l’activation du matching IA ;
- le modèle Gemini utilisé ;
- la clé API Gemini.

La configuration enregistrée depuis l’interface prend le dessus sur les variables d’environnement existantes :

```dotenv
AI_MATCHING_ENABLED=
GEMINI_MATCHING_MODEL=
GEMINI_API_KEY=
```

Si aucune surcharge locale n’existe, JobPilot continue d’utiliser les variables `.env`. Supprimer une clé enregistrée depuis l’interface restaure donc automatiquement la clé d’environnement lorsqu’elle existe.

## Stockage des secrets

Les secrets saisis dans l’interface ne sont jamais enregistrés dans la base de données ni dans le dépôt Git.

Ils sont stockés dans `var/private/ai-matching-config.enc` et chiffrés avec `sodium_crypto_secretbox`, en utilisant `APP_ENCRYPTION_KEY` comme source de clé. Le fichier est créé avec des permissions locales restrictives.

L’API de configuration ne retourne jamais la valeur d’une clé. Elle expose uniquement :

- si une clé effective est configurée ;
- si elle provient de l’interface, de l’environnement ou d’aucune source ;
- le provider, le modèle et l’état d’activation.

Un champ de clé vide lors d’un enregistrement conserve le secret courant. Une action explicite est nécessaire pour supprimer la clé enregistrée dans JobPilot.

## Application à chaud

Le matcher résout la configuration effective à chaque analyse. Une modification de l’activation, du modèle ou de la clé Gemini depuis l’interface est donc utilisée par les prochains matchings sans redémarrage du conteneur.

Les erreurs Gemini, quotas et réponses invalides continuent de basculer vers le matcher déterministe existant.

## Free tier Gemini

Pendant les tests avec le niveau gratuit, JobPilot conserve la minimisation introduite avec le matching IA : seuls les critères de matching et les champs nécessaires de l’offre sont envoyés. Le CV complet, le nom, les e-mails, Gmail et les identifiants des autres connecteurs ne sont pas transmis à Gemini.

## Extension prévue

Le coffre est introduit d’abord pour Gemini afin de garder la livraison petite et testable. Les prochains providers IA et connecteurs pourront réutiliser le même principe de stockage chiffré et d’API masquée, sans exposer les secrets dans les réponses frontend.
