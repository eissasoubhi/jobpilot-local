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
- la limite configurée de pages par recherche ;
- `requestedMaxListingRequests`, le budget théorique avant garde-fou ;
- `globalPageBudget`, le budget réellement autorisé pour les pages de liste ;
- `budgetLimited`, qui indique si le plan a dû être borné ;
- `pageLimit` pour chaque mot-clé après répartition équitable du budget.

Le garde-fou existant reste fixé à **10 pages de liste maximum par synchronisation**. JobPilot ne multiplie donc pas silencieusement la charge réseau lorsqu’on ajoute des mots-clés. Par exemple, quatre recherches `PHP`, `Symfony`, `Vue.js`, `React.js` configurées à cinq pages chacune demanderaient théoriquement 20 pages ; le budget global 10 est réparti `3 / 3 / 2 / 2`.

`estimatedMaxListingRequests` reste exposé pour compatibilité et représente désormais le budget global effectif, pas le produit non borné `searchCount × maxPages`.

Cette route sert au diagnostic manuel avant de brancher l’exécution multi-mots-clés. Elle permet de vérifier les templates propres à chaque plateforme et leur budget de requêtes sans déclencher de scraping.

## Garde-fous

- HTTPS obligatoire ;
- même domaine que `listingUrl` ;
- seul `{keyword}` est accepté dans ce premier lot ;
- 20 mots-clés maximum par source ;
- 80 caractères maximum par mot-clé ;
- 10 pages de liste maximum par synchronisation, réparties entre les recherches ;
- aucune authentification, cookie privé ou information sensible dans le template.

La pagination continue d’être gérée séparément par le scraper existant. Un placeholder `{page}` n’est donc pas introduit dans ce lot afin de ne pas dupliquer la logique de pagination par source.

## Étapes suivantes

1. faire consommer les `pageLimit` calculés par l’orchestrateur d’extraction ;
2. fusionner les résultats via `CustomScraperSearchResultMerger` ;
3. enrichir les diagnostics avec les résultats et erreurs par mot-clé ;
4. ajouter l’édition du template et des mots-clés dans la configuration avancée de l’interface Scraping.
