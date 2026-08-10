# Client API du Browser Worker

Cette tranche prépare l’appel Symfony vers le worker Playwright sans encore rendre les sources Browser synchronisables.

## Configuration

Le client est désactivé tant que les deux variables suivantes ne sont pas présentes :

- `BROWSER_WORKER_URL` ;
- `JOBPILOT_BROWSER_WORKER_TOKEN` avec au moins 24 caractères.

Ces valeurs sont des paramètres d’infrastructure JobPilot. Elles ne viennent jamais de la configuration d’une source utilisateur.

## Contrat

`BrowserRenderClientInterface::render()` reçoit :

- le code interne de la source ;
- l’URL publique à rendre ;
- le domaine autorisé ;
- la confirmation que l’autorisation de collecte a déjà été validée ;
- la confirmation que `robots.txt` a déjà été validé.

Le client refuse d’appeler le worker si une des deux confirmations est absente.

## Validation côté API

Même si le worker possède ses propres garde-fous, Symfony revalide la réponse :

- URL demandée HTTPS sur le domaine exact ;
- URL finale HTTPS sur le même domaine ;
- aucun identifiant ou fragment dans les URLs ;
- HTML non vide et inférieur ou égal à 3 Mo ;
- taille déclarée cohérente avec le contenu effectivement reçu.

Le client transmet un timeout de navigation de 10 secondes, un délai de stabilisation de 800 ms et la limite de 3 Mo.

## État de livraison

Le client et ses tests sont disponibles mais ne sont pas encore appelés par `CustomScraperExtractionService`. Une source forcée `BROWSER` reste donc non synchronisable dans cette tranche.

La prochaine étape doit connecter le client uniquement après les mêmes contrôles d’autorisation/robots que le transport HTTP, puis envoyer le HTML rendu dans les extracteurs et garde-fous déjà utilisés par le chemin HTTP.
