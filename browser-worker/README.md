# JobPilot Browser Worker

Worker Playwright isolé destiné uniquement au rendu de pages publiques autorisées qui nécessitent JavaScript.

## Responsabilité

Le worker ne recherche pas les offres et ne décide pas si un site est autorisé. L’API JobPilot doit avoir vérifié l’autorisation utilisateur et `robots.txt` avant de lui envoyer une URL.

Il effectue seulement :

1. une navigation HTTPS vers le domaine autorisé ;
2. un rendu JavaScript borné ;
3. le retour du HTML final à l’API ;
4. aucune persistance locale du HTML.

## Garde-fous

- token Bearer interne obligatoire sur `/render` ;
- confirmation `authorizationApproved=true` et `robotsApproved=true` obligatoire ;
- URL principale HTTPS et exactement sur `allowedDomain` ;
- blocage des hôtes locaux, IP privées, link-local, multicast et plages réservées ;
- contrôle DNS également sur les sous-requêtes du navigateur ;
- navigation principale hors domaine bloquée ;
- méthodes autres que GET/HEAD bloquées ;
- images, vidéo/audio et polices bloquées ;
- service workers bloqués ;
- téléchargements annulés ;
- dialogues automatiquement fermés ;
- aucun cookie, session, identifiant ou stockage utilisateur injecté ;
- aucun clic, formulaire ou automatisation de connexion ;
- aucun mode stealth, proxy de contournement ou CAPTCHA bypass ;
- timeout maximal de navigation : 15 s ;
- délai de stabilisation maximal : 2 s ;
- HTML rendu maximal : 3 Mo.

Le worker est volontairement read-only. Certaines applications qui chargent leurs offres uniquement via une requête POST JavaScript ne fonctionneront donc pas dans cette version ; elles devront faire l’objet d’une stratégie explicitement auditée plutôt que d’autoriser globalement les requêtes d’écriture.

## Docker Compose

Le `docker-compose.yml` principal construit et démarre automatiquement `browser-worker`. Le service :

- n’expose aucun port sur la machine hôte ;
- est joignable uniquement par les autres services Compose via `http://browser-worker:3100` ;
- s’exécute avec un utilisateur non-root ;
- utilise un système de fichiers root read-only et un `/tmp` temporaire ;
- abandonne toutes les capabilities Linux et active `no-new-privileges` ;
- limite les processus et la mémoire partagée utilisée par Chromium ;
- possède un healthcheck `/health`.

`api` et `scheduler` reçoivent automatiquement :

```text
BROWSER_WORKER_URL=http://browser-worker:3100
JOBPILOT_BROWSER_WORKER_TOKEN=<token partagé>
```

Le token de développement fourni par défaut dans Compose ne doit pas être réutilisé hors environnement local. Pour un environnement partagé ou déployé, définir une valeur aléatoire d’au moins 24 caractères :

```bash
export JOBPILOT_BROWSER_WORKER_TOKEN='replace-with-a-long-random-token'
docker compose up --build
```

Le navigateur n’est pas une frontière de confiance à lui seul : l’isolation du conteneur complète les contrôles réseau/URL appliqués dans `policy.mjs` et les préflights effectués par l’API.

## Lancer sans Docker

```bash
cd browser-worker
npm install
npx playwright install --with-deps chromium
export JOBPILOT_BROWSER_WORKER_TOKEN='replace-with-a-long-random-token'
npm start
```

Le service écoute par défaut sur `0.0.0.0:3100`.

## API

`GET /health` renvoie uniquement l’état du worker.

`POST /render` exige `Authorization: Bearer <token>` et un JSON semblable à :

```json
{
  "sourceCode": "custom-scraper-12",
  "url": "https://jobs.example.com/offres",
  "allowedDomain": "jobs.example.com",
  "authorizationApproved": true,
  "robotsApproved": true,
  "timeoutMs": 10000,
  "settleMs": 800,
  "maxHtmlBytes": 3000000
}
```

La réponse contient l’URL finale, le statut de navigation, le titre, le HTML rendu, sa taille et les nombres de requêtes autorisées/bloquées. L’API JobPilot reste responsable de l’extraction DOM, du score de qualité, de la déduplication et du matching.

## Tests

Les tests de politique n’ouvrent aucun navigateur et n’accèdent à aucun site réel :

```bash
npm test
```

Le workflow `.github/workflows/browser-worker.yml` exécute ces tests puis construit l’image Docker lorsque les fichiers du worker ou de Compose changent.
