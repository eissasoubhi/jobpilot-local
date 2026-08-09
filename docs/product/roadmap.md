# Roadmap

Cette roadmap privilégie des livraisons petites, testables et réversibles.

## Roadmaps produit versionnées

La roadmap opérationnelle ci-dessous décrit l’état réel du projet et les prochaines livraisons concrètes. Les visions produit à moyen et long terme sont conservées séparément afin de pouvoir les revisiter sans polluer le backlog actif :

- [`roadmap-v1.md`](./roadmap-v1.md) — **V1 Personal ATS** : version active, centrée sur le Unified Offers Workspace, la Review Queue, la timeline fiable, le CRM contextualisé, les analytics et le hardening production ;
- [`roadmap-v2.md`](./roadmap-v2.md) — **V2 AI-first ATS** : assistant IA contextuel, explication, comparaison, application copilot, interview coach, salary advisor et follow-up assistant ;
- [`roadmap-v3.md`](./roadmap-v3.md) — **V3 Career OS** : vision long terme incluant networking, learning, objectifs de carrière, salary history et analytics de carrière.

### Règle d’utilisation

- `roadmap.md` = vérité opérationnelle actuelle ;
- `roadmap-v1.md` = cible produit active ;
- `roadmap-v2.md` et `roadmap-v3.md` = idées et plans d’action conservés pour réévaluation ultérieure ;
- une idée V2/V3 ne remonte dans la roadmap active qu’après identification d’un problème utilisateur réel et d’un premier PR petit, sûr et testable.

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

### Priorité active — scraping public exhaustif

La collecte des offres publiques devient une priorité V1. La matrice complète des plateformes doit être auditée source par source, y compris les plateformes pour lesquelles aucun connecteur n’existe aujourd’hui.

Pour chaque plateforme listée dans `docs/connectors/acquisition-matrix.md`, JobPilot doit déterminer explicitement :

1. si la liste des offres est accessible publiquement sans compte, cookie privé ni session authentifiée ;
2. si un canal API/RSS officiel préférable existe ;
3. si le scraping HTTP statique est autorisé et techniquement suffisant ;
4. si un rendu navigateur Playwright est nécessaire et autorisé ;
5. si `robots.txt` et les conditions de réutilisation permettent cette collecte planifiée ;
6. sinon, pourquoi la source reste limitée à Gmail, extension ou import assisté.

Une page visible sans connexion est un **signal technique**, pas à elle seule une autorisation de collecte. Aucune source ne doit rester indéfiniment `UNDER_REVIEW` : elle doit évoluer vers un mode concret (`API`, `RSS`, `SCRAPING_HTTP`, `SCRAPING_BROWSER`, `GMAIL/EXTENSION`) ou vers un état bloqué documenté.

### Prochaines livraisons

- auditer les 31 plateformes de la matrice pour identifier les listes d’offres publiques sans authentification et documenter une décision de collecte pour chacune ;
- implémenter ensuite, **une source par PR**, les scrapers HTTP des plateformes publiques dont ce mode est autorisé ;
- introduire un worker Playwright isolé pour les pages publiques rendues en JavaScript lorsque le scraping navigateur est nécessaire et autorisé ;
- conserver API/RSS comme canal prioritaire lorsqu’un flux officiel équivalent ou meilleur existe ;
- maintenir Gmail/extension/import assisté pour les sources dont la collecte automatisée n’est pas autorisée ;
- appliquer à tous les scrapers les mêmes limites : quotas, délais, backoff, cache conditionnel, circuit breaker, parser versionné, fixtures locales, métriques de santé et zéro appel externe dans la CI.

## Phase 5 — Candidature et CRM — en cours

### Préparation de candidature — livrée

- Message de candidature concis et adapté à l’offre.
- CV sélectionné sans inventer ni modifier son contenu approuvé.
- Réponse de rémunération conservée séparément.
- Lettre de motivation distincte et **toujours préparée**, même lorsque l’offre ne l’exige pas explicitement.
- Génération de lettre déterministe fondée uniquement sur les faits connus du profil et de l’offre, avec comportement FR/EN et sans compétence inventée.
- `coverLetterRequired` conservé uniquement comme métadonnée indiquant qu’une plateforme ou une offre exige explicitement une lettre.
- Envoi Gmail limité au message concis et au CV ; aucune concaténation silencieuse de la lettre.

### Review Queue — livrée

- Vue dédiée `/offres/review` limitée aux candidatures `READY_TO_SUBMIT`.
- Ordre relatif identique à la page Offres, sans tri concurrent propre à la queue.
- Carte de décision pleine page avec mission, contrat, contexte, score et raisons du matching.
- Comparaison environnement/profil avec stack principale, technologies communes et technologies manquantes ; réutilisation des métadonnées IA existantes sans nouvel appel Gemini pour l’affichage.
- Deux décisions principales persistantes dans la barre basse : `Ne correspond pas` → `IGNORED_NOT_MATCH` et `Envoyée` → `SUBMITTED`.
- Auto-avance vers l’offre suivante après une décision persistée.
- `Précédente` / `Suivante` disponibles comme navigation secondaire et raccourcis `ArrowLeft` / `ArrowRight` hors contrôles interactifs.
- Focus clavier transféré au titre de la nouvelle offre après navigation/décision et progression annoncée aux technologies d’assistance.
- Aucun bouton `Envoyée` ne soumet sur une plateforme externe : il enregistre uniquement le suivi JobPilot après l’envoi réel par l’utilisateur.

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
- Mesure du temps de review uniquement lorsqu’un événement métier fiable de début/fin de revue sera défini.

## Phase 6 — Reporting — en cours

### Livré

- Conversion par source avec attribution multi-sources explicite.
- Conversion par type de contrat sans normalisation métier risquée.
- Conversion par mode de travail à partir des libellés structurés existants.
- Offres, candidatures, envois, réponses, entretiens et refus par groupe.
- Taux de candidature, de réponse et d’entretien.
- TJM proposé moyen et salaire annuel brut proposé moyen, calculés uniquement depuis les champs structurés existants.
- Score de matching moyen et proportion d’offres avec un score supérieur ou égal à 60.

### Prochaines livraisons

- Temps moyen de réponse à partir d’événements horodatés fiables.
- Taux d’acceptation lorsque le statut métier correspondant sera défini et historisé.
- Corrections manuelles du matching après clarification du comportement produit et de la traçabilité attendue.

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
