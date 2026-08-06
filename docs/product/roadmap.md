# Roadmap

Cette roadmap privilégie des livraisons petites, testables et réversibles.

## Phase 0 — Fondation — livrée

- Documenter l’état actuel et la cible.
- Définir les ADR, conventions et Definition of Done.
- Corriger les écarts entre comportement et documentation.
- Renforcer progressivement l’analyse statique et la CI.

## Phase 1 — Framework de connecteurs — livrée

- Contrat commun de connecteur.
- Registre des sources et capacités.
- Historique des synchronisations (`sync_run`).
- Diagnostic et page Connecteurs.
- Migration d’Arbeitnow et Adzuna.
- Filtre par source dans Offres.

## Phase 2 — Gmail intelligent — livrée

- Synchronisation planifiée et visible.
- Classification des alertes, propositions, réponses, refus et entretiens.
- Association aux offres et candidatures.
- Inbox intelligente et erreurs traçables.

## Phase 3 — Offre canonique — livrée

- Séparer offre canonique et occurrences de sources.
- Déduplication multi-sources déterministe et conservatrice.
- Historique de première et dernière apparition.
- Compteurs distincts pour import, fusion et occurrence connue.
- Badges, liens et filtre multi-sources dans l’interface.

## Phase 4 — Collecte multi-mode — en cours

### Fondation HTTP — livrée

- Client HTTP commun soumis à la politique de conformité.
- User-Agent, timeouts, taille maximale et redirections contrôlées.
- Quotas par synchronisation et par jour.
- Délai minimal, retries, backoff et `Retry-After`.
- Circuit breaker et arrêt après refus d’accès.
- Cache conditionnel `ETag` / `Last-Modified`.
- Vérification de `robots.txt` selon la politique.
- Tests entièrement locaux avec `MockHttpClient`.

### Premier canal syndiqué autorisé — livré

- Connecteur `symfony-jobs` fondé sur le lien RSS officiel du job board Symfony.
- Parseur commun RSS 2.0 et Atom.
- Extraction du contrat, du mode de travail, du lieu, de la langue, du salaire et du TJM.
- Identité idempotente par `guid` ou identifiant Atom.
- Quotas, retries, cache et circuit breaker fournis par le transport HTTP contrôlé.
- Fixtures locales ; aucun appel au site réel dans la CI.

### Santé et monitoring des connecteurs — livrés

- Contrat optionnel de versionnement des parseurs.
- Version du parseur conservée dans les diagnostics de synchronisation.
- Taux de normalisation et détection des résultats vides.
- Référence calculée depuis les six dernières exécutions.
- États `NO_DATA`, `HEALTHY`, `WATCH`, `DEGRADED` et `BROKEN`.
- Mesure de la complétude de chaque champ obligatoire et recommandé.
- Règles de qualité par connecteur avec fallback commun.
- Audit de fraîcheur du scheduler avec états `FRESH`, `DUE`, `OVERDUE` et `STALE`.
- Sortie JSON exploitable par un système de supervision externe.
- Notification webhook HTTPS optionnelle, signée et dédupliquée pour les alertes de fraîcheur.
- Visibilité complète dans la page Connecteurs et son historique.
- Catalogue visible des sources planifiées, en revue ou limitées à Gmail et à l’extension.

### Prochaines livraisons

- Premier scraper HTML pilote uniquement après identification d’une source qui autorise explicitement ce mode de collecte.
- Worker navigateur Playwright isolé lorsque nécessaire.
- Ajout progressif des plateformes disposant d’un canal autorisé.

## Phase 5 — Candidature et CRM — en cours

### Préparation de candidature — livrée

- Message de candidature concis et adapté à l’offre.
- CV sélectionné sans inventer ni modifier son contenu approuvé.
- Réponse de rémunération conservée séparément.
- Lettre de motivation distincte et préparée uniquement lorsque l’offre la demande explicitement.
- Exclusion des formulations indiquant que la lettre est facultative, non requise ou inutile.
- Envoi Gmail limité au message concis et au CV ; aucune concaténation de la lettre.

### Fondation CRM — livrée

- Annuaire dérivé des candidatures, positionnements et messages Gmail déjà associés.
- Regroupement déterministe des sociétés, agences et clients finaux par nom normalisé.
- Contacts recruteurs, adresses de candidature et correspondants Gmail conservés avec leur origine.
- Compteurs d’offres, candidatures, positionnements, messages et statuts par organisation.
- Aucune identité inventée et aucun contenu privé de message exposé.
- Endpoint `GET /api/crm/organizations` documenté et testé.
- Interface CRM recherchable avec filtre par rôle, contacts validés, statuts et offres récentes.
- Liens directs vers les adresses e-mail, téléphones et annonces originales disponibles.
- Notes et corrections de nom persistées séparément des données sources.
- Clé et nom généré d’origine toujours conservés dans la réponse CRM.
- Endpoint d’annotation validé, effaçable et limité aux organisations réellement présentes.
- Éditeur CRM pour enregistrer ou effacer le nom affiché et la note interne.
- Recherche étendue aux noms corrigés, noms sources et notes CRM.
- Corrections de nom, e-mail et téléphone des contacts persistées dans une table séparée.
- Valeurs sources et clé stable du contact conservées dans la réponse CRM.
- Overlay partiel : chaque champ non corrigé continue d’utiliser sa valeur source.
- Endpoint de correction limité aux contacts réellement dérivés dans l’organisation demandée.
- Validation stricte des noms, adresses e-mail et téléphones ; suppression des corrections entièrement vides.
- Aucun changement de déduplication ou de données Gmail/positionnement après une correction manuelle.
- Interface dédiée d’édition et de suppression des corrections de contacts.
- Recherche accent-insensible et filtres corrigés/non corrigés sur les contacts.
- Export CSV local des contacts actuellement filtrés, avec valeurs affichées et valeurs sources séparées.
- Protection contre l’exécution de formules lors de l’ouverture du CSV dans un tableur.
- Tâches et rappels de relance locaux, sans envoi ni notification automatique.
- Vue chronologique en lecture seule des événements de candidature disponibles.

### Prochaines livraisons

- Règles de fusion manuelle des organisations, après clarification du comportement produit.
- Historique métier persistant des transitions de candidature, uniquement après définition des événements à conserver.

## Phase 6 — Reporting — en cours

### Livré

- Conversion par source avec attribution multi-sources explicite.
- Offres, candidatures, envois, réponses, entretiens et refus par source.
- Taux de candidature, de réponse et d’entretien.
- TJM proposé moyen et salaire annuel brut proposé moyen par source, calculés uniquement depuis les champs structurés existants.

### Prochaines livraisons

- Temps moyen de réponse à partir d’événements horodatés fiables.
- Taux d’acceptation lorsque le statut métier correspondant sera défini et historisé.
- Qualité du matching et corrections manuelles.

## Phase 7 — Production

- Images Docker immuables.
- PostgreSQL sauvegardé ou managé.
- RabbitMQ et workers séparés.
- Stockage objet pour les documents.
- Observabilité, alertes, restauration et procédure d’incident.

## Hors périmètre sans décision explicite

- Contournement de CAPTCHA ou de contrôle d’accès.
- Automatisation d’un login privé non autorisé.
- Rotation de proxys pour contourner des limitations.
- Microservices ou Kubernetes sans besoin opérationnel démontré.
