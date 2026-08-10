# ADR 0001 — Stratégie d’acquisition des offres : flux, HTTP, IA grounded et Browser

- **Statut** : Accepté
- **Date** : 2026-08-10
- **Décision liée** : issue #14

## Contexte

JobPilot agrège des offres provenant de sources hétérogènes : API officielles, RSS, alertes Gmail, pages HTML publiques et applications JavaScript.

Un scraper générique unique serait difficile à contrôler et créerait plusieurs risques :

- envoyer inutilement un navigateur headless sur toutes les sources ;
- consommer de l’IA pour des pages que le DOM ou Schema.org décrit déjà correctement ;
- masquer les limites réseau et les règles propres à chaque domaine ;
- confondre faisabilité technique et autorisation de collecte ;
- importer des liens ou descriptions insuffisamment fiables dans le scoring candidat ;
- multiplier les chemins de déduplication et de persistance.

L’architecture doit donc choisir le moyen **le moins puissant et le plus déterministe suffisant** pour chaque source, tout en gardant un seul pipeline canonique après extraction.

## Décision

JobPilot applique la hiérarchie suivante.

### Niveau 0 — API, RSS ou flux public structuré

À utiliser en priorité lorsqu’un accès officiellement prévu existe.

Exemples déjà présents dans JobPilot :

- API France Travail ;
- API Adzuna ;
- SmartRecruiters ;
- flux Symfony Jobs ;
- autres connecteurs publics dédiés.

**Raison** : structure stable, coût réseau limité, contrat explicite et moins de dépendance au HTML de présentation.

### Niveau 1 — Gmail / import assisté

Pour une plateforme dont la collecte automatisée du site n’est pas retenue, JobPilot peut analyser les alertes que l’utilisateur a déjà reçues dans son propre Gmail connecté.

Ce canal :

- ne crawle pas la plateforme ;
- ne visite pas automatiquement les autres offres du site ;
- conserve la plateforme d’origine dans la provenance (`Free-Work via Gmail`, etc.) ;
- isole titre, description et métadonnées offre par offre avant scoring ;
- alimente le même pipeline canonique que les autres connecteurs.

Les presets classés `ASSISTED_ONLY` utilisent cette voie par défaut.

### Niveau 2 — HTTP déterministe

Pour une source publique dont la collecte a été explicitement autorisée et que `robots.txt` permet opérationnellement :

1. requête HTTPS contrôlée ;
2. détection de Schema.org `JobPosting` ;
3. fallback DOM/lien déterministe ;
4. pagination uniquement via un `nextUrl` explicitement détecté sur le même domaine ;
5. enrichissement borné des fiches détail ;
6. score de fiabilité d’extraction ;
7. seules les offres fiables rejoignent le pipeline canonique.

Le transport impose notamment :

- même domaine autorisé ;
- `robots.txt` ;
- quotas et délais ;
- timeouts ;
- taille maximale des réponses ;
- circuit breaker ;
- zéro fabrication arbitraire d’URL de pagination.

### Niveau 3 — Gemini grounded comme récupération

Gemini n’est **jamais** l’extracteur principal d’une page comprise par le moteur déterministe.

Il peut intervenir uniquement quand :

- le chemin HTTP est autorisé ;
- la page ressemble réellement à une page d’offres ;
- aucun candidat déterministe exploitable n’a été trouvé ;
- la page ne nécessite pas déjà Browser ;
- le budget IA de la synchronisation et les quotas du compte sont disponibles.

Le modèle reçoit :

- au maximum 20 000 caractères de texte visible nettoyé ;
- au maximum 120 liens HTTPS du même domaine présents dans la page.

Une URL produite par le modèle n’est acceptée que si elle correspond **exactement** à un lien autorisé fourni en entrée. Les URLs hallucinéées, transformées, dupliquées ou hors domaine sont rejetées en PHP.

Un lien récupéré par IA doit encore :

1. passer par une fiche détail ;
2. être enrichi par le moteur déterministe ;
3. passer le même score de qualité d’extraction.

L’IA ne décide pas de la compatibilité finale candidat/offre dans cette étape.

### Niveau 4 — Browser Playwright isolé

Browser est le dernier recours pour les pages publiques nécessitant JavaScript.

Règles de sélection :

- source `HTTP` : jamais de bascule Browser ;
- source `AUTO` : Browser uniquement si HTTP/IA n’a produit aucune offre fiable **et** si le diagnostic indique que le rendu JavaScript est nécessaire ;
- source `BROWSER` : rendu possible uniquement si le worker est configuré et les mêmes contrôles d’autorisation/robots sont satisfaits.

Avant chaque page liste ou fiche détail rendue, l’API effectue un préflight HTTP contrôlé et valide `robots.txt`.

Le worker Playwright :

- est isolé dans un service Docker séparé ;
- n’est pas exposé sur un port hôte par défaut ;
- utilise un token interne ;
- s’exécute non-root avec filesystem read-only, `/tmp` temporaire, capabilities Linux supprimées et `no-new-privileges` ;
- limite les navigations principales au domaine HTTPS autorisé ;
- bloque les IP/destinations privées ou réservées ;
- bloque les méthodes autres que GET/HEAD ;
- bloque images, vidéo/audio, polices, service workers et téléchargements ;
- n’injecte aucun cookie, identifiant ou session privée ;
- n’effectue aucun clic ni formulaire ;
- n’utilise ni stealth, ni rotation de proxy, ni CAPTCHA bypass ;
- retourne seulement un HTML borné ensuite revalidé par l’API.

Budgets actuels du chemin Browser :

- 3 pages de liste maximum ;
- 10 fiches détail maximum ;
- pagination sûre même domaine uniquement.

Le HTML rendu repart ensuite dans **les mêmes extracteurs et le même garde-fou qualité** que le chemin HTTP.

## Pipeline canonique après extraction

Quel que soit le canal d’acquisition :

```text
source
  ↓
normalisation offre
  ↓
déduplication intra/multi-source
  ↓
filtre d’admission / matching profil
  ↓
score
  ↓
préparation de candidature
  ↓
décision utilisateur / workflow candidature
```

Un connecteur ne doit pas créer un pipeline parallèle de persistance ou de scoring.

## Séparation conformité / technique

Une page techniquement accessible n’est pas automatiquement activable.

Le catalogue de sources distingue :

- `AUTHORIZATION_REQUIRED` : techniquement compatible, mais autorisation applicable à fournir avant création/activation ;
- `ASSISTED_ONLY` : pas de scraping automatique proposé par JobPilot ; Gmail/import assisté/API partenaire privilégiés.

Les revues publiques de conformité :

- expirent après 90 jours ;
- déclenchent une pré-alerte 14 jours avant échéance ;
- bloquent le préremplissage d’un preset après expiration ;
- ne transforment jamais `robots.txt` en autorisation contractuelle.

## Alternatives rejetées

### Tout faire avec Playwright

Rejeté car plus coûteux, plus lent, plus difficile à isoler et inutile pour les pages déjà structurées.

### Tout faire avec Gemini

Rejeté car non déterministe, coûteux en tokens/quota et sujet aux hallucinations. L’IA reste un filet de récupération grounded.

### Scraper spécifique codé en dur pour chaque plateforme

Rejeté comme stratégie générale. Des connecteurs spécialisés restent possibles lorsqu’une API ou une structure stable le justifie, mais le framework générique doit couvrir les sources autorisées simples sans dupliquer tout le pipeline.

### Fabriquer automatiquement `?page=N`

Rejeté. Une pagination générique ne suit que des liens `next` détectés et validés sur le même domaine.

### Utiliser une session utilisateur pour contourner les restrictions

Rejeté. Pas de login automatisé, cookies privés, CAPTCHA bypass, stealth ou contournement d’accès.

## Observabilité

Chaque source personnalisée conserve une identité stable `custom-scraper-{id}` afin de suivre :

- dernière synchronisation ;
- erreurs ;
- quotas ;
- parser/mode ;
- historique des occurrences ;
- provenance dans le catalogue.

Les diagnostics exposent notamment :

- mode configuré/recommandé/effectif ;
- Browser requis ou non ;
- pages visitées et raison d’arrêt ;
- enrichissements détail ;
- nombre d’offres fiables ;
- éventuelle récupération IA/Browser.

## Rollback

Le rollback doit rester possible sans migration destructive.

### Désactiver une source

Mettre la source personnalisée `enabled=false`. Elle disparaît du registre dynamique sans supprimer les offres déjà historisées.

### Désactiver Gemini

Désactiver la configuration IA ou retirer sa clé. Le chemin déterministe continue de fonctionner ; l’IA échoue en mode fail-open vers zéro résultat de récupération.

### Désactiver Browser

Retirer `BROWSER_WORKER_URL` / le token ou arrêter le service `browser-worker`. Les sources `HTTP` restent inchangées ; les sources nécessitant Browser ne doivent pas tenter un chemin moins sûr pour contourner l’indisponibilité.

### Retirer une plateforme du catalogue de presets

Cela n’altère pas les sources déjà créées. La source existante peut être désactivée séparément ; le preset n’est qu’un mécanisme d’onboarding revu.

## Conséquences

### Positives

- coût réseau/CPU/IA maîtrisé ;
- comportement plus déterministe ;
- meilleure auditabilité ;
- sécurité Browser séparée ;
- qualité d’extraction indépendante du matching candidat ;
- conformité visible dans le produit ;
- possibilité d’ajouter progressivement des sources sans multiplier les pipelines métier.

### Négatives

- certaines plateformes resteront volontairement en import assisté ;
- les sites dont le JavaScript dépend de POST ou d’une session privée peuvent rester non pris en charge ;
- les presets de conformité nécessitent une maintenance régulière ;
- l’architecture comporte plusieurs niveaux, donc davantage de tests de politique sont nécessaires.

## Validation de la décision

La décision reste valide tant que :

- les chemins HTTP/IA/Browser convergent vers le même pipeline canonique ;
- l’IA reste grounded et bornée ;
- Browser reste isolé et read-only ;
- aucune source ne peut contourner les contrôles d’autorisation/robots ;
- les budgets sont explicites et testés ;
- les règles de conformité sont revues périodiquement.

L’incident CI #128 doit être résolu séparément avant de considérer la Definition of Done #14 entièrement satisfaite, car la validation automatique des tests reste actuellement indisponible au niveau du runner GitHub Actions.
