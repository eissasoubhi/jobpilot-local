# Connecteur Gmail

## Objectif

Le connecteur Gmail transforme les alertes d’emploi et les échanges de recrutement en données exploitables par JobPilot.

Il couvre deux usages distincts :

1. envoyer les candidatures explicitement autorisées ;
2. lire les messages correspondant à la recherche configurée afin de détecter des offres, des réponses et des actions à traiter.

Gmail sert aussi de canal d’acquisition assisté lorsque les conditions d’une plateforme ne permettent pas à JobPilot de scraper directement son site. JobPilot analyse alors uniquement les alertes reçues dans le compte Gmail connecté par l’utilisateur.

## Autorisations OAuth

JobPilot demande uniquement :

```text
https://www.googleapis.com/auth/gmail.readonly
https://www.googleapis.com/auth/gmail.send
```

Le mot de passe Gmail n’est jamais demandé ni stocké. Le jeton OAuth est enregistré dans le volume privé et chiffré avec `APP_ENCRYPTION_KEY`.

Après une modification des scopes, il faut déconnecter puis reconnecter Gmail depuis **Paramètres**.

## Synchronisation

Gmail est un connecteur `GMAIL` enregistré dans le même registre que les autres sources.

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
GMAIL_SEARCH_QUERY=(job OR emploi OR offre OR mission OR candidature OR application OR recruiter OR entretien) newer_than:30d
GMAIL_MAX_RESULTS=100
GMAIL_MAX_PAGES=3
```

`emploi` et `offre` font partie de la requête recommandée afin de couvrir les alertes francophones qui n’emploient pas les mots `job` ou `mission` dans le sujet/contenu recherché par Gmail. Une installation existante avec une valeur personnalisée conserve sa propre requête.

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

La reconnaissance de plateforme est centralisée dans `AssistedJobPlatformCatalog`. Les plateformes reconnues incluent actuellement :

- LinkedIn ;
- Indeed ;
- APEC ;
- Hellowork ;
- Welcome to the Jungle ;
- Free-Work ;
- LesJeudis ;
- Le Hibou ;
- France Travail.

Le même catalogue accepte plusieurs variantes de chemins d’offres (par exemple jobs/missions Free-Work, job/jobs LesJeudis ou mission/freelance LeHibou) tout en exigeant le domaine attendu et un chemin ressemblant réellement à une offre.

Les paramètres de tracking courants sont supprimés de l’URL. Les liens de désabonnement, de connexion, de conditions ou de préférences ne deviennent pas des offres.

Une offre extraite reçoit dans ses données brutes :

```text
alertPlatform
alertPlatformCode
```

Le code stable permet de relier l’import Gmail au catalogue des sources suggérées sans changer la source canonique du connecteur, qui reste `gmail`.

Une offre extraite passe ensuite par le pipeline normal : déduplication, filtrage, scoring, sélection du CV et préparation.

## Sources suggérées et conformité

La page **Paramètres > Scraping personnalisé** expose les plateformes prioritaires avec leur statut de conformité. Lorsque `gmailSupported=true`, le panneau affiche **Gmail pris en charge** et un accès direct à **Messagerie**.

Cela ne transforme pas Gmail en contournement du site : JobPilot ne visite pas automatiquement la plateforme à partir de ce canal pour explorer d’autres offres. Il traite les liens que l’utilisateur a déjà reçus dans son propre compte Gmail et les normalise dans le pipeline existant.

Pour les plateformes classées `ASSISTED_ONLY`, le panneau ne propose aucun bouton d’activation du scraper. L’utilisateur peut créer une alerte sur la plateforme, connecter Gmail à JobPilot puis lancer **Synchroniser Gmail**.

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

- connecter/reconnecter Gmail ;
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
- Une installation qui a déjà défini une requête personnalisée doit l’élargir manuellement si elle veut ajouter `emploi`/`offre`.
- Aucun message n’est supprimé, déplacé, marqué comme lu ou modifié dans Gmail.
