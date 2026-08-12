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

## Tester réellement les pages de liste

`POST /api/custom-scrapers/{id}/search-preview` exécute le plan multi-mots-clés sur les pages publiques autorisées du connecteur. Ce test manuel utilise le même `connectorCode` pour toutes les recherches : le quota par synchronisation reste donc partagé entre PHP, Symfony, Vue.js, React.js, etc.

Le résultat expose :

- `networkRequests`, `durationMs` et le budget global ;
- `rawCandidateCount`, `duplicateCount` et `candidateCount` après fusion ;
- les offres fusionnées avec `rawData.discoveredByKeywords` ;
- un diagnostic par mot-clé : URL de départ, pages visitées, limite, nombre brut d’offres, `statusCodes`, dernier statut HTTP, durée, mode recommandé, raison d’arrêt et erreur éventuelle ;
- un historique page par page avec URL, statut HTTP et pagination détectée ;
- `requiresBrowser` lorsqu’une page publique autorisée, y compris une page de pagination ultérieure, semble nécessiter JavaScript ;
- `stoppedEarly` et `globalError` si la collecte doit être interrompue.

Une erreur d’accès ou de transport arrête les recherches suivantes au lieu d’essayer d’autres mots-clés pour contourner un blocage. De la même façon, si le HTML indique qu’un rendu navigateur est nécessaire, JobPilot arrête la collecte HTTP et signale `requiresBrowser` sans tenter de mécanisme furtif ou de contournement.

La déduplication intervient avant tout futur enrichissement de détail : une même offre trouvée avec PHP et Symfony n’est conservée qu’une fois, avec les deux mots-clés de provenance.

## Garde-fous

- HTTPS obligatoire ;
- même domaine que `listingUrl` ;
- seul `{keyword}` est accepté dans ce premier lot ;
- 20 mots-clés maximum par source ;
- 80 caractères maximum par mot-clé ;
- 10 pages de liste maximum par synchronisation, réparties entre les recherches ;
- un seul quota partagé entre toutes les recherches ;
- robots.txt, circuit breaker et détection anti-automatisation restent appliqués par le client HTTP contrôlé ;
- aucune authentification, cookie privé ou information sensible dans le template ;
- aucun appel live dans la CI : les tests utilisent des réponses HTML locales simulées.

La pagination continue d’être détectée à partir des pages publiques. Un placeholder `{page}` n’est pas introduit afin de ne pas dupliquer la logique de pagination propre aux sites.

## Étapes suivantes

1. intégrer cette phase de liste multi-mots-clés dans `CustomScraperExtractionService::collect()` ;
2. enrichir uniquement les candidats déjà fusionnés avec les pages détail ;
3. afficher les diagnostics par mot-clé dans l’interface de configuration des connecteurs ;
4. ajouter l’édition du template et des mots-clés dans la configuration avancée de l’interface Scraping.
