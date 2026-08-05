# ADR 0010 — Déduire la santé des parseurs depuis l’historique de synchronisation

- Statut : accepté
- Date : 2026-08-05

## Contexte

Les garde-fous HTTP détectent les erreurs réseau, les refus d’accès et les quotas, mais pas une rupture silencieuse du parseur. Une source peut continuer à répondre correctement tout en renvoyant zéro offre ou des données devenues impossibles à normaliser.

L’issue de scraping demande le versionnement des parseurs, un taux d’extraction et une alerte lorsqu’une source renvoie soudainement zéro offre.

## Décision

JobPilot ajoute :

- le contrat optionnel `VersionedJobSourceConnector` ;
- une version stable du parseur pour les connecteurs concernés ;
- des diagnostics par exécution dans `connector_sync_run.details` ;
- un `ConnectorHealthAnalyzer` déterministe ;
- un état de santé calculé à la lecture depuis les six dernières exécutions.

Le diagnostic n’est pas persisté dans une nouvelle colonne. Il est dérivé de l’historique existant afin d’éviter deux sources de vérité et une migration prématurée.

## Règles principales

- aucune exécution positive : `NO_DATA` ;
- une exécution vide après une référence positive : `WATCH` ;
- deux exécutions vides : `DEGRADED` ;
- trois exécutions vides : `BROKEN` ;
- taux de normalisation inférieur à 90 % : `DEGRADED` ;
- taux inférieur à 50 %, exécution `FAILED` ou erreur explicite : `BROKEN`.

## Conséquences

### Positives

- aucun changement de schéma ;
- diagnostic reproductible et testable ;
- historique conservé avec la version du parseur ;
- visibilité immédiate dans la page Connecteurs ;
- les sources réellement vides ne sont pas déclarées cassées avant l’existence d’une référence positive.

### Négatives

- lecture de quelques exécutions supplémentaires par connecteur ;
- seuils heuristiques qui devront être ajustés avec l’usage ;
- absence initiale de notification externe et de métriques par champ.

## Alternatives écartées

### Persister un statut de santé dans `source_connector`

Écarté pour cette étape : le statut serait une donnée dérivée pouvant devenir incohérente avec l’historique.

### Déclarer toute synchronisation vide comme cassée

Écarté : une source peut légitimement ne proposer aucune offre.

### Dépendre d’un smoke test sur le site réel dans la CI

Écarté : la CI doit rester déterministe et indépendante des sources externes.
