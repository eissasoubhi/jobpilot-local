# Santé des connecteurs et détection de rupture

## Objectif

Une synchronisation techniquement réussie ne garantit pas que le parseur fonctionne encore. Une source peut répondre `200`, mais ne plus produire d’offres après une modification de flux, de structure ou de champs.

JobPilot calcule donc un état de santé d’extraction à partir des dernières synchronisations terminées et mesure la complétude des champs de chaque lot reçu.

## États

```text
NO_DATA    aucune référence positive disponible
HEALTHY    résultats cohérents avec l’historique récent
WATCH      résultat vide isolé ou champs recommandés très incomplets
DEGRADED   résultats vides répétés, normalisation faible ou champ obligatoire manquant
BROKEN     rupture prolongée, taux très faible, erreur ou forte perte des champs obligatoires
```

`NO_DATA` n’est pas une alerte : un nouveau connecteur ou une source temporairement vide ne doit pas être déclaré cassé sans référence positive.

## Qualité des champs

Chaque lot est analysé avant son import dans le catalogue canonique.

Champs obligatoires :

```text
externalId
title
description
```

Champs recommandés :

```text
company
sourceUrl
location
contractType
publishedAt
```

Pour chaque champ, JobPilot conserve :

- le nombre de valeurs présentes ;
- le nombre de valeurs absentes ;
- le taux de complétude ;
- sa catégorie obligatoire ou recommandée.

Le diagnostic expose aussi :

- `requiredCompleteness` ;
- `recommendedCompleteness` ;
- `overallCompleteness` ;
- `missingRequiredRecords` ;
- jusqu’à huit avertissements synthétiques.

Le score global donne deux fois plus de poids aux champs obligatoires qu’aux champs recommandés. Aucun score artificiel n’est généré lorsqu’un lot est vide.

## Impact sur la santé

Lorsqu’un lot contient des offres :

- complétude obligatoire inférieure à 100 % : `DEGRADED` ;
- complétude obligatoire inférieure à 80 % : `BROKEN` ;
- complétude recommandée inférieure à 50 % : `WATCH` ;
- taux de normalisation inférieur à 90 % : `DEGRADED` ;
- taux de normalisation inférieur à 50 % : `BROKEN`.

Les erreurs explicites et les séries de synchronisations vides restent prioritaires dans le diagnostic.

## Mesures exposées

La page **Connecteurs** affiche :

- une synthèse des connecteurs en alerte ;
- la version du parseur lorsqu’elle est déclarée ;
- le nombre de synchronisations utilisées comme échantillon ;
- le taux de normalisation de la dernière exécution ;
- la moyenne d’offres reçues lors des exécutions positives ;
- le nombre de synchronisations vides consécutives ;
- la complétude obligatoire, recommandée et globale ;
- le nombre d’offres incomplètes ;
- les champs absents et leur volume ;
- la raison principale du diagnostic.

L’historique d’une synchronisation conserve aussi :

- `parserVersion` ;
- `normalizationRate` ;
- `zeroResults` ;
- `fieldQuality` ;
- jusqu’à cinq erreurs de normalisation non sensibles.

## Calcul du taux de normalisation

Le taux est calculé uniquement lorsqu’au moins un enregistrement a été reçu :

```text
(imported + merged + duplicates) / received × 100
```

Un enregistrement sans identifiant externe ou rejeté par le pipeline canonique compte comme échec.

## Versionnement

Un connecteur dont l’extraction dépend d’un parseur implémente `VersionedJobSourceConnector` et retourne une version stable, par exemple :

```text
syndication-v1
```

La version change lorsque la logique d’extraction ou le contrat de normalisation change de façon significative. Elle ne dépend pas du numéro de version global de l’application.

## Limites

Le diagnostic est volontairement conservateur :

- il ne valide pas encore la cohérence sémantique d’une valeur présente ;
- il ne déclenche pas encore de notification externe ;
- il utilise les six dernières exécutions disponibles ;
- une source réellement vide peut passer temporairement en surveillance après avoir eu des résultats ;
- les champs recommandés sont communs à toutes les sources et ne sont pas encore configurables par connecteur.

La prochaine évolution pourra publier ces alertes via l’observabilité et ajouter des règles de qualité propres à chaque connecteur.
