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

Une source ne peut pas être activée, testée ou prévisualisée si l’utilisateur retire sa confirmation d’autorisation.

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

## Prévisualiser l’extraction HTTP

L’API `POST /api/custom-scrapers/{id}/preview` exécute la première vraie extraction générique, sans enregistrer d’offre dans le catalogue.

Cette prévisualisation :

1. exige toujours la confirmation d’autorisation ;
2. charge la liste via le transport HTTP contrôlé, après contrôle `robots.txt` ;
3. utilise zéro retry, un timeout de 10 secondes et une réponse maximale de 3 Mo par page ;
4. applique d’abord le détecteur HTTP/Browser ;
5. en mode `AUTO`, n’extrait que si HTTP est le mode effectif ;
6. refuse un mode `BROWSER` forcé tant que le worker Playwright isolé n’est pas disponible ;
7. retourne au maximum 50 candidats ;
8. peut enrichir au maximum 10 fiches détail par prévisualisation, même si la source est configurée avec une limite supérieure ;
9. arrête immédiatement les lectures de fiches après la première erreur de détail afin de ne pas insister sur une source qui refuse ou devient instable.

L’extraction de liste est déterministe et privilégie les données structurées Schema.org `JobPosting`. Elle récupère quand disponibles le titre, l’entreprise, la localisation, le type de contrat, le mode de travail, la description, la date de publication, l’URL, l’identifiant et les bornes de salaire/TJM.

Si aucun `JobPosting` n’est présent, l’extracteur peut retourner des liens de fiches probables du **même domaine**. Ces résultats sont marqués `JOB_LINK` avec `needsDetailFetch=true`.

Pour les fiches détail, JobPilot privilégie à nouveau un `JobPosting` structuré. À défaut, un fallback DOM borné peut enrichir les champs les plus fiables : titre, description visible, contrat, mode de travail, date et TJM. L’identifiant externe trouvé sur la liste est conservé afin de ne pas casser la future déduplication.

Chaque URL de détail repasse par le transport contrôlé et le contrôle `robots.txt`. Les URL hors domaine sont ignorées. Aucun login, cookie privé, Gemini ou navigateur n’est déclenché par cette prévisualisation.

## Qualité de l’extraction

La qualité de l’extraction est évaluée séparément du score de matching du profil candidat.

Chaque candidat reçoit `rawData.quality` avec :

- `score` de 0 à 100 ;
- `reliable` ;
- les raisons du score.

Le score favorise un titre réellement exploitable, une URL HTTPS du domaine autorisé, une description suffisamment longue, la présence de données Schema.org `JobPosting`, un enrichissement de fiche détail et des champs métier supplémentaires.

Un simple lien de type `JOB_LINK` sans description ne peut pas devenir fiable. Une URL hors domaine ou un titre générique comme « Voir le poste » bloque également l’éligibilité. Le seuil actuel pour l’import automatique est **70/100 avec une description d’au moins 60 caractères**.

Ce garde-fou ne décide pas si l’offre correspond au profil PHP/Symfony ou à un autre profil candidat : ce contrôle reste effectué ensuite par le pipeline normal de matching de JobPilot.

## Synchronisation dans JobPilot

Chaque source personnalisée active et autorisée devient un connecteur dynamique distinct, avec un code stable `custom-scraper-{id}`. Elle dispose donc de son propre état de synchronisation, historique, diagnostics, parser et origine dans le catalogue.

Pour cette première version de synchronisation générique :

1. une seule page de liste est lue par cycle ;
2. jusqu’à 10 fiches détail sont enrichies selon la configuration de la source ;
3. seuls les candidats `rawData.quality.reliable=true` sont retournés par le connecteur ;
4. ces candidats entrent ensuite dans le pipeline canonique existant : déduplication multi-sources, filtre profil/IA éventuel, scoring et préparation ;
5. les candidats non fiables ne sont pas persistés comme offres ;
6. la fréquence `syncIntervalMinutes` de chaque source est respectée sans ajouter de colonne en base ;
7. les connecteurs classiques sans fréquence spécifique continuent d’utiliser l’intervalle global JobPilot.

Le champ `maxPages` reste conservé dans la configuration mais n’est pas encore utilisé pour parcourir plusieurs pages : la pagination générique devra être détectée explicitement avant d’être activée. JobPilot ne fabrique pas automatiquement des URL `?page=N` pour un domaine inconnu.

Une source forcée en `BROWSER` reste non synchronisable tant que le worker Playwright isolé n’est pas livré. En mode `AUTO`, une page détectée comme nécessitant Browser ne produit aucune offre HTTP.

## Choix du mode

`AUTO` reste le mode recommandé.

Le diagnostic recherche plusieurs signaux : données structurées `JobPosting`, liens ressemblant à des fiches d’offres, quantité de texte visible, termes liés aux offres, nombre de scripts, conteneurs d’application vides et marqueurs React/Next/Nuxt/Angular.

Le résultat suit ces règles :

1. si les offres ou des signaux forts sont déjà présents dans le HTML serveur, `HTTP` est recommandé ;
2. si la réponse ressemble clairement à une coquille JavaScript presque vide, `BROWSER` est recommandé ;
3. si le résultat reste ambigu, HTTP reste le point de départ le plus léger et le diagnostic indique qu’une vérification Browser sera nécessaire ;
4. un mode forcé par l’utilisateur reste prioritaire sur la recommandation ;
5. Browser ne doit jamais servir à contourner une authentification, un CAPTCHA ou une restriction d’accès.

Le diagnostic et la prévisualisation actuels ne lancent pas encore Chromium. La vérification Browser/Playwright réelle arrivera dans une étape séparée et isolée.

## Étapes suivantes

Le registre, le diagnostic HTTP, l’extraction de liste, l’enrichissement borné des fiches, l’interface de prévisualisation, le garde-fou de qualité et le connecteur dynamique sont maintenant branchés dans la chaîne en cours de livraison. Les prochains incréments prévus sont :

- exposer la fiabilité dans l’interface de prévisualisation ;
- ajouter une détection de pagination générique sûre ;
- utiliser Gemini seulement lorsque nécessaire pour interpréter un DOM inconnu, avec cache et quotas ;
- worker Browser/Playwright isolé pour les sources publiques autorisées dont le rendu JavaScript est réellement requis.

## Sécurité

Le registre accepte uniquement des URL HTTPS sans identifiants intégrés. L’URL d’exemple de détail doit utiliser le même domaine que la liste des offres.

Le diagnostic et la prévisualisation réutilisent les protections du transport contrôlé : blocage des URL locales/privées explicites, contrôle `robots.txt`, quotas, délai minimal, timeout, redirections bornées, circuit breaker, cache HTTP et taille de réponse maximale. Aucun login, cookie de session privé, CAPTCHA bypass, proxy furtif ou contournement de 401/403/429 n’est ajouté.
