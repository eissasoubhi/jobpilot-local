# JobPilot Autofill — modèle de sécurité

## Accès aux pages

JobPilot n’a pas d’accès permanent à toutes les pages visitées.

- Le manifeste ne déclare pas `<all_urls>`.
- Aucun `content_script` n’est injecté automatiquement au chargement d’un site.
- Les permissions permanentes de contenu sont limitées à l’API locale JobPilot sur `localhost:8080` et `127.0.0.1:8080`.
- Une page de candidature est accessible temporairement grâce à `activeTab`, uniquement après un clic explicite de l’utilisateur sur **Importer cette offre** ou **Remplir avec JobPilot**.
- `chrome.scripting.executeScript` injecte seulement le bundle nécessaire à l’action choisie.
- Les pages internes du navigateur et les URL non HTTP/HTTPS sont refusées proprement.

## Données conservées dans le navigateur

Le profil candidat complet n’est jamais persisté dans le stockage de l’extension.

Le contexte temporaire d’un onglet contient uniquement :

- l’identifiant de l’offre JobPilot ;
- l’URL source ;
- un timestamp.

Ce contexte utilise `chrome.storage.session`. Si cette API n’est pas disponible, JobPilot utilise uniquement une `Map` en mémoire du service worker. Aucun fallback vers `chrome.storage.local` n’est autorisé. Le contexte est supprimé à la fermeture de l’onglet.

## Remplissage des formulaires

Le moteur conserve les règles suivantes :

- ne pas écraser une valeur déjà saisie par l’utilisateur ;
- laisser les classifications ambiguës en revue ;
- ne pas remplir les champs cachés, mots de passe, boutons ou contrôles de soumission ;
- ne pas choisir arbitrairement une option si plusieurs valeurs correspondent ;
- ne jamais cliquer sur **Envoyer** et ne jamais soumettre automatiquement une candidature.

## Données sensibles

Les réponses sensibles restent soumises à validation explicite. Les corrections apprises excluent les données `screening.*`, la rémunération et les textes libres.

Les suggestions IA ne répondent pas aux catégories sensibles ou légales configurées comme manuelles et ne sont jamais insérées dans le formulaire sans clic explicite sur **Insérer**.

## Documents

Les documents sont récupérés uniquement depuis des chemins locaux `/api/...` servis par JobPilot. Une réponse documentaire est limitée à 10 Mo et un fichier déjà sélectionné par l’utilisateur n’est pas remplacé.

## Régression de sécurité

Toute évolution de l’extension doit conserver les invariants suivants :

1. aucun `<all_urls>` dans le manifeste ;
2. aucun `content_scripts` permanent ;
3. aucune utilisation de `chrome.storage.local` pour le contexte candidat ;
4. injection de page uniquement après action utilisateur ;
5. aucun auto-submit.
