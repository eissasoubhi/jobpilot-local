# Recherches configurables des scrapers

Les scrapers personnalisés peuvent préparer plusieurs recherches ciblées au lieu de parcourir tout le catalogue d’une plateforme.

## Configuration

Une source peut déclarer :

- `searchUrlTemplate` : URL HTTPS contenant le placeholder `{keyword}` ;
- `searchKeywords` : liste ordonnée de mots-clés à rechercher.

Exemple :

```text
searchUrlTemplate = https://jobs.example.com/recherche?q={keyword}&sort=date
searchKeywords = [PHP, Symfony, Vue.js, React.js]
```

Le plan produit alors une requête distincte par mot-clé. Le mot-clé est encodé dans l’URL et les doublons de mots-clés, sans tenir compte de la casse, sont supprimés.

Si aucun template ou aucun mot-clé n’est configuré, JobPilot conserve le comportement historique et utilise simplement `listingUrl`.

## Prévisualiser le plan sans lancer de collecte

`GET /api/custom-scrapers/{id}/search-plan` expose le plan calculé sans effectuer aucun appel externe. La réponse contient :

- l’URL finale de chaque recherche et le mot-clé associé ;
- le nombre de recherches ;
- la limite de pages par recherche ;
- le nombre maximal estimé de requêtes de liste (`searchCount × maxPages`).

Cette route sert au diagnostic manuel avant de brancher l’exécution multi-mots-clés. Elle permet de vérifier les templates propres à chaque plateforme et leur budget de requêtes sans déclencher de scraping.

## Garde-fous

- HTTPS obligatoire ;
- même domaine que `listingUrl` ;
- seul `{keyword}` est accepté dans ce premier lot ;
- 20 mots-clés maximum par source ;
- 80 caractères maximum par mot-clé ;
- aucune authentification, cookie privé ou information sensible dans le template.

La pagination continue d’être gérée séparément par le scraper existant. Un placeholder `{page}` n’est donc pas introduit dans ce lot afin de ne pas dupliquer la logique de pagination par source.

## Étapes suivantes

1. exécuter ce plan dans le service d’extraction ;
2. dédupliquer les offres retrouvées par plusieurs recherches ;
3. enrichir les diagnostics avec les résultats et erreurs par mot-clé ;
4. ajouter l’édition du template et des mots-clés dans la configuration avancée de l’interface Scraping.
