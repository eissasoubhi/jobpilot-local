# Critères globaux de recherche

La page **Critères de recherche** distingue les valeurs utilisées pour collecter les offres des règles appliquées localement après leur récupération.

## Clés actives

### `targetJobs`

Liste des postes ciblés. `JobSearchSyncService` transmet cette liste à chaque connecteur avec `skills`.

Selon le connecteur, elle peut être :

- transformée en requêtes envoyées à une API, comme pour France Travail et Adzuna ;
- utilisée comme filtre local sur un catalogue public récupéré en nombre limité, comme pour Arbeitnow ;
- ignorée par un connecteur dont le canal d’origine possède déjà ses propres critères, par exemple une alerte e-mail.

### `skills`

Liste des compétences recherchées. Elle est transmise à chaque connecteur, utilisée comme solution de repli lorsque les intitulés ne produisent aucune requête exploitable et utilisée dans le score de matching local.

### `exclusions`

Liste appliquée uniquement après import. Si une exclusion apparaît dans le titre ou la description normalisée, l’offre est rejetée par le filtre local avec un score nul.

Cette liste n’est jamais envoyée aux plateformes externes.

### `matchingThreshold`

Seuil local compris entre `0` et `100`. Une offre non rejetée peut être préparée automatiquement lorsque son score atteint ce seuil et que la préparation automatique est activée.

Ce seuil ne modifie pas les résultats renvoyés par une plateforme et ne déclenche pas à lui seul une candidature.

## Données de profil non encore appliquées au moteur

Les contrats acceptés, la mobilité, la ville, le code postal et la préférence de mode de travail sont enregistrés dans le profil candidat. Dans l’état actuel du moteur, ils ne sont pas envoyés aux connecteurs et ne participent pas au calcul de `MatchingScoreService`.

La page l’indique explicitement afin de ne pas laisser croire que ces valeurs filtrent déjà la collecte. Leur intégration devra faire l’objet d’une livraison séparée avec règles de normalisation, tests et comportement produit clairement définis.

## Édition

La page charge et enregistre les quatre clés actives via l’API existante :

```text
GET /api/settings
PUT /api/settings
```

La mise à jour est partielle : seuls `targetJobs`, `skills`, `exclusions` et `matchingThreshold` sont envoyés depuis cette page. Les paramètres de rémunération, Gmail et automatisation restent inchangés.

Les listes sont saisies à raison d’une valeur par ligne. Les espaces sont normalisés et les doublons sont supprimés sans tenir compte de la casse avant l’enregistrement.

## Aperçus par connecteur

Les critères globaux constituent la source de vérité. Les sections par connecteur servent uniquement à montrer la transformation réellement appliquée et, lorsqu’un diagnostic existe, les résultats de la dernière synchronisation.

Pour France Travail, la page affiche le contenu exact du paramètre `motsCles`, le statut HTTP et le nombre d’offres reçu pour chaque requête. Le bouton **Tester ces critères maintenant** utilise les valeurs globales préalablement enregistrées.
