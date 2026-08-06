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
3. utilise les compétences uniquement si aucun intitulé ne produit de requête exploitable ;
4. récupère au maximum 150 offres par recherche, 50 par défaut ;
5. normalise l’identifiant, le titre, l’entreprise, le lieu, le contrat, le mode de travail, la description, la date, l’URL et le salaire annuel explicite ;
6. envoie chaque résultat dans le catalogue canonique multi-sources de JobPilot.

Un statut HTTP `204 No Content` sur une recherche signifie qu’aucune offre ne correspond à cette requête. JobPilot considère ce résultat comme vide, continue avec les autres requêtes de la synchronisation et ne classe plus le connecteur en erreur pour ce seul statut.

Les salaires mensuels, horaires ou ambigus ne sont pas convertis artificiellement en salaire annuel. Les offres déjà connues restent idempotentes grâce à l’identifiant France Travail.

## Voir et modifier les critères

La page **Critères de recherche** est accessible depuis la navigation principale.

Elle affiche :

- les intitulés ciblés enregistrés dans les paramètres globaux ;
- les compétences utilisées comme solution de repli ;
- les requêtes finales réellement envoyées dans le paramètre `motsCles` de France Travail ;
- le tri appliqué et la limite de six requêtes par synchronisation.

Les intitulés et compétences sont saisis à raison d’un élément par ligne. Les doublons sont supprimés sans tenir compte de la casse. Chaque liste accepte au maximum vingt éléments de 120 caractères.

Le connecteur retire des intitulés certains termes génériques comme `senior`, `developer`, `développeur`, `engineer` et `native`, puis remplace les séparateurs comme `/` ou `-` par des espaces. La page affiche le résultat exact de cette transformation avant la prochaine synchronisation.

Les valeurs sont globales : une modification depuis cette page met également à jour les champs **Postes ciblés** et **Compétences** de la page Paramètres, ainsi que les autres connecteurs qui utilisent ces réglages.

API locale correspondante :

```text
GET /api/connectors/france-travail/criteria
PUT /api/connectors/france-travail/criteria
POST /api/connectors/france-travail/sync
```

Exemple de mise à jour :

```json
{
  "targetJobs": ["Senior PHP/Symfony", "Full-Stack Symfony/React"],
  "skills": ["PHP", "Symfony", "React"]
}
```

## Mesurer l’efficacité des mots-clés

Chaque synchronisation conserve dans `connector_sync_run.details.searchDiagnostics` un diagnostic limité aux informations nécessaires :

- mot-clé envoyé ;
- statut HTTP de la recherche ;
- résultat, absence de résultat ou erreur ;
- nombre d’offres reçues ;
- nombre d’offres uniques ajoutées au lot après déduplication interne.

Aucun jeton OAuth, client ID, client secret, corps de réponse complet ou donnée de candidature n’est conservé dans ce diagnostic. La mesure réutilise les requêtes déjà effectuées et n’ajoute aucun appel API.

La page **Critères de recherche** affiche le dernier diagnostic sous chaque mot-clé. Elle indique aussi si ce diagnostic correspond encore aux critères actuels. Après une modification, le résultat précédent est marqué **Critères modifiés depuis ce test** jusqu’à la prochaine synchronisation France Travail.

Le bouton **Tester ces critères maintenant** lance le connecteur France Travail avec les valeurs enregistrées, puis recharge automatiquement les diagnostics depuis le serveur. Il ne sauvegarde pas les champs encore en cours d’édition : il faut d’abord cliquer sur **Enregistrer les critères**. Si le connecteur est désactivé, incomplet ou bloqué, le motif est affiché et les anciens diagnostics restent visibles.

Un mot-clé qui renvoie régulièrement `204` peut ainsi être supprimé ou élargi. Un statut `200` ou `206` avec zéro résultat est également présenté comme une recherche vide plutôt que comme une panne globale.

## Diagnostic d’une authentification HTTP 400

JobPilot affiche le code OAuth et une description limitée lorsque France Travail les fournit. Le client secret n’est jamais inclus dans le message d’erreur.

- `invalid_scope` : vérifier que le produit API Offres d’emploi est rattaché et actif dans l’application, puis comparer `FRANCE_TRAVAIL_SCOPE` avec le scope affiché dans le portail ;
- `invalid_client` : vérifier que le client ID et le client secret viennent de la même application et qu’aucun espace ou retour à la ligne n’a été copié ;
- `unauthorized_client` : vérifier que l’application est autorisée à utiliser le flux `client_credentials` et le produit Offres d’emploi ;
- autre code : vérifier l’état de l’application et de sa souscription au produit dans France Travail.io.

Après toute correction, recréer les conteneurs `api` et `scheduler`, puis relancer **Tester maintenant** depuis la page Connecteurs ou **Tester ces critères maintenant** depuis la page Critères de recherche.

## Politique et limites

- canal : API officielle uniquement ;
- statut : `AUTHORIZED_ONLY` ;
- aucune requête lorsque les identifiants manquent ;
- une authentification et au maximum six recherches par synchronisation ;
- aucun login utilisateur, cookie, scraping, CAPTCHA ou contournement de quota ;
- les tests utilisent `MockHttpClient` et ne contactent jamais France Travail.

Le connecteur apparaît dans la page **Connecteurs** dès le démarrage. Il reste en configuration requise tant que les deux identifiants ne sont pas présents.
