# JobPilot Local

Application locale en français pour centraliser, rechercher, scorer, préparer et suivre les candidatures CDI, CDD et freelance.

La documentation produit, architecture, développement et exploitation se trouve dans [`docs/`](docs/README.md).

## Fonctionnalités incluses

- Profil candidat modifiable depuis l'interface.
- Téléversement et sélection automatique des CV français/anglais.
- Recherche automatique d'offres toutes les six heures.
- Import immédiat depuis Arbeitnow, sans compte ni clé API.
- Connecteur Adzuna France facultatif pour élargir les résultats.
- Import manuel d'offres et import depuis l'extension Chrome.
- Détection de langue et choix du CV correspondant.
- Score de compatibilité, préparation automatique à partir de 50/100.
- Tri par fraîcheur puis par score.
- Calcul du TJM selon les règles configurées, avec plafond à 520 €.
- Suivi des candidatures et modification des messages préparés.
- Suivi des positionnements par client, agence et commercial.
- Détection de double positionnement par référence ou similarité.
- Connexion Gmail OAuth en lecture et envoi.
- Synchronisation manuelle des messages Gmail depuis les paramètres.
- Envoi automatique Gmail uniquement lorsque la candidature, le CV, le destinataire et les autorisations le permettent.
- Test fonctionnel de l'e-mail automatique avec aperçu exact et envoi réel.
- Registre des plateformes de recherche.
- Extension Chrome Manifest V3 pour importer une offre et préremplir les formulaires.

## Démarrage sur macOS

Prérequis : Docker Desktop et Google Chrome.

### Démarrage rapide

Double-cliquer sur `start.command`. Au premier lancement, le script crée le fichier `.env`, génère la clé de chiffrement et ouvre l’interface. Pour arrêter l’application, double-cliquer sur `stop.command`.

Ou depuis le Terminal :

```bash
cp .env.example .env
# Recommandé pour chiffrer les jetons Gmail
openssl rand -base64 32
# Copier le résultat dans APP_ENCRYPTION_KEY du fichier .env

docker compose up --build
```

Ouvrir ensuite :

- Interface : http://localhost:3000
- API : http://localhost:8080/api/health

Les ports sont liés à `127.0.0.1`, donc l'application n'est pas exposée au réseau local.

## Recherche automatique d'offres

Le service `scheduler` lance une recherche au démarrage, puis toutes les six heures. La page **Offres** lance également une synchronisation non forcée à son ouverture et propose le bouton **Rechercher maintenant**.

### Arbeitnow

Arbeitnow est actif par défaut et ne nécessite aucune clé. JobPilot consulte les offres récentes, conserve les postes distants ou situés en France, filtre les technologies selon les métiers et compétences configurés, puis applique le scoring habituel.

Pour le désactiver :

```dotenv
ARBEITNOW_ENABLED=0
```

Le nombre de pages consultées est configurable :

```dotenv
ARBEITNOW_PAGES=3
```

### Adzuna France

Adzuna est facultatif. Créer un compte développeur sur `developer.adzuna.com`, récupérer `app_id` et `app_key`, puis compléter le fichier `.env` :

```dotenv
ADZUNA_APP_ID=your-app-id
ADZUNA_APP_KEY=your-app-key
ADZUNA_COUNTRY=fr
ADZUNA_WHERE=
ADZUNA_RESULTS_PER_QUERY=20
```

Laisser `ADZUNA_WHERE` vide recherche dans toute la France. Une ville ou une région peut être renseignée pour limiter les résultats.

Pour modifier l'intervalle, utiliser un nombre de secondes, avec un minimum de 900 secondes :

```dotenv
JOB_SYNC_INTERVAL_SECONDS=21600
```

Après modification du fichier `.env`, recréer uniquement les conteneurs concernés :

```bash
docker compose up -d --force-recreate api scheduler web
```

La synchronisation peut aussi être lancée depuis le Terminal :

```bash
docker compose exec api php bin/console app:jobs:sync --force
```

Les offres sont actuellement dédupliquées par source et identifiant externe. La déduplication canonique multi-sources est prévue dans la roadmap. Les offres importées passent par les règles de langue, exclusion, score, CV, TJM, salaire et préparation automatique.

## Extension Chrome

1. Ouvrir `chrome://extensions`.
2. Activer **Mode développeur**.
3. Cliquer **Charger l'extension non empaquetée**.
4. Sélectionner le dossier `extension`.
5. Ouvrir une offre, puis cliquer sur l'icône JobPilot.

L'extension importe le titre, l'URL et le texte visible vers l'API locale. Elle peut aussi préremplir les champs standards, mais ne clique jamais automatiquement sur le bouton final de soumission.

## Gmail OAuth

1. Créer un projet Google Cloud.
2. Activer Gmail API.
3. Créer un client OAuth de type application Web.
4. Ajouter exactement l'URI de redirection : `http://localhost:8080/api/integrations/gmail/callback`.
5. Renseigner `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` dans `.env`.
6. Ajouter le compte Gmail dans les utilisateurs de test lorsque l'application OAuth est en mode Testing.
7. Dans **Paramètres**, cliquer sur **Connecter Gmail**.

JobPilot demande les scopes suivants :

```text
https://www.googleapis.com/auth/gmail.readonly
https://www.googleapis.com/auth/gmail.send
```

Aucun mot de passe Gmail n'est stocké. Les jetons OAuth doivent être chiffrés avec `APP_ENCRYPTION_KEY`.

La synchronisation des messages existe via l'interface et importe les métadonnées correspondant à `GMAIL_SEARCH_QUERY`. Dans l'architecture actuelle, le scheduler automatise la synchronisation des offres et les candidatures autorisées, mais pas encore la synchronisation Gmail. Cette automatisation et la classification métier complète sont prévues dans la roadmap.

## Commandes

```bash
make start      # démarrer
make stop       # arrêter
make logs       # logs
make test       # tests backend
make reset      # supprimer la base locale et recommencer
```

## Limites assumées

JobPilot ne contourne pas les CAPTCHA, authentifications, contrôles d'accès ou limitations de plateformes. Les futurs connecteurs pourront utiliser une API, un flux public, le scraping contrôlé de pages librement accessibles, Gmail ou l'extension selon les possibilités de chaque source.

Les actions automatiques sensibles doivent être explicitement autorisées, idempotentes, traçables et limitées. Une lettre de motivation reste séparée du corps de l'e-mail et ne doit être envoyée que lorsqu'elle est demandée.

## Architecture et qualité

Les décisions structurantes sont documentées dans les ADR. La cible est un modular monolith Symfony avec DDD pragmatique, architecture hexagonale, CQS et traitements event-driven lorsque le besoin le justifie.

Consulter :

- [`docs/architecture/overview.md`](docs/architecture/overview.md)
- [`docs/architecture/context-map.md`](docs/architecture/context-map.md)
- [`docs/development/testing.md`](docs/development/testing.md)
- [`docs/definition-of-done.md`](docs/definition-of-done.md)
