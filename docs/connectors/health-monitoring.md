# Santé des connecteurs et détection de rupture

## Objectif

Une synchronisation techniquement réussie ne garantit pas que le parseur fonctionne encore. Une source peut répondre `200`, mais ne plus produire d’offres après une modification de flux, de structure ou de champs.

JobPilot calcule donc un état de santé d’extraction à partir des dernières synchronisations terminées.

## États

```text
NO_DATA    aucune référence positive disponible
HEALTHY    résultats cohérents avec l’historique récent
WATCH      une synchronisation vide après une référence positive
DEGRADED   deux synchronisations vides ou taux de normalisation inférieur à 90 %
BROKEN     trois synchronisations vides, taux inférieur à 50 % ou erreur de collecte
```

`NO_DATA` n’est pas une alerte : un nouveau connecteur ou une source temporairement vide ne doit pas être déclaré cassé sans référence positive.

## Mesures exposées

La page **Connecteurs** affiche :

- la version du parseur lorsqu’elle est déclarée ;
- le nombre de synchronisations utilisées comme échantillon ;
- le taux de normalisation de la dernière exécution ;
- la moyenne d’offres reçues lors des exécutions positives ;
- le nombre de synchronisations vides consécutives ;
- la raison principale du diagnostic.

L’historique d’une synchronisation conserve aussi :

- `parserVersion` ;
- `normalizationRate` ;
- `zeroResults` ;
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

- il ne compare pas encore les distributions champ par champ ;
- il ne déclenche pas encore de notification externe ;
- il utilise les six dernières exécutions disponibles ;
- une source réellement vide peut passer temporairement en surveillance après avoir eu des résultats.

La prochaine évolution pourra publier ces alertes via l’observabilité et ajouter des métriques par champ obligatoire.
