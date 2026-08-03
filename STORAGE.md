# Stockage local des documents et accès PostgreSQL

## Documents privés

En développement local, tous les documents privés sont enregistrés physiquement dans le projet :

```text
data/private/
```

Les CV téléversés depuis l’interface sont dans :

```text
data/private/cvs/
```

Le jeton Gmail chiffré et les autres fichiers privés générés par JobPilot sont également stockés sous `data/private/`.

Le dossier est monté dans l’API et le scheduler à l’emplacement `/app/var/private`. Les nouveaux uploads apparaissent donc immédiatement sur le Mac dans le dossier du projet.

Tous les fichiers privés sont ignorés par Git. Seul `data/private/README.md` est versionné pour conserver le dossier dans le dépôt.

## Migration des documents déjà présents

Au premier démarrage après cette modification, `start.command` et `make start` copient automatiquement les fichiers de l’ancien volume Docker `jobpilot_private` vers `data/private/`.

Pour relancer manuellement la copie :

```bash
make migrate-private-storage
```

Cette commande copie les fichiers sans supprimer l’ancien volume.

Pour ouvrir le dossier dans Finder :

```bash
make open-private
```

Ou directement :

```bash
open data/private
```

## Connexion à PostgreSQL avec un client

PostgreSQL est exposé uniquement sur l’interface locale du Mac.

Valeurs par défaut :

```text
Host: 127.0.0.1
Port: 5432
Database: jobpilot
User: jobpilot
Password: jobpilot
SSL: disabled
```

Ces valeurs peuvent être modifiées dans `.env` avec :

```dotenv
POSTGRES_DB=jobpilot
POSTGRES_USER=jobpilot
POSTGRES_PASSWORD=jobpilot
POSTGRES_PORT=5432
```

Si le port 5432 est déjà occupé, utiliser par exemple :

```dotenv
POSTGRES_PORT=5433
```

Puis recréer le conteneur PostgreSQL :

```bash
docker compose up -d --force-recreate db
```

Clients compatibles : DBeaver, TablePlus, DataGrip, pgAdmin ou Postico.

Pour ouvrir directement `psql` dans le conteneur :

```bash
make db-shell
```

## Sauvegarde

Le dossier `data/private/` peut être sauvegardé comme n’importe quel dossier du Mac. La base PostgreSQL reste dans le volume Docker `jobpilot_db` et doit être sauvegardée avec `pg_dump`.

Ne commitez jamais le contenu de `data/private/`, car il peut contenir des CV et des jetons d’accès chiffrés.
