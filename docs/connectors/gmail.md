# Connecteur Gmail

## Objectif

Le connecteur Gmail transforme les alertes d’emploi et les échanges de recrutement en données exploitables par JobPilot.

Il couvre deux usages distincts :

1. envoyer les candidatures explicitement autorisées ;
2. lire les messages correspondant à la recherche configurée afin de détecter des offres, des réponses et des actions à traiter.

## Autorisations OAuth

JobPilot demande uniquement :

```text
https://www.googleapis.com/auth/gmail.readonly
https://www.googleapis.com/auth/gmail.send
```

Le mot de passe Gmail n’est jamais demandé ni stocké. Le jeton OAuth est enregistré dans le volume privé et chiffré avec `APP_ENCRYPTION_KEY`.

Après une modification des scopes, il faut déconnecter puis reconnecter Gmail depuis **Paramètres**.

## Synchronisation

Gmail est un connecteur `GMAIL` enregistré dans le même registre qu’Arbeitnow et Adzuna.

La synchronisation est lancée :

- par le scheduler avec les autres sources ;
- depuis la page **Connecteurs** ;
- depuis la page **Messagerie** ;
- en ligne de commande :

```bash
docker compose exec api php bin/console app:jobs:sync --force --connector=gmail
```

La recherche Gmail est contrôlée par :

```dotenv
GMAIL_SEARCH_QUERY=(job OR mission OR candidature OR application OR recruiter OR entretien) newer_than:30d
GMAIL_MAX_RESULTS=100
GMAIL_MAX_PAGES=3
```

Les limites protègent contre une lecture trop large de la boîte mail.

## Catégories métier

Chaque nouveau message est classé dans une catégorie :

```text
JOB_ALERT
RECRUITER_OPPORTUNITY
APPLICATION_CONFIRMATION
APPLICATION_REPLY
INTERVIEW_REQUEST
REJECTION
INFORMATION_REQUEST
UNKNOWN
```

Le classement est déterministe et testable. Il ne repose pas encore sur un modèle d’IA externe.

Les catégories `INTERVIEW_REQUEST`, `INFORMATION_REQUEST`, `APPLICATION_REPLY` et `RECRUITER_OPPORTUNITY` peuvent être marquées comme nécessitant une action.

## Association aux candidatures

Pour les réponses, JobPilot compare le sujet et le contenu du message aux candidatures récentes :

- intitulé du poste ;
- entreprise ;
- mots significatifs de l’intitulé.

Une association n’est créée que lorsque le score dépasse un seuil conservateur. Lorsqu’une association est trouvée, le statut peut évoluer vers :

```text
APPLICATION_CONFIRMED
RESPONSE_RECEIVED
INFORMATION_REQUESTED
INTERVIEW
REJECTED
```

Une association incertaine reste non liée afin d’éviter de modifier la mauvaise candidature.

## Extraction des offres

Pour les catégories `JOB_ALERT` et `RECRUITER_OPPORTUNITY`, JobPilot analyse les liens du message complet.

Les domaines reconnus incluent actuellement :

- LinkedIn ;
- Indeed ;
- APEC ;
- Hellowork ;
- Welcome to the Jungle ;
- Free-Work ;
- LesJeudis ;
- Le Hibou ;
- France Travail.

Les paramètres de tracking courants sont supprimés de l’URL. Les liens de désabonnement, de connexion et de préférences sont ignorés.

Une offre extraite passe ensuite par le pipeline normal : déduplication, filtrage, scoring, sélection du CV et préparation.

## Données conservées

JobPilot conserve uniquement les informations nécessaires à l’Inbox :

- identifiants Gmail et thread ;
- expéditeur, destinataire et Reply-To ;
- sujet, aperçu et texte analysé ;
- catégorie et raison du classement ;
- plateforme détectée ;
- association éventuelle à une offre ou une candidature ;
- état traité / à traiter.

Le HTML complet n’est pas conservé en base. Le texte stocké est limité à 100 000 caractères.

## Interface

La page **Messagerie** permet de :

- synchroniser Gmail ;
- filtrer par catégorie ou par action requise ;
- voir la plateforme détectée ;
- ouvrir le message original dans Gmail ;
- voir l’offre et la candidature associées ;
- marquer un message comme traité ou le remettre à traiter.

## Limites connues

- Les formats d’alertes peuvent changer selon les plateformes.
- Une offre dont le lien ou le titre est trop générique n’est pas importée.
- L’association à une candidature est volontairement prudente.
- Le connecteur ne lit que les messages correspondant à `GMAIL_SEARCH_QUERY`.
- Aucun message n’est supprimé, déplacé, marqué comme lu ou modifié dans Gmail.
