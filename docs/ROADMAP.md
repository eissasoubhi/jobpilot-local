# Roadmap

## Version livrée — MVP local

- Profil, paramètres et CV.
- Import manuel et extension Chrome.
- Score, langue, fraîcheur, sélection du CV.
- Préparation des messages et lettres.
- Calcul du TJM.
- Candidatures et positionnements.
- Gmail OAuth lecture seule.
- Registre des sources.

## Étape 2

- Extraction plus robuste du salaire/TJM depuis le texte.
- Déduplication d’offres par URL canonique et similarité.
- Association automatique des e-mails aux candidatures.
- Modèles de réponses personnalisables.
- Notifications macOS.
- API officielle France Travail.

## Étape 3 — JobPilot Autofill / Browser Extension

Objectif : supprimer au maximum la saisie répétitive pendant les candidatures tout en gardant une validation humaine avant l’envoi final.

### 3.1 — Profil candidat centralisé

- Étendre le profil candidat avec les données réutilisables : identité, coordonnées, adresse, intitulé, localisation, mobilité, disponibilité, type de contrat, salaire, TJM, télétravail, LinkedIn, GitHub et autres URLs professionnelles.
- Conserver les années d’expérience globales et par technologie lorsque disponibles.
- Gérer les variantes de valeurs en français et en anglais.
- Permettre de définir une valeur par défaut et des valeurs alternatives selon le contexte de la candidature.

### 3.2 — Bibliothèque de réponses récurrentes

- Bibliothèque éditable de réponses aux questions de présélection.
- Questions/réponses FR et EN.
- Support des réponses texte, numériques, oui/non, choix unique et choix multiple.
- Exemples : disponibilité, mobilité, autorisation de travail, besoin de sponsorship, années d’expérience, salaire/TJM souhaité, télétravail et technologies maîtrisées.
- Recherche et réutilisation automatique des réponses connues.

### 3.3 — Moteur générique d’autofill

- Ajouter à l’extension Chrome une action explicite « Remplir avec JobPilot ».
- Détecter les champs à partir des labels, `name`, `id`, `placeholder`, attributs ARIA et contexte DOM.
- Remplir automatiquement les champs texte, textarea, nombres, dates, radios, checkboxes et listes déroulantes.
- Gérer les composants d’autocomplétion où l’utilisateur doit normalement rechercher puis sélectionner une option.
- Faire correspondre les valeurs du profil aux options proposées, par exemple localisation, type de contrat ou niveau d’expérience.
- Afficher les champs remplis, non reconnus ou ambigus avant validation.

### 3.4 — CV et documents

- Sélection automatique du CV approprié selon la langue, le poste et la candidature.
- Téléversement du CV dans les formulaires pris en charge.
- Support de la lettre de motivation générée par JobPilot lorsque le formulaire demande un fichier.
- Support du collage de la lettre dans un champ texte lorsque le site ne demande pas de fichier.
- Prévoir PDF et DOCX pour les documents générés lorsque la plateforme les accepte.

### 3.5 — Adaptateurs ATS

- Ajouter des adaptateurs spécialisés quand le moteur générique ne suffit pas.
- Priorité : SmartRecruiters, Greenhouse, Lever, Teamtailor, Recruitee, Workday et Ashby.
- Prévoir ensuite les plateformes de recrutement utilisées fréquemment dans JobPilot.
- Conserver les mappings spécifiques par plateforme sans polluer le moteur générique.

### 3.6 — Réponses intelligentes aux questions personnalisées

- Détecter les questions qui ne correspondent pas à une réponse enregistrée.
- Générer une proposition à partir du profil candidat, du CV, de l’offre et des informations déjà connues sur l’entreprise.
- Ne jamais inventer une compétence, une expérience ou une autorisation administrative absente du profil.
- Afficher la réponse générée avant insertion avec actions Modifier, Régénérer et Insérer.
- Mettre en cache les réponses lorsque cela réduit les appels IA sans réutiliser une réponse contextuelle de manière incorrecte.

### 3.7 — Apprentissage à partir des corrections

- Détecter lorsqu’une valeur remplie par JobPilot est corrigée manuellement.
- Proposer d’enregistrer la correction comme nouvelle valeur ou règle de mapping.
- Mémoriser les mappings spécifiques à un domaine ou ATS lorsque nécessaire.
- Permettre de consulter, modifier et supprimer les règles apprises.
- Ne jamais apprendre automatiquement une donnée sensible ou une réponse ambiguë sans validation explicite.

### 3.8 — UX, contrôle et sécurité

- Mode par défaut : Remplir → Vérifier → Envoyer manuellement.
- Ne pas déclencher automatiquement le bouton final de candidature.
- Signaler clairement les champs remplis avec forte confiance et ceux nécessitant une vérification.
- Demander confirmation avant toute réponse potentiellement sensible ou engageante.
- Minimiser les permissions de l’extension Chrome et limiter l’accès aux domaines nécessaires.
- Garder autant que possible les données personnelles dans JobPilot/local et éviter leur exposition à des services tiers inutiles.
- Journaliser localement les champs remplis pour faciliter le debug sans enregistrer les mots de passe ni secrets.

### Plan de PRs — Autofill

1. **PR Autofill 01 — Candidate Profile Schema** : profil candidat enrichi + API de lecture pour l’extension.
2. **PR Autofill 02 — Reusable Answers Library** : bibliothèque de questions/réponses FR/EN et interface de gestion.
3. **PR Autofill 03 — Generic Form Detector** : détection et classification des champs d’un formulaire.
4. **PR Autofill 04 — Generic Autofill Engine** : remplissage texte, select, radio, checkbox et autocomplete.
5. **PR Autofill 05 — Resume & Cover Letter Upload** : sélection et téléversement des documents.
6. **PR Autofill 06 — ATS Adapters** : SmartRecruiters, Greenhouse, Lever, Teamtailor et Recruitee.
7. **PR Autofill 07 — Workday & Ashby Adapters** : prise en charge des formulaires plus spécifiques.
8. **PR Autofill 08 — AI Custom Answers** : génération contextuelle des réponses inconnues avec validation utilisateur.
9. **PR Autofill 09 — Learn From Corrections** : mappings appris et règles spécifiques par site.
10. **PR Autofill 10 — Review & Safety UX** : aperçu avant envoi, niveaux de confiance, permissions et garde-fous.
11. **PR Autofill 11 — E2E Compatibility Matrix** : tests end-to-end et matrice de compatibilité des principaux ATS.

## Étape 4 — Intelligence et reporting

- Module IA local ou fournisseur configurable, avec sortie JSON structurée.
- Score sémantique avec embeddings locaux.
- Analyse des statistiques : taux de réponse par source, CV, salaire/TJM et intitulé.
- Exploiter les statistiques d’autofill : taux de champs reconnus, corrections manuelles et compatibilité par ATS.

## Étape 5 — Preference Learning / Ranking adaptatif

Objectif : faire évoluer le classement des offres à partir des décisions réelles de l’utilisateur et des résultats du pipeline, sans remplacer les critères explicites du profil.

### 5.1 — Signaux de préférence

- Enregistrer les décisions utilisateur : « correspond », « ne correspond pas », ignorée, enregistrée et candidature envoyée.
- Utiliser les événements aval comme signaux plus forts : réponse recruteur, demande d’informations, entretien, offre et embauche.
- Conserver les signaux négatifs : rejet explicite de l’offre, refus du candidat, désintérêt répété pour un type de poste ou une technologie.
- Ne jamais considérer un refus recruteur comme une préférence négative du candidat.

### 5.2 — Modèle de préférence explicable

- Construire un score de préférence séparé du score de compatibilité métier.
- Identifier les tendances récurrentes : intitulés, technologies, secteurs, localisation, contrat, télétravail, salaire/TJM et type d’entreprise.
- Garder les critères utilisateur explicites prioritaires sur l’apprentissage implicite.
- Afficher les raisons principales lorsqu’un score est influencé par l’historique : « vous avez souvent envoyé des candidatures à des missions similaires », « ce type d’offre est souvent marqué comme non pertinent », etc.
- Prévoir la possibilité de désactiver ou réinitialiser l’apprentissage.

### 5.3 — Similar Jobs

- Ajouter une recherche « offres similaires » à partir d’une ou plusieurs candidatures choisies.
- Permettre d’utiliser les candidatures envoyées, les offres favorites ou les offres ayant conduit à un entretien comme exemples positifs.
- Utiliser un seuil de similarité configurable et expliquer les éléments communs détectés.
- Ne jamais masquer automatiquement une offre uniquement à cause du modèle de préférence ; conserver un garde-fou par score métier et critères explicites.

### Plan de PRs — Preference Learning

1. **Preference 01 — Preference Signals** : modèle de données et événements de préférence.
2. **Preference 02 — Preference Feature Extraction** : extraction des dimensions apprises depuis les offres et le pipeline.
3. **Preference 03 — Adaptive Ranking** : score de préférence séparé + combinaison contrôlée avec le matching existant.
4. **Preference 04 — Explainability & Controls** : raisons, reset, activation/désactivation et transparence utilisateur.
5. **Preference 05 — Similar Jobs** : recherche et classement par similarité à partir d’exemples positifs.

## Étape 6 — CV Tailoring sécurisé

Objectif : adapter un CV à une offre sans jamais inventer de compétence, d’expérience, d’entreprise ou de responsabilité.

### 6.1 — Modèle source de vérité

- Conserver le CV original comme source immuable.
- Extraire les expériences, compétences, réalisations et informations de profil dans une représentation structurée et traçable.
- Toute proposition de modification doit pointer vers un fait présent dans le CV source ou le profil candidat validé.

### 6.2 — Adaptation par offre

- Réordonner les compétences selon leur pertinence pour l’offre.
- Mettre davantage en avant les expériences et réalisations directement liées au besoin.
- Adapter le résumé professionnel et les mots-clés ATS.
- Raccourcir les éléments peu pertinents lorsque cela améliore la lisibilité.
- Préserver les dates, employeurs, intitulés, technologies réellement utilisées et responsabilités réelles.

### 6.3 — Diff et validation

- Afficher un diff clair entre le CV source et la version adaptée.
- Signaler chaque reformulation générée et sa source factuelle.
- Permettre Accepter, Modifier, Rejeter et Régénérer par bloc.
- Refuser automatiquement toute proposition non rattachable à un fait connu.
- Conserver un historique des variantes utilisées par candidature.

### 6.4 — Intégration Autofill

- Associer explicitement la variante de CV validée à la candidature.
- L’extension ne doit téléverser que la variante approuvée pour cette candidature.
- Prévoir un fallback vers le CV original lorsqu’aucune variante adaptée n’est validée.

### Plan de PRs — CV Tailoring

1. **CV Tailoring 01 — Structured Resume Facts**.
2. **CV Tailoring 02 — Grounded Tailoring Engine**.
3. **CV Tailoring 03 — Resume Diff & Review UI**.
4. **CV Tailoring 04 — Application Resume Variants**.
5. **CV Tailoring 05 — Autofill Integration & E2E**.

## Étape 7 — Hiring Manager / Recruiter Intelligence

Objectif : prolonger une candidature par une stratégie de contact et de relance intégrée au CRM JobPilot.

### 7.1 — Contacts liés aux organisations

- Enrichir le CRM avec les contacts liés à une entreprise, une agence ou un client final.
- Stocker nom, fonction, niveau hiérarchique, LinkedIn, email, source et niveau de confiance.
- Catégoriser les rôles : Recruiter, Talent Acquisition, HR, Engineering Manager, Head of Engineering, CTO, Founder, Commercial/Business Manager, etc.
- Distinguer les contacts réellement connus de ceux uniquement suggérés.

### 7.2 — Discovery et enrichissement

- Permettre une recherche ciblée de contacts depuis une offre ou une organisation.
- Prioriser les personnes les plus susceptibles d’être impliquées dans le recrutement concerné.
- Prévoir des fournisseurs interchangeables ou des imports manuels plutôt qu’un couplage à un service unique.
- Ne pas inventer une adresse email lorsqu’elle n’est pas vérifiée ou suffisamment fiable.

### 7.3 — Follow-up intelligent

- Depuis une candidature envoyée, proposer une relance après un délai configurable.
- Utiliser l’historique Gmail pour ne jamais relancer lorsqu’une réponse a déjà été reçue.
- Préparer des messages différents selon le destinataire : recruteur, hiring manager, commercial, client final.
- Afficher clairement le contexte utilisé et laisser l’utilisateur valider l’envoi.
- Mesurer le taux de réponse après relance et la performance par type de contact.

### Plan de PRs — Contacts & Follow-up

1. **Contacts 01 — CRM Contact Model**.
2. **Contacts 02 — Organization Contact Workspace**.
3. **Contacts 03 — Contact Discovery Provider Interface**.
4. **Contacts 04 — Follow-up Recommendations**.
5. **Contacts 05 — Gmail Follow-up Guardrails & Send Flow**.
6. **Contacts 06 — Contact Performance Analytics**.

## Étape 8 — Interview Workspace

Objectif : créer un espace de préparation directement lié à chaque candidature au lieu d’un assistant entretien isolé.

### 8.1 — Contexte automatique

- Construire le contexte à partir de l’offre canonique, de la description complète, de l’entreprise, du CV réellement envoyé, des réponses de candidature et des échanges Gmail associés.
- Ajouter le contexte CRM disponible : recruteur, commercial, client final et historique des interactions.
- Ne jamais utiliser une information non reliée à la candidature sans l’indiquer explicitement.

### 8.2 — Préparation

- Générer une présentation orale 2, 5 et 10 minutes adaptée à la mission.
- Générer les questions techniques probables avec réponses structurées.
- Générer les questions métier et comportementales probables.
- Proposer les expériences les plus pertinentes à mettre en avant.
- Générer des questions pertinentes à poser au recruteur ou au client.
- Identifier les points faibles ou technologies manquantes à préparer avant l’entretien.

### 8.3 — Simulation et apprentissage

- Ajouter un mode simulation d’entretien avec questions successives.
- Permettre de noter les questions réellement posées après l’entretien.
- Réutiliser ces retours pour améliorer les préparations futures par type de poste, technologie, entreprise ou secteur.
- Ne jamais présenter un contenu généré comme une expérience réellement vécue par le candidat.

### Plan de PRs — Interview Workspace

1. **Interview 01 — Application Context Builder**.
2. **Interview 02 — Interview Preparation Generator**.
3. **Interview 03 — Interview Workspace UI**.
4. **Interview 04 — Mock Interview Mode**.
5. **Interview 05 — Real Interview Feedback Learning**.

## Étape 9 — Autopilot contrôlé

Objectif : permettre un niveau d’automatisation supérieur uniquement lorsque les données, le matching et le formulaire sont suffisamment fiables.

### 9.1 — Trois modes

- **Assisté** : JobPilot trouve, analyse et prépare ; aucune écriture dans les formulaires sans action utilisateur.
- **Copilot** : JobPilot prépare et remplit ; l’utilisateur vérifie et déclenche l’envoi final.
- **Autopilot** : JobPilot peut déclencher l’envoi final uniquement lorsqu’une politique explicite l’autorise.

### 9.2 — Policy Engine

Une candidature Autopilot ne peut être autorisée que si toutes les règles configurées sont satisfaites, par exemple :

- score de compatibilité supérieur au seuil configuré ;
- score de préférence suffisant ;
- aucun champ ambigu ou inconnu bloquant ;
- aucune question sensible non validée ;
- CV et documents explicitement sélectionnés ;
- salaire/TJM compatible avec la politique candidat ;
- aucun double positionnement ou conflit CRM ;
- ATS/site marqué comme compatible et fiable ;
- aucun CAPTCHA, authentification supplémentaire ou protection anti-automatisation ;
- limite journalière et quota par entreprise respectés.

### 9.3 — Dry Run, audit et arrêt d’urgence

- Ajouter un mode « Dry Run » montrant exactement ce qui serait envoyé sans soumettre.
- Journaliser localement la décision de politique, les champs remplis, documents sélectionnés et raison de l’autorisation.
- Rendre chaque soumission idempotente pour éviter un double envoi.
- Fournir un bouton global d’arrêt immédiat de l’Autopilot.
- Permettre de désactiver une entreprise, une source ou un ATS de l’Autopilot.

### 9.4 — Déploiement progressif

- Commencer par une liste blanche de sites/ATS validés en E2E.
- Limiter initialement le volume quotidien.
- Comparer taux de réponse, taux d’erreur et corrections entre Copilot et Autopilot avant d’élargir la couverture.
- Ne jamais contourner CAPTCHA, authentification, limites de plateforme ou protections anti-automatisation.

### Plan de PRs — Autopilot

1. **Autopilot 01 — Automation Modes & Policy Model**.
2. **Autopilot 02 — Submission Eligibility Evaluator**.
3. **Autopilot 03 — Dry Run & Audit Trail**.
4. **Autopilot 04 — Trusted ATS Submission Adapter**.
5. **Autopilot 05 — Idempotency, Limits & Kill Switch**.
6. **Autopilot 06 — Controlled Rollout & Metrics**.

## Principes UX transverses

Objectif : conserver une interface simple même si la plateforme devient techniquement plus puissante.

- Ne pas exposer la complexité des connecteurs, workers, scrapers, mappings ATS ou moteurs IA sur les écrans principaux.
- Le dashboard doit répondre d’abord à : « qu’est-ce qui a changé ? », « qu’est-ce qui mérite mon attention ? » et « quelle est la prochaine action ? ».
- Préférer des actions métier simples : Revoir, Envoyer, Relancer, Préparer entretien, Adapter CV.
- Garder diagnostics, connecteurs, quotas et réglages avancés dans des écrans secondaires.
- Éviter de créer un outil IA indépendant pour chaque besoin lorsque le même contexte candidat/offre/candidature peut alimenter plusieurs fonctionnalités.
- Construire un contexte métier partagé : Candidate Profile + Offers + Applications + Gmail + CRM + Timeline + Market Signals.
- Conserver l’explicabilité : chaque action automatique importante doit pouvoir répondre à « pourquoi JobPilot a fait ça ? ».
- Conserver le contrôle humain comme comportement par défaut ; augmenter l’automatisation uniquement avec une politique explicite.

## Ordre stratégique recommandé

1. Terminer **Autofill / Browser Extension** et la matrice ATS.
2. Construire **Preference Learning** pour améliorer le ranking à partir de l’usage réel.
3. Ajouter **CV Tailoring sécurisé**.
4. Ajouter **Hiring Manager / Recruiter Intelligence** et les relances Gmail/CRM.
5. Construire **Interview Workspace**.
6. Introduire **Autopilot contrôlé** seulement après validation de la fiabilité Autofill/ATS et des garde-fous.

Le positionnement cible de JobPilot est celui d’un système de pilotage personnel de la recherche d’emploi couvrant le cycle complet :

**Discover → Understand → Decide → Apply → Track → Communicate → Prepare → Negotiate → Learn**.
