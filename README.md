# JobPilot Local

Application locale en français pour centraliser, rechercher, scorer, préparer et suivre les candidatures CDI, CDD et freelance.

La documentation produit, architecture, développement et exploitation se trouve dans [`docs/`](docs/README.md).

## Fonctionnalités incluses

- Profil candidat modifiable depuis l'interface.
- Téléversement et sélection automatique des CV français/anglais.
- Recherche automatique d'offres toutes les six heures.
- Import immédiat depuis Arbeitnow, sans compte ni clé API.
- Connecteur Adzuna France facultatif pour élargir les résultats.
- Registre persistant des connecteurs, activation indépendante et historique des synchronisations.
- Page **Connecteurs** avec diagnostic, test manuel, compteurs et erreurs par source.
- Catalogue canonique : une seule offre métier, même lorsqu’elle apparaît sur plusieurs plateformes.
- Occurrences de sources conservant URL, identifiant, première et dernière observation et preuve de rapprochement.
- Filtre multi-sources dans la page **Offres** et détail des liens de chaque plateforme.
- Import manuel d'offres et import assisté depuis l'extension Chrome.
- Extraction structurée `JobPosting` et prise en charge dédiée des pages Free-Work ouvertes par l’utilisateur.
- Imports de l’extension idempotents dans le catalogue canonique.
- Détection de langue et choix du CV correspondant.
- Score de compatibilité, préparation automatique à partir de 50/100.
- Tri par fraîcheur puis par score.
- Calcul du TJM selon les règles configurées, avec plafond à 520 €.
- Suivi des candidatures et modification des messages préparés.
- Suivi des positionnements par client, agence et commercial.
- Détection de double positionnement par référence ou similarité.
- Connexion Gmail OAuth avec droits de lecture et d'envoi séparément diagnostiqués.
- Synchronisation Gmail automatique avec les autres connecteurs et lancement manuel depuis **Messagerie** ou **Connecteurs**.
- Inbox intelligente : alertes emploi, propositions recruteurs, confirmations, réponses, demandes d'informations, entretiens et refus.
- Extraction d'offres depuis les alertes Gmail reconnues et passage dans le pipeline normal de scoring et de préparation.
- Association prudente des réponses Gmail aux candidatures et mise à jour de leur statut.
- Envoi automatique Gmail uniquement lorsque la candidature, le CV, le destinataire et les autorisations le permettent.
- Test fonctionnel de l'e-mail automatique avec aperçu exact et envoi réel.
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

Les connecteurs disponibles sont enregistrés dans PostgreSQL avec leur état, leur dernière erreur et leurs compteurs. La page **Connecteurs** permet de les activer, les désactiver et les tester individuellement. Chaque exécution est conservée dans un historique.

### Arbeitnow

Arbeitnow est actif par défaut et ne nécessite aucune clé. JobPilot consulte les offres récentes, conserve les postes distants ou situés en France, filtre les technologies selon les métiers et compétences configurés, puis applique le scoring habituel.

Pour le désactiver au niveau de l’environnement :

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
docker compose exec api php bin/console app:jobs:sync
docker compose exec api php bin/console app:jobs:sync --force
docker compose exec api php bin/console app:jobs:sync --force --connector=arbeitnow
docker compose exec api php bin/console app:jobs:sync --force --connector=gmail
```

Chaque résultat reçu devient une occurrence de source. JobPilot distingue :

- une **nouvelle offre** canonique ;
- une **nouvelle source fusionnée** avec une offre existante ;
- une **occurrence déjà connue** pour le même `sourceCode + externalId` ;
- un échec de normalisation ou de traitement.

Le rapprochement multi-sources utilise l’URL canonique, puis l’intitulé et l’entreprise normalisés, puis une similarité conservatrice intégrant contrat, lieu et date. Une seule offre est scorée et préparée ; ses différentes plateformes restent visibles avec leurs liens et la preuve du rapprochement.

Les offres nouvelles passent par les règles de langue, exclusion, score, CV, TJM, salaire et préparation automatique.

Documentation :

- [`docs/connectors/overview.md`](docs/connectors/overview.md)
- [`docs/job-catalog/canonical-offers.md`](docs/job-catalog/canonical-offers.md)

## Extension Chrome

1. Ouvrir `chrome://extensions`.
2. Activer **Mode développeur**.
3. Cliquer **Charger l'extension non empaquetée**.
4. Sélectionner le dossier `extension`.
5. Ouvrir une page de détail d’offre, puis cliquer sur l'icône JobPilot.
6. Cliquer sur **Importer l’offre** et vérifier le résultat dans la page **Offres**.

L’extension privilégie le balisage structuré `JobPosting` lorsque le site le fournit. À défaut, elle analyse uniquement le contenu déjà visible dans l’onglet. Elle transmet notamment le titre, l’entreprise, le lieu, le contrat, le télétravail, la date, le salaire ou TJM, la description et l’URL canonique.

Les imports passent par le catalogue canonique. Réimporter la même page met à jour son occurrence au lieu de créer une seconde carte ou une seconde candidature.

Pour Free-Work, la collecte automatique en arrière-plan n’est pas activée. L’intégration utilise les alertes Gmail ou cet import assisté déclenché par l’utilisateur. Détails : [`docs/connectors/free-work.md`](docs/connectors/free-work.md).

L'extension peut aussi préremplir les champs standards, mais ne clique jamais automatiquement sur le bouton final de soumission.

## Gmail OAuth et Inbox intelligente

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

Le connecteur Gmail lit uniquement les messages correspondant à la recherche configurée :

```dotenv
GMAIL_SEARCH_QUERY=(job OR mission OR candidature OR application OR recruiter OR entretien) newer_than:30d
GMAIL_MAX_RESULTS=100
GMAIL_MAX_PAGES=3
```

La synchronisation récupère le texte nécessaire à l'analyse, classe les messages, détecte les actions à traiter, associe prudemment les réponses aux candidatures et extrait les liens d'offres reconnus. Le HTML complet n'est pas conservé en base et aucun message n'est supprimé, déplacé ou marqué comme lu dans Gmail.

Les plateformes actuellement reconnues dans les alertes comprennent LinkedIn, Indeed, APEC, Hellowork, Welcome to the Jungle, Free-Work, LesJeudis, Le Hibou et France Travail. Une alerte au format inconnu reste visible dans l'Inbox sans créer automatiquement une offre douteuse.

Documentation complète : [`docs/connectors/gmail.md`](docs/connectors/gmail.md).

## Commandes

```bash
make start      # démarrer
make stop       # arrêter
make logs       # logs
make test       # tests backend
make reset      # supprimer la base locale et recommencer
```

## Limites assumées

JobPilot ne contourne pas les CAPTCHA, authentifications, contrôles d'accès ou limitations de plateformes. Un scraper ne doit être activé qu’après revue de l’API officielle, des flux disponibles, des conditions d’utilisation, des mentions légales et de `robots.txt`.

Une page accessible publiquement ne constitue pas automatiquement une autorisation de collecte massive. Pour une source limitée à l’usage personnel, JobPilot privilégie les alertes e-mail et l’extension déclenchée par l’utilisateur.

La fusion automatique privilégie la prudence. Une offre ambiguë reste séparée plutôt que d’être attachée à tort à une autre mission. La migration crée une occurrence pour chaque offre existante, mais ne fusionne pas destructivement les anciens doublons.

Les actions automatiques sensibles doivent être explicitement autorisées, idempotentes, traçables et limitées. Une lettre de motivation reste séparée du corps de l'e-mail et ne doit être envoyée que lorsqu'elle est demandée.

## Architecture et qualité

Les décisions structurantes sont documentées dans les ADR. La cible est un modular monolith Symfony avec DDD pragmatique, architecture hexagonale, CQS et traitements event-driven lorsque le besoin le justifie.

Consulter :

- [`docs/architecture/overview.md`](docs/architecture/overview.md)
- [`docs/architecture/context-map.md`](docs/architecture/context-map.md)
- [`docs/connectors/overview.md`](docs/connectors/overview.md)
- [`docs/connectors/gmail.md`](docs/connectors/gmail.md)
- [`docs/connectors/free-work.md`](docs/connectors/free-work.md)
- [`docs/job-catalog/canonical-offers.md`](docs/job-catalog/canonical-offers.md)
- [`docs/development/testing.md`](docs/development/testing.md)
- [`docs/definition-of-done.md`](docs/definition-of-done.md)
