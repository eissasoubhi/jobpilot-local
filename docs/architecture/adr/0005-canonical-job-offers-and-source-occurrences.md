# ADR 0005 — Offres canoniques et occurrences de sources

- Statut : accepté
- Date : 2026-08-05

## Contexte

Le registre de connecteurs garantit l’idempotence d’une source grâce au couple `sourceCode + externalId`. Il ne résout pas le cas où la même mission est publiée sur plusieurs plateformes ou reçue à la fois par une API et une alerte Gmail.

Stocker chaque apparition dans `job_offer` produit plusieurs cartes, plusieurs scores et potentiellement plusieurs candidatures pour le même besoin.

## Décision

Nous séparons :

- `JobOffer`, l’offre canonique utilisée par les domaines Matching et Candidacy ;
- `JobSourceOccurrence`, l’apparition idempotente d’une offre sur une source.

Chaque occurrence conserve son identité de source, son URL, ses dates d’observation, le payload utile et la preuve du rapprochement.

Le rapprochement suit une stratégie déterministe et conservatrice :

1. occurrence déjà connue par `sourceCode + externalId` ;
2. URL canonique exacte ;
3. intitulé et entreprise normalisés identiques ;
4. score de similarité combinant intitulé, entreprise, contrat, localisation et date ;
5. création d’une nouvelle offre si aucun seuil sûr n’est atteint.

Une fusion ajoute une occurrence sans supprimer ni réidentifier l’offre canonique existante.

## Conséquences positives

- une seule carte et une seule candidature par offre métier ;
- conservation de toutes les plateformes et de leurs URL ;
- distinction claire entre nouvelle offre, nouvelle source et occurrence répétée ;
- filtres et statistiques multi-sources ;
- historique de la première et de la dernière observation ;
- stratégie testable sans service d’IA externe.

## Conséquences négatives

- modèle et migration plus complexes ;
- risque résiduel de faux positif ou de doublon non fusionné ;
- lecture d’un maximum de candidats récents pendant la similarité ;
- les doublons historiques ne sont pas fusionnés automatiquement lors de la migration ;
- un outil futur de correction manuelle pourra être nécessaire.

## Alternatives rejetées

### Conserver uniquement `sourceCode + externalId`

Insuffisant dès que plusieurs connecteurs représentent la même mission.

### Fusionner uniquement par URL

Les agrégateurs et les alertes utilisent souvent des URL différentes pour le même poste.

### Fusionner automatiquement par embeddings

Cette option ajoute une dépendance externe, un coût, une variabilité et des enjeux de données disproportionnés au stade actuel. Une stratégie sémantique pourra être ajoutée comme signal complémentaire, jamais comme preuve unique.

### Supprimer immédiatement l’un des doublons

Trop risqué pour les candidatures et les relations existantes. La fusion est additive : une offre canonique reste stable et reçoit des occurrences.

## Règles

- une occurrence est unique par `sourceCode + externalId` ;
- une fusion automatique doit expliquer son type, son score et ses raisons ;
- les seuils privilégient l’absence de fausse fusion ;
- une nouvelle occurrence ne déclenche pas une seconde candidature ;
- l’enrichissement ne remplace pas une valeur canonique déjà renseignée ;
- la migration initiale ne détruit ni ne fusionne les offres existantes ;
- les tests couvrent l’idempotence, la fusion multi-sources et l’affichage d’une carte unique.
