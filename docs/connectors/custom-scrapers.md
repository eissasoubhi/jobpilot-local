# Scrapers personnalisés

JobPilot permet à l’utilisateur d’enregistrer lui-même les sites dont il a vérifié que la collecte automatisée est autorisée.

Cette fonctionnalité sépare explicitement deux responsabilités :

- l’utilisateur décide si une source peut être collectée et conserve la référence/date de cette vérification ;
- JobPilot applique ensuite les limites techniques, le mode de rendu, l’extraction et la synchronisation.

## Registre

La page **Paramètres > Scraping personnalisé** enregistre pour chaque source :

- nom ;
- domaine HTTPS ;
- URL de la liste des offres ;
- URL d’une fiche détail facultative ;
- mode `AUTO`, `HTTP` ou `BROWSER` ;
- fréquence de synchronisation ;
- nombre maximal de pages ;
- nombre maximal de fiches détail ;
- activation ;
- confirmation explicite de l’autorisation ;
- date de vérification ;
- référence ou note CGU facultative.

Une source ne peut pas être activée, testée ou analysée si l’utilisateur retire sa confirmation d’autorisation.

## Tester le site

Le bouton **Tester le site** effectue un diagnostic manuel et borné. Il ne crée aucune offre et ne lance aucune synchronisation.

Le diagnostic :

1. vérifie que l’autorisation est toujours confirmée ;
2. passe par le client HTTP de scraping contrôlé déjà utilisé par JobPilot ;
3. vérifie `robots.txt` avant la page cible ;
4. utilise zéro retry, un timeout de 10 secondes, une réponse maximale de 3 Mo, une requête cible maximale par diagnostic et un quota journalier dédié ;
5. analyse uniquement la réponse HTML reçue ;
6. renvoie le statut HTTP, la taille de réponse, les signaux détectés et le mode recommandé.

La CI utilise uniquement des réponses HTTP synthétiques locales : aucun test automatisé ne dépend d’un site réel.

## Choix du mode

`AUTO` reste le mode recommandé.

Le diagnostic recherche plusieurs signaux : données structurées `JobPosting`, liens ressemblant à des fiches d’offres, quantité de texte visible, termes liés aux offres, nombre de scripts, conteneurs d’application vides et marqueurs React/Next/Nuxt/Angular.

Le résultat suit ces règles :

1. si les offres ou des signaux forts sont déjà présents dans le HTML serveur, `HTTP` est recommandé ;
2. si la réponse ressemble clairement à une coquille JavaScript presque vide, `BROWSER` est recommandé ;
3. si le résultat reste ambigu, HTTP reste le point de départ le plus léger et le diagnostic indique qu’une vérification Browser sera nécessaire ;
4. un mode forcé par l’utilisateur reste prioritaire sur la recommandation ;
5. Browser ne doit jamais servir à contourner une authentification, un CAPTCHA ou une restriction d’accès.

## Aperçu d’extraction Gemini

Le bouton **Analyser avec Gemini** ajoute une étape d’aperçu sans persistance. Il ne crée aucun `JobOffer`, aucune candidature et aucune synchronisation planifiée.

Le pipeline est :

1. la même récupération HTTP contrôlée que le diagnostic est utilisée ;
2. `AUTO` recalcule le mode effectif ;
3. si le mode effectif est `BROWSER`, l’aperçu s’arrête immédiatement : aucun appel Gemini et aucun quota Gemini ne sont consommés tant que Playwright n’a pas rendu la page ;
4. pour un DOM HTTP exploitable, JobPilot enlève scripts, styles, iframes, SVG, templates, commentaires et attributs inutiles ;
5. les blocs JSON-LD contenant `JobPosting` sont conservés séparément ;
6. le contenu compacté est plafonné à **60 000 caractères** avant l’appel au modèle ;
7. Gemini doit renvoyer un JSON structuré contenant au maximum 30 offres ;
8. JobPilot revalide et borne la sortie avant de l’afficher.

Le prompt traite le DOM comme une donnée **non fiable**. Une instruction placée dans une annonce ou dans le HTML ne peut pas modifier les règles d’extraction. Le modèle reçoit uniquement le nom de la source, son domaine, l’URL publique et le DOM compacté ; le profil candidat, les CV, Gmail et les identifiants de connecteurs ne sont pas envoyés pour cette extraction.

La validation après Gemini :

- refuse les lignes sans titre ;
- borne les longueurs ;
- limite les technologies ;
- normalise les dates et valeurs numériques ;
- déduplique l’aperçu ;
- accepte uniquement les URL HTTPS appartenant exactement au domaine enregistré ;
- rejette les URL `javascript:`, `mailto:`, `tel:`, les identifiants intégrés et les URL externes.

L’extraction réutilise la configuration Gemini de JobPilot et le même `AiQuotaManager` provider+model. Un cache dédié, indexé par modèle et empreinte du prompt/DOM, conserve pendant 30 jours uniquement la sortie structurée validée. Un cache hit évite un nouvel appel fournisseur et une nouvelle réservation de quota.

## Étapes suivantes

Le registre, le diagnostic HTTP et l’aperçu Gemini sont maintenant branchés. Les prochains incréments prévus sont :

- worker Browser/Playwright isolé pour les sources publiques autorisées dont le rendu JavaScript est réellement requis ;
- extraction Gemini du DOM rendu par Browser avec les mêmes règles de validation ;
- intégration des offres validées dans le pipeline normal de normalisation, déduplication et matching ;
- pagination bornée, fiches détail et synchronisation régulière selon la configuration de chaque source.

## Sécurité

Le registre accepte uniquement des URL HTTPS sans identifiants intégrés. L’URL d’exemple de détail doit utiliser le même domaine que la liste des offres.

Le diagnostic et l’aperçu réutilisent les protections du transport contrôlé : blocage des URL locales/privées explicites, contrôle `robots.txt`, quotas, délai minimal, timeout, redirections bornées, circuit breaker, cache HTTP et taille de réponse maximale. Aucun login, cookie de session privé, CAPTCHA bypass, proxy furtif ou contournement de 401/403/429 n’est ajouté.
