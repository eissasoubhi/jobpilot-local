# Connecteur France Travail

JobPilot utilise l’API officielle **Offres d’emploi v2** avec le flux OAuth2 `client_credentials`.

## Prérequis

Créer une application sur France Travail.io, demander l’accès à l’API Offres d’emploi, puis récupérer les identifiants de l’application. Les secrets ne doivent jamais être commités.

```dotenv
FRANCE_TRAVAIL_CLIENT_ID=your-client-id
FRANCE_TRAVAIL_CLIENT_SECRET=your-client-secret
```

La création de l’application seule ne suffit pas : le produit **API Offres d’emploi** doit être rattaché et actif dans cette application sur France Travail.io.

Les valeurs techniques suivantes sont configurables uniquement pour suivre une évolution officielle de l’API ou faciliter les tests :

```dotenv
FRANCE_TRAVAIL_SCOPE=api_offresdemploiv2 o2dsoffre
FRANCE_TRAVAIL_TOKEN_ENDPOINT=https://entreprise.francetravail.fr/connexion/oauth2/access_token?realm=/partenaire
FRANCE_TRAVAIL_SEARCH_ENDPOINT=https://api.francetravail.io/partenaire/offresdemploi/v2/offres/search
FRANCE_TRAVAIL_RESULTS_PER_QUERY=50
```

Après modification de `.env`, recréer les services API et scheduler :

```bash
docker compose up -d --force-recreate api scheduler
```

## Fonctionnement

Une synchronisation :

1. demande un jeton OAuth à France Travail ;
2. construit au maximum six recherches à partir des intitulés ciblés ;
3. récupère au maximum 150 offres par recherche, 50 par défaut ;
4. normalise l’identifiant, le titre, l’entreprise, le lieu, le contrat, le mode de travail, la description, la date, l’URL et le salaire annuel explicite ;
5. envoie chaque résultat dans le catalogue canonique multi-sources de JobPilot.

Les salaires mensuels, horaires ou ambigus ne sont pas convertis artificiellement en salaire annuel. Les offres déjà connues restent idempotentes grâce à l’identifiant France Travail.

## Diagnostic d’une authentification HTTP 400

JobPilot affiche le code OAuth et une description limitée lorsque France Travail les fournit. Le client secret n’est jamais inclus dans le message d’erreur.

- `invalid_scope` : vérifier que le produit API Offres d’emploi est rattaché et actif dans l’application, puis comparer `FRANCE_TRAVAIL_SCOPE` avec le scope affiché dans le portail ;
- `invalid_client` : vérifier que le client ID et le client secret viennent de la même application et qu’aucun espace ou retour à la ligne n’a été copié ;
- `unauthorized_client` : vérifier que l’application est autorisée à utiliser le flux `client_credentials` et le produit Offres d’emploi ;
- autre code : vérifier l’état de l’application et de sa souscription au produit dans France Travail.io.

Après toute correction, recréer les conteneurs `api` et `scheduler`, puis relancer **Tester maintenant** depuis la page Connecteurs.

## Politique et limites

- canal : API officielle uniquement ;
- statut : `AUTHORIZED_ONLY` ;
- aucune requête lorsque les identifiants manquent ;
- une authentification et au maximum six recherches par synchronisation ;
- aucun login utilisateur, cookie, scraping, CAPTCHA ou contournement de quota ;
- les tests utilisent `MockHttpClient` et ne contactent jamais France Travail.

Le connecteur apparaît dans la page **Connecteurs** dès le démarrage. Il reste en configuration requise tant que les deux identifiants ne sont pas présents.
