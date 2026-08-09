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

Une source ne peut pas être activée si l’utilisateur retire sa confirmation d’autorisation.

## Choix du mode

`AUTO` est le mode recommandé.

Le comportement cible est :

1. tenter un téléchargement HTTP contrôlé ;
2. analyser le HTML reçu et vérifier si la liste des offres est exploitable ;
3. conserver HTTP lorsque le DOM serveur contient les données nécessaires ;
4. basculer vers Browser/Playwright uniquement lorsque les offres dépendent réellement de JavaScript ;
5. ne jamais utiliser Browser pour contourner une authentification, un CAPTCHA ou une restriction d’accès.

`HTTP` peut être forcé pour un catalogue HTML classique. `BROWSER` peut être forcé lorsqu’un site public autorisé dépend systématiquement d’un rendu JavaScript.

## Limites du registre initial

Le registre persistant et son API n’exécutent pas encore eux-mêmes de requête vers les sites personnalisés. L’exécution sera branchée progressivement sur le transport HTTP contrôlé, puis sur l’analyse DOM/Gemini et enfin sur un worker Browser/Playwright isolé.

Cette séparation permet de livrer et tester le modèle d’autorisation avant toute collecte réelle.

## Sécurité

Le registre accepte uniquement des URL HTTPS sans identifiants intégrés. L’URL d’exemple de détail doit utiliser le même domaine que la liste des offres.

Le moteur d’exécution devra en plus conserver les protections déjà utilisées par JobPilot : résolution réseau sûre, blocage des adresses privées, contrôle `robots.txt` lorsqu’il s’applique, quotas, délais, timeout, redirections bornées, backoff, circuit breaker et absence de dépendance au site réel dans la CI.
