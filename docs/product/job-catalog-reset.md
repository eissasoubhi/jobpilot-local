# Réinitialisation du catalogue d’offres

## Objectif

Permettre depuis `/parametres` de repartir d’un catalogue vide puis de relancer immédiatement la collecte depuis les connecteurs actifs. Ce flux est utile après une modification importante des critères de matching ou de la logique IA.

## Données supprimées

La réinitialisation supprime :

- toutes les `JobOffer` ;
- toutes les `JobSourceOccurrence` liées ;
- toutes les `Application` liées, quel que soit leur statut (`READY_TO_SUBMIT`, `SUBMITTED`, `INTERVIEW`, `REJECTED`, etc.).

Le bouton avertit explicitement que l’historique de candidatures lié aux offres est donc perdu.

## Données conservées

La réinitialisation ne touche pas :

- au profil candidat ;
- aux CV ;
- aux paramètres de recherche/matching/rémunération ;
- aux clés API et secrets d’intégration ;
- à l’état activé/désactivé des connecteurs ;
- à l’historique des runs de synchronisation ;
- au cache IA et aux compteurs de quota IA.

Conserver le cache IA évite de refaire un appel fournisseur pour une offre identique lorsque le profil, le modèle, le prompt et le schéma n’ont pas changé. Les changements de critères candidat invalident naturellement les entrées concernées via le fingerprint existant.

## Sécurité UX

Le panneau vit dans la Zone dangereuse de la page Paramètres. Deux confirmations sont requises :

1. saisir exactement `REINITIALISER` ;
2. confirmer l’avertissement navigateur rappelant que les candidatures liées seront supprimées.

L’API demande en plus le token interne `RESET_OFFERS`. Une requête sans cette confirmation est refusée.

## Synchronisation concurrente

Le reset utilise le même fichier de verrou que la synchronisation des offres. Si un sync est déjà en cours, aucune donnée n’est supprimée et l’API répond avec un conflit. Après la suppression, JobPilot lance un `sync(force=true)` avec le trigger `catalog-reset`.

Le mode forcé ne contourne aucune politique de source : seuls les connecteurs qui sont activés, configurés et autorisés par `canSynchronize()` sont exécutés. Les règles d’authentification, robots.txt, délais, quotas et limites propres aux connecteurs restent inchangées.

## Matching IA

Les nouvelles offres repassent par le pipeline normal, y compris le filtre pré-persistence IA lorsqu’il est activé. Les `NO_MATCH` à haute confiance et avec preuve concrète peuvent donc être écartés avant stockage, selon la règle déjà documentée dans `ai-matching.md`.

Si Gemini est indisponible ou son quota atteint, le comportement fail-open existant est conservé afin de ne pas perdre une opportunité uniquement à cause d’un problème fournisseur.
