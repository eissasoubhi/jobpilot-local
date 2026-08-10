# Dead-letter queue des connecteurs

JobPilot conserve une dead-letter queue (DLQ) minimale pour les erreurs de collecte ou d'import qui se répètent.

## Pourquoi

L'historique `ConnectorSyncRun` reste utile pour comprendre une synchronisation donnée, mais une erreur identique peut réapparaître plusieurs jours de suite et se perdre dans les historiques successifs. La DLQ donne une identité stable à ce problème persistant.

## Cycle de vie

Une erreur est regroupée par connecteur, étape et empreinte stable :

- `TRACKING` : 1 ou 2 occurrences ;
- `OPEN` : 3 occurrences ou plus ;
- `RESOLVED` : le même connecteur/payload a ensuite été traité avec succès ou l'entrée a été résolue manuellement.

Si une erreur réapparaît après résolution, le compteur repart à 1. Les entrées `TRACKING` qui ne se reproduisent plus sont supprimées après 7 jours lors d'une synchronisation ultérieure.

Deux étapes sont distinguées :

- `SEARCH` : le connecteur n'a pas réussi à produire sa liste d'offres ;
- `IMPORT` : un payload produit par le connecteur n'a pas pu rejoindre le catalogue canonique.

Une offre rejetée normalement par le filtre de profil n'est pas une erreur et résout une ancienne dead-letter `IMPORT` éventuelle.

## Données conservées

La DLQ ne stocke jamais le HTML, le corps d'un e-mail, les cookies, les credentials ni le `rawData` du connecteur.

Elle conserve uniquement :

- code du connecteur et étape ;
- empreinte SHA-256 ;
- identifiant externe, titre et URL lorsque disponibles ;
- classe et message d'erreur borné ;
- compteur et dates d'échec/résolution.

Pour les URLs, JobPilot conserve seulement `scheme://host/path`. La query string et le fragment sont supprimés afin d'éviter de retenir des `trackingId`, tokens ou paramètres d'alertes.

## API

`GET /api/connectors/dead-letters` retourne par défaut les entrées `OPEN`.

Paramètres :

- `state=OPEN|TRACKING|RESOLVED|ALL` ;
- `limit=1..200`.

`POST /api/connectors/dead-letters/{id}/resolve` marque une entrée comme résolue. Cette action ne relance pas automatiquement la collecte ou l'import : elle sert à reconnaître qu'un incident a été traité ou accepté.

Chaque objet retourné par `GET /api/connectors` expose aussi `deadLetterOpen`, le nombre d'incidents ouverts pour ce connecteur.

## Exploitation

Une dead-letter `OPEN` doit être analysée avec l'historique du connecteur : parser cassé, changement de schéma, blocage HTTP, payload incomplet ou erreur de normalisation.

La DLQ ne remplace pas les alertes de fraîcheur, les métriques `zeroResults`, le circuit breaker HTTP ni les versions de parser. Elle complète ces signaux en donnant une mémoire persistante aux erreurs répétitives.
