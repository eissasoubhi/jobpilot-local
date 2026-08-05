# Offres canoniques et occurrences de sources

## Objectif

Une même mission peut être détectée par Gmail, Arbeitnow, Adzuna, une extension ou plusieurs plateformes. JobPilot doit afficher une seule offre métier sans perdre l’origine de chaque détection.

Le catalogue distingue désormais :

- `job_offer` : l’offre canonique utilisée pour le score, le CV, la préparation et la candidature ;
- `job_source_occurrence` : une apparition de cette offre sur une source donnée.

Une candidature reste toujours rattachée à l’offre canonique. Ajouter une nouvelle source ne crée donc pas une seconde candidature.

## Identité d’une occurrence

Une occurrence est idempotente grâce au couple :

```text
sourceCode + externalId
```

Lorsque le même connecteur renvoie à nouveau cet identifiant, JobPilot met uniquement à jour `lastSeenAt`. Le compteur de synchronisation augmente alors dans **occurrences connues**.

## Recherche d’une offre canonique

Pour une occurrence inconnue, la stratégie applique les niveaux suivants dans cet ordre.

### 1. URL canonique exacte

L’URL est normalisée :

- schéma ramené à HTTPS ;
- suppression de `www.` ;
- suppression du slash terminal ;
- tri des paramètres utiles ;
- suppression des paramètres de tracking courants.

Une URL canonique déjà connue produit une fusion avec un score de 100.

### 2. Empreinte exacte

Après normalisation des accents, de la casse, de la ponctuation et des suffixes juridiques, le couple suivant est comparé :

```text
intitulé + entreprise
```

Une égalité exacte produit une fusion avec un score de 98.

### 3. Similarité conservatrice

La fusion approximative évalue :

- les mots significatifs de l’intitulé ;
- le nom de l’entreprise ;
- le type de contrat ;
- la localisation ;
- la proximité des dates de publication.

Les garde-fous actuels sont :

- similarité de l’intitulé au moins égale à 72 % ;
- similarité de l’entreprise au moins égale à 82 % ;
- score global au moins égal à 84/100.

Sous ces seuils, une nouvelle offre canonique est créée. Cette stratégie préfère quelques doublons résiduels à une fusion erronée de deux missions différentes.

## Enrichissement

Lorsqu’une source est fusionnée, elle peut compléter une information manquante :

- entreprise, lieu, contrat ou mode de travail ;
- client final ;
- description lorsqu’elle est nettement plus complète ;
- adresse de candidature ;
- salaire ou TJM ;
- date de publication la plus ancienne connue.

Une valeur déjà présente n’est pas écrasée automatiquement. Le score et la candidature existante ne sont pas recalculés lors d’une simple fusion, afin d’éviter les effets de bord et les envois répétés.

## Compteurs de synchronisation

Chaque connecteur distingue :

- `imported` : nouvelle offre canonique ;
- `merged` : nouvelle occurrence ajoutée à une offre existante ;
- `duplicates` : occurrence déjà connue ;
- `failed` : occurrence invalide ou erreur de traitement.

Ces compteurs sont visibles dans **Connecteurs**, dans l’historique et dans le résumé de la page **Offres**.

## Interface

La page **Offres** affiche :

- une seule carte par offre canonique ;
- le nombre de sources ;
- les badges des plateformes ;
- un filtre qui fonctionne sur toutes les occurrences ;
- le détail de chaque source, son URL, la méthode de rapprochement, le score et les raisons.

## Migration des données existantes

La migration crée une occurrence principale pour chaque offre historique. Les anciennes offres, candidatures et relations conservent leurs identifiants ; il n’y a pas de fusion destructive pendant la migration.

Les nouvelles occurrences sont ensuite fusionnées au fil des synchronisations. Une opération séparée sera nécessaire si l’on souhaite regrouper rétroactivement des offres historiques déjà dupliquées.

## Limites connues

- Une entreprise masquée ou un intitulé très générique empêchent volontairement la fusion approximative.
- Deux missions différentes portant exactement le même intitulé dans la même entreprise peuvent rester ambiguës ; contrat, lieu et date réduisent ce risque sans le supprimer entièrement.
- La stratégie est déterministe et locale ; elle n’utilise pas encore d’embeddings ni de modèle externe.
- Les fusions automatiques ne suppriment aucune offre canonique existante.
