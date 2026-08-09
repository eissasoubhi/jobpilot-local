# Nettoyage sélectif des offres hors profil

JobPilot propose deux opérations distinctes dans **Paramètres** :

- **Nettoyer les offres hors profil** : supprime uniquement les offres actuelles dont le non-match est déjà connu avec un niveau de confiance suffisant.
- **Réinitialiser les offres** : supprime tout le catalogue et les candidatures liées, puis resynchronise les sources actives.

## Règle de suppression sélective

Une offre ne peut être supprimée par le nettoyage ciblé que si elle ne porte pas un historique de candidature traité et qu'au moins une condition sûre est déjà enregistrée :

1. l'utilisateur l'a déjà marquée `IGNORED_NOT_MATCH` ;
2. une analyse IA persistée indique `NO_MATCH` avec une confiance >= 85 % et une preuve concrète de mismatch.

La preuve concrète suit la même politique que le filtre de synchronisation : score sous le seuil configuré, prérequis obligatoire manquant ou conflit explicite.

Le nettoyage ciblé **ne lance plus de nouvelle analyse Gemini**. Une offre sans décision persistée suffisamment sûre est conservée.

## Pourquoi aucun appel IA pendant le nettoyage

Le catalogue peut contenir plusieurs centaines d'offres. Lancer une analyse provider pour chaque offre dans une seule requête HTTP rendait l'opération longue, consommait inutilement RPM/TPM/RPD et pouvait monopoliser le serveur API local jusqu'à provoquer des timeouts/HTTP 500 sur le nettoyage et sur des appels concurrents comme `/api/settings/ai`.

Les nouvelles offres sont déjà filtrées pendant la synchronisation avant persistance. Le nettoyage du catalogue existant est donc volontairement une opération locale fondée sur les décisions déjà stockées.

## Cas conservés

Le nettoyage conserve :

- `MATCH` et `REVIEW` ;
- les analyses à faible confiance ;
- les offres sans décision IA persistée suffisamment sûre ;
- les offres qui ont une candidature dans un état traité, par exemple envoyée, en attente de soumission, entretien, réponse reçue, refus ou confirmation.

Seuls les statuts de candidature non traités `DRAFT`, `MISSING_CV`, `READY_TO_SUBMIT` et `IGNORED_NOT_MATCH` peuvent être supprimés avec leur offre.

## Coût IA

Le nettoyage ciblé effectue **zéro nouvel appel provider** et ne réserve aucun quota RPM/TPM/RPD. Il réutilise uniquement les raisons de score IA déjà persistées.

Le filtre IA reste actif au moment de la synchronisation des nouvelles offres, avec cache, quota local et comportement fail-open existants.

## Concurrence

Le nettoyage utilise le même verrou `job-search-sync.lock` que la synchronisation et le reset complet. Si une synchronisation est en cours, aucune suppression n'est effectuée et l'API renvoie HTTP 409.

## API

`POST /api/job-search/cleanup-profile-mismatches`

Corps attendu :

```json
{
  "confirmation": "CLEAN_PROFILE_MISMATCHES"
}
```

L'opération ne déclenche pas de nouvelle synchronisation. Les synchronisations futures continuent à appliquer le filtre IA avant persistance.
