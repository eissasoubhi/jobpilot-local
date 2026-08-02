# JobPilot Local

Application locale en français pour centraliser, scorer, préparer et suivre les candidatures CDI, CDD et freelance.

## Fonctionnalités incluses

- Profil candidat modifiable depuis l'interface.
- Téléversement et sélection automatique des CV français/anglais.
- Import manuel d'offres et import depuis l'extension Chrome.
- Détection de langue et choix du CV correspondant.
- Score de compatibilité, préparation automatique à partir de 50/100.
- Tri par fraîcheur puis par score.
- Calcul du TJM selon les règles configurées, avec plafond à 520 €.
- Suivi des candidatures et modification des messages préparés.
- Suivi des positionnements par client, agence et commercial.
- Détection de double positionnement par référence ou similarité.
- Connexion Gmail OAuth en lecture seule et synchronisation des alertes/réponses.
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
4. Ajouter l'URI de redirection : `http://localhost:8080/api/integrations/gmail/callback`.
5. Renseigner `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` dans `.env`.
6. Dans **Paramètres**, cliquer sur **Connecter Gmail**.

Le scope utilisé est `gmail.readonly`. Aucun mot de passe Gmail n'est stocké.

## Commandes

```bash
make start      # démarrer
make stop       # arrêter
make logs       # logs
make test       # tests backend
make reset      # supprimer la base locale et recommencer
```

## Limites assumées

Cette première version ne contourne ni CAPTCHA, ni protection anti-bot, ni conditions d'utilisation des plateformes. L'envoi final reste en mode confirmation en un clic. Les connecteurs directs plateforme par plateforme pourront être ajoutés ensuite quand une API officielle ou un flux autorisé existe.

## Correctif 1.0.1

- Correction de la dépendance Composer `symfony/test-pack` de `^2.0` vers `^1.2`.

## Compatibility fix (DoctrineBundle 3)

The Doctrine configuration intentionally omits legacy ORM options removed from DoctrineBundle 3:
`auto_generate_proxy_classes`, `enable_lazy_ghost_objects`, and `report_fields_where_declared`.
