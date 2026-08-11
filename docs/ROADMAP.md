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

## Étape 4

- Module IA local ou fournisseur configurable, avec sortie JSON structurée.
- Score sémantique avec embeddings locaux.
- Analyse des statistiques : taux de réponse par source, CV, salaire/TJM et intitulé.
- Exploiter les statistiques d’autofill : taux de champs reconnus, corrections manuelles et compatibilité par ATS.
