# Nettoyage sélectif des offres hors profil

JobPilot propose deux opérations distinctes dans **Paramètres** :

- **Nettoyer les offres hors profil** : supprime uniquement les offres actuelles dont le non-match est suffisamment sûr.
- **Réinitialiser les offres** : supprime tout le catalogue et les candidatures liées, puis resynchronise les sources actives.

## Règle de suppression sélective

Une offre ne peut être supprimée par le nettoyage ciblé que si elle ne porte pas un historique de candidature traité et qu'au moins une condition sûre est satisfaite :

1. l'utilisateur l'a déjà marquée `IGNORED_NOT_MATCH` ;
2. une analyse IA persistée indique `NO_MATCH` avec une confiance >= 85 % et une preuve concrète de mismatch ;
3. le filtre d'entrée IA actuel retourne le même verdict sûr.

La preuve concrète suit la même politique que le filtre de synchronisation : score sous le seuil configuré, prérequis obligatoire manquant ou conflit explicite.

## Cas conservés

Le nettoyage conserve :

- `MATCH` et `REVIEW` ;
- les analyses à faible confiance ;
- les cas où l'IA est désactivée, indisponible ou à court de quota, sauf rejet manuel déjà enregistré ;
- les offres qui ont une candidature dans un état traité, par exemple envoyée, en attente de soumission, entretien, réponse reçue, refus ou confirmation.

Seuls les statuts de candidature non traités `DRAFT`, `MISSING_CV`, `READY_TO_SUBMIT` et `IGNORED_NOT_MATCH` peuvent être supprimés avec leur offre.

## Coût IA

Le nettoyage réutilise d'abord les raisons de score IA déjà persistées. Pour les autres offres, il appelle le filtre IA existant, qui bénéficie du cache de matching et des garde-fous RPM/TPM/RPD. Si le quota ou le fournisseur n'est pas disponible, l'offre est conservée.

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
