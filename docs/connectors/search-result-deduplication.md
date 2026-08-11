# Déduplication des recherches multi-mots-clés

Une même offre peut être retournée par plusieurs recherches d’une source, par exemple `PHP` et `Symfony`. JobPilot fusionne ces résultats avant l’import canonique afin de ne pas compter plusieurs fois la même offre.

## Identité dans ce niveau de fusion

La fusion intra-source utilise :

1. `externalId` lorsqu’il est disponible ;
2. sinon la combinaison déterministe `sourceUrl + title`.

Ce niveau ne remplace pas la déduplication canonique multi-sources existante. Il évite uniquement les doublons créés par plusieurs requêtes de recherche du même scraper.

## Candidat conservé

Lorsque plusieurs recherches retrouvent la même offre, la version la plus riche est conservée selon les mêmes signaux simples que l’extraction actuelle : longueur de description et présence des champs entreprise, lieu, contrat, mode de travail et date de publication.

## Provenance

`rawData.discoveredByKeywords` conserve la liste ordonnée des mots-clés ayant retrouvé l’offre. Cette information sert aux diagnostics et à l’explicabilité ; elle n’altère ni le score de matching ni l’identité canonique.

Le résultat expose également `rawCount` et `duplicateCount` pour permettre au futur orchestrateur multi-requêtes d’afficher clairement les volumes bruts et fusionnés.

## Garde-fou quota

Cette brique ne déclenche aucun appel réseau. L’orchestrateur qui l’utilisera devra partager un seul `connectorCode` et un budget global de requêtes afin que plusieurs mots-clés ne contournent jamais la limite par synchronisation ou le quota journalier.
